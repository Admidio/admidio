<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * usr_id: id des Users dessen Bild geaendert werden soll
 * job - save :   Welcher Teil des Skriptes soll ausgeführt werden
 *     - dont_save :
 *     - upload :
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
require("../../system/login_valid.php");

//pruefen ob in den aktuellen Servereinstellungen file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=no_file_upload_server";
    header($location);
    exit();
}

// Uebergabevariablen pruefen

if(isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=usr_id";
    header($location);
    exit();
}

if(isset($_GET["job"]) && $_GET["job"] != "save" 
&& $_GET["job"] != "dont_save" && $_GET["job"] != "upload")
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=job";
    header($location);
    exit(); 
}

if(!array_key_exists('usr_id', $_GET))
{
    // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
    $user_id = $g_current_user->id;
    $edit_user = true;
}
else
{
    // Daten eines anderen Users anzeigen und pruefen, ob editiert werden darf
    $user_id = $_GET['usr_id'];
    if(editUser())
    {
        // jetzt noch schauen, ob User ueberhaupt Mitglied in der Gliedgemeinschaft ist
        if(isMember($_GET['usr_id']))      
        {
            $edit_user = true;
        }
        else
        {
            $edit_user = false;
        }
    }
    else
    {
        $edit_user = false;
    }
}


// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if(!editUser() && $user_id != $g_current_user->id)
{
   $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

// User auslesen
if($user_id > 0)
{
    $user = new User($g_adm_con);
    $user->GetUser($user_id);
}


//Pfad fuer zwischenspeicherung des Bildes
$bild="../../../adm_my_files/photos/".$user_id.".jpg";

    /*****************************Bild speichern*************************************/
    if($_GET["job"]=="save")
    {

        //Bilddaten in Datenbank schreiben
        $database_pic = addslashes(fread(fopen($bild, "r"), filesize($bild)));

        $sql="  UPDATE ". TBL_USERS. "
                SET usr_photo = '$database_pic'
                WHERE usr_id = $user_id ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        //Zwischenspeicher leeren
        if(file_exists("$bild"))
        {
            unlink("$bild");
        }

        // zur Ausgangsseite zurueck
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=profile_photo_update&timer=2000&url=".
                    urlencode("$g_root_path/adm_program/modules/profile/profile.php?user_id=".$user_id."");
        header($location);
        exit();

    }
        /*****************************Bild nicht speichern*************************************/
    if($_GET["job"]=="dont_save")
    {
        //Zwischenspeicher leeren
        if(file_exists("$bild"))
        {
            unlink("$bild");
        }

        // zur Ausgangsseite zurueck
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=profile_photo_update_cancel&timer=2000&url=".
                    urlencode("$g_root_path/adm_program/modules/profile/profile.php?user_id=".$user_id."");
        header($location);
        exit();

    }
    /***********************Kontrollmechanismen*********************************/
    //kontrollmechanismen
    if($_POST["upload"])
    {
        
        //Dateigroesse
        if ($_FILES["bilddatei"]["error"]==1)
        {
            $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=profile_photo_2big";
            header($location);
            exit();
        }
        
        //Kontrolle ob Bilder ausgewaehlt wurden
        if(!file_exists($_FILES["bilddatei"]["tmp_name"]))
        {
            $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=profile_photo_nopic";
            header($location);
            exit();
        }

        //Dateiendung
        $bildinfo=getimagesize($_FILES["bilddatei"]["tmp_name"]);
        if ($_FILES["bilddatei"]["name"]!=NULL && $bildinfo['mime']!="image/jpeg")
        {
            $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=dateiendungphotoup";
            header($location);
            exit();
        }

   }//Kontrollmechanismen


    /*****************************HTML-Teil*************************************/
echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Profilfoto</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");



   /*****************************Bild hochladen*************************************/
    if($_GET["job"]==NULL)
    {
        echo "
        <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

            <div class=\"formHead\">";
                if($user_id == $g_current_user->id)
                {
                    echo strspace("Mein Profilfoto &auml;ndern", 2);
                }
                else
                {
                    echo strspace("Profilfoto von ". $user->first_name. " ". $user->last_name. " &auml;ndern", 1);
                }
            echo "</div>

            <div class=\"formBody\">";
                echo"Aktuelles Bild:<br>";

                //Nachsehen ob fuer den User ein Photo gespeichert wurde
                $sql =" SELECT usr_photo
                        FROM ".TBL_USERS."
                        WHERE usr_id = '$user_id'";
                $result_photo = mysql_query($sql, $g_adm_con);
                db_error($result_photo);

                //Falls vorhanden Bild ausgeben
                if(@MYSQL_RESULT($result_photo,0,"usr_photo")!=NULL)
                {
                    echo"<img src=\"profile_photo_show.php?usr_id=$user_id\"\">";
                }
                //wenn nicht Schattenkopf
                else
                {
                    echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\">";
                }
                echo"<br><br>";

            //Bildupload
            echo"
            <form name=\"photoup\" method=\"post\" action=\"profile_photo_edit.php?job=upload&usr_id=".$user_id."\" enctype=\"multipart/form-data\">
                Bitte hier ein neues Bild ausw&auml;hlen:
                <p><input type=\"file\" id=\"bilddatei\" name=\"bilddatei\" size=\"40\" value=\"durchsuchen\"></p>
                <hr width=\"85%\" />
                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile.php?user_id=".$user_id."'\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck
                    </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"upload\" type=\"submit\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/page_white_get.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Bild Hochladen
                    </button>
                 </div>
            </form>";

            echo"
            </div>
        </div>";
    }

    /*****************************Bild zwischenspeichern bestaetigen***********************************/
    if($_GET["job"]=="upload")
    {
        echo "
        <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">

            <div class=\"formHead\">";
                if($user_id == $g_current_user->id)
                {
                    echo strspace("Mein Profilfoto", 2);
                }
                else
                {
                   echo strspace("Profilfoto von ". $user->first_name. " ". $user->last_name, 1);
                }
            echo "</div>

            <div class=\"formBody\">";
            //Groessnanpassung Bild und Bericht
                if(move_uploaded_file($_FILES["bilddatei"]["tmp_name"], "../../../adm_my_files/photos/".$user_id.".jpg"))
                {

                    //Ermittlung der Original Bildgroesse
                    $bildgroesse = getimagesize("$bild");

                    //Errechnung seitenverhaeltniss
                    $seitenverhaeltnis = $bildgroesse[0]/$bildgroesse[1];

                    //x-Seite soll scalliert werden
                    if(($bildgroesse[0]/130)>=($bildgroesse[1]/170))
                    {
                        $neubildsize = array (130, round(130/$seitenverhaeltnis));

                    }

                    //y-Seite soll scalliert werden
                    if(($bildgroesse[0]/130)<($bildgroesse[1]/170))
                    {
                        $neubildsize =  array (round(170*$seitenverhaeltnis), 170);
                    }

                    // Erzeugung neues Bild
                    $neubild = imagecreatetruecolor($neubildsize[0], $neubildsize[1]);

                    //Aufrufen des Originalbildes
                    $bilddaten = imagecreatefromjpeg("$bild");

                    //kopieren der Daten in neues Bild
                    imagecopyresampled($neubild, $bilddaten, 0, 0, 0, 0, $neubildsize[0], $neubildsize[1], $bildgroesse[0], $bildgroesse[1]);

                    //Zwischenspeichern des neuen Bildes
                    require("../../system/login_valid.php");
                    imagejpeg($neubild, $bild, 95);
                    chmod($bild, 0777);

                    imagedestroy($neubild);

                    //Nachsehen ob fuer den User ein Photo gespeichert war
                    $sql =" SELECT usr_photo
                            FROM ".TBL_USERS."
                            WHERE usr_id = '$user_id'";
                    $result_photo = mysql_query($sql, $g_adm_con);
                    db_error($result_photo);

                    //neues und altes Bild anzeigen
                    echo"
                    <table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" style=\"width: 100%\">
                        <tr style=\"text-align: center;\">
                            <td>Aktuelles Bild:<br>";
                                //Falls vorhanden Bild ausgeben
                                if(@MYSQL_RESULT($result_photo,0,"usr_photo")!=NULL)
                                {
                                    echo"<img src=\"profile_photo_show.php?usr_id=$user_id\"\">";
                                }
                                //wenn nicht Schattenkopf
                                else
                                {
                                    echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\">";
                                }
                                echo"
                            </td>
                            <td>Neues Bild:<br><img src=\"".$bild."\"\"></td>
                        </tr>
                    </table>

                    <hr width=\"85%\" />
                    <div style=\"margin-top: 6px;\">
                        <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&usr_id=".$user_id."'\">
                            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                            &nbsp;Abbrechen
                        </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <button name=\"update\" type=\"button\" value=\"update\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=save&usr_id=".$user_id."'\">
                            <img src=\"$g_root_path/adm_program/images/database_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                            &nbsp;Neues Bild &uuml;bernehmen
                        </button>
                 </div>";
                }
            echo"
            </div>
        </div>";
    }

    echo "
    <script type=\"text/javascript\"><!--
        document.getElementById('bilddatei').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";

?>
