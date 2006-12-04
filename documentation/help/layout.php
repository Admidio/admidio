
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
   <h2>Admidio dem eigenen Layout anpassen:</h2>

   <p>Nachdem Sie nun die Admidio-Module erfolgreich auf Ihrer Homepage verlinkt haben,
         m&ouml;chten Sie nun das Layout der Module Ihrer Homepage anpassen.
      Dazu gibt es vier Dateien im Ordner adm_config, die Sie ab&auml;ndern m&uuml;ssen
      und mit denen Sie Admidio sehr flexibel ver&auml;ndern k&ouml;nnen.</p>

   <p>Um Ihnen zu zeigen, wie einfach diese Anpassung sein kann, haben wir ein
      <a style="target-new: tab;" href="http://de.selfhtml.org/layouts/nr02/index.htm">fertiges
      Layout von SelfHtml</a> f&uuml;r Admidio &uuml;bernommen und werden die Anpassungen Schritt
      f&uuml;r Schritt mit Ihnen durchgehen.</p>

   <p><a style="target-new: tab;" href="help/images/screenshots/demo_1.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_1.png" alt="Die original Seite (vorher)" /></a>
      <br />Die original Seite (vorher)</p>
   <p><a style="target-new: tab;" href="help/images/screenshots/demo_7.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_7.png" alt="Die Admidio-Seite (nachher)" /></a>
      <br />Die Admidio-Seite (nachher)</p>

   <ul style="padding-left: 15px;">
      <li>
         <p>Als erstes bauen wir oben in der Link-Leiste einen neuen Link mit der Bezeichnung
         <b>Login</b> ein. Dieser wird mir der entsprechenden Loginseite von Admidio
         <span style="font-family: Courier New, Courier">adm_program/system/login.php</span>
         verlinkt.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_2.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_2.png" alt="Link einbauen" /></a></p>
      </li>
      <li>
         <p>Bei einem Klick auf den Link &ouml;ffnet sich die Loginseite noch im Admidio-Design.
         Dieses m&uuml;ssen wir nun Schritt f&uuml;r Schritt umstellen.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_3.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_3.png" alt="Link einbauen" /></a></p>
      </li>
      <li>
         <p>Als erstes passen Sie die <span style="font-family: Courier New, Courier">body_top.php</span>
         Datei an. Diese wird vor dem Admidio-Script in die Html-Datei eingebunden. Hier f&uuml;gen wir
         die Men&uuml;leiste mit dem Rahmen ein.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_4.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_4.png" alt="Link einbauen" /></a></p>
      </li>
      <li>
         <p>Im n&auml;chsten Schritt sollten Sie die <span style="font-family: Courier New, Courier">body_bottom.php</span>
         Datei anpassen. Diese wird nach dem Admidio-Script in die Html-Datei eingebunden und &auml;ndert den Fu&szlig;
         der Seite.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_5.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_5.png" alt="Link einbauen"/></a></p>
      </li>
      <li>
         <p>Nun m&uuml;ssen Sie die Datei <span style="font-family: Courier New, Courier">header.php</span>
         anpassen. Hier k&ouml;nnen Sie eigene Stylesheets oder Javascripts, die Sie zum Beispiel f&uuml;r Ihren
         Inhalt in <span style="font-family: Courier New, Courier">body_top.php</span> oder
         <span style="font-family: Courier New, Courier">body_bottom.php</span> brauchen.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_6.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_6.png" alt="Link einbauen" /></a></p>
      </li>

      <li>
         <p>Im letzten Schritt m&uuml;ssen Sie noch die Stylesheets in der
         <span style="font-family: Courier New, Courier">main.css</span> anpassen. Die einzelnen Klassen
         werden in der Datei kurz erkl&auml;rt. F&uuml;gen Sie dort Ihre Farbcodes ein und passen Sie, wenn gew&uuml;nscht,
         noch ein paar andere Einstellungen an.</p>
         <p><a style="target-new: tab;" href="help/images/screenshots/demo_7.png"><img style="border: 0px;" src="help/images/screenshots/demo_thumb_7.png" alt="Link einbauen" /></a></p>
         <p>Nun ist Admidio optimal an Ihre Seite angepasst.</p>
      </li>
   </ul>

   <br />

   <div style="text-align: left; float: left;">
      <a href="index.php?help/module_einbauen.php"><img src="help/images/icons/back.png" style="vertical-align: bottom; border: 0px;" alt="Aufruf der Module einbauen" title="Aufruf der Module einbauen" /></a>
   </div>
   <div style="text-align: right;">
      <b>N&auml;chster Artikel:</b>&nbsp;
      <a href="index.php?help/plugins.php">Plugins einbauen</a>&nbsp;
      <a href="index.php?help/plugins.php"><img src="help/images/icons/forward.png" style="vertical-align: bottom; border: 0px;" alt="Plugins einbauen" title="Plugins einbauen" /></a>
   </div>
</td>