<?php
/******************************************************************************
 * RSS - Feed fuer Links
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer alle Links
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/ubb_parser.php');
require('../../system/classes/rss.php');


// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($g_preferences["enable_rss"] != 1)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show('rss_disabled');
}

// pruefen ob das Modul ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($g_preferences['enable_weblinks_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

// Nachschauen ob BB-Code aktiviert ist...
if ($g_preferences['enable_bbcode'] == 1)
{
    //BB-Parser initialisieren
    $bbcode = new ubbParser();
}

// alle Links aus der DB fischen...
$sql = 'SELECT cat.*, lnk.*,
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '. TBL_CATEGORIES .' cat, '. TBL_LINKS. ' lnk
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = lnk_usr_id_create
           AND cre_surname.usd_usf_id = '.$g_current_user->getProperty('Nachname', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = lnk_usr_id_create
           AND cre_firstname.usd_usf_id = '.$g_current_user->getProperty('Vorname', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = lnk_usr_id_change
           AND cha_surname.usd_usf_id = '.$g_current_user->getProperty('Nachname', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = lnk_usr_id_change
           AND cha_firstname.usd_usf_id = '.$g_current_user->getProperty('Vorname', 'usf_id').'
         WHERE lnk_cat_id = cat_id
           AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
           AND cat_type = "LNK"
         ORDER BY lnk_timestamp_create DESC';
$result = $g_db->query($sql);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed('http://'. $g_current_organization->getValue('org_homepage'), $g_current_organization->getValue('org_longname'). ' - Links', 'Linksammlung von '. $g_current_organization->getValue('org_longname'));


// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $g_db->fetch_object($result))
{
    // Die Attribute fuer das Item zusammenstellen
    $title = $row->lnk_name;
    $link  = $g_root_path. '/adm_program/modules/links/links.php?id='. $row->lnk_id;
    $description = '<a href="'.$row->lnk_url.'" target="_blank"><b>'.$row->lnk_name.'</b></a>';


    // Die Ankuendigungen eventuell durch den UBB-Parser schicken
    if ($g_preferences['enable_bbcode'] == 1)
    {
        $description = $description. '<br /><br />'. $bbcode->parse($row->lnk_description);
    }
    else
    {
        $description = $description. '<br /><br />'. nl2br($row->lnk_description);
    }

    $description = $description. '<br /><br /><a href="'.$link.'">Link auf '. $g_current_organization->getValue('org_homepage'). '</a>';

    // Den Autor und letzten Bearbeiter des Links ermitteln und ausgeben
    $description = $description. '<br /><br /><i>Angelegt von '. $row->create_firstname. " ". $row->create_surname.
                                 ' am '. mysqldatetime('d.m.y h:i', $row->lnk_timestamp_create). '</i>';

    if($row->lnk_usr_id_change > 0)
    {
        $description = $description. '<br /><i>Zuletzt bearbeitet von '. $row->change_firstname. " ". $row->change_surname.
									 ' am '. mysqldatetime('d.m.y h:i', $row->lnk_timestamp_change). '</i>';
    }

    $pubDate = date("r", strtotime($row->lnk_timestamp_create));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>