<?php
namespace Admidio\SSO\Entity;

use Admidio\Changelog\Entity\LogChanges;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Users\Entity\User;

class OIDCConsent extends Entity
{
    /**
     * User and client of this consent, each read only once for the changelog entries of one save
     * operation. adjustLogEntry() is called for every changed column.
     * @var User|null
     */
    private ?User $logUser = null;
    /**
     * @var OIDCClient|null
     */
    private ?OIDCClient $logClient = null;

    public function __construct(Database $database, int $id = 0)
    {
        parent::__construct($database, TBL_OIDC_CONSENTS, 'oco', $id);
    }

    public function readDataByUserAndClient(int $organizationID, int $userID, int $clientID): bool
    {
        return $this->readDataByColumns(
            array(
                'oco_org_id' => $organizationID,
                'oco_usr_id' => $userID,
                'oco_ocl_id' => $clientID
            )
        );
    }

    public function coversScopes(array $scopes): bool
    {
        $storedScopes = preg_split('/\s+/', trim($this->getValue('oco_scopes')));

        return count(array_diff($scopes, $storedScopes)) === 0;
    }

    /**
     * Whether this consent was given for the claim release policy of this fingerprint.
     *
     * The scopes alone do not describe what a client receives, because the claim mapping of
     * the client decides which profile fields a scope releases. A consent that was stored
     * before the fingerprint existed does not describe a policy at all and never matches, so
     * the user is asked once more.
     */
    public function matchesReleasePolicy(string $policyHash): bool
    {
        $storedHash = (string) $this->getValue('oco_policy_hash');

        return $storedHash !== '' && hash_equals($storedHash, $policyHash);
    }

    /**
     * A consent is named after the user who granted it.
     *
     * The table has no name column of its own, so the default implementation would fall back to
     * the numeric consent id, which tells a reader of the changelog nothing.
     *
     * @return string The name of the user this consent belongs to
     * @throws \Exception
     */
    public function readableName(): string
    {
        return $this->readLogUser()->readableName();
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     *
     * The user and the client are the record and the related object of the entry, and the
     * organization is the one the entry is filed under, so logging the three foreign keys as
     * changed values would only repeat what the entry already shows. The policy hash is an
     * internal checksum over the claim mapping of the client: it changes whenever that mapping
     * changes, means nothing to a reader, and the mapping change is already logged on the client.
     * What remains is oco_scopes, the access the user actually approved.
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        $ignored = parent::getIgnoredLogColumns();
        $ignored[] = 'oco_org_id';
        $ignored[] = 'oco_ocl_id';
        $ignored[] = 'oco_policy_hash';
        return $ignored;
    }

    /**
     * Adjust the changelog entry for this db record.
     *
     * Granting a client access is a decision of the user, and the consent has no edit page of its
     * own, so the entry is named after and linked to the user, and names the client as the
     * related object.
     *
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws \Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        $user = $this->readLogUser();
        $client = $this->readLogClient();

        $logEntry->setValue('log_record_name', $user->readableName());
        $logEntry->setValue('log_record_uuid', $user->getValue('usr_uuid'));
        $logEntry->setLogLinkID((int) $user->getValue('usr_id'));

        $logEntry->setLogRelated(
            (string) $client->getValue('ocl_uuid'),
            $client->readableName()
        );
    }

    /**
     * The user of this consent, read only once for all changelog entries of one save operation.
     *
     * @return User The user who granted this consent
     * @throws \Exception
     */
    private function readLogUser(): User
    {
        global $gProfileFields;

        $usrId = (int) $this->getValue('oco_usr_id');

        if ($this->logUser === null || (int) $this->logUser->getValue('usr_id') !== $usrId) {
            $this->logUser = new User($this->db, $gProfileFields, $usrId);
        }

        return $this->logUser;
    }

    /**
     * The client of this consent, read only once for all changelog entries of one save operation.
     *
     * @return OIDCClient The client this consent was granted for
     * @throws \Exception
     */
    private function readLogClient(): OIDCClient
    {
        $clientId = (int) $this->getValue('oco_ocl_id');

        if ($this->logClient === null || (int) $this->logClient->getValue('ocl_id') !== $clientId) {
            $this->logClient = new OIDCClient($this->db, $clientId);
        }

        return $this->logClient;
    }
}