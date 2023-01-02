<?php
/**
 ***********************************************************************************************
 * RSS feed for weblinks
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 *
 * Spezification of RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Headline for RSS-Feed
 *             (Default) Weblinks
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_WEBLINKS')));

// Check if RSS is active...
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if module is active or is public
if ((int) $gSettingsManager->get('enable_weblinks_module') !== 1) {
    // disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if ((int) $gSettingsManager->get('system_show_create_edit') === 1) {
    // show firstname and lastname of create and last change user
    $additionalFields = ' cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USER_DATA .' AS cre_surname
                                ON cre_surname.usd_usr_id = lnk_usr_id_create
                               AND cre_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                         LEFT JOIN '. TBL_USER_DATA .' AS cre_firstname
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
                         LEFT JOIN '. TBL_USERS .' AS cre_username
                                ON cre_username.usr_id = lnk_usr_id_create ';
    $queryParams = array();
}

// read weblinks from database
$sql = 'SELECT cat.*, lnk.*, '.$additionalFields.'
          FROM '. TBL_CATEGORIES .' AS cat
    INNER JOIN '.TBL_LINKS.' AS lnk
            ON cat_id = lnk_cat_id
               '.$additionalTables.'
         WHERE cat_type = \'LNK\'
           AND cat_org_id = ? -- $gCurrentOrgId
      ORDER BY lnk_timestamp_create DESC';
$queryParams[] = $gCurrentOrgId;
$statement = $gDb->queryPrepared($sql, $queryParams);

// start defining the RSS Feed

$orgLongname = $gCurrentOrganization->getValue('org_longname');

// create RSS feed object with channel information
$rss = new RssFeed(
    $orgLongname.' - '.$getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('SYS_LINK_COLLECTION_FROM', array($orgLongname)),
    $orgLongname
);

$weblink = new TableWeblink($gDb);

// Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch()) {
    // submit links to object
    $weblink->clear();
    $weblink->setArray($row);

    $lnkUrl = $weblink->getValue('lnk_url');

    // add entry to RSS feed
    $rss->addItem(
        $weblink->getValue('lnk_name'),
        '<a href="'.$lnkUrl.'" target="_blank">'.$lnkUrl.'</a><br /><br />'. $weblink->getValue('lnk_description'),
        SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/links/links.php', array('id' => (int) $weblink->getValue('lnk_id'))),
        $row['create_name'],
        \DateTime::createFromFormat('Y-m-d H:i:s', $weblink->getValue('lnk_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
        $weblink->getValue('cat_name')
    );
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
