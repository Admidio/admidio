<?php
/**
 ***********************************************************************************************
 * ical - Feed for events
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Headline für Ics-Feed
 *             (Default) Events
 * cat_uuid  - show all dates of calendar with this UUID
 * date_from - set the minimum date of the events that should be shown
 *             if this parameter is not set than the actual date is set
 * date_to   - set the maximum date of the events that should be shown
 *             if this parameter is not set than this date is set to 31.12.9999
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

// Daterange defined in preferences
if ($getDateFrom == '') {
    $now = new \DateTime();
    $dayOffsetPast   = new \DateInterval('P'.$gSettingsManager->getInt('dates_ical_days_past').'D');
    $dayOffsetFuture = new \DateInterval('P'.$gSettingsManager->getInt('dates_ical_days_future').'D');
    $getDateFrom = $now->sub($dayOffsetPast)->format('Y-m-d');
    $getDateTo   = $now->add($dayOffsetFuture)->format('Y-m-d');
}

// Message if module is disabled
if ((int) $gSettingsManager->get('enable_dates_module') === 0) {
    // Module disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_dates_module') === 2) {
    // only with valid login
    require(__DIR__ . '/../../system/login_valid.php');
}

// If Ical enabled and module is public
if (!$gSettingsManager->getBool('enable_dates_ical')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_ICAL_DISABLED'));
    // => EXIT
}

$calendar = new TableCategory($gDb);

if (strlen($getCatUuid) > 1) {
    $calendar->readDataByUuid($getCatUuid);
    $getHeadline .= '_'.$calendar->getValue('cat_name');
}

// create Object
$dates = new ModuleDates();
// set mode, viewmode, calendar, startdate and enddate manually
$dates->setParameter('view_mode', 'period');
$dates->setParameter('cat_id', $calendar->getValue('cat_id'));
$dates->setDateRange($getDateFrom, $getDateTo);
// read events for output
$datesResult = $dates->getDataSet(0, 0);
// get parameters fom $_GET Array stored in class
$parameters = $dates->getParameters();

$date = new TableDate($gDb);
$iCal = $date->getIcalHeader();

if ($datesResult['numResults'] > 0) {
    $date = new TableDate($gDb);
    foreach ($datesResult['recordset'] as $row) {
        $date->clear();
        $date->setArray($row);
        $iCal .= $date->getIcalVEvent(DOMAIN);
    }
}

$iCal .= $date->getIcalFooter();

$filename = FileSystemUtils::getSanitizedPathEntry($getHeadline) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="'. $filename. '"');

// necessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

echo $iCal;
