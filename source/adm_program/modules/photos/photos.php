<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 * 
 * pho_id: id der Veranstaltung deren Bilder angezeigt werden sollen
 * thumb_seite: welch Seite der Thumbnails ist die aktuelle
 * start: mit welchem Element beginnt die Veranstaltungsliste
 * locked: die Veranstaltung soll freigegebn/gesperrt werden
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

if(isset($_SESSION['photo_event_request']))
{
    $form_values = $_SESSION['photo_event_request'];
    unset($_SESSION['photo_event_request']);
}


// Uebergabevariablen pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false && $_GET["pho_id"]!=NULL)
{
    $g_message->show("invalid");
}

//aktuelle event_element
if(array_key_exists("start", $_GET))
{
    if(is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
    $event_element = $_GET['start'];
}
else
{
    $event_element = 0;
}

if(array_key_exists("thumb_seite", $_GET))
{
    if(is_numeric($_GET["thumb_seite"]) == false)
    {
        $g_message->show("invalid");
    }
    $thumb_seite = $_GET['thumb_seite'];
}
else
{
    $thumb_seite = 1;
}

if(isset($_GET["locked"]) && is_numeric($_GET["locked"]) == false)
{
    $g_message->show("invalid");
}

//Uebername der uebergebenen Variablen
//ID einer bestimmten Veranstaltung
$pho_id=$_GET["pho_id"];

//Aufruf der ggf. uebergebenen Veranstaltung
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE pho_id ={0}";
$sql    = prepareSQL($sql, array($pho_id));
$result_event = mysql_query($sql, $g_adm_con);
db_error($result_event);
$adm_photo = mysql_fetch_array($result_event);


//erfassen ob Unterveranstaltungen existieren
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE pho_pho_id_parent ={0}";
$sql    = prepareSQL($sql, array($pho_id));
$result_children = mysql_query($sql, $g_adm_con);
db_error($result_event);
$children = mysql_num_rows($result_children);

//Erfassen des Anlegers der uebergebenen Veranstaltung
if($pho_id!=NULL && $adm_photo["pho_usr_id"]!=NULL)
{
    $sql="  SELECT * 
            FROM ". TBL_USERS. " 
            WHERE usr_id =".$adm_photo["pho_usr_id"];
    $result_u1 = mysql_query($sql, $g_adm_con);
    db_error($result_u1);
    $user1 = mysql_fetch_object($result_u1);
}

//Erfassen des Veraenderers der uebergebenen Veranstaltung
if($pho_id!=NULL && $adm_photo["pho_usr_id_change"]!=NULL)
{
    $sql="  SELECT * 
            FROM ". TBL_USERS. " 
            WHERE usr_id =".$adm_photo["pho_usr_id_change"];
    $result_u2 = mysql_query($sql, $g_adm_con);
    db_error($result_u2);
    $user2 = mysql_fetch_object($result_u2);
}

/*********************LOCKED************************************/       
//Falls gefordert und Foto-edit-rechte, aendern der Freigabe
if($_GET["locked"]=="1" || $_GET["locked"]=="0")
{
    //bei Seitenaufruf ohne Moderationsrechte
    if(!$g_session_valid || $g_session_valid && !editPhoto($adm_photo["pho_org_shortname"]))
    {
        $g_message->show("photoverwaltungsrecht");
    }
    
    //bei Seitenaufruf mit Moderationsrechten
    if($g_session_valid && editPhoto($adm_photo["pho_org_shortname"]))
    {
        $locked=$_GET["locked"];
        $sql="  UPDATE ". TBL_PHOTOS. " SET  pho_locked = $locked
                 WHERE pho_id = {0}";
        $sql    = prepareSQL($sql, array($pho_id));
        $result_approved = mysql_query($sql, $g_adm_con);
        db_error($result_approved);
        
        //Zurueck zur Elternveranstaltung
        $pho_id=$adm_photo_parent["pho_id"];
        $sql="   SELECT *
                 FROM ". TBL_PHOTOS. "
                 WHERE pho_id ={0}";
        $sql    = prepareSQL($sql, array($pho_id));
        $result_event = mysql_query($sql, $g_adm_con);
        db_error($result_event);
        $adm_photo = mysql_fetch_array($result_event);
    }
}


/*********************HTML_TEIL*******************************/ 

//allgemeiner HTML-Teil
echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
    <head>
        <title>$g_current_organization->longname - Fotogalerien</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if lt IE 7]>
            <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";
        
        require("../../../adm_config/header.php");
    echo "
    </head>";
    
    require("../../../adm_config/body_top.php");

    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
        //Ueberschift
        echo"<h1>";
        if($pho_id==NULL)
        {
            echo "Fotogalerien";
        }
        else
        {
            echo $adm_photo["pho_name"];
        }
        echo "</h1>";
        
        //solange nach Unterveranstaltungen suchen bis es keine mehr gibt
        $navilink = "";
        $pho_parent_id = $adm_photo["pho_pho_id_parent"];
        while ($pho_parent_id > 0)
        {
            //Erfassen der Eltern Veranstaltung
            $sql=" SELECT *
                     FROM ". TBL_PHOTOS. "
                    WHERE pho_id ='$pho_parent_id'";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $adm_photo_parent = mysql_fetch_array($result);
    
            //Link zusammensetzen
            $navilink = "&nbsp;&gt;&nbsp;<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo["pho_pho_id_parent"]."\">".$adm_photo_parent["pho_name"]."</a>".$navilink;

            //Elternveranst
            $pho_parent_id=$adm_photo_parent["pho_pho_id_parent"];
        }
        
        if($pho_id > 0)
        {
            //Ausgabe des Linkpfads
            echo "<p>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photos.php\"><img 
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/application_view_tile.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Fotogalerien\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photos.php\">Fotogalerien</a>$navilink
                </span>
            </p>";
        }
    
        //bei Seitenaufruf mit Moderationsrechten
        if($g_session_valid && editPhoto())
        {
            echo"<p>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photo_event_new.php?aufgabe=new&amp;pho_id=$pho_id\"><img 
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Veranstaltung anlegen\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photo_event_new.php?aufgabe=new&amp;pho_id=$pho_id\">Veranstaltung anlegen</a>
                </span>";
                if($pho_id > 0)
                {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=".$adm_photo["pho_id"]."\"><img 
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/photo.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Bilder hochladen\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=".$adm_photo["pho_id"]."\">Bilder hochladen</a>
                    </span>";
                }
            echo "</p>";
        }

        //Anlegender Tabelle
        echo "<div class=\"formBody\">";
            /*************************THUMBNAILS**********************************/   
            //Nur wenn uebergeben Veranstaltung Bilder enthaelt
            if($adm_photo["pho_quantity"] > 0)
            {
                //Aanzahl der Bilder
                $bilder = $adm_photo["pho_quantity"];
                //Ordnerpfad
                $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

                //Thumbnails pro Seite
                $thumbs_per_side = $g_preferences['photo_thumbs_row']*$g_preferences['photo_thumbs_column'];

                //Differenz
                $difference = $g_preferences['photo_thumbs_row']-$g_preferences['photo_thumbs_column'];
                
                //Popupfenstergröße
                $popup_height = $g_preferences['photo_show_height']+210;
                $popup_width  = $g_preferences['photo_show_width']+70;
                
                //Ausrechnen der Seitenzahl
                if (settype($bilder,integer) || settype($thumb_seiten,integer))
                {
                    $thumb_seiten = round($bilder / $thumbs_per_side);
                }

                if ($thumb_seiten * $thumbs_per_side < $bilder)
                {
                    $thumb_seiten++; 
                }

                //Datum der Veranstaltung
                echo"
                Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
                if($adm_photo["pho_end"] != $adm_photo["pho_begin"])
                {
                    echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
                }

                //Seitennavigation
                echo"<br>Seite:&nbsp;";

                //Vorherige thumb_seite
                $vorseite=$thumb_seite-1;
                if($vorseite>=1)
                {
                    echo"
                    <a href=\"photos.php?thumb_seite=$vorseite&amp;pho_id=$pho_id\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Vorherige\">
                    </a>
                    <a href=\"photos.php?thumb_seite=$vorseite&amp;pho_id=$pho_id\">Vorherige</a>&nbsp;&nbsp;";
                }

                //Seitenzahlen
                for($s=1; $s<=$thumb_seiten; $s++)
                {
                    if($s==$thumb_seite)
                    {
                        echo $thumb_seite."&nbsp;";
                    }
                    if($s!=$thumb_seite){
                        echo"<a href='photos.php?thumb_seite=$s&pho_id=$pho_id'>$s</a>&nbsp;";
                    }
                }

                //naechste thumb_seite
                $nachseite=$thumb_seite+1;
                if($nachseite<=$thumb_seiten){
                    echo"
                    <a href=\"photos.php?thumb_seite=$nachseite&amp;pho_id=$pho_id\">N&auml;chste</a>
                    <a href=\"photos.php?thumb_seite=$nachseite&amp;pho_id=$pho_id\">
                        <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"N&auml;chste\">
                    </a>";
                }

                //Thumbnailtabelle
                echo"
                <table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" style=\"width: 100%\">";
                    for($zeile=1;$zeile<=$g_preferences['photo_thumbs_row'];$zeile++)//durchlaufen der Tabellenzeilen
                    {
                        echo "<tr>";
                        for($spalte=1;$spalte<=$g_preferences['photo_thumbs_column'];$spalte++)//durchlaufen der Tabellenzeilen
                        {
                            $bild = ($thumb_seite*$thumbs_per_side)-$thumbs_per_side+($zeile*$g_preferences['photo_thumbs_column'])-$g_preferences['photo_thumbs_row']+$spalte+$difference;//Errechnug welches Bild ausgegeben wird
                            if ($bild <= $bilder)
                            {
                                echo"
                                <td style=\"text-align: center;\">
                                    <img onclick=\"window.open('photopopup.php?bild=$bild&pho_id=$pho_id','msg', 'height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" style=\"vertical-align: middle; cursor: pointer;\"
                                    src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=".$g_preferences['photo_thumbs_scale']."&amp;aufgabe=anzeigen\" border=\"0\" alt=\"$bild\">
                                    <br>";

                                    //Buttons fuer moderatoren
                                    if ($g_session_valid && editPhoto($adm_photo["pho_org_shortname"]))
                                    {
                                        echo"
                                        <img src=\"$g_root_path/adm_program/images/arrow_turn_left.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"nach links drehen\" title=\"nach links drehen\"
                                            onclick=\"self.location.href='photo_function.php?pho_id=$pho_id&bild=$bild&thumb_seite=$thumb_seite&job=rotate&direction=left'\">
                                        <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Foto l&ouml;schen\" title=\"Foto l&ouml;schen\"
                                            onclick=\"self.location.href='photo_function.php?pho_id=$pho_id&bild=$bild&thumb_seite=$thumb_seite&job=delete_request'\">
                                        <img src=\"$g_root_path/adm_program/images/arrow_turn_right.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"nach rechts drehen\" title=\"nach rechts drehen\"
                                            onclick=\"self.location.href='photo_function.php?pho_id=$pho_id&bild=$bild&thumb_seite=$thumb_seite&job=rotate&direction=right'\">";
                                    }
                                echo"
                                </td>";
                            }//if
                        }//for
                        echo "
                        </tr>";//Zeilenende
                    }//for
                echo "</table>";

                //Anleger und Veraendererinfos
                echo"
                <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: center;\">";
                    if($adm_photo["pho_usr_id"]!=NULL)
                    {
                        echo"Angelegt von ". strSpecialChars2Html($user1->usr_first_name). " ". strSpecialChars2Html($user1->usr_last_name)
                        ." am ". mysqldatetime("d.m.y h:i", $adm_photo["pho_timestamp"]);
                    }
                    if($adm_photo["pho_usr_id_change"]!=NULL && strtotime($adm_photo["pho_last_change"])>(strtotime($adm_photo["pho_timestamp"])+ 3600))
                    {
                        echo"<br>
                        Letztes Update durch ". strSpecialChars2Html($user2->usr_first_name). " ". strSpecialChars2Html($user2->usr_last_name)
                        ." am ". mysqldatetime("d.m.y h:i", $adm_photo["pho_last_change"]);
                    }     
                echo "</div>";
                
                if($children>0)
                {
                    echo"<hr width=\"90%\" />";
                }
            }
            /************************Veranstaltungsliste*************************************/  

            //erfassen der Veranstaltungen die in der Veranstaltungstabelle ausgegeben werden sollen
            $sql="      SELECT *
                        FROM ". TBL_PHOTOS. "
                        WHERE pho_org_shortname ='$g_organization' ";
            if($pho_id==NULL)
            {
                $sql=$sql." AND (pho_pho_id_parent IS NULL) ";
            }
            if($pho_id!=NULL)
            {
                $sql=$sql." AND pho_pho_id_parent = {0} ";
            }
            if (!editPhoto($adm_photo_list["pho_org_shortname"]))
            {
                $sql=$sql." AND pho_locked = 0 ";
            }

            $sql=$sql." ORDER BY pho_begin DESC ";

            $sql    = prepareSQL($sql, array($pho_id));
            $result_list = mysql_query($sql, $g_adm_con);
            db_error($result_list);

            //Gesamtzahl der auszugebenden Veranstaltungen
            $events=mysql_num_rows($result_list);
                              
            $ignored=0; //Summe aller zu ignorierender Elemente
            $ignore=0; //Summe der zu ignorierenden Elemente auf dieser Seite
            for($x=0; $x<$events; $x++)
            {
                $adm_photo_list = mysql_fetch_array($result_list);
                //Hauptordner
                $ordner = "../../../adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];
                if((!file_exists($ordner) || $adm_photo_list["pho_locked"]==1) && (!editPhoto($adm_photo_list["pho_org_shortname"])))
                {
                    $ignored++;
                    if($x>=$event_element+$ignored-$ignore)$ignore++;
                }
            }
                   
            //Dateizeiger auf erstes auszugebendes Element setzen
            if($events>0)
            {
                mysql_data_seek($result_list, $event_element+$ignored-$ignore);
            }
                    
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

            //Funktion mit selbstaufruf zum auswaehlen eines Beispielbildes aus einem moeglichst hohen Ordner  
            function beispielbild($pho_id_parent){
                global $g_adm_con; 
                global $g_organization;
                global $bsp_pho_id;
                global $bsp_pic_nr;
                global $bsp_pic_beginn;
                $sql = "    SELECT *
                            FROM ". TBL_PHOTOS. "
                            WHERE pho_pho_id_parent = $pho_id_parent
                            AND pho_locked = 0";
                $result_child= mysql_query($sql, $g_adm_con);
                db_error($result_child, 1);
                while($adm_photo_child=mysql_fetch_array($result_child))
                {
                    if($adm_photo_child["pho_quantity"]!=0)
                    {
                        $bsp_pic_nr=mt_rand(1, $adm_photo_child["pho_quantity"]);
                        $bsp_pho_id=$adm_photo_child["pho_id"];
                        $bsp_pic_beginn=$adm_photo_child["pho_begin"];
                    }
                    else beispielbild($adm_photo_child["pho_id"]);
                };      
            }//function
            
            // Navigation mit Vor- und Zurueck-Buttons
            $base_url = "$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$pho_id;
            echo "<div align=\"center\">".generatePagination($base_url, $events-$ignored, 10, $event_element, TRUE)."</div>
            <table style=\"border-width: 0px;\" cellpadding=\"4\" cellspacing=\"0\">";
                for($x=$event_element+$ignored-$ignore; $x<=$event_element+$ignored+9 && $x<$events; $x++)
                {
                    $adm_photo_list = mysql_fetch_array($result_list);
                    //Hauptordner
                    $ordner = "../../../adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];

                    //wenn ja Zeile ausgeben
                    if(file_exists($ordner) && ($adm_photo_list["pho_locked"]==0) || ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])))
                    {
                        //Summe der Bilder erfassen und zufaelliges Beispeilbild auswaehlen
                        $bildersumme=$adm_photo_list["pho_quantity"];
                        //Funktion zum Bildersummieren aufrufen
                        bildersumme($adm_photo_list["pho_id"]);

                        //Bild aus Veranstaltung als Vorschau auswaehlen
                        $bsp_pho_id=0;
                        $bsp_pic_nr=0;
                        $bsp_pic_beginn=0;

                        //sehen ob die Hauptveranstaltung Bilder enthaelt, nur wenn nicht in unterveranst suchen
                        if($adm_photo_list["pho_quantity"]>0)
                        {
                            $bsp_pic_nr=mt_rand(1, $adm_photo_list["pho_quantity"]);
                            $bsp_pho_id=$adm_photo_list["pho_id"];
                            $bsp_pic_beginn=$adm_photo_list["pho_begin"];         
                        }
                        //Sonst Funktionsaufruf zur Bildauswahl
                        else beispielbild($adm_photo_list["pho_id"]);

                        //Pfad des Beispielbildes
                        $bsp_pic_path = "../../../adm_my_files/photos/".$bsp_pic_beginn."_".$bsp_pho_id."/".$bsp_pic_nr.".jpg";

                        //Wenn kein Bild gefunden wurde
                        if($bsp_pho_id==0)
                        {
                            $bsp_pic_path ="../../images/nopix.jpg";
                        }


                        //Ausgabe
                        echo"
                        <tr>
                            <td style=\"width: 35%\"><div align=\"center\">";
                            if(file_exists($ordner))
                            {
                                echo"
                                <a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">
                                    <img src=\"resize.php?bild=$bsp_pic_path&amp;scal=".$g_preferences['photo_preview_scale']."&amp;aufgabe=anzeigen&amp;side=y\" border=\"0\" alt=\"$previewpic\"
                                    style=\"vertical-align: middle; align: right;\"></a></div>";
                            }
                            echo"
                            </td>
                            <td>";

                            //Warnung fuer Leute mit Fotorechten: Ordner existiert nicht
                            if(!file_exists($ordner) && ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])))
                            {
                                echo"<img src=\"$g_root_path/adm_program/images/warning16.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Warnhinweis\" title=\"Warnhinweis\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=folder_not_found','Message','width=400, height=400, left=310,top=200,scrollbars=no')\">&nbsp;";
                            }

                            //Hinweis fur Leute mit Photorechten: Veranstaltung ist gesperrt
                            if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
                            {
                                echo"<img src=\"$g_root_path/adm_program/images/lock.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung ist gesperrt\" title=\"Veranstaltung ist gesperrt\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=not_approved','Message','width=400, height=300, left=310,top=200,scrollbars=no')\">&nbsp;";
                            }

                            //Veranstaltungs angaben
                            if(file_exists($ordner))
                            {
                                echo"<a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."</a><br>";
                            }
                            else
                            {
                                echo $adm_photo_list["pho_name"];
                            }

                            echo"
                            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                                Bilder: ".$bildersumme." <br>
                                Datum: ".mysqldate("d.m.y", $adm_photo_list["pho_begin"]);
                                if($adm_photo_list["pho_end"] != $adm_photo_list["pho_begin"])
                                {
                                    echo " bis ".mysqldate("d.m.y", $adm_photo_list["pho_end"]);
                                }
                                echo "<br>Fotos von: ".$adm_photo_list["pho_photographers"]."<br>";

                                //bei Moderationrecheten
                                if ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"]))
                                {
                                    $this_pho_id = $adm_photo_list["pho_id"];
                                    if(file_exists($ordner))
                                    {
                                        echo"
                                        <img src=\"$g_root_path/adm_program/images/photo.png\" style=\"cursor: pointer; vertical-align: middle;\" 
                                            width=\"16\" height=\"16\" border=\"0\" alt=\"Bilder hochladen\" title=\"Bilder hochladen\"
                                            onclick=\"self.location.href='photoupload.php?pho_id=$this_pho_id'\">&nbsp;
                                     
                                        <img src=\"$g_root_path/adm_program/images/edit.png\"style=\"cursor: pointer; vertical-align: middle;\" 
                                            width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\" 
                                            onclick=\"self.location.href='photo_event_new.php?pho_id=$this_pho_id&aufgabe=change'\">&nbsp;";
                                    }
                                    
                                    echo"
                                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\"
                                         alt=\"Veranstaltung L&oumlschen\" title=\"Veranstaltung L&oumlschen\"
                                         onclick=\"self.location.href='photo_event_function.php?job=delete_request&pho_id=$this_pho_id'\">";
          
                                    if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
                                    {
                                        echo"
                                        <img src=\"$g_root_path/adm_program/images/key.png\"  alt=\"Freigeben\" title=\"Freigeben\"
                                            style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\"
                                            onclick=\"self.location.href='photos.php?pho_id=$this_pho_id&locked=0'\">";
                                    }
                                    
                                    if($adm_photo_list["pho_locked"]==0 && file_exists($ordner))
                                    {
                                        echo"
                                        <img src=\"$g_root_path/adm_program/images/key.png\" alt=\"Sperren\" title=\"Sperren\"
                                            style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\"
                                            onclick=\"self.location.href='photos.php?pho_id=$this_pho_id&locked=1'\">";
                                    }
                                }
                            echo"
                            </div>
                            </td>
                        </tr>";
                    }//Ende Ordner existiert
                };//for
            echo "</table>";

            /****************************Leere Veranstaltung****************/
            //Falls die Veranstaltung weder Bilder noch Unterordner enthaelt
                if(($adm_photo["pho_quantity"]=="0" || !isset($adm_photo["pho_quantity"])) && mysql_num_rows($result_list)==0)
                {
                    echo"<tr style=\"text-align: center;\"><td td colspan=\"$colums\">Diese Veranstaltung enth&auml;lt leider noch keine Bilder.</td></tr>";
                }
        
            if(mysql_num_rows($result_list) > 2)
            {
                // Navigation mit Vor- und Zurueck-Buttons
                // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
                echo generatePagination($base_url, $events-$ignored, 10, $event_element, TRUE);
            }
        echo "</div>";
    
        /************************Buttons********************************/
        //Uebersicht
        if($adm_photo["pho_id"]!=NULL)
        {
            echo "<p>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=". $adm_photo["pho_pho_id_parent"]. "\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=". $adm_photo["pho_pho_id_parent"]. "\">Zur&uuml;ck</a>
                </span>
            </p>";
        }
    
        /***************************Seitenende***************************/
    echo"</div>";
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>