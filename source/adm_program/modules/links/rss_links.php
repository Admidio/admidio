<?php
/******************************************************************************
 * RSS - Feed fuer Links
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer alle Links
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Ueberschrift fuer den RSS-Feed
 *             (Default) Weblinks
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/rss.php');
require_once('../../system/classes/table_weblink.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
}

// pruefen ob das Modul ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($gPreferences['enable_weblinks_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Initialize and check the parameters
$get_headline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('LNK_WEBLINKS'));

// alle Links aus der DB fischen...
$sql = 'SELECT cat.*, lnk.*,
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '. TBL_CATEGORIES .' cat, '. TBL_LINKS. ' lnk
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = lnk_usr_id_create
           AND cre_surname.usd_usf_id = '.$gCurrentUser->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = lnk_usr_id_create
           AND cre_firstname.usd_usf_id = '.$gCurrentUser->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = lnk_usr_id_change
           AND cha_surname.usd_usf_id = '.$gCurrentUser->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = lnk_usr_id_change
           AND cha_firstname.usd_usf_id = '.$gCurrentUser->getProperty('FIRST_NAME', 'usf_id').'
         WHERE lnk_cat_id = cat_id
           AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
           AND cat_type = "LNK"
         ORDER BY lnk_timestamp_create DESC';
$result = $gDb->query($sql);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed('http://'. $gCurrentOrganization->getValue('org_homepage'), $gCurrentOrganization->getValue('org_longname'). ' - '.$get_headline, 
		$gL10n->get('LNK_LINKS_FROM', $gCurrentOrganization->getValue('org_longname')));
$weblink = new TableWeblink($gDb);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $gDb->fetch_object($result))
{
    // ausgelesene Linkdaten in Weblink-Objekt schieben
    $weblink->clear();
    $weblink->setArray($row);

    // Die Attribute fuer das Item zusammenstellen
    $title = $weblink->getValue('lnk_name');
    $link  = $g_root_path. '/adm_program/modules/links/links.php?id='. $weblink->getValue('lnk_id');
    $description = '<a href="'.$weblink->getValue('lnk_url').'" target="_blank"><b>'.$weblink->getValue('lnk_name').'</b></a>';

    // Beschreibung und Link zur Homepage ausgeben
    $description = $description. '<br /><br />'. $weblink->getDescription('HTML'). 
                   '<br /><br /><a href="'.$link.'">'. $gL10n->get('SYS_LINK_TO', $gCurrentOrganization->getValue('org_homepage')). '</a>';

    // Den Autor und letzten Bearbeiter des Links ermitteln und ausgeben
    $description = $description. '<br /><br /><i>'.$gL10n->get('SYS_CREATED_BY', $row->create_firstname. ' '. $row->create_surname, $weblink->getValue('lnk_timestamp_create')). '</i>';

    if($weblink->getValue('lnk_usr_id_change') > 0)
    {
        $description = $description. '<br /><i>'.$gL10n->get('SYS_LAST_EDITED_BY', $row->change_firstname. ' '. $row->change_surname, $weblink->getValue('lnk_timestamp_change')). '</i>';
    }

    $pubDate = date('r', strtotime($weblink->getValue('lnk_timestamp_create')));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>