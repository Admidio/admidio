<?php

namespace Admidio\Infrastructure;

use Ramsey\Uuid\Uuid;

/** Installation identity stored independently of organizations. */
final class InstallationIdentifier
{
    public static function get(Database $db): ?string
    {
        $globalId = (new InstallationSettings($db))->get('system_tenant_id');
        if ($globalId !== null && $globalId !== '') {
            return $globalId;
        }
        // The updater may preview an installation before its legacy identity has been migrated.
        $value = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_value <> ?
            ORDER BY CASE WHEN prf_org_id IS NULL THEN 0 ELSE 1 END, prf_org_id LIMIT 1',
            array('system_tenant_id', ''))->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Create once, including when statistics are disabled. Reuse a reviewed ID if supplied. */
    public static function ensure(Database $db, ?string $previewId = null): string
    {
        return (new InstallationSettings($db))->withLock(function (InstallationSettings $settings) use ($db, $previewId): string {
            $tenantId = $settings->get('system_tenant_id');
            if ($tenantId === null || $tenantId === '') {
                // Preserve the identity of installations using the former organization preference.
                $legacyId = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
                    WHERE prf_name = ? AND prf_value <> ? ORDER BY prf_org_id LIMIT 1',
                    array('system_tenant_id', ''))->fetchColumn();
                $tenantId = is_string($legacyId) && $legacyId !== '' ? $legacyId
                    : ($previewId !== null && $previewId !== '' ? $previewId : Uuid::uuid4()->toString());
                $settings->set('system_tenant_id', $tenantId);
            }
            $db->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . '
                WHERE prf_name = ? AND prf_org_id IS NOT NULL', array('system_tenant_id'));

            return $tenantId;
        });
    }
}
