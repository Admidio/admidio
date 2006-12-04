
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
    <h2>Der Unterschied zwischen Plugins und Modulen:</h2>

    <p>Plugins sind kleine Php-Scripte f&uuml;r bestimmte Aufgaben (z.B. n&auml;chsten 2 Termine anzeigen, aktuelle Geburtstage anzeigen ...).
    Sie m&uuml;ssen im Gegensatz zu Modulen aber nicht unbedingt als eigene Seite aufgerufen werden, sondern k&ouml;nnen in jede beliebige
    Html- oder Php-Seite eingebunden werden. Sie k&ouml;nnen die Plugins auch in der body_top.php oder body_bottom.php von Admidio 
    einbauen, so dass bestimmte Informationen auf allen Admidio-Seiten angezeigt werden.</p>
    
    <h2>Wie baue ich Plugins ein ?</h2>
    
    <p>Laden Sie das Plugin von unserer Homepage herunter und entpacken Sie die Zip-Datei. Kopieren Sie das
    Verzeichnis mit den Dateien in den Ordner <b>adm_plugins</b> in Ihrem Admidio-Ordner. Falls der Ordner adm_plugins
    noch nicht existiert, m&uuml;ssen Sie ihn zuerst noch erstellen. Dieser sollte sich auf derselben Ebene wie <i>adm_my_files</i> 
    befinden.</p>
    
    <p>Plugins sind meistens in Php geschrieben und sollen ihren Inhalt in eine bestehende Html- oder 
    Php-Seite einbinden.</p>
    
    <p>Falls Sie das Plugin nun in eine reine Html-Seite einbinden wollen, sollten Sie die Dateierweiterung
    der Seite zuerst von <i>html</i> in <i>php</i> umbenennen. Nun k&ouml;nnen wir das Modul mit Hilfe von Php
    in Ihre ehemals reine Html-Seite integrieren. F&uuml;gen Sie nun folgenden Abschnitt im Html-Code an der Stelle 
    ein, an der die Ausgabe des Plugins dargestellt werden soll und ersetzen Sie den Link zum Plugin durch Ihre
    individuellen Daten:</p>
    
    <p class="code">&lt;?php<br>
    include("<i>http://www.meine-domain.de/admidio/adm_plugins/
    sidebar_dates/sidebar_dates.php</i>");<br>
    ?&gt;</p>
    
    <p>Kann man das Plugin &uuml;ber Variablen konfigurieren, so sieht der 
    <a href="http://tut.php-q.net/get.html#u2" target="_blank">Aufruf mit Variablen</a> in etwa so aus:</p>

    <p class="code">&lt;?php<br>
    include("<i>http://www.meine-domain.de/admidio/adm_plugins/
    sidebar_dates/sidebar_dates.php<b>?plg_dates_count=3</b></i>");<br>
    ?&gt;</p>

    <p>Innerhalb von Php-Code ist nat&uuml;rlich nur der Include-Aufruf n&ouml;tig.
    <p class="code">include("<i>http://www.meine-domain.de/admidio/adm_plugins/
    sidebar_dates/sidebar_dates.php?plg_dates_count=3</i>");</p>
    
    <p>Ein Beispiel f&uuml;r eine Integration von mehreren Plugins in einer Seitenleiste sehen Sie auf diesem 
    Screenshot im hervorgehobenen Bereich:</p>
    
    <a style="target-new: tab;" href="help/images/screenshots/plugins_md.png"><img
        style="border: 0px;" src="help/images/screenshots/plugins_md_thumb.png" alt="Beispiel f&uuml;r Plugins"
    title="Beispiel f&uuml;r Plugins" /></a>

   <br /><br />

   <div style="text-align: left; float: left;">
      <a href="index.php?help/layout.php"><img src="help/images/icons/back.png" style="vertical-align: bottom; border: 0px;" alt="Admidio dem eigenen Layout anpassen" title="Admidio dem eigenen Layout anpassen" /></a>
   </div>
   <div style="text-align: right;">
      <b>N&auml;chster Artikel:</b>&nbsp;
      <a href="index.php?help/rollen.php">Rollen anlegen und pflegen</a>&nbsp;
      <a href="index.php?help/rollen.php"><img src="help/images/icons/forward.png" style="vertical-align: bottom; border: 0px;" alt="Rollen anlegen und pflegen" title="Rollen anlegen und pflegen" /></a>
   </div>
</td>