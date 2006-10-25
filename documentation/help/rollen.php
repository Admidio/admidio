
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Was sind Rollen ?</h2>

   <p>Mit Hilfe der Rollen k&ouml;nnen Sie die Struktur Ihrer Organisation, Gruppierung oder des Verein
   in Admidio abbilden. Eine Rolle kann eine Abteilung, wie zum Beispiel <i>Fu&szlig;ball</i> sein,
   aber auch eine Position im Verein, wie zum Beispiel der <i>Vorstand</i>. Auch Eigenschaften von
   Personen k&ouml;nnen als Rollen definiert werden. Sp&auml;ter k&ouml;nnen Sie dann jeder Person, die Sie
   angelegt haben, die verschiedene Rollen zuordnen.</p>

   <p>Sie selber richten die Rollen ein, die Sie f&uuml;r Ihre Struktur brauchen. Wichtig dabei ist, dass
   jeder Benutzer mindestens eine Rolle (z.B. <i>Mitglied</i> oder &auml;hnliche) braucht, damit der Benutzer
   sich bei Admidio anmelden kann !</p>

   <h2>Welche Vorteile bieten Rollen ?</h2>

   <p>Sie k&ouml;nnen &uuml;ber das Listenmodul zu jeder Rolle verschiedene Mitgliederlisten aufrufen oder
   &uuml;ber die <i>Eigene Liste</i> eine individuelle Liste zusammenstellen. Diese Mitgliederlisten
   k&ouml;nnen Sie dann zum Beispiel nach Excel oder Open-Office exportieren.</p>

   <p>Au&szlig;erdem k&ouml;nnen Sie jeder Rolle verschiedene Rechte zuordnen. Dies f&auml;ngt mit der
   Berechtigung an Termine anzulegen oder Benutzerdaten zu bearbeiten und h&ouml;rt mit dem
   Moderationsrecht auf, das Mitgliedern der Rolle erlaubt neue Rollen anzulegen oder Benutzern
   verschiedene Rollen zu zuweisen.</p>

   <h2>Wie richte ich Rollen ein ?</h2>

   <ul style="padding-left: 15px;">
      <li>
         <p>&Ouml;ffnen Sie die Rollenverwaltung aus der &Uuml;bersicht oder direkt &uuml;ber den Pfad:<br />
         <span style="font-family: Courier New, Courier">adm_program/administration/roles/roles.php</span></p>
      </li>
      <li>
         <p>Hier sehen Sie im Moment nur die Rollen <i>Mitglied</i>, <i>Vorstand</i> und <i>Webmaster</i>.
         Der Rolle <i>Mitglied</i> und <i>Webmaster</i> sind Sie automatisch durch die Installation zugeordnet
         geworden sind.</p>

         <a style="target-new: tab;" href="help/images/screenshots/rollen_liste.png"><img
            style="border: 0px;" src="help/images/screenshots/rollen_liste_thumb.png" alt="Liste aller angelegten Rollen und ihrer Berechtigungen"
            title="Liste aller angelegten Rollen und ihrer Berechtigungen" /></a>

         <p>Klicken Sie nun auf <b>Rolle anlegen</b>.</p>
      </li>
      <li>
         <p>Nun erscheint ein Dialog mit dem Sie neue Rollen anlegen und diesen auch Berechtigungen zuordnen
         k&ouml;nnen. Geben Sie zun&auml;chst eine Bezeichnung und Beschreibung ein und w&auml;hlen Sie eine geeignete
         Kategorie f&uuml;r diese Rolle aus.</p>
         <p>(Kategorien k&ouml;nnen Sie im Dialog <i>Organisationseinstellungen</i> anlegen, bearbeiten und l&ouml;schen.)</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/lock.png" alt="Gesperrte Rolle" />&nbsp;
         Rollen, die diese Option aktiviert haben, sind <b>nur</b> f&uuml;r Moderatoren
         sichtbar. Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
         k&ouml;nnen keine E-Mails an diese Rolle schreiben, keine Listen dieser Rolle
         aufrufen und sehen auch nicht im Profil einer Person, dass diese Mitglied
         dieser Rolle ist.</p>

         <p><a style="target-new: tab;" href="help/images/screenshots/rolle_erstellen.png"><img
            style="border: 0px;" src="help/images/screenshots/rolle_erstellen_thumb.png" alt="Rolle anlegen"/></a></p>
      </li>
      <li>
         <p>Nun k&ouml;nnen Sie jeder Rolle und damit auch den zugeordneten Benutzern verschiedene <b>Berechtigungen</b> zuweisen:</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/wand.png" alt="Moderation" />&nbsp;
         Benutzer dieser Rolle bekommen erweiterte Rechte. Sie k&ouml;nnen Rollen erstellen,
         verwalten und anderen Benutzern Rollen zuordnen.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/user.png" alt="Benutzer bearbeiten" />&nbsp;
         Rollen, die diese Option aktiviert haben, haben die Berechtigung
         Benutzerdaten (au&szlig;er Passw&ouml;rter) und Rollenzugeh&ouml;rigkeiten
         anderer Mitglieder zu bearbeiten.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/note.png" alt="Ank&uuml;ndigungen" />&nbsp;
         Benutzer k&ouml;nnen Ank&uuml;ndigungen anlegen (keine Termine) und diese
         sp&auml;ter auch bearbeiten oder l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/date.png" alt="Termine" />&nbsp;
         Benutzer k&ouml;nnen eigene Termine anlegen (keine Ank&uuml;ndigungen) und diese
         sp&auml;ter auch bearbeiten oder l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/photo.png" alt="Foto" />&nbsp;
         Benutzer k&ouml;nnen Fotos hochladen oder l&ouml;schen. Neue Veranstaltungen anlegen
         und editieren. Betrachten kann die Fotos nachher jeder.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/folder.png" alt="Download" />&nbsp;
         Benutzer, die einer Rolle mit dieser Berechtigung zugeordnet sind, d&uuml;rfen Downloads
         hochladen und l&ouml;schen. Zugriff auf die Downloads hat nachher jeder Benutzer.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/mail.png" alt="Mail ausgeloggt" />&nbsp;
         Besucher der Homepage, die nicht eingeloggt sind, k&ouml;nnen E-Mails an diese Rolle
         schreiben, die dann automatisch an alle Mitglieder weitergeleitet wird.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/mail_key.png" alt="Mail eingeloggt" />&nbsp;
         Benutzer, die sich angemeldet haben, k&ouml;nnen E-Mails an diese Rolle schreiben, die
         dann automatisch an alle Mitglieder weitergeleitet wird.</p>
         <p><img style="vertical-align: bottom;" src="help/images/icons/globe.png" alt="Weblinks" />&nbsp;
         Benutzer bekommen die Berechtigung Weblinks zu erfassen, zu bearbeiten und zu l&ouml;schen.</p>
      </li>
      <li>
         <p>Die <b>Einstellungen</b> sind optional und sollten nur bei Bedarf gef&uuml;llt werden. Hier k&ouml;nnen Termine
         f&uuml;r regelm&auml;&szlig;ige Treffen, Preise f&uuml;r Kurse oder die maximale Anzahl an Teilnehmern definiert werden.</p>
      </li>
      <li>
         <p>Klicken Sie nun auf <b>Speichern</b> und die neue Rolle wird im System angelegt.
         Nun k&ouml;nnen Sie im Profil eines Benutzers diesem die Rolle zuordnen und er wird
         damit ein Mitglied derselbigen.</p>
      </li>
   </ul>

   <br />

   <div style="text-align: left;">
      <a href="index.php?help/layout.php"><img src="help/images/icons/back.png" style="vertical-align: bottom; border: 0px;" alt="Admidio dem eigenen Layout anpassen" title="Admidio dem eigenen Layout anpassen" /></a>
   </div>
</td>