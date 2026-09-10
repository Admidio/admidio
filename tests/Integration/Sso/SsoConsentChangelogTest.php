<?php
/**
 * Regression coverage for the changelog entries of the SSO tables.
 *
 * Granting a client access to an account is a decision of the user and belongs in the audit
 * trail, while the tokens and the session/logout state of the OIDC and SAML flows are written
 * by the protocol itself and only drown the entries that carry meaning. These tests pin both
 * halves of that, and the shape of a consent entry: it is named after the client, names the
 * consenting user as the related object, and never stores the internal policy checksum.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\SSO\Entity\OIDCConsent;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Users\Entity\User;

class SsoConsentChangelogTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite installs the schema with the changelog switched off; a test that wants the
        // trail has to switch it back on. See tests/README.md.
        Entity::setLoggingEnabled(true);
    }

    protected function tearDown(): void
    {
        // put the flag back the way the rest of the suite found it
        Entity::setLoggingEnabled(false);

        parent::tearDown();
    }

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Save the consent with the changelog of its table switched on.
     *
     * Whether a table is logged is an organization preference, and `changelog_table_oidc_consents`
     * is off by default like the other SSO tables. The preference is restored afterwards so that
     * the setting does not leak into the following test.
     *
     * @return array The log entries of adm_oidc_consents that the callback produced
     */
    private function loggingConsents(callable $callback): array
    {
        $settings = $GLOBALS['gSettingsManager'];
        $previous = $settings->getBool('changelog_table_oidc_consents');
        $settings->set('changelog_table_oidc_consents', 1);

        try {
            $before = $this->lastLogId();
            $callback();

            return $this->logEntriesSince($before, 'oidc_consents');
        } finally {
            $settings->set('changelog_table_oidc_consents', $previous ? 1 : 0);
        }
    }

    /**
     * The id of the newest log entry, to tell the entries of one action from the ones before it.
     */
    private function lastLogId(): int
    {
        return (int) $this->getDatabase()
            ->queryPrepared('SELECT COALESCE(MAX(log_id), 0) FROM ' . TBL_LOG_CHANGES)
            ->fetchColumn();
    }

    /**
     * The log entries of one table that were written after the given id.
     */
    private function logEntriesSince(int $logId, string $table): array
    {
        $sql = 'SELECT log_table, log_record_id, log_record_name, log_record_uuid, log_record_linkid,
                       log_related_id, log_related_name, log_field, log_action
                  FROM ' . TBL_LOG_CHANGES . '
                 WHERE log_id > ? AND log_table = ?
              ORDER BY log_id';

        return $this->getDatabase()->queryPrepared($sql, array($logId, $table))->fetchAll();
    }

    /**
     * Store a consent of a freshly created user for a freshly created client.
     *
     * @return array{consent: OIDCConsent, user: array, client: \Admidio\SSO\Entity\OIDCClient}
     */
    private function createConsent(string $scopes = 'openid profile'): array
    {
        global $gCurrentOrgId;

        global $gProfileFields;

        $suffix = $this->suffix();
        $user = $this->getFixture()->createAndSaveUser(
            'sso-log-' . $suffix,
            'sso-log-' . $suffix . '@example.local'
        );
        $client = $this->createRestrictedClient($suffix, array());

        // A name, so that the related object of the entry is asserted against something that a
        // reader would recognise instead of against the empty default of a bare fixture user.
        $named = new User($this->getDatabase(), $gProfileFields, (int) $user['usr_id']);
        $named->setValue('LAST_NAME', 'Consenter' . $suffix);
        $named->setValue('FIRST_NAME', 'Test');
        $named->save();
        $user['readable_name'] = $named->readableName();

        $consent = new OIDCConsent($this->getDatabase());
        $consent->setValue('oco_org_id', (int) $gCurrentOrgId);
        $consent->setValue('oco_usr_id', (int) $user['usr_id']);
        $consent->setValue('oco_ocl_id', (int) $client->getValue('ocl_id'));
        $consent->setValue('oco_scopes', $scopes);
        $consent->setValue('oco_policy_hash', str_repeat('b', 64));

        return array('consent' => $consent, 'user' => $user, 'client' => $client);
    }

    /**
     * @testdox A consent is logged and named after the user, with the client as related object
     */
    public function testAConsentIsLoggedWithTheUserAsNameAndTheClientAsRelatedObject(): void
    {
        $prepared = $this->createConsent();
        $client = $prepared['client'];
        $user = $prepared['user'];

        $entries = $this->loggingConsents(fn () => $prepared['consent']->save());
        $this->assertNotEmpty($entries, 'Granting a consent has to be logged.');

        foreach ($entries as $entry) {
            // The name must be the consenting user, never the numeric id of the consent record.
            $this->assertSame(
                $user['readable_name'],
                $entry['log_record_name'],
                'A consent entry is named after the user who granted it.'
            );
            $this->assertStringContainsString('Consenter', (string) $entry['log_record_name']);
            $this->assertSame($user['usr_uuid'], $entry['log_record_uuid']);
            $this->assertSame((int) $user['usr_id'], (int) $entry['log_record_linkid']);

            // The client the access was granted to is the related object.
            $this->assertSame($client->getValue('ocl_uuid'), $entry['log_related_id']);
            $this->assertSame($client->getValue('ocl_client_name'), $entry['log_related_name']);
        }
    }

    /**
     * The record name must never fall back to the numeric consent id, which is what the default
     * implementation of readableName() would return for a table without a name column.
     *
     * @testdox The name of a consent entry is never the numeric record id
     */
    public function testTheNameOfAConsentEntryIsNeverTheRecordId(): void
    {
        $prepared = $this->createConsent();

        $entries = $this->loggingConsents(fn () => $prepared['consent']->save());
        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $this->assertNotSame(
                (string) $entry['log_record_id'],
                (string) $entry['log_record_name'],
                'The name of the entry must not be the numeric id of the consent.'
            );
            $this->assertFalse(
                ctype_digit((string) $entry['log_record_name']),
                'The name of the entry must not be a bare number.'
            );
        }
    }

    /**
     * @testdox The internal policy checksum of a consent is never written to the changelog
     */
    public function testThePolicyHashIsNeverLogged(): void
    {
        $prepared = $this->createConsent();
        $consent = $prepared['consent'];

        $entries = $this->loggingConsents(function () use ($consent) {
            $consent->save();

            // Change the checksum on its own, the way a changed claim mapping of the client would.
            $consent->setValue('oco_policy_hash', str_repeat('c', 64));
            $consent->save();
        });

        $fields = array_column($entries, 'log_field');

        $this->assertNotContains(
            'oco_policy_hash',
            $fields,
            'The policy hash is an internal checksum and must stay out of the changelog.'
        );
        $this->assertContains('oco_scopes', $fields, 'The approved scopes are the point of the entry.');
    }

    /**
     * The entry already shows the user as its record and the client as the related object, and it
     * is filed under the organization, so logging the three foreign keys as changed values would
     * only repeat that. Membership does the same for mem_usr_id and mem_rol_id.
     *
     * @testdox The foreign keys of a consent are not repeated as changed values
     */
    public function testTheForeignKeysOfAConsentAreNotLoggedAsValues(): void
    {
        $prepared = $this->createConsent();

        $entries = $this->loggingConsents(fn () => $prepared['consent']->save());
        $fields = array_column($entries, 'log_field');

        foreach (array('oco_org_id', 'oco_ocl_id', 'oco_usr_id') as $column) {
            $this->assertNotContains(
                $column,
                $fields,
                $column . ' is shown by the entry itself and must not be logged as a value.'
            );
        }

        // The approved scopes are what is left, and they are the point of the entry.
        $this->assertContains('oco_scopes', $fields);
        $this->assertArrayHasKey('oco_scopes', ChangelogService::getFieldTranslations());
    }

    /**
     * @testdox The consent table is registered everywhere the changelog needs it
     */
    public function testTheConsentTableIsFullyRegistered(): void
    {
        $this->assertNotContains(
            'oidc_consents',
            ChangelogService::$noLogTables,
            'Consents are a user decision and stay in the audit trail.'
        );

        // Without the label the changelog cannot name the table, without the entity it cannot
        // resolve a record, and without the related table it cannot link the related object.
        $this->assertNotEmpty(ChangelogService::getTableLabel('oidc_consents'));
        $this->assertInstanceOf(OIDCConsent::class, ChangelogService::getObjectForTable('oidc_consents'));
        $this->assertSame('oidc_clients', ChangelogService::getRelatedTable('oidc_consents'));
    }

    /**
     * The runtime tables of the SSO flows are written and expired by the protocol itself. They
     * used to fill the changelog with entries that named a bare record id and had no data.
     *
     * @testdox The SSO session, logout and token tables are exempt from the changelog
     */
    public function testTheSsoRuntimeTablesAreExemptFromTheChangelog(): void
    {
        $exempt = array(
            'oidc_access_tokens',
            'oidc_refresh_tokens',
            'oidc_auth_codes',
            'oidc_session_participants',
            'saml_logout_transactions',
            'saml_session_participants',
        );

        foreach ($exempt as $table) {
            $this->assertContains(
                $table,
                ChangelogService::$noLogTables,
                $table . ' is runtime bookkeeping and must not be logged.'
            );
            $this->assertFalse(
                ChangelogService::isTableLogged($table),
                $table . ' must stay out of the changelog even when logging is switched on.'
            );
        }
    }
}
