<?php
/**
 ***********************************************************************************************
 * Generate iCal file for all events
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
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
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

// Date range defined in preferences
if ($getDateFrom === '') {
    try {
        $now = new DateTime();
        $dayOffsetPast = new DateInterval('P' . $gSettingsManager->getInt('dates_ical_days_past') . 'D');
        $dayOffsetFuture = new DateInterval('P' . $gSettingsManager->getInt('dates_ical_days_future') . 'D');
        $getDateFrom = $now->sub($dayOffsetPast)->format('Y-m-d');
        $getDateTo = $now->add($dayOffsetFuture)->format('Y-m-d');
    } catch (Exception $e) {
        $gMessage->show($e->getMessage());
        // => EXIT
    }
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

// If iCal enabled and module is public
if (!$gSettingsManager->getBool('enable_dates_ical')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_ICAL_DISABLED'));
    // => EXIT
}

$filename = FileSystemUtils::getSanitizedPathEntry($gCurrentOrganization->getValue('org_longname'));
$calendar = new TableCategory($gDb);

if ($getCatUuid !== '') {
    $calendar->readDataByUuid($getCatUuid);
    $filename .= '-'.$calendar->getValue('cat_name');
}

try {
    $dates = new ModuleDates();// set mode, viewmode, calendar, startdate and enddate manually
    $dates->setParameter('view_mode', 'period');
    $dates->setParameter('cat_id', $calendar->getValue('cat_id'));
    $dates->setDateRange($getDateFrom, $getDateTo);// read events for output
    $datesResult = $dates->getDataSet(0, 0);// get parameters fom $_GET Array stored in class
    $parameters = $dates->getParameters();

    $date = new TableDate($gDb);
    $iCal = $date->getIcalHeader();
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

if ($datesResult['numResults'] > 0) {
    $date = new TableDate($gDb);
    foreach ($datesResult['recordset'] as $row) {
        $date->clear();
        $date->setArray($row);
        $iCal .= $date->getIcalVEvent(DOMAIN);
    }
}

$iCal .= $date->getIcalFooter();

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="'. $filename. '.ics"');

// necessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

echo $iCal;
