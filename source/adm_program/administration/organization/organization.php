<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
$_SESSION['navigation']->addUrl($g_current_url);

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
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = "Organisationseinstellungen";
$g_layout['header'] =  "
    <style type=\"text/css\">
        .smallText {
            font-size:  7pt;
            font-weight: normal;
        }
        
        .groupBox {
            visibility: hidden; 
            display:    none; 
            width:      95%;
        }
    </style>

    <script type=\"text/javascript\">
        // Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
        ids = new Array('general', 'register', 'announcement-module', 'download-module', 'photo-module', 
                        'guestbook-module', 'list-module', 'mail-module','profile-module', 'dates-module', 
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
    </script>";

// Html-Kopf ausgeben
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Organisationseinstellungen</h1>

<div class=\"formLayout\">
    <div class=\"formBody\" style=\"text-align: center;\">
        <a href=\"#\" onClick=\"toggleDiv('general');\">Allgemein</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('register');\">Registrierung</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('announcement-module');\">Ank&uuml;ndigungen</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('download-module');\">Downloads</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('photo-module');\">Fotos</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('guestbook-module');\">G&auml;stebuch</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('list-module');\">Listen</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('mail-module');\">Mails</a>
         
         &#124; <a href=\"#\" onClick=\"toggleDiv('profile-module');\">Profil</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('dates-module');\">Termine</a>

         &#124; <a href=\"#\" onClick=\"toggleDiv('links-module');\">Links</a>
    </div>
</div><br />

<form action=\"$g_root_path/adm_program/administration/organization/organization_function.php\" method=\"post\">
<div class=\"formLayout\" id=\"organization_form\">
    <div class=\"formBody\">
        <div class=\"groupBox\" id=\"general\">
            <div class=\"groupBoxHeadline\">Allgemeine Einstellungen</div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"version\">Admidio-Version:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"version\" name=\"version\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"". ADMIDIO_VERSION. "\">&nbsp;
                                <a href=\"http://www.admidio.org/index.php?download.php\" target=\"_blank\">Update suchen</a>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_shortname\">Name (Abk.):</label></dt>
                            <dd><input type=\"text\" id=\"org_shortname\" name=\"org_shortname\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"". $form_values['org_shortname']. "\"></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_longname\">Name (lang):</label></dt>
                            <dd><input type=\"text\" id=\"org_longname\" name=\"org_longname\" style=\"width: 200px;\" maxlength=\"60\" value=\"". $form_values['org_longname']. "\"></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"org_homepage\">Homepage:</label></dt>
                            <dd><input type=\"text\" id=\"org_homepage\" name=\"org_homepage\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['org_homepage']. "\"></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"email_administrator\">E-Mail Adresse des Administrator:</label></dt>
                            <dd><input type=\"text\" id=\"email_administrator\" name=\"email_administrator\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['email_administrator']. "\"></dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier sollte die E-Mail-Adresse eines Administrators stehen. Diese wird als Absenderadresse
                        f&uuml;r Systemnachrichten benutzt. (z.B. bei der Registierungsbest&auml;tigung)
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
                                                echo " selected ";
                                            }
                                            echo ">keine</option>";

                                            while($row = $g_db->fetch_object($result))
                                            {
                                                echo "<option value=\"$row->org_id\"";
                                                    if($form_values['org_org_id_parent'] == $row->org_id)
                                                    {
                                                        echo " selected ";
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
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier k&ouml;nnen die Systemmails von Admidio deaktiviert werden. Systemmails sind Benachrichtigungen,
                        wenn sich zum Beispiel ein neuer User angemeldet hat. Aber auch Registrierungsbest&auml;tigungen
                        werden als Systemmail verschickt. Dieses Feature sollte also am besten nicht deaktiviert werden.
                        Es sei denn der Server unterst&uuml;tzt gar keinen Mailversand.
                        Das Mailmodul ist durch die Deaktivierung nicht betroffen.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_bbcode\">BBCode zulassen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_bbcode\" name=\"enable_bbcode\" ";
                                if(isset($form_values['enable_bbcode']) && $form_values['enable_bbcode'] == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Die Benutzer k&ouml;nnen in Textboxen (z.B. Terminbeschreibungen, G&auml;stebucheintr&auml;gen) BB-Code nutzen um den
                        Text besser zu formatieren.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_rss\">RSS-Feeds aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_rss\" name=\"enable_rss\" ";
                                if(isset($form_values['enable_rss']) && $form_values['enable_rss'] == 1)
                                {
                                    echo " checked ";
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
                            <dt><label for=\"logout_minutes\">Automatischer Logout nach:</label></dt>
                            <dd><input type=\"text\" id=\"logout_minutes\" name=\"logout_minutes\" size=\"4\" maxlength=\"4\" value=\"". $form_values['logout_minutes']. "\"> Minuten</dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Dieser Wert gibt an, nach wieviel Minuten ein inaktiver Benutzer automatisch ausgeloggt wird.
                        Inaktiv ist ein Benutzer solange er keine Seite des Admidio-Systems aufruft.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"user_css\">Eigene Stylesheet-Datei:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"user_css\" name=\"user_css\">
                                    <option value=\"\">keine</option>";
                                    $config_path = SERVER_PATH. "/adm_config";
                                    $dir_handle  = opendir($config_path);

                                    while (false !== ($filename = readdir($dir_handle)))
                                    {
                                        if(strpos($filename, ".css") > 0)
                                        {
                                            echo "<option value=\"$filename\" ";
                                            if($form_values['user_css'] == $filename)
                                            {
                                                echo " selected ";
                                            }
                                            echo ">$filename</option>";
                                        }
                                    }
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Eine Stylesheet-Datei aus dem adm_config-Ordner kann hier ausgew&auml;hlt werden. 
                        Diese &uuml;berschreibt dann die System-Stylesheet-Einstellungen. 
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"system_align\">Admidio Anordnung:</label></dt>
                            <dd>
                                <select size=\"1\" id=\"system_align\" name=\"system_align\">";
                                    $arr_align = array("center" => "Zentriert", "left" => "Linksb&uuml;ndig", "right" => "Rechtsb&uuml;ndig");

                                    foreach($arr_align as $key => $value)
                                    {
                                        echo "<option value=\"$key\" ";
                                        if($form_values['system_align'] == $key)
                                        {
                                            echo " selected ";
                                        }                            
                                        echo ">$value</option>";
                                    }
                                echo "</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Hier kann eingestellt werden, wie die Admidio-Module im Browser angeordnet werden sollen. Dies wirkt sich 
                        nur auf das Modul selber und nicht den Inhalt aus body_top.php und body_bottom.php aus.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        // Einstellungen Registrierung
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"register\">
            <div class=\"groupBoxHeadline\">Einstellungen Registrierung&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Registrierung:</div>
                    <div style=\"text-align: left;\">
                        <select size=\"1\" name=\"registration_mode\">
                            <option value=\"0\" ";
                            if($form_values['registration_mode'] == 0)
                            {
                                echo " selected ";
                            }
                            echo ">Deaktiviert</option>
                            <option value=\"1\" ";
                            if($form_values['registration_mode'] == 1)
                            {
                                echo " selected ";
                            }
                            echo ">Schnelle Registrierung</option>
                            <option value=\"2\" ";
                            if($form_values['registration_mode'] == 2)
                            {
                                echo " selected ";
                            }
                            echo ">Erweiterte Registrierung</option>
                        </select>
                    </div>
                </div>
                <div class=\"smallText\">
                    Hier kann die Art der Registrierung festgelegt bzw. ganz abgeschaltet werden. Mit der schnellen
                    Registrierung kann der Benutzer nur die Pflichtfelder eingeben, bei der erweiterten
                    Registrierung stehen ihm alle Felder des Profils zur Verf&uuml;gung.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Captcha aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_registration_captcha\" name=\"enable_registration_captcha\" ";
                        if(isset($form_values['enable_registration_captcha']) && $form_values['enable_registration_captcha'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Bei der Registrierung wird f&uuml;r alle Benutzer bei aktiviertem Captcha ein alphanumerischer
                    Code eingeblendet. Diesen muss der Benutzer vor der Registrierung korrekt eingeben. Dies soll sicherstellen,
                    dass das Formular nicht von Spammern missbraucht werden kann.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">E-Mail-Benachrichtigung:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_registration_admin_mail\" name=\"enable_registration_admin_mail\" ";
                        if(isset($form_values['enable_registration_admin_mail']) && $form_values['enable_registration_admin_mail'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Alle Webmaster erhalten eine E-Mail, sobald sich ein neuer User im System registriert hat.
                </div>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Ankuendigungsmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"announcement-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Ank&uuml;ndigungsmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_announcements_module\">Ank&uuml;ndigungsmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_announcements_module\" name=\"enable_announcements_module\" ";
                                if(isset($form_values['enable_announcements_module']) && $form_values['enable_announcements_module'] == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Ank&uuml;ndigungsmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Downloadmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"download-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Downloadmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Downloadmodul aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_download_module\" name=\"enable_download_module\" ";
                        if(isset($form_values['enable_download_module']) && $form_values['enable_download_module'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Das Downloadmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                    aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Maximale Dateigr&ouml;&szlig;e:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"max_file_upload_size\" size=\"6\" maxlength=\"10\" value=\"". $form_values['max_file_upload_size']. "\"> KB
                    </div>
                </div>
                <div class=\"smallText\">
                    Benutzer k&ouml;nnen nur Dateien hochladen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                    angegebene Wert ist. Steht hier 0, so ist der Upload deaktiviert.
                </div>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Photomodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"photo-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Fotomodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Fotomodul aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_photo_module\" name=\"enable_photo_module\" ";
                        if(isset($form_values['enable_photo_module']) && $form_values['enable_photo_module'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Das Fotomodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                    aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Thumbnailzeilen:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_thumbs_row\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_row']. "\">
                     </div>
                </div>
                <div class=\"smallText\">
                    Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden. (Standardwert: 5)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Thumbnailspalten:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_thumbs_column\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_column']. "\">
                     </div>
                </div>
                <div class=\"smallText\">
                    Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.
                    Vorsicht: zuviele Thumbnails nebeneinander passen nicht ins Layout. Ggf. die Thumbnailskalierung
                    herunter setzen. (Standardwert: 5)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Skalierung Thumbnails:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_thumbs_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_thumbs_scale']. "\"> Pixel
                     </div>
                </div>
                <div class=\"smallText\">
                    Hier kann festgelegt werden auf welchen Wert die l&auml;ngere Bildseite in der Thumbnailanzeige
                    skaliert werden soll. Vorsicht: Werden die Thumbnails zu breit, passen weniger nebeneinander.
                    Ggf. weniger Thumbnailspalten einstellen. (Standardwert: 100)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Skalierung beim Hochladen:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_save_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_save_scale']. "\"> Pixel
                     </div>
                </div>
                <div class=\"smallText\">
                    Beim hochladen werden alle Bilder neu skaliert. Der hier eingegeben Pixelwert
                    ist der Parameter f&uuml;r die l&auml;ngere Seite des Bildes, egal ob das Bild im Hoch-
                    oder Querformat &uuml;bergeben wurde. Die andere Seite wird im Verh&auml;ltnis berechnet.(Standardwert: 640)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">H&ouml;he der Vorschaubilder:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_preview_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_preview_scale']. "\"> Pixel
                     </div>
                </div>
                <div class=\"smallText\">
                    Hier wird die H&ouml;he des jeweiligen Vorschaubildes in der Veranstaltungs&uuml;bersicht festgelegt. (Standardwert: 100)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Max. Bildanzeigebreite:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_show_width\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_width']. "\"> Pixel
                     </div>
                </div>
                <div class=\"smallText\">
                    Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                    haben darf. Das Fenster wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                    ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                    sein muss. (Standardwert: 500)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Max. Bildanzeigeh&ouml;he:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"photo_show_height\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_height']. "\"> Pixel
                     </div>
                </div>
                <div class=\"smallText\">
                    Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                    haben darf. Das Fenster wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                    ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                    sein muss. (Standardwert: 380)
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Bildtext einblenden:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"photo_image_text\" name=\"photo_image_text\" ";
                        if(isset($form_values['photo_image_text']) && $form_values['photo_image_text'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Ist diese Funktion aktiviert, wird in jedes angezeigte Bild das &#169;-Symbol und die Homepage
                    eingeblendet. Der Schriftzug wird nicht beim hochladen mit abgespeichert. Die Einblendung
                    erfolgt nur bei Bildern mit einer Skalierung &uuml;ber 200 Pixel der l&auml;ngeren Seite, also in der Regl nicht bei Thumbnails.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Fotodarstellung:</div>
                    <div style=\"text-align: left;\">
                        <select size=\"1\" name=\"photo_show_mode\">
                            <option value=\"0\" ";
                            if($form_values['photo_show_mode'] == 0)
                            {
                                echo " selected ";
                            }
                            echo ">Popupfenster</option>
                            <option value=\"1\" ";
                            if($form_values['photo_show_mode'] == 1)
                            {
                                echo " selected ";
                            }
                            echo ">Lightbox</option>
                            <option value=\"2\" ";
                            if($form_values['photo_show_mode'] == 2)
                            {
                                echo " selected ";
                            }
                            echo ">Gleiches Fenster</option>
                        </select>
                    </div>
                </div>
                <div class=\"smallText\">
                    Wie sollen die Bilder in der Gro&szlig;enansicht angezeigt werden?<br/>
                    1) in einem Popupfenster<br/>
                    2) mit Lightbox (der rest der Seite wird ausgegraut)<br/>
                    3) im gleichen Fenster                  
                </div>

            </div>
        </div>";

        /**************************************************************************************/
        //Einstellungen Gaestebuchmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"guestbook-module\">
            <div class=\"groupBoxHeadline\">Einstellungen G&auml;stebuchmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">G&auml;stebuch aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_guestbook_module\" name=\"enable_guestbook_module\" ";
                        if(isset($form_values['enable_guestbook_module']) && $form_values['enable_guestbook_module'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Das G&auml;stebuch kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                    aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Captcha aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_guestbook_captcha\" name=\"enable_guestbook_captcha\" ";
                        if(isset($form_values['enable_guestbook_captcha']) && $form_values['enable_guestbook_captcha'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    F&uuml;r nicht eingeloggte Benutzer wird im G&auml;stebuchformular bei aktiviertem Captcha ein alphanumerischer
                    Code eingeblendet. Diesen muss der Benutzer vor dem Absenden des Formularinhalts korrekt eingeben.
                    Dies soll sicherstellen, dass das Formular nicht von Spammern missbraucht werden kann.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Anonyme Kommentare erlauben:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_gbook_comments4all\" name=\"enable_gbook_comments4all\" ";
                        if(isset($form_values['enable_gbook_comments4all']) && $form_values['enable_gbook_comments4all'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Nicht eingeloggte Benutzer k&ouml;nnen, nach Aktivierung dieser Option, Eintr&auml;ge im G&auml;stebuch kommentieren. Die Rechtevergabe
                    f&uuml;r dieses Feature &uuml;ber die Rollenverwaltung wird dann ignoriert.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Flooding Protection Intervall:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"flooding_protection_time\" size=\"4\" maxlength=\"4\" value=\"". $form_values['flooding_protection_time']. "\"> Sekunden
                    </div>
                </div>
                <div class=\"smallText\">
                    F&uuml;r nicht eingeloggte Benutzer wird bei Eintr&auml;gen im G&auml;stebuch &uuml;berpr&uuml;ft,
                    ob sie innerhalb des eingestellten Intervalls bereits einen Eintrag get&auml;tigt haben.
                    Damit soll verhindert werden, dass Benutzer in zu kurzen Zeitabst&auml;nden hintereinander
                    ungew&uuml;nschte Eintr&auml;ge erzeugen. Ist das Intervall auf 0 gesetzt wird diese &Uuml;berpr&uuml;fung
                    nicht durchgef&uuml;hrt.
                </div>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Listenmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"list-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Listen&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: left; width: 55%; float: left;\">Anzahl Rollen pro Seite:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"lists_roles_per_page\" size=\"4\" maxlength=\"4\" value=\"". $form_values['lists_roles_per_page']. "\">
                    </div>
                </div>
                <div class=\"smallText\">
                    Anzahl der Rollen die auf einer Seite in der Listen&uuml;bersicht aufgelistet werden. Gibt es mehr Rollen
                    so kann man in der Liste bl&auml;ttern. Bei dem Wert 0 werden alle Rollen aufgelistet und die 
                    Bl&auml;ttern-Funktion deaktiviert.
                </div>
                
                <div style=\"margin-top: 6px;\">
                 <div style=\"text-align: left; width: 55%; float: left;\">Anzahl Teilnehmer pro Seite:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"lists_members_per_page\" size=\"4\" maxlength=\"4\" value=\"". $form_values['lists_members_per_page']. "\">
                    </div>
                </div>
                <div class=\"smallText\">
                    Anzahl der Teilnehmer die auf einer Seite in einer Liste aufgelistet werden. 
                    Gibt es mehr Teilnehmer zu einer Rolle, so kann man in der Liste bl&auml;ttern. 
                    Die Druckvorschau und der Export sind von diesem Wert nicht betroffen. 
                    Bei dem Wert 0 werden alle Teilnehmer aufgelistet und die Bl&auml;ttern-Funktion deaktiviert.
                </div>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Mailmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"mail-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Mailmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Mailmodul aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_mail_module\" name=\"enable_mail_module\" ";
                        if(isset($form_values['enable_mail_module']) && $form_values['enable_mail_module'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    Das Mailmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                    aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt. Falls der Server keinen
                    Mailversand unterst&uuml;tzt, sollte das Modul deaktiviert werden.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Captcha aktivieren:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"checkbox\" id=\"enable_mail_captcha\" name=\"enable_mail_captcha\" ";
                        if(isset($form_values['enable_mail_captcha']) && $form_values['enable_mail_captcha'] == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                    </div>
                </div>
                <div class=\"smallText\">
                    F&uuml;r nicht eingeloggte Benutzer wird im Mailformular bei aktiviertem Captcha ein alphanumerischer
                    Code eingeblendet. Diesen muss der Benutzer vor dem Mailversand korrekt eingeben. Dies soll sicherstellen,
                    dass das Formular nicht von Spammern missbraucht werden kann.
                </div>

                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: left; width: 55%; float: left;\">Maximale Dateigr&ouml;&szlig;e f&uuml;r Anh&auml;nge:</div>
                    <div style=\"text-align: left;\">
                        <input type=\"text\" name=\"max_email_attachment_size\" size=\"4\" maxlength=\"6\" value=\"". $form_values['max_email_attachment_size']. "\"> KB
                    </div>
                </div>
                <div class=\"smallText\">
                    Benutzer k&ouml;nnen nur Dateien anh&auml;ngen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                    angegebene Wert ist. Steht hier 0, so sind keine Anh&auml;nge im Mailmodul m&ouml;glich.
                </div>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Profilmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"profile-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Profilmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"default_country\">Standard-Land:</label></dt>
                            <dd>";
                                // Laenderliste oeffnen
                                $landlist = fopen("../../system/staaten.txt", "r");
                                echo "
                                <select size=\"1\" id=\"default_country\" name=\"default_country\">
                                    <option value=\"\"";
                                    if(strlen($form_values['default_country']) == 0)
                                    {
                                        echo " selected ";
                                    }
                                    echo ">- Bitte w&auml;hlen -</option>";
                                    $land = utf8_decode(trim(fgets($landlist)));
                                    while (!feof($landlist))
                                    {
                                        echo"<option value=\"$land\"";
                                        if($land == $form_values['default_country'])
                                        {
                                            echo " selected ";
                                        }
                                        echo">$land</option>";
                                        $land = utf8_decode(trim(fgets($landlist)));
                                    }
                                echo"</select>
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das ausgew&auml;hlte Land wird beim Anlegen eines neuen Benutzers automatisch vorgeschlagen und
                        erleichtert die Eingabe.
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"enable_roles_view\">Rollenmitgliedschaften anzeigen:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_roles_view\" name=\"enable_roles_view\" ";
                                if(isset($form_values['enable_roles_view']) && $form_values['enable_roles_view'] == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Es wird ein Kasten mit allen Rollen dieser Organisation angezeigt, bei denen der Benutzer Mitglied ist. 
                        Dazu werden die entsprechenden Berechtigungen und das Eintrittsdatum aufgelistet.
                    </li>";
                    
                    if($g_current_organization->getValue("org_org_id_parent") > 0 
                    || $g_current_organization->hasChildOrganizations() )
                    {
                        echo "
                        <li>
                            <dl>
                                <dt><label for=\"enable_extern_roles_view\">Rollen anderer Organisationen anzeigen:</label></dt>
                                <dd>
                                    <input type=\"checkbox\" id=\"enable_extern_roles_view\" name=\"enable_extern_roles_view\" ";
                                    if(isset($form_values['enable_extern_roles_view']) && $form_values['enable_extern_roles_view'] == 1)
                                    {
                                        echo " checked ";
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
            <div class=\"groupBoxHeadline\">Einstellungen Terminmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_dates_module\">Terminmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_dates_module\" name=\"enable_dates_module\" ";
                                if(isset($form_values['enable_dates_module']) && $form_values['enable_dates_module'] == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Terminmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                    </li>
                </ul>
            </div>
        </div>";


        /**************************************************************************************/
        //Einstellungen Weblinksmodul
        /**************************************************************************************/

        echo"
        <div class=\"groupBox\" id=\"links-module\">
            <div class=\"groupBoxHeadline\">Einstellungen Weblinksmodul&nbsp;&nbsp; </div>
            <div class=\"groupBoxBody\">
                <ul class=\"formFieldList\">
                    <li>
                        <dl>
                            <dt><label for=\"enable_weblinks_module\">Weblinksmodul aktivieren:</label></dt>
                            <dd>
                                <input type=\"checkbox\" id=\"enable_weblinks_module\" name=\"enable_weblinks_module\" ";
                                if(isset($form_values['enable_weblinks_module']) && $form_values['enable_weblinks_module'] == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" />
                            </dd>
                        </dl>
                    </li>
                    <li class=\"smallFontSize\">
                        Das Weblinksmodul kann &uuml;ber diese Einstellung komplett deaktiviert werden. Es ist dann nicht mehr
                        aufrufbar und wird auch in der Modul&uuml;bersichtsseite nicht mehr angezeigt.
                    </li>
                </ul>
            </div>
        </div>

     <br />
    </div>
</div>

    <br />
<div class=\"formLayout\">
    <div class=\"formBody\" style=\"text-align: center;\">
        <button name=\"save\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
            &nbsp;Speichern</button>
    </div>
</div>
</form>

<script type=\"text/javascript\"><!--
    toggleDiv('general');
            document.getElementById('longname').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>