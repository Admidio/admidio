<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\SSO\Entity\OIDCClient;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Psr\Http\Message\ResponseInterface;

class OIDCLogoutNotificationService
{
    private const BACKCHANNEL_LOGOUT_EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    private OIDCSessionParticipantService $participantService;

    public function __construct(private Database $database, private string $issuerURL)
    {
        $this->participantService = new OIDCSessionParticipantService($database);
    }

    /**
     * Send logout notifications to all participants of the OP session.
     *
     * @return array<int,string> Front-channel logout iframe URLs.
     */
    public function notifySession(string $externalSessionId, bool $includeFrontChannel = true): array 
    {
        $frontChannelUris = array();

        foreach ($this->participantService->getParticipants($externalSessionId) as $participant) {
            $clientId = (int) ($participant['osp_client_id'] ?? 0);
            if ($clientId <= 0) {
                continue;
            }

            $client = new OIDCClient($this->database, $clientId);
            if ($client->isNewRecord() || !$client->isEnabled()) {
                continue;
            }

            if ($client->getBackChannelLogoutUri() !== '') {
                $this->sendBackChannelLogout($client, $externalSessionId, (string) ($participant['osp_subject'] ?? ''));
            }

            if (!$includeFrontChannel) {
                continue;
            }

            $frontChannelUri = $client->getFrontChannelLogoutUri();
            if ($frontChannelUri === '') {
                continue;
            }

            if ($client->isFrontChannelLogoutSessionRequired()) {
                $frontChannelUri .= (str_contains($frontChannelUri, '?') ? '&' : '?')
                    . http_build_query(array(
                        'iss' => $this->issuerURL,
                        'sid' => $externalSessionId
                    ));
            }

            $frontChannelUris[] = $frontChannelUri;
        }

        return array_values(array_unique($frontChannelUris));
    }


    /**
     * Render hidden front-channel logout iframes and continue afterwards.
     *
     * @param array<int,string> $logoutUris
     */
    public function createFrontChannelResponse(
        array $logoutUris,
        ?string $redirectLocation
    ): ResponseInterface {
        $frames = '';
        foreach ($logoutUris as $logoutUri) {
            $frames .= '<iframe hidden src="'
                . htmlspecialchars($logoutUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"></iframe>';
        }

        $continueScript = '';
        if ($redirectLocation !== null) {
            $continueScript = '<script>'
                . 'window.setTimeout(function(){window.location.replace('
                . json_encode($redirectLocation, JSON_THROW_ON_ERROR)
                . ');},3000);'
                . '</script>';
        }

        $body = new Stream(fopen('php://temp', 'r+'));
        $body->write(
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="referrer" content="no-referrer">'
            . '<title>Logout</title></head><body>'
            . $frames
            . $continueScript
            . '</body></html>'
        );

        return (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache')
            ->withBody($body);
    }


    private function sendBackChannelLogout(OIDCClient $client, string $externalSessionId, string $subject): void 
    {
        global $gLogger;

        try {
            $logoutToken = $this->createBackChannelLogoutToken($client, $externalSessionId, $subject);

            $curl = curl_init($client->getBackChannelLogoutUri());
            if ($curl === false) {
                throw new \RuntimeException('Could not initialize the back-channel HTTP request.');
            }

            curl_setopt_array($curl, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(array('logout_token' => $logoutToken)),
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false
            ));

            $result = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($result === false || $statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException('HTTP status ' . $statusCode . ($error === '' ? '' : ': ' . $error));
            }
        } catch (\Throwable $exception) {
            $gLogger->warning(
                'OIDC back-channel logout failed for client "'
                . $client->getIdentifier() . '".',
                array(
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage()
                )
            );
        }
    }

    private function createBackChannelLogoutToken(
        OIDCClient $client,
        string $externalSessionId,
        string $subject
    ): string {
        global $gSettingsManager;

        $keyService = new KeyService($this->database);
        $key = $keyService->getUsableKey(
            (int) $gSettingsManager->get('sso_oidc_signing_key'),
            KeyService::USAGE_OIDC_SIGNING
        );

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText((string) $key->getValue('key_private')),
            InMemory::plainText((string) $key->getValue('key_public'))
        );

        $builder = $configuration->builder()
            ->issuedBy($this->issuerURL)
            ->permittedFor($client->getIdentifier())
            ->issuedAt(new \DateTimeImmutable())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->withClaim('events', array(
                self::BACKCHANNEL_LOGOUT_EVENT => new \stdClass()
            ));

        if ($client->isBackChannelLogoutSessionRequired()) {
            $builder = $builder->withClaim('sid', $externalSessionId);
        } else {
            $builder = $builder->relatedTo($subject);
        }

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
