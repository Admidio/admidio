<?php
/**
 ***********************************************************************************************
 * RSS feed of all weblinks.
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization : Short name of the organization whose topics should be shown in the RSS feed
 * *********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;
use Admidio\Weblinks\Entity\Weblink;

require_once(__DIR__ . '/../adm_program/system/common.php');

try {
    $getOrganization = admFuncVariableIsValid($_GET, 'organization', 'string');

    // Check if RSS is active...
    if (!$gSettingsManager->getBool('enable_rss')) {
        throw new Exception('SYS_RSS_DISABLED');
    }

    // check if module is active or is public
    if ((int)$gSettingsManager->get('enable_weblinks_module') !== 1) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if ($getOrganization !== '') {
        $organization = new Organization($gDb, $getOrganization);
        $organizationName = $organization->getValue('org_longname');
        $organizationID = $organization->getValue('org_id');
    } else {
        $organizationName = $gCurrentOrganization->getValue('org_longname');
        $organizationID = $gCurrentOrgId;
    }

    if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
        // show firstname and lastname of create and last change user
        $additionalFields = ' cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
        $additionalTables = '
                         LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                                ON cre_surname.usd_usr_id = lnk_usr_id_create
                               AND cre_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                         LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                                ON cre_firstname.usd_usr_id = lnk_usr_id_create
                               AND cre_firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')';
        $queryParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        );
    } else {
        // show username of create and last change user
        $additionalFields = ' cre_username.usr_login_name AS create_name ';
        $additionalTables = '
                         LEFT JOIN ' . TBL_USERS . ' AS cre_username
                                ON cre_username.usr_id = lnk_usr_id_create ';
        $queryParams = array();
    }

    // read weblinks from database
    $sql = 'SELECT cat.*, lnk.*, ' . $additionalFields . '
          FROM ' . TBL_CATEGORIES . ' AS cat
    INNER JOIN ' . TBL_LINKS . ' AS lnk
            ON cat_id = lnk_cat_id
               ' . $additionalTables . '
         WHERE cat_type = \'LNK\'
           AND cat_org_id = ? -- $organizationID
      ORDER BY lnk_timestamp_create DESC';
    $queryParams[] = $organizationID;
    $statement = $gDb->queryPrepared($sql, $queryParams);

    // create RSS feed object with channel information
    $rss = new RssFeed(
        $organizationName . ' - ' . $gL10n->get('SYS_WEBLINKS'),
        $gCurrentOrganization->getValue('org_homepage'),
        $gL10n->get('SYS_LINK_COLLECTION_FROM', array($organizationName)),
        $organizationName
    );

    $weblink = new Weblink($gDb);

    // add the RSS items to the RssFeed object
    while ($row = $statement->fetch()) {
        // submit links to object
        $weblink->clear();
        $weblink->setArray($row);

        $lnkUrl = $weblink->getValue('lnk_url');

        // add entry to RSS feed
        $rss->addItem(
            $weblink->getValue('lnk_name'),
            '<a href="' . $lnkUrl . '" target="_blank">' . $lnkUrl . '</a><br /><br />' . $weblink->getValue('lnk_description'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links.php', array('id' => (int)$weblink->getValue('lnk_id'))),
            $row['create_name'],
            DateTime::createFromFormat('Y-m-d H:i:s', $weblink->getValue('lnk_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            $weblink->getValue('cat_name'),
            $weblink->getValue('lnk_uuid')
        );
    }

    $rss->getRssFeed();
} catch (Exception $e) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($e->getMessage());
}
