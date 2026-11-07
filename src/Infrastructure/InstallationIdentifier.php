<?php

namespace Admidio\Infrastructure;

use Ramsey\Uuid\Uuid;
use Throwable;

/** Installation identity stored independently of organizations. */
final class InstallationIdentifier
{
    /** The first organization controls statistics consent. */
    public static function getOrganizationId(Database $db): int
    {
        return (int)$db->queryPrepared('SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS)->fetchColumn();
    }

    public static function get(Database $db): ?string
    {
        $value = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_value <> ?
            ORDER BY CASE WHEN prf_org_id IS NULL THEN 0 ELSE 1 END, prf_org_id LIMIT 1',
            array('system_tenant_id', ''))->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Create once, including when statistics are disabled. Reuse a reviewed ID if supplied. */
    public static function ensure(Database $db, ?string $previewId = null): string
    {
        $db->startTransaction();
        try {
            // The core component survives organization deletion and serializes identity creation.
            $db->queryPrepared('SELECT com_id FROM ' . TBL_COMPONENTS . '
                WHERE com_name_intern = ? FOR UPDATE', array('CORE'));
            $value = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
                WHERE prf_name = ? AND prf_org_id IS NULL', array('system_tenant_id'))->fetchColumn();
            $tenantId = is_string($value) && $value !== '' ? $value : null;
            if ($tenantId === null) {
                // Preserve the identity of installations using the former organization preference.
                $legacyId = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
                    WHERE prf_name = ? AND prf_value <> ? ORDER BY prf_org_id LIMIT 1',
                    array('system_tenant_id', ''))->fetchColumn();
                $tenantId = is_string($legacyId) && $legacyId !== '' ? $legacyId
                    : ($previewId !== null && $previewId !== '' ? $previewId : Uuid::uuid4()->toString());
                $db->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . '
                    (prf_org_id, prf_name, prf_value) VALUES (NULL, ?, ?)', array('system_tenant_id', $tenantId));
            }
            $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . '
                WHERE prf_name = ? AND prf_org_id IS NOT NULL', array('system_tenant_id'));
            $db->endTransaction();

            return $tenantId;
        } catch (Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }
}
