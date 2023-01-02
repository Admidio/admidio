<?php
/**
 ***********************************************************************************************
 * RSS feed of events
 *
 * @copyright 2004-2023 The Admidio Team
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

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_dates_module') !== 1) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create Object
$dates = new ModuleDates();
$dates->setDateRange();

// read events for output
$datesResult = $dates->getDataSet(0, 10);

// ab hier wird der RSS-Feed zusammengestellt

$orgLongname = $gCurrentOrganization->getValue('org_longname');
// create RSS feed object with channel information
$rss  = new RssFeed(
    $orgLongname . ' - ' . $getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('DAT_CURRENT_DATES_OF_ORGA', array($orgLongname)),
    $orgLongname
);
$date = new TableDate($gDb);

// Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
if ($datesResult['numResults'] > 0) {
    $date = new TableDate($gDb);
    foreach ($datesResult['recordset'] as $row) {
        // ausgelesene Termindaten in Date-Objekt schieben
        $date->clear();
        $date->setArray($row);

        $dateUuid     = $date->getValue('dat_uuid');
        $dateFrom     = $date->getValue('dat_begin', $gSettingsManager->getString('system_date'));
        $dateTo       = $date->getValue('dat_end', $gSettingsManager->getString('system_date'));
        $dateLocation = $date->getValue('dat_location');

        // set data for attributes of this entry
        $title = $dateFrom;
        if ($dateFrom !== $dateTo) {
            $title .= ' - ' . $dateTo;
        }
        $title  .= ' ' . $date->getValue('dat_headline');

        // add additional information about the event to the description
        $descDateFrom = $dateFrom;
        $descDateTo   = '';
        $description  = $descDateFrom;

        if ($date->getValue('dat_all_day') == 0) {
            $descDateFrom .= ' ' . $date->getValue('dat_begin', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');

            if ($dateFrom !== $dateTo) {
                $descDateTo = $dateTo . ' ';
            }
            $descDateTo  .= ' ' . $date->getValue('dat_end', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');
            $description = $gL10n->get('SYS_DATE_FROM_TO', array($descDateFrom, $descDateTo));
        } else {
            if ($dateFrom !== $dateTo) {
                $description = $gL10n->get('SYS_DATE_FROM_TO', array($descDateFrom, $dateTo));
            }
        }

        if ($dateLocation !== '') {
            $description .= '<br /><br />' . $gL10n->get('DAT_LOCATION') . ': ' . $dateLocation;
        }

        $description .= '<br /><br />' . $date->getValue('dat_description');

        // i-cal downloadlink
        $description .= '<br /><br /><a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates_function.php', array('dat_uuid' => $dateUuid, 'mode' => '6')).'">' . $gL10n->get('DAT_ADD_DATE_TO_CALENDAR') . '</a>';

        // add entry to RSS feed
        $rss->addItem(
            $title,
            $description,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php', array('dat_uuid' => $dateUuid, 'view' => 'detail')),
            $row['create_name'],
            \DateTime::createFromFormat('Y-m-d H:i:s', $date->getValue('dat_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            $date->getValue('cat_name')
        );
    }
}
// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
