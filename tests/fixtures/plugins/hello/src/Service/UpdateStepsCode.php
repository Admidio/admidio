<?php

namespace AdmidioPlugin\Hello\Service;

use Admidio\Infrastructure\Database;
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
}
