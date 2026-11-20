<?php

namespace Admidio\Infrastructure;

use InvalidArgumentException;
use Throwable;

/** Installation-wide preferences, independent of organization lifecycle and session caches. */
final class InstallationSettings
{
    private bool $locked = false;

    public function __construct(private Database $db)
    {
    }

    /** Defaults are deliberately separate from organization preferences. */
    public static function defaults(): array
    {
        return array(
            'system_tenant_id' => '',
            'system_statistics_last_sent' => '',
            'system_statistics_enabled' => '0'
        );
    }

    public function get(string $name): ?string
    {
        self::validateName($name);
        $value = $this->db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_org_id IS NULL', array($name))->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function getBool(string $name): bool
    {
        return $this->get($name) === '1';
    }

    public function set(string $name, string $value): void
    {
        self::validateName($name);
        if ($name === 'system_statistics_enabled' && !in_array($value, array('0', '1'), true)) {
            throw new InvalidArgumentException('Statistics consent must be 0 or 1.');
        }
        $this->withLock(function () use ($name, $value): void {
            if ($this->get($name) === null) {
                $this->db->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . '
                    (prf_org_id, prf_name, prf_value) VALUES (NULL, ?, ?)', array($name, $value));
            } else {
                $this->db->queryPrepared('UPDATE ' . TBL_PREFERENCES . '
                    SET prf_value = ? WHERE prf_name = ? AND prf_org_id IS NULL', array($value, $name));
            }
        });
    }

    /** Serialize read-modify-write operations, including first insertion of a NULL-scoped row. */
    public function withLock(callable $operation): mixed
    {
        if ($this->locked) {
            return $operation($this);
        }
        $this->db->startTransaction();
        try {
            $this->db->queryPrepared('SELECT com_id FROM ' . TBL_COMPONENTS . '
                WHERE com_name_intern = ? FOR UPDATE', array('CORE'));
            $this->locked = true;
            $result = $operation($this);
            $this->db->endTransaction();

            return $result;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        } finally {
            $this->locked = false;
        }
    }

    private static function validateName(string $name): void
    {
        if (!array_key_exists($name, self::defaults())) {
            throw new InvalidArgumentException('Unknown installation preference: ' . $name);
        }
    }
}
