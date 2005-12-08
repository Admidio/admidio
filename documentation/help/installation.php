
<?php include("help/help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Datenbank installieren und Basisdaten anlegen:</h2>

   <p><b>Achtung !!!<br />Diese Seite ist nur f&uuml;r eine Erstinstallation von Admidio gedacht. Falls Sie
   Admidio updaten gilt diese Anleitung nicht.</b></p>

   <ul style="padding-left: 15px;">
   <li>Nachdem Sie Admidio von <a href="index.php?download.html">dieser Seite</a> heruntergeladen haben,
   entpacken Sie die Zip-Datei und kopieren den Inhalt (5 Ordner und die Html-Datei) mit Ihrem
   FTP-Programm auf den Webserver Ihrer Homepage.</li>

   <li><p>Rufen Sie nun in Ihrem Browser die Datei <b>admidio.html</b> auf Ihrer Homepage auf.</p>
   <p>Beispiel: <span style="font-family: Courier New, Courier">http://www.ihre-domain.de/admidio.html</span></p></li>

   <li>
   <p>Nun erscheint folgende Seite auf der Sie unter anderem Ihre Zugangsdaten zur MySQL-Datenbank,
   die Basisdaten Ihres Vereins / Gruppierung / Organisation und die Logindaten f&uuml;r den Webmaster
   eingeben k&ouml;nnen:</p>
   <p><img src="help/images/installation.png" alt="Admidio Installationsseite" /></p>
   </li>

   <li>
   <p>Geben Sie im ersten Abschnitt die Zugangsdaten zu Ihrer MySQL-Datenbank ein.
   Die Zugangsdaten sollten Sie von Ihrem Webspace-Anbieter bekommen haben.</p>

   <p><img src="help/images/inst_form_1.png" alt="Zugangsdaten zur Datenbank" /></p>
   </li>

   <li>
   <p>Die Option "Datenbank anlegen" m&uuml;ssen Sie angeklickt lassen. Geben Sie nun noch ein
   K&uuml;rzel und eine l&auml;ngere Bezeichnung f&uuml;r Ihren Verein/Gruppe/Organisation ein.</p>

   <p><img src="help/images/inst_form_2.png" alt="Optionen" /></p>
   </li>

   <li>
   <p>Damit Sie Admidio weiter einrichten k&ouml;nnen, m&uuml;ssen Sie einen Benutzer mit den
   Rechten eines Webmasters anlegen. Geben Sie die dazu notwendigen Daten in das
   Formular ein.</p>

   <p><img src="help/images/inst_form_3.png" alt="Benutzer anlegen" /></p>
   </li>

   <li><p>Nachdem Sie das Formular vollst&auml;ndig ausgef&uuml;llt haben, klicken Sie auf
   <b>1. Config-Datei erzeugen</b>. Speichern Sie diese Datei auf Ihrer Festplatte und
   kopieren Sie diese dann in das adm_config Verzeichnis auf Ihrem Webserver.</p></li>

   <li><p>W&auml;hlen Sie nun <b>2. Datenbank installieren</b> an und die Datenbank wird
   auf Ihrem Webspace eingerichtet. Danach k&ouml;nnen Sie wieder die <i>admidio.html</i> Datei aufrufen und sich
   bei Admidio anmelden.</p></li>

   <li><p>Wenn Sie Admidio auf einem lokalen Webserver auf Ihrem Computer nutzen wollen, m&uuml;ssen Sie
   in der Datei <i>config.php</i> die Variable <b>$g_internet</b> von 1 auf <b>0</b> setzen. Sollten Sie
   diese Datei sp&auml;ter auf den Webserver Ihres Providers kopieren, m&uuml;ssen Sie diesen Wert wieder auf
   1 setzen.</p></li>
   </ul>

   <br />

   <div style="text-align: right;">
      <b>N&auml;chster Artikel:</b>&nbsp;
      <a href="index.php?help/integrieren.php">Admidio in die bestehende Homepage integrieren</a>&nbsp;
      <a href="index.php?help/integrieren.php"><img src="images/forward.png" style="vertical-align: bottom; border: 0px;" alt="Admidio in die bestehende Homepage integrieren" title="Admidio in die bestehende Homepage integrieren" /></a>
   </div>
</td>