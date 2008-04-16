<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id: id des Albums zu dem die Bilder hinzugefuegt werden sollen
 *
 *****************************************************************************/

require("../../system/photo_album_class.php");
require("../../system/common.php");
require("../../system/login_valid.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
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
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue("pho_id") == $_GET["pho_id"])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new PhotoAlbum($g_db, $_GET["pho_id"]);
    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("invalid");
}

// Html-Kopf ausgeben
$g_layout['title'] = "Fotos hochladen";
require(THEME_SERVER_PATH. "/overall_header.php");

/**************************Formular********************************************************/
echo"
<form method=\"post\" action=\"$g_root_path/adm_program/modules/photos/photoupload_do.php?pho_id=". $_GET['pho_id']. "\" enctype=\"multipart/form-data\">
<div class=\"formLayout\" id=\"photo_upload_form\">
    <div class=\"formHead\">Bilder hochladen</div>
    <div class=\"formBody\">
        <p>
			Die Bilder werden zu dem Album <strong>".$photo_album->getValue("pho_name")."</strong> hinzugefügt.<br />"
            ."(Beginn: ". mysqldate("d.m.y", $photo_album->getValue("pho_begin")). ")
        </p>

        <ul class=\"formFieldList\">
            <li><dl>
                <dt><label for=\"bilddatei1\">Bild 1:</label></dt>
                <dd><input type=\"file\" id=\"bilddatei1\" name=\"bilddatei[]\" value=\"durchsuchen\" /></dd>
            </dl></li>
            <li><dl>
                <dt><label for=\"bilddatei1\">Bild 2:</label></dt>
                <dd><input type=\"file\" id=\"bilddatei2\" name=\"bilddatei[]\" value=\"durchsuchen\" /></dd>
            </dl></li>
            <li><dl>
                <dt><label for=\"bilddatei1\">Bild 3:</label></dt>
                <dd><input type=\"file\" id=\"bilddatei3\" name=\"bilddatei[]\" value=\"durchsuchen\" /></dd>
            </dl></li>
            <li><dl>
                <dt><label for=\"bilddatei1\">Bild 4:</label></dt>
                <dd><input type=\"file\" id=\"bilddatei4\" name=\"bilddatei[]\" value=\"durchsuchen\" /></dd>
            </dl></li>
            <li><dl>
                <dt><label for=\"bilddatei1\">Bild 5:</label></dt>
                <dd><input type=\"file\" id=\"bilddatei5\" name=\"bilddatei[]\" value=\"durchsuchen\" /></dd>
            </dl></li>
        </ul>
        <hr />
        <div class=\"formSubmit\">
            <button name=\"upload\" type=\"submit\" value=\"speichern\"><img src=\"". THEME_PATH. "/icons/page_white_get.png\" alt=\"Speichern\" />&nbsp;Bilder hochladen</button>
        </div>
   </div>
</div>
</form>";

echo "
<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>    
    <li>
        <span class=\"iconTextLink\">
            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help&amp;window=true','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\"  
                onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help',this);\" onmouseout=\"ajax_hideTooltip()\" />   
            <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help&amp;window=true','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\">Hilfe</a>
        </span>
    </li>
</ul>";

//Seitenende
echo"
<script type=\"text/javascript\"><!--
        document.getElementById('bilddatei1').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>