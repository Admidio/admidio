<?php 
/******************************************************************************
 * RSS - Feed fuer Links
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer alle Links
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/bbcode.php");
require("../../system/rss_class.php");


// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl("home");
    $g_message->show("rss_disabled");
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// Nachschauen ob BB-Code aktiviert ist...
if ($g_preferences['enable_bbcode'] == 1)
{
    //BB-Parser initialisieren
    $bbcode = new ubbParser();
}

// alle Links aus der DB fischen...
$sql = "SELECT * FROM ". TBL_LINKS. "
        WHERE lnk_org_id = ". $g_current_organization->getValue("org_id"). "
        ORDER BY lnk_timestamp DESC";

$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed("http://". $g_current_organization->getValue("org_homepage"), $g_current_organization->getValue("org_longname"). " - Links", "Linksammlung von ". $g_current_organization->getValue("org_longname"));


// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = mysql_fetch_object($result))
{
    // Die Attribute fuer das Item zusammenstellen
    $title = $row->lnk_name;
    $link  = "$g_root_path/adm_program/modules/links/links.php?id=". $row->lnk_id;
    $description = "<a href=\"$row->lnk_url\" target=\"_blank\"><b>". strSpecialChars2Html($row->lnk_name). "</b></a>";


    // Die Ankuendigungen eventuell durch den UBB-Parser schicken
    if ($g_preferences['enable_bbcode'] == 1)
    {
        $description = $description. "<br /><br />". strSpecialChars2Html($bbcode->parse($row->lnk_description));
    }
    else
    {
        $description = $description. "<br /><br />". nl2br(strSpecialChars2Html($row->lnk_description));
    }

    $description = $description. "<br /><br /><a href=\"$link\">Link auf ". $g_current_organization->getValue("org_homepage"). "</a>";

    // Den Autor der Links ermitteln und ausgeben
    $user = new User($g_adm_con, $row->lnk_usr_id);
    $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($user->getValue("Vorname")). " ". strSpecialChars2Html($user->getValue("Nachname"));
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp). "</i>";

    $pubDate = date('r', strtotime($row->lnk_timestamp));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>