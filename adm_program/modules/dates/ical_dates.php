<?php
/******************************************************************************
 * ical - Feed for events
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline - Headline für Ics-Feed
 *            (Default) Events
 * mode   1 - Textausgabe
 *        2 - Download
 * cat_id   - show all dates of calendar with this id
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric');

// prüfen ob das Modul überhaupt aktiviert ist
if($gPreferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // nur eingelochte Benutzer dürfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Nachschauen ob ical ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($gPreferences['enable_dates_ical'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_ICAL_DISABLED'));
}

//create Object
$dates = new ModuleDates();
// get parameters fom $_GET Array stored in class
$parameter = $dates->getParameters();
// set mode, viewmode, startdate and enddate manually
$parameter['mode'] = 2;
$parameter['view_mode'] = 'period';
$parameter['date_from'] = date('Y-m-d', time()-$gPreferences['dates_ical_days_past']*86400);
$parameter['date_to'] = date('Y-m-d', time()+$gPreferences['dates_ical_days_future']*86400);

// read events for output
$datesResult = $dates->getDataset();

//Headline für Dateinamen
if($dates->getCatId() > 0)
{
    $calendar = new TableCategory($gDb, $dates->getCatId());
    $getHeadline.= '_'. $calendar->getValue('cat_name');
}

$date = new TableDate($gDb);
$iCal = $date->getIcalHeader();

if($datesResult['numResults'] > 0)
{
    $date = new TableDate($gDb);
    foreach($datesResult['recordset'] as $row)
    {
        $date->clear();
        $date->setArray($row);
        $iCal .= $date->getIcalVEvent($_SERVER['HTTP_HOST']);
    }
}

$iCal .= $date->getIcalFooter();

if($parameter['mode'] == 2)
{
    // for IE the filename must have special chars in hexadecimal
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
    {
        $getHeadline = urlencode($getHeadline);
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="'. $getHeadline. '.ics"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}
echo $iCal;
