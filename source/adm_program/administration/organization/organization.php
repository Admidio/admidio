<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_text.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show('norights');
}

// der Installationsordner darf aus Sicherheitsgruenden nicht existieren
if($g_debug == 0 && file_exists('../../../adm_install'))
{
    $g_message->show('installFolderExists');
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

$languages = array('de' => 'deutsch', 'en' => 'english');

if(isset($_SESSION['organization_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['organization_request']);
    unset($_SESSION['organization_request']);
}
else
{
    foreach($g_current_organization->dbColumns as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // alle Systemeinstellungen in das form-Array schreiben
    foreach($g_preferences as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // Forumpassword immer auf 0000 setzen, damit es nicht ausgelesen werden kann
    $form_values['forum_pw'] = '0000';
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = 'Organisationseinstellungen';
$g_layout['header'] =  '
    <script type="text/javascript"><!--
        // Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
        ids = new Array("general", "register", "announcement-module", "download-module", "photo-module", "forum",
                        "guestbook-module", "list-module", "mail-module", "system-mail", "ecard-module", "profile-module",
                        "dates-module", "links-module", "systeminfo");


        // Die eigentliche Funktion: Schaltet die Einstellungsdialoge durch
        function toggleDiv(element_id)
        {
            var i;
            for (i=0;i<ids.length;i++)
            {
                // Erstmal alle DIVs aus unsichtbar setzen
                document.getElementById(ids[i]).style.display    = "none";
            }
            // Angeforderten Bereich anzeigen
            document.getElementById(element_id).style.display    = "block";
        }

        // Versteckt oder zeigt weitere Einstellungsmöglichkeiten
        function showHideMoreSettings(LayerSetting,LayerSwith,LayerSettingName,Setting)
        {
            if(document.getElementById(LayerSwith).value == "1" && document.getElementById(LayerSetting))
            {
                if(Setting == 0)
                {
                    document.getElementById(LayerSetting).innerHTML = \'<input type="text" id="LayerSettingName" name="LayerSettingName" size="4" maxlength="4" value="'. $form_values["ecard_cc_recipients"]. '" />\';
                }
                else if(Setting == 1)
                {
                    document.getElementById(LayerSetting).innerHTML = \'<input type="text" id="LayerSettingName" name="LayerSettingName" size="4" maxlength="4" value="'. $form_values["ecard_text_length"]. '" />\';
                }
            }
            else if(document.getElementById(LayerSetting))
            {
                    document.getElementById(LayerSetting).innerHTML = "";
            }
        }
        function drawForumAccessDataTable()
        {
            var layerSetting = document.getElementById("forum_access_data");
            if(document.getElementById("forum_sqldata_from_admidio").checked == true && layerSetting)
            {
                $("#" + "forum_access_data").hide("slow");
                $("#" + "forum_access_data_text").hide("slow");
            }
            else if (document.getElementById("forum_sqldata_from_admidio").checked == false && layerSetting)
            {
                var ElementsArray = Array("forum_srv","forum_usr","forum_pw","forum_db");
                var ValuesArray = Array();
                ValuesArray[0] = Array("Server:","TEXT","50", "200","'. $form_values['forum_srv']. '");
                ValuesArray[1] = Array("User:","TEXT","50", "200","'. $form_values['forum_usr']. '");
                ValuesArray[2] = Array("Passwort:","PASSWORD","50", "200","'. $form_values['forum_pw']. '");
                ValuesArray[3] = Array("Datenbank:","TEXT","50", "200","'. $form_values['forum_db']. '");
                appendElements(ElementsArray,ValuesArray,layerSetting);

                $("#" + "forum_access_data").show("slow");
                $("#" + "forum_access_data_text").show("slow");
            }
        }
        function appendElements(array,valuesArray,layer)
        {
            layer.innerHTML="";
            for(var i = 0; i < array.length;i++)
            {
                    var li = document.createElement("LI");
                    var dl = document.createElement("DL");
                    var dt = document.createElement("DT");
                    var dd = document.createElement("DD");
                    var label = document.createElement("label");
                    var input = document.createElement("input");
                    label.appendChild(document.createTextNode(valuesArray[i][0]));
                    input.type=valuesArray[i][1];
                    input.id = array[i];
                    input.name = array[i];
                    input.maxlength = valuesArray[i][2];
                    input.width = valuesArray[i][3];
                    input.value = valuesArray[i][4];
                    li.appendChild(dl);
                    dl.appendChild(dt);
                    dl.appendChild(dd);
                    dd.appendChild(input);
                    dt.appendChild(label);
                    layer.appendChild(li);
            }
        }

        $(document).ready(function()
        {
            toggleDiv("general");
            drawForumAccessDataTable();
            $("#org_longname").focus();
        });
    //--></script>';

// Html-Kopf ausgeben
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<h1 class="moduleHeadline">Organisationseinstellungen</h1>

<div class="formLayout" id="organization_menu">
    <div class="formBody">
        <table style="border-width: 0px; width: 100%; text-align: left;">
        <tr>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'general\');"><img src="'.THEME_PATH.'/icons/options.png" alt="Allgemein" title="Allgemein" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'general\');">Allgemein</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'register\');"><img src="'.THEME_PATH.'/icons/new_registrations.png" alt="Registrierung" title="Registrierung" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'register\');">Registrierung</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'announcement-module\');"><img src="'.THEME_PATH.'/icons/announcements.png" alt="Ankündigungen" title="Ankündigungen" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'announcement-module\');">Ankündigungen</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'download-module\');"><img src="'.THEME_PATH.'/icons/download.png" alt="Downloads" title="Downloads" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'download-module\');">Downloads</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'forum\');"><img src="'.THEME_PATH.'/icons/forum.png" alt="Forum" title="Forum" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'forum\');">Forum</a></span>
        </span>
        </td>
        </tr>
        <tr>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'photo-module\');"><img src="'.THEME_PATH.'/icons/photo.png" alt="Fotos" title="Fotos" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'photo-module\');">Fotos</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'ecard-module\');"><img src="'.THEME_PATH.'/icons/ecard.png" alt="Grußkarten" title="Grußkarten" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'ecard-module\');">Grußkarten</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'guestbook-module\');"><img src="'.THEME_PATH.'/icons/guestbook.png" alt="Gästebuch" title="Gästebuch" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'guestbook-module\');">Gästebuch</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'mail-module\');"><img src="'.THEME_PATH.'/icons/email.png" alt="E-Mails" title="E-Mails" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'mail-module\');">E-Mails</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'system-mail\');"><img src="'.THEME_PATH.'/icons/system_mail.png" alt="Systemmails" title="Systemmails" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'system-mail\');">Systemmails</a></span>
        </span>
        </td>
        </tr>
        <tr>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'list-module\');"><img src="'.THEME_PATH.'/icons/list.png" alt="Listen" title="Listen" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'list-module\');">Listen</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'profile-module\');"><img src="'.THEME_PATH.'/icons/profile.png" alt="Profil" title="Profil" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'profile-module\');">Profil</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'dates-module\');"><img src="'.THEME_PATH.'/icons/dates.png" alt="Termine" title="Termine" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'dates-module\');">Termine</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'links-module\');"><img src="'.THEME_PATH.'/icons/weblinks.png" alt="Weblinks" title="Weblinks" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'links-module\');">Weblinks</a></span>
        </span>
        </td>
        <td>
        <span class="iconTextLink">
            <a href="#" onclick="toggleDiv(\'systeminfo\');"><img src="'.THEME_PATH.'/icons/info.png" alt="Systeminformationen" title="Systeminformationen" /></a>
            <span class="defaultFontSize"><a href="#" onclick="toggleDiv(\'systeminfo\');">Systeminfo</a></span>
        </span>
        </td>
        </tr>
        </table>
    </div>
</div>

<form action="'.$g_root_path.'/adm_program/administration/organization/organization_function.php" method="post">
<div class="formLayout" id="organization_form">
    <div class="formBody">
        <div class="groupBox" id="general">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/options.png" alt="Allgemein" />
                Allgemeine Einstellungen</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="org_shortname">Name (Abk.):</label></dt>
                            <dd><input type="text" id="org_shortname" name="org_shortname" readonly="readonly" style="width: 100px;" maxlength="10" value="'. $form_values['org_shortname']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="org_longname">Name (lang):</label></dt>
                            <dd><input type="text" id="org_longname" name="org_longname" style="width: 200px;" maxlength="60" value="'. $form_values['org_longname']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="org_homepage">Homepage:</label></dt>
                            <dd><input type="text" id="org_homepage" name="org_homepage" style="width: 200px;" maxlength="60" value="'. $form_values['org_homepage']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="system_language">Sprache:</label></dt>
                            <dd>
                                <select size="1" id="system_language" name="system_language">
                                    <option value="">- Bitte wählen -</option>';
                                    foreach($languages as $key => $value)
                                    {
                                        echo '<option value="'.$key.'" ';
                                        if($key == $form_values['system_language'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$value.'</option>';
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="theme">Admidio-Theme:</label></dt>
                            <dd>
                                <select size="1" id="theme" name="theme">
                                    <option value="">- Bitte wählen -</option>';
                                    $themes_path = SERVER_PATH. '/adm_themes';
                                    $dir_handle  = opendir($themes_path);

                                    while (false !== ($filename = readdir($dir_handle)))
                                    {
                                        if(is_file($filename) == false
                                        && strpos($filename, '.') !== 0)
                                        {
                                            echo '<option value="'.$filename.'" ';
                                            if($form_values['theme'] == $filename)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$filename.'</option>';
                                        }
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Das aktuelle Admidio-Layout kann hier ausgewählt werden. Es werden alle Layouts
                        aus dem Ordner adm_themes angezeigt. (Standard: modern)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="system_date">Datumformat:</label></dt>
                            <dd><input type="text" id="system_date" name="system_date" style="width: 100px;" maxlength="20" value="'. $form_values['system_date']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Das Format entspricht der PHP-Funktion <a href="http://www.php.net/date">date()</a>. (Standard: d.m.Y)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="system_time">Zeitformat:</label></dt>
                            <dd><input type="text" id="system_time" name="system_time" style="width: 100px;" maxlength="20" value="'. $form_values['system_time']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Das Format entspricht der PHP-Funktion <a href="http://www.php.net/date">date()</a>. (Standard: H:i)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="homepage_logout">Startseite (Besucher):</label></dt>
                            <dd><input type="text" id="homepage_logout" name="homepage_logout" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_logout']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Diese Seite ist die Standard-Startseite von Admidio auf die Besucher geleitet werden.
                        Der Pfad zu der Seite muss relativ zum Admidio-Verzeichnis angegeben werden.<br />
                        (Beispiel: adm_program/index.php)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="homepage_login">Startseite (angemeldete Benutzer):</label></dt>
                            <dd><input type="text" id="homepage_login" name="homepage_login" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_login']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Auf diese Seite wird der Benutzer geleitet, sobald er sich angemeldet hat.
                        Der Pfad zu der Seite muss relativ zum Admidio-Verzeichnis angegeben werden.<br />
                        (Beispiel: adm_program/index.php)
                    </li>';

                    //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                    if($g_current_organization->hasChildOrganizations() == false)
                    {
                        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                                 WHERE org_id <> ". $g_current_organization->getValue("org_id"). "
                                   AND org_org_id_parent is NULL
                                 ORDER BY org_longname ASC, org_shortname ASC ";
                        $result = $g_db->query($sql);

                        if($g_db->num_rows($result) > 0)
                        {
                            // Auswahlfeld fuer die uebergeordnete Organisation
                            echo "
                            <li>
                                <dl>
                                    <dt><label for=\"org_org_id_parent\">&Uuml;bergeordnete Organisation:</label></dt>
                                    <dd>
                                        <select size=\"1\" id=\"org_org_id_parent\" name=\"org_org_id_parent\">
                                            <option value=\"0\" ";
                                            if(strlen($form_values['org_org_id_parent']) == 0)
                                            {
                                                echo " selected=\"selected\" ";
                                            }
                                            echo ">keine</option>";

                                            while($row = $g_db->fetch_object($result))
                                            {
                                                echo "<option value=\"$row->org_id\"";
                                                    if($form_values['org_org_id_parent'] == $row->org_id)
                                                    {
                                                        echo " selected=\"selected\" ";
                                                    }
                                                    echo ">$row->org_shortname</option>";
                                            }
                                        echo "</select>
                                    </dd>
                                </dl>
                            </li>
                            <li class=\"smallFontSize\">
                                Hier kannst du die übergeordnete Organisation festlegen.
                                Diese haben dann die Berechtigung Termine für die untergeordneten Organisationen anzulegen.
                            </li>";
                        }
                    }

                    echo "
                    <li>
                        <dl>
                            <dt><label for=\"enable_bbcode\">BBCode zulassen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_bbcode\" name=\"enable_bbcode\" ";
                                if(isset($form_values['enable_bbcode']) && $form_values['enable_bbcode'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Die Benutzer können in Textboxen (z.B. Terminbeschreibungen, Gästebucheinträgen) BB-Code nutzen um den
                        Text besser zu formatieren. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_rss\">RSS-Feeds aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_rss\" name=\"enable_rss\" ";
                                if(isset($form_values['enable_rss']) && $form_values['enable_rss'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Admidio kann RSS-Feeds für verschiedene Module (Ankündigungen,
                        Termine, Gästebuch, Fotogalerien und Weblinks) auf den jeweiligen &Uuml;bersichtsseiten
                        bereitstellen, die dann über den Browser einem Feedreader zugeordnet
                        werden k&ouml;nnen. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_auto_login\">Automatisch anmelden:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_auto_login\" name=\"enable_auto_login\" ";
                                if(isset($form_values['enable_auto_login']) && $form_values['enable_auto_login'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Benutzer können beim Anmelden festlegen, ob die Anmeldung auf dem Rechner gespeichert werden soll.
                        Bei einem weiteren Besuch der Homepage sind diese Benutzer dann automatisch angemeldet. Dies kann
                        allerdings auch dazu führen, dass Benutzer diese Option unbedacht einsetzen und so evtl. fremde
                        Personen Zugriff auf die Daten bekommen. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"logout_minutes\">Automatischer Logout nach:</label></dt>
                            <dd><input type=\"text\" id=\"logout_minutes\" name=\"logout_minutes\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['logout_minutes']. "\" /> Minuten</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Dieser Wert gibt an, nach wieviel Minuten ein inaktiver Benutzer automatisch ausgeloggt wird.
                        Inaktiv ist ein Benutzer solange er keine Seite des Admidio-Systems aufruft. Diese Einstellung
                        wird ignoriert, falls der Benutzer <b>Angemeldet bleiben</b> ausgewählt hat. (Standard: 20 Minuten)
                    </li>

                     <li>
                        <dl>
                            <dt><label for=\"enable_password_recovery\">Passwort zusenden:</label>
                            </dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_password_recovery\" name=\"enable_password_recovery\" ";
                                if(isset($form_values['enable_password_recovery']) && $form_values['enable_password_recovery'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hat der Benutzer sein Passwort vergessen, kann er es sich ein neu generiertes Passwort automatisch
                        zuschicken lassen. Ist diese Option deaktiviert, kann der Benutzer nur eine Anfrage an den
                        Administrator stellen. (Standard: ja)
                    </li>
                </ul>
            </div>
        </div>";



        /**************************************************************************************/
        // Einstellungen Registrierung
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"register\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/new_registrations.png\" alt=\"Registrierung\" />
                Einstellungen Registrierung</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"registration_mode\">Registrierung:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"registration_mode\" name=\"registration_mode\">
                                    <option value=\"0\" ";
                                    if($form_values['registration_mode'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['registration_mode'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Schnelle Registrierung</option>
                                    <option value=\"2\" ";
                                    if($form_values['registration_mode'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Erweiterte Registrierung</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann die Art der Registrierung festgelegt bzw. ganz abgeschaltet werden. Mit der schnellen
                        Registrierung kann der Benutzer nur die Pflichtfelder eingeben, bei der erweiterten
                        Registrierung stehen ihm alle Felder des Profils zur Verfügung.  (Standard: Schnelle Registrierung)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_registration_captcha\">Captcha aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_registration_captcha\" name=\"enable_registration_captcha\" ";
                                if(isset($form_values['enable_registration_captcha']) && $form_values['enable_registration_captcha'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Bei der Registrierung wird für alle Benutzer bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor der Registrierung korrekt eingeben. Dies soll sicherstellen,
                        dass das Formular nicht von Spammern missbraucht werden kann. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_registration_admin_mail\">E-Mail-Benachrichtigung:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_registration_admin_mail\" name=\"enable_registration_admin_mail\" ";
                                if(isset($form_values['enable_registration_admin_mail']) && $form_values['enable_registration_admin_mail'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Mitglieder aller Rollen mit der Berechtigung <strong>Registrierungen verwalten und zuordnen</strong> erhalten eine E-Mail,
                        sobald sich ein neuer User im System registriert hat. (Standard: ja)
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Ankuendigungsmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"announcement-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/announcements.png\" alt=\"Ankündigungen\" />
                Einstellungen Ankündigungsmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_announcements_module\">Ankündigungsmodul aktivieren:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"enable_announcements_module\" name=\"enable_announcements_module\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_announcements_module'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_announcements_module'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                    <option value=\"2\" ";
                                    if($form_values['enable_announcements_module'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Ankündigungsmodul kann über diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar. (Standard: Aktiviert)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"announcements_per_page\">Anzahl Einträge pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"announcements_per_page\" name=\"announcements_per_page\"
                                     style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['announcements_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Ankündigungen die auf einer Seite dargestellt werden. Gibt es mehr Ankündigungen
                        so kann man zwischen den Ankündigungen blättern. Bei dem Wert 0 werden alle Ankündigungen
                        aufgelistet und die Blättern-Funktion deaktiviert. (Standard: 10)
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Downloadmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"download-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/download.png\" alt=\"Downloads\" />
                Einstellungen Downloadmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_download_module\">Downloadmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_download_module\" name=\"enable_download_module\" ";
                                if(isset($form_values['enable_download_module']) && $form_values['enable_download_module'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Downloadmodul kann über diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modulübersichtsseite nicht mehr angezeigt. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"max_file_upload_size\">Maximale Dateigr&ouml;&szlig;e:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"max_file_upload_size\" name=\"max_file_upload_size\" style=\"width: 50px;\"
                                    maxlength=\"10\" value=\"". $form_values['max_file_upload_size']. "\" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Benutzer können nur Dateien hochladen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so ist der Upload deaktiviert. (Standard: 4000KB)
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Fotomodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"photo-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/photo.png\" alt=\"Fotos\" />
                Einstellungen Fotomodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_photo_module\">Fotomodul aktivieren:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"enable_photo_module\" name=\"enable_photo_module\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_photo_module'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_photo_module'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                    <option value=\"2\" ";
                                    if($form_values['enable_photo_module'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Fotomodul kann über diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar.  (Standard: Aktiviert)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_show_mode\">Fotodarstellung:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"photo_show_mode\" name=\"photo_show_mode\">
                                    <option value=\"0\" ";
                                    if($form_values['photo_show_mode'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Popupfenster</option>
                                    <option value=\"1\" ";
                                    if($form_values['photo_show_mode'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Thickbox</option>
                                    <option value=\"2\" ";
                                    if($form_values['photo_show_mode'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Gleiches Fenster</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann eingestellt werden, wie die Fotos im Fotomodul präsentiert werden sollen.
                        Dies kann über ein Popup-Fenster, über eine Javascript-Animation (Thickbox) oder auf
                        dergleichen Seite in HTML erfolgen. (Standard: Thickbox)
                    </li>
                     <li>
                        <dl>
                            <dt><label for=\"photo_upload_mode\">Multiupload:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"photo_upload_mode\" name=\"photo_upload_mode\" ";
                                if(isset($form_values['photo_upload_mode']) && $form_values['photo_upload_mode'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Der Multiupload ermöglicht eine komfortable Möglichkeit mehrere Fotos gleichzeitig auszuwählen und hochzuladen.
                        Allerdings wird dies mit Hilfe einer Flashanwendung gemacht. Ist kein Flash (Version 9+) installiert oder diese Option nicht
                        aktiviert, so wird automatisch die Einzelauswahl per HTML dargestellt.
                        (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_thumbs_row\">Thumbnails pro Seite (Spalten x Zeilen):</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_thumbs_column\" name=\"photo_thumbs_column\" style=\"width: 50px;\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_column']. "\" /> x
                                <input type=\"text\" id=\"photo_thumbs_row\" name=\"photo_thumbs_row\" style=\"width: 50px;\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_row']. "\" />
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Der hier angegebene Wert bestimmt wieviele Spalten und Zeilen an Thumbnails auf einer Seite angezeigt werden. (Standard: 3 x 5)
                    </li>

                    <li>
                        <dl>
                            <dt><label for=\"photo_thumbs_scale\">Skalierung Thumbnails:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_thumbs_scale\" name=\"photo_thumbs_scale\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['photo_thumbs_scale']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann festgelegt werden auf welchen Wert die längere Bildseite in der Thumbnailanzeige
                        skaliert werden soll. Vorsicht: Werden die Thumbnails zu breit, passen weniger nebeneinander.
                        Ggf. weniger Thumbnailspalten einstellen. (Standard: 160Pixel)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_save_scale\">Skalierung beim Hochladen:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_save_scale\" name=\"photo_save_scale\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['photo_save_scale']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Beim Hochladen werden alle Fotos neu skaliert. Der eingegebene Pixelwert
                        ist der Parameter für die längere Seite des Fotos, egal ob das Foto im Hoch-
                        oder Querformat übergeben wurde. Die andere Seite wird im Verhältnis berechnet. (Standard: 640 Pixel)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_show_width\">Max. Bildanzeigegröße (Breite x Höhe):</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_show_width\" name=\"photo_show_width\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['photo_show_width']. "\" /> x
                                <input type=\"text\" id=\"photo_show_height\" name=\"photo_show_height\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['photo_show_height']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Die angegebenen Werte bestimmen die maximale Größe, die ein Bild im Anzeigefenster
                        haben darf. Das Fenster im Popup- bzw. Thickboxmodus wird automatisch in der Größe angepasst. Besonders bei der Höhe
                        ist Vorsicht angebracht, da über und unter dem Bild noch genug Platz für Layout und Browser
                        sein muss. Idealerweise orientiert sich dieser Wert an der Skalierung beim Hochladen, so dass die Bilder
                        für die Anzeige nicht neu skaliert werden müssen. (Standard: 640 x 480 Pixel)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_image_text\">Bildtext einblenden:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_image_text\" name=\"photo_image_text\" maxlength=\"60\" value=\"".$form_values['photo_image_text']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Der eingegebene Text wird in allen angezeigten Fotos, ab einer Skalierung von 200 Pixeln der längeren Seite, eingeblendet.
                        (Standard: &#169; ".$g_current_organization->getValue("org_homepage").")
                    </li>
                </ul>
            </div>
        </div>";

        /**************************************************************************************/
        //Einstellungen Forum
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"forum\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/forum.png\" alt=\"Forum\" />
                Einstellungen Forum</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_forum_interface\">Forum aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_forum_interface\" name=\"enable_forum_interface\" ";
                                if(isset($form_values['enable_forum_interface']) && $form_values['enable_forum_interface'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        <img class=\"iconHelpLink\" src=\"".THEME_PATH."/icons/warning.png\" alt=\"Warnhinweis\" title=\"Warnhinweis\" />&nbsp;
                        Admidio selber verfügt über kein Forum. Allerdings kann ein bestehendes externes Forum (momentan nur phpBB 2.0)
                        eingebunden werden. (Standard: nein)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_version\">Genutztes Forum:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"forum_version\" name=\"forum_version\">
                                    <option value=\"phpBB2\" ";
                                    if($form_values['forum_version'] == "phpBB2")
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">phpBB2</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Welches Forum soll genutzt werden?<br/>
                        <table summary=\"Forum_Auflistung\" border=\"0\">
                            <tr><td>1) \"phpbb2\"</td><td> - PHP Bulletin Board 2.x (Standard)</td></tr>
                        </table>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_link_intern\">Forum Link Intern aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"forum_link_intern\" name=\"forum_link_intern\" ";
                                if(isset($form_values['forum_link_intern']) && $form_values['forum_link_intern'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Aktiviert: Forum wird innerhalb des Admidio Layouts angezeigt. (Standard)<br />
                        Deaktiviert: Forum wird in einem neuen Browserfenster angezeigt.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_width\">Forum Breite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"forum_width\" name=\"forum_width\" style=\"width: 50px;\" maxlength=\"4\" style=\"width: 50px;\" value=\"". $form_values['forum_width']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        <img class=\"iconHelpLink\" src=\"".THEME_PATH."/icons/warning.png\" alt=\"Warnhinweis\" title=\"Warnhinweis\" />&nbsp;
                        Achtung, ändern des Wertes kann das Layout verrutschen lassen. (Standard: 570Pixel)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_export_user\">Admidiobenutzer exportieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"forum_export_user\" name=\"forum_export_user\" ";
                                if(isset($form_values['forum_export_user']) && $form_values['forum_export_user'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Vorhandene Admidiobenutzer werden automatisch beim Anmelden des Benutzers ins Forum exportiert und dort als Forumsbenutzer angelegt. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_set_admin\">Webmasterstatus ins Forum exportieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"forum_set_admin\" name=\"forum_set_admin\" ";
                                if(isset($form_values['forum_set_admin']) && $form_values['forum_set_admin'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Existierende Admidio-Webmaster bekommen automatisch den Status eines Forumadministrators. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_praefix">Forum Tabellen praefix:</label></dt>
                            <dd>
                                <input type="text" id="forum_praefix" name="forum_praefix" style="width: 50px;" value="'. $form_values['forum_praefix']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Hier wird das Präfix der Tabellen des phpBB-Forums angegeben. (Beispiel: phpbb)
                    </li>
                    <li>
                        <dl>
                            <dt><strong>Zugangsdaten zur Datenbank des Forums</strong></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_sqldata_from_admidio">Zugangsdaten von Admidio verwenden:</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_sqldata_from_admidio" name="forum_sqldata_from_admidio" onclick="javascript:drawForumAccessDataTable();" ';
                                if(isset($form_values['forum_sqldata_from_admidio']) && $form_values['forum_sqldata_from_admidio'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Läuft das Forum über dieselbe Datenbank, wie Admidio, so kann dieses Flag gesetzt werden und
                        die Zugangsdaten müssen nicht mehr eingegeben werden. (Standard: nein)
                    </li>
                    <li id="forum_access_data"></li>
                    <li id="forum_access_data_text" class="smallFontSize">
                        Läuft das Forum auf einer anderen Datenbank als Admidio, so müssen die Zugangsdaten zu dieser
                        Datenbank angegeben werden.
                    </li>
                </ul>
            </div>
        </div>';

        /**************************************************************************************/
        //Einstellungen Gaestebuchmodul
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"guestbook-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/guestbook.png\" alt=\"Gästebuch\" />
                Einstellungen Gästebuchmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_guestbook_module\">Gästebuch aktivieren:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"enable_guestbook_module\" name=\"enable_guestbook_module\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_guestbook_module'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_guestbook_module'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                    <option value=\"2\" ";
                                    if($form_values['enable_guestbook_module'] == 2)
                                    {
                                        echo "selected=\"selected\" ";
                                    }
                                    echo ">Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Gästebuch kann über diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar. (Standard: Aktiviert)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"guestbook_entries_per_page\">Anzahl Einträge pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"guestbook_entries_per_page\" name=\"guestbook_entries_per_page\"
                                     style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['guestbook_entries_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Gästebucheinträge die auf einer Seite dargestellt werden. Gibt es mehr Einträge
                        so kann man zwischen den Einträgen blättern. Bei dem Wert 0 werden alle Gästebucheinträge
                        aufgelistet und die Blättern-Funktion deaktiviert. (Standard: 10)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_guestbook_captcha\">Captcha aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_guestbook_captcha\" name=\"enable_guestbook_captcha\" ";
                                if(isset($form_values['enable_guestbook_captcha']) && $form_values['enable_guestbook_captcha'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Für nicht eingeloggte Benutzer wird im Gästebuchformular bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Absenden des Formularinhalts korrekt eingeben.
                        Dies soll sicherstellen, dass das Formular nicht von Spammern missbraucht werden kann. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_gbook_comments4all\">Anonyme Kommentare erlauben:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_gbook_comments4all\" name=\"enable_gbook_comments4all\" ";
                                if(isset($form_values['enable_gbook_comments4all']) && $form_values['enable_gbook_comments4all'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Nicht eingeloggte Benutzer k&ouml;nnen, nach Aktivierung dieser Option, Einträge im Gästebuch kommentieren. Die Rechtevergabe
                        für dieses Feature über die Rollenverwaltung wird dann ignoriert. (Standard: nein)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_intial_comments_loading\">Kommentare direkt anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_intial_comments_loading\" name=\"enable_intial_comments_loading\" ";
                                if(isset($form_values['enable_intial_comments_loading']) && $form_values['enable_intial_comments_loading'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Wenn diese Option aktiviert ist, werden beim Aufruf der Gästebuchseite die Kommentare direkt geladen und nicht ausgeblendet. (Standard: nein)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"flooding_protection_time\">Flooding Protection Intervall:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"flooding_protection_time\" name=\"flooding_protection_time\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['flooding_protection_time']. "\" /> Sekunden
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Für nicht eingeloggte Benutzer wird bei Einträgen im Gästebuch überprüft,
                        ob sie innerhalb des eingestellten Intervalls bereits einen Eintrag getätigt haben.
                        Damit soll verhindert werden, dass Benutzer in zu kurzen Zeitabständen hintereinander
                        ungewünschte Einträge erzeugen. Ist das Intervall auf 0 gesetzt wird diese &Uuml;berprüfung
                        nicht durchgeführt. (Standard: 60 Sekunden)
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Listenmodul
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"list-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/list.png\" alt=\"Listen\" />
                Einstellungen Listen</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"lists_roles_per_page\">Anzahl Rollen pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"lists_roles_per_page\" name=\"lists_roles_per_page\" style=\"width: 50px;\"
                                    maxlength=\"4\" value=\"". $form_values['lists_roles_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Rollen die auf einer Seite in der Listenübersicht aufgelistet werden. Gibt es mehr Rollen
                        so kann man in der Liste blättern. Bei dem Wert 0 werden alle Rollen aufgelistet und die
                        Blättern-Funktion deaktiviert. (Standard: 10)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"lists_members_per_page\">Anzahl Teilnehmer pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"lists_members_per_page\" name=\"lists_members_per_page\" style=\"width: 50px;\"
                                    maxlength=\"4\" value=\"". $form_values['lists_members_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Teilnehmer die auf einer Seite in einer Liste aufgelistet werden.
                        Gibt es mehr Teilnehmer zu einer Rolle, so kann man in der Liste blättern.
                        Die Druckvorschau und der Export sind von diesem Wert nicht betroffen.
                        Bei dem Wert 0 werden alle Teilnehmer aufgelistet und die Blättern-Funktion deaktiviert. (Standard: 20)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"lists_hide_overview_details\">Details in Übersicht einklappen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"lists_hide_overview_details\" name=\"lists_hide_overview_details\" ";
                                if(isset($form_values['lists_hide_overview_details']) && $form_values['lists_hide_overview_details'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Bei Aktivierung dieser Option werden die Details in der Listenübersicht standardmäßig eingeklappt. Auf Wunsch
                        lassen sich die Details weiterhin anzeigen. (Standard: nein)
                    </li>                 
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Mailmodul
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"mail-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/email.png\" alt=\"E-Mails\" />
                Einstellungen Mailmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_mail_module\">Mailmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_mail_module\" name=\"enable_mail_module\" ";
                                if(isset($form_values['enable_mail_module']) && $form_values['enable_mail_module'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Mailmodul kann über diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modulübersichtsseite nicht mehr angezeigt. Falls der Server keinen
                        Mailversand unterstützt, sollte das Modul deaktiviert werden. (Standard: Aktiviert)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"mail_bcc_count\">Anzahl der BCC Empfänger:</label>
                            </dt>
                            <dd>
                                <input type=\"text\" id=\"mail_bcc_count\" name=\"mail_bcc_count\" style=\"width: 50px;\" maxlength=\"6\" value=\"". $form_values['mail_bcc_count']. "\" />
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann eingestellt werden, wieviele max. BCC Empfänger pro Mail angehängt werden. (Standard: 50)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_mail_captcha\">Captcha aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_mail_captcha\" name=\"enable_mail_captcha\" ";
                                if(isset($form_values['enable_mail_captcha']) && $form_values['enable_mail_captcha'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Für nicht eingeloggte Benutzer wird im Mailformular bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Mailversand korrekt eingeben. Dies soll sicherstellen,
                        dass das Formular nicht von Spammern missbraucht werden kann. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"max_email_attachment_size\">Maximale Dateigröße für Anhänge:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"max_email_attachment_size\" name=\"max_email_attachment_size\" style=\"width: 50px;\" maxlength=\"6\" value=\"". $form_values['max_email_attachment_size']. "\" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Benutzer können nur Dateien anhängen, bei denen die Dateigröße kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so sind keine Anhänge im Mailmodul möglich. (Standard:1024KB)
                    </li>";
                    echo'
                    <li>
                        <dl>
                            <dt><label for="mail_sendmail_address">Absender Mailadresse:</label></dt>
                            <dd><input type="text" id="mail_sendmail_address" name="mail_sendmail_address" style="width: 200px;" maxlength="50" value="'. $form_values['mail_sendmail_address'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Manche Provider erlauben die Nutzung unbekannter Mailadressen als Absender nicht. 
                        In diesem Fall kann hier eine Adresse eingetragen werden, von der aus dann alle Mails aus dem Mailmodul verschickt 
                        werden, (z.B. mailversand@'.$_SERVER['HTTP_HOST'].'). 
                        Bleibt das Feld leer wird die Adresse des Absenders genutzt. (Standard: <i>leer</i>)
                    </li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Systemmails
        /**************************************************************************************/

        $text = new TableText($g_db);
        echo '
        <div class="groupBox" id="system-mail">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/system_mail.png" alt="Systemmails" />
                Einstellungen Systemmails</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_system_mails">Systemmails aktivieren:</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_system_mails" name="enable_system_mails" ';
                                if(isset($form_values['enable_system_mails']) && $form_values['enable_system_mails'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Hier können die Systemmails von Admidio deaktiviert werden. Systemmails sind Benachrichtigungen,
                        wenn sich zum Beispiel ein neuer User angemeldet hat. Aber auch Registrierungsbestätigungen
                        werden als Systemmail verschickt. Dieses Feature sollte in der Regel nicht deaktiviert werden.
                        Es sei denn der Server unterstützt keinen Mailversand.
                        Das E-Mailmodul ist durch die Deaktivierung nicht betroffen. (Standard: ja)
                    </li>                  
                    <li>
                        <dl>
                            <dt><label for="email_administrator">Systemmailadresse:</label></dt>
                            <dd><input type="text" id="email_administrator" name="email_administrator" style="width: 200px;" maxlength="50" value="'. $form_values['email_administrator'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Hier sollte die E-Mail-Adresse eines Administrators stehen. Diese wird als Absenderadresse
                        für Systemnachrichten benutzt, z.B. bei der Registierungsbestätigung. (Standard: webmaster@'. $_SERVER['HTTP_HOST'].')
                    </li>
                    <li>
                        <dl>
                            <dt><label>Systemmail-Texte:</label></dt>
                            <dd><br /></dd>
                        </dl>
                    </li>
                    <li  class="smallFontSize">
                        Hier können die Texte aller Systemmails angepasst und ergänzt werden. Die Texte sind in 2 Bereiche (Betreff &amp; Inhalt) unterteilt und
                        werden durch die Zeichenfolge <strong>#Betreff#</strong> und <strong>#Inhalt#</strong> identifiziert. Danach folgt dann
                        der jeweilige Inhalt für diesen Bereich.<br /><br />
                        In jeder Mail können folgende Platzhalter benutzt werden, welche dann zur Laufzeit durch die entsprechenden Inhalt ersetzt werden:<br />
                        <strong>%user_first_name%</strong> - Vorname des Benutzers aus dem jeweiligen Mailkontext<br />
                        <strong>%user_last_name%</strong> - Nachname des Benutzers aus dem jeweiligen Mailkontext<br />
                        <strong>%user_login_name%</strong> - Benutzername des Benutzers aus dem jeweiligen Mailkontext<br />
                        <strong>%user_email%</strong> - E-Mail des Benutzers aus dem jeweiligen Mailkontext<br />
                        <strong>%webmaster_email%</strong> - Systememailadresse der Organisation<br />
                        <strong>%organization_short_name%</strong> - Kurzbezeichnung der Organisation<br />
                        <strong>%organization_long_name%</strong> - Name der Organisation<br />
                        <strong>%organization_homepage%</strong> - URL der Webseite der Organisation<br /><br />
                    </li>';

                    $text->readData("SYSMAIL_REGISTRATION_USER");
                    echo '<li>
                        Bestätigung der Anmeldung nach der manuellen Freigabe:<br />
                        <textarea id="SYSMAIL_REGISTRATION_USER" name="SYSMAIL_REGISTRATION_USER" style="width: 100%;" rows="7" cols="40">'.$text->getValue("txt_text").'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_REGISTRATION_WEBMASTER");
                    echo '<li>
                        <br />Benachrichtung des Webmasters nach einer Registrierung:<br />
                        <textarea id="SYSMAIL_REGISTRATION_WEBMASTER" name="SYSMAIL_REGISTRATION_WEBMASTER" style="width: 100%;" rows="7" cols="40">'.$text->getValue("txt_text").'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_NEW_PASSWORD");
                    echo '<li>
                        <br />Neues Passwort zuschicken:<br />
                    </li>
                    <li class="smallFontSize">
                        Zusätzliche Variablen:<br />
                        <strong>%variable1%</strong> - Neues Passwort des Benutzers<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_NEW_PASSWORD" name="SYSMAIL_NEW_PASSWORD" style="width: 100%;" rows="7" cols="40">'.$text->getValue("txt_text").'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_ACTIVATION_LINK");
                    echo '<li>
                        <br />Neues Passwort mit Aktivierungslink:<br />
                    </li>
                    <li class="smallFontSize">
                        Zusätzliche Variablen:<br />
                        <strong>%variable1%</strong> - Neues Passwort des Benutzers<br />
                        <strong>%variable2%</strong> - Aktivierungslink für das neue Passwort<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_ACTIVATION_LINK" name="SYSMAIL_ACTIVATION_LINK" style="width: 100%;" rows="7" cols="40">'.$text->getValue("txt_text").'</textarea>
                    </li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Grußkartenmodul
        /**************************************************************************************/
        echo "
        <div class=\"groupBox\" id=\"ecard-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/ecard.png\" alt=\"Grußkarten\" />
                Einstellungen Grußkartenmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_ecard_module\">Grußkartenmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_ecard_module\" name=\"enable_ecard_module\" ";
                                if(isset($form_values['enable_ecard_module']) && $form_values['enable_ecard_module'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Grußkartenmodul kann über diese Einstellung komplett deaktiviert oder aktiviert werden.
                        Falls der Server keinen Mailversand unterstützt, sollte das Modul deaktiviert werden.
                        Dieses Modul steht generell nur eingeloggten Benutzern zur Verfügung. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_view_width\">Skalierung Vorschaubild (Breite x Höhe):</label></dt>
                            <dd><input type=\"text\" id=\"ecard_view_width\" name=\"ecard_view_width\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_view_width']. "\" />
                                x
                                <input type=\"text\" id=\"ecard_view_height\" name=\"ecard_view_height\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_view_height']. "\" />
                                Pixel
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann festgelegt werden auf welchen Wert die Breite und H&ouml;he des Vorschau-Bildes skaliert werden soll.
                        (Standard: 250 x 250 Pixel²)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_card_picture_width\">Skalierung Grußkartenbild (Breite x Höhe):</label></dt>
                            <dd><input type=\"text\" id=\"ecard_card_picture_width\" name=\"ecard_card_picture_width\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_card_picture_width']. "\" />
                                x
                                <input type=\"text\" id=\"ecard_card_picture_height\" name=\"ecard_card_picture_height\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_card_picture_height']. "\" />
                                Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                       Hier kann festgelegt werden auf welchen Wert die Breite und Höhe des Grußkarten-Bildes skaliert werden soll.
                       (Standard: 400 x 250 Pixel²)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_cc_recipients\">Max. weitere Empfänger</label>
                            </dt>
                            <dd>
                            <select size=\"1\" id=\"enable_ecard_cc_recipients\" name=\"enable_ecard_cc_recipients\" style=\"margin-right:20px;\" onchange=\"javascript:showHideMoreSettings('cc_recipients_count','enable_ecard_cc_recipients','ecard_cc_recipients',0);\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_ecard_cc_recipients'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_ecard_cc_recipients'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                </select>
                                <div id=\"cc_recipients_count\" style=\"display:inline;\">";
                                if($form_values['enable_ecard_cc_recipients'] == 1)
                                {
                                echo "<input type=\"text\" id=\"ecard_cc_recipients\" name=\"ecard_cc_recipients\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_cc_recipients']. "\" />";
                                }
                            echo "</div>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann diese Einstellung deaktiviert oder falls gewünscht die max. Anzahl der weiteren Empfängern festgelegt werden. (Standard: 10)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_text_length\">Max. Länge des Mitteilungstextes:</label></dt>
                            <dd>
                             <select size=\"1\" id=\"enable_ecard_text_length\" name=\"enable_ecard_text_length\" style=\"margin-right:20px;\" onchange=\"javascript:showHideMoreSettings('text_length_count','enable_ecard_text_length','ecard_text_length',1);\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_ecard_text_length'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_ecard_text_length'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                </select>
                                <div id=\"text_length_count\" style=\"display:inline;\">";
                                if($form_values['enable_ecard_text_length'] == 1)
                                {
                               echo "<input type=\"text\" id=\"ecard_text_length\" name=\"ecard_text_length\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['ecard_text_length']. "\" />";
                                }
                            echo "</div>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann diese Einstellung deaktiviert oder falls gewünscht die max. Zeichenlänge des Mitteilungstextes festgelegt werden. (Standard: 500)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_text_length\">Template:</label></dt>
                            <dd>";
                                echo getMenueSettings(getfilenames(THEME_SERVER_PATH. '/ecard_templates'),'ecard_template',$form_values['ecard_template'],'180','false','false');
                             echo "</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier wird das Standard Template festgelegt. (Standard: Brief standard)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_text_length\">Schriftart:</label></dt>
                            <dd>";
                                echo getMenueSettings(getElementsFromFile('../../system/schriftarten.txt'),'ecard_text_font',$form_values['ecard_text_font'],'120','true','false');
                             echo "</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier wird die Standard Schriftart festgelegt. (Standard: Comic Sans MS)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_text_length\">Schriftgr&ouml;&szlig;e:</label></dt>
                            <dd>";
                                echo getMenueSettings(array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"),'ecard_text_size',$form_values['ecard_text_size'],'120','false','false');
                             echo "</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                       Hier wird die Standard Schriftgr&ouml;&szlig;e festgelegt. (Standard: 20)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_text_color\">Schriftfarbe:</label></dt>
                            <dd>";
                             echo getMenueSettings(getElementsFromFile('../../system/schriftfarben.txt'),'ecard_text_color',$form_values['ecard_text_color'],'120','false','true');
                             echo "</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier wird die Standard Schriftfarbe festgelegt. (Standard: black)
                    </li>

                </ul>
            </div>
        </div>";
        function getfilenames($directory)
        {
            $array_files    = array();
            $i                = 0;
            if($curdir = opendir($directory))
            {
                while($file = readdir($curdir))
                {
                    if($file != '.' && $file != '..')
                    {
                        $array_files[$i] = $file;
                        $i++;
                    }
                }
            }
            closedir($curdir);
            return $array_files;
        }

        // oeffnet ein File und gibt alle Zeilen als Array zurueck
        // Uebergabe:
        //            $filepath .. Der Pfad zu dem File
        function getElementsFromFile($filepath)
        {
            $elementsFromFile = array();
            $list = fopen($filepath, "r");
            while (!feof($list))
            {
                array_push($elementsFromFile,trim(fgets($list)));
            }
            return $elementsFromFile;
        }

        // gibt ein Menue fuer die Einstellungen des Grußkartenmoduls aus
        // Uebergabe:
        //             $data_array            .. Daten fuer die Einstellungen in einem Array
        //            $name                .. Name des Drop down Menues
        //            $first_value        .. der Standart Wert oder eingestellte Wert vom Benutzer
        //            $width                .. die Groeße des Menues
        //            $schowfont            .. wenn gesetzt werden   die Menue Eintraege mit der übergebenen Schriftart dargestellt   (Darstellung der Schriftarten)
        //            $showcolor            .. wenn gesetzt bekommen die Menue Eintraege einen farbigen Hintergrund (Darstellung der Farben)
        function getMenueSettings($data_array,$name,$first_value,$width,$schowfont,$showcolor)
        {
            $temp_data = "";
            $temp_data .=  '<select size="1" id="'.$name.'" name="'.$name.'" style="width:'.$width.'px;">';
            for($i=0; $i<count($data_array);$i++)
            {
                $name = "";
                if(!is_integer($data_array[$i]) && strpos($data_array[$i],'.tpl') > 0)
                {
                    $name = ucfirst(preg_replace("/[_-]/"," ",str_replace(".tpl","",$data_array[$i])));
                }
                elseif(is_integer($data_array[$i]))
                {
                    $name = $data_array[$i];
                }
                else if(strpos($data_array[$i],'.') === false)
                {
                    $name = $data_array[$i];
                }
                if($name != "")
                {
                    if (strcmp($data_array[$i],$first_value) == 0 && $schowfont != "true" && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\'>'.$name.'</option>';
                    }
                    else if($schowfont != "true" && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'">'.$name.'</option>';
                    }
                    else if (strcmp($data_array[$i],$first_value) == 0 && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\' style="font-family:'.$name.';">'.$name.'</option>';
                    }
                    else if($showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" style="font-family:'.$name.';">'.$name.'</option>';
                    }
                    else if (strcmp($data_array[$i],$first_value) == 0)
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\' style="background-color:'.$name.';">'.$name.'</option>';
                    }
                    else
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" style="background-color:'.$name.';">'.$name.'</option>';
                    }
                }
            }
            $temp_data .='</select>';
            return $temp_data;
        }

        /**************************************************************************************/
        //Einstellungen Profilmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="profile-module">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/profile.png" alt="Profil" />
                Einstellungen Profilmodul</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label>Profilfelder pflegen:</label></dt>
                            <dd>
                                <span class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php"><img
                                    src="'. THEME_PATH. '/icons/application_form.png" alt="Organisationsspezifische Profilfelder pflegen" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php">zur Profilfeldpflege wechseln</a>
                                </span>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        In der Profilfeldpflege können Profilfelder angelegt und bearbeitet werden. Diese können dann in 
                        Kategorien zusammengefasst werden.<br />
                        <img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="Warnhinweis" title="Warnhinweis" />
                        Alle nicht gespeicherten Organisationseinstellungen gehen dabei verloren.
                    </li>
                    <li>
                        <dl>
                            <dt><label for="default_country">Standard-Land:</label></dt>
                            <dd>
                                <select size="1" id="default_country" name="default_country">
                                    <option value=""';
                                    if(strlen($form_values['default_country']) == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>- Bitte wählen -</option>';

                                    // Datei mit Laenderliste oeffnen und alle Laender einlesen
                                    $country_list = fopen("../../system/staaten.txt", "r");
                                    $country = trim(fgets($country_list));
                                    while (!feof($country_list))
                                    {
                                        echo '<option value="'.$country.'"';
                                        if($country == $form_values['default_country'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$country.'</option>';
                                        $country = trim(fgets($country_list));
                                    }
                                    fclose($country_list);
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das ausgewählte Land wird beim Anlegen eines neuen Benutzers automatisch vorgeschlagen und
                        erleichtert die Eingabe. (Standard: Deutschland)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"profile_show_map_link\">Kartenlink anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"profile_show_map_link\" name=\"profile_show_map_link\" ";
                                if(isset($form_values['profile_show_map_link']) && $form_values['profile_show_map_link'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Sobald genügend Adressinformationen vorhanden sind, wird ein Link zu Google-Maps erstellt,
                        welcher den Wohnort des Benutzers anzeigt, sowie eine Routenlink ausgehend vom eigenen Wohnort. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"profile_show_roles\">Rollenmitgliedschaften anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"profile_show_roles\" name=\"profile_show_roles\" ";
                                if(isset($form_values['profile_show_roles']) && $form_values['profile_show_roles'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Es wird ein Kasten mit allen Rollen dieser Organisation angezeigt, bei denen der Benutzer Mitglied <b>ist</b>.
                        Dazu werden die entsprechenden Berechtigungen und das Eintrittsdatum aufgelistet. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"profile_show_former_roles\">Ehemalige Rollenmitgliedschaften anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"profile_show_former_roles\" name=\"profile_show_former_roles\" ";
                                if(isset($form_values['profile_show_former_roles']) && $form_values['profile_show_former_roles'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Es wird ein Kasten mit allen Rollen dieser Organisation angezeigt, bei denen der Benutzer Mitglied <b>war</b>.
                        Dazu wird das entsprechende Eintritts- und Austrittsdatum angezeigt. (Standard: ja)
                    </li>";

                    if($g_current_organization->getValue("org_org_id_parent") > 0
                    || $g_current_organization->hasChildOrganizations() )
                    {
                        echo "
                        <li>
                            <dl>
                                <dt><label for=\"profile_show_extern_roles\">Rollen anderer Organisationen anzeigen:</label></dt>
                                <dd>
                                    <input type=\"checkbox\" id=\"profile_show_extern_roles\" name=\"profile_show_extern_roles\" ";
                                    if(isset($form_values['profile_show_extern_roles']) && $form_values['profile_show_extern_roles'] == 1)
                                    {
                                        echo " checked=\"checked\" ";
                                    }
                                    echo " value=\"1\" />
                                </dd>
                            </dl>
                        </li>
                        <li class=\"smallFontSize\">
                            Ist der Benutzer Mitglied in Rollen einer anderen Organisation, so wird ein Kasten
                            mit allen entsprechenden Rollen und dem Eintrittsdatum angezeigt. (Standard: ja)
                        </li>";
                    }
                    echo '
                    <li>
                        <dl>
                            <dt><label for="profile_photo_storage">Speicherort der Profilbilder:</label></dt>
                            <dd>
                                <select size="1" id="profile_photo_storage" name="profile_photo_storage">
                                    <option value="">- Bitte wählen -</option>
                                    <option value="0" ';
                                            if($form_values['profile_photo_storage'] == 0)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>Datenbank
                                    </option>
                                    <option value="1" ';
                                            if($form_values['profile_photo_storage'] == 1)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>Ordner
                                    </option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Hier kann ausgewählt werden, ob die Profilbilder in der Datenbank oder im Ordner adm_my_files gespeichert werden.
                        Beim Wechsel zwischen den Einstellungen werden die bisherigen Bilder nicht übernommen. (Standard: Datenbank)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="profile_default_role">Standardrolle:</label></dt>
                            <dd>
                                '.generateRoleSelectBox($g_preferences['profile_default_role'], 'profile_default_role').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Diese Rolle wird neuen Benutzern automatisch zugeordnet, falls der Ersteller des neuen Benutzers keine Rechte hat,
                        Rollen zu zuordnen.
                    </li>                       
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Terminmodul
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"dates-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/dates.png\" alt=\"Termine\" />
                Einstellungen Terminmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_dates_module\">Terminmodul aktivieren:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"enable_dates_module\" name=\"enable_dates_module\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_dates_module'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_dates_module'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                    <option value=\"2\" ";
                                    if($form_values['enable_dates_module'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Terminmodul kann über diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar. (Standard: Aktiviert)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"dates_per_page\">Anzahl Einträge pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"dates_per_page\" name=\"dates_per_page\"
                                     style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['dates_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Termine die auf einer Seite dargestellt werden. Gibt es mehr Termine
                        so kann man zwischen den Terminen blättern. Bei dem Wert 0 werden alle Termine
                        aufgelistet und die Blättern-Funktion deaktiviert. (Stdandrad: 10)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"dates_show_map_link\">Kartenlink anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"dates_show_map_link\" name=\"dates_show_map_link\" ";
                                if(isset($form_values['dates_show_map_link']) && $form_values['dates_show_map_link'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Wird ein Treffpunkt angegeben, so wird versucht ein Link zu Google-Maps zu erstellen,
                        welcher den Treffpunkt anzeigt, sowie eine Routenlink ausgehend vom eigenen Wohnort. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"dates_show_calendar_select\">Kalenderauswahlbox anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"dates_show_calendar_select\" name=\"dates_show_calendar_select\" ";
                                if($form_values['dates_show_calendar_select'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\"/>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Eine Auswahlbox für die einzelnen Kalender wird angezeigt, um dem Besucher eine
                        schnelle Suche nach einem Termin zu ermöglichen. (Standard: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"dates_show_rooms\">Raum auswählbar:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"dates_show_rooms\" name=\"dates_show_rooms\" ";
                                if($form_values['dates_show_rooms'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1"/>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Eine Auswahlbox für die verfügbaren Räume wird angezeigt, um dem Ersteller eines Termins die Auswahl 
                        eines Raums zu ermöglichen. (Standard: nein)
                    </li>
                    <li>
                        <dl>
                            <dt><label>Räume pflegen:</label></dt>
                            <dd>
                                <span class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php"><img
                                    src="'. THEME_PATH. '/icons/home.png" alt="Räume anlegen und bearbeiten" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php">zur Raumpflege wechseln</a>
                                </span>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Hier können Räume angelegt und bearbeitet werden.<br />
                        <img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="Warnhinweis" title="Warnhinweis" />
                        Alle nicht gespeicherten Organisationseinstellungen gehen dabei verloren.
                    </li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Weblinksmodul
        /**************************************************************************************/

        echo "
        <div class=\"groupBox\" id=\"links-module\">
            <div class=\"groupBoxHeadline\"><img src=\"". THEME_PATH. "/icons/weblinks.png\" alt=\"Weblinks\" />
                Einstellungen Weblinkmodul</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_weblinks_module\">Weblinkmodul aktivieren:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"enable_weblinks_module\" name=\"enable_weblinks_module\">
                                    <option value=\"0\" ";
                                    if($form_values['enable_weblinks_module'] == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Deaktiviert</option>
                                    <option value=\"1\" ";
                                    if($form_values['enable_weblinks_module'] == 1)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Aktiviert</option>
                                    <option value=\"2\" ";
                                    if($form_values['enable_weblinks_module'] == 2)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Weblinksmodul kann über diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modulübersichtsseite nicht mehr angezeigt.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"weblinks_per_page\">Anzahl Links pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"weblinks_per_page\" name=\"weblinks_per_page\"
                                    style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['weblinks_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Links die auf einer Seite dargestellt werden. Gibt es mehr Links
                        so kann man zwischen den Links blättern. Bei dem Wert 0 werden alle Links
                        aufgelistet und die Blättern-Funktion deaktiviert.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"weblinks_target\">Link-Target:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"weblinks_target\" name=\"weblinks_target\">
                                    <option value=\"_self\"";
                                    if($form_values['weblinks_target'] == "_self")
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Gleiches Fenster</option>
                                    <option value=\"_blank\"";
                                    if($form_values['weblinks_target'] == "_blank")
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">Neues Fenster</option>";
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Gibt an, ob die Links im gleichen oder in einem neuen Fenster geöffnet werden.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"weblinks_redirect_seconds\">Anzeige Redirect:</label></dt>
                            <dd><input type=\"text\" id=\"weblinks_redirect_seconds\" name=\"weblinks_redirect_seconds\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $form_values['weblinks_redirect_seconds']. "\" /> Sekunden</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann die automatische Weiterleitung für Links aktiviert werden. Es wird bei Aufruf eines Links aus dem
                        Linkmodul zunächst eine Hinweisseite angezeigt, die auf das Verlassen der Admidioseiten hinweist. Nach vorgegebener
                        Zeit in Sekunden wird dann der eigentliche Link aufgerufen. Wird der Redirect auf 0 gesetzt wird der Link ohne
                        Anzeige der Hinweisseite direkt aufgerufen. (Standard: 10 Sekunden)
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Nachrichtenmodul
        /**************************************************************************************/
/*
        echo '
        <div class="groupBox" id="messages-module">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/list_small.png" alt="Nachrichten" />
                Einstellungen Nachrichtenmodul</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_messages_module">Nachrichtenmodul aktivieren:</label></dt>
                            <dd>
                                <select size="1" id="enable_messages_module" name="enable_messages_module">
                                    <option value="0" ';
                                    if($form_values['enable_messages_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>Deaktiviert</option>
                                    <option value="1" ';
                                    if($form_values['enable_messages_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>Aktiviert</option>
                                    <option value="2" ';
                                    if($form_values['enable_messages_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>Nur für registrierte Benutzer</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Das Nachrichtenmodul kann über diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modulübersichtsseite nicht mehr angezeigt.
                    </li>
                    <li>
                        <dl>
                            <dt><label for="messages_reminder">Email Benachrichtigung:</label></dt>
                            <dd>
                                <input type="checkbox" id="messages_reminder" name="messages_reminder" ';
                                if(isset($form_values['forum_link_intern']) && $form_values['messages_reminder'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Aktiviert: Das Mitglied erh&auml;lt eine Benachrichtigung per Email, wenn neue Nachrichten eintreffen.<br />
                        Deaktiviert: Keine Email Benachrichtigung.
                    </li>
                    <li>
                        <dl>
                            <dt><label for="messages_in_box">Anzahl der Nachrichten im Posteingang:</label></dt>
                            <dd>
                                <input type="text" id="messages_in_box" name="messages_in_box"
                                    style="width: 50px;" maxlength="4" value="'. $form_values['messages_in_box']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Anzahl der Nachrichten die sich im Posteingang befinden d&uuml;rfen. Wird die Anzahl
                        &uuml;berschritten, werden immer die &auml;ltesten Nachrichten gel&ouml;scht.
                        <br />Bei dem Wert 0 werden keine Nachrichten gel&ouml;scht. (Standard: 0)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="messages_out_box">Anzahl der Nachrichten im Postausgang:</label></dt>
                            <dd>
                                <input type="text" id="messages_out_box" name="messages_out_box"
                                    style="width: 50px;" maxlength="4" value="'. $form_values['messages_out_box']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Anzahl der Nachrichten die sich im Postausgang befinden d&uuml;rfen. Wird die Anzahl
                        &uuml;berschritten, werden immer die &auml;ltesten Nachrichten gel&ouml;scht.
                        <br />Bei dem Wert 0 werden keine Nachrichten gel&ouml;scht. (Standard: 0)
                    </li>
                    <li>
                        <dl>
                            <dt><label for="messages_archive">Anzahl der Nachrichten im Achriv:</label></dt>
                            <dd>
                                <input type="text" id="messages_archive" name="messages_archive"
                                    style="width: 50px;" maxlength="4" value="'. $form_values['messages_archive']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        Anzahl der Nachrichten die sich im Nachrichtenarchiv befinden dürfen. Wird die Anzahl
                        überschritten, werden immer die ältesten Nachrichten gelöscht.
                        <br />Bei dem Wert 0 werden keine Nachrichten gelöscht. (Standard: 0)
                    </li>
                </ul>
            </div>
        </div>';
*/

        /**************************************************************************************/
        //Systeminformationen
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="systeminfo">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/info.png" alt="Systeminformationen" />
                Systeminformationen
            </div>
            <div class="groupBoxBody">';
                require_once('systeminfo.php');
            echo'</div>
        </div>';
     echo'
    </div>
</div>

<div class="formLayout" id="organization_save_button">
    <div class="formBody">
        <button name="save" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
    </div>
</div>
</form>';

require(THEME_SERVER_PATH. '/overall_footer.php');
?>