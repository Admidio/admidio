<?php
/******************************************************************************
 * Popup-Window mit Informationen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
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
require("../../adm_config/config.php");
require("function.php");
require("date.php");
require("session_check.php");

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>Hinweis</title>
   <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if gte IE 5.5000]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->
</head>

<body>
   <div class=\"groupBox\" align=\"left\" style=\"padding: 10px\">";
      switch ($_GET['err_code'])
      {
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

         case "email":
            echo "Es ist wichtig, dass du eine g&uuml;ltige E-Mail-Adresse angibst.<br />
                  Ohne diese kann die Anmeldung nicht durchgef&uuml;hrt werden.";
            break;

         case "field_locked":
            echo "Felder, die diese Option aktiviert haben, sind <b>nur</b> für Moderatoren
                  sichtbar und können auch nur von diesen gepflegt werden.<br /><br />
                  Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
                  können den Inhalt der Felder weder sehen noch bearbeiten.";
            break;

         case "login":
            echo "Normalerweise wirst du aus Sicherheitsgr&uuml;nden nach 30 Minuten, in denen du
                  nichts auf der Homepage gemacht hast, automatisch abgemeldet.<br /><br />
                  Sollte es allerdings &ouml;fters vorkommen, dass du l&auml;ngere Zeit nicht am Computer
                  bist, du dich aber trotzdem nicht jedesmal neu einloggen willst, so kannst
                  du diesen Zeitraum auf maximal 8 Stunden erh&ouml;hen.";
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
            angezeigt und von diesen bearbeitet werden können.";
            break;

         case "ranking":
            echo "<table class=\"tableBox\">
                     <th class=\"tableHeader\" colspan=\"2\">Login-Ranking</th>
                     <tr><td>00 - 19</td><td>&nbsp;&nbsp;Newbie</td></tr>
                     <tr><td>20 - 39</td><td>&nbsp;&nbsp;Gruppenkind</td></tr>
                     <tr><td>40 - 69</td><td>&nbsp;&nbsp;Hilfsleiter</td></tr>
                     <tr><td>70 - 99</td><td>&nbsp;&nbsp;Leiter</td></tr>
                     <tr><td>100 - 149</td><td>&nbsp;&nbsp;Planschreiber</td></tr>
                     <tr><td>150 - 199</td><td>&nbsp;&nbsp;Obermini</td></tr>
                     <tr><td>ab 200</td><td>&nbsp;&nbsp;Ehrenmitglied</td></tr>
                  </table>
                     ";
            break;

         case "rolle_termine":
            echo "Rollen, die diese Option aktiviert haben, d&uuml;rfen Termine erfassen.";
            break;

         case "rolle_benutzer":
            echo "Rollen, die diese Option aktiviert haben, haben die Berechtigung fremde
                  Benutzerdaten (au&szlig;er Passw&ouml;rter & Rollen) zu bearbeiten.";
            break;

         case "rolle_locked":
            echo "Rollen, die diese Option aktiviert haben, sind <b>nur</b> für Moderatoren
                  sichtbar. Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
                  können keine E-Mails an diese Rolle schreiben, keine Listen dieser Rolle
                  aufrufen und sehen auch nicht im Profil einer Person, dass diese Mitglied
                  dieser Rolle ist.";
            break;

         case "rolle_logout":
            echo "Besucher der Homepage, die nicht eingeloggt sind, k&ouml;nnen den Mitgliedern
                  dieser Rolle E-Mails schreiben.";
            break;

         case "rolle_login":
            echo "Eingeloggte Benutzer k&ouml;nnen den Mitgliedern dieser Rolle E-Mails schreiben.";
            break;

         case "rolle_gruppe":
            echo "Rollen, die diese Option aktiviert haben, haben erweiterte Funktionalit&auml;ten.
                  Zu diesen Rollen k&ouml;nnen weitere Daten der Gruppe (Zeitraum, Uhrzeit, Ort)
                  erfasst und jeder Gruppe Gruppenleiter zugeordnet werden.";
            break;

         case "rolle_moderation":
            echo "Benutzer dieser Rolle bekommen erweiterte Rechte. Sie k&ouml;nnen Rollen erstellen,
                  verwalten und anderen Benutzern Rollen zuordnen. Au&szlig;erdem k&ouml;nnen Sie
                  alle Termine bearbeiten oder l&ouml;schen.";
            break;

         case "rolle_mail":
            echo "Deine E-Mail wird an alle Mitglieder der ausgew&auml;hlten Rolle geschickt, sofern
                  diese ihre E-Mail-Adresse im System hinterlegt haben.<br /><br />
                  Wenn du eingeloggt bist stehen dir weitere Rollen zur Verf&uuml;gung, an die du E-Mails
                  schreiben kannst.";
            break;

         case "termin_global":
            echo "Termine / Nachrichten, die diese Option aktiviert haben, erscheinen auf den Webseiten
                  folgender Gruppierungen:<br /><b>";

                  // alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
                  $sql = "SELECT * FROM adm_gruppierung
                           WHERE ag_shortname = '$g_organization'
                              OR ag_mutter    = '$g_organization' ";
                  $result_bg = mysql_query($sql, $g_adm_con);
                  db_error($result_bg);

                  $organizations = "";
                  $i             = 0;

                  while($row_bg = mysql_fetch_object($result_bg))
                  {
                     if($i > 0) $organizations = $organizations. ", ";
                     $organizations = $organizations. $row_bg->ag_longname;

                     if($row_bg->ag_shortname == $g_organization
                     && strlen($row_bg->ag_mutter) > 0)
                     {
                        // Muttergruppierung noch auflisten
                        $sql = "SELECT ag_longname FROM adm_gruppierung
                                 WHERE ag_shortname = '$row_bg->ag_mutter' ";
                        $result = mysql_query($sql, $g_adm_con);
                        db_error($result);
                        $row = mysql_fetch_array($result);
                        $organizations = $organizations. ", ". $row[0];
                     }

                     $i++;
                  }

                  echo "$organizations</b><br /><br />
                  Moderatoren dieser Gruppierungen k&ouml;nnen den Termin / Nachricht dann bearbeiten
                  bzw. die Option zur&uuml;cksetzen.";
            break;
         
      case "dateiname":
            echo "   Die Datei sollte so benannt sein, dass man vom Namen auf den Inhalt schließen kann.
               Der Dateiname hat Einfluss auf die Anzeigereihenfolge. In einem Ordner in dem z.B. Sitzungsprotokolle
               gespeichert werden, sollten die Dateinamen immer mit dem Datum beginnen (jjjj-mm-tt).";
            break;
      //Photomodulehifen
      
      case "photo_up_help":
         echo " <h3>Was ist zu tun?</h3>
         Auf das „Durchsuchen“ Button klicken und die gewünschte Bilddatei auf der
         Festplatte auswählen. Den Vorgang ggf. bis zu fünfmal wiederholen, 
         bis alle Felder gefüllt sind. Dann auf „Bilder Speichern“ klicken und ein wenig Geduld haben.
         <br>
         <h3>Hinweise:</h3>
         Die Bilder müssen im JPG Format gespeichert sein.
         Die Bilder werden automatisch auf eine Auflösung von 640Pixel der 
         längeren Seite skaliert (andere Seite im Verhältnis), bevor sie gespeichert werden.
         Der Name der Dateien spielt keine Rolle, da sie automatisch mit fortlaufender
         Nummer benannt werden.
         Da auch bei schnellen Internetanbindungen das Hochladen von größeren Dateien einige
         Zeit in Anspruch nehmen kann, empfehlen wir zunächst alle hoch zu ladenden Bilder in einen
         Sammelordner zu kopieren und diese dann mit einer Bildbearbeitungssoftware auf 640Pixel 
         (längere Bildseite) zu skalieren. Die JPG-Qualität sollte beim abspeichern auf 100% 
         (also keine Komprimierung) gestellt werden.
         Natürlich ist auch das direkte Upload möglich.
         ";
         break;
      
      case "veranst_help":
         echo " <h3>Was ist zu tun?</h3>
         Alle offenen Felder ausfüllen. Die Felder Veranstaltung und Beginn sind Pflichtfelder.
         Die Felder Ende und Photografen sind optional. Alle übrigen Felder werden automatisch ausgefüllt.
         Danach auf Speichern klicken.
         ";
         break;
         

         default:
            echo "Es ist ein Fehler aufgetreten.";
            break;
      }

   echo "</div>
      <div style=\"padding-top: 10px;\" align=\"center\">
      <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
      <img src=\"$g_root_path/adm_program/images/error.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">
      &nbsp;Schlie&szlig;en</button>
      </div>
</body>
</html>";
?>