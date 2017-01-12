<?php
/**
 ***********************************************************************************************
 * RSS feed for weblinks
 *
 * @copyright 2004-2017 The Admidio Team
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
require_once('../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('LNK_WEBLINKS')));

// Check if RSS is active...
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if module is active or is public
if ($gPreferences['enable_weblinks_module'] != 1)
{
    // disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if($gPreferences['system_show_create_edit'] == 1)
{
    // show firstname and lastname of create and last change user
    $additionalFields = '
        cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USER_DATA .' cre_surname
                                ON cre_surname.usd_usr_id = lnk_usr_id_create
                               AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                         LEFT JOIN '. TBL_USER_DATA .' cre_firstname
                                ON cre_firstname.usd_usr_id = lnk_usr_id_create
                               AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
}
else
{
    // show username of create and last change user
    $additionalFields = ' cre_username.usr_login_name AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USERS .' cre_username
                                ON cre_username.usr_id = lnk_usr_id_create ';
}

// read weblinks from database
$sql = 'SELECT cat.*, lnk.*, '.$additionalFields.'
          FROM '. TBL_CATEGORIES .' cat, '.TBL_LINKS.' lnk
               '.$additionalTables.'
         WHERE lnk_cat_id = cat_id
           AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
           AND cat_type = \'LNK\'
      ORDER BY lnk_timestamp_create DESC';
$statement = $gDb->query($sql);

// start defining the RSS Feed

// create RSS feed object with channel information
$rss = new RSSfeed($gCurrentOrganization->getValue('org_longname').' - '.$getHeadline,
                   $gCurrentOrganization->getValue('org_homepage'),
                   $gL10n->get('LNK_LINKS_FROM',
                   $gCurrentOrganization->getValue('org_longname')),
                   $gCurrentOrganization->getValue('org_longname'));
$weblink = new TableWeblink($gDb);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch())
{
    // submit links to object
    $weblink->clear();
    $weblink->setArray($row);

    // set data for attributes of this entry
    $title = $weblink->getValue('lnk_name');
    $description = '<a href="'.$weblink->getValue('lnk_url').'" target="_blank">'.$weblink->getValue('lnk_url').'</a>'.
                   '<br /><br />'. $weblink->getValue('lnk_description');
    $link    = ADMIDIO_URL. FOLDER_MODULES.'/links/links.php?id='. $weblink->getValue('lnk_id');
    $author  = $row['create_name'];
    $pubDate = date('r', strtotime($weblink->getValue('lnk_timestamp_create')));

    // add entry to RSS feed
    $rss->addItem($title, $description, $link, $author, $pubDate);
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
