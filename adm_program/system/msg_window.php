<?php
/******************************************************************************
 * Popup-Window mit Informationen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * err_code  - Code fuer die Information, die angezeigt werden soll
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

require("common.php");

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>Hinweis</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->
    
    
</head>

<body onLoad=\"windowresize()\">
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/window_resize.js\"></script>
    <div class=\"groupBox\" align=\"left\" style=\"padding: 10px\" id=\"Inhalt\">";
        switch ($_GET['err_code'])
        {
            case "bbcode":
                echo "Die Beschreibung bei einigen Modulen (Ank&uuml;ndigungen, Terminen, G&auml;stebuch und Weblinks)
                      k&ouml;nnen mit verschiedenen Tags (BBCode) formatiert werden. Daf&uuml;r m&uuml;ssen die 
                      hier aufgelisteten Tags um den entsprechenden Textabschnitt gesetzt werden.<br /><br />
                      Beispiele:<br /><br />
                      <table class=\"tableList\" style=\"width: 100%;\" cellpadding=\"5\" cellspacing=\"0\">
                         <tr>
                            <th class=\"tableHeader\" width=\"155\" valign=\"top\">Beispiel</th>
                            <th class=\"tableHeader\" valign=\"top\">BBCode</th>
                         </tr>
                         <tr>
                            <td valign=\"top\">Text <b>fett</b> darstellen</td>
                            <td valign=\"top\">Text <b>[b]</b>fett<b>[/b]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Text <u>unterstreichen</u></td>
                            <td valign=\"top\">Text <b>[u]</b>unterstreichen<b>[/u]</b></td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Text <i>kursiv</i> darstellen</td>
                            <td valign=\"top\">Text <b>[i]</b>kursiv<b>[/i]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Text <span style=\"font-size: 14pt;\">gro&szlig;</span> darstellen</td>
                            <td valign=\"top\">Text <b>[big]</b>gro&szlig;<b>[/big]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Text <span style=\"font-size: 8pt;\">klein</span> darstellen</td>
                            <td valign=\"top\">Text <b>[small]</b>klein<b>[/small]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Einen <a href=\"http://$g_current_organization->homepage\">Link</a> setzen</td>
                            <td valign=\"top\">Einen <b>[url=</b>http://www.beispiel.de<b>]</b>Link<b>[/url]</b> setzen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Eine <a href=\"mailto:". $g_preferences['email_administrator']. "\">Mailadresse</a> angeben</td>
                            <td valign=\"top\">Eine <b>[email=</b>webmaster@demo.de<b>]</b> Mailadresse<b>[/email]</b> angeben</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Ein Bild <img src=\"$g_root_path/adm_program/images/admidio_logo_20.png\"> anzeigen</td>
                            <td valign=\"top\">Eine Bild <b>[img]</b>http://www.beispiel.de/bild.jpg<b>[/img]</b> anzeigen</td>
                         </tr>
                      </table>";
                break;

            case "condition":
                echo "Hier kannst du Bedingungen zu jedem Feld in deiner neuen Liste eingeben.
                      Damit wird die ausgew&auml;hlte Rolle noch einmal nach deinen Bedingungen
                      eingeschr&auml;nkt.<br /><br />
                      Beispiele:<br /><br />
                      <table class=\"tableList\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
                         <tr>
                            <th class=\"tableHeader\" width=\"75\" valign=\"top\">Feld</th>
                            <th class=\"tableHeader\" width=\"110\" valign=\"top\">Bedingung</th>
                            <th class=\"tableHeader\">Erkl&auml;rung</th>
                         </tr>
                         <tr>
                            <td valign=\"top\">Nachname</td>
                            <td width=\"110\" valign=\"top\"><b>Schmitz</b></td>
                            <td>Sucht alle Benutzer mit dem Nachnamen Schmitz</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Nachname</td>
                            <td width=\"110\" valign=\"top\"><b>Mei*</b></td>
                            <td>Sucht alle Benutzer deren Namen mit Mei anf&auml;ngt</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Geburtstag</td>
                            <td width=\"110\" valign=\"top\"><b>&gt; 01.03.1986</b></td>
                            <td>Sucht alle Benutzer, die nach dem 01.03.1986 geboren wurden</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Ort</td>
                            <td width=\"110\" valign=\"top\"><b>K&ouml;ln oder Bonn</b></td>
                            <td>Sucht alle Benutzer, die aus K&ouml;ln oder Bonn kommen</td>
                         </tr>
                         <tr>
                            <td valign=\"top\">Telefon</td>
                            <td width=\"110\" valign=\"top\"><b>*241*&nbsp;&nbsp;*54</b></td>
                            <td>Sucht alle Benutzer, deren Telefonnummer 241 enth&auml;lt und
                               mit 54 endet</td>
                         </tr>
                      </table>";
                break;

            case "enable_rss":
                echo "Admidio kann RSS-Feeds f&uuml;r verschiedene Module (Ank&uuml;ndigungen, 
                      Termine, G&auml;stebuch und Weblinks) auf den jeweiligen &Uuml;bersichtsseiten 
                      bereitstellen, die dann &uuml;ber den Browser einem Feedreader zugeordnet
                      werden k&ouml;nnen.";
                break;

            case "email":
                echo "Es ist wichtig, dass du eine g&uuml;ltige E-Mail-Adresse angibst.<br />
                      Ohne diese kann die Anmeldung nicht durchgef&uuml;hrt werden.";
                break;

            case "field_locked":
                echo "Felder, die diese Option aktiviert haben, sind <b>nur</b> f&uuml;r Moderatoren
                      sichtbar und k&ouml;nnen auch nur von diesen gepflegt werden.<br /><br />
                      Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
                      k&ouml;nnen den Inhalt der Felder weder sehen noch bearbeiten.";
                break;

            case "file_size":
                echo "Hier kannst Du die maximal zul&auml;ssige Gr&ouml;&szlig;e einer Datei f&uuml;r das
                      jeweilige Modul in Kilobyte definieren.<br /><br />
                      Wenn du 0 als Dateigr&ouml;&szlig;e eintr&auml;gst, deaktivierst du die entsprechende
                      Funktion f&uuml;r alle Benutzer.";
                break;

            case "leader":
                echo "Leiter werden in den Mitgliederlisten dieser Rolle gesondert aufgef&uuml;hrt.<br><br>
                      Leiter haben au&szlig;erdem die M&ouml;glichkeit neue Mitglieder aus der Organisation 
                      der Rolle zu zuordnen oder vorhandene Mitglieder zu entfernen.";
                break;

            case "login":
                echo "Normalerweise wirst du aus Sicherheitsgr&uuml;nden nach 30 Minuten, in denen du
                      nichts auf der Homepage gemacht hast, automatisch abgemeldet.<br /><br />
                      Sollte es allerdings &ouml;fters vorkommen, dass du l&auml;ngere Zeit nicht am Computer
                      bist, du dich aber trotzdem nicht jedesmal neu einloggen willst, so kannst
                      du diesen Zeitraum auf maximal 8 Stunden erh&ouml;hen.";
                break;

            case "mail_admin":
                echo "Die E-Mail-Adresse des Administrators wird als Absenderadresse f&uuml;r Systemnachrichten
                      benutzt. (z.B. bei der Registierungsbest&auml;tigung)<br><br>
                      Gib hier eine Adresse ein unter der du selber auch Nachrichten empfangen kannst, falls
                      jemand auf eine Systemnachricht antwortet.";
                break;

            case "send_email_extern":
                echo "E-Mails werden in der Regel &uuml;ber den Webserver verschickt auf dem Admidio eingerichtet
                      ist. Sollte dein Webserver keinen E-Mailversand unterst&uuml;tzen, kannst du diese Option
                      aktivieren. Dadurch wird versucht, das lokale E-Mail-Programm des Benutzers zu starten,
                      sobald dieser auf einen E-Mail-Link klickt.<br /><br />
                      Allerdings funktioniert dann die automatische Benachrichtigung bei Neuanmeldungen nicht
                      mehr.";
                break;

            case "nickname":
                echo "Mit diesem Namen kannst du dich sp&auml;ter auf der Homepage anmelden.<br /><br />
                      Damit du ihn dir leicht merken kannst, solltest du deinen Spitznamen oder Vornamen nehmen.
                      Auch Kombinationen, wie zum Beispiel <i>Andi78</i> oder <i>StefanT</i>, sind m&ouml;glich.";
                break;

            case "password":
                echo "Das Passwort wird verschl&uuml;sselt gespeichert.
                      Es ist sp&auml;ter nicht mehr m&ouml;glich dieses nachzuschauen.
                      Aus diesem Grund solltest du es dir gut merken.";
                break;

            case "profil_felder":
                echo "Du kannst beliebig viele neue Felder definieren, die im Profil der einzelnen Benutzer
                      angezeigt und von diesen bearbeitet werden k&ouml;nnen. Au&szlig;erdem stehen dir diese
                      Felder bei den Eigenen Listen zur Verf&uuml;gung.";
                break;

            case "rolle_benutzer":
                echo "Rollen, die diese Option aktiviert haben, haben die Berechtigung
                      Benutzerdaten (au&szlig;er Passw&ouml;rter) und Rollenzugeh&ouml;rigkeiten
                      anderer Mitglieder zu bearbeiten.<br>
                      Au&szlig;erdem haben sie Zugriff auf die Benutzerverwaltung und k&ouml;nnen 
                      dort neue Benutzer anlegen oder alte Benutzer l&ouml;schen.";
                break;

            case "rolle_locked":
                echo "Rollen, die diese Option aktiviert haben, sind <b>nur</b> f&uuml;r Moderatoren
                      sichtbar. Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
                      k&ouml;nnen keine E-Mails an diese Rolle schreiben, keine Listen dieser Rolle
                      aufrufen und sehen auch nicht im Profil einer Person, dass diese Mitglied
                      dieser Rolle ist.";
                break;

            case "rolle_logout":
                echo "Besucher der Homepage, die nicht eingeloggt sind, k&ouml;nnen E-Mails an diese Rolle
                      schreiben, die dann automatisch an alle Mitglieder weitergeleitet wird.";
                break;

            case "rolle_login":
                echo "Benutzer, die sich angemeldet haben, k&ouml;nnen E-Mails an diese Rolle schreiben, die
                      dann automatisch an alle Mitglieder weitergeleitet wird.";
                break;

            case "rolle_moderation":
                echo "Benutzer dieser Rolle bekommen erweiterte Rechte. Sie haben Zugriff auf die Rollenverwaltung
                      und k&ouml;nnen neue Rollen erstellen, verwalten und anderen Benutzern Rollen zuordnen.";
                break;

            case "rolle_mail":
                echo "Deine E-Mail wird an alle Mitglieder der ausgew&auml;hlten Rolle geschickt, sofern
                      diese ihre E-Mail-Adresse im System hinterlegt haben.<br /><br />
                      Wenn du eingeloggt bist stehen dir weitere Rollen zur Verf&uuml;gung, an die du E-Mails
                      schreiben kannst.";
                break;
                
            case "role_assign":
                echo "W&auml;hle bitte eine Rolle aus, der alle importierten Benutzer automatisch zugeordnet werden.";
                break;

            case "termin_global":
                echo "Termine / Ank&uuml;ndigungen, die diese Option aktiviert haben, erscheinen auf den Webseiten
                      folgender Organisationen:<br /><b>";

                // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
                $organizations = $g_current_organization->longname;
                $arr_ref_orgas = $g_current_organization->getReferenceOrganizations(true, true, true);

                while($orga = current($arr_ref_orgas))
                {
                    $organizations = $organizations. ", $orga";
                    next($arr_ref_orgas);
                }

                echo "$organizations</b><br /><br />
                      Moderatoren dieser Organisationen k&ouml;nnen den Termin / Nachricht dann bearbeiten
                      bzw. die Option zur&uuml;cksetzen.";
                break;
                
            case "user_field_description":
                $sql = "SELECT usf_description FROM ". TBL_USER_FIELDS. "
                         WHERE usf_org_shortname = '$g_organization'
                           AND usf_name          = {0} ";
                $sql = prepareSQL($sql, array($_GET['err_text']));
                $result_field = mysql_query($sql, $g_adm_con);
                db_error($result_field);
                
                $row = mysql_fetch_object($result_field);
                echo $row->usf_description;
                break;

            case "dateiname":
                echo "   Die Datei sollte so benannt sein, dass man vom Namen auf den Inhalt schlie&szlig;en kann.
                   Der Dateiname hat Einfluss auf die Anzeigereihenfolge. In einem Ordner in dem z.B. Sitzungsprotokolle
                   gespeichert werden, sollten die Dateinamen immer mit dem Datum beginnen (jjjj-mm-tt).";
                break;
                
            //Fotomoduleinstellungen
                    
            case "photo_thumbs_row":
                echo "Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.";
                break;
            
            case "photo_thumbs_column":
                echo "Der hier angegebene Wert bestimmt wieviele Zeilen an Thumbnails auf einer Seite angezeigt werden.
                      Vorsicht: zuviele Thumbnails nebeneinander passen nicht ins Layout. Ggf. die Thumbnailskalierung
                        herunter setzen. ";
                break;
                
           case "photo_thumbs_scale":
                echo "Hier kann festgelegt werden auf welchen Wert die l&auml;ngere Bildseite in der Thumbnailanzeige
                      skaliert werden soll. Vorsicht: Werden die Thumbnails zu breit, passen weniger nebeneinander.
                      Ggf. weniger Thumbnailspalten einstellen.";
                break;
           
           case "photo_save_scale":
                echo "Beim hochladen werden alle Bilder neu Skaliert. Der hier eingegeben Pixelwert
                      ist der Parameter f&uuml;r die l&auml;ngere Seite des Bildes, egal ob das Bild im Hoch-
                      oder Querformat &uuml;bergeben wurde. Die ander Seite wird im Verh&auml;tnis berechnet.";
                break;
           case "photo_show_size":
                echo "Die hier angegeben Werte bestimmen die maximale Breite und H&ouml;he die ein Bild im Anzeigefenster
                      haben darf. Das Fenster wird automatisch entsprechend gr&ouml;&szlig;er. Besonders bei der H&ouml;he
                      ist Vorsicht angebracht, da &uuml;ber und unter dem Bild noch genug Platz f&uuml;r Layout und Browser
                      sein muss. ";
                break;
                
           case "photo_image_text":
                echo "Ist diese Funktion aktivier, wird in jedes angezeigte Bild das &#169;-Symbol und die Homepage
                      eingeblendet. Der Schriftzug wird nicht beim hochladen mit abgespeichert. Die Einblendung
                      erfolgt nur bei Bildern mit einer Skalierung &uuml;ber 200 Pixel der l&auml;ngeren Seite, also in der Regl nicht bei Thumbnails.";
                break;
                
           case "photo_preview_scale":
                echo "Hier wird die H&ouml;he des jeweiligen Vorschaubildes in der Veranstaltungs&uuml;bersicht festgelegt.";
                break;
                        
            //Fotomodulhifen

            case "photo_up_help":
                echo " <h3>Was ist zu tun?</h3>
                    Auf den &bdquo;Durchsuchen&ldquo; Button klicken und die gew&uuml;nschte Bilddatei auf der
                    Festplatte ausw&auml;hlen. Den Vorgang ggf. bis zu f&uuml;nfmal wiederholen,
                    bis alle Felder gef&uuml;llt sind. Dann auf &bdquo;Bilder hochladen&ldquo; klicken und ein wenig Geduld haben.
                    <br>
                    <h3>Hinweise:</h3>
                    Die Bilder m&uuml;ssen im JPG Format gespeichert sein.
                    Die Bilder werden automatisch auf eine Aufl&ouml;sung von ".$g_preferences['photo_save_scale']." Pixel der
                    l&auml;ngeren Seite skaliert (andere Seite im Verh&auml;ltnis) bevor sie gespeichert werden.
                    Der Name der Dateien spielt keine Rolle, da sie automatisch mit fortlaufender
                    Nummer benannt werden.<br>
                    Da auch bei schnellen Internetanbindungen das Hochladen von gr&ouml;&szlig;eren Dateien einige
                    Zeit in Anspruch nehmen kann, empfehlen wir zun&auml;chst alle hoch zu ladenden Bilder in einen
                    Sammelordner zu kopieren und diese dann mit einer Bildbearbeitungssoftware auf ".$g_preferences['photo_save_scale']." Pixel
                    (l&auml;ngere Bildseite) zu skalieren. Die JPG-Qualit&auml;t sollte beim Abspeichern auf mindestens 90%
                    (also geringe Komprimierung) gestellt werden.
                    Die maximale Dateigr&ouml;&szlig;e eines hochgeladenen Bildes wird nur durch die Servereinstellungen beschr&auml;nkt.
                    ";
                break;

            case "veranst_help":
                echo " <h3>Was ist zu tun?</h3>
                    Alle offenen Felder ausf&uuml;llen. Die Felder Veranstaltung und Beginn sind Pflichtfelder. Ggf. ausw&auml;hlen
                    welcher Veranstaltung die Neue untergeordnet werden soll, z.B. &bdquo;Tag 3&ldquo; in &bdquo;Turnier 2010&ldquo; (solche Unterteilungen sind empfehlenswert bei vielen Bildern).
                    Die Felder Ende und Fotografen sind optional. Nur Freigegebene Veranstaltungen sind f&uuml;r Homepagebesucher sichtbar. M&ouml;chte man z.B. erst alle Bilder hochladen
                    oder auch nur schon mal alle Daten eintragen, kann man die Freigabe einfach sp&auml;ter setzen.
                    Danach auf Speichern klicken.
                    ";
                break;

            case "folder_not_found":
                echo " <h3>Warnung!!!</h3>
                    Der zugeh&ouml;rige Ordner wurde nicht gefunden. Sollte er bewusst &uuml;ber FTP gel&ouml;scht worden sein
                    oder nicht mehr die M&ouml;glichkeit bestehen ihn wieder herzustellen, bitte
                    den Datensatz mit klick auf das (<img src=\"$g_root_path/adm_program/images/cross.png\" style=\"vertical-align: top;\">)Icon l&ouml;schen.
                    Besuchern der Website ohne Fotoverwaltungsrecht, wird diese Veranstaltung nicht mehr angezeigt.";
                break;

            case "not_approved":
                echo " <h3>Warnung!!!</h3>
                    Die Veranstaltung ist z.Zt. gesperrt und wird Homepagebesuchern deswegen nicht angezeigt. Zum Freigeben bitte
                    den entsprechende Icon (<img src=\"$g_root_path/adm_program/images/key.png\" border=\"0\">)
                    in der Bearbeitungszeile nutzen.";
                break;

            default:
                echo "Es ist ein Fehler aufgetreten.";
                break;
        }

    echo "</div>
    <div style=\"padding-top: 10px;\" align=\"center\">
        <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
            <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">
            &nbsp;Schlie&szlig;en</button>
    </div>
</body>
</html>";
?>