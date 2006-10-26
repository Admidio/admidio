<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

// nur Webmaster duerfen Organisationen bearbeiten
if(!hasRole("Webmaster"))
{
    $g_message->show("norights");
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['organization_request']))
{
   $form_values = $_SESSION['organization_request'];
   unset($_SESSION['organization_request']);
}
else
{
   $form_values['shortname']                 = $g_current_organization->shortname;
   $form_values['longname']                  = $g_current_organization->longname;
   $form_values['homepage']                  = $g_current_organization->homepage;
   $form_values['email_administrator']       = $g_preferences['email_administrator'];
   $form_values['default_country']           = $g_preferences['default_country'];
   $form_values['parent']                    = $g_current_organization->org_id_parent;
   $form_values['enable_bbcode']             = $g_preferences['enable_bbcode'];
   $form_values['enable_rss']                = $g_preferences['enable_rss'];
   $form_values['registration_mode']         = $g_preferences['registration_mode'];
   $form_values['logout_minutes']            = $g_preferences['logout_minutes'];
   $form_values['send_email_extern']         = $g_preferences['send_email_extern'];
   $form_values['max_email_attachment_size'] = $g_preferences['max_email_attachment_size'];
   $form_values['enable_mail_captcha']       = $g_preferences['enable_mail_captcha'];
   $form_values['max_file_upload_size']      = $g_preferences['max_file_upload_size'];
   $form_values['photo_thumbs_row']          = $g_preferences['photo_thumbs_row'];
   $form_values['photo_thumbs_column']       = $g_preferences['photo_thumbs_column'];
   $form_values['photo_thumbs_scale']        = $g_preferences['photo_thumbs_scale'];
   $form_values['photo_save_scale']          = $g_preferences['photo_save_scale'];
   $form_values['photo_preview_scale']       = $g_preferences['photo_preview_scale'];
   $form_values['photo_show_width']          = $g_preferences['photo_show_width'];
   $form_values['photo_show_height']         = $g_preferences['photo_show_height'];
   $form_values['photo_image_text']          = $g_preferences['photo_image_text'];
   $form_values['enable_guestbook_captcha']  = $g_preferences['enable_guestbook_captcha'];
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - bearbeiten</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <style type=\"text/css\">
        .smallText {
            font-size: 7pt;
            font-weight: normal;
        }
    </style>

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>Organisationseinstellungen</h1>
        <p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields.php\"><img
                 class=\"iconLink\" src=\"$g_root_path/adm_program/images/application_form.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Organisationsspezifische Profilfelder pflegen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields.php\">Profilfelder pflegen</a>
            </span>
        </p>
        <form action=\"organization_function.php?org_id=$g_current_organization->id\" method=\"post\" name=\"orga_settings\">
            <div class=\"formBody\">
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Allgemeine Einstellungen</div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Name (Abk.):</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"shortname\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"". $form_values['shortname']. "\">
                        </div>
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Name (lang):</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" id=\"longname\" name=\"longname\" style=\"width: 200px;\" maxlength=\"60\" value=\"". $form_values['longname']. "\">
                        </div>
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Homepage:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"homepage\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['homepage']. "\">
                        </div>
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">E-Mail Adresse des Administrator:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"email_administrator\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['email_administrator']. "\">
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Hier sollte die E-Mail-Adresse eines Administrators stehen. Diese wird als Absenderadresse
                        f&uuml;r Systemnachrichten benutzt. (z.B. bei der Registierungsbest&auml;tigung)
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Standard-Land:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">";
                            // Laenderliste oeffnen
                            $landlist = fopen("../../system/staaten.txt", "r");
                            echo "
                            <select size=\"1\" name=\"default_country\">
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
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Das ausgew&auml;hlte Land wird beim Anlegen eines neuen Benutzers automatisch vorgeschlagen und
                        erleichtert die Eingabe.
                    </div>";

                    // Pruefung ob dieser Orga bereits andere Orgas untergeordnet sind
                    $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_org_id_parent = $g_current_organization->id";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);

                    //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                    if(mysql_num_rows($result)==0)
                    {
                        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                                 WHERE org_id <> $g_current_organization->id
                                   AND org_org_id_parent is NULL
                                 ORDER BY org_longname ASC, org_shortname ASC ";
                        $result = mysql_query($sql, $g_adm_con);
                        db_error($result);

                        if(mysql_num_rows($result) > 0)
                        {
                            // Auswahlfeld fuer die uebergeordnete Organisation
                            echo "
                            <div style=\"margin-top: 15px;\">
                                <div style=\"text-align: right; width: 48%; float: left;\">&Uuml;bergeordnete Organisation:</div>
                                <div style=\"text-align: left; margin-left: 50%;\">
                                    <select size=\"1\" name=\"parent\">
                                        <option value=\"0\" ";
                                        if(strlen($form_values['parent']) == 0)
                                        {
                                            echo " selected ";
                                        }
                                        echo ">keine</option>";

                                        while($row = mysql_fetch_object($result))
                                        {
                                            echo "<option value=\"$row->org_id\"";
                                                if($form_values['parent'] == $row->org_id)
                                                {
                                                    echo " selected ";
                                                }
                                                echo ">$row->org_shortname</option>";
                                        }
                                    echo "</select>
                                </div>
                            </div>";
                        }
                    }

                    echo "
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">BBCode zulassen:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"checkbox\" id=\"enable_bbcode\" name=\"enable_bbcode\" ";
                            if(isset($form_values['enable_bbcode']) && $form_values['enable_bbcode'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Die Benutzer k&ouml;nnen in Textboxen (z.B. Terminbeschreibungen, G&auml;stebucheintr&auml;gen) BB-Code nutzen um den
                        Text besser zu formatieren.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">RSS-Feeds aktivieren:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"checkbox\" id=\"enable_rss\" name=\"enable_rss\" ";
                            if(isset($form_values['enable_rss']) && $form_values['enable_rss'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Admidio kann RSS-Feeds f&uuml;r verschiedene Module (Ank&uuml;ndigungen,
                        Termine, G&auml;stebuch und Weblinks) auf den jeweiligen &Uuml;bersichtsseiten
                        bereitstellen, die dann &uuml;ber den Browser einem Feedreader zugeordnet
                        werden k&ouml;nnen.
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Registrierung:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
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
                        Registrierung kann der Benutzer nur die Logindaten und seinen Namen eingeben, bei der erweiterten
                        Registrierung stehen ihm alle Felder des Profils zur Verf&uuml;gung.
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Automatischer Logout nach:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"logout_minutes\" size=\"4\" maxlength=\"4\" value=\"". $form_values['logout_minutes']. "\"> Minuten
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Dieser Wert gibt an, nach wieviel Minuten ein inaktiver Benutzer automatisch ausgeloggt wird.
                        Inaktiv ist ein Benutzer solange er keine Seite des Admidio-Systems aufruft.
                    </div>
                </div>";

                /**************************************************************************************/
                //Einstellungen Mailmodul
                /**************************************************************************************/

                echo"
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen Mailmodul&nbsp;&nbsp; </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Externes Mailprogramm:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"checkbox\" id=\"send_email_extern\" name=\"send_email_extern\" ";
                            if(isset($form_values['send_email_extern']) && $form_values['send_email_extern'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        </div>
                    </div>
                    <div class=\"smallText\">
                        E-Mails werden in der Regel &uuml;ber den Webserver verschickt auf dem Admidio eingerichtet
                        ist. Sollte dein Webserver keinen E-Mailversand unterst&uuml;tzen, kannst du diese Option
                        aktivieren. Dadurch wird versucht, das lokale E-Mail-Programm des Benutzers zu starten,
                        sobald dieser auf einen E-Mail-Link klickt.
                        Allerdings funktioniert dann die automatische Benachrichtigung bei Neuanmeldungen nicht mehr.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Captcha aktivieren:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"checkbox\" id=\"enable_mail_captcha\" name=\"enable_mail_captcha\" ";
                            if(isset($form_values['enable_mail_captcha']) && $form_values['enable_mail_captcha'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        </div>
                    </div>
                    <div class=\"smallText\">
                        F&uuml;r nicht eingeloggte Benutzer wird im Mailformular bei aktiviertem Captcha ein Alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Mailversand korrekt eingeben. Dies soll sicherstellen,
                        dass das Formular nicht von Spammern missbraucht werden kann.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Maximale Dateigr&ouml;&szlig;e f&uuml;r Anh&auml;nge:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"max_email_attachment_size\" size=\"4\" maxlength=\"6\" value=\"". $form_values['max_email_attachment_size']. "\"> KB
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Benutzer k&ouml;nnen nur Dateien anh&auml;ngen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so sind keine Anh&auml;nge im Mailmodul m&ouml;glich.
                    </div>
                </div>";

                /**************************************************************************************/
                //Einstellungen Downloadmodul
                /**************************************************************************************/

                echo"
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen Downloadmodul&nbsp;&nbsp; </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Maximale Dateigr&ouml;&szlig;e:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"max_file_upload_size\" size=\"4\" maxlength=\"6\" value=\"". $form_values['max_file_upload_size']. "\"> KB
                        </div>
                    </div>
                    <div class=\"smallText\">
                        Benutzer k&ouml;nnen nur Dateien hochladen, bei denen die Dateigr&ouml;&szlig;e kleiner als der hier
                        angegebene Wert ist. Steht hier 0, so ist der Upload deaktiviert.
                    </div>
                </div>";

                /**************************************************************************************/
                //Einstellungen Photomodul
                /**************************************************************************************/

                echo"
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen Fotomodul&nbsp;&nbsp; </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Thumbnailzeilen:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_thumbs_row\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_row']. "\">
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Thumbnailspalten:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_thumbs_column\" size=\"2\" maxlength=\"2\" value=\"". $form_values['photo_thumbs_column']. "\">
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.
                        Vorsicht: zuviele Thumbnails nebeneinander passen nicht ins Layout. Ggf. die Thumbnailskalierung
                        herunter setzen.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Skalierung Thumbnails:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_thumbs_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_thumbs_scale']. "\"> Pixel
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Hier kann festgelegt werden auf welchen Wert die l&auml;ngere Bildseite in der Thumbnailanzeige
                        skaliert werden soll. Vorsicht: Werden die Thumbnails zu breit, passen weniger nebeneinander.
                        Ggf. weniger Thumbnailspalten einstellen.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Skalierung beim Hochladen:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_save_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_save_scale']. "\"> Pixel
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Beim hochladen werden alle Bilder neu skaliert. Der hier eingegeben Pixelwert
                        ist der Parameter f&uuml;r die l&auml;ngere Seite des Bildes, egal ob das Bild im Hoch-
                        oder Querformat &uuml;bergeben wurde. Die andere Seite wird im Verh&auml;ltnis berechnet.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">H&ouml;he der Vorschaubilder:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_preview_scale\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_preview_scale']. "\"> Pixel
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Hier wird die H&ouml;he des jeweiligen Vorschaubildes in der Veranstaltungs&uuml;bersicht festgelegt.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Max. Bildanzeigebreite:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_show_width\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_width']. "\"> Pixel
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                        haben darf. Das Fenster wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                        ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                        sein muss.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Max. Bildanzeigeh&ouml;he:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"text\" name=\"photo_show_height\" size=\"4\" maxlength=\"4\" value=\"". $form_values['photo_show_height']. "\"> Pixel
                         </div>
                    </div>
                    <div class=\"smallText\">
                        Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                        haben darf. Das Fenster wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                        ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                        sein muss.
                    </div>

                    <div style=\"margin-top: 15px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Bildtext einblenden:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
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

                </div>";

                /**************************************************************************************/
                //Einstellungen Gaestebuchmodul
                /**************************************************************************************/

                echo"
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen G&auml;stebuchmodul&nbsp;&nbsp; </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; width: 55%; float: left;\">Captcha aktivieren:</div>
                        <div style=\"text-align: left; margin-left: 45%;\">
                            <input type=\"checkbox\" id=\"enable_guestbook_captcha\" name=\"enable_guestbook_captcha\" ";
                            if(isset($form_values['enable_guestbook_captcha']) && $form_values['enable_guestbook_captcha'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        </div>
                    </div>
                    <div class=\"smallText\">
                        F&uuml;r nicht eingeloggte Benutzer wird im G&auml;stebuchformular bei aktiviertem Captcha ein Alphanumerischer
                        Code eingeblendet. Diesen muss der Benutzer vor dem Absenden des Formularinhalts korrekt eingeben.
                        Dies soll sicherstellen, dass das Formular nicht von Spammern missbraucht werden kann.
                    </div>
                </div>";

                echo "
                <div style=\"margin-top: 6px;\">
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>

    <script type=\"text/javascript\"><!--
        document.getElementById('longname').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>