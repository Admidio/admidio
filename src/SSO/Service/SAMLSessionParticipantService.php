<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLSessionParticipant;

class SAMLSessionParticipantService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Persist or update the participant for one Admidio session and SAML client.
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
        $participant->readDataByExternalSessionAndClient($externalSessionId, $clientId);
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
                AND ssp_expires_at > CURRENT_TIMESTAMP
              ORDER BY ssp_id',
            array($organizationId, $externalSessionId)
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
                AND ssp_expires_at > CURRENT_TIMESTAMP
              ORDER BY ssp_id',
            array($organizationId, $clientId, $sessionIndex)
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
              WHERE ssp_expires_at < CURRENT_TIMESTAMP'
        );
    }
}
