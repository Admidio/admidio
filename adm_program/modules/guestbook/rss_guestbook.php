<?php
/**
 ***********************************************************************************************
 * RSS feed of guestbook. Lists the newest 50 guestbook entries.
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization_short_name : short name of the organization whose guestbook entries should be shown
 * *********************************************************************************************
 */

use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;

require_once(__DIR__ . '/../../system/common.php');

try {
    $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');

    // Check if RSS is active...
    if (!$gSettingsManager->getBool('enable_rss')) {
        throw new Exception('SYS_RSS_DISABLED');
    }

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('enable_guestbook_module') !== 1) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if ($getOrganizationShortName !== '') {
        $organization = new Organization($gDb, $getOrganizationShortName);
        $organizationName = $organization->getValue('org_longname');
        $organizationID = $organization->getValue('org_id');
    } else {
        $organizationName = $gCurrentOrganization->getValue('org_longname');
        $organizationID = $gCurrentOrgId;
    }

    // get the latest 10 guestbook entries
    $sql = 'SELECT *
          FROM ' . TBL_GUESTBOOK . '
         WHERE gbo_org_id = ? -- $organizationID
           AND gbo_locked = false
      ORDER BY gbo_timestamp_create DESC
         LIMIT 50';
    $statement = $gDb->queryPrepared($sql, array($organizationID));

    // create RSS feed object with channel information
    $rss = new RssFeed(
        $organizationName . ' - ' . $gL10n->get('GBO_GUESTBOOK'),
        $gCurrentOrganization->getValue('org_homepage'),
        $gL10n->get('GBO_LATEST_GUESTBOOK_ENTRIES_OF_ORGA', array($organizationName)),
        $organizationName
    );
    $guestbook = new Topic($gDb);

    // add the RSS items to the RssFeed object
    while ($row = $statement->fetch()) {
        $guestbook->clear();
        $guestbook->setArray($row);

        // add entry to RSS feed
        $rss->addItem(
            $guestbook->getValue('gbo_name'),
            $guestbook->getValue('gbo_text'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('id' => (int)$guestbook->getValue('gbo_id'))),
            $guestbook->getValue('gbo_name'),
            \DateTime::createFromFormat('Y-m-d H:i:s', $guestbook->getValue('gbo_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            '',
            $guestbook->getValue('gbo_uuid')
        );
    }

    $rss->getRssFeed();
} catch (Exception $e) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($e->getMessage());
}
