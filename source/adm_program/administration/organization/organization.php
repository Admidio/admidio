<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show("norights");
}

// der Installationsordner darf aus Sicherheitsgruenden nicht existieren
if($g_debug == 0 && file_exists("../../../adm_install"))
{
    $g_message->show("installFolderExists");
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

if(isset($_SESSION['organization_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['organization_request']);
    unset($_SESSION['organization_request']);
}
else
{
    foreach($g_current_organization->db_fields as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // alle Systemeinstellungen in das form-Array schreiben
    foreach($g_preferences as $key => $value)
    {
        $form_values[$key] = $value;
    }
    
    // Forumpassword immer auf 0000 setzen, damit es nicht ausgelesen werden kann
    $form_values['forum_pw'] = "0000";
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = "Organisationseinstellungen";
$g_layout['header'] =  "
    <style type=\"text/css\">
        .groupBox {
            visibility: hidden;
            display:    none;
            width:      95%;
        }
    </style>

    <script type=\"text/javascript\"><!--
        // Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
        ids = new Array('general', 'register', 'announcement-module', 'download-module', 'photo-module', 'forum',
                        'guestbook-module', 'list-module', 'mail-module', 'ecard-module', 'profile-module', 'dates-module',
                        'links-module');


        // Die eigentliche Funktion: Schaltet die Einstellungsdialoge durch
        function toggleDiv(element_id)
        {
            var i;
            for (i=0;i<ids.length;i++)
            {
                // Erstmal alle DIVs aus unsichtbar setzen
                document.getElementById(ids[i]).style.visibility = 'hidden';
                document.getElementById(ids[i]).style.display    = 'none';
            }
            // Angeforderten Bereich anzeigen
            document.getElementById(element_id).style.visibility = 'visible';
            document.getElementById(element_id).style.display    = 'block';
            // window.blur();
        }
        // Versteckt oder zeigt weitere Einstellungsmöglichkeiten
        function showHideMoreSettings(LayerSetting,LayerSwith,LayerSettingName,Setting)
        {
            if(document.getElementById(LayerSwith).value == \"1\" && document.getElementById(LayerSetting))
            {
				if(Setting == 0)
				{
                    document.getElementById(LayerSetting).innerHTML = \"<input type='text' id='LayerSettingName' name='LayerSettingName' size='4' maxlength='4' value='". $form_values['ecard_cc_recipients']. "' />\";
				}
				else if(Setting == 1)
				{
					document.getElementById(LayerSetting).innerHTML = \"<input type='text' id='LayerSettingName' name='LayerSettingName' size='4' maxlength='4' value='". $form_values['ecard_text_length']. "' />\";
				}
            }
            else if(document.getElementById(LayerSetting))
            {            
                    document.getElementById(LayerSetting).innerHTML = \"\";
            }
        }
        function drawForumAccessDataTable(LayerSetting,LayerSwith)
        {
			var layerSetting = document.getElementById(LayerSetting);
            if(document.getElementById(LayerSwith).checked == true && layerSetting)
            {
                    layerSetting.innerHTML = '<li><dl><dt><label for=\'forum_db\'>Datenbank:<\/label><\/dt><dd><input type=\'text\' id=\'forum_db\' name=\'forum_db\' style=\'width: 200px;\' maxlength=\'50\' value=\'". $form_values['forum_db']. "\' \/><\/dd><\/dl><\/li>';
            }
            else if (document.getElementById(LayerSwith).checked == false && layerSetting)
            {
                    layerSetting.innerHTML = \"<li><dl><dt><label for='forum_srv'>Server:<\/label><\/dt><dd><input type='text' id='forum_srv' name='forum_srv' style='width: 200px;' maxlength='50' value='". $form_values['forum_srv']. "' \/><\/dd><\/dl><\/li><li><dl><dt><label for='forum_usr'>Username:<\/label><\/dt><dd><input type='text' id='forum_usr' name='forum_usr' style='width: 200px;' maxlength='50' value='". $form_values['forum_usr']. "' \/><\/dd><\/dl><\/li><li><dl><dt><label for='forum_pw'>Passwort:<\/label><\/dt><dd><input type='password' id='forum_pw' name='forum_pw' style='width: 200px;' maxlength='50' value='". $form_values['forum_pw']. "' \/><\/dd><\/dl><\/li><li><dl><dt><label for='forum_db'>Datenbank:<\/label><\/dt><dd><input type='text' id='forum_db' name='forum_db' style='width: 200px;' maxlength='50' value='". $form_values['forum_db']. "' \/><\/dd><\/dl><\/li>\";
            }
        }
    --></script>";

// Html-Kopf ausgeben
require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Organisationseinstellungen</h1>

<div class=\"formLayout\" id=\"organization_menu\">
    <div class=\"formBody\"><center>
        <a href=\"#\" onclick=\"toggleDiv('general');\"><img src=\"". THEME_PATH. "/icons/options_big.png\" alt=\"Allgemein\" title=\"Allgemein\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('register');\"><img src=\"". THEME_PATH. "/icons/new_registrations_big.png\" alt=\"Registrierungen\" title=\"Registrierungen\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('announcement-module');\"><img src=\"". THEME_PATH. "/icons/announcements_big.png\" alt=\"Ankündigungen\" title=\"Ankündigungen\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('download-module');\"><img src=\"". THEME_PATH. "/icons/download_big.png\" alt=\"Downloads\" title=\"Downloads\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('photo-module');\"><img src=\"". THEME_PATH. "/icons/photo_big.png\" alt=\"Fotos\" title=\"Fotos\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('forum');\"><img src=\"". THEME_PATH. "/icons/forum_big.png\" alt=\"Forum\" title=\"Forum\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('guestbook-module');\"><img src=\"". THEME_PATH. "/icons/guestbook_big.png\" alt=\"Gästebuch\" title=\"Gästebuch\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('ecard-module');\"><img src=\"". THEME_PATH. "/icons/smile_big.png\" alt=\"Grußkarten\" title=\"Grußkarten\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('list-module');\"><img src=\"". THEME_PATH. "/icons/list_big.png\" alt=\"Listen\" title=\"Listen\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('mail-module');\"><img src=\"". THEME_PATH. "/icons/email_big.png\" alt=\"E-Mails\" title=\"E-Mails\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('profile-module');\"><img src=\"". THEME_PATH. "/icons/profile_big.png\" alt=\"Profil\" title=\"Profil\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('dates-module');\"><img src=\"". THEME_PATH. "/icons/dates_big.png\" alt=\"Termine\" title=\"Termine\" /></a>
	&nbsp;
        <a href=\"#\" onclick=\"toggleDiv('links-module');\"><img src=\"". THEME_PATH. "/icons/weblinks_big.png\" alt=\"Weblinks\" title=\"Weblinks\" /></a>
    </center></div>
</div>

<form action=\"$g_root_path/adm_program/administration/organization/organization_function.php\" method=\"post\">
<div class=\"formLayout\" id=\"organization_form\">
    <div class=\"formBody\">
        <div class=\"groupBox\" id=\"general\">

            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/options_small.png\" alt=\"Allgemeine Einstellungen\" title=\"Allgemeine Einstellungen\" /> Allgemeine Einstellungen</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"version\">Admidio-Version:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"version\" name=\"version\" class=\"readonly\" readonly=\"readonly\" size=\"10\" maxlength=\"10\" value=\"". ADMIDIO_VERSION. "\" />&nbsp;
                                <a href=\"http://www.admidio.org/index.php?page=download\">Update suchen</a>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_shortname\">Name (Abk.):</label></dt>
                            <dd><input type=\"text\" id=\"org_shortname\" name=\"org_shortname\" class=\"readonly\" readonly=\"readonly\" size=\"10\" maxlength=\"10\" value=\"". $form_values['org_shortname']. "\" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_longname\">Name (lang):</label></dt>
                            <dd><input type=\"text\" id=\"org_longname\" name=\"org_longname\" style=\"width: 200px;\" maxlength=\"60\" value=\"". $form_values['org_longname']. "\" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_homepage\">Homepage:</label></dt>
                            <dd><input type=\"text\" id=\"org_homepage\" name=\"org_homepage\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['org_homepage']. "\" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"theme\">Admidio-Theme:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"theme\" name=\"theme\">
                                    <option value=\"\">- Bitte wählen -</option>";
                                    $themes_path = SERVER_PATH. "/adm_themes";
                                    $dir_handle  = opendir($themes_path);

                                    while (false !== ($filename = readdir($dir_handle)))
                                    {
                                        if(is_file($filename) == false
                                        && strpos($filename, ".") !== 0)
                                        {
                                            echo "<option value=\"$filename\" ";
                                            if($form_values['theme'] == $filename)
                                            {
                                                echo " selected=\"selected\" ";
                                            }
                                            echo ">$filename</option>";
                                        }
                                    }
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                    	Das aktuelle Admidio-Layout kann hier ausgewählt werden. Es werden alle Layouts
                    	aus dem Ordner adm_themes angezeigt.
                    </li>                    
                    <li>
                        <dl>
                            <dt><label for=\"homepage_logout\">Startseite (Besucher):</label></dt>
                            <dd><input type=\"text\" id=\"homepage_logout\" name=\"homepage_logout\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['homepage_logout']. "\" /></dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Diese Seite ist die Standard-Startseite von Admidio auf die Besucher geleitet werden.
                        Der Pfad zu der Seite muss relativ zum Admidio-Verzeichnis angegeben werden.<br />
                        Beispiel: adm_program/index.php
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"homepage_login\">Startseite (angemeldete Benutzer):</label></dt>
                            <dd><input type=\"text\" id=\"homepage_login\" name=\"homepage_login\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['homepage_login']. "\" /></dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Auf diese Seite wird der Benutzer geleitet, sobald er sich angemeldet hat.
                        Der Pfad zu der Seite muss relativ zum Admidio-Verzeichnis angegeben werden.<br />
                        Beispiel: adm_program/index.php
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"email_administrator\">E-Mail Adresse des Administrator:</label></dt>
                            <dd><input type=\"text\" id=\"email_administrator\" name=\"email_administrator\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['email_administrator']. "\" /></dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier sollte die E-Mail-Adresse eines Administrators stehen. Diese wird als Absenderadresse
                        für Systemnachrichten benutzt. (z.B. bei der Registierungsbestätigung)
                    </li>";

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
                                Hier kannst du die &uuml;bergeordnete Organisation festlegen.
                                Diese haben dann die Berechtigung Termine f&uuml;r die untergeordneten Organisationen anzulegen.
                            </li>";
                        }
                    }

                    echo "
                    <li>
                        <dl>
                            <dt><label for=\"enable_system_mails\">Systemmails aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_system_mails\" name=\"enable_system_mails\" ";
                                if(isset($form_values['enable_system_mails']) && $form_values['enable_system_mails'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier können die Systemmails von Admidio deaktiviert werden. Systemmails sind Benachrichtigungen,
                        wenn sich zum Beispiel ein neuer User angemeldet hat. Aber auch Registrierungsbestätigungen
                        werden als Systemmail verschickt. Dieses Feature sollte also am besten nicht deaktiviert werden.
                        Es sei denn der Server unterstützt gar keinen Mailversand.
                        Das Mailmodul ist durch die Deaktivierung nicht betroffen.
                    </li>
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
                        Text besser zu formatieren.
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
                        Admidio kann RSS-Feeds f&uuml;r verschiedene Module (Ank&uuml;ndigungen,
                        Termine, G&auml;stebuch, Fotogalerien und Weblinks) auf den jeweiligen &Uuml;bersichtsseiten
                        bereitstellen, die dann &uuml;ber den Browser einem Feedreader zugeordnet
                        werden k&ouml;nnen.
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
                        Personen Zugriff auf die Daten bekommen.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"logout_minutes\">Automatischer Logout nach:</label></dt>
                            <dd><input type=\"text\" id=\"logout_minutes\" name=\"logout_minutes\" size=\"4\" maxlength=\"4\" value=\"". $form_values['logout_minutes']. "\" /> Minuten</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Dieser Wert gibt an, nach wieviel Minuten ein inaktiver Benutzer automatisch ausgeloggt wird.
                        Inaktiv ist ein Benutzer solange er keine Seite des Admidio-Systems aufruft. Diese Einstellung
                        wird ignoriert, falls der Benutzer <b>Angemeldet bleiben</b> ausgewählt hat.
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
						Administrator stellen.
                    </li>
                </ul>
            </div>
        </div>";



        /**************************************************************************************/
        // Einstellungen Registrierung
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"register\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/new_registrations_small.png\" alt=\"Einstellungen Registrierung\" title=\"Einstellungen Registrierung\" /> Einstellungen Registrierung&nbsp;&nbsp; </div>
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
                        Registrierung stehen ihm alle Felder des Profils zur Verf&uuml;gung.
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
                        Bei der Registrierung wird f&uuml;r alle Benutzer bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor der Registrierung korrekt eingeben. Dies soll sicherstellen,
                        dass das Formular nicht von Spammern missbraucht werden kann.
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
                        Alle Webmaster erhalten eine E-Mail, sobald sich ein neuer User im System registriert hat.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Ankuendigungsmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"announcement-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/announcements_small.png\" alt=\"Einstellungen Ankündigungsmodul\" title=\"Einstellungen Ankündigungsmodul\" /> Einstellungen Ank&uuml;ndigungsmodul&nbsp;&nbsp; </div>
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
                        Das Ankündigungsmodul kann &uuml;ber diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Downloadmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"download-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/download_small.png\" alt=\"Einstellungen Downloadmodul\" title=\"Einstellungen Downloadmodul\" /> Einstellungen Downloadmodul&nbsp;&nbsp; </div>
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
                        Das Downloadmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"max_file_upload_size\">Maximale Dateigr&ouml;&szlig;e:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"max_file_upload_size\" name=\"max_file_upload_size\" size=\"6\" maxlength=\"10\" value=\"". $form_values['max_file_upload_size']. "\" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Benutzer k&ouml;nnen nur Dateien hochladen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so ist der Upload deaktiviert.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Photomodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"photo-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/photo_small.png\" alt=\"Einstellungen Fotomodul\" title=\"Einstellungen Fotomodul\" /> Einstellungen Fotomodul&nbsp;&nbsp; </div>
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
                        Das Fotomodul kann &uuml;ber diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar.<br /><br />
						<img class=\"iconHelpLink\" src=\"".THEME_PATH."/icons/warning16.png\" alt=\"Warnhinweis\" title=\"Warnhinweis\">&nbsp;
						Achtung&#33;&#33;&#33; bei der Einstellung &bdquo;Nur f&uuml;r registrierte Benutzer&rdquo;, wird nur der Zugriff &uuml;ber
						die Webseite verhindert. Die Bilddatein werden nicht gesch&uuml;tzt. 		
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
                                    echo ">Lightbox</option>
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
                        Hier kann eingestellt werden, wie die Bilder im Fotomodul präsentiert werden sollen. 
                        dies kann über ein Popup-Fenster, über eine Javascript-Animation (Lightbox) oder auf 
                        dergleichen Seite in normalem HTML erfolgen.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_thumbs_row\">Thumbnailzeilen:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_thumbs_row\" name=\"photo_thumbs_row\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_row']. "\" />
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden. (Standardwert: 5)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_thumbs_column\">Thumbnailspalten:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_thumbs_column\" name=\"photo_thumbs_column\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_column']. "\" />
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.
                        Vorsicht: zuviele Thumbnails nebeneinander passen nicht ins Layout. Ggf. die Thumbnailskalierung
                        herunter setzen. (Standardwert: 5)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_thumbs_scale\">Skalierung Thumbnails:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_thumbs_scale\" name=\"photo_thumbs_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_thumbs_scale']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann festgelegt werden auf welchen Wert die l&auml;ngere Bildseite in der Thumbnailanzeige
                        skaliert werden soll. Vorsicht: Werden die Thumbnails zu breit, passen weniger nebeneinander.
                        Ggf. weniger Thumbnailspalten einstellen. (Standardwert: 100)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_save_scale\">Skalierung beim Hochladen und im LBmodus:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_save_scale\" name=\"photo_save_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_save_scale']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Beim hochladen werden alle Bilder neu skaliert. Der hier eingegeben Pixelwert
                        ist der Parameter f&uuml;r die l&auml;ngere Seite des Bildes, egal ob das Bild im Hoch-
                        oder Querformat &uuml;bergeben wurde. Die andere Seite wird im Verh&auml;ltnis berechnet.(Standardwert: 640)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_preview_scale\">Höhe der Vorschaubilder:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_preview_scale\" name=\"photo_preview_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_preview_scale']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier wird die Höhe des jeweiligen Vorschaubildes in der Albenübersicht festgelegt. (Standardwert: 100)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_show_width\">Max. Bildanzeigebreite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_show_width\" name=\"photo_show_width\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_width']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Die hier angegeben Werte bestimmen die maximale Breite die ein Bild im Anzeigefenster
                        haben darf, au&szlig;er Lightboxmodus. Das Fenster im Popupmodus wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                        ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                        sein muss. (Standardwert: 500)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_show_height\">Max. Bildanzeigeh&ouml;he:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"photo_show_height\" name=\"photo_show_height\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_height']. "\" /> Pixel
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                        haben darf, au&szlig;er Lightboxmodus. Das Fenster  im Popupmodus wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                        ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                        sein muss. (Standardwert: 380)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"photo_image_text\">Bildtext einblenden:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"photo_image_text\" name=\"photo_image_text\" ";
                                if(isset($form_values['photo_image_text']) && $form_values['photo_image_text'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Ist diese Funktion aktiviert, wird in jedes angezeigte Bild das &#169;-Symbol und die Homepage
                        eingeblendet. Der Schriftzug wird nicht beim hochladen mit abgespeichert. Die Einblendung
                        erfolgt nur bei Bildern mit einer Skalierung &uuml;ber 200 Pixel der l&auml;ngeren Seite, also in der Regl nicht bei Thumbnails.
						Diese Funktion steht im Lightboxmodus nicht zur Verfügung.
                    </li>
                </ul>
            </div>
        </div>";
        
        /**************************************************************************************/
        //Einstellungen Forum
        /**************************************************************************************/

         echo"
        <div class=\"groupBox\" id=\"forum\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/forum_small.png\" alt=\"Einstellungen Forum\" title=\"Einstellungen Forum\" /> Einstellungen Forum&nbsp;&nbsp; </div>
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
                        Das Forum kann über diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modulübersichtsseite nicht mehr angezeigt.
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
                        Vorhandene Admidiobenutzer werden automatisch beim Anmelden des Benutzers ins Forum exportiert und dort als Forumsbenutzer angelegt. (Standardwert: ja)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_praefix\">Forum Tabellen praefix:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"forum_praefix\" name=\"forum_praefix\" style=\"width: 50px;\" value=\"". $form_values['forum_praefix']. "\" />
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier wird der prefix der Tabellen des phpBB-Forums angegeben. (Bsp.: phpbb)
                    </li>
                    <li>
                        <dl>
                            <dt><label>Zugangsdaten zur Datenbank des Forums:</label></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"forum_sqldata_from_admidio\">Zugangsdaten von Admidio verwenden:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"forum_sqldata_from_admidio\" name=\"forum_sqldata_from_admidio\" onclick=\"javascript:drawForumAccessDataTable('Forum_Zugangsdaten','forum_sqldata_from_admidio');\" ";
                                if(isset($form_values['forum_sqldata_from_admidio']) && $form_values['forum_sqldata_from_admidio'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Falls das Forum über die gleiche Datenbank wie auch Admidio betrieben wird.
                    </li>
					<li id=\"Forum_Zugangsdaten\">
						<script type=\"text/javascript\"><!--
							drawForumAccessDataTable('Forum_Zugangsdaten','forum_sqldata_from_admidio');
						--></script>
					</li>
                    <li class=\"smallFontSize\">
                        Hier müssen die Zugangsdaten des Forums eingegeben werden, falls ein soclhes ausgewählt und aktiviert wurde.
                    </li>                
                </ul>
            </div>
        </div>";

        /**************************************************************************************/
        //Einstellungen Gaestebuchmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"guestbook-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/guestbook_small.png\" alt=\"Einstellungen Gästebuchmodul\" title=\"Einstellungen Gästebuchmodul\" /> Einstellungen G&auml;stebuchmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_guestbook_module\">G&auml;stebuch aktivieren:</label></dt>
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
                        Das Gästebuch kann &uuml;ber diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar.
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
                        F&uuml;r nicht eingeloggte Benutzer wird im G&auml;stebuchformular bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Absenden des Formularinhalts korrekt eingeben.
                        Dies soll sicherstellen, dass das Formular nicht von Spammern missbraucht werden kann.
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
                        Nicht eingeloggte Benutzer k&ouml;nnen, nach Aktivierung dieser Option, Eintr&auml;ge im G&auml;stebuch kommentieren. Die Rechtevergabe
                        f&uuml;r dieses Feature &uuml;ber die Rollenverwaltung wird dann ignoriert.
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
                        Wenn diese Option aktiviert ist, werden beim Aufruf der G&auml;stebuchseite die Kommentare direkt geladen und nicht ausgeblendet.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"flooding_protection_time\">Flooding Protection Intervall:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"flooding_protection_time\" name=\"flooding_protection_time\" size=\"4\" maxlength=\"4\" value=\"". $form_values['flooding_protection_time']. "\" /> Sekunden
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        F&uuml;r nicht eingeloggte Benutzer wird bei Eintr&auml;gen im G&auml;stebuch &uuml;berpr&uuml;ft,
                        ob sie innerhalb des eingestellten Intervalls bereits einen Eintrag get&auml;tigt haben.
                        Damit soll verhindert werden, dass Benutzer in zu kurzen Zeitabst&auml;nden hintereinander
                        ungew&uuml;nschte Eintr&auml;ge erzeugen. Ist das Intervall auf 0 gesetzt wird diese &Uuml;berpr&uuml;fung
                        nicht durchgef&uuml;hrt.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Listenmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"list-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/list_small.png\" alt=\"Einstellungen Listenmodul\" title=\"Einstellungen Listenmodul\" /> Einstellungen Listen&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"lists_roles_per_page\">Anzahl Rollen pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"lists_roles_per_page\" name=\"lists_roles_per_page\" size=\"4\" maxlength=\"4\" value=\"". $form_values['lists_roles_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Rollen die auf einer Seite in der Listen&uuml;bersicht aufgelistet werden. Gibt es mehr Rollen
                        so kann man in der Liste bl&auml;ttern. Bei dem Wert 0 werden alle Rollen aufgelistet und die
                        Bl&auml;ttern-Funktion deaktiviert.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"lists_members_per_page\">Anzahl Teilnehmer pro Seite:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"lists_members_per_page\" name=\"lists_members_per_page\" size=\"4\" maxlength=\"4\" value=\"". $form_values['lists_members_per_page']. "\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Anzahl der Teilnehmer die auf einer Seite in einer Liste aufgelistet werden.
                        Gibt es mehr Teilnehmer zu einer Rolle, so kann man in der Liste bl&auml;ttern.
                        Die Druckvorschau und der Export sind von diesem Wert nicht betroffen.
                        Bei dem Wert 0 werden alle Teilnehmer aufgelistet und die Bl&auml;ttern-Funktion deaktiviert.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Mailmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"mail-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/email_small.png\" alt=\"Einstellungen Mailmodul\" title=\"Einstellungen Mailmodul\" /> Einstellungen Mailmodul&nbsp;&nbsp; </div>
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
                        Das Mailmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt. Falls der Server keinen
                        Mailversand unterst&uuml;tzt, sollte das Modul deaktiviert werden.
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
                        F&uuml;r nicht eingeloggte Benutzer wird im Mailformular bei aktiviertem Captcha ein alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Mailversand korrekt eingeben. Dies soll sicherstellen,
                        dass das Formular nicht von Spammern missbraucht werden kann.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"max_email_attachment_size\">Maximale Dateigr&ouml;&szlig;e f&uuml;r Anh&auml;nge:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"max_email_attachment_size\" name=\"max_email_attachment_size\" size=\"4\" maxlength=\"6\" value=\"". $form_values['max_email_attachment_size']. "\" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Benutzer k&ouml;nnen nur Dateien anh&auml;ngen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so sind keine Anh&auml;nge im Mailmodul m&ouml;glich.
                    </li>
                </ul>
            </div>
        </div>";

        /**************************************************************************************/
        //Einstellungen Grußkartenmodul
        /**************************************************************************************/
        echo"
        <div class=\"groupBox\" id=\"ecard-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/smilies/emoticon_smile.png\" alt=\"Einstellungen Grußkartenmodell\" title=\"Einstellungen Grußkartenmodell\" /> Einstellungen Grußkartenmodul&nbsp;&nbsp; </div>
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
						Dieses Modul steht generell nur eingeloggten Benutzern zur Verfügung.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_view_width\">Skalierung Vorschaubild:</label></dt>
                            <dd>
                                <table summary=\"Skalierung Vorschaubild\" border=\"0\" cellspacing=\"2\">
                                    <tr>
                                        <td>Breite: </td>
                                        <td>&nbsp;</td>
                                        <td style=\"padding-left:20px;\">Höhe: </td>
                                        <td>&nbsp;</td>
                                    </tr>
                                    <tr>
                                       
                                        <td><input type=\"text\" id=\"ecard_view_width\" name=\"ecard_view_width\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_view_width']. "\" /></td>
                                        <td>Pixel</td>
                                        <td style=\"padding-left:20px;\"><input type=\"text\" id=\"ecard_view_height\" name=\"ecard_view_height\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_view_height']. "\" /></td>
                                        <td>Pixel</td>
                                    </tr>
                                </table>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann festgelegt werden auf welchen Wert die Breite und H&ouml;he des Vorschau-Bildes skaliert werden soll.
                        (Standardwert: Breite 250 | H&ouml;he 250)
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"ecard_card_picture_width\">Skalierung Grußkartenbild:</label></dt>
                            <dd>
                                <table summary=\"Skalierung Grußkartenbild\" border=\"0\" cellspacing=\"2\">
                                    <tr>
                                        <td>Breite: </td>
                                        <td>&nbsp;</td>
                                        <td style=\"padding-left:20px;\">Höhe: </td>
                                        <td>&nbsp;</td>
                                    </tr>
                                    <tr>                             
                                        <td>
                                            <input type=\"text\" id=\"ecard_card_picture_width\" name=\"ecard_card_picture_width\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_card_picture_width']. "\" />
                                        </td>
                                        <td>Pixel</td>
                                        <td style=\"padding-left:20px;\">
                                          <input type=\"text\" id=\"ecard_card_picture_height\" name=\"ecard_card_picture_height\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_card_picture_height']. "\" />
                                        </td>
                                        <td>Pixel</td>
                                    </tr>
                                </table>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                       Hier kann festgelegt werden auf welchen Wert die Breite und Höhe des Grußkarten-Bildes skaliert werden soll.
                       (Standardwert: Breite 400 | H&ouml;he 250)
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
                                echo "<input type=\"text\" id=\"ecard_cc_recipients\" name=\"ecard_cc_recipients\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_cc_recipients']. "\" />";
                                }
                            echo "</div>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann diese Einstellung deaktiviert oder falls gewünscht die max. Anzahl der weiteren Empf&auml;ngern festgelegt werden. (Standardwert: 10)
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
                               echo "<input type=\"text\" id=\"ecard_text_length\" name=\"ecard_text_length\" size=\"4\" maxlength=\"4\" value=\"". $form_values['ecard_text_length']. "\" />";
                                }
                            echo "</div>
                             </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann diese Einstellung deaktiviert oder falls gewünscht die max. Zeichenlänge des Mitteilungstextes festgelegt werden. (Standardwert: 500)
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
                        Hier wird das Standard Template festgelegt.
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
                        Hier wird die Standard Schriftart festgelegt.
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
                       Hier wird die Standard Schriftgr&ouml;&szlig;e festgelegt.
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
                        Hier wird die Standard Schriftfarbe festgelegt.
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

        echo"
        <div class=\"groupBox\" id=\"profile-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/profile_small.png\" alt=\"Einstellungen Profilmodul\" title=\"Einstellungen Profilmodul\" /> Einstellungen Profilmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"default_country\">Standard-Land:</label></dt>
                            <dd>";
                                // Laenderliste oeffnen
                                $country_list = fopen("../../system/staaten.txt", "r");
                                echo "
                                <select size=\"1\" id=\"default_country\" name=\"default_country\">
                                    <option value=\"\"";
                                    if(strlen($form_values['default_country']) == 0)
                                    {
                                        echo " selected=\"selected\" ";
                                    }
                                    echo ">- Bitte wählen -</option>";
                                    $land = trim(fgets($country_list));
                                    while (!feof($country_list))
                                    {
                                        echo"<option value=\"$land\"";
                                        if($land == $form_values['default_country'])
                                        {
                                            echo " selected=\"selected\" ";
                                        }
                                        echo">$land</option>";
                                        $land = trim(fgets($country_list));
                                    }
                                    fclose($country_list);
                                echo"</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das ausgewählte Land wird beim Anlegen eines neuen Benutzers automatisch vorgeschlagen und
                        erleichtert die Eingabe.
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
                        welcher den Wohnort des Benutzers anzeigt, sowie eine Routenlink ausgehend vom eigenen Wohnort.
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
                        Dazu werden die entsprechenden Berechtigungen und das Eintrittsdatum aufgelistet.
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
                        Dazu wird das entsprechende Eintritts- und Austrittsdatum angezeigt.
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
                            mit allen entsprechenden Rollen und dem Eintrittsdatum angezeigt.
                        </li>";
                    }
                echo "</ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Terminmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"dates-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/dates_small.png\" alt=\"Einstellungen Terminmodul\" title=\"Einstellungen Terminmodul\" /> Einstellungen Terminmodul</div>
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
                        Das Terminmodul kann &uuml;ber diese Einstellung komplett deaktiviert oder nur für
                        registrierte Nutzer freigegeben werden. Haben nur registrierte Nutzer Zugriff, so
                        wird das Modul für Besucher komplett ausgeblendet. Der RSS-Feed ist allerdings
                        für beide Gruppen dann nicht mehr aufrufbar.
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
                        welcher den Treffpunkt anzeigt, sowie eine Routenlink ausgehend vom eigenen Wohnort.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Weblinksmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"links-module\">
            <div class=\"groupBoxHeadline\"><img style=\"vertical-align: top;\" src=\"". THEME_PATH. "/icons/weblinks_small.png\" alt=\"Einstellungen Weblinkmodul\" title=\"Einstellungen Weblinkmodul\" /> Einstellungen Weblinkmodul&nbsp;&nbsp; </div>
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
                </ul>
            </div>
        </div>
    </div>
</div>

<div class=\"formLayout\" id=\"organization_save_button\">
    <div class=\"formBody\">
        <button name=\"save\" type=\"submit\" value=\"speichern\"><img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />&nbsp;Speichern</button>
    </div>
</div>
</form>

<script type=\"text/javascript\"><!--
    toggleDiv('general');
            document.getElementById('org_longname').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");
?>