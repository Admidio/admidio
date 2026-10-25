<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\InstallationIdentifier;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;
use Ramsey\Uuid\Uuid;
use Throwable;

/** Shared statistics preview, consent and delivery for the web installation and update. */
final class StatisticsConsent
{
    private const SESSION_DATA = 'installationStatisticsData';

    public static function addToForm(FormPresenter $form, InstallationPresenter $page, ?string $previousVersion = null): void
    {
        global $gDb, $gL10n, $gSettingsManager;

        // Fresh installations have no organization/preferences tables yet.
        $tenantId = $previousVersion !== null ? InstallationIdentifier::get($gDb) : null;
        if ($tenantId === null) {
            $tenantId = $_SESSION[self::SESSION_DATA]['id'] ?? Uuid::uuid4()->toString();
        }
        $statistics = new InstallationStatistics($tenantId, $previousVersion);
        $_SESSION[self::SESSION_DATA] = $statistics->getInfoData();
        $page->assignSmartyVariable('statisticsJson', $statistics->getJsonData($_SESSION[self::SESSION_DATA]));
        $enabled = !isset($gSettingsManager) || !$gSettingsManager->has('system_statistics_enabled')
            || $gSettingsManager->getBool('system_statistics_enabled');
        $page->assignSmartyVariable('statisticsEnabled', $enabled);
        $form->addCheckbox('adm_send_statistics', $gL10n->get('SYS_SEND_ANONYMOUS_STATISTICS'), $enabled);
        $page->addJavascript('
            $("#adm_send_statistics").on("change", function () {
                $("#adm_send_statistics_summary").prop("checked", this.checked);
            });
        ', true);
    }

    /** Pending identity is retained even if the user opted out of sending statistics. */
    public static function tenantId(): ?string
    {
        return $_SESSION[self::SESSION_DATA]['id'] ?? null;
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

    /** Send only after the installation/update succeeded; ID creation belongs to its lifecycle. */
    public static function send(?array $data): void
    {
        global $gLogger;

        if ($data === null) {
            return;
        }
        try {
            $statistics = new InstallationStatistics($data['id']);
            if (!$statistics->sent($data)) {
                $gLogger->warning('Anonymous installation statistics could not be sent.');
            }
        } catch (Throwable $exception) {
            // Telemetry must never turn a completed installation/update into a failure.
            $gLogger->warning('Anonymous installation statistics could not be sent.', array('exception' => $exception));
        }
    }

    /** Persist the reviewed choice after installation/update has created the preferences. */
    public static function savePreference(bool $enabled): void
    {
        global $gDb;

        $settings = new SettingsManager($gDb, InstallationIdentifier::getOrganizationId($gDb));
        $settings->set('system_statistics_enabled', $enabled ? '1' : '0');
    }
}
