<?php

namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

class SAMLSessionParticipant extends Entity
{
    /**
     * @throws Exception
     */
    public function __construct(Database $database, int|string $participantId = '') 
    {
        parent::__construct($database, TBL_SAML_SESSION_PARTICIPANTS, 'ssp', $participantId);
    }

    /**
     * Read the participant for an Admidio session and SAML client.
     *
     * @throws Exception
     */
    public function readDataByExternalSessionAndClient(string $externalSessionId, int $clientId): bool 
    {
        return $this->readDataByColumns(
            array(
                'ssp_external_session_id' => $externalSessionId,
                'ssp_client_id' => $clientId
            )
        );
    }

    /**
     * Initialize or update an active SAML session participant.
     *
     * @throws Exception
     */
    public function setParticipantData(int $organizationId, int $userId, string $externalSessionId,
        int $clientId, string $nameID, string $nameIDFormat, ?string $nameIDSPNameQualifier,
        string $sessionIndex, \DateTimeInterface $authnInstant, \DateTimeInterface $expiresAt
    ): void 
    {
        $this->setValue('ssp_org_id', $organizationId);
        $this->setValue('ssp_usr_id', $userId);
        $this->setValue('ssp_external_session_id', $externalSessionId);
        $this->setValue('ssp_client_id', $clientId);
        $this->setValue('ssp_name_id', $nameID);
        $this->setValue('ssp_name_id_format', $nameIDFormat);
        $this->setValue('ssp_name_id_sp_name_qualifier', $nameIDSPNameQualifier);
        $this->setValue('ssp_session_index', $sessionIndex);
        $this->setValue('ssp_authn_instant', $authnInstant->format('Y-m-d H:i:s'));
        $this->setValue('ssp_expires_at', $expiresAt->format('Y-m-d H:i:s'));
    }
}
