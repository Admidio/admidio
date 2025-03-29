<?php
/**
 ***********************************************************************************************
 * RSS feed of events. Lists the newest 50 events.
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization_short_name : short name of the organization whose events should be shown
 * *********************************************************************************************
 */

use Admidio\Events\Entity\Event;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;

require_once(__DIR__ . '/../adm_program/system/common.php');

try {
    $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');

    // Check if RSS is active...
    if (!$gSettingsManager->getBool('enable_rss')) {
        throw new Exception('SYS_RSS_DISABLED');
    }

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('events_module_enabled') !== 1) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if ($getOrganizationShortName !== '') {
        $organization = new Organization($gDb, $getOrganizationShortName);
        $organizationName = $organization->getValue('org_longname');
        $gCurrentUser->setOrganization($organization->getValue('org_id'));
    } else {
        $organizationName = $gCurrentOrganization->getValue('org_longname');
    }

    $events = new ModuleEvents();
    $events->setDateRange();
    $eventsResult = $events->getDataSet(0, 50);

    // create RSS feed object with channel information
    $rss = new RssFeed(
        $organizationName . ' - ' . $gL10n->get('SYS_EVENTS'),
        $gCurrentOrganization->getValue('org_homepage'),
        $gL10n->get('SYS_CURRENT_EVENTS_OF_ORGA', array($organizationName)),
        $organizationName
    );

    // add the RSS items to the RssFeed object
    if ($eventsResult['numResults'] > 0) {
        $event = new Event($gDb);
        foreach ($eventsResult['recordset'] as $row) {
            // move read out event data into event object
            $event->clear();
            $event->setArray($row);

            $eventUuid = $event->getValue('dat_uuid');
            $eventFrom = $event->getValue('dat_begin', $gSettingsManager->getString('system_date'));
            $eventTo = $event->getValue('dat_end', $gSettingsManager->getString('system_date'));
            $eventLocation = $event->getValue('dat_location');

            // set data for attributes of this entry
            $title = $eventFrom;
            if ($eventFrom !== $eventTo) {
                $title .= ' - ' . $eventTo;
            }
            $title .= ' ' . $event->getValue('dat_headline');

            // add additional information about the event to the description
            $descEventFrom = $eventFrom;
            $descEventTo = '';
            $description = $descEventFrom;

            if ($event->getValue('dat_all_day') == 0) {
                $descEventFrom .= ' ' . $event->getValue('dat_begin', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');

                if ($eventFrom !== $eventTo) {
                    $descEventTo = $eventTo . ' ';
                }
                $descEventTo .= ' ' . $event->getValue('dat_end', $gSettingsManager->getString('system_time')) . ' ' . $gL10n->get('SYS_CLOCK');
                $description = $gL10n->get('SYS_DATE_FROM_TO', array($descEventFrom, $descEventTo));
            } else {
                if ($eventFrom !== $eventTo) {
                    $description = $gL10n->get('SYS_DATE_FROM_TO', array($descEventFrom, $eventTo));
                }
            }

            if ($eventLocation !== '') {
                $description .= '<br /><br />' . $gL10n->get('SYS_VENUE') . ': ' . $eventLocation;
            }

            $description .= '<br /><br />' . $event->getValue('dat_description');

            // i-cal download link
            $description .= '<br /><br /><a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events_function.php', array('dat_uuid' => $eventUuid, 'mode' => 'export')) . '">' . $gL10n->get('SYS_ADD_EVENT_TO_CALENDAR') . '</a>';

            // add entry to RSS feed
            $rss->addItem(
                $title,
                $description,
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events.php', array('dat_uuid' => $eventUuid, 'view' => 'detail')),
                $row['create_name'],
                DateTime::createFromFormat('Y-m-d H:i:s', $event->getValue('dat_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
                $event->getValue('cat_name'),
                $event->getValue('dat_uuid')
            );
        }
    }

    $gCurrentUser->setOrganization($gCurrentOrgId);
    $rss->getRssFeed();
} catch (Exception $e) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($e->getMessage());
}
