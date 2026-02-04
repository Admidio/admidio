<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents payments.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsPayment extends TableResidentsBase
{
    public function __construct(Database $database, int $paymentId = 0)
    {
        parent::__construct($database, TBL_RE_PAYMENTS, 'rpa', $paymentId);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            ResidentsHistory::log($this->db, TBL_RE_PAYMENTS_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

        return $result;
    }

    public function deleteWithRelations(?int $actingUserId = null): bool
    {
        if ($this->isNewRecord()) {
            return false;
    }

        $paymentId = (int)$this->getValue('rpa_id');
        if ($paymentId <= 0) {
            return false;
    }
        $beforePayment = ResidentsHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $paymentId);
        $beforeItems   = ResidentsHistory::fetchRowsByFk($this->db, TBL_RE_PAYMENT_ITEMS, 'rpi_payment_id', $paymentId);

        $this->db->startTransaction();
        $ok = true;

        $invoiceIds = array();
        $invoiceStmt = $this->db->queryPrepared('SELECT DISTINCT rpi_inv_id FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_payment_id = ?', array($paymentId), false);
        if ($invoiceStmt !== false) {
            while ($row = $invoiceStmt->fetch()) {
                $invId = (int)($row['rpi_inv_id'] ?? 0);
                if ($invId > 0) {
                    $invoiceIds[$invId] = true;
        }
            }
    }

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_payment_id = ?', array($paymentId), false) !== false);

        $statement = $this->db->queryPrepared('SELECT rtr_id FROM ' . TBL_RE_TRANS . ' WHERE rtr_payment_id = ?', array($paymentId), false);
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ?', array((int)$row['rtr_id']), false) !== false);
            }
    }

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_TRANS . ' WHERE rtr_payment_id = ?', array($paymentId), false) !== false);

        if (!empty($invoiceIds)) {
            $invoiceIds = array_keys($invoiceIds);
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $updateFields = array('riv_is_paid = ?', 'riv_timestamp_change = ?');
            $params = array(0, date('Y-m-d H:i:s'));
            if ($actingUserId !== null) {
                $updateFields[] = 'riv_usr_id_change = ?';
                $params[] = $actingUserId;
            }
            $params = array_merge($params, $invoiceIds);
            $sql = 'UPDATE ' . TBL_RE_INVOICES . ' SET ' . implode(', ', $updateFields) . ' WHERE riv_id IN (' . $placeholders . ')';
            $ok = $ok && ($this->db->queryPrepared($sql, $params, false) !== false);
    }

        $result = $ok ? parent::delete() : false;

        if (!$result) {
            $this->db->rollback();
            return false;
    }

        $this->db->endTransaction();

        if ($result) {
            ResidentsHistory::log($this->db, TBL_RE_PAYMENTS_HIST, $beforePayment ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            foreach ($beforeItems as $row) {
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

        $orgId = $filters['org_id'] ?? null;
        if ($orgId !== null) {
            $baseConditions[] = '(p.rpa_org_id = ? OR p.rpa_org_id IS NULL)';
            $baseParams[] = (int)$orgId;
    }

        $isAdmin = (bool)($filters['is_admin'] ?? false);
        if (!$isAdmin && isset($filters['current_user_id'])) {
            $baseConditions[] = 'p.rpa_usr_id = ?';
            $baseParams[] = (int)$filters['current_user_id'];
    }

        if (!empty($filters['filter_user'])) {
            $filterConditions[] = 'p.rpa_usr_id = ?';
            $filterParams[] = (int)$filters['filter_user'];
    }

        if (!empty($filters['filter_group'])) {
            $filterConditions[] = 'p.rpa_usr_id IN (SELECT mem_usr_id FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ? AND mem_end > NOW())';
            $filterParams[] = (int)$filters['filter_group'];
    }

        if (!empty($filters['filter_status'])) {
            $filterConditions[] = 'p.rpa_status = ?';
            $filterParams[] = $filters['filter_status'];
    }

        if (!empty($filters['filter_type'])) {
            $filterConditions[] = 'p.rpa_pay_type = ?';
            $filterParams[] = $filters['filter_type'] === 'offline' ? 'Offline' : 'Online';
    }

        if (!empty($filters['filter_start'])) {
            $filterConditions[] = 'p.rpa_date >= ?';
            $filterParams[] = $filters['filter_start'];
    }

        if (!empty($filters['filter_end'])) {
            $filterConditions[] = 'p.rpa_date <= ?';
            $filterParams[] = $filters['filter_end'] . ' 23:59:59';
    }

        $searchTerm = trim((string)($filters['search'] ?? ''));
        if ($searchTerm !== '') {
            $searchCondition = " (
        LOWER(CONCAT_WS(' ', COALESCE(fn.usd_value, ''), COALESCE(ln.usd_value, ''))) LIKE ?
        OR LOWER(COALESCE(p.rpa_bank_ref_no, '')) LIKE ?
        OR LOWER(COALESCE(p.rpa_pg_pay_method, '')) LIKE ?
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

        $sql = 'SELECT p.*,
                        CONCAT_WS(\' \', fn.usd_value, ln.usd_value) AS user_name,
                        (SELECT COALESCE(SUM(rpi_amount),0) FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_payment_id = p.rpa_id) AS total_amount,
                        (SELECT rpi_currency FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_payment_id = p.rpa_id ORDER BY rpi_id DESC LIMIT 1) AS total_currency
                                    FROM ' . TBL_RE_PAYMENTS . ' p
                LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = p.rpa_usr_id
                LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;

        if (count($whereParts) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

        $sortMap = array(
            'no' => 'p.rpa_id',
            'date' => 'p.rpa_date',
            'status' => 'p.rpa_status',
            'method' => 'p.rpa_pg_pay_method',
            'type' => 'p.rpa_pay_type',
            'customer_name' => 'user_name',
            'reference' => 'p.rpa_bank_ref_no',
            'amount' => 'total_amount'
        );
        $sortCol = $filters['sort_col'] ?? 'date';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $sortDir = in_array($sortDir, array('ASC','DESC'), true) ? $sortDir : 'DESC';
        $orderBy = $sortMap[$sortCol] ?? 'p.rpa_date';

        if (($options['db_type'] ?? '') === 'pgsql') {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir . ' NULLS LAST, p.rpa_id DESC';
    } else {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir;
            if ($orderBy !== 'p.rpa_id') {
                $sql .= ', p.rpa_id DESC';
            }
    }

        $length = (int)($options['length'] ?? 25);
        $offset = (int)($options['offset'] ?? 0);
        $paramsWithLimit = $params;
        $sql .= ' LIMIT ? OFFSET ?';
        $paramsWithLimit[] = $length;
        $paramsWithLimit[] = $offset;

        $statement = $database->queryPrepared($sql, $paramsWithLimit, false);
        $rows = $statement ? $statement->fetchAll() : array();

        // total count without filters/search (security constraints only)
        $countBaseSql = 'SELECT COUNT(*) FROM ' . TBL_RE_PAYMENTS . ' p';
        if (count($baseConditions) > 0) {
            $countBaseSql .= ' WHERE ' . implode(' AND ', $baseConditions);
    }
        $countBaseStatement = $database->queryPrepared($countBaseSql, $baseParams, false);
        $totalBase = $countBaseStatement ? (int)$countBaseStatement->fetchColumn() : 0;

        $countSql = 'SELECT COUNT(*)
                        FROM ' . TBL_RE_PAYMENTS . ' p
                                    LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = p.rpa_usr_id
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

    public static function fetchDistinctMethods(Database $database): array
    {
        $methods = array();
        $statement = $database->queryPrepared('SELECT DISTINCT rpa_pg_pay_method AS value FROM ' . TBL_RE_PAYMENTS . ' ORDER BY value', array(), false);
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                if ($row['value'] !== null && $row['value'] !== '') {
                    $methods[] = $row['value'];
        }
            }
    }

        return $methods;
    }

    public static function fetchUserOptions(Database $database, bool $isAdmin, int $firstNameFieldId, int $lastNameFieldId, ?int $currentUserId, int $groupId = 0): array
    {
        if (!$isAdmin) {
            if ($currentUserId === null) {
                return array();
            }
            return array($currentUserId => residentsFetchUserNameById($currentUserId));
    }

        global $gCurrentOrgId;

        $sql = 'SELECT DISTINCT u.usr_id,
                        (SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = u.usr_id AND usd_usf_id = ?) AS first_name,
                        (SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = u.usr_id AND usd_usf_id = ?) AS last_name
                                    FROM ' . TBL_USERS . ' u';

        $params = array($firstNameFieldId, $lastNameFieldId);

        // Always join to scope users to the current organization
        $sql .= ' JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_end > NOW()';
        $sql .= ' JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = 1';
        $sql .= ' JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)';
        $params[] = (int)$gCurrentOrgId;

        $where = array('u.usr_valid = 1');

        if ($groupId > 0) {
            $where[] = 'm.mem_rol_id = ?';
            $params[] = $groupId;
        }

        $sql .= ' WHERE ' . implode(' AND ', $where) . '
                            ORDER BY last_name, first_name';

        $statement = $database->queryPrepared($sql, $params, false);
        $options = array();
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                $options[$row['usr_id']] = trim((string)$row['first_name'] . ' ' . $row['last_name']);
            }
    }

        return $options;
    }

    public function getItems(bool $includeInvoiceNumber = false): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $columns = 'pi.*';
        $join = '';
        if ($includeInvoiceNumber) {
            $columns .= ', inv.riv_number';
            $join = ' LEFT JOIN ' . TBL_RE_INVOICES . ' inv ON inv.riv_id = pi.rpi_inv_id';
    }

        $sql = 'SELECT ' . $columns . ' FROM ' . TBL_RE_PAYMENT_ITEMS . ' pi' . $join . ' WHERE pi.rpi_payment_id = ? ORDER BY inv.riv_id ASC';
        $statement = $this->db->queryPrepared($sql, array((int)$this->getValue('rpa_id')), false);

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        $paymentId = (int)$this->getValue('rpa_id');
        if ($paymentId <= 0) {
            throw new RuntimeException('Cannot replace payment items on unsaved payment.');
    }
        $existingItems = ResidentsHistory::fetchRowsByFk($this->db, TBL_RE_PAYMENT_ITEMS, 'rpi_payment_id', $paymentId);

        if ($this->db->queryPrepared('DELETE FROM ' . TBL_RE_PAYMENT_ITEMS . ' WHERE rpi_payment_id = ?', array($paymentId), false) === false) {
            throw new RuntimeException('Failed to delete existing payment items.');
    }
        foreach ($existingItems as $row) {
            ResidentsHistory::log($this->db, TBL_RE_PAYMENT_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
    }

        foreach ($items as $item) {
            $amount = trim((string)($item['amount'] ?? ''));
            if ($amount === '') {
                continue;
            }

            $invoiceId = isset($item['invoice_id']) ? (int)$item['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                continue;
            }

            $currency = (string)($item['currency'] ?? '');

            $columns = array('rpi_payment_id', 'rpi_amount', 'rpi_currency', 'rpi_inv_id', 'rpi_usr_id_create');
            $values = array($paymentId, $amount, $currency, $invoiceId, $creatorUserId);

            global $gCurrentOrgId;
            if (!empty($gCurrentOrgId)) {
                $columns[] = 'rpi_org_id';
                $values[] = (int)$gCurrentOrgId;
            }

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO ' . TBL_RE_PAYMENT_ITEMS . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            if ($this->db->queryPrepared($sql, $values, false) === false) {
                throw new RuntimeException('Failed to insert payment item.');
            }
    }
    }
}
