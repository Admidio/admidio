<?php
/******************************************************************************
 * Update der Admidio-Datenbank auf eine neue Version
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode     = 1 : (Default) Statuspruefung und Anzeige, ob ein Update notwendig ist
 *            2 : Update durchfuehren
 *            3 : Ergebnis des Updates ausgeben
 *
 *****************************************************************************/

function showPage($message, $next_url, $icon, $icon_text)
{
    // Html des Modules ausgeben
    global $g_root_path;
    echo '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
    <head>
        <!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->
        
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="author"   content="Admidio Team" />
        <meta name="robots"   content="noindex" />
        
        <title>Admidio - Update</title>

        <link rel="stylesheet" type="text/css" href="../adm_themes/classic/css/system.css" />
        <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/common_functions.js"></script>

        <!--[if lt IE 7]>
        <script type="text/javascript"><!--
            window.attachEvent("onload", correctPNG);
        --></script>
        <![endif]-->
        
        <script><!--
            imgLoader = new Image();
            imgLoader.src = "../adm_themes/classic/icons/loader.gif";

            function startUpdate()
            {
                submit_button = document.getElementById(\'next_page\');
                if(submit_button.value == \'Datenbank aktualisieren\')
                {
                    submit_button.disabled  = true;
                    document.btn_icon.src = imgLoader.src;
                    document.getElementById(\'btn_text\').innerHTML = \'Datenbank wird aktualisiert\';
                }
                document.forms[0].submit();
            }
        --></script>
    </head>
    <body>
        <form action="'. $next_url. '" method="post">
        <div class="formLayout" id="installation_form">
            <div class="formHead" style="text-align: left; letter-spacing: 0em;">
                <img style="float:left; padding: 5px 0px 0px 0px; border: none;" src="../adm_themes/classic/images/admidio_logo_50.png" alt="www.admidio.org" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <div style="font-size: 16pt; font-weight: bold; text-align: right; padding: 5px 10px 10px 0px;">Version '. ADMIDIO_VERSION. '</div>
                <div style="font-size: 11pt; padding: 0px 0px 5px 0px;">Die Online-Mitgliederverwaltung f&uuml;r Vereine, Gruppen und Organisationen</div>
            </div>

            <div class="formBody" style="text-align: left;">
                <p class="bigFontSize">'.
                    $message.
                '</p>

                <div class="formSubmit" style="text-align: center;">
                    <button type="button" id="next_page" name="next_page" value="'. $icon_text. '" onclick="startUpdate()"><img id="btn_icon" src="../adm_themes/classic/icons/'. $icon. '" alt="'. $icon_text. '" />&nbsp;<span id="btn_text">'. $icon_text. '</span></button>
                </div>            
            </div>
        </div>
        </form>

        <script type="text/javascript"><!--
            document.getElementById(\'next_page\').focus();
        --></script>
    </body>
    </html>';
    exit();
}

// Uebergabevariablen pruefen

if(isset($_GET['mode']) && is_numeric($_GET['mode']))
{
    $req_mode = $_GET['mode'];
}
else
{
    $req_mode = 1;
}

$admidio_path = substr(__FILE__, 0, strpos(__FILE__, "adm_install")-1);

// Konstanten und Konfigurationsdatei einbinden
require_once($admidio_path. "/config.php");
require_once($admidio_path. "/adm_program/system/constants.php");
require_once(SERVER_PATH. "/adm_program/system/string.php");
require_once(SERVER_PATH. "/adm_program/system/function.php");
require_once(SERVER_PATH. "/adm_program/system/organization_class.php");

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = "adm";
}

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = "mysql";
}

require_once(SERVER_PATH. "/adm_program/system/". $g_db_type. "_class.php");

 // Verbindung zu Datenbank herstellen
$g_db = new MySqlDB();
$g_adm_con = $g_db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// Daten der aktuellen Organisation einlesen
$g_current_organization = new Organization($g_db, $g_organization);

if($g_current_organization->getValue("org_id") == 0)
{
    // Organisation wurde nicht gefunden
    die("<div style=\"color: #CC0000;\">Error: ". $message_text['missing_orga']. "</div>");
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$g_preferences = $g_current_organization->getPreferences();

$message = "";

if($req_mode == 1)
{
    // pruefen, ob ein Update ueberhaupt notwendig ist
    if(isset($g_preferences['db_version']) == false)
    {
        $message   = '<strong>Eine Aktualisierung der Datenbank ist erforderlich</strong><br /><br />
                      Sie haben bisher eine Version 1.x von Admidio verwendet. Um Ihre Datenbank
                      erfolgreich auf Admidio '. ADMIDIO_VERSION. ' zu migrieren, ist es erforderlich,
                      dass Sie Ihre bisherige Version angeben:<br /><br />
                      Bisherige Admidio-Version:&nbsp;
                      <select id="old_version" name="old_version" size="1">
                          <option value="0" selected="selected">- Bitte wählen -</option>
                          <option value="1.4">Version 1.4.*</option>
                          <option value="1.3">Version 1.3.*</option>
                          <option value="1.2">Version 1.2.*</option>
                      </select>';
        showPage($message, "update.php?mode=2", "database_in.png", "Datenbank aktualisieren");
    }
    elseif(version_compare(substr($g_preferences['db_version'], 0, 3), substr(ADMIDIO_VERSION, 0, 3)) != 0)
    {
        $message   = "<strong>Eine Aktualisierung der Datenbank ist erforderlich</strong><br /><br />";
        showPage($message, "update.php?mode=2", "database_in.png", "Datenbank aktualisieren");
    }
    elseif(version_compare(substr($g_preferences['db_version'], 0, 3), substr(ADMIDIO_VERSION, 0, 3)) == 0)
    {
        $message   = "<strong>Eine Aktualisierung ist nicht erforderlich</strong><br /><br />
                      Die Admidio-Datenbank ist aktuell.";
        showPage($message, "$g_root_path/index.html", "application_view_list.png", "Übersichtsseite");
    }
}
elseif($req_mode == 2)
{
    // Updatescripte fuer die Datenbank verarbeiten
    
    if(isset($g_preferences['db_version']) == false)
    {
        if(isset($_POST['old_version']) == false 
        || strlen($_POST['old_version']) > 3
        || $_POST['old_version'] == 0)
        {
            $message   = "Das Feld <strong>bisherige Admidio-Version</strong> ist nicht gefüllt.";
            showPage($message, "update.php", "back.png", "Zurück");
        }
        $old_version = $_POST['old_version'];
    }
    else
    {
        $old_version = substr($g_preferences['db_version'], 0, 3);
    }

    $main_version_old  = substr($old_version, 0, 1);
    $sub_version_old   = substr($old_version, 2, 1);
    $main_version_new  = substr(ADMIDIO_VERSION, 0, 1);
    $sub_version_new   = substr(ADMIDIO_VERSION, 2, 1);
    $flag_next_version = true;

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(120);

    // vor dem Update erst einmal alle Session loeschen, damit User ausgeloggt sind
    $sql = "DELETE FROM ". TBL_SESSIONS;
    $g_db->query($sql);

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    include("db_scripts/preferences.php");

    $sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
    $result_orga = $g_db->query($sql);

    while($row_orga = $g_db->fetch_array($result_orga))
    {
        $g_current_organization->setValue("org_id", $row_orga['org_id']);
        $g_current_organization->setPreferences($orga_preferences, false);
    }

    // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
    while($flag_next_version)
    {
        // Update-Datei der naechsten hoeheren Version ermitteln
        $flag_next_version = false;
        $sub_version_old   = $sub_version_old + 1;
        $sql_file  = "db_scripts/upd_". $main_version_old. "_". $sub_version_old. "_db.sql";
        $conv_file = "db_scripts/upd_". $main_version_old. "_". $sub_version_old. "_conv.php";                
        
        if(file_exists($sql_file))
        {
            // SQL-Script abarbeiten
            $file    = fopen($sql_file, "r")
                       or showPage("Die Datei <strong>$sql_file</strong> konnte nicht geöffnet werden.", "update.php", "back.png", "Zurück");
            $content = fread($file, filesize($sql_file));
            $sql_arr = explode(";", $content);
            fclose($file);

            foreach($sql_arr as $sql)
            {
                if(strlen(trim($sql)) > 0)
                {
                    // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
                    $sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
                    $g_db->query($sql);
                }
            }
            
            $flag_next_version = true;
        }

        if(file_exists($conv_file))
        {
            // Nun das PHP-Script abarbeiten
            include($conv_file);
            $flag_next_version = true;
        }

        // keine Datei mit der Version gefunden, dann die Main-Version hochsetzen
        if($flag_next_version == false && $main_version_old < 2)
        {
            $main_version_old  = $main_version_old + 1;
            $sub_version_old   = -1;
            $flag_next_version = true;
        }
    }

    // nach einem erfolgreichen Update noch die neue Versionsnummer in DB schreiben
    $sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
    $result_orga = $g_db->query($sql);

    while($row_orga = $g_db->fetch_array($result_orga))
    {
        $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '". ADMIDIO_VERSION. "'
                 WHERE prf_org_id  = ". $row_orga['org_id']. "
                   AND prf_name    = 'db_version' ";
        $g_db->query($sql);                
    }

    // globale Objekte aus einer evtl. vorhandenen Session entfernen, 
    // damit diese neu eingelesen werden muessen
    session_name('admidio_php_session_id');
    session_start();
    unset($_SESSION['g_current_organisation']);
    unset($_SESSION['g_preferences']);
    unset($_SESSION['g_current_user']);

    $message   = '<strong>Die Aktualisierung war erfolgreich</strong><br /><br />
                  Die Admidio-Datenbank ist jetzt auf die Version '. ADMIDIO_VERSION. ' aktualisiert worden.<br />
                  Sie können nun wieder mit Admidio arbeiten.';
    showPage($message, "$g_root_path/index.html", "application_view_list.png", "Übersichtsseite");
}

?>