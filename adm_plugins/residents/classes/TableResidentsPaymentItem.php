<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents payment item rows.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsPaymentItem extends TableResidentsBase
{
    public function __construct(Database $database, int $itemId = 0)
    {
        parent::__construct($database, TBL_RE_PAYMENT_ITEMS, 'rpi', $itemId);
    }

    public function assignToPayment(int $paymentId): void
    {
        $this->setValue('rpi_payment_id', $paymentId);
    }

    public function assignInvoice(int $invoiceId): void
    {
        $this->setValue('rpi_inv_id', $invoiceId);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            ResidentsHistory::log($this->db, TBL_RE_PAYMENT_ITEMS_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

        return $result;
    }

    public function delete(): bool
    {
        if ($this->isNewRecord()) {
            return false;
    }

        $id      = (int)$this->getValue($this->keyColumnName);
        $before  = ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $id);
        $result  = parent::delete();
        ResidentsHistory::log($this->db, TBL_RE_PAYMENT_ITEMS_HIST, $before ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);

        return $result;
    }
}
