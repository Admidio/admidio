<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\TenantStatistics;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;
use Ramsey\Uuid\Uuid;
use Throwable;

/** Shared statistics preview, consent and delivery for the web installation and update. */
final class InstallationStatistics
{
    private const SESSION_DATA = 'installationStatisticsData';

    public static function addToForm(FormPresenter $form, InstallationPresenter $page, ?string $previousVersion = null): void
    {
        global $gSettingsManager, $gL10n;

        $tenantId = isset($gSettingsManager) && $gSettingsManager->has('system_tenant_id')
            ? $gSettingsManager->getString('system_tenant_id') : '';
        if ($tenantId === '') {
            $tenantId = $_SESSION[self::SESSION_DATA]['id'] ?? Uuid::uuid4()->toString();
        }
        $statistics = new TenantStatistics($tenantId, $previousVersion);
        $_SESSION[self::SESSION_DATA] = $statistics->getInfoData();
        $page->assignSmartyVariable('statisticsJson', $statistics->getJsonData($_SESSION[self::SESSION_DATA]));
        $form->addCheckbox('adm_send_statistics', $gL10n->get('SYS_SEND_ANONYMOUS_STATISTICS'), true);
        $page->addJavascript('
            $("#adm_send_statistics").on("change", function () {
                $("#adm_send_statistics_summary").prop("checked", this.checked);
            });
        ', true);
    }

    /** Copy the server-side snapshot before installation/update clears the session. */
    public static function selectedData(array $formValues): ?array
    {
        if (($formValues['adm_send_statistics'] ?? '0') !== '1') {
            return null;
        }
        $data = $_SESSION[self::SESSION_DATA] ?? null;
        if (!is_array($data)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return $data;
    }

    /** Persist the ID and send only after the installation/update succeeded. */
    public static function send(?array $data): void
    {
        global $gSettingsManager, $gLogger;

        if ($data === null) {
            return;
        }
        try {
            $gSettingsManager->set('system_tenant_id', $data['id']);
            $statistics = new TenantStatistics($data['id']);
            if (!$statistics->sent($data)) {
                $gLogger->warning('Anonymous installation statistics could not be sent.');
            }
        } catch (Throwable $exception) {
            // Telemetry must never turn a completed installation/update into a failure.
            $gLogger->warning('Anonymous installation statistics could not be sent.', array('exception' => $exception));
        }
    }
}
