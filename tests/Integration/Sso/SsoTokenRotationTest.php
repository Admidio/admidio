<?php
/**
 * Regression coverage for the link between the tokens and the authorization code they stem from.
 *
 * A code that is presented again may have been intercepted, so every token it produced has to be
 * revoked (RFC 6749, section 4.1.2). Only the tokens of the code exchange itself were linked to the
 * code; a client that had refreshed its tokens since held tokens that no longer led back to the
 * code and stayed valid. A replay after the code expired (league reports that first) and the purge
 * of the expired code row had the same effect. See OIDC-17.
 */

namespace Admidio\Tests\Integration\Sso;

use League\OAuth2\Server\Exception\OAuthServerException;

class SsoTokenRotationTest extends SsoTokenGrantTestCase
{
    /**
     * @testdox A refresh gives the new tokens the authorization code of the refresh token
     */
    public function testARefreshKeepsTheCodeLink(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [$codeId, , $refreshTokenId] = $this->exchangeCode($client);

        [$newAccessTokenId, $newRefreshTokenId] = $this->refresh($client, $refreshTokenId)->tokenIdentifiers();

        $codeRowId = $this->codeRowId($codeId);
        $this->assertSame($codeRowId, $this->linkOf(TBL_OIDC_ACCESS_TOKENS, 'oat', $newAccessTokenId));
        $this->assertSame($codeRowId, $this->linkOf(TBL_OIDC_REFRESH_TOKENS, 'ort', $newRefreshTokenId));
    }

    /**
     * @testdox A reused code revokes the tokens of every refresh since its exchange
     */
    public function testAReusedCodeRevokesTheRotatedTokens(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [$codeId, , $refreshTokenId] = $this->exchangeCode($client);
        [$secondAccessTokenId, $secondRefreshTokenId] = $this->refresh($client, $refreshTokenId)->tokenIdentifiers();
        [$thirdAccessTokenId, $thirdRefreshTokenId] = $this->refresh($client, $secondRefreshTokenId)->tokenIdentifiers();

        $this->assertFalse($this->accessTokenRepository->isAccessTokenRevoked($thirdAccessTokenId));
        $this->assertFalse($this->refreshTokenRepository->isRefreshTokenRevoked($thirdRefreshTokenId));

        $this->authCodeRepository->revokeAuthCode($codeId);
        $this->assertTrue($this->authCodeRepository->isAuthCodeRevoked($codeId));

        foreach (array($secondAccessTokenId, $thirdAccessTokenId) as $accessTokenId) {
            $this->assertTrue($this->accessTokenRepository->isAccessTokenRevoked($accessTokenId));
        }
        foreach (array($secondRefreshTokenId, $thirdRefreshTokenId) as $refreshTokenId) {
            $this->assertTrue($this->refreshTokenRepository->isRefreshTokenRevoked($refreshTokenId));
        }
    }

    /**
     * @testdox A refresh token from before the link existed produces tokens without a code
     */
    public function testARefreshTokenWithoutACodeHandsOnNoLink(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->exchangeCode($client);
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_REFRESH_TOKENS . ' SET ort_oac_id = NULL WHERE ort_token = ?',
            array(hash('sha256', $refreshTokenId))
        );

        [$newAccessTokenId, $newRefreshTokenId] = $this->refresh($client, $refreshTokenId)->tokenIdentifiers();

        $this->assertNull($this->linkOf(TBL_OIDC_ACCESS_TOKENS, 'oat', $newAccessTokenId));
        $this->assertNull($this->linkOf(TBL_OIDC_REFRESH_TOKENS, 'ort', $newRefreshTokenId));
        $this->assertFalse($this->refreshTokenRepository->isRefreshTokenRevoked($newRefreshTokenId));
    }

    /**
     * @testdox An expired authorization code is kept while tokens are linked to it
     */
    public function testAnExpiredCodeIsKeptWhileItsTokensLive(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [$codeId, $accessTokenId] = $this->exchangeCode($client);
        $unlinkedCodeId = $this->issueAuthCode($client);
        $this->expireCode($codeId);
        $this->expireCode($unlinkedCodeId);

        // Persisting any code purges the expired ones
        $this->issueAuthCode($client);

        $this->assertFalse($this->authCodeRepository->getToken($codeId)->isNewRecord(), 'The code of live tokens must stay.');
        $this->assertTrue($this->authCodeRepository->getToken($unlinkedCodeId)->isNewRecord(), 'A code without tokens is purged.');

        // Once the tokens are gone, the code can go as well
        $this->getDatabase()->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_ACCESS_TOKENS . ' WHERE oat_token = ?',
            array(hash('sha256', $accessTokenId))
        );
        $this->getDatabase()->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_REFRESH_TOKENS . ' WHERE ort_oac_id = ?',
            array($this->codeRowId($codeId))
        );
        $this->issueAuthCode($client);
        $this->assertTrue($this->authCodeRepository->getToken($codeId)->isNewRecord());
    }

    /**
     * league answers an expired code with "expired" before it looks at whether the code was used,
     * so presenting an exchanged code after its lifetime revoked nothing.
     *
     * @testdox An exchanged code that is presented after it expired still revokes its tokens
     */
    public function testAReplayAfterExpiryRevokesTheTokens(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [$codeId, $accessTokenId, $refreshTokenId] = $this->exchangeCode($client);
        $this->authCodeRepository->revokeAuthCode($codeId);
        $this->assertFalse($this->accessTokenRepository->isAccessTokenRevoked($accessTokenId));

        try {
            $this->presentCode($client, $codeId, time() - 60);
            $this->fail('The expired code should have been refused.');
        } catch (OAuthServerException $exception) {
            $this->assertSame('invalid_grant', $exception->getErrorType());
        }

        $this->assertTrue($this->accessTokenRepository->isAccessTokenRevoked($accessTokenId));
        $this->assertTrue($this->refreshTokenRepository->isRefreshTokenRevoked($refreshTokenId));
    }

    /**
     * @testdox An expired code that was never exchanged revokes nothing of other codes
     */
    public function testAnUnusedExpiredCodeRevokesNothing(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, $otherAccessTokenId] = $this->exchangeCode($client);
        $codeId = $this->issueAuthCode($client);

        try {
            $this->presentCode($client, $codeId, time() - 60);
            $this->fail('The expired code should have been refused.');
        } catch (OAuthServerException $exception) {
            $this->assertSame('invalid_grant', $exception->getErrorType());
        }

        $this->assertFalse($this->accessTokenRepository->isAccessTokenRevoked($otherAccessTokenId));
    }
}
