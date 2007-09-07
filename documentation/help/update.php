
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
    <h2>Updateanleitung:</h2>

    <p class="notice"><b>Hinweis:</b><br />
    Diese Updateanleitung betrifft nur das Update einer Hauptversion. Falls Sie ein Bugfix-Update einspielen
    (diese &auml;ndern nur die 3. Stelle der Versionsnummer z.B. 1.5.0 nach 1.5.1)
    brauchen Sie nur das Verzeichnis <b>adm_program</b> auf Ihrem Webserver zu ersetzen und haben damit das Update erfolgreich
    durchgef&uuml;hrt.<br /><br />
    <strong>Legen Sie vor dem Update ein Backup Ihrer Datenbank und aller Programmdateien an !</strong></p>
   
   <ol style="padding-left: 20px;">
   <li><p>Laden Sie Admidio von unserer <a href="index.php?download.php">Downloadseite</a> herunter und
   entpacken Sie die Zip-Datei lokal auf Ihrem Rechner.</li>
   
   <li>L&ouml;schen Sie auf Ihrem Webspace die Ordner
   <b>adm_install</b> (wenn nicht schon geschehen) und <b>adm_program</b>.</p>
   <p class="notice"><b>Hinweis:</b><br />
   Die Ordner <i>adm_config</i> und <i>adm_my_files</i> d&uuml;rfen Sie auf keinen Fall l&ouml;schen, da hier Ihre Einstellungen,
   Fotos und Downloads gespeichert werden!</p></li>

   <li><p>Kopieren Sie <b>nur</b> die Verzeichnisse <b>adm_install</b> und <b>adm_program</b> mit Ihrem
   FTP-Programm auf den Webserver Ihrer Homepage.</p></li>

   <li><p>Rufen Sie nun in Ihrem Browser die Datei <b>index.php</b> aus dem Verzeichnis <b>adm_install</b>
   auf Ihrer Homepage auf.</p>
   <p>Beispiel: <span style="font-family: Courier New, Courier">http://www.ihre-domain.de/adm_install/index.php</span></p></li>

   <li>
   <p>Nun erscheint folgende Seite auf der Sie ausw&auml;hlen k&ouml;nnen, ob Sie Admidio installieren, updaten oder eine
   neue Gruppierung hinzuf&uuml;gen wollen. Klicken Sie auf <b>Admidio Datenbank updaten</b>, falls diese
   Option noch nicht ausgew&auml;hlt wurde und dann auf den Button <b>Weiter</b>.</p>
   <p><img src="help/images/screenshots/installation.png" alt="Admidio Installationsseite" /></p>
   </li>

   <li><p>Auf der n&auml;chsten Maske m&uuml;ssen Sie Ihre Zugangsdaten zur Datenbank und die momentan installierte Admidio-Version
   angeben. Nachdem Sie auf <b>Weiter</b> geklickt haben, warten Sie solange bis die Meldung erscheint, dass die
   Datenbank erfolgreich installiert wurde. </p>
   <p>Falls Sie wiederholt auf Weiter klicken oder die Seite schlie&szlig;en, kann das
   Datenbank update fehlschlagen und das Update eventuell auch nicht mehr erfolgreich wiederholt werden.</p></li>

   <li>Nachdem das Update erfolgreich durchgelaufen ist, sollten Sie das Verzeichnis <b>adm_install</b> auf
   Ihrem Webserver l&ouml;schen.</li>
   </ol>

   <p><strong>Nun haben Sie Admidio erfolgreich aktualisiert und k&ouml;nnen mit der neuen Version arbeiten !</strong></p>

</td>