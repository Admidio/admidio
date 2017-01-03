<?php
/**
 ***********************************************************************************************
 * Admidio update check
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode         : 1 - (Default) check availability of updates
 *                2 - Show results of updatecheck
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1, 'directOutput' => true));

if($getMode === 3 && !$gCurrentUser->isAdministrator())
{
    echo $gL10n->get('SYS_NO_RIGHTS');
    exit();
}

/**
 * Funktion zur Ermittlung der Update-Version
 * @param string $updateInfo
 * @param string $search
 * @return string
 */
function getUpdateVersion($updateInfo, $search)
{
    // Variablen festlegen
    $i = 0;
    $pointer = '';
    $updateVersion = '';
    $currentVersionStart = strpos($updateInfo, $search);
    $adding = strlen($search) - 1;

    // Version auslesen
    while($pointer !== "\n")
    {
        ++$i;
        $updateVersion .= $pointer;
        $pointer = substr($updateInfo, $currentVersionStart + $adding + $i, 1);
    }

    return trim($updateVersion, "\n\r");
}

/**
 * Funktion zur Überprüfung eines Updates
 * @param string $currentVersion
 * @param string $checkStableVersion
 * @param string $checkBetaVersion
 * @param string $betaRelease
 * @param string $betaFlag
 * @return int
 */
function checkVersion($currentVersion, $checkStableVersion, $checkBetaVersion, $betaRelease, $betaFlag)
{
    // Updatezustand (0 = Kein Update, 1 = Neue stabile Version, 2 = Neue Beta-Version, 3 = Neue stabile + Beta Version)
    $update = 0;

    // Zunächst auf stabile Version prüfen
    if(version_compare($checkStableVersion, $currentVersion, '>'))
    {
        $update = 1;
    }

    // Jetzt auf Beta Version prüfen
    $status = version_compare($checkBetaVersion, $currentVersion);
    if($status === 1 || ($status === 0 && version_compare($betaRelease, $betaFlag, '>')))
    {
        if($update === 1)
        {
            $update = 3;
        }
        else
        {
            $update = 2;
        }
    }

    return $update;
}

// Erreichbarkeit der Updateinformation prüfen und bei Verbindung
// verfügbare Admidio Versionen vom Server einlesen (Textfile)
// Zunächst die Methode selektieren (CURL bevorzugt)
if(@file_get_contents(ADMIDIO_HOMEPAGE.'update.txt') === false)
{
    // Admidio Versionen nicht auslesbar
    $stableVersion = 'n/a';
    $betaVersion   = 'n/a';
    $betaRelease   = '';

    $versionUpdate = 99;
}
else
{
    $update_info = file_get_contents(ADMIDIO_HOMEPAGE.'update.txt');

    // Admidio Versionen vom Server übergeben
    $stableVersion = getUpdateVersion($update_info, 'Version=');
    $betaVersion   = getUpdateVersion($update_info, 'Beta-Version=');
    $betaRelease   = getUpdateVersion($update_info, 'Beta-Release=');

    // Keine Stabile Version verfügbar (eigentlich unmöglich)
    if($stableVersion === '')
    {
        $stableVersion = 'n/a';
    }

    // Keine Beatversion verfügbar
    if($betaVersion === '')
    {
        $betaVersion = 'n/a';
        $betaRelease = '';
    }

    // Auf Update prüfen
    $versionUpdate = checkVersion(ADMIDIO_VERSION, $stableVersion, $betaVersion, $betaRelease, ADMIDIO_VERSION_BETA);
}

// Nur im Anzeigemodus geht es weiter, ansonsten kann der aktuelle Updatestand
// in der Variable $version_update abgefragt werden.
// $version_update (0 = Kein Update, 1 = Neue stabile Version, 2 = Neue Beta-Version, 3 = Neue stabile + Beta Version, 99 = Keine Verbindung)

if($getMode === 2)
{
    /***********************************************************************/
    /* Updateergebnis anzeigen */
    /***********************************************************************/

    if($versionUpdate === 1)
    {
        $versionstext = $gL10n->get('UPD_NEW');
    }
    elseif($versionUpdate === 2)
    {
        $versionstext = $gL10n->get('UPD_NEW_BETA');
    }
    elseif($versionUpdate === 3)
    {
        $versionstext = $gL10n->get('UPD_NEW_BOTH');
    }
    elseif($versionUpdate === 99)
    {
        $admidioLink = '<a href="'.ADMIDIO_HOMEPAGE.'download.php" target="_blank">Admidio</a>';
        $versionstext = $gL10n->get('UPD_CONNECTION_ERROR', $admidioLink);
    }
    else
    {
        if(ADMIDIO_VERSION_BETA > 0)
        {
            $versionstextBeta = 'Beta ';
        }
        else
        {
            $versionstextBeta = ' ';
        }
        $versionstext = $gL10n->get('UPD_NO_NEW', $versionstextBeta);
    }

    echo '
        <p>'.$gL10n->get('UPD_CURRENT_VERSION').':&nbsp;'.ADMIDIO_VERSION_TEXT.'</p>
        <p>'.$gL10n->get('UPD_STABLE_VERSION').':&nbsp;
            <a class="btn" href="'.ADMIDIO_HOMEPAGE.'download.php" target="_blank">
                <img src="'.THEME_URL.'/icons/update_link.png" alt="'.$gL10n->get('UPD_ADMIDIO').'" />'.$stableVersion.'
            </a>
            <br />
            '.$gL10n->get('UPD_BETA_VERSION').': &nbsp;';

    if($versionUpdate !== 99 && $betaVersion !== 'n/a')
    {
        echo '
            <a class="btn" href="'.ADMIDIO_HOMEPAGE.'download.php" target="_blank">
                <img src="'.THEME_URL.'/icons/update_link.png" alt="'.$gL10n->get('UPD_ADMIDIO').'" />
                '.$betaVersion.'&nbsp;Beta&nbsp;'.$betaRelease.'
            </a>';
    }
    else
    {
        echo $betaVersion;
    }
    echo '
        </p>
        <strong>'.$versionstext.'</strong>';
}
