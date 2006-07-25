
<?php include("help_menu.php"); ?>

<td style="background-color: #ffffff; padding-left: 15px;">
	<h2>Aufruf der Module einbauen:</h2>

	<p>Nachdem Sie Admidio erfolgreich installiert haben, m&ouml;chten Sie
	verschiedene Module in Ihre Homepage zu integrieren. Admidio liefert von
	Haus aus eine Reihe Module (Ank&uuml;ndigungen, Termine, Listen, Profile ...) mit.
	Diese k&ouml;nnen Sie alle auf der &Uuml;bersichtsseite sehen.</p>
      
	<a style="target-new: tab;" href="help/images/uebersicht.png"><img 
		style="border: 0px;" src="help/images/uebersicht_thumb.png" alt="&Uuml;bersicht der Module" 
		title="&Uuml;bersicht der Module" /></a>
		
	<p>Dazu kommen noch verschiedene Administrationsdialoge.</p>
          
	<p>Jedes dieser Module funktioniert eigenständig. Das heißt, Sie können einen Link zu diesem
	Modul an jeder beliebigen Stelle von Ihrer Homepage aus setzen. Sollte eine Benutzeranmeldung
	erforderlich sein, so wird die Loginseite automatisch aufgerufen. Ist der Benutzer einmal 
	angemeldet, so kann er zwischen den Admidio-Modulen und Ihren eigenen Seiten beliebig 
	navigieren und muss sich nicht noch einmal anmelden.</p>
	
	<p>Um die Sicherheit zu erhöhen sollten Sie aber einen Link zur Loginseite und zum Logout
	auf Ihrer Homepage einbauen.</p>
	   
	<h2>Links zu den Modulen:</h2>
	
	<p>Die folgenden Links zu den Modulen sind hier aus Platzgründen zweizeilig dargestellt. 
		Sie müssen diese dann natürlich zusammengeschrieben verlinken.</p>
	
	<p>Alle Paramter, die hier angegeben werden, sind optional. Jedes Modul kann ohne die 
		jeweiligen Paramter aufgerufen werden. Sie sollten diese nur übergeben, falls sie 
		die gewünschte Änderung benötigen.</p>
		
	<p>Parameter werden direkt an den URL angehangen. Sie müssen einfach ein <b>?</b>
		hinter die URL setzen und können dann den Parameter schreiben. Nach einem <b>=</b>
		folgt dann der Wert. Wollen Sie mehr als einen Parameter anhängen, so müssen sie 
		die Parameter durch ein <b>&</b> voneinander trennen.</p>
	
	<p class="code">Beispiel:<br />http://www.test.de/.../mail.php?rolle=Webmaster&subject=Test</p>

	<p>Bitte beachten Sie, dass in der kompletten URL kein Leerzeichen und Sonderzeichen 
		vorkommen darf. Dies gilt auch für die Parameter. Falls Sie dennoch einen Wert mit 
		Leerzeichen übergeben wollen, können Sie anstatt dem Leerzeichen einfach <b>%20</b> 
		schreiben.</p>

	<ul style="padding-left: 15px;">
		<li>
			 <p><b>Loginseite</b></p>
			 <p>Aufruf der Loginseite mit Link zur Registrierung und späteren Weiterleitung
			 	zum gewünschten Modul bzw. zur Startseite</p>
			 <p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/system/login.php</span>
			 </p>
		</li>
		<li>
			 <p><b>Logout</b></p>
			 <p>Ausloggen des Benutzers mit Weiterleitung zur Startseite</p>
			 <p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/system/logout.php</span>
			 </p>
		</li>
		<li>
			 <p><b>Ankündigungen</b></p>
			 <p>Liste mit allen erfassten Ankündigungen. Berechtige Benutzer haben hier die Möglichkeit
			  	Ankündigungen zu erfassen, editieren oder zu löschen.</p>
			 <p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/announcements/annnouncements.php</span>
			 </p>
			 <p>
				 <table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>headline</b></td>
				 		<td class="parameter"><i>Bezeichnung</i></td>
				 		<td class="parameter">Frei wählbare Bezeichnung für das Modul anstelle von Ankündigungen</td>
				 	</tr>
				 </table>
			 </p>
		</li>
		<li>
			<p><b>Downloads</b></p>
			<p>Auflisten von verschiedenen Dateidownloads in einer frei wählbaren Ordnerstruktur</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/downloads/downloads.php</span>
			</p>
			<p>
				<table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>default_folder</b></td>
				 		<td class="parameter"><i>Ordnername</i></td>
				 		<td class="parameter">Angabe des Downloadordners, der direkt angezeigt werden soll. 
				 			Die Angabe ist relativ zum Basisordner und kann Unterordner beinhalten.<br />
				 			Beispiel: <i>Dokumente/Protokolle</i></td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>sort</b></td>
				 		<td class="parameter"><i>asc</i> (default)<br /><i>desc</i></td>
				 		<td class="parameter">Angabe zur Sortierung der Dateien und Ordner.<br />
				 			Es wird normalerweise aufsteigend (A-Z) sortiert. Übergibt man sort=desc
				 			so wird absteigend (Z-A) sortiert.</i></td>
				 	</tr>
				 </table>
			</p>
		</li>
		<li>
			 <p><b>E-Mail</b></p>
			 <p>Verschicken von E-Mails an alle Mitglieder bestimmter Rollen. Ohne 
			 	Parameter, kann der Benutzer die Rolle über eine Kombobox wählen.</p>
			 <p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/mail/mail.php</span>
			 </p>
			 <p>
				<table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>rolle</b></td>
				 		<td class="parameter"><i>Rollenname</i></td>
				 		<td class="parameter">Name der Rolle, an deren Mitglieder eine E-Mail geschickt wird.</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>subject</b></td>
				 		<td class="parameter"><i>Betreff</i></td>
				 		<td class="parameter">Betreff der E-Mail kann hiermit vorbelegt werden.</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>body</b></td>
				 		<td class="parameter"><i>Text</i></td>
				 		<td class="parameter">Inhalt der E-Mail kann hier vorbelegt werden.</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>kopie</b></td>
				 		<td class="parameter"><i>1</i> (Default)<br /><i>0</i></td>
				 		<td class="parameter">Flag, ob eine Kopie an den Absender verschickt werden soll.</td>
				 	</tr>
				</table>
			</p>
		</li>		
		<li>
			<p><b>Fotos</b></p>
			<p>Anzeige einer Fotogallerie. Berechtigte Benutzer können neue Gallerien anlegen,
			pflegen und löschen.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/photos/photos.php</span>
			</p>
		</li>
		<li>
			<p><b>Gästebuch</b></p>
			<p>Ein Gästebuch mit Kommentierfunktion für Mitglieder bestimmter Rollen.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/guestbook/guestbook.php</span>
			</p>
		</li>
		<li>
			<p><b>Listen</b></p>
			<p>Übersicht über alle Rollen mit Aufruf verschiedener Mitgliederlisten
				zu jeder Rolle.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/lists/lists.php</span>
			</p>
			<p>
				<table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>category</b></td>
				 		<td class="parameter"><i>Kategoriename</i></td>
				 		<td class="parameter">Name der Kategorie deren Rollen direkt 
				 			angezeigt werden sollen</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>category-selection</b></td>
				 		<td class="parameter"><i>yes</i> (Default)<br /><i>No</i></td>
				 		<td class="parameter">Flag, ob die Kombobox mit der Kategorieauswahl
				 			angezeigt werden soll</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>active_role</b></td>
				 		<td class="parameter"><i>1</i> (Default)<br /><i>0</i></td>
				 		<td class="parameter">Bei 1 werden alle aktiven Rollen in der 
				 			Kategorie anzeigt und bei 0 nur die inaktiven Rollen</td>
				 	</tr>
				 </table>
			</p>
		</li>
		<li>
			<p><b>Eigene Listen</b></p>
			<p>Hier kann eine individuelle Mitgliederliste zu einer Rolle erstellt werden.
				Spalten, Sortierung und Bedingungen sind frei wählbar.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/lists/mylist.php</span>
			</p>
			<p>
				<table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>active_role</b></td>
				 		<td class="parameter"><i>1</i> (Default)<br /><i>0</i></td>
				 		<td class="parameter">Bei 1 werden alle aktiven Rollen der 
				 			Organisation anzeigt und bei 0 nur die inaktiven Rollen</td>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>active_member</b></td>
				 		<td class="parameter"><i>1</i> (Default)<br /><i>0</i></td>
				 		<td class="parameter">Bei 1 werden alle aktiven Mitglieder der 
				 			Rolle anzeigt und bei 0 nur die inaktiven Mitglieder</td>
				 	</tr>
				 </table>
			</p>
		</li>
		<li>
			<p><b>Profil</b></p>
			<p>Das Profil eines Benutzers. Es wird automatisch das Profil des aktuell
				eingeloggten Benutzers angezeigt.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/profile/profile.php</span>
			</p>
		</li>		
		<li>
			 <p><b>Termine</b></p>
			 <p>Liste mit allen erfassten Terminen. Berechtige Benutzer haben hier die Möglichkeit
			  	Termine zu erfassen, editieren oder zu löschen.</p>
			 <p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/dates/dates.php</span>
			 </p>
			 <p>
				 <table class="parameter">
				 	<tr>
				 		<th class="parameter">Parameter</th>
				 		<th class="parameter">Wert</th>
				 		<th class="parameter">Beschreibung</th>
				 	</tr>
				 	<tr>
				 		<td class="parameter"><b>mode</b></td>
				 		<td class="parameter"><i>actual</i> (Default)<br /><i>old</i></td>
				 		<td class="parameter">Im Standardfall werden alle kommenden Termine angezeigt. 
				 			Ist der Wert <i>old</i> werden nur die bereits vergangenen Termine angezeigt.</td>
				 	</tr>
				 </table>
			 </p>
		</li>
		<li>
			<p><b>Weblinks</b></p>
			<p>Liste aller eingetragenen Links. Berechtigte Benutzer können hier neue Links
				hinzufügen und Alte bearbeiten oder löschen.</p>
			<p>Link :<br />
			 	<span class="code">http://www.ihre-domain.de/admidio-ordner/ 
			 		adm_program/modules/links/links.php</span>
			</p>
		</li>		
	</ul>

	<br />

	<div style="text-align: left; float: left;">
		<a href="index.php?help/installation.php"><img src="help/images/back.png" style="vertical-align: bottom; border: 0px;" alt="Datenbank installieren und Basisdaten anlegen" title="Datenbank installieren und Basisdaten anlegen" /></a>
	</div>
	<div style="text-align: right;">
		<b>N&auml;chster Artikel:</b>&nbsp;
		<a href="index.php?help/layout.php">Admidio dem eigenen Layout anpassen</a>&nbsp;
		<a href="index.php?help/layout.php"><img src="help/images/forward.png" style="vertical-align: bottom; border: 0px;" alt="Admidio dem eigenen Layout anpassen" title="Admidio dem eigenen Layout anpassen" /></a>
	</div>
</td>