<?php
/******************************************************************************
 * Installationsdialog fuer die MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * mode : 0 (Default) Erster Dialog
 *        1 Admidio installieren - Config-Datei
 *        2 Datenbank installieren
 *        3 Datenbank updaten
 *        4 Neue Organisation anlegen
 *
 *****************************************************************************/

// pruefen, ob es eine Erstinstallation ist
if(file_exists("../adm_config/config.php"))
{
   $first_install = false;
}
else
{
   $first_install = true;
}

// Uebergabevariablen pruefen
$req_mode = 0;

if(isset($_REQUEST['mode']) && is_numeric($_REQUEST['mode']))   // mode kann per GET oder POST uebergeben werden
{
   $req_mode = $_REQUEST['mode'];
}

// Versionsnummer aus common.php auslesen
// muss hier etwas komplizierter sein, da common.php nicht eingebunden werden kann
$file_handle = fopen("../adm_program/system/common.php", "r");
$admidio_version = null;

while (!feof($file_handle) && strlen($admidio_version) == 0) 
{
    $buffer = fgets($file_handle, 4096);
    if(strpos($buffer, "ADMIDIO_VERSION") !== false)
    {
        $str_arr = explode("'", $buffer);
        $admidio_version = $str_arr[3];
    }
}
fclose ($file_handle); 

// Html des Modules ausgeben
echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>Admidio - Installation</title>

    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-15\">
    <meta name=\"author\"   content=\"Admidio Team\">
    <meta name=\"robots\"   content=\"noindex\">

    <link rel=\"stylesheet\" type=\"text/css\" href=\"../adm_program/layout/system.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"../adm_program/system/correct_png.js\"></script>
    <![endif]-->
</head>
<body>
<div align=\"center\">
    <div class=\"formLayout\" id=\"installation_form\">
        <div class=\"formHead\" style=\"text-align: left; letter-spacing: 0em;\">
            <img style=\"float:left; padding: 5px 0px 0px 0px; border: none;\" src=\"../adm_program/images/admidio_logo_50.png\" alt=\"www.admidio.org\" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <div style=\"font-size: 16pt; font-weight: bold; text-align: right; padding: 5px 10px 10px 0px;\">Version $admidio_version</div>
            <div style=\"font-size: 11pt; padding: 0px 0px 5px 0px;\">Die Online-Mitgliederverwaltung f&uuml;r Vereine, Gruppen und Organisationen</div>
        </div>

        <div class=\"formBody\">
            <div align=\"center\">";
                if($req_mode == 0)
                {
                    $title = "Installation &amp; Einrichtung";
                }
                elseif($req_mode == 1)
                {
                    $title = "Datenbank installieren";
                }
                elseif($req_mode == 2)
                {
                    $title = "Konfigurationsdatei erstellen";
                }
                elseif($req_mode == 3)
                {
                    $title = "Datenbank updaten";
                }
                elseif($req_mode == 4)
                {
                    $title = "Neue Organisation hinzuf&uuml;gen";
                }
                echo "<h1 class=\"moduleHeadline\" style=\"letter-spacing: 0.2em;\">$title</h1>";

                if($req_mode == 0)
                {
                    echo "
                    <form name=\"installation\" action=\"index.php\" method=\"post\">
                    <div class=\"groupBox\" style=\"width: 350px;\">
                        <div class=\"groupBoxHeadline\">Aktion ausw&auml;hlen</div>
                        <div class=\"groupBoxBody\">
                            <ul class=\"formFieldList\">
                                <li>
                                    <dl>
                                        <dt style=\"width: 30px;\">
                                            <input type=\"radio\" id=\"install\" name=\"mode\" value=\"1\" ";
                                            if($first_install) echo " checked ";
                                                echo "/>
                                        </dt>
                                        <dd style=\"margin-left: 30px; padding-top: 1px;\">
                                            <label for=\"install\">Admidio installieren und einrichten</label>
                                        </dd>
                                    </dl>
                                </li>
                                <li>
                                    <dl>
                                        <dt style=\"width: 30px;\">
                                            <input type=\"radio\" id=\"update\" name=\"mode\" value=\"3\" ";
                                            if(!$first_install) echo " checked ";
                                                echo "/>
                                        </dt>
                                        <dd style=\"margin-left: 30px; padding-top: 1px;\">
                                            <label for=\"update\">Admidio Datenbank updaten</label>
                                        </dd>
                                    </dl>
                                </li>
                            </ul>";
                            /* vorlaefig ausgebaut, da die Einrichtung noch nicht komfortabel genug ist
                            <br>
                            <div>&nbsp;
                                <input type=\"radio\" id=\"orga\" name=\"mode\" value=\"4\" />&nbsp;
                                <label for=\"orga\">Neue Organisation hinzuf&uuml;gen
                            </div>*/
                        echo "</div>
                    </div>";
                }
                elseif($req_mode == 2)
                {
                    echo "<br>
                    <form name=\"installation\" action=\"inst_do.php?mode=5\" method=\"post\">
                        <div class=\"groupBox\" style=\"width: 350px;\">
                            <div class=\"groupBoxHeadline\">Datei config.php anlegen</div>
                            <div class=\"groupBoxBody\">
                                <p>Laden Sie nun die Datei <b>config.php</b> herunter und kopieren Sie
                                    diese in das Verzeichnis <b>adm_config</b>.</p>
                                    <p>Klicken Sie erst danach auf <i>Weiter</i>.</p>
                                <p align=\"center\">
                                <button name=\"config_file\" type=\"button\" value=\"config_file\"  style=\"width: 190px;\"
                                onclick=\"location.href='inst_do.php?mode=2'\">
                                    <img src=\"../adm_program/images/page_white_put.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                                    width=\"16\" height=\"16\" border=\"0\" alt=\"config.php herunterladen\">
                                    config.php herunterladen</button></p>
                            </div>
                        </div>
                        <br />";
                }
                else
                {
                    // Verbindungsdaten zur Datenbank
                    echo "<form name=\"installation\" action=\"inst_do.php?mode=$req_mode\" method=\"post\">
                        <div class=\"groupBox\" style=\"width: 350px;\">
                            <div class=\"groupBoxHeadline\">Zugangsdaten MySql-Datenbank</div>
                            <div class=\"groupBoxBody\">
                                <ul class=\"formFieldList\">
                                    <li>
                                        <dl>
                                            <dt><label for=\"server\">Server:</label></dt>
                                            <dd><input type=\"text\" name=\"server\" id=\"server\" style=\"width: 200px;\" maxlength=\"50\"></dd>
                                        </dl>
                                    </li>
                                    <li>
                                        <dl>
                                            <dt><label for=\"user\">Login:</label></dt>
                                            <dd><input type=\"text\" name=\"user\" id=\"user\" style=\"width: 200px;\" maxlength=\"50\"></dd>
                                        </dl>
                                    </li>
                                    <li>
                                        <dl>
                                            <dt><label for=\"password\">Passwort:</label></dt>
                                            <dd><input type=\"password\" name=\"password\" id=\"password\" style=\"width: 200px;\" maxlength=\"50\"></dd>
                                        </dl>
                                    </li>
                                    <li>
                                        <dl>
                                            <dt><label for=\"database\">Datenbank:</label></dt>
                                            <dd><input type=\"text\" name=\"database\" id=\"database\" style=\"width: 200px;\" maxlength=\"50\"></dd>
                                        </dl>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <br />";

                        // Optionen fuer die Installation
                        if($req_mode == 1)
                        {
                            echo "
                            <div class=\"groupBox\" style=\"width: 350px;\">
                                <div class=\"groupBoxHeadline\">Optionen</div>
                                <div class=\"groupBoxBody\">
                                    <ul class=\"formFieldList\">
                                        <li>Hier k&ouml;nnen Sie ein Pr&auml;fix f&uuml;r die Datenbank-Tabellen von Admidio angeben.</li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"praefix\">Tabellenpr&auml;fix:</label></dt>
                                                <dd><input type=\"text\" name=\"praefix\" id=\"praefix\" style=\"width: 80px;\" maxlength=\"10\"></dd>
                                            </dl>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <br />";
                        }

                        // Updaten von Version
                        if($req_mode == 3)
                        {
                            echo "<br />
                            <div class=\"groupBox\" style=\"width: 350px;\">
                                <div class=\"groupBoxHeadline\">Optionen</div>
                                <div class=\"groupBoxBody\">
                                    <ul class=\"formFieldList\">
                                        <li>
                                            <dl>
                                                <dt><label for=\"version\">Update von:</label></dt>
                                                <dd>
                                                    <select size=\"1\" name=\"version\" id=\"version\">
                                                        <option value=\"0\" selected=\"selected\">- Bitte w&auml;hlen -</option>
                                                        <option value=\"5\">Version 1.4.*</option>
                                                        <option value=\"4\">Version 1.3.*</option>
                                                        <option value=\"3\">Version 1.2.*</option>
                                                    </select>
                                                </dd>
                                            </dl>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <br />";
                        }

                        // Organisation anlegen
                        if($req_mode != 3)
                        {
                            echo "
                            <div class=\"groupBox\" style=\"width: 350px;\">
                                <div class=\"groupBoxHeadline\">Organisation / Verein</div>
                                <div class=\"groupBoxBody\">
                                    <ul class=\"formFieldList\">
                                        <li>
                                            <dl>
                                                <dt><label for=\"orga_name_short\">Name (Abk.):</label></dt>
                                                <dd><input type=\"text\" name=\"orga_name_short\" id=\"orga_name_short\" style=\"width: 80px;\" maxlength=\"10\" /></dd>
                                            </dl>
                                        </li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"orga_name_long\">Name (lang):</label></dt>
                                                <dd><input type=\"text\" name=\"orga_name_long\" id=\"orga_name_long\" style=\"width: 200px;\" maxlength=\"60\" /></dd>
                                            </dl>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <br />";
                        }

                        // Webmaster anlegen
                        if($req_mode == 1
                        || $req_mode == 4)
                        {
                            echo "
                            <div class=\"groupBox\" style=\"width: 350px;\">
                                <div class=\"groupBoxHeadline\">Webmaster anlegen</div>
                                <div class=\"groupBoxBody\">
                                    <ul class=\"formFieldList\">
                                        <li>
                                            <dl>
                                                <dt><label for=\"user_last_name\">Nachname:</label></dt>
                                                <dd><input type=\"text\" name=\"user_last_name\" id=\"user_last_name\" style=\"width: 200px;\" maxlength=\"50\" /></dd>
                                            </dl>
                                        </li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"user_first_name\">Vorname:</label></dt>
                                                <dd><input type=\"text\" name=\"user_first_name\" id=\"user_first_name\" style=\"width: 200px;\" maxlength=\"50\" /></dd>
                                            </dl>
                                        </li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"user_email\">E-Mail:</label></dt>
                                                <dd><input type=\"text\" name=\"user_email\" id=\"user_email\" style=\"width: 200px;\" maxlength=\"50\" /></dd>
                                            </dl>
                                        </li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"user_login\">Benutzername:</label></dt>
                                                <dd><input type=\"text\" name=\"user_login\" id=\"user_login\" style=\"width: 200px;\" maxlength=\"50\" /></dd>
                                            </dl>
                                        </li>
                                        <li>
                                            <dl>
                                                <dt><label for=\"password\">Passwort:</label></dt>
                                                <dd><input type=\"text\" name=\"password\" id=\"password\" style=\"width: 200px;\" maxlength=\"50\" /></dd>
                                            </dl>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <br />";
                        }
                    }
                    echo "
                    <div style=\"margin-top: 15px; margin-bottom: 5px;\">";
                        if($req_mode > 0)
                        {
                            echo "<button name=\"back\" type=\"button\" value=\"back\" onclick=\"history.back()\">
                                <img src=\"../adm_program/images/back.png\" alt=\"Zurueck\">
                                &nbsp;Zur&uuml;ck</button>&nbsp;&nbsp;";
                        }
                        echo "<button name=\"forward\" type=\"submit\" value=\"forward\">Weiter&nbsp;
                            <img src=\"../adm_program/images/forward.png\" alt=\"Weiter\">
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class=\"formHead\">
            <span class=\"smallFontSize\">
                &copy; 2004 - 2007&nbsp;&nbsp;<a href=\"http://www.admidio.org\" target=\"_blank\">The Admidio Team</a>
            </span>
        </div>
    </div>
</div>
</body>
</html>";
?>