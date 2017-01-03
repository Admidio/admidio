<?php
/**
 ***********************************************************************************************
 * RSS feed of events
 *
 * @copyright 2004-2017 The Admidio Team
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
 * headline  - Headline for the rss feed
 *             (Default) Events
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create Object
$dates = new ModuleDates();
$dates->setDateRange();

// read events for output
$datesResult = $dates->getDataSet(0, 10);

// ab hier wird der RSS-Feed zusammengestellt

// create RSS feed object with channel information
$rss  = new RSSfeed($gCurrentOrganization->getValue('org_longname'). ' - '. $getHeadline,
            $gCurrentOrganization->getValue('org_homepage'),
            $gL10n->get('DAT_CURRENT_DATES_OF_ORGA', $gCurrentOrganization->getValue('org_longname')),
            $gCurrentOrganization->getValue('org_longname'));
$date = new TableDate($gDb);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
if($datesResult['numResults'] > 0)
{
    $date = new TableDate($gDb);
    foreach($datesResult['recordset'] as $row)
    {
        // ausgelesene Termindaten in Date-Objekt schieben
        $date->clear();
        $date->setArray($row);

        // set data for attributes of this entry
        $title = $date->getValue('dat_begin', $gPreferences['system_date']);
        if($date->getValue('dat_begin', $gPreferences['system_date']) !== $date->getValue('dat_end', $gPreferences['system_date']))
        {
            $title = $title. ' - '. $date->getValue('dat_end', $gPreferences['system_date']);
        }
        $title   = $title. ' '. $date->getValue('dat_headline');
        $link    = ADMIDIO_URL.FOLDER_MODULES.'/dates/dates.php?id='. $date->getValue('dat_id').'&view=detail';
        $author  = $row['create_name'];
        $pubDate = date('r', strtotime($date->getValue('dat_timestamp_create')));

        // add additional information about the event to the description
        $descDateTo   = '';
        $descDateFrom = $date->getValue('dat_begin', $gPreferences['system_date']);
        $description  = $descDateFrom;

        if ($date->getValue('dat_all_day') == 0)
        {
            $descDateFrom = $descDateFrom. ' '. $date->getValue('dat_begin', $gPreferences['system_time']).' '.$gL10n->get('SYS_CLOCK');

            if($date->getValue('dat_begin', $gPreferences['system_date']) !== $date->getValue('dat_end', $gPreferences['system_date']))
            {
                $descDateTo = $date->getValue('dat_end', $gPreferences['system_date']). ' ';
            }
            $descDateTo  = $descDateTo. ' '. $date->getValue('dat_end', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK');
            $description = $gL10n->get('SYS_DATE_FROM_TO', $descDateFrom, $descDateTo);
        }
        else
        {
            if($date->getValue('dat_begin', $gPreferences['system_date']) !== $date->getValue('dat_end', $gPreferences['system_date']))
            {
                $description = $gL10n->get('SYS_DATE_FROM_TO', $descDateFrom, $date->getValue('dat_end', $gPreferences['system_date']));
            }
        }

        if ($date->getValue('dat_location') !== '')
        {
            $description = $description. '<br /><br />'.$gL10n->get('DAT_LOCATION').':&nbsp;'. $date->getValue('dat_location');
        }

        $description = $description. '<br /><br />'. $date->getValue('dat_description');

        // i-cal downloadlink
        $description = $description. '<br /><br /><a href="'.ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?dat_id='.$date->getValue('dat_id').'&mode=6">'.$gL10n->get('DAT_ADD_DATE_TO_CALENDAR').'</a>';

        // add entry to RSS feed
        $rss->addItem($title, $description, $link, $author, $pubDate);
    }
}
// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
