<?php

namespace Admidio\Infrastructure;

use Admidio\Preferences\ValueObject\SettingsManager;
use Ramsey\Uuid\Uuid;
use Throwable;

/** Installation identity, stored with the organization with the smallest ID. */
final class TenantIdentifier
{
    public static function get(Database $db): ?string
    {
        $value = $db->queryPrepared('SELECT prf_value FROM ' . TBL_PREFERENCES . '
            WHERE prf_name = ? AND prf_org_id = (SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS . ')',
            array('system_tenant_id'))->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Create once, including when statistics are disabled. Reuse a reviewed ID if supplied. */
    public static function ensure(Database $db, ?string $previewId = null): string
    {
        $db->startTransaction();
        try {
            // Serialize ID creation so concurrent callers cannot generate different identities.
            $orgId = (int) $db->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS . '
                ORDER BY org_id LIMIT 1 FOR UPDATE')->fetchColumn();
            if ($orgId === 0) {
                throw new Exception('SYS_TENANT_ID_NOT_INITIALIZED');
            }
            $settings = new SettingsManager($db, $orgId);
            $tenantId = $settings->has('system_tenant_id') ? $settings->getString('system_tenant_id') : '';
            if ($tenantId === '') {
                $tenantId = $previewId !== null && $previewId !== '' ? $previewId : Uuid::uuid4()->toString();
                $settings->set('system_tenant_id', $tenantId);
            }
            $db->endTransaction();

            return $tenantId;
        } catch (Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }
}
