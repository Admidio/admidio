<?php
/******************************************************************************
 * RSS - Feed fuer Photos
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 neuesten Fotoveranstaltungen
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
require("../../system/rss_class.php");


// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl("home");
    $g_message->show("rss_disabled");
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// die neuesten 10 Photoveranstaltungen aus der DB fischen...
$sql = "SELECT * FROM ". TBL_PHOTOS. "
        WHERE ( pho_org_shortname = {0}
        AND pho_locked = 0)
        ORDER BY pho_timestamp DESC
        LIMIT 10";
$sql    = prepareSQL($sql, array($g_current_organization->shortname));
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);

//Funktion mit selbstaufruf zum erfassen der Bilder in Unterveranstaltungen
function bildersumme($pho_id_parent){
    global $g_adm_con;
    global $g_organization;
    global $bildersumme;
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_pho_id_parent = {0}
                AND pho_locked = 0";
    $sql    = prepareSQL($sql, array($pho_id_parent));
    $result_child= mysql_query($sql, $g_adm_con);
    db_error($result_child,__FILE__,__LINE__);
    while($adm_photo_child=mysql_fetch_array($result_child))
    {
        $bildersumme=$bildersumme+$adm_photo_child["pho_quantity"];
        bildersumme($adm_photo_child["pho_id"]);
    };
}//function

// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed("http://$g_current_organization->homepage", "$g_current_organization->longname - Fotos", "Die 10 neuesten Fotoveranstaltungen");

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = mysql_fetch_object($result))
{
    // Den Anleger ermitteln
    $sql     = "SELECT usr_first_name, usr_last_name FROM ". TBL_USERS. " WHERE usr_id = {0}";
    $sql    = prepareSQL($sql, array($row->pho_usr_id));
    $result2 = mysql_query($sql, $g_adm_con);
    db_error($result2,__FILE__,__LINE__);
    $create_user = mysql_fetch_object($result2);

    // Den Veraenderer ermitteln falls ungleich NULL
    if($row->pho_usr_id_change!= NULL)
    {
        $sql     = "SELECT usr_first_name, usr_last_name FROM ". TBL_USERS. " WHERE usr_id = {0}";
        $sql    = prepareSQL($sql, array($row->pho_usr_id_change));
        $result3 = mysql_query($sql, $g_adm_con);
        db_error($result3,__FILE__,__LINE__);
        $update_user = mysql_fetch_object($result3);
    }
    // Die Attribute fuer das Item zusammenstellen

    //Titel
    $parents = "";
    $pho_parent_id = $row->pho_pho_id_parent;
    //Titel muss mit Ordnerstruktur zusammengesetzt werden
    while ($pho_parent_id != NULL)
    {
        //Erfassen der Eltern Veranstaltung
        $sql=" SELECT *
                 FROM ". TBL_PHOTOS. "
                WHERE pho_id ={0}";
        $sql    = prepareSQL($sql, array($pho_parent_id));
        $result_parents = mysql_query($sql, $g_adm_con);
        db_error($result_parents,__FILE__,__LINE__);
        $adm_photo_parent = mysql_fetch_array($result_parents);

        //Link zusammensetzen
        $parents = " > ".$adm_photo_parent["pho_name"].$parents;

        //Elternveranst
        $pho_parent_id=$adm_photo_parent["pho_pho_id_parent"];
    }
    $title = "Fotogalerien".$parents."&nbsp;&gt;&nbsp;".$row->pho_name;

    //Link
    $link  = "$g_root_path/adm_program/modules/photos/photos.php?pho_id=". $row->pho_id;

    //Bildersumme
    $bildersumme=$row->pho_quantity;
    bildersumme($row->pho_id);

    //Inhalt zusammensetzen
    $description = "Fotogalerien".$parents." > ". strSpecialChars2Html($row->pho_name);
    $description = $description. "<br /><br /> Bilder: ".$bildersumme;
    $description = $description. "<br /> Datum: ".mysqldate("d.m.y", $row->pho_begin);
    //Enddatum nur wenn anders als startdatum
    if($row->pho_end != $row->pho_begin)
    {
        $description = $description. " bis ".mysqldate("d.m.y", $row->pho_end);
    }
    $description = $description. "<br />Fotos von: ".$row->pho_photographers;

    //die letzten fuenf Bilder sollen als Beispiel genutzt werden
    if($row->pho_quantity >0)
    {
        $description = $description. "<br /><br />Beispielbilder:<br />";
        for($bild=$row->pho_quantity; $bild>=$row->pho_quantity-4 && $bild>0; $bild--)
        {
            $bildpfad = "../../../adm_my_files/photos/".$row->pho_begin."_".$row->pho_id."/".$bild.".jpg";
            //Zu Sicherheit noch überwachen ob das Bild existiert, wenn ja raus damit
            if (file_exists($bildpfad))
            {
                $description = $description. "
                 <img src=\"$g_root_path/adm_program/modules/photos/photo_show.php?bild=".$bildpfad."&amp;scal=100\" border=\"0\" alt=\"bild\">&nbsp;";
            }
        }
    }

    //Link zur Momepage
    $description = $description. "<br /><br /><a href=\"$link\">Link auf $g_current_organization->homepage</a>";

    //Angaben zum Anleger
    $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($create_user->usr_first_name). " ". strSpecialChars2Html($create_user->usr_last_name);
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->pho_timestamp). "</i>";

    //Angaben zum Updater
    $description = $description. "<br /><i>Letztes Update durch ". strSpecialChars2Html($update_user->usr_first_name). " ". strSpecialChars2Html($update_user->usr_last_name);
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->pho_last_change). "</i>";

    $pubDate = date('r',strtotime($row->pho_timestamp));

    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}

// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>