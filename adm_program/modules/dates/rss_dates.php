<?php
/******************************************************************************
 * RSS - Feed fuer Termine
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/classes/ubb_parser.php");
require("../../system/classes/rss.php");
require("../../system/classes/date.php");

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show("rss_disabled");
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] != 1)
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

// alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = "";
$arr_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($arr_orgas as $key => $value)
{
	$organizations = $organizations. "'$value', ";
}
$organizations = $organizations. "'". $g_current_organization->getValue("org_shortname"). "'";

// aktuelle Termine aus DB holen die zur Orga passen
$sql = "SELECT * FROM ". TBL_DATES. "
        WHERE ( dat_org_shortname = '".$g_current_organization->getValue("org_shortname")."'
        OR ( dat_global = 1 AND dat_org_shortname IN ($organizations) ))
        AND ( dat_begin >= '".date("Y-m-d h:i:s", time())."' OR dat_end >= '".date("Y-m-d h:i:s", time())."' )
        ORDER BY dat_begin ASC
        LIMIT 10 ";
$result = $g_db->query($sql);

// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss  = new RSSfeed("http://". $g_current_organization->getValue("org_homepage"), $g_current_organization->getValue("org_longname"). " - Termine", "Die 10 naechsten Termine");
$date = new Date($g_db);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $g_db->fetch_object($result))
{
    // ausgelesene Termindaten in Date-Objekt schieben
    $date->clear();
    $date->setArray($row);

    // Die Attribute fuer das Item zusammenstellen
    $title = mysqldatetime("d.m.y", $date->getValue("dat_begin"));
    if(mysqldatetime("d.m.y", $date->getValue("dat_begin")) != mysqldatetime("d.m.y", $date->getValue("dat_end")))
    {
        $title = $title. ' - '. mysqldatetime("d.m.y", $date->getValue("dat_end"));
    }
    $title = $title. " ". $date->getValue("dat_headline");
    $link  = $g_root_path."/adm_program/modules/dates/dates.php?id=". $date->getValue("dat_id");
    $description = "<b>".$date->getValue("dat_headline")."</b> <br />". mysqldatetime("d.m.y", $date->getValue("dat_begin"));

    if ($date->getValue("dat_all_day") == 0)
    {
        $description = $description. " von ". mysqldatetime("h:i", $date->getValue("dat_begin")). " Uhr bis ";
        if(mysqldatetime("d.m.y", $date->getValue("dat_begin")) != mysqldatetime("d.m.y", $date->getValue("dat_end")))
        {
            $description = $description. mysqldatetime("d.m.y", $date->getValue("dat_end")). " ";
        }
        $description = $description. mysqldatetime("h:i", $date->getValue("dat_end")). " Uhr";
    }
    else
    {
        if(mysqldatetime("d.m.y", $date->getValue("dat_begin")) != mysqldatetime("d.m.y", $date->getValue("dat_end")))
        {
            $description = $description. " bis ". mysqldatetime("d.m.y", $date->getValue("dat_end"));
        }
    }

    if ($date->getValue("dat_location") != "")
    {
        $description = $description. "<br /><br />Treffpunkt:&nbsp;". $date->getValue("dat_location");
    }

    //eventuell noch die Beschreibung durch den UBB-Parser schicken...
    if ($g_preferences['enable_bbcode'] == 1)
    {
        $description = $description. "<br /><br />". $bbcode->parse($date->getValue("dat_description"));
    }
    else
    {
        $description = $description. "<br /><br />". nl2br($date->getValue("dat_description"));
    }

    $description = $description. '<br /><br /><a href="'.$link.'">Link auf '. $g_current_organization->getValue("org_homepage"). '</a>';
    
    //i-cal downloadlink
    $description = $description. '<br /><br /><a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$date->getValue("dat_id").'&mode=4">Termin in meinen Kalender übernehmen</a>';
    
    // Den Autor und letzten Bearbeiter der Ankuendigung ermitteln und ausgeben
    $user = new User($g_db, $date->getValue("dat_usr_id_create"));
    $description = $description. "<br /><br /><i>Angelegt von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_timestamp_create")). "</i>";

    if($date->getValue("dat_usr_id_change") > 0)
    {
        $user_change = new User($g_db, $date->getValue("dat_usr_id_change"));
        $description = $description. "<br /><i>Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname");
        $description = $description. " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_timestamp_change")). "</i>";
    }
    
    $pubDate = date('r',strtotime($date->getValue("dat_timestamp_create")));


    //Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);

}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>