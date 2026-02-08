<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents payment gateway transactions.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsTransaction extends TableResidentsBase
{
    public function __construct(Database $database, int $transactionId = 0)
    {
        parent::__construct($database, TBL_RE_TRANS, 'rtr', $transactionId);
    }

    public function assignPayment(int $paymentId): void
    {
        $this->setValue('rtr_payment_id', $paymentId);
    }

    public function getItems(): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $statement = $this->db->queryPrepared(
            'SELECT * FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ? ORDER BY rti_id',
            array((int)$this->getValue('rtr_id'))
        );

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        if ($this->isNewRecord()) {
            throw new RuntimeException('Cannot replace transaction items on unsaved transaction.');
    }

        $transactionId = (int)$this->getValue('rtr_id');
        $this->db->queryPrepared('DELETE FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ?', array($transactionId), false);

        foreach ($items as $item) {
            $invoiceId = isset($item['invoice_id']) ? (int)$item['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                continue;
            }

            $amount = trim((string)($item['amount'] ?? ''));
            if ($amount === '') {
                continue;
            }

            $currency = (string)($item['currency'] ?? '');

            $sql = 'INSERT INTO ' . TBL_RE_TRANS_ITEMS . ' (rti_pg_payment_id, rti_inv_id, rti_amount, rti_currency, rti_usr_id_create)
                    VALUES (?,?,?,?,?)';
            $this->db->queryPrepared($sql, array(
        $transactionId,
        $invoiceId,
        $amount,
        $currency,
        $creatorUserId
            ), false);
    }
    }

    public static function expireInitiated(Database $database, string $thresholdTimestamp): void
    {
        $sql = 'UPDATE ' . TBL_RE_TRANS . ' SET rtr_status = ? WHERE rtr_status = ? AND rtr_pg_trans_date < ?';
        $database->queryPrepared($sql, array('TO', 'IT', $thresholdTimestamp), false);
    }
}
