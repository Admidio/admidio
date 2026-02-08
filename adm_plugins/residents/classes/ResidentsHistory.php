<?php
/**
 ***********************************************************************************************
 * Lightweight history logger for Residents plugin entities.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

class ResidentsHistory
{
    /**
        * Map history table names to their column prefixes for audit columns.
        */
    private static function getHistoryPrefix(string $historyTable): string
    {
        // Extract table name without the adm_ prefix for matching
        $prefixMap = array(
            '_re_invoices_hist' => 'ivh',
            '_re_invoice_items_hist' => 'iih',
            '_re_payments_hist' => 'pah',
            '_re_payment_items_hist' => 'pih',
            '_re_charges_hist' => 'chh',
            '_re_devices_hist' => 'deh',
        );

        foreach ($prefixMap as $tableSuffix => $prefix) {
            if (strpos($historyTable, $tableSuffix) !== false) {
                return $prefix;
            }
    }

        return 'blh';
    }

    /**
        * Insert a full-row copy into the matching history table, plus audit columns.
        *
        * @param Database $db            Database connection
        * @param string   $historyTable  Target history table name (with prefix)
        * @param array    $row           Source row (all columns from live table)
        * @param string   $action        insert|update|delete
        * @param int|null $userId        Acting user
        */
    public static function log(Database $db, string $historyTable, array $row, string $action, ?int $userId = null): void
    {
        // Skip insert actions: only maintain history for update/delete
        if ($action === 'insert') {
            return;
    }

        if (empty($row)) {
            return;
    }

        $prefix = self::getHistoryPrefix($historyTable);

        $columns = array_keys($row);
        $columns[] = $prefix . '_action';
        $columns[] = $prefix . '_usr_id';
        $columns[] = $prefix . '_timestamp';

        $placeholders = array_fill(0, count($columns), '?');

        $params = array_values($row);
        $params[] = $action;
        $params[] = $userId;
        $params[] = DATETIME_NOW;

        $sql = 'INSERT INTO ' . $historyTable . ' (' . implode(',', $columns) . ')
        VALUES (' . implode(',', $placeholders) . ')';

        // Never trigger Admidio's fatal SQL error page from optional history logging.
        $db->queryPrepared($sql, $params, false);
    }

    public static function fetchRow(Database $db, string $table, string $pkColumn, int $id): ?array
    {
        // Best-effort reads; callers handle missing rows.
        $stmt = $db->queryPrepared('SELECT * FROM ' . $table . ' WHERE ' . $pkColumn . ' = ?', array($id), false);
        $row  = $stmt ? $stmt->fetch() : false;
        return $row !== false ? $row : null;
    }

    public static function fetchRowsByFk(Database $db, string $table, string $fkColumn, int $id): array
    {
        // Best-effort reads; return empty on failure.
        $stmt = $db->queryPrepared('SELECT * FROM ' . $table . ' WHERE ' . $fkColumn . ' = ?', array($id), false);
        return $stmt ? $stmt->fetchAll() : array();
    }
}
