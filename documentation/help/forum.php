
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
    <h2>Einbau des phpBB Forums in Admidio:</h2>

    <p>In dieser Admidio Version 1.4 wird das phpForum (www.phpbb.de oder 
    www.phpbb.com) ab der Versionen 2.0.18 vollständig integriert und unterstützt. </p>
    
    
    <h2>Funktionsumfang:</h2>
    
    <p>Nach erfolgreicher Installation des Forums und der Anpassung in Admidio 
    erhalten sie auf der Übersichtsseite den zusätzlichen Modulpunkt Forum:<br>
    <img border="0" src="help/images/screenshots/forum_uebersicht_logoff.png" width="414" height="80"><br>
    In Admidio angemeldete User erhalten eine abweichende Ansicht dieses 
    Modulpunktes:<br>
    <img border="0" src="help/images/screenshots/forum_uebersicht_logon.png" width="505" height="66"><br>
    <br>
    Jeder User, der sich über eine Webanmeldung bei Admdio anmeldet, oder der 
    unter der Benutzerverwaltung von Admidio mit einem gültigen Benutzernamen 
    angelegt wurde, erhält im Forum einen gleichnamigen Benutzeraccount. </p>
    
    <p>Auf der Übersicht sieht jeder User, ob im Forum eine PM (Private Message) 
    noch ungelesen verweilt.</p>
    
    <p>Änderungen des Benutzernamens, der Email oder des Passwords in Admidio 
    werden im Forum gleichgezogen und sind somit redundant.</p>
    
    <p>Ein im Forum abweichendes Password oder Email wird immer auf das in 
    Admido hinterlegte Password und Email zurückgesetzt. Der User erhält dazu 
    eine entspechende Meldung.</p>
    
    <p>Es wird im Forum geprüft ob ein gleichnamiger Benutzeraccount existiert, 
    im ja Falle erhält der User bei der Webanmeldung die Aufforderung einen 
    anderen Benutzernamen zu wählen. Gleiches gilt für das Ändern oder Anlegen 
    eines Benutzernamens in Admidio.</p>
    
    <p>Bei der Erstanmeldung des Webmasters in Admidio, nach erfolgreicher 
    Installation des Forums und der Anpassung in Admidio, wird der Administrator 
    Account im Forum auf den Benutzernamen, dass Password und die Email der in 
    Admidio hinterlegten Daten gleichgezogen. Der Webmaster erhält einen 
    entsprechenden Hinweis.</p>
    
    <p><b>Hinweis:</b> Das Forum befindet sich im parallel Betrieb zu Admidio. 
    Mit der Standardinstallation und Anpassung kann sich jederzeit ein Benutzer 
    auf dem Forum anmelden, ohne sich dabei in Admidio zu registrieren. Das 
    bedeutet, AdmidioUser können im Forum mit dort registrierten Usern und 
    Besuchern schreiben. Ein Forum User ist nicht immer ein Admidio User, jedoch 
    ist jeder Admidio User (mit gültigem Benutzernamen) ein User im Forum.<br>
    Das Forum lässt sich auch&nbsp; -so es gewünscht ist - im Stand Allone 
    Betrieb mit Admidio anpassen. Dazu sind Anpassungen einiger php Datei des 
    Forums notwenig. Es ist dann keinerlei Registrierung im Forum mehr möglich. 
    Nur registrierte Benutzer über Admidio haben einen gültigen Account im 
    Forum. Dazu sind Anpassungen einiger php Datei des Forums notwenig, welche 
    ich im Abschnitt Stand-Allone-Forum näher erkläre.&nbsp; </p>
    
    <h2>Installation des phpBB Forums:</h2>
    
    <p>Laden sie sich auf phpbb.de oder phpbb.com die Installationsdateien 
    herunter und installieren sie das Board nach den Installationsanleitungen 
    von phpBB auf ihrem Webserver.&nbsp; Support für die Installation des phpBB 
    Forums erhalten sie bei phpBB direkt. <br>
    Wir empfehlen Ihnen einen Installationsordner direkt auf ihrem Webspace 
    unter root/forum. Sollte ihre Domaine z.B. www.beispiel.de sein, wäre der 
    entsprechende Ordner www.beispiel.de/forum</p>
    
    <h2>Nötige Einstellungen auf dem Forum:</h2>
    
    <p>Stellen sie sicher, dass das Forum komplett installiert und lauffähig 
    ist. Gehen sie in die Administration des Forums unter Allgemein, 
    Konfiguration. Suchen sie dort den Punkt &quot;Namenswechsel erlauben&quot; und 
    Schalten sie es auf NEIN! Speichern sie die Einstellungen.<br>
    <br>
    <img border="0" src="help/images/screenshots/forum_einstellungen.png" width="288" height="46"><br>
    <b><br>
    Hinweis, in Admidio werden die Forumsuser anhand ihres Benutzernamens im 
    Forum identifiziert und zugeordnet. Es ist sehr wichtig, dass sie diesen 
    Punkt deaktivieren.</b><br>
    Ein Wechsel des Benutzernamens in Admidio wird im Forum dann vollkommen 
    automatisch nachgezogen und gleichgesetzt. </p>
    
    <h2>Anpassungen der Admidio Software:</h2>
    
    <p>Laden sie sich die Datei config.php aus dem Verzeichnis adm_config 
    herunter und öffnen Sie diese mit einem Editor. Am Ende finden sie die 
    Zeilen:</p>

    <p class="code">// Forumspezifisch<br>
    // Forum integriert<br>
    // 1 = ja<br>
    // 0 = nein<br>
    $g_forum = 0;<br>
    <br>
    // Praefix der Tabellen des phpBB-Forums<br>
    $g_forum_praefix = &quot;&quot;;<br>
    <br>
    $g_forum_srv = &quot;&quot;;<br>
    $g_forum_usr = &quot;&quot;;<br>
    $g_forum_pw = &quot;&quot;;<br>
    $g_forum_db = &quot;&quot;;<br></p>
    
    
    <p>Setzen sie den Wert für $g_forum auf 1. </p>
    
    
    <p>Bei $g_forum_praefix verwenden sie den Praefix (ohne Unterstrich &quot;_&quot;), den 
    sie ebenfalls bei der Installation des Forums angegeben haben. <br>
    Der Standardwert ist bei einer Neuinstallation des Forums ist hier &quot;phpbb&quot;. </p>
    
    
    <p>Tragen sie unter $g_forum_srv den Datenbankserver ihrer mySQL Datenbank 
    ein. </p>
    
    
    <p>Bei $g_forum_usr verwenden sie den Usernamen für die Datenbank unter 
    $g_forum_srv.</p>
    
    
    <p>Das Password dieses Datenbankusers hinterlegen sie bei $g_forum_pw.</p>
    
    
    <p>Und letztendlich geben sie unter $g_forum_db den Namen der zu verwendeten 
    Datenbank an.</p>
    
    
    <p><b>Beispiel:</b> Sie haben den Datenbankserer auf <b>db.beispieldb.de</b> und 
    melden sich auf diesem Datenbankserver mit dem User <b>beispieluser</b> und dem 
    Password <b>beispielpassword</b> an. Sie verwenden dort die Datenbank auf der das 
    Forum ebenfalls installiert ist mit dem Namen <b>beispiel_forums_datenbank</b>. Dann 
    würde ihr config.php wie folgt aussehen:</p>
    
    
    <p class="code">// Forumspezifisch<br>
    // Forum integriert<br>
    // 1 = ja<br>
    // 0 = nein<br>
    $g_forum = 1;<br>
    <br>
    // Praefix der Tabellen des phpBB-Forums<br>
    $g_forum_praefix = &quot;phpbb&quot;;<br>
    <br>
    $g_forum_srv = &quot;db.beispieldb.de&quot;;<br>
    $g_forum_usr = &quot;beispieluser&quot;;<br>
    $g_forum_pw = &quot;beispielpassword&quot;;<br>
    $g_forum_db = &quot;beispiel_forums_datenbank&quot;;<br></p>
    
    <p>Speichern sie ihre config.php und laden sie diese auf ihren Webspace ins 
    Verzeichnis adm_config.</p>
    
    
    <p dir="ltr"><b>Hinweis:</b> Die Datei config.php ist im UTF8 Datenformat. 
    Viele Editoren setzen an den Anfang der Datei ein Byte-Order-Mark, welches 
    der Webserver als Zeichen interpretiert. Sie erhalten dann beim Aufruf der 
    Seite eine Fehlermeldung: <b>headers already sent (output started at...</b><br>
    Sollte dies bei ihnen der Fall sein, laden sie die Datei config.php mit dem 
    Standard Windows Editor Notepad. Dieser Notepad Editor kann sehr wohl UTF8 
    Dateien lesen, speichert diese jedoch ohne Byte-Order-Mark. Speichern sie 
    diese als speichern unter und überschreiben die original Datei.&nbsp; Laden 
    sie diese dann wieder auf ihren Webspace ins angegebene Verzeichnis.</p>
    
    
    <h2>Stand-Allone-Betrieb des Forums:</h2>
    
    <p>Standardmäßig wird das Forum im parallel Betrieb zu Admidio genutzt. 
    Jederzeit können sich User im Forum registrieren. Admidio Benutzer können 
    sich im Forum mit Gästen und anderen Forumsbenutzern unterhalten. </p>
	<p>Möchten sie das Forum exklusiv für Admidio Benutzer betreiben, sind 
	Anpassungen der Forum Sourcen notwenig.</p>
	<p>Zu bearbeitende Dateien:<br></p>
	<p class="code">includes/usercp_register.php<br>
	language/lang_german/lang_main.php<br>
	templates/subSilver/agreement.tpl</p>
	<p><b>Öffnen sie die Datei includes/usercp_register.php und suchen sie</b><br></p>
	<p class="code">if ( $mode == 'register' &amp;&amp; !isset($HTTP_POST_VARS['agreed']) &amp;&amp; 
!isset($HTTP_GET_VARS['agreed']) )</p>
	<p>Ersetzten sie diese Zeile mit</p>
	<p class="code">if ( $mode == 'register' &amp;&amp; 
    !isset($HTTP_POST_VARS['agreed']))</p>
	<p>suchen sie<br></p>
	<p class="code">show_coppa();</p>
	<p>Ersetzten sie diese Zeile mit</p>
	<p class="code">//show_coppa();<br>
    echo &quot;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&lt;b&gt;&lt;center&gt;<br>
    Die Registrierung ist auf diesem Board abgeschaltet!&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;<br>
    Melden sie sich bitte über die Admidio Seite an.&lt;/b&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;<br>
    &lt;a href=&quot;.getenv('HTTP_REFERER').&quot;&gt;Zurück zur vorherigen Seite&lt;/a&gt;&lt;br /&gt;&lt;br 
    /&gt;<br>
    &lt;a href=http://&quot;.getenv('SERVER_NAME').&quot;&gt;Zurück zur Hauptseite&lt;/a&gt;<br>
    &lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;&quot;;</p>
	<p><b>Öffnen sie die Datei 
	language/lang_german/lang_main.php, sofern vorhanden in allen anderen 
    Spachdateien, suchen sie</b></p>
	<p class="code">$lang['Agree_under_13']</p>
	<p>Entfernen sie in dieser und der Zeile, beginnend mit $lang['Agree_over_13'] die 
    &lt;b&gt; und &lt;/b&gt; HTML Tags.

   </p>

	<p><b>Öffnen sie die Datei 
	templates/subSilver/agreement.tpl und sofern vorhanden in allen weiteren 
    Templates, suchen sie</b><br></p>
	<p class="code">&lt;td&gt;&lt;span class=&quot;genmed&quot;&gt;&lt;br /&gt;{AGREEMENT}&lt;br /&gt;&lt;br /&gt;&lt;br 
    /&gt;&lt;div align=&quot;center&quot;&gt;&lt;a href=&quot;{U_AGREE_OVER13}&quot; 
    class=&quot;genmed&quot;&gt;{AGREE_OVER_13}&lt;/a&gt;&lt;br /&gt;&lt;br /&gt;&lt;a href=&quot;{U_INDEX}&quot; 
    class=&quot;genmed&quot;&gt;{DO_NOT_AGREE}&lt;/a&gt;&lt;/div&gt;&lt;br /&gt;&lt;/span&gt;&lt;/td&gt;</p>
	<p>Ersetzten sie diese Zeile mit</p>
	<p class="code">&lt;td&gt;&lt;span class=&quot;genmed&quot;&gt;&lt;br /&gt;{AGREEMENT}&lt;br /&gt;&lt;br /&gt;&lt;br 
    /&gt;&lt;div align=&quot;center&quot;&gt;&lt;form method=&quot;post&quot; action=&quot;{U_AGREE_OVER13}&quot;&gt;&lt;input 
    type=&quot;hidden&quot; name=&quot;agreed&quot; value=&quot;true&quot;&gt;&lt;input type=&quot;submit&quot; 
    class=&quot;mainoption&quot; style=&quot;width:700px; border:2px solid&quot; 
    value=&quot;{AGREE_OVER_13}&quot;&gt;&lt;br /&gt;&lt;br /&gt;&lt;input type=&quot;submit&quot; class=&quot;mainoption&quot; 
    style=&quot;width:700px; border:2px solid&quot; value=&quot;{AGREE_UNDER_13}&quot;&gt;&lt;/form&gt;&lt;br 
    /&gt;&lt;form method=&quot;post&quot; action=&quot;{U_INDEX}&quot;&gt;&lt;input type=&quot;submit&quot; 
    class=&quot;mainoption&quot; style=&quot;width:700px; border:2px solid&quot; 
    value=&quot;{DO_NOT_AGREE}&quot;&gt;&lt;/div&gt;&lt;br /&gt;&lt;/span&gt;&lt;/td&gt;</p>
	<p>Speicher sie die Dateien und laden Sie sie in die entsprechenden 
    Verzeichnisse hoch.</p>
	<p><b>Hinweis:</b> Die Änderungen der Sourcen beseitigen gleichzeitig den 
    Fehler der automatischen BOT Registrierungen im Forum.</p>

	<p>&nbsp;</p>

   <div style="text-align: left;">
      <a href="index.php?help/rollen.php"><img src="help/images/icons/back.png" style="vertical-align: bottom; border: 0px;" alt="Rollen" title="Rollen" /></a>
   </div>
</td>