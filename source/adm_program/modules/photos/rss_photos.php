<?php
/******************************************************************************
 * RSS - Feed fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

// damit das SQL-Statement nachher nicht auf die Nase faellt, muss $organizations gefuellt sein
if (strlen($organizations) == 0)
{
    $organizations = "'$g_current_organization->shortname'";
}


// die neuesten 10 Annkuedigungen aus der DB fischen...
$sql = "SELECT * FROM ". TBL_PHOTOS. "
        WHERE ( pho_org_shortname = '$g_current_organization->shortname')
        ORDER BY pho_timestamp DESC
        LIMIT 10";

$result = mysql_query($sql, $g_adm_con);
db_error($result);

//Funktion mit selbstaufruf zum erfassen der Bilder in Unterveranstaltungen
function bildersumme($pho_id_parent){
    global $g_adm_con;
    global $g_organization;
    global $bildersumme;
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_pho_id_parent = $pho_id_parent
                AND pho_locked = 0";
    $result_child= mysql_query($sql, $g_adm_con);
    db_error($result_child, 1);
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
    // Den Autor des Termins ermitteln
    $sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id = $row->pho_usr_id";
    $result2 = mysql_query($sql, $g_adm_con);
    db_error($result2);
    $user = mysql_fetch_object($result2);


    // Die Attribute fuer das Item zusammenstellen
    //Titel
    $parents = "";
    $pho_parent_id = $row->pho_pho_id_parent;
    while ($pho_parent_id != NULL)
    {
        //Erfassen der Eltern Veranstaltung
        $sql=" SELECT *
                 FROM ". TBL_PHOTOS. "
                WHERE pho_id ='$pho_parent_id'";
        $result_parents = mysql_query($sql, $g_adm_con);
        db_error($result_parents);
        $adm_photo_parent = mysql_fetch_array($result_parents);

        //Link zusammensetzen
        $parents = "&nbsp;&gt;&nbsp;".$adm_photo_parent["pho_name"].$parents;

        //Elternveranst
        $pho_parent_id=$adm_photo_parent["pho_pho_id_parent"];
    }
    $title = "Fotogalerien".$parents."&nbsp;&gt;&nbsp;".$row->pho_name;

    //Link
    $link  = "$g_root_path/adm_program/modules/photos/photos.php?pho_id=". $row->pho_id;

    //Inhalt
    $description = "Fotogalerien".$parents."&nbsp;&gt;&nbsp;<b>". strSpecialChars2Html($row->pho_name). "</b>";

    $bildersumme=$row->pho_quantity;
    bildersumme($row->pho_id);

    $description = $description. "<br /><br /> Bilder: ".$bildersumme;
    $description = $description. "<br /> Datum: ".mysqldate("d.m.y", $row->pho_begin);
    if($row->pho_end != $row->pho_begin)
    {
        $description = $description. " bis ".mysqldate("d.m.y", $row->pho_end);
    }
    $description = $description. "<br />Fotos von: ".$row->pho_photographers;

    //die ersten fuenf Bilder
    if($row->pho_quantity >0)
    {
        $description = $description. "<br /><br />Beispielbilder:<br />";
        for($bild=1; $bild<=5 && $bild<=$row->pho_quantity; $bild++)
        {
            $bildpfad = "../../../adm_my_files/photos/".$row->pho_begin."_".$row->pho_id.$ordner."/".$bild.".jpg";
            $description = $description. "
                <img src=\"$g_root_path/adm_program/modules/photos/resize.php?bild=".$bildpfad."&amp;scal=100&amp;aufgabe=anzeigen\" border=\"0\" alt=\"bild\">&nbsp;";
        }
    }

    $description = $description. "<br /><br /><a href=\"$link\">Link auf $g_current_organization->homepage</a>";
    $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($user->usr_first_name). " ". strSpecialChars2Html($user->usr_last_name);
    $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->pho_timestamp). "</i>";

    $pubDate = date('r',strtotime($row->pho_timestamp));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>