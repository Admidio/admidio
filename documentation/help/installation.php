
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Datenbank installieren und Basisdaten anlegen:</h2>

   <p><b>Achtung !!!<br />Diese Seite ist nur f&uuml;r eine Erstinstallation von Admidio gedacht. Falls Sie
   Admidio updaten lesen Sie bitte <a href="index.php?help/update.php">hier</a> weiter.</b></p>

   <ul style="padding-left: 15px;">
   <li>Nachdem Sie Admidio von <a href="index.php?download.html">dieser Seite</a> heruntergeladen haben,
   entpacken Sie die Zip-Datei und kopieren den Inhalt (4 Ordner und die Html-Datei) mit Ihrem
   FTP-Programm auf den Webserver Ihrer Homepage.</li>

   <li><p>Rufen Sie nun in Ihrem Browser die Datei <b>admidio.html</b> auf Ihrer Homepage auf.</p>
   <p>Beispiel: <span style="font-family: Courier New, Courier">http://www.ihre-domain.de/admidio.html</span></p></li>

   <li>
   <p>Nun erscheint folgende Seite auf der Sie ausw&auml;hlen k&ouml;nnen, ob Sie Admidio installieren, updaten oder eine
   neue Gruppierung hinzuf&uuml;gen wollen. Klicken Sie auf <b>Admidio installieren und einrichten</b>, falls diese
   Option noch nicht ausgew&auml;hlt wurde und dann auf den Button <b>Weiter</b>.</p>
   <p><img src="help/images/installation.png" alt="Admidio Installationsseite" /></p>
   </li>

   <li>
   <p>In dem darauffolgenden Formular geben Sie bitte im ersten Abschnitt die Zugangsdaten zu Ihrer
   MySQL-Datenbank ein. Diese sollten Sie von Ihrem Webspace-Anbieter zugeschickt bekommen haben.</p>

   <p><img src="help/images/inst_form_database.png" alt="Zugangsdaten zur Datenbank" /></p>
   </li>

   <li>
   <p>Sie k&ouml;nnen den Tabellennamen von Admidio ein beliebiges Pr&auml;fix verpassen. Vorgegeben wird
   <b>adm</b>. Somit w&uuml;rden die Tabellen nach dem Schema <i>adm_tabellenname</i> benannt.</p>

   <p><img src="help/images/inst_form_options.png" alt="Optionen" /></p>
   </li>

   <li>
   <p>Nun m&uuml;ssen Sie noch ein K&uuml;rzel und eine l&auml;ngere Bezeichnung f&uuml;r Ihren Verein,
   Gruppe oder Organisation eingeben. Das K&uuml;rzel k&ouml;nnen Sie sp&auml;ter nicht mehr &auml;ndern!</p>

   <p><img src="help/images/inst_form_organization.png" alt="Gruppierung" /></p>
   </li>

   <li>
   <p>Damit Sie sich bei Admidio nach der Installation direkt einloggen k&ouml;nnen, wird w&auml;hrend der
   Installation ein Benutzer mit den Rechten eines Webmasters angelegt. Geben Sie die dazu notwendigen
   Daten in das Formular ein.</p>

   <p><img src="help/images/inst_form_webmaster.png" alt="Benutzer anlegen" /></p>
   </li>

   <li><p>Nachdem Sie das Formular vollst&auml;ndig ausgef&uuml;llt haben, klicken Sie auf
   <b>Weiter</b>.</p></li>

   <li><p>Auf der n&auml;chsten Seite k&ouml;nnen Sie die Konfigurationsdatei <b>config.php</b> herunterladen.
   Kopieren Sie die Datei in das Verzeichnis <b>adm_config</b> auf Ihrem Webspace. Danach k&ouml;nnen
   Sie auf <b>Weiter</b> klicken.</p></li>

   <p><img src="help/images/inst_form_config_file.png" alt="Konfigurationsdatei" /></p>
   </li>

   <li><p>Sie haben nun Admidio erfolgreich auf Ihrem Webspace installiert.<br>L&ouml;schen Sie bitte nach
   der Installation das Verzeichnis <b>adm_install</b> auf Ihrem Webspace um einem Mi&szlig;brauch dieser
   Scripte vorzubeugen!</p></li>

   <li><p>Um das Foto- oder das Downloadmodul nutzen zu können, müssen Sie den Ordnern <b>download</b>
   und <b>photos</b> im Ordner <i>adm_my_files</i> über Ihr FTP-Programm die Dateirechte 777 geben, 
   damit die Scripte dort Dateien ablegen können.</li>
   </ul>

   <br />

   <div style="text-align: right;">
      <b>N&auml;chster Artikel:</b>&nbsp;
      <a href="index.php?help/module_einbauen.php">Aufruf der Module einbauen</a>&nbsp;
      <a href="index.php?help/module_einbauen.php"><img src="help/images/forward.png" style="vertical-align: bottom; border: 0px;" alt="Aufruf der Module einbauen" title="Aufruf der Module einbauen" /></a>
   </div>
</td>