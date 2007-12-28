<?php 
/******************************************************************************
 * Popup-Window mit Informationen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * err_code - Code fuer die Information, die angezeigt werden soll
 * err_text - Text, der innerhalb einer Meldung angezeigt werden kann
 *
 *****************************************************************************/

require("common.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_err_code = null;
$req_err_text = null;

// Uebergabevariablen pruefen

if(isset($_GET['err_code']) && strlen($_GET['err_code']) > 0)
{
    $req_err_code = strStripTags($_GET['err_code']);
}
else
{
    $g_message->show("invalid");
}

if(isset($_GET['err_text']))
{
    $req_err_text = strStripTags($_GET['err_text']);
}

// Html-Kopf ausgeben
$g_layout['title']    = "Hinweis";
$g_layout['onload']   = "windowresize()";
$g_layout['includes'] = false;
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<div class=\"groupBox\" id=\"message_window\">
    <div class=\"groupBoxHeadline\">Hinweis</div>
    <div class=\"groupBoxBody\">";
        switch ($req_err_code)
        {
            case "bbcode":
                echo "Die Beschreibung bei einigen Modulen (Ank&uuml;ndigungen, Terminen, G&auml;stebuch und Weblinks)
                      k&ouml;nnen mit verschiedenen Tags (BBCode) formatiert werden. Daf&uuml;r m&uuml;ssen die
                      hier aufgelisteten Tags um den entsprechenden Textabschnitt gesetzt werden.<br /><br />
                      Beispiele:<br /><br />
                      <table class=\"tableList\" style=\"width: 100%;\" cellspacing=\"0\">
                         <tr>
                            <th style=\"width: 155px;\">Beispiel</th>
                            <th>BBCode</th>
                         </tr>
                         <tr>
                            <td>Text <b>fett</b> darstellen</td>
                            <td>Text <b>[b]</b>fett<b>[/b]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td>Text <u>unterstreichen</u></td>
                            <td>Text <b>[u]</b>unterstreichen<b>[/u]</b></td>
                         </tr>
                         <tr>
                            <td>Text <i>kursiv</i> darstellen</td>
                            <td>Text <b>[i]</b>kursiv<b>[/i]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td>Text <span style=\"font-size: 14pt;\">gro&szlig;</span> darstellen</td>
                            <td>Text <b>[big]</b>gro&szlig;<b>[/big]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td>Text <span style=\"font-size: 8pt;\">klein</span> darstellen</td>
                            <td>Text <b>[small]</b>klein<b>[/small]</b> darstellen</td>
                         </tr>
                         <tr>
                            <td style=\"text-align: center;\">Text zentriert darstellen</td>
                            <td><b>[center]</b>Text zentriert darstellen<b>[/center]</b></td>
                         </tr>
                         <tr>
                            <td>Einen <a href=\"http://". $g_current_organization->getValue("org_homepage"). "\">Link</a> setzen</td>
                            <td>Einen <b>[url=</b>http://www.beispiel.de<b>]</b>Link<b>[/url]</b> setzen</td>
                         </tr>
                         <tr>
                            <td>Eine <a href=\"mailto:". $g_preferences['email_administrator']. "\">Mailadresse</a> angeben</td>
                            <td>Eine <b>[email=</b>webmaster@demo.de<b>]</b> Mailadresse<b>[/email]</b> angeben</td>
                         </tr>
                         <tr>
                            <td>Ein Bild <img src=\"". THEME_PATH. "/icons/admidio_logo_20.png\" /> anzeigen</td>
                            <td>Ein Bild <b>[img]</b>http://www.beispiel.de/bild.jpg<b>[/img]</b> anzeigen</td>
                         </tr>
                      </table>";
                break;

            case "condition":
                echo "Hier kannst du Bedingungen zu jedem Feld in deiner neuen Liste eingeben.
                      Damit wird die ausgew&auml;hlte Rolle noch einmal nach deinen Bedingungen
                      eingeschr&auml;nkt.<br /><br />
                      Beispiele:<br /><br />
                      <table class=\"tableList\" style=\"width: 100%;\" cellspacing=\"0\">
                         <tr>
                            <th style=\"width: 75px;\">Feld</th>
                            <th style=\"width: 110px;\">Bedingung</th>
                            <th>Erkl&auml;rung</th>
                         </tr>
                         <tr>
                            <td>Nachname</td>
                            <td><b>Schmitz</b></td>
                            <td>Sucht alle Benutzer mit dem Nachnamen Schmitz</td>
                         </tr>
                         <tr>
                            <td>Nachname</td>
                            <td><b>Mei*</b></td>
                            <td>Sucht alle Benutzer deren Namen mit Mei anf&auml;ngt</td>
                         </tr>
                         <tr>
                            <td>Geburtstag</td>
                            <td><b>&gt; 01.03.1986</b></td>
                            <td>Sucht alle Benutzer, die nach dem 01.03.1986 geboren wurden</td>
                         </tr>
                         <tr>
                            <td>Ort</td>
                            <td><b>K&ouml;ln oder Bonn</b></td>
                            <td>Sucht alle Benutzer, die aus K&ouml;ln oder Bonn kommen</td>
                         </tr>
                         <tr>
                            <td>Telefon</td>
                            <td><b>*241*&nbsp;&nbsp;*54</b></td>
                            <td>Sucht alle Benutzer, deren Telefonnummer 241 enth&auml;lt und
                               mit 54 endet</td>
                         </tr>
                         <tr>
                            <td>Ja/Nein Feld</td>
                            <td><b>Ja</b></td>
                            <td>Sucht alle Benutzer bei denen ein H&auml;ckchen gesetzt wurde</td>
                         </tr>
                      </table>";
                break;

            case "enable_rss":
                echo "Admidio kann RSS-Feeds f&uuml;r verschiedene Module (Ank&uuml;ndigungen,
                      Termine, G&auml;stebuch und Weblinks) auf den jeweiligen &Uuml;bersichtsseiten
                      bereitstellen, die dann &uuml;ber den Browser einem Feedreader zugeordnet
                      werden k&ouml;nnen.";
                break;

            case "field":
                echo "Es k&ouml;nnen beliebig viele zus&auml;tzliche Felder definiert werden. 
                      Diese werden im Profil der einzelnen Benutzer angezeigt und k&ouml;nnen dort auch
                      bearbeitet werden. Au&szlig;erdem stehen diese Felder bei den Eigenen Listen zur 
                      Verf&uuml;gung.";
                break;

            case "field_hidden":
                echo "Ein Feld ist normalerweise f&uuml;r alle Benutzer sichtbar. Wird diese Funktion
                      nicht ausgew&auml;hlt, so k&ouml;nnen die Daten nur von Benutzern gesehen werden,
                      die das Recht haben alle Benutzer zu editieren. Im eigenen Profil kann der Benutzer 
                      diese Daten auch sehen.";
                break;

            case "field_disabled":
                echo "Wird ein Feld gesperrt, so k&ouml;nnen Benutzer im eigenen Profil dieses Feld nicht
                      bearbeiten. Es kann nur noch von Benutzern bearbeitet werden, die das Rollenrecht
                      besitzen alle Benutzer zu editieren.";
                break;

            case "field_mandatory":
                echo "Felder, die als Pflichtfelder markiert sind, m&uuml;ssen immer gef&uuml;llt werden.
                      Dies gilt f&uuml;r die Registrierung, aber auch bei der gew&ouml;hnlichen 
                      Profildatenbearbeitung.";
                break;

            case "file_size":
                echo "Hier kannst Du die maximal zul&auml;ssige Gr&ouml;&szlig;e einer Datei f&uuml;r das
                      jeweilige Modul in Kilobyte definieren.<br /><br />
                      Wenn du 0 als Dateigr&ouml;&szlig;e eintr&auml;gst, deaktivierst du die entsprechende
                      Funktion f&uuml;r alle Benutzer.";
                break;

            case "leader":
                echo "Leiter werden in den Mitgliederlisten dieser Rolle gesondert aufgef&uuml;hrt.<br /><br />
                      Leiter haben au&szlig;erdem die M&ouml;glichkeit neue Mitglieder aus der Organisation
                      der Rolle zu zuordnen oder vorhandene Mitglieder zu entfernen.";
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

            case "rolle_benutzer":
                echo "Rollen, die diese Option aktiviert haben, haben die Berechtigung
                      Benutzerdaten (au&szlig;er Passw&ouml;rter) und Rollenzugeh&ouml;rigkeiten
                      anderer Mitglieder zu bearbeiten.<br />
                      Au&szlig;erdem haben sie Zugriff auf die Benutzerverwaltung und k&ouml;nnen
                      dort neue Benutzer anlegen oder alte Benutzer l&ouml;schen.";
                break;

            case "rolle_locked":
                echo "Rollen, die diese Option aktiviert haben, sind <b>nur</b> f&uuml;r Benutzer
                      sichtbar, die selber das Recht haben, Rollen zu editieren.<br />
                      Benutzer, die dieses Recht nicht besitzen, k&ouml;nnen keine E-Mails an 
                      diese Rolle schreiben, keine Listen dieser Rolle aufrufen und sehen auch 
                      nicht im Profil einer Person, dass diese Mitglied dieser Rolle ist.";
                break;

            case "rolle_logout":
                echo "Besucher der Homepage, die nicht eingeloggt sind, k&ouml;nnen E-Mails an diese Rolle
                      schreiben, die dann automatisch an alle Mitglieder weitergeleitet wird.";
                break;

            case "rolle_login":
                echo "Benutzer, die sich angemeldet haben, k&ouml;nnen E-Mails an diese Rolle schreiben, die
                      dann automatisch an alle Mitglieder weitergeleitet wird.";
                break;

            case "rolle_zuordnen":
                echo "Benutzer dieser Rolle haben Zugriff auf die Rollenverwaltung und k&ouml;nnen neue 
                      Rollen erstellen, verwalten und anderen Benutzern Rollen zuordnen.";
                break;

            case "rolle_mail":
                echo "Deine E-Mail wird an alle Mitglieder der ausgew&auml;hlten Rolle geschickt, sofern
                      diese ihre E-Mail-Adresse im System hinterlegt haben.<br /><br />
                      Wenn du eingeloggt bist stehen dir weitere Rollen zur Verf&uuml;gung, an die du E-Mails
                      schreiben kannst.";
                break;
                
            case "rolle_ecard":
                echo "Deine Gru&szlig;karte wird an ein Mitglied der ausgew&auml;hlten Rolle geschickt, sofern
                      diese ihre E-Mail-Adresse im System hinterlegt hat.<br /><br />
                      Unter der Rollenauswahl besteht die M&ouml;glichkeit ein Mitglied dieser Rolle oder die gesammte Rolle auszuw&auml;hlen.";
                break;

            case "role_assign":
                echo "W&auml;hle bitte eine Rolle aus, der alle importierten Benutzer automatisch zugeordnet werden.";
                break;

            case "termin_global":
                echo "Termine / Ank&uuml;ndigungen, die diese Option aktiviert haben, erscheinen auf den Webseiten
                      folgender Organisationen:<p><b>";

                // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
                $organizations = $g_current_organization->getValue("org_longname");
                $arr_ref_orgas = $g_current_organization->getReferenceOrganizations(true, true, true);

                while($orga = current($arr_ref_orgas))
                {
                    $organizations = $organizations. ",<br />- $orga";
                    next($arr_ref_orgas);
                }

                echo "- $organizations</b></p>
                      Moderatoren dieser Organisationen k&ouml;nnen den Termin / Ank&uuml;ndigung dann bearbeiten
                      bzw. die Option zur&uuml;cksetzen.";
                break;

            case "user_field_description":
                echo $g_current_user->getProperty($req_err_text, "usf_description");
                break;

            case "dateiname":
                echo "   Die Datei sollte so benannt sein, dass man vom Namen auf den Inhalt schlie&szlig;en kann.
                   Der Dateiname hat Einfluss auf die Anzeigereihenfolge. In einem Ordner in dem z.B. Sitzungsprotokolle
                   gespeichert werden, sollten die Dateinamen immer mit dem Datum beginnen (jjjj-mm-tt).";
                break;

            //Fotomodulhifen

            case "photo_up_help":
                echo " <h3>Was ist zu tun?</h3>
                    Auf den &bdquo;Durchsuchen&ldquo; Button klicken und die gew&uuml;nschte Bilddatei auf der
                    Festplatte ausw&auml;hlen. Den Vorgang ggf. bis zu f&uuml;nfmal wiederholen,
                    bis alle Felder gef&uuml;llt sind. Dann auf &bdquo;Bilder hochladen&ldquo; klicken und ein wenig Geduld haben.
                    <br />
                    <h3>Hinweise:</h3>
                    Die Bilder m&uuml;ssen im JPG Format gespeichert sein.
                    Die Bilder werden automatisch auf eine Aufl&ouml;sung von ".$g_preferences['photo_save_scale']." Pixel der
                    l&auml;ngeren Seite skaliert (andere Seite im Verh&auml;ltnis) bevor sie gespeichert werden.
                    Der Name der Dateien spielt keine Rolle, da sie automatisch mit fortlaufender
                    Nummer benannt werden.<br />
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
                    den Datensatz mit klick auf das (<img src=\"". THEME_PATH. "/icons/cross.png\" style=\"vertical-align: top;\" />)Icon l&ouml;schen.
                    Besuchern der Website ohne Fotoverwaltungsrecht, wird diese Veranstaltung nicht mehr angezeigt.";
                break;

            case "not_approved":
                echo " <h3>Warnung!!!</h3>
                    Die Veranstaltung ist z.Zt. gesperrt und wird Homepagebesuchern deswegen nicht angezeigt. Zum Freigeben bitte
                    den entsprechende Icon (<img src=\"". THEME_PATH. "/icons/key.png\" />)
                    in der Bearbeitungszeile nutzen.";
                break;

            //Captcha-Hilfen
            case "captcha_help":
                echo " <h3>Was ist das f&uuml;r ein Best&auml;tigungscode?</h3>
                    Hierbei handelt es sich um ein Captcha. Ein Captcha dient zur Spamerkennung. Mit Hilfe des Bildes wird versucht festzustellen, ob das
                    Formular von einem User oder einem Script/Spambot ausgef&uuml;llt wurde. <br /> Bitte trage den im Bild angezeigten
                    4- bis 6-stelligen Code in das Formularfeld ein.";
                break;

            default:
                echo "Es ist ein Fehler aufgetreten.";
                break;
        }

    echo "</div>
</div>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"javascript:window.close();\"><img
            src=\"". THEME_PATH. "/icons/door_in.png\" alt=\"Schließen\" /></a>
            <a href=\"javascript:window.close();\">Schließen</a>
        </span>
    </li>
</ul>";
    
require(THEME_SERVER_PATH. "/overall_footer.php");

?>