<?php

namespace AdmidioPlugin\Hello\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\LegacyConfig;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use RuntimeException;

final class UpdateStepsCode
{
    public static function setDatabase(Database $database): void
    {
    }

    public static function updateStep10Succeed(): void
    {
        $GLOBALS['helloPluginUpdateFirstStepRuns'] = ($GLOBALS['helloPluginUpdateFirstStepRuns'] ?? 0) + 1;
    }

    public static function updateStep100MustNotRun(): void
    {
        $GLOBALS['helloPluginUpdatePreviousStepRuns'] = ($GLOBALS['helloPluginUpdatePreviousStepRuns'] ?? 0) + 1;
    }

    public static function updateStep20FailWhileRequested(): void
    {
        if ($GLOBALS['helloPluginUpdateShouldFail'] ?? false) {
            throw new RuntimeException('The test plugin update step failed.');
        }
    }

    public static function updateStep30ImportLegacyConfig(): void
    {
        LegacyConfig::import('hello', array('plg_greeting' => array('hello_greeting', 'strval')));
    }

    public static function updateStep40TakeOverMenuEntry(): void
    {
        global $gDb;

        $sql = 'UPDATE ' . TBL_MENU . ' SET men_com_id = ? WHERE men_name_intern = ? AND men_com_id IS NULL';
        $gDb->queryPrepared($sql, array(PluginRegistry::getComponentId('hello'), 'hello_legacy'));
    }
}
