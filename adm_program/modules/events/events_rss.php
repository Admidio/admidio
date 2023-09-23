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
 * headline - Headline for the rss feed
 *             (Default) Events
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_EVENTS')));

// Check if RSS is active...
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('events_module_enabled') !== 1) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create Object
$events = new ModuleEvents();
$events->setDateRange();

// read events for output
$eventsResult = $events->getDataSet(0, 10);

// ab hier wird der RSS-Feed zusammengestellt

$orgLongname = $gCurrentOrganization->getValue('org_longname');
// create RSS feed object with channel information
$rss  = new RssFeed(
    $orgLongname . ' - ' . $getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('DAT_CURRENT_DATES_OF_ORGA', array($orgLongname)),
    $orgLongname
);
$event = new Event($gDb);

// Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
if ($eventsResult['numResults'] > 0) {
    $event = new Event($gDb);
    foreach ($eventsResult['recordset'] as $row) {
        // ausgelesene Termindaten in Date-Objekt schieben
        $event->clear();
        $event->setArray($row);

        $eventUuid     = $event->getValue('dat_uuid');
        $eventFrom     = $event->getValue('dat_begin', $gSettingsManager->getString('system_date'));
        $eventTo       = $event->getValue('dat_end', $gSettingsManager->getString('system_date'));
        $eventLocation = $event->getValue('dat_location');

        // set data for attributes of this entry
        $title = $eventFrom;
        if ($eventFrom !== $eventTo) {
            $title .= ' - ' . $eventTo;
        }
        $title  .= ' ' . $event->getValue('dat_headline');

        // add additional information about the event to the description
        $descEventFrom = $eventFrom;
        $descEventTo   = '';
        $description  = $descEventFrom;

        if ($event->getValue('dat_all_day') == 0) {
            $descEventFrom .= ' ' . $event->getValue('dat_begin', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');

            if ($eventFrom !== $eventTo) {
                $descEventTo = $eventTo . ' ';
            }
            $descEventTo  .= ' ' . $event->getValue('dat_end', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');
            $description = $gL10n->get('SYS_DATE_FROM_TO', array($descEventFrom, $descEventTo));
        } else {
            if ($eventFrom !== $eventTo) {
                $description = $gL10n->get('SYS_DATE_FROM_TO', array($descEventFrom, $eventTo));
            }
        }

        if ($eventLocation !== '') {
            $description .= '<br /><br />' . $gL10n->get('DAT_LOCATION') . ': ' . $eventLocation;
        }

        $description .= '<br /><br />' . $event->getValue('dat_description');

        // i-cal downloadlink
        $description .= '<br /><br /><a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events_function.php', array('dat_uuid' => $eventUuid, 'mode' => '6')).'">' . $gL10n->get('DAT_ADD_DATE_TO_CALENDAR') . '</a>';

        // add entry to RSS feed
        $rss->addItem(
            $title,
            $description,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events.php', array('dat_uuid' => $eventUuid, 'view' => 'detail')),
            $row['create_name'],
            \DateTime::createFromFormat('Y-m-d H:i:s', $event->getValue('dat_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            $event->getValue('cat_name')
        );
    }
}
// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
