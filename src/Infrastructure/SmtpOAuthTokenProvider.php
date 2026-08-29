<?php

namespace Admidio\Infrastructure;

use RuntimeException;
use PHPMailer\PHPMailer\OAuthTokenProvider;

/**
 * Retrieves access tokens for SMTP XOAUTH2 authentication.
 *
 * The OAuth 2.0 client credentials and refresh token grants are deliberately
 * implemented directly. This avoids coupling SMTP delivery to a provider
 * specific OAuth SDK and works with any standards-compliant token endpoint.
 */
final class SmtpOAuthTokenProvider implements OAuthTokenProvider
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $scope,
        private readonly string $grantType,
        private readonly string $refreshToken,
        private readonly string $userName
    ) {
    }

    /**
     * Generate the base64 encoded XOAUTH2 string expected by PHPMailer.
     * @return string
     */
    public function getOauth64(): string
    {
        if ($this->accessToken === null || time() >= $this->accessTokenExpiresAt) {
            $this->requestAccessToken();
        }

        return base64_encode(
            'user=' . $this->userName . "\001auth=Bearer " . $this->accessToken . "\001\001"
        );
    }

    /**
     * Request and cache an access token from a standards-compliant OAuth 2.0 endpoint.
     * @return void
     */
    private function requestAccessToken(): void
    {
        if ($this->tokenUrl === '' || $this->clientId === '' || $this->clientSecret === '' || $this->userName === '') {
            throw new RuntimeException('SMTP OAuth configuration is incomplete.');
        }
        if (filter_var($this->tokenUrl, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($this->tokenUrl, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('The SMTP OAuth token endpoint must use HTTPS.');
        }
        if (!in_array($this->grantType, array('client_credentials', 'refresh_token'), true)) {
            throw new RuntimeException('The SMTP OAuth grant type is invalid.');
        }

        $parameters = array(
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        );

        if ($this->scope !== '') {
            $parameters['scope'] = $this->scope;
        }
        if ($this->grantType === 'refresh_token') {
            if ($this->refreshToken === '') {
                throw new RuntimeException('An OAuth refresh token is required for SMTP authentication.');
            }
            $parameters['refresh_token'] = $this->refreshToken;
        }

        $curl = curl_init($this->tokenUrl);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize the SMTP OAuth token request.');
        }

        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ));

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('SMTP OAuth token request failed: ' . $error);
        }

        $tokenData = json_decode($response, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($tokenData) || empty($tokenData['access_token'])) {
            $oauthError = is_array($tokenData) && isset($tokenData['error']) ? (string) $tokenData['error'] : 'invalid response';
            throw new RuntimeException('SMTP OAuth token request failed (HTTP ' . $statusCode . '): ' . $oauthError);
        }

        $this->accessToken = (string) $tokenData['access_token'];
        $this->accessTokenExpiresAt = time() + max(1, (int) ($tokenData['expires_in'] ?? 300) - 60);
    }
}
