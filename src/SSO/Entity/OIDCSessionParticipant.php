<?php

namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

class OIDCSessionParticipant extends Entity
{
    /**
     * @throws Exception
     */
    public function __construct(Database $database, int|string $participantId = '')
    {
        parent::__construct($database, TBL_OIDC_SESSION_PARTICIPANTS, 'osp', $participantId);
    }

    /**
     * @throws Exception
     */
    public function readDataBySessionAndClient(string $externalSessionId, int $clientId): bool
    {
        return $this->readDataByColumns(array(
            'osp_external_session_id' => $externalSessionId,
            'osp_client_id' => $clientId
        ));
    }

    /**
     * @throws Exception
     */
    public function setParticipantData(
        int $organizationId,
        int $userId,
        int $clientId,
        string $externalSessionId,
        string $subject,
        \DateTimeInterface $expiresAt
    ): void {
        $this->setValue('osp_org_id', $organizationId);
        $this->setValue('osp_usr_id', $userId);
        $this->setValue('osp_client_id', $clientId);
        $this->setValue('osp_external_session_id', $externalSessionId);
        $this->setValue('osp_subject', $subject);
        $this->setValue('osp_expires_at', $expiresAt->format('Y-m-d H:i:s'));
    }
}
