
<?php include("help/help_menu.php"); ?>

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
         <p>Hier sehen Sie im Moment nur die Rolle <i>Webmaster</i> deren Mitglied Sie automatisch durch die
         Installation geworden sind. Diese Rolle wird bei der Installation angelegt und
         kann nicht mehr gel&ouml;scht werden.</p>

         <img style="border: 0px;" src="help/images/rollen_liste.png" alt="Liste aller angelegten Rollen und ihrer Berechtigungen" title="Liste aller angelegten Rollen und ihrer Berechtigungen" />

         <p>Klicken Sie nun auf <b>Rolle anlegen</b>.</p>
      </li>
      <li>
         <p>Nun erscheint ein Dialog mit dem Sie neue Rollen anlegen und diesen auch Rechte zuordnen k&ouml;nnen. Geben Sie
         zun&auml;chst eine Bezeichnung und eine Beschreibung f&uuml;r die Rolle ein.</p>

         <p><a style="target-new: tab;" href="help/images/rolle_erstellen.png"><img style="border: 0px;" src="help/rolle_erstellen_thumb.png" alt="Rolle anlegen"/></a></p>
      </li>
      <li>
         <p>Nun k&ouml;nnen Sie jeder Rolle und damit auch den zugeordneten Benutzern verschiedene Rechte zuweisen:</p>
         <p><img style="vertical-align: bottom;" src="images/wand.png" alt="Moderation" />&nbsp;
         Benutzer dieser Rolle bekommen erweiterte Rechte. Sie k&ouml;nnen Rollen erstellen,
         verwalten und anderen Benutzern Rollen zuordnen. Au&szlig;erdem k&ouml;nnen Sie
         Ank&uuml;ndigungen und Termine erfassen, bearbeiten und l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="images/person.png" alt="Benutzer bearbeiten" />&nbsp;
         Rollen, die diese Option aktiviert haben, haben die Berechtigung
         Benutzerdaten (au&szlig;er Passw&ouml;rter) und Rollenzugeh&ouml;rigkeiten
         anderer Mitglieder zu bearbeiten.</p>
         <p><img style="vertical-align: bottom;" src="images/history.png" alt="Termine" />&nbsp;
         Benutzer k&ouml;nnen eigene Termine anlegen (keine Ank&uuml;ndigungen) und diese
         sp&auml;ter auch bearbeiten oder l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="images/photo.png" alt="Foto" />&nbsp;
         Benutzer dieser Rolle bekommen erweiterte Rechte. Sie k&ouml;nnen Rollen erstellen,
         verwalten und anderen Benutzern Rollen zuordnen. Au&szlig;erdem k&ouml;nnen Sie
         Ank&uuml;ndigungen und Termine erfassen, bearbeiten und l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="images/download.png" alt="Download" />&nbsp;
         Benutzer dieser Rolle bekommen erweiterte Rechte. Sie k&ouml;nnen Rollen erstellen,
         verwalten und anderen Benutzern Rollen zuordnen. Au&szlig;erdem k&ouml;nnen Sie
         Ank&uuml;ndigungen und Termine erfassen, bearbeiten und l&ouml;schen.</p>
         <p><img style="vertical-align: bottom;" src="images/mail-open.png" alt="Mail ausgeloggt" />&nbsp;
         Besucher der Homepage, die nicht eingeloggt sind, k&ouml;nnen E-Mails an diese Rolle
         schreiben, die dann automatisch an alle Mitglieder weitergeleitet wird.</p>
         <p><img style="vertical-align: bottom;" src="images/mail-open-key.png" alt="Mail eingeloggt" />&nbsp;
         Benutzer, die sich angemeldet haben, k&ouml;nnen E-Mails an diese Rolle schreiben, die
         dann automatisch an alle Mitglieder weitergeleitet wird.</p>
         <p><img style="vertical-align: bottom;" src="images/lock.png" alt="Gesperrte Rolle" />&nbsp;
         Rollen, die diese Option aktiviert haben, sind <b>nur</b> f&uuml;r Moderatoren
         sichtbar. Benutzer, denen keiner moderierenden Rolle zugewiesen wurden,
         k&ouml;nnen keine E-Mails an diese Rolle schreiben, keine Listen dieser Rolle
         aufrufen und sehen auch nicht im Profil einer Person, dass diese Mitglied
         dieser Rolle ist.</p>
         <p><img style="vertical-align: bottom;" src="images/gruppe.png" alt="Gruppe" />&nbsp;
         Rollen, die diese Option aktiviert haben, haben erweiterte Funktionalit&auml;ten.
         Zu diesen Rollen k&ouml;nnen weitere Daten der Gruppe (Zeitraum, Uhrzeit, Ort)
         erfasst und jeder Gruppe Gruppenleiter zugeordnet werden.</p>
      </li>
      <li>
         <p>Klicken Sie nun auf <b>Speichern</b> und die neue Rolle wird im System angelegt.
         Nun k&ouml;nnen Sie im Profil eines Benutzers diesem die Rolle zuordnen und er wird
         damit ein Mitglied derselbigen.</p>
      </li>
   </ul>

   <br />

   <div style="text-align: left;">
      <a href="index.php?help/integrieren.php"><img src="images/back.png" style="vertical-align: bottom; border: 0px;" alt="Admidio in die bestehende Homepage integrieren" title="Admidio in die bestehende Homepage integrieren" /></a>
   </div>
</td>