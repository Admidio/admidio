<?php
/******************************************************************************
 * RSS - Feed fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer Gaestebucheintraege
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/ubb_parser.php');
require('../../system/classes/rss.php');


// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show('rss_disabled');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] != 1)
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

// die 10 letzten Eintraege aus der DB fischen...
$sql = 'SELECT * FROM '. TBL_GUESTBOOK. '
        WHERE gbo_org_id = '. $g_current_organization->getValue('org_id'). '
        ORDER BY gbo_timestamp DESC
        LIMIT 10 ';
$result = $g_db->query($sql);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed('http://'. $g_current_organization->getValue('org_homepage'), $g_current_organization->getValue('org_longname'). ' - Gaestebuch', 'Die 10 neuesten Gaestebucheintraege');


// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $g_db->fetch_object($result))
{
    // Die Attribute fuer das Item zusammenstellen
    $title = $row->gbo_name;
    $link  = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?id='. $row->gbo_id;
    $description = '<b>'.$row->gbo_name.' schrieb am '. mysqldatetime('d.m.y h:i', $row->gbo_timestamp).'</b>';


    // Die Ankuendigungen eventuell durch den UBB-Parser schicken
    if ($g_preferences['enable_bbcode'] == 1)
    {
        $description = $description. '<br /><br />'. $bbcode->parse($row->gbo_text);
    }
    else
    {
        $description = $description. '<br /><br />'. nl2br($row->gbo_text);
    }

    $description = $description. '<br /><br /><a href="'.$link.'">Link auf '. $g_current_organization->getValue('org_homepage'). '</a>';

    $pubDate = date('r', strtotime($row->gbo_timestamp));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>