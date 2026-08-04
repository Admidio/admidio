<?php

namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use JsonException;

class SAMLLogoutTransaction extends Entity
{
    /**
     * @var array<string,mixed>
     */
    private array $transactionData = array();

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function __construct(Database $database, int|string $transactionId = '')
    {
        parent::__construct($database, TBL_SAML_LOGOUT_TRANSACTIONS, 'slt', $transactionId);
        if ($transactionId !== '') {
            $this->loadTransactionData();
        }
    }

    /**
     * Load a logout transaction through its opaque RelayState token.
     *
     * @throws Exception
     * @throws JsonException
     */
    public function readDataByToken(string $token): bool
    {
        $result = $this->readDataByColumns(array('slt_token' => $token));
        if ($result) {
            $this->loadTransactionData();
        }

        return $result;
    }

    /**
     * Initialize a new logout transaction.
     *
     * @param array<int,array<string,mixed>> $pendingClients
     *
     * @throws JsonException
     */
    public function initialize(int $organizationId, int $initiatorClientId, string $initiatorRequestId, ?string $initiatorRelayState, array $pendingClients): void 
    {
        $this->setValue('slt_token', bin2hex(random_bytes(32)));
        $this->setValue('slt_org_id', $organizationId);
        $this->setValue('slt_expires_at', date('Y-m-d H:i:s', time() + 600));

        $this->transactionData = array(
            'initiatorClientId' => $initiatorClientId,
            'initiatorRequestId' => $initiatorRequestId,
            'initiatorRelayState' => $initiatorRelayState ?? '',
            'pendingClients' => array_values($pendingClients),
            'currentClientId' => null,
            'currentRequestId' => null,
            'partialLogout' => false
        );

        $this->writeTransactionData();
    }

    public function getInitiatorClientId(): int
    {
        return (int) ($this->transactionData['initiatorClientId'] ?? 0);
    }

    public function getInitiatorRequestId(): string
    {
        return (string) ($this->transactionData['initiatorRequestId'] ?? '');
    }

    public function getInitiatorRelayState(): string
    {
        return (string) ($this->transactionData['initiatorRelayState'] ?? '');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getPendingClients(): array
    {
        $pendingClients = $this->transactionData['pendingClients'] ?? array();
        return is_array($pendingClients) ? $pendingClients : array();
    }

    /**
     * @param array<int,array<string,mixed>> $pendingClients
     *
     * @throws JsonException
     */
    public function setPendingClients(array $pendingClients): void
    {
        $this->transactionData['pendingClients'] = array_values($pendingClients);
        $this->writeTransactionData();
    }

    public function getCurrentClientId(): int
    {
        return (int) ($this->transactionData['currentClientId'] ?? 0);
    }

    /**
     * @throws JsonException
     */
    public function setCurrentClientId(?int $clientId): void
    {
        $this->transactionData['currentClientId'] = $clientId;
        $this->writeTransactionData();
    }

    public function getCurrentRequestId(): string
    {
        return (string) ($this->transactionData['currentRequestId'] ?? '');
    }

    /**
     * @throws JsonException
     */
    public function setCurrentRequestId(?string $requestId): void
    {
        $this->transactionData['currentRequestId'] = $requestId;
        $this->writeTransactionData();
    }

    public function hasPartialLogout(): bool
    {
        return (bool) ($this->transactionData['partialLogout'] ?? false);
    }

    /**
     * @throws JsonException
     */
    public function setPartialLogout(bool $partialLogout): void
    {
        $this->transactionData['partialLogout'] = $partialLogout;
        $this->writeTransactionData();
    }

    /**
     * Set the currently pending client and request together.
     *
     * @throws JsonException
     */
    public function setCurrentRequest(?int $clientId, ?string $requestId): void {
        $this->transactionData['currentClientId'] = $clientId;
        $this->transactionData['currentRequestId'] = $requestId;

        $this->writeTransactionData();
    }

    public function isExpired(): bool
    {
        $expiresAt = strtotime((string) $this->getValue('slt_expires_at'));

        return $expiresAt === false || $expiresAt < time();
    }

    /**
     * @throws JsonException
     */
    private function loadTransactionData(): void
    {
        $data = $this->getValue('slt_data');

        if (empty($data)) {
            $this->transactionData = array();
            return;
        }

        $decodedData = json_decode((string) $data, true, 512, JSON_THROW_ON_ERROR);

        $this->transactionData = is_array($decodedData) ? $decodedData : array();
    }

    /**
     * @throws JsonException
     */
    private function writeTransactionData(): void
    {
        $this->setValue(
            'slt_data',
            json_encode($this->transactionData, JSON_THROW_ON_ERROR)
        );
    }
}