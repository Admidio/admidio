<?php
/**
 ***********************************************************************************************
 * Non-fatal TableAccess base for the Residents plugin.
 *
 * Admidio's core Database wrapper can render a dedicated SQL error page and exit when
 * a statement fails (Database::queryPrepared with $showError=true). The Residents
 * plugin prefers to handle failures gracefully (inline alerts, redirects, JSON errors).
 *
 * This base class overrides write operations to always execute with $showError=false
 * so callers can decide how to surface the error.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Entity\Entity;
use Ramsey\Uuid\Uuid;

class TableResidentsBase extends Entity
{
    public function save(bool $updateFingerPrint = true): bool
    {
        if (!$this->columnsValueChanged && $this->dbColumns[$this->keyColumnName] !== '') {
            return false;
    }

        // Ensure UUID on new records (matches TableAccess behavior)
        if ($this->isNewRecord()
            && array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)
            && (string) $this->getValue($this->columnPrefix . '_uuid') === '') {
                $this->setValue($this->columnPrefix . '_uuid', (string) Uuid::uuid4());
    }

        // Fingerprint updates (matches TableAccess behavior)
        if ($updateFingerPrint && isset($GLOBALS['gCurrentUserId']) && $GLOBALS['gCurrentUserId'] > 0) {
            if ($this->newRecord && $this->insertRecord && array_key_exists($this->columnPrefix . '_usr_id_create', $this->dbColumns)) {
                $this->setValue($this->columnPrefix . '_timestamp_create', DATETIME_NOW);
                $this->setValue($this->columnPrefix . '_usr_id_create', $GLOBALS['gCurrentUserId']);
            } elseif (array_key_exists($this->columnPrefix . '_usr_id_change', $this->dbColumns)) {
                if ($GLOBALS['gCurrentUserId'] !== $this->getValue($this->columnPrefix . '_usr_id_create')
                    || time() > (strtotime((string) $this->getValue($this->columnPrefix . '_timestamp_create')) + 900)) {
                        $this->setValue($this->columnPrefix . '_timestamp_change', DATETIME_NOW);
                        $this->setValue($this->columnPrefix . '_usr_id_change', $GLOBALS['gCurrentUserId']);
        }
            }
    }

        $sqlFieldArray = array();
        $sqlSetArray = array();
        $queryParams = array();
        $returnCode = false;

        foreach ($this->dbColumns as $key => $value) {
            if (!str_starts_with($key, $this->columnPrefix . '_')) {
                continue;
            }

            if (($this->columnsInfos[$key]['type'] ?? '') === 'boolean' && DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
                if ($value || $value === '1') {
                    $value = 'true';
        } else {
                    $value = 'false';
        }
            }

            if (($this->columnsInfos[$key]['serial'] ?? false) || !($this->columnsInfos[$key]['changed'] ?? false)) {
                continue;
            }

            if ($this->insertRecord) {
                if ($value !== '') {
                    $sqlFieldArray[] = $key;
                    $queryParams[] = $value;
        }
            } else {
                $sqlSetArray[] = $key . ' = ?';
                if ($value === '' || $value === null) {
                    $queryParams[] = null;
        } else {
                    $queryParams[] = $value;
        }
            }
    }

        if ($this->insertRecord) {
            if (count($sqlFieldArray) === 0) {
                return false;
            }

            $sql = 'INSERT INTO ' . $this->tableName . '
                        (' . implode(',', $sqlFieldArray) . ')
                            VALUES (' . Database::getQmForValues($sqlFieldArray) . ')';

            if ($this->db->queryPrepared($sql, $queryParams, false) !== false) {
                $returnCode = true;
                $this->insertRecord = false;
                if ($this->keyColumnName !== '') {
                    $this->dbColumns[$this->keyColumnName] = $this->db->lastInsertId();
        }
            }
    } else {
            if (count($sqlSetArray) === 0) {
                return false;
            }

            $sql = 'UPDATE ' . $this->tableName . '
                    SET ' . implode(', ', $sqlSetArray) . '
                WHERE ' . $this->keyColumnName . ' = ? -- $this->dbColumns[$this->keyColumnName]';
            $queryParams[] = $this->dbColumns[$this->keyColumnName];

            if ($this->db->queryPrepared($sql, $queryParams, false) !== false) {
                $returnCode = true;
            }
    }

        if ($returnCode) {
            foreach ($this->columnsInfos as $columnName => &$info) {
                if (str_starts_with($columnName, $this->columnPrefix . '_')) {
                    $info['changed'] = false;
        }
            }
            unset($info);

            $this->columnsValueChanged = false;
    }

        return $returnCode;
    }

    public function delete(): bool
    {
        if (array_key_exists($this->keyColumnName, $this->dbColumns) && $this->dbColumns[$this->keyColumnName] !== '') {
            $sql = 'DELETE FROM ' . $this->tableName . '
                WHERE ' . $this->keyColumnName . ' = ? -- $this->dbColumns[$this->keyColumnName]';
            if ($this->db->queryPrepared($sql, array($this->dbColumns[$this->keyColumnName]), false) === false) {
                return false;
            }
    }

        $this->clear();
        return true;
    }
}
