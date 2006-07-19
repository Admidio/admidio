<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 * 
 * pho_id: id der Veranstaltung zu der die Bilder hinzugefuegt werden sollen
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
 * Foundation, Inc., 79 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid & !editPhoto())
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
    header($location);
    exit();
}

//Kontrolle ob Server Dateiuploads zulaesst
if(ini_get(file_uploads)==0)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=no_file_upload_server";
    header($location);
    exit();
}


//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid & editPhoto())
{
    //Uebernahme Variablen
    $pho_id= $_GET['pho_id'];

    //erfassen der Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $adm_photo = mysql_fetch_array($result);
    
    //Ordnerpfad
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    //Erfassen der Eltern Veranstaltung
    if($adm_photo["pho_pho_id_parent"]!=NULL)
    {
        $pho_parent_id=$adm_photo["pho_pho_id_parent"];
        $sql="  SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_id ='$pho_parent_id'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $adm_photo_parent = mysql_fetch_array($result);
    }

 
    //Beginn HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
        <head>
            <title>$g_current_organization->longname - Fotos hochladen</title>
            <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

            <!--[if lt IE 7]>
                <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
            <![endif]-->";

            require("../../../adm_config/header.php");
        echo "
        </head>";

        require("../../../adm_config/body_top.php");
            echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";


            /**************************Formular********************************************************/
            echo"
            <form name=\"photoup\" method=\"post\" action=\"photoupload_do.php?pho_id=$pho_id\" enctype=\"multipart/form-data\">
                <div style=\"width: 410px\" align=\"center\" class=\"formHead\">Bilder hochladen</div>
                <div style=\"width: 410px\" align=\"center\" class=\"formBody\">
                    Bilder zu dieser Veranstaltung hinzuf&uuml;gen:<br>"
                    .$adm_photo["pho_name"]."<br>"
                    ."(Beginn: ". mysqldate("d.m.y", $adm_photo["pho_begin"]).")"
                    ."<hr width=\"85%\" />
                    <p>Bild 1:<input type=\"file\" id=\"bilddatei1\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
                    <p>Bild 2:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
                    <p>Bild 3:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
                    <p>Bild 4:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
                    <p>Bild 5:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>

                    <hr width=\"85%\" />
                    Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\">
                    <hr width=\"85%\" />

                    <div style=\"margin-top: 6px;\">
                        <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_parent["pho_id"]."'\">
                            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                            &nbsp;Zur&uuml;ck
                        </button>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <button name=\"upload\" type=\"submit\" value=\"speichern\">
                            <img src=\"$g_root_path/adm_program/images/page_white_get.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                            &nbsp;Bilder hochladen
                        </button>
                    </div>
               </div> 
            </form>";

            //Seitenende
            echo"
            </div>
            <script type=\"text/javascript\"><!--
                    document.getElementById('bilddatei1').focus();
            --></script>";

            require("../../../adm_config/body_bottom.php");
        echo"</body>
    </html>";
}//if Moderator
?>