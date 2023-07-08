<?php
/**
 ***********************************************************************************************
 * Admidio update check
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode         : 1 - (Default) check availability of updates
 *                2 - Show results of update check
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1, 'directOutput' => true));

if (!$gCurrentUser->isAdministrator()) {
    echo $gL10n->get('SYS_NO_RIGHTS');
    exit();
}

/**
 * Function to determine the update version
 * @param string $updateInfo
 * @param string $search
 * @return string
 */
function getUpdateVersion(string $updateInfo, string $search): string
{
    // Variablen festlegen
    $i = 0;
    $pointer = '';
    $updateVersion = '';
    $currentVersionStart = strpos($updateInfo, $search);
    $adding = strlen($search) - 1;

    // Version auslesen
    while ($pointer !== "\n") {
        ++$i;
        $updateVersion .= $pointer;
        $pointer = $updateInfo[$currentVersionStart + $adding + $i];
    }

    return trim($updateVersion, "\n\r");
}

/**
 * Function to check an update
 * @param string $currentVersion
 * @param string $checkStableVersion
 * @param string $checkBetaVersion
 * @param string $betaRelease
 * @param string $betaFlag
 * @return int
 */
function checkVersion(string $currentVersion, string $checkStableVersion, string $checkBetaVersion, string $betaRelease, string $betaFlag): int
{
    // Update state (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version)
    $update = 0;

    // Zunächst auf stabile Version prüfen
    if (version_compare($checkStableVersion, $currentVersion, '>')) {
        $update = 1;
    }

    // Check for beta version now
    $status = version_compare($checkBetaVersion, $currentVersion);
    if ($status === 1 || ($status === 0 && version_compare($betaRelease, $betaFlag, '>'))) {
        if ($update === 1) {
            $update = 3;
        } else {
            $update = 2;
        }
    }

    return $update;
}

// check availability of update information and if connected
// read available Admidio versions from server (text file)
// First select the method (CURL preferred)
$updateInfoUrl = ADMIDIO_HOMEPAGE . 'update.txt';
if (@file_get_contents($updateInfoUrl) === false) {
    // Admidio Versionen nicht auslesbar
    $stableVersion = 'n/a';
    $betaVersion   = 'n/a';
    $betaRelease   = '';

    $versionUpdate = 99;
} else {
    $updateInfo = file_get_contents($updateInfoUrl);

    // Admidio versions passed from server
    $stableVersion = getUpdateVersion($updateInfo, 'Version=');
    $betaVersion   = getUpdateVersion($updateInfo, 'Beta-Version=');
    $betaRelease   = getUpdateVersion($updateInfo, 'Beta-Release=');

    // No stable version available (actually impossible)
    if ($stableVersion === '') {
        $stableVersion = 'n/a';
    }

    // No beat version available
    if ($betaVersion === '') {
        $betaVersion = 'n/a';
        $betaRelease = '';
    }

    // check for update
    $versionUpdate = checkVersion(ADMIDIO_VERSION, $stableVersion, $betaVersion, $betaRelease, ADMIDIO_VERSION_BETA);
}

// Only continues in display mode, otherwise the current update state can be
// queried in the $versionUpdate variable.
// $versionUpdate (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version, 99 = No connection)
if ($getMode === 2) {
    // show update result
    if ($versionUpdate === 1) {
        $versionsText = $gL10n->get('SYS_NEW_VERSION_AVAILABLE');
    } elseif ($versionUpdate === 2) {
        $versionsText = $gL10n->get('SYS_NEW_BETA_AVAILABLE');
    } elseif ($versionUpdate === 3) {
        $versionsText = $gL10n->get('SYS_NEW_BOTH_AVAILABLE');
    } elseif ($versionUpdate === 99) {
        $admidioLink = '<a href="' . ADMIDIO_HOMEPAGE . 'download.php" target="_blank">Admidio</a>';
        $versionsText = $gL10n->get('SYS_CONNECTION_ERROR', array($admidioLink));
    } else {
        $versionsTextBeta = '';
        if (ADMIDIO_VERSION_BETA > 0) {
            $versionsTextBeta = 'Beta ';
        }

        $versionsText = $gL10n->get('SYS_USING_CURRENT_VERSION', array($versionsTextBeta));
    }

    echo '
        <p>' . $gL10n->get('SYS_INSTALLED') . ':&nbsp;' . ADMIDIO_VERSION_TEXT . '</p>
        <p>' . $gL10n->get('SYS_AVAILABLE') . ':&nbsp;
            <a class="btn" href="' . ADMIDIO_HOMEPAGE . 'download.php" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">'.
                '<i class="fas fa-link"></i>' . $stableVersion . '
            </a>
            <br />
            ' . $gL10n->get('SYS_AVAILABLE_BETA') . ': &nbsp;';

    if ($versionUpdate !== 99 && $betaVersion !== 'n/a') {
        echo '
            <a class="btn" href="' . ADMIDIO_HOMEPAGE . 'download.php" title="' . $gL10n->get('SYS_ADMIDIO_DOWNLOAD_PAGE') . '" target="_blank">'.
                '<i class="fas fa-link"></i>' . $betaVersion . ' Beta ' . $betaRelease . '
            </a>';
    } else {
        echo $betaVersion;
    }
    echo '
        </p>
        <strong>' . $versionsText . '</strong>';
}
