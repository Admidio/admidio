<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLSessionParticipant;

class SAMLSessionParticipantService
{
    /** Sign-ins of one session at one client that are remembered for the logout */
    private const MAX_PARTICIPANTS_PER_CLIENT = 10;

    public function __construct(private Database $database)
    {
    }

    /**
     * Persist the participant for one SAML sign-in of an Admidio session at a SAML client.
     *
     * Every sign-in is a session of its own at the service provider and has its own SessionIndex, so a
     * second sign-in of the same Admidio session at the same client does not replace the first one: the
     * logout has to address both. The oldest ones beyond a limit are dropped, so that a service provider
     * that sends the browser to the sign-in again and again cannot fill the table.
     *
     * @throws Exception
     */
    public function persistParticipant(
        int $organizationId,
        int $userId,
        string $externalSessionId,
        int $clientId,
        string $nameID,
        string $nameIDFormat,
        ?string $nameIDSPNameQualifier,
        string $sessionIndex,
        \DateTimeInterface $authnInstant,
        \DateTimeInterface $expiresAt
    ): void {
        $participant = new SAMLSessionParticipant($this->database);
        $participant->setParticipantData(
            $organizationId,
            $userId,
            $externalSessionId,
            $clientId,
            $nameID,
            $nameIDFormat,
            $nameIDSPNameQualifier,
            $sessionIndex,
            $authnInstant,
            $expiresAt
        );
        $participant->save();

        $statement = $this->database->queryPrepared(
            'SELECT ssp_id FROM ' . TBL_SAML_SESSION_PARTICIPANTS . '
              WHERE ssp_org_id = ? AND ssp_external_session_id = ? AND ssp_client_id = ?
              ORDER BY ssp_id DESC',
            array($organizationId, $externalSessionId, $clientId)
        );
        $ids = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        foreach (array_slice($ids, self::MAX_PARTICIPANTS_PER_CLIENT) as $surplusId) {
            $this->deleteParticipant((int) $surplusId);
        }
    }

    /**
     * Return all active SAML participants for an Admidio session.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getParticipants(int $organizationId, string $externalSessionId): array
    {
        $statement = $this->database->queryPrepared(
            'SELECT ssp_id,
                    ssp_org_id,
                    ssp_usr_id,
                    ssp_client_id,
                    ssp_external_session_id,
                    ssp_name_id,
                    ssp_name_id_format,
                    ssp_name_id_sp_name_qualifier,
                    ssp_session_index
               FROM ' . TBL_SAML_SESSION_PARTICIPANTS . '
              WHERE ssp_org_id = ?
                AND ssp_external_session_id = ?
                AND ssp_expires_at > ?
              ORDER BY ssp_id',
            array($organizationId, $externalSessionId, DATETIME_NOW)
        );

        $participants = array();
        while ($row = $statement->fetch()) {
            $participants[] = $row;
        }

        return $participants;
    }

    /**
     * Return active participant candidates for a SAML client and SessionIndex.
     *
     * The caller must still compare the NameID details from the LogoutRequest.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getParticipantsByClientAndSessionIndex(
        int $organizationId,
        int $clientId,
        string $sessionIndex
    ): array {
        $statement = $this->database->queryPrepared(
            'SELECT ssp_id,
                    ssp_org_id,
                    ssp_usr_id,
                    ssp_client_id,
                    ssp_external_session_id,
                    ssp_name_id,
                    ssp_name_id_format,
                    ssp_name_id_sp_name_qualifier,
                    ssp_session_index
               FROM ' . TBL_SAML_SESSION_PARTICIPANTS . '
              WHERE ssp_org_id = ?
                AND ssp_client_id = ?
                AND ssp_session_index = ?
                AND ssp_expires_at > ?
              ORDER BY ssp_id',
            array($organizationId, $clientId, $sessionIndex, DATETIME_NOW)
        );

        $participants = array();
        while ($row = $statement->fetch()) {
            $participants[] = $row;
        }

        return $participants;
    }

    /**
     * @throws Exception
     */
    public function deleteParticipant(int $participantId): void
    {
        $participant = new SAMLSessionParticipant($this->database, $participantId);
        $participant->delete();
    }

    public function removeExpiredParticipants(): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_SAML_SESSION_PARTICIPANTS . '
              WHERE ssp_expires_at < ?',
            array(DATETIME_NOW)
        );
    }
}
