<?php
/******************************************************************************
 * Bilder werden hochgeladen und Bericht angezeigt
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * Uebergaben:
 *
 * pho_id: id der Veranstaltung zu der die Bilder hinzugefuegt werden sollen
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
 * Foundation, Inc., 79 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("photo_function.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show("photoverwaltunsrecht");
}

// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false)
{
    $g_message->show("invalid");
}

if (empty($_POST))
{
    $g_message->show("empty_photo_post", ini_get(post_max_size));
}

//Uebernahme Variablen
$pho_id= $_GET['pho_id'];

//erfassen der Veranstaltung
$sql = "    SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id = {0}";
$sql    = prepareSQL($sql, array($pho_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);
$adm_photo = mysql_fetch_array($result);

// pruefen, ob Veranstaltung zur aktuellen Organisation gehoert
if($adm_photo['pho_org_shortname'] != $g_organization)
{
    $g_message->show("invalid");
}

//Ordnerpfad
$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

//Erfassen der Eltern Veranstaltung
if($adm_photo["pho_pho_id_parent"]!=NULL)
{
    $pho_parent_id=$adm_photo["pho_pho_id_parent"];
    $sql="  SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id = $pho_parent_id";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
    $adm_photo_parent = mysql_fetch_array($result);
}

//Kontrollmechanismen bei Upload
if($_POST["upload"])
{
    //zaehlen wieviele Bilder hochgeladen werden sollen und ob alle Uploads Fehlerfrei sind
    $counter=0;
    for($x=0; $x<=4; $x++)
    {
        //Datei wurde hochgeladen
        if(isset($_FILES["bilddatei"]["name"]["$x"]))
        {
            //Es liegt kein Fehler vor, die Datei wurde erfolgreich hochgeladen.
            if($_FILES["bilddatei"]["error"]["$x"]==0)
            {
                $counter++;

                //Dateiendungskontrolle
                $bildinfo=getimagesize($_FILES["bilddatei"]["tmp_name"][$x]);
                if ($_FILES["bilddatei"]["name"][$x]!=NULL && $bildinfo['mime']!="image/jpeg")
                {
                    $g_message->show("dateiendungphotoup");
                }
            }

            //Die hochgeladene Datei ueberschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Groesse.
            if($_FILES["bilddatei"]["error"]["$x"]==1)
            {
                $g_message->show("photo_2big", ini_get(upload_max_filesize));
            }
        }

    }

    //Kontrolle ob Bilder ausgewaehlt wurden
    if($counter==0)
    {
        $g_message->show("photodateiphotoup");
    }

    // Bilder wurden erfolgreich hochgeladen -> Upload-Seite aus der Navi-Klasse entfernen
    $_SESSION['navigation']->deleteLastUrl();
}//Kontrollmechanismen

// Html-Kopf ausgeben
$g_layout['title'] = "Fotos hochladen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

/*****************************Verarbeitung******************************************/
if($_POST["upload"])
{
    //bei selbstaufruf der Datei Hinweise zu hochgeladenen Dateien und Kopieren der Datei in Ordner
    //Anlegen des Berichts
    echo"
    <div style=\"width: 670px\" align=\"center\" class=\"formHead\">Bericht</div>
    <div style=\"width: 670px\" align=\"center\" class=\"formBody\">Bitte einen Moment Geduld. Die Bilder wurden der Veranstaltung <br> - ".$adm_photo["pho_name"]." - <br>erfolgreich hinzugef&uuml;gt, wenn sie hier angezeigt werden.<br>";

         //Verarbeitungsschleife fuer die einzelnen Bilder
            $bildnr=$adm_photo["pho_quantity"];
            for($x=0; $x<=4; $x=$x+1)
            {
                $y=$x+1;
                if($_FILES["bilddatei"]["name"][$x]!=NULL && $ordner!=NULL)
                {
                    //errechnen der neuen Bilderzahl
                    $bildnr++;
                    echo "<br>Bild $bildnr:<br>";

                    //Bild in Tempordner verschieben, groeße aendern und speichern
                    if(move_uploaded_file($_FILES["bilddatei"]["tmp_name"][$x], "../../../adm_my_files/photos/temp".$y.".jpg"))
                    {
                        $temp_bild="../../../adm_my_files/photos/temp".$y.".jpg";
                        
                        //Bild skalliert speichern
                        image_save($temp_bild, $g_preferences['photo_save_scale'], $ordner."/".$bildnr.".jpg");

                        //Loeschen des Bildes aus Arbeitsspeicher
                        if(file_exists("../../../adm_my_files/photos/temp".$y.".jpg"))
                        {
                            unlink("../../../adm_my_files/photos/temp".$y.".jpg");
                        }
                    }//Ende Bild speichern


                    //Kontrolle
                    if(file_exists($ordner."/".$bildnr.".jpg"))
                    {
                        echo"<img src=\"photo_show.php?scal=".$g_preferences['photo_save_scale']."&aufgabe=anzeigen&bild=$ordner/$bildnr.jpg\"><br><br>";
                        //Aendern der Datenbankeintaege
                        $sql=" UPDATE ". TBL_PHOTOS. "
                               SET   pho_quantity = {0},
                                     pho_last_change ={1},
                                     pho_usr_id_change = {2}
                               WHERE pho_id = {3}";
                        $sql    = prepareSQL($sql, array($bildnr, $act_datetime, $g_current_user->getValue("usr_id"),$pho_id));
                        $result = mysql_query($sql, $g_adm_con);
                        db_error($result,__FILE__,__LINE__);
                    }
                    else
                    {
                        $bildnr--;
                        echo"Das Bild konnte nicht verarbeitet werden.";
                    }
                    unset($y);
                }//if($bilddatei!= "")
            }//for


        //Buttons
        echo"
        <hr class=\"formLine\" width=\"85%\" />
        <div style=\"margin-top: 6px;\">
            <button name=\"uebersicht\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\">
                <img src=\"$g_root_path/adm_program/images/application_view_tile.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                &nbsp;&Uuml;bersicht
            </button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"moreupload\" type=\"button\" value=\"moreupload\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id'\">
                <img src=\"$g_root_path/adm_program/images/photo.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                &nbsp;Weitere Bilder hochladen
            </button>
         </div>
    </div><br><br>";
}//if($upload)

//Seitenende
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>