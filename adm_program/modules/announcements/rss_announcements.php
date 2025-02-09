<?php
/**
 ***********************************************************************************************
 * RSS feed of announcements. Lists the newest 50 announcements.
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization_short_name : short name of the organization whose announcements should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

$getOrganizationShortName  = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');

// Check if RSS is active...
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if module is active or is public
if ((int) $gSettingsManager->get('announcements_module_enabled') !== 1) {
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$announcements = new ModuleAnnouncements();

if ($getOrganizationShortName !== '') {
    $organization = new Organization($gDb, $getOrganizationShortName);
    $organizationName = $organization->getValue('org_longname');
    $gCurrentUser->setOrganization($organization->getValue('org_id'));
} else {
    $organizationName = $gCurrentOrganization->getValue('org_longname');
}

// create RSS feed object with channel information
$rss = new RssFeed(
    $organizationName . ' - ' . $gL10n->get('SYS_ANNOUNCEMENTS'),
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('SYS_RECENT_ANNOUNCEMENTS_OF_ORGA', array($organizationName)),
    $organizationName
);

if ($announcements->getDataSetCount() > 0) {
    $announcement = new TableAnnouncement($gDb);
    $rows = $announcements->getDataSet(0, 50);

    // add the RSS items to the RssFeed object
    foreach ($rows['recordset'] as $row) {
        $announcement->clear();
        $announcement->setArray($row);

        // add entry to RSS feed
        $rss->addItem(
            $announcement->getValue('ann_headline'),
            $announcement->getValue('ann_description'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('ann_uuid' => $announcement->getValue('ann_uuid'))),
            $row['create_name'],
            DateTime::createFromFormat('Y-m-d H:i:s', $announcement->getValue('ann_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            $announcement->getValue('cat_name'),
            $announcement->getValue('ann_uuid')
        );
    }
}

$gCurrentUser->setOrganization($gCurrentOrgId);
$rss->getRssFeed();
