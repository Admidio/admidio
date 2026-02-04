<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents charges.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsCharge extends TableResidentsBase
{
    public function __construct(Database $database, int $chargeId = 0)
    {
        parent::__construct($database, TBL_RE_CHARGES, 'rch', $chargeId);
    }

    public function setRoleIds(array $roleIds): void
    {
        $this->setValue('rch_role_ids', residentsSerializeRoleIds($roleIds));
    }

    public function getRoleIds(): array
    {
        return residentsDeserializeRoleIds((string)$this->getValue('rch_role_ids'));
    }

    public function setAmountFromString(string $amount): void
    {
        $this->setValue('rch_amount', number_format((float)$amount, 2, '.', ''));
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            ResidentsHistory::log($this->db, TBL_RE_CHARGES_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
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
        ResidentsHistory::log($this->db, TBL_RE_CHARGES_HIST, $before ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);

        return $result;
    }
}
