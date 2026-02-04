<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents transaction item rows.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsTransactionItem extends TableResidentsBase
{
    public function __construct(Database $database, int $itemId = 0)
    {
        parent::__construct($database, TBL_RE_TRANS_ITEMS, 'rti', $itemId);
    }

    public function assignTransaction(int $transactionId): void
    {
        $this->setValue('rti_pg_payment_id', $transactionId);
    }

    public function assignInvoice(int $invoiceId): void
    {
        $this->setValue('rti_inv_id', $invoiceId);
    }
}
