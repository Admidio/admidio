<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents invoices.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsInvoice extends TableResidentsBase
{
    public function __construct(Database $database, int $invoiceId = 0)
    {
        parent::__construct($database, TBL_RE_INVOICES, 'riv', $invoiceId);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            ResidentsHistory::log($this->db, TBL_RE_INVOICES_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

        return $result;
    }

    public function deleteWithRelations(): bool
    {
        if ($this->isNewRecord()) {
            return false;
    }

        $invoiceId = (int)$this->getValue('riv_id');
        if ($invoiceId <= 0) {
            return false;
    }

        $beforeInvoice  = ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $invoiceId);
        $beforeItems    = ResidentsHistory::fetchRowsByFk($this->db, TBL_RE_INVOICE_ITEMS, 'rii_inv_id', $invoiceId);
        $beforePayItems = ResidentsHistory::fetchRowsByFk($this->db, TBL_RE_PAYMENT_ITEMS, 'rpi_inv_id', $invoiceId);

        $this->db->startTransaction();
        $ok = true;

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ?', array($invoiceId), false) !== false);
        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_inv_id = ?', array($invoiceId), false) !== false);
        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_inv_id = ?', array($invoiceId), false) !== false);

        $result = $ok ? parent::delete() : false;

        if (!$result) {
            $this->db->rollback();
            return false;
    }

        $this->db->endTransaction();

        if ($result) {
            ResidentsHistory::log($this->db, TBL_RE_INVOICES_HIST, $beforeInvoice ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            foreach ($beforeItems as $row) {
                ResidentsHistory::log($this->db, TBL_RE_INVOICE_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            }
            foreach ($beforePayItems as $row) {
                ResidentsHistory::log($this->db, TBL_RE_PAYMENT_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            }
    }

        return $result;
    }

    public static function fetchList(Database $database, array $filters, array $options): array
    {
        $baseConditions = array();
        $baseParams = array();
        $filterConditions = array();
        $filterParams = array();
        $searchCondition = '';
        $searchParams = array();

        $orgId = isset($filters['org_id']) ? (int)$filters['org_id'] : 0;
        if ($orgId > 0) {
            $baseConditions[] = 'b.riv_org_id = ?';
            $baseParams[] = $orgId;
    }

        $isAdmin = (bool)($filters['is_admin'] ?? false);
        $currentUserId = isset($filters['current_user_id']) ? (int)$filters['current_user_id'] : null;
        if (!$isAdmin && $currentUserId !== null) {
            $baseConditions[] = 'b.riv_usr_id = ?';
            $baseParams[] = $currentUserId;
    }

        if (!empty($filters['filter_group'])) {
            $filterConditions[] = 'EXISTS (
        SELECT 1
                                    FROM ' . TBL_MEMBERS . ' m
                                    JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                                    JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
                    WHERE m.mem_usr_id = b.riv_usr_id
                    AND m.mem_begin <= ?
                    AND m.mem_end > ?
                    AND m.mem_rol_id = ?
                    AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
            )';
            $filterParams[] = DATE_NOW;
            $filterParams[] = DATE_NOW;
            $filterParams[] = (int)$filters['filter_group'];
            $filterParams[] = (int)($filters['org_id'] ?? 0);
    }

        if (!empty($filters['filter_user'])) {
            $filterConditions[] = 'b.riv_usr_id = ?';
            $filterParams[] = (int)$filters['filter_user'];
    }

        if (isset($filters['filter_paid']) && $filters['filter_paid'] !== '' && $filters['filter_paid'] !== null) {
            $filterConditions[] = 'b.riv_is_paid = ?';
            $filterParams[] = (int)$filters['filter_paid'];
    }

        if (!empty($filters['date_from'])) {
            $filterConditions[] = 'b.riv_end_date >= ?';
            $filterParams[] = (string)$filters['date_from'];
    }

        if (!empty($filters['date_to'])) {
            $filterConditions[] = 'b.riv_start_date <= ?';
            $filterParams[] = (string)$filters['date_to'];
    }

        $searchTerm = trim((string)($filters['search'] ?? ''));
        if ($searchTerm !== '') {
            $searchCondition = " (
        LOWER(COALESCE(b.riv_number, '')) LIKE ?
        OR LOWER(CONCAT_WS(' ', COALESCE(fn.usd_value, ''), COALESCE(ln.usd_value, ''))) LIKE ?
        OR LOWER(COALESCE(u.usr_login_name, '')) LIKE ?
            )";
            $searchLike = '%' . strtolower($searchTerm) . '%';
            $searchParams = array($searchLike, $searchLike, $searchLike);
    }

        $whereParts = array_merge($baseConditions, $filterConditions);
        if ($searchCondition !== '') {
            $whereParts[] = $searchCondition;
    }
        $params = array_merge($baseParams, $filterParams, $searchParams);

        $lnId = (int)($options['profile_last_name_id'] ?? 0);
        $fnId = (int)($options['profile_first_name_id'] ?? 0);

        $sql = 'SELECT b.*,
                    CONCAT_WS(\' \', fn.usd_value, ln.usd_value) AS user_name,
                        (SELECT COALESCE(SUM(rii_amount), 0) FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = b.riv_id) AS total_amount,
                        (SELECT rii_currency FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = b.riv_id ORDER BY rii_id DESC LIMIT 1) AS total_currency
                                    FROM ' . TBL_RE_INVOICES . ' b
                LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = b.riv_usr_id
                LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;

        if (count($whereParts) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

        $sortMap = array(
            'number' => 'b.riv_number',
            'date' => 'b.riv_date',
            'start_date' => 'b.riv_start_date',
            'end_date' => 'b.riv_end_date',
            'status' => 'b.riv_is_paid',
            'user' => 'user_name',
            'due_date' => 'b.riv_due_date',
            'amount' => 'total_amount'
        );
        $sortCol = $filters['sort_col'] ?? 'date';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $sortDir = in_array($sortDir, array('ASC', 'DESC'), true) ? $sortDir : 'DESC';
        $orderBy = $sortMap[$sortCol] ?? 'b.riv_date';

        if (($options['db_type'] ?? '') === 'pgsql') {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir . ' NULLS LAST, b.riv_id DESC';
    } else {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir;
            if ($orderBy !== 'b.riv_id') {
                $sql .= ', b.riv_id DESC';
            }
    }

        $length = (int)($options['length'] ?? 25);
        if ($length <= 0) {
            $length = 25;
    }
        $offset = (int)($options['offset'] ?? 0);
        $paramsWithLimit = $params;
        $sql .= ' LIMIT ? OFFSET ?';
        $paramsWithLimit[] = $length;
        $paramsWithLimit[] = $offset;

        $statement = $database->queryPrepared($sql, $paramsWithLimit, false);
        $rows = $statement ? $statement->fetchAll() : array();

        $countBaseSql = 'SELECT COUNT(*) FROM ' . TBL_RE_INVOICES . ' b';
        if (count($baseConditions) > 0) {
            $countBaseSql .= ' WHERE ' . implode(' AND ', $baseConditions);
    }
        $countBaseStatement = $database->queryPrepared($countBaseSql, $baseParams, false);
        $totalBase = $countBaseStatement ? (int)$countBaseStatement->fetchColumn() : 0;

        $countSql = 'SELECT COUNT(*)
                        FROM ' . TBL_RE_INVOICES . ' b
                                    LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = b.riv_usr_id
                                    LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                                    LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;
        if (count($whereParts) > 0) {
            $countSql .= ' WHERE ' . implode(' AND ', $whereParts);
    }
        $countStatement = $database->queryPrepared($countSql, $params, false);
        $totalCount = $countStatement ? (int)$countStatement->fetchColumn() : count($rows);

        return array(
            'rows' => $rows,
            'total' => $totalCount,
            'total_base' => $totalBase
        );
    }

    public function getItems(): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $statement = $this->db->queryPrepared(
            'SELECT * FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ? ORDER BY rii_id',
            array((int)$this->getValue('riv_id')),
            false
        );

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        $invoiceId = (int)$this->getValue('riv_id');
        if ($invoiceId <= 0) {
            throw new RuntimeException('Cannot replace invoice items on unsaved invoice.');
    }

        $existingItems = ResidentsHistory::fetchRowsByFk($this->db, TBL_RE_INVOICE_ITEMS, 'rii_inv_id', $invoiceId);

        if ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ?', array($invoiceId), false) === false) {
            throw new RuntimeException('Failed to delete existing invoice items.');
    }
        foreach ($existingItems as $row) {
            ResidentsHistory::log($this->db, TBL_RE_INVOICE_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
    }

        foreach ($items as $item) {
            $chargeId = (int)($item['charge_id'] ?? 0);
            $name = trim((string)($item['name'] ?? ''));
            if ($chargeId <= 0 || $name === '') {
                continue;
            }

            $startDateRaw = trim((string)($item['start_date'] ?? ''));
            $endDateRaw = trim((string)($item['end_date'] ?? ''));

            $rateRaw = trim((string)($item['rate'] ?? ''));
            $quantityRaw = trim((string)($item['quantity'] ?? ''));
            $amountRaw = trim((string)($item['amount'] ?? ''));

            $rate = $rateRaw !== '' ? str_replace(',', '', $rateRaw) : null;
            $quantity = $quantityRaw !== '' ? str_replace(',', '', $quantityRaw) : null;
            $amount = $amountRaw !== '' ? str_replace(',', '', $amountRaw) : null;

            $columns = array('rii_inv_id', 'rii_chg_id', 'rii_name');
            $values = array($invoiceId, $chargeId, $name);

            $columns[] = 'rii_start_date';
            $values[] = $startDateRaw !== '' ? $startDateRaw : null;
            $columns[] = 'rii_end_date';
            $values[] = $endDateRaw !== '' ? $endDateRaw : null;

            $columns = array_merge($columns, array('rii_type', 'rii_currency', 'rii_rate', 'rii_quantity', 'rii_amount', 'rii_usr_id_create'));
            $values = array_merge($values, array(
        (string)($item['type'] ?? ''),
        (string)($item['currency'] ?? ''),
        $rate,
        $quantity,
        $amount,
        $creatorUserId
            ));

            global $gCurrentOrgId;
            if (!empty($gCurrentOrgId)) {
                $columns[] = 'rii_org_id';
                $values[] = (int)$gCurrentOrgId;
            }

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO ' . TBL_RE_INVOICE_ITEMS . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

            if ($this->db->queryPrepared($sql, $values, false) === false) {
                throw new RuntimeException('Failed to insert invoice item.');
            }
    }
    }

    public static function fetchOpenInvoicesByUser(Database $database, int $userId, ?int $orgId = null): array
    {
        global $gCurrentOrgId;
        
        $filterOrgId = ($orgId !== null) ? $orgId : (int)$gCurrentOrgId;
        
        $conditions = array('riv_usr_id = ?', 'COALESCE(riv_is_paid, 0) = 0');
        $params = array($userId);
        
        if ($filterOrgId > 0) {
            $conditions[] = 'riv_org_id = ?';
            $params[] = $filterOrgId;
    }
        
        $sql = 'SELECT * FROM ' . TBL_RE_INVOICES . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY riv_date ASC, riv_id ASC';
        $statement = $database->queryPrepared($sql, $params);

        return $statement ? $statement->fetchAll() : array();
    }
}
