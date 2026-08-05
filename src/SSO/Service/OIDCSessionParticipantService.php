<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\OIDCSessionParticipant;

class OIDCSessionParticipantService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Persist or update the participant for one OP session and OIDC client.
     *
     * @throws Exception
     */
    public function persistParticipant(
        int $organizationId,
        int $userId,
        int $clientId,
        string $externalSessionId,
        string $subject,
        \DateTimeInterface $expiresAt
    ): void {
        $participant = new OIDCSessionParticipant($this->database);
        $participant->readDataBySessionAndClient($externalSessionId, $clientId);
        $participant->setParticipantData(
            $organizationId,
            $userId,
            $clientId,
            $externalSessionId,
            $subject,
            $expiresAt
        );
        $participant->save();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getParticipants(string $externalSessionId): array
    {
        $statement = $this->database->queryPrepared(
            'SELECT osp_id,
                    osp_org_id,
                    osp_usr_id,
                    osp_client_id,
                    osp_external_session_id,
                    osp_subject,
                    osp_expires_at
               FROM ' . TBL_OIDC_SESSION_PARTICIPANTS . '
              WHERE osp_external_session_id = ?
                AND osp_expires_at > CURRENT_TIMESTAMP
              ORDER BY osp_id',
            array($externalSessionId)
        );

        $participants = array();
        while ($row = $statement->fetch()) {
            $participants[] = $row;
        }

        return $participants;
    }

    /**
     * Verify that a token hint identifies a currently tracked participant.
     *
     * @throws Exception
     */
    public function assertParticipant(
        string $externalSessionId,
        int $clientId,
        string $subject
    ): void {
        $participant = new OIDCSessionParticipant($this->database);
        if (!$participant->readDataBySessionAndClient($externalSessionId, $clientId)) {
            throw new Exception(
                'The ID token hint does not identify an active OIDC session.'
            );
        }

        $expiresAt = (int) $participant->getValue('osp_expires_at', 'U');
        if ($expiresAt <= time()
            || !hash_equals((string) $participant->getValue('osp_subject'), $subject)
        ) {
            throw new Exception(
                'The ID token hint does not identify an active OIDC session.'
            );
        }
    }

    public function deleteParticipants(string $externalSessionId): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_SESSION_PARTICIPANTS . '
              WHERE osp_external_session_id = ?',
            array($externalSessionId)
        );
    }

    public function removeExpiredParticipants(): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_SESSION_PARTICIPANTS . '
              WHERE osp_expires_at < CURRENT_TIMESTAMP'
        );
    }
}
