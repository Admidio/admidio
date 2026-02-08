<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents invoice item rows.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsInvoiceItem extends TableResidentsBase
{
    public function __construct(Database $database, int $itemId = 0)
    {
        parent::__construct($database, TBL_RE_INVOICE_ITEMS, 'rii', $itemId);
    }

    public function assignToInvoice(int $invoiceId): void
    {
        $this->setValue('rii_inv_id', $invoiceId);
    }

    public function setAmountValues(?string $currency, $rate, $quantity, $amount): void
    {
        $this->setValue('rii_currency', $currency);
        $this->setValue('rii_rate', $rate);
        $this->setValue('rii_quantity', $quantity);
        $this->setValue('rii_amount', $amount);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            ResidentsHistory::log($this->db, TBL_RE_INVOICE_ITEMS_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
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
        ResidentsHistory::log($this->db, TBL_RE_INVOICE_ITEMS_HIST, $before ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);

        return $result;
    }
}
