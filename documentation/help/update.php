
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Admidio auf eine neue Version updaten:</h2>

   <ul style="padding-left: 15px;">
   <li><p>Nachdem Sie Admidio von <a href="index.php?download.html">dieser Seite</a> heruntergeladen haben,
   entpacken Sie die Zip-Datei lokal auf Ihrem Rechner. L&ouml;schen Sie auf Ihrem Webspace die Ordner
   <b>adm_install</b> (wenn nicht schon geschehen) und <b>adm_program</b>.</p>
   <p>( Die Ordner <i>adm_config</i> und <i>adm_my_files</i> d&uuml;rfen Sie auf keinen Fall l&ouml;schen, da hier Ihre Einstellungen,
   Fotos und Downloads gespeichert werden! )</p></li>

   <li><p>Kopieren Sie nun <b>nur</b> die Verzeichnisse <b>adm_install</b> und <b>adm_program</b> mit Ihrem
   FTP-Programm auf den Webserver Ihrer Homepage.</p></li>

   <li>Bevor Sie nun die Datenbank updaten, sollten Sie auf jeden Fall ein Backup erstellen, damit Sie im Fehlerfall
   noch einmal eine Datenbank einspielen und das Update erneut durchf&uuml;hren k&ouml;nnen !</li>

   <li><p>Rufen Sie nun in Ihrem Browser die Datei <b>index.php</b> aus dem Verzeichnis <b>adm_install</b>
   auf Ihrer Homepage auf.</p>
   <p>Beispiel: <span style="font-family: Courier New, Courier">http://www.ihre-domain.de/adm_install/index.php</span></p></li>

   <li>
   <p>Nun erscheint folgende Seite auf der Sie ausw&auml;hlen k&ouml;nnen, ob Sie Admidio installieren, updaten oder eine
   neue Gruppierung hinzuf&uuml;gen wollen. Klicken Sie auf <b>Admidio Datenbank updaten</b>, falls diese
   Option noch nicht ausgew&auml;hlt wurde und dann auf den Button <b>Weiter</b>.</p>
   <p><img src="help/images/installation.png" alt="Admidio Installationsseite" /></p>
   </li>

   <li>Auf der n&auml;chsten Maske m&uuml;ssen Sie Ihre Zugangsdaten zur Datenbank und die momentan installierte Admidio-Version
   angeben. Nachdem Sie auf <b>Weiter</b> geklickt haben, warten Sie solange bis die Meldung erscheint, dass die
   Datenbank erfolgreich installiert wurde. </p>
   <p>Falls Sie wiederholt auf Weiter klicken oder die Seite schlie&szlig;en, kann das
   Datenbank update fehlschlagen und das Update eventuell auch nicht mehr erfolgreich wiederholt werden.</p></li>
   </ul>

   <b>Nun haben Sie Admidio erfolgreich aktualisiert und k&ouml;nnen mit der neuen Version arbeiten !</b>

</td>