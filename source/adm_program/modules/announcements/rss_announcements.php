<?php
/******************************************************************************
 * RSS - Feed fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 neuesten Ankuendigungen
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
if ($g_preferences['enable_announcements_module'] != 1)
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

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$arr_ref_orgas = $g_current_organization->getReferenceOrganizations();
$organizations = "";
$i             = 0;

while ($orga = current($arr_ref_orgas))
{
    if ($i > 0)
    {
        $organizations = $organizations. ", ";
    }
    $organizations = $organizations. "'$orga'";
    next($arr_ref_orgas);
    $i++;
}

// damit das SQL-Statement nachher nicht auf die Nase faellt, muss $organizations gefuellt sein
if (strlen($organizations) == 0)
{
    $organizations = "'". $g_current_organization->getValue("org_shortname"). "'";
}


// die neuesten 10 Annkuedigungen aus der DB fischen...
$sql = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
        WHERE ( ann_org_shortname = '". $g_current_organization->getValue("org_shortname"). "'
        OR ( ann_global = 1 AND ann_org_shortname IN ($organizations) ))
        ORDER BY ann_timestamp DESC
        LIMIT 10 ";

$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed("http://". $g_current_organization->getValue("org_homepage"), $g_current_organization->getValue("org_longname"). " - Ankuendigungen", "Die 10 neuesten Ankuendigungen");

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = mysql_fetch_object($result))
{
    // Die Attribute fuer das Item zusammenstellen
    $title = $row->ann_headline;
    $link  = "$g_root_path/adm_program/modules/announcements/announcements.php?id=". $row->ann_id;
    $description = "<b>". strSpecialChars2Html($row->ann_headline). "</b>";


    // Die Ankuendigungen eventuell durch den UBB-Parser schicken
    if ($g_preferences['enable_bbcode'] == 1)
    {
        $description = $description. "<br /><br />". strSpecialChars2Html($bbcode->parse($row->ann_description));
    }
    else
    {
        $description = $description. "<br /><br />". nl2br(strSpecialChars2Html($row->ann_description));
    }

    $description = $description. "<br /><br /><a href=\"$link\">Link auf ". $g_current_organization->getValue("org_homepage"). "</a>";

    // Den Autor der Ankuendigung ermitteln und ausgeben
    $user = new User($g_db, $row->ann_usr_id);
    $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($user->getValue("Vorname")). " ". strSpecialChars2Html($user->getValue("Nachname"));
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->ann_timestamp). "</i>";

    // Zuletzt geaendert nur anzeigen, wenn Änderung nach 15 Minuten oder durch anderen Nutzer gemacht wurde
    if($row->ann_usr_id_change > 0
    && (  strtotime($row->ann_last_change) > (strtotime($row->ann_timestamp) + 900)
       || $row->ann_usr_id_change != $row->ann_usr_id ) )
    {
        $user_change = new User($g_db, $row->ann_usr_id_change);
        $description = $description. "<br>Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname");
        $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->ann_last_change);
    }
                
    $pubDate = date('r',strtotime($row->ann_timestamp));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>