<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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

require("../../system/photo_event_class.php");
require("../../system/common.php");
require("../../system/login_valid.php");

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

//Kontrolle ob Server Dateiuploads zulaesst
$ini = ini_get('file_uploads');
if($ini!=1)
{
    $g_message->show("no_file_upload_server");
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl($g_current_url);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_event']) && $_SESSION['photo_event']->getValue("pho_id") == $_GET["pho_id"])
{
    $photo_event =& $_SESSION['photo_event'];
    $photo_event->db_connection = $g_adm_con;
}
else
{
    $photo_event = new PhotoEvent($g_adm_con, $_GET["pho_id"]);
    $_SESSION['photo_event'] =& $photo_event;
}

// pruefen, ob Veranstaltung zur aktuellen Organisation gehoert
if($photo_event->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("invalid");
}

//Ordnerpfad
$ordner = "../../../adm_my_files/photos/".$photo_event->getValue("pho_begin")."_".$photo_event->getValue("pho_id");

// Html-Kopf ausgeben
$g_layout['title'] = "Fotos hochladen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");    

/**************************Formular********************************************************/
echo"
<form name=\"photoup\" method=\"post\" action=\"$g_root_path/adm_program/modules/photos/photoupload_do.php?pho_id=". $_GET['pho_id']. "\" enctype=\"multipart/form-data\">
    <div style=\"width: 410px\" align=\"center\" class=\"formHead\">Bilder hochladen</div>
    <div style=\"width: 410px\" align=\"center\" class=\"formBody\">
        Bilder zu dieser Veranstaltung hinzuf&uuml;gen:<br>"
        .$photo_event->getValue("pho_name")."<br>"
        ."(Beginn: ". mysqldate("d.m.y", $photo_event->getValue("pho_begin")).")"
        ."<hr class=\"formLine\" width=\"85%\" />
        <p>Bild 1:<input type=\"file\" id=\"bilddatei1\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
        <p>Bild 2:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
        <p>Bild 3:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
        <p>Bild 4:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>
        <p>Bild 5:<input type=\"file\" name=\"bilddatei[]\" value=\"durchsuchen\"></p>

        <hr class=\"formLine\" width=\"85%\" />
        Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\">
        <hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
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
<script type=\"text/javascript\"><!--
        document.getElementById('bilddatei1').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>