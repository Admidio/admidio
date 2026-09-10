<?php
/**
 * Regression coverage for the state that carries a SAML logout across the browser hops.
 *
 * A single logout visits one service provider after the other through the browser, so everything
 * the chain needs has to survive in the database and may only be addressable through the opaque
 * RelayState token. See findings 37 and 47.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\SAMLLogoutTransaction;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoLogoutTransactionTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox A logout transaction survives the browser hop through its RelayState token
     */
    public function testATransactionIsReadBackThroughItsToken(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();
        $client = $this->createSamlClient($suffix);
        $clientId = (int) $client->getValue('smc_id');

        $pendingClients = array(
            array(
                'participantId' => 17,
                'clientId' => $clientId,
                'nameId' => 'name-' . $suffix,
                'nameIdFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
                'nameIdSPNameQualifier' => null,
                'sessionIndex' => 'session-' . $suffix
            )
        );

        $transaction = new SAMLLogoutTransaction($this->getDatabase());
        $transaction->initialize((int) $gCurrentOrgId, $clientId, 42, 'request-' . $suffix,
            'relay-' . $suffix, $pendingClients,
            array('type' => SAMLLogoutTransaction::COMPLETION_REDIRECT, 'url' => 'https://admidio.test/logout'));
        $transaction->save();

        $token = (string) $transaction->getValue('slt_token');
        $this->assertSame(64, strlen($token));

        $reloaded = new SAMLLogoutTransaction($this->getDatabase());
        $this->assertTrue($reloaded->readDataByToken($token));

        $this->assertSame($clientId, $reloaded->getInitiatorClientId());
        $this->assertSame(42, $reloaded->getInitiatorParticipantId());
        $this->assertSame('request-' . $suffix, $reloaded->getInitiatorRequestId());
        $this->assertSame('relay-' . $suffix, $reloaded->getInitiatorRelayState());
        $this->assertSame(SAMLLogoutTransaction::COMPLETION_REDIRECT, $reloaded->getCompletionType());
        $this->assertSame('https://admidio.test/logout', $reloaded->getCompletionUrl());
        $this->assertSame($pendingClients, $reloaded->getPendingClients());
        $this->assertFalse($reloaded->isExpired());
        $this->assertFalse($reloaded->hasPartialLogout());
    }

    /**
     * A logout that the user started inside Admidio has no initiating service provider. That was
     * the missing piece of finding 37, so the transaction has to accept it.
     *
     * @testdox A transaction without an initiating service provider is valid
     */
    public function testATransactionWithoutAnInitiatorIsValid(): void
    {
        global $gCurrentOrgId;

        $transaction = new SAMLLogoutTransaction($this->getDatabase());
        $transaction->initialize((int) $gCurrentOrgId, 0, null, '', null, array(),
            array('type' => SAMLLogoutTransaction::COMPLETION_REDIRECT, 'url' => 'https://admidio.test/'));
        $transaction->save();

        $reloaded = new SAMLLogoutTransaction($this->getDatabase());
        $this->assertTrue($reloaded->readDataByToken((string) $transaction->getValue('slt_token')));

        $this->assertSame(0, $reloaded->getInitiatorClientId());
        $this->assertSame(0, $reloaded->getInitiatorParticipantId());
        $this->assertSame('', $reloaded->getInitiatorRequestId());
        $this->assertSame('', $reloaded->getInitiatorRelayState());
        $this->assertSame(array(), $reloaded->getPendingClients());
        $this->assertSame('https://admidio.test/', $reloaded->getCompletionUrl());
    }

    /**
     * @testdox The progress of a logout chain is written back for the next hop
     */
    public function testTheProgressOfTheChainIsPersisted(): void
    {
        global $gCurrentOrgId;

        $suffix = $this->suffix();

        $transaction = new SAMLLogoutTransaction($this->getDatabase());
        $transaction->initialize((int) $gCurrentOrgId, 0, null, '', null,
            array(array('participantId' => 1, 'clientId' => 2), array('participantId' => 3, 'clientId' => 4)));
        $transaction->save();
        $token = (string) $transaction->getValue('slt_token');

        // One service provider is contacted, so it leaves the pending list and becomes the current one.
        $pending = $transaction->getPendingClients();
        $current = array_shift($pending);
        $transaction->setPendingClients($pending);
        $transaction->setCurrentRequest((int) $current['participantId'], (int) $current['clientId'], 'req-' . $suffix);
        $transaction->setPartialLogout(true);
        $transaction->save();

        $reloaded = new SAMLLogoutTransaction($this->getDatabase());
        $reloaded->readDataByToken($token);

        $this->assertCount(1, $reloaded->getPendingClients());
        $this->assertSame(1, $reloaded->getCurrentParticipantId());
        $this->assertSame(2, $reloaded->getCurrentClientId());
        $this->assertSame('req-' . $suffix, $reloaded->getCurrentRequestId());
        $this->assertTrue($reloaded->hasPartialLogout());
    }

    /**
     * @testdox An unknown or expired RelayState token does not open a transaction
     */
    public function testAnUnknownOrExpiredTokenIsRefused(): void
    {
        global $gCurrentOrgId;

        $unknown = new SAMLLogoutTransaction($this->getDatabase());
        $this->assertFalse($unknown->readDataByToken(bin2hex(random_bytes(32))));

        $transaction = new SAMLLogoutTransaction($this->getDatabase());
        $transaction->initialize((int) $gCurrentOrgId, 0, null, '', null, array());
        $transaction->save();
        $token = (string) $transaction->getValue('slt_token');

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_SAML_LOGOUT_TRANSACTIONS . ' SET slt_expires_at = ? WHERE slt_token = ?',
            array(date('Y-m-d H:i:s', strtotime('-1 hour')), $token)
        );

        $expired = new SAMLLogoutTransaction($this->getDatabase());
        $this->assertTrue($expired->readDataByToken($token));
        $this->assertTrue($expired->isExpired());
    }

    /**
     * @testdox Two logout transactions never share their token
     */
    public function testTheTokensAreDistinct(): void
    {
        global $gCurrentOrgId;

        $tokens = array();
        for ($run = 0; $run < 5; $run++) {
            $transaction = new SAMLLogoutTransaction($this->getDatabase());
            $transaction->initialize((int) $gCurrentOrgId, 0, null, '', null, array());
            $transaction->save();
            $tokens[] = (string) $transaction->getValue('slt_token');
        }

        $this->assertCount(5, array_unique($tokens));
    }
}
