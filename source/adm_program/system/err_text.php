<?php
/******************************************************************************
 * Fehler-Texte
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

function getErrorText($error_code, $error_text)
{
   global $g_root_path;
   global $g_organization;

   switch ($error_code)
   {
      case "anmeldung":
         $error_str = "<p>Deine Daten wurden gespeichert.</p>
         <p>Du kannst dich noch nicht einloggen.<br />
         Sobald deine Anmeldung vom Administrator best&auml;tigt wurde, erh&auml;lst du eine E-Mail.</p>";
         break;

      case "datum":
         $error_str = "Es wurde kein g&uuml;ltiges Datum in das Feld <b>$error_text</b> eingegeben.";
         break;

      case "delete":
         $error_str = "Die Daten wurden gel&ouml;scht !";
         break;

      case "delete_announcement":
         $error_str = "Willst du die Ank&uuml;ndigung<br />
         <b>$error_text</b><br />wirklich l&ouml;schen ?";
         break;

      case "delete_date":
         $error_str = "Willst du den Termin<br />
         <b>$error_text</b><br />wirklich l&ouml;schen ?";
         break;

      case "delete_field":
         $error_str = "<p>Willst du das Feld <b>$error_text</b> wirklich l&ouml;schen ?</p>
         <p>Es werden alle Daten, die Benutzer in diesem Feld gespeichert haben, gel&ouml;scht.</p>";
         break;

      case "delete_user":
         $error_str = "<p>Willst du <b>$error_text</b> wirklich l&ouml;schen ?</p>
         <p>Der Benutzer wird physikalisch in der Datenbank gel&ouml;scht. Falls
         du nur die Mitgliedschaft beenden willst, musst du im Profil
         einfach die entsprechenden Rollen entfernen !</p>";
         break;

      case "delete_new_user":
         $error_str = "<p>Willst du die Web-Registrierung von
         <b>$error_text</b> wirklich l&ouml;schen ?</p>";
         break;

      case "delete_member":
         $error_str = "Wollen Sie die Mitgliedschaft des Benutzers $error_text bei $g_organization beenden ?";
         break;

      case "delete_member_ok":
         $error_str = "Die Mitgliedschaft des Benutzers bei $g_organization
         wurde erfolgreich beendet !";
         break;

      case "email_invalid":
         $error_str = "Die E-Mail-Adresse ist nicht g&uuml;ltig.";
         break;

      case "field_numeric":
         $error_str = "Das Feld <b>$error_text</b> darf nur Zahlen enthalten.<br>
         Korrigier bitte deine Eingabe.";
         break;

      case "feld":
         $error_str = "Das Feld <b>$error_text</b> ist nicht gef&uuml;llt.";
         break;

      case "felder":
         $error_str = "Es sind nicht alle Felder aufgef&uuml;llt worden.";
         break;

      case "invalid":
         $error_str = "Ung&uuml;ltiger Seitenaufruf !";
         break;

      case "invalid_variable":
         $error_str = "Dem aufgerufenen Skript wurde die notwendige Variable ($error_text) nicht richtig übergeben !";
         break;

      case "login_failed":
         $error_str = "Du hast dich innerhalb kurzer Zeit mehrmals mit einem
         falschen Passwort versucht einzuloggen.<br />Aus Sicherheitsgr&uuml;nden
         ist dein Zugang f&uuml;r <b>15 Minuten</b> gesperrt.";
         break;

      case "login_name":
         $error_str = "Der gew&auml;hlte Benutzername existiert schon.<br /><br />
               W&auml;hle bitte einen neuen Namen.";
         break;

      case "login_unknown":
         $error_str = "Der angegebene Benutzername existiert nicht.";
         break;

      case "login":
         $error_str = "Du hast dich erfolgreich angemeldet.";
         break;

      case "loginNew":
         $error_str = "Du warst l&auml;nger als 30 Minuten inaktiv.<p>
            Aus diesem Grund wurdest du vom System abgemeldet.<br />
            Melde dich bitte erneut an.";
         break;

      case "logout":
         $error_str = "Du wurdest erfolgreich abgemeldet.";
         break;

      case "mysql":
         $error_str = "Folgender Fehler trat beim Zugriff auf die Datenbank auf:<br /><br />
               <b>$error_text</b>";
         break;

      case "new_user":
         $error_str = "Bist du sicher, dass der Benutzer noch nicht in der Datenbank existiert ?";
         break;

      case "noaccept":
         $error_str = "Deine Anmeldung wurde noch nicht vom Administrator best&auml;tigt.<br /><br />
               Einloggen ist nicht m&ouml;glich";
         break;

      case "nodata":
         $error_str = "Es sind keine Daten vorhanden !";
         break;

      case "nolist":
         $error_str = "Es sind noch keine Rollen f&uuml;r diese Kategorie erstellt worden.<br /><br />
            Rollen k&ouml;nnen <a href=\"$g_root_path/adm_program/administration/roles/roles.php\">hier</a>
            erstellt und gepflegt werden.";
         break;

      case "norights":
         $error_str = "Du hast keine Rechte diese Aktion auszuf&uuml;hren";
         break;

      case "noforeign":
         $error_str = "Du darfst nur Termine &auml;ndern<br />
               die von dir angelegt wurden";
         break;

      case "noforeigndel":
         $error_str = "Du darfst nur Termine l&ouml;schen<br />
               die von dir angelegt wurden";
         break;

      case "nomembers":
         $error_str = "Es sind keine Anmeldungen vorhanden.";
         break;

      case "norolle":
         $error_str = "Die Daten k&ouml;nnen nicht gespeichert werden.<br />
               Dem Benutzer sind keine Rollen zugeordnet.";
         break;

      case "passwort":
         $error_str = "Das Passwort stimmt nicht mit der Wiederholung &uuml;berein.";
         break;

      case "password_unknown":
         $error_str = "Du hast ein falsches Passwort eingegeben und
               konntest deshalb nicht angemeldet werden.<br /><br />
               &Uuml;berpr&uuml;f bitte dein Passwort und gib dieses dann erneut ein.";
         break;

      case "remove_rolle":
         $error_str = "<p>Willst du die Rolle <b>$error_text</b> wirklich entfernen ?</p>
         <p>Die Rolle und alle Mitgliedschaften werden dadurch ung&uuml;ltig !</p>";
         break;

      case "roleexist":
         $error_str = " Es existiert bereits eine Rolle in Gruppierung mit dieser Funktion.";
         break;

      case "save":
         $error_str = "Deine Daten wurden erfolgreich gespeichert.";
         break;

      case "uhrzeit":
         $error_str = "Es wurde keine g&uuml;ltige Uhrzeit eingegeben.<br /><br />
               Die Uhrzeit muss dem Format mm:ss entsprechen.<br />
               Beispiele: 13:05 ; 04:30 ; 23:55";
         break;

      case "zuordnen":
         $error_str = "Wollen Sie die aktuelle Webanmeldung wirklich<br />
               <b>$error_text</b> zuordnen ?";
         break;

      case "send_login_mail":
         $error_str = "Die Logindaten wurden erfolgreich zugeordnet und der
         		Benutzer wurde dar&uuml;ber benachrichtigt.";
          break;

      case "send_new_login":
         $error_str = "Möchtest du <b>$error_text</b> eine E-Mail mit seinem Benutzernamen
         		und einem neuen Passwort zumailen ?";
          break;

      case "write_access":
       if($g_current_organization->mail_extern == 1)
         $mail_link = "mailto:webmaster@$g_domain";
       else
         $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rolle=Webmaster";
         $error_str = "Der Ordner <b>$error_text</b> konnte nicht angelegt werden. Du musst dich an
         den <a href=\"$mail_link\">Webmaster</a>
         wenden, damit dieser <acronym title=\"Über FTP die Dateiattribute auf 0777 bzw. drwxrwxrwx setzen.\">
         Schreibrechte</acronym> für den Ordner setzen kann.";
         break;

//Fehlermeldungen Fotomodul

      case "photodateiphotoup":
         $error_str = "Du hast keine Bilddatei ausgew&auml;hlt, die hinzugef&uuml;gt
                       werden sollen.<br />";
          break;

      case "photoverwaltunsrecht":
         $error_str = "Nur eingeloggte Benutzer mit Moderationsrechten d&uuml;rfen Fotos verwalten.<br />";
          break;

      case "dateiendungphotoup":
         $error_str = "Die ausgew&auml;hlte Datei ist nicht im JPG-Format gespeichert.<br />";
          break;

      case "startdatum":
         $error_str = "Es muss ein g&uuml;ltiges  Startdatum f&uuml;r die Veranstalltung eingegeben werden.<br />";
          break;

      case "enddatum":
         $error_str = "Das eingegebene Enddatum ist ung&uuml;ltig.<br />";
          break;

      case "veranstaltung":
         $error_str = "Es muss ein Name f&uuml;r die Veranstaltung eingegeben weden.<br />";
          break;

      case "delete_veranst":
         $error_str = "Willst du die Veranstaltung:<br />
         <b>$error_text</b><br />wirklich l&ouml;schen ?<br> Alle enthaltenen  Unterveranstaltungen und Bilder gehen verloren.";
         break;

      case "delete_photo":
         $error_str = "Soll das ausgew&auml;hlte Foto wirklich gel&ouml;scht werden?";
         break;

      case "photo_deleted":
         $error_str = "Das Foto wurde erfolgreich gel&ouml;scht.";
         break;

//Ende Fehlermeldungen Fotomodul

//Fehlermeldungen Downloadmodul

      case "invalid_folder":
         $error_str = "Sie haben einen ung&uuml;ltigen Ordner aufgerufen !";
         break;
         
      case "invalid_folder":
         $error_str = "Sie haben eine ung&uuml;ltigen Datei aufgerufen !";
         break;

      case "invalid_file_name":
         $error_str = "Du kannst die Datei <b>$error_text</b> nicht hochladen,
            da der Dateiname ung&uuml;ltige Zeichen enth&auml;lt.";
         break;

      case "invalid_file_extension":
         $error_str = "Du kannst keine PHP, HTML oder Perl Dateien hochladen.";
         break;

      case "folder_not_exist":
         $error_str = "Der von Ihnen aufgerufene Ordner existiert nicht.";
         break;

      case "delete_file_folder":
         $error_str = "Willst du die Datei / den Ordner <b>$error_text</b> wirklich l&ouml;schen ?";
         break;

      case "delete_file":
         $error_str = "Die Datei <b>$error_text</b> wurde gel&ouml;scht.";
         break;

      case "delete_folder":
         $error_str = "Der Ordner <b>$error_text</b> wurde gel&ouml;scht.";
         break;

      case "upload_file":
         $error_str = "Die Datei <b>$error_text</b> wurde hochgeladen.";
         break;

      case "create_folder":
         $error_str = "Der Ordner <b>$error_text</b> wurde angelegt.";
         break;

      case "folder_exists":
         $error_str = "Der Ordner <b>$error_text</b> existiert bereits.<br />
         Gib bitte einen anderen Namen ein.";
         break;

      case "file_exists":
         $error_str = "Die Datei <b>$error_text</b> existiert bereits.";
         break;

      case "rename_folder":
         $error_str = "Der Ordner <b>$error_text</b> wurde umbenannt.";
         break;

      case "rename_file":
         $error_str = "Die Datei <b>$error_text</b> wurde umbenannt.";
         break;

//Ende Fehlermeldungen Downloadmodul

//Fehlermeldungen Mailmodul

      case "mail_send":
         $error_str = "Deine Mail wurde erfolgreich an <b>$error_text</b> versendet.";
         break;

      case "attachment":
         $error_str = "Dein Dateinanhang konnte nicht hochgeladen werden.<br />
         Vermutlich ist das Attachment zu groß!";
         break;

      case "mail_extern":
         $error_str = "Es können keine E-Mails über diese Homepage verschickt werden.<br /><br />
         Benutze bitte dein E-Mail-Programm zum Versenden der E-Mail.";
         break;

      case "mail_rolle":
         $error_str = "Bitte wähle eine Rolle als Adressat der Mail aus!";
         break;

//Ende Fehlermeldungen Mailmodul

//Fehlermeldungen RSSmodul

      case "rss_disabled":
         $error_str = "Die RSS-Funktion wurde vom Webmaster deaktiviert";
         break;

//Ende Fehlermeldungen RSSmodul


      default:
         $error_str = "Es ist ein Fehler aufgetreten.";
         if(strlen($error_text) > 0)
         	$error_str = $error_str. "<p><b>Fehler: $error_text</b></p>";
         break;


   }
   return $error_str;
}

?>