<?php
/******************************************************************************
 * Popup-Window mit Informationen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * err_code - Code fuer die Information, die angezeigt werden soll
 * err_text - Text, der innerhalb einer Meldung angezeigt werden kann
 * window    - true wenn das script über window.open anstatt über das tooltip aufgerufen wird
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_rooms.php');
// lokale Variablen der Uebergabevariablen initialisieren
$req_err_code = null;
$req_err_text = null;

// Uebergabevariablen pruefen

if(isset($_GET['err_code']) && strlen($_GET['err_code']) > 0)
{
    $req_err_code = strStripTags($_GET['err_code']);
}
else
{
    $g_message->show('invalid');
}

if(isset($_GET['err_text']))
{
    $req_err_text = strStripTags($_GET['err_text']);
}

// Html-Kopf ausgeben
if(isset($_GET['window']))
{
    $g_layout['title']    = 'Hinweis';
    $g_layout['includes'] = false;
    require(THEME_SERVER_PATH. '/overall_header.php');

    $title=$_GET['room_id']==null?'Hinweis':'Raumdetails';
    // Html des Modules ausgeben
    echo '
    <div class="formLayout" id="message_window">
            <div class="formHead">'.$title.'</div>
            <div class="formBody">';
}

switch ($req_err_code)
{
    case 'bbcode':
        echo 'Die Beschreibung bei einigen Modulen (Ankündigungen, Terminen, Gästebuch und Weblinks)
              können mit verschiedenen Tags (BBCode) formatiert werden. Dafür müssen die
              hier aufgelisteten Tags um den entsprechenden Textabschnitt gesetzt werden.<br /><br />
              <table class="tableList" style="width: auto;" cellspacing="0">
                 <tr>
                    <th style="width: 155px;">Beispiel</th>
                    <th>BBCode</th>
                 </tr>
                 <tr>
                    <td>Text <b>fett</b> darstellen</td>
                    <td>Text <b>[b]</b>fett<b>[/b]</b> darstellen</td>
                 </tr>
                 <tr>
                    <td>Text <u>unterstreichen</u></td>
                    <td>Text <b>[u]</b>unterstreichen<b>[/u]</b></td>
                 </tr>
                 <tr>
                    <td>Text <i>kursiv</i> darstellen</td>
                    <td>Text <b>[i]</b>kursiv<b>[/i]</b> darstellen</td>
                 </tr>
                 <tr>
                    <td>Text <span style="font-size: 14pt;">groß</span> darstellen</td>
                    <td>Text <b>[big]</b>groß<b>[/big]</b> darstellen</td>
                 </tr>
                 <tr>
                    <td>Text <span style="font-size: 8pt;">klein</span> darstellen</td>
                    <td>Text <b>[small]</b>klein<b>[/small]</b> darstellen</td>
                 </tr>
                 <tr>
                    <td style="text-align: center;">Text zentriert darstellen</td>
                    <td><b>[center]</b>Text zentriert darstellen<b>[/center]</b></td>
                 </tr>
                 <tr>
                    <td>Einen <a href="http://www.admidio.org">Link</a> setzen</td>
                    <td>Einen <b>[url=</b>http://www.admidio.org<b>]</b>Link<b>[/url]</b> setzen</td>
                 </tr>
                 <tr>
                    <td>Eine <a href="mailto:webmaster@admidio.org">Mailadresse</a> angeben</td>
                    <td>Eine <b>[email=</b>webmaster@admidio.org<b>]</b> Mailadresse<b>[/email]</b> angeben</td>
                 </tr>
                 <tr>
                    <td>Ein Bild <img src="'.THEME_PATH.'/images/admidio_logo_20.png" alt="logo" /> anzeigen</td>
                    <td>Ein Bild <b>[img]</b>http://www.admidio.org/bild.jpg<b>[/img]</b> anzeigen</td>
                 </tr>
              </table>';
        break;

    case 'category_global':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = $g_current_organization->getValue('org_longname');
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));

        echo 'Profilfelder von Kategorien bei denen diese Option aktiviert ist, erscheinen im Profil
              folgender Organisationen:
              <p><strong>- '.$organizations.'</strong></p>
              Möchtest du die Daten nur in deiner Organisation sehen, dann sollte diese Option deaktiviert werden.';
        break;

    case 'date_global':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = $g_current_organization->getValue('org_longname');
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));

        echo 'Termine / Ankündigungen, die diese Option aktiviert haben, erscheinen auf den Webseiten
              folgender Organisationen:
              <p><strong>- '.$organizations.'</strong></p>
              Moderatoren dieser Organisationen können den Termin / Ankündigung dann bearbeiten
              bzw. die Option zurücksetzen.';
        break;
        
    case 'date_login_possible': 
        echo 'Hier können Sie auswählen, ob eine Anmeldung zu dem von Ihnen angegebenen Termin möglich sein soll oder nicht. <br/> Falls Sie die Anmeldung nachträglich löschen, so werden auch alle bisherigen Teilnehmer vom Termin entfernt. <br/> Wenn Sie hingegen nur die Anmeldung für neue Teilnehmer beenden möchten, so setzen Sie die
        derzeitige Teilnehmerzahl als Teilnahmebegrenzung.';
        break;
        
    case 'date_max_members': 
        echo 'Hier können Sie die Teilnehmeranzahl beschränken. Ist die Anzahl nicht begrenzt, so können Sie dieses Feld leer lassen.';
        break;
    
    case 'date_location_link':
        echo 'Werden genügend Informationen (Straße, Stadt, Lokalität) zum Ort des Termins eingegeben, 
        so kann ein Link bzw. eine Route zu diesem Treffpunkt über Google-Maps erstellt werden.';
        break;
        
    case 'room_overhang':
        echo 'Sind noch zusätzliche Plätze außer der oben angegebenen Sitzpätze zu vergeben, wie z.B. durch zusätzliche Stühle oder mögliche
              Stehplätze, so kann ihre Anzahl hier im Überhang angegeben werden. ';
        break;
    
    case 'enable_rss':
        echo 'Admidio kann RSS-Feeds für verschiedene Module (Ankündigungen,
              Termine, Gästebuch und Weblinks) auf den jeweiligen Übersichtsseiten
              bereitstellen, die dann über den Browser einem Feedreader zugeordnet
              werden können.';
        break;

    case 'field':
        echo 'Es können beliebig viele zusätzliche Felder definiert werden.
              Diese werden im Profil der einzelnen Benutzer angezeigt und können dort auch
              bearbeitet werden. Außerdem stehen diese Felder bei den Eigenen Listen zur
              Verfügung.';
        break;

    case 'field_hidden':
        echo 'Ein Feld ist normalerweise für alle Benutzer sichtbar. Wird diese Funktion
              nicht ausgewählt, so können die Daten nur von Benutzern gesehen werden,
              die das Recht haben alle Benutzer zu bearbeiten. Im eigenen Profil kann der Benutzer
              diese Daten auch sehen.';
        break;

    case 'field_disabled':
        echo 'Wird ein Feld gesperrt, so können Benutzer im eigenen Profil dieses Feld nicht
              bearbeiten. Es kann nur noch von Benutzern bearbeitet werden, die das Rollenrecht
              besitzen alle Benutzer zu bearbeiten.';
        break;

    case 'field_mandatory':
        echo 'Felder, die als Pflichtfelder markiert sind, müssen immer gefüllt werden.
              Dies gilt für die Registrierung, aber auch bei der gewöhnlichen
              Profildatenbearbeitung.';
        break;

    case 'leader':
        echo 'Neben der separaten Darstellung der Leiter in Listen und Rollenzuordnungen
              haben Leiter mehr Rechte als ein normales Rollenmitglied.<br /><br />
              Leiter können unabhängig von der Rollenrechteeinstellung die Mitgliederlisten
              immer einsehen und Mitglieder der Rolle zuordnen oder entfernen.';
        break;

    case 'mail_max_attachment_size':
        require_once('classes/email.php');
        echo 'Du kannst beliebig viele Anhänge hinzufügen. Allerdings darf die Dateigröße aller 
              Anhänge zusammen '. Email::getMaxAttachementSize('mb'). ' MB nicht überschreiten.';
        break;

    case 'profile_login_name':
        echo 'Mit diesem Namen kannst du dich später auf der Homepage anmelden.<br /><br />
              Damit du ihn dir leicht merken kannst, solltest du deinen Spitznamen oder Vornamen nehmen.
              Auch Kombinationen, wie zum Beispiel <i>Andi78</i> oder <i>StefanT</i>, sind möglich.';
        break;

    case 'profile_password':
        echo 'Das Passwort muss mindestens aus 6 Zeichen bestehen. Es sollte sowohl Zahlen,  
              Buchstaben als auch Sonderzeichen beinhalten.<br /><br />
              Aus Sicherheitsgründen wird das Passwort verschlüsselt gespeichert.
              Es ist später nicht mehr möglich dieses einzusehen.';
        break;

    case 'rol_edit_user':
        echo 'Rollen, die diese Option aktiviert haben, haben die Berechtigung alle
              Benutzerdaten (außer Passwörter) anderer Mitglieder zu bearbeiten.<br />
              Außerdem haben sie Zugriff auf die Benutzerverwaltung und können
              dort neue Benutzer anlegen oder alte Benutzer löschen.';
        break;

    case 'role_mail_this_role':
        echo 'Diese Einstellung steuert, wer das Recht hat über das Mailmodul Emails an diese Rolle zu schicken.
			  Das Rollenrecht <strong>Emails an alle Rollen schreiben</strong> steht allerdings noch
              über dieser Einstellung.';
        break;
        
    case 'role_show_list':
        echo 'Diese Einstellung steuert, welche Benutzer das Recht haben, diverse Listen und
              die einzelnen Benutzerprofile der Rollenmitglieder anzuschauen. Das Rollenrecht
              <strong>Mitgliederlisten aller Rollen einsehen</strong> steht allerdings noch
              über dieser Einstellung.';
        break;

    case 'rol_assign_roles':
        echo 'Benutzer dieser Rolle haben Zugriff auf die Rollenverwaltung und können neue
              Rollen erstellen, verwalten und anderen Benutzern Rollen zuordnen.';
        break;

    case 'rolle_mail':
        echo 'Deine E-Mail wird an alle Mitglieder der ausgewählten Rolle geschickt, sofern
              diese ihre E-Mail-Adresse im System hinterlegt haben.<br /><br />
              Wenn du eingeloggt bist stehen dir weitere Rollen zur Verfügung, an die du E-Mails
              schreiben kannst.';
        break;

    case 'rolle_ecard':
        echo 'Deine Grußkarte wird an ein Mitglied der ausgewählten Rolle geschickt, sofern
              diese ihre E-Mail-Adresse im System hinterlegt hat.<br /><br />
              Unter der Rollenauswahl besteht die Möglichkeit ein Mitglied dieser Rolle oder die gesamte Rolle auszuwählen.';
        break;

    case 'role_assign':
        echo 'Wähle bitte eine Rolle aus, der alle importierten Benutzer automatisch zugeordnet werden.<br /><br />
        Dir stehen nur Rollen zur Auswahl, die du sehen darfst und keine Rollenzuordnungsberechtigung besitzen,
        falls du diese selber nicht besitzt.';
        break;
    
    case 'room_detail':
        $room = new TableRooms($g_db);
        $room->readData($_GET['room_id']);
        echo '
        <table>
            <tr>
                <td><strong>Raumname:</strong></td>
                <td>'.$room->getValue('room_name').'</td>
            </tr>
            <tr>
                <td><strong>Kapazität:</strong></td>
                <td>'.$room->getValue('room_capacity').'</td>
            </tr>
            <tr>
                <td><strong>Überhang:</strong></td>
                <td>'.$room->getValue('room_overhang').'</td>
            </tr>
            <tr>
                <td><strong>Raumbeschreibung:</strong></td>
                <td>'.$room->getValue('room_description').'</td>
            </tr>
        </table>
        ';
        break;

    case 'user_field_description':
        echo $g_current_user->getProperty($req_err_text, 'usf_description');
        break;

    //Downloadmodulhilfen

    case 'dateiname':
        echo 'Die Datei sollte so benannt sein, dass man vom Namen auf den Inhalt schließen kann.
           Der Dateiname hat Einfluss auf die Anzeigereihenfolge. In einem Ordner in dem z.B. Sitzungsprotokolle
           gespeichert werden, sollten die Dateinamen immer mit dem Datum beginnen (jjjj-mm-tt).';
        break;

    case 'folderNotExists':
        echo 'Der Ordner existiert physikalisch nicht mehr auf dem Server. Der Ordner sollte aus der Datenbank gelöscht werden.';
        break;

    case 'fileNotExists':
        echo 'Die Datei existiert physikalisch nicht mehr auf dem Server. Die Datei sollte aus der Datenbank gelöscht werden.';
        break;

    case 'additionalFiles':
        echo 'In dieser Übersicht sind Dateien und Ordner aufgelistet, die noch nicht in der Datenbank verwaltet werden.
            Diese können nun der Datenbank hinzugefügt werden. Es werden automatisch die Berechtigungen des aktuellen Ordners übernommen.';
        break;

    case 'publicDownloadFlag':
        echo 'Wenn diese Option aktiviert ist, können nur eingeloggte Besucher,
            die Mitglied in einer der berechtigten Rollen sind, den Inhalt des Ordners sehen.';
        break;

	// Eigene Listen

    case 'mylist_condition':
        echo 'Hier kannst du Bedingungen zu jedem Feld in deiner neuen Liste eingeben.
              Damit wird die ausgewählte Rolle noch einmal nach deinen Bedingungen
              eingeschränkt.<br /><br />
              Beispiele:<br /><br />
              <table class="tableList" style="width: 100%;" cellspacing="0">
                 <tr>
                    <th style="width: 75px;">Feld</th>
                    <th style="width: 110px;">Bedingung</th>
                    <th>Erklärung</th>
                 </tr>
                 <tr>
                    <td>Nachname</td>
                    <td><b>Schmitz</b></td>
                    <td>Sucht alle Benutzer mit dem Nachnamen Schmitz</td>
                 </tr>
                 <tr>
                    <td>Nachname</td>
                    <td><b>Mei*</b></td>
                    <td>Sucht alle Benutzer deren Namen mit Mei anfängt</td>
                 </tr>
                 <tr>
                    <td>Geburtstag</td>
                    <td><b>&gt; 01.03.1986</b></td>
                    <td>Sucht alle Benutzer, die nach dem 01.03.1986 geboren wurden</td>
                 </tr>
                 <tr>
                    <td>Geburtstag</td>
                    <td><b>&gt; 18j</b></td>
                    <td>Sucht alle Benutzer, die älter als 18 Jahre sind</td>
                 </tr>
                 <tr>
                    <td>Geschlecht</td>
                    <td><b>M/W/U</b></td>
                    <td>Sucht nach Männlich, Weiblich, Unbekannt</td>
                 </tr>
                 <tr>
                    <td>Ort</td>
                    <td><b>Köln oder Bonn</b></td>
                    <td>Sucht alle Benutzer, die aus Köln oder Bonn kommen</td>
                 </tr>
                 <tr>
                    <td>Telefon</td>
                    <td><b>*241*&nbsp;&nbsp;*54</b></td>
                    <td>Sucht alle Benutzer, deren Telefonnummer 241 enthält und
                       mit 54 endet</td>
                 </tr>
                 <tr>
                    <td>Ja/Nein Feld</td>
                    <td><b>Ja</b></td>
                    <td>Sucht alle Benutzer bei denen ein Häckchen gesetzt wurde</td>
                 </tr>
              </table>';
        break;

	case 'mylist_config_webmaster':
		echo '<h3>Vorgegebene Konfigurationen</h3>
		    Als Webmaster kannst du Konfigurationen erstellen, welche allen Benutzern des Systems zur Verfügung stehen.
		 	Konfiguriere die entsprechende Spalten und Bedingungen und speicher diese unter dem gewünschten Namen. 
		 	Wähle nun die neu erstellte Liste aus und klick auf dieses Symbol: 
		 	<img src="'. THEME_PATH. '/icons/list_global.png" alt="list_global" />
		 	<h3>Standardkonfigurationen</h3>
		 	Eine vorgegebene Konfiguration kannst du zur Standardkonfiguration machen. Wähle dazu die entsprechende 
		 	vorgegebene Konfiguration aus und klick auf folgendes Symbol: 
		 	<img src="'. THEME_PATH. '/icons/star.png" alt="star" />. Die gewählte Konfiguration wird nun
		 	an allen Stellen angezeigt, bei denen der Anwender eine Rollenliste angezeigt bekommt, ohne
		 	vorher die Möglichkeit zu haben, eine Konfiguration auszuwählen.';
		break;

    //Fotomodulhifen

   case 'photo_up_help':
        echo '<h3>Was ist zu tun?</h3>
            <ul>
				<li>Auf den &bdquo;Durchsuchen&ldquo; Button klicken und die gewünschte(n) Fotodatei(en) von der Festplatte auswählen.</li>
				<li>Bei Einzelbildupload den Vorgang ggf. bis zu fünfmal wiederholen, bis alle Felder gefüllt sind.</li>
				<li>Dann auf &bdquo;Fotos hochladen&ldquo; klicken und ein wenig Geduld haben.</li>
			</ul>  
            <h3>Einschränkungen:</h3>
            <ul>
				<li>Die Fotos müssen im Format JPG oder PNG gespeichert sein.</li>
				<li>Der Server kann Fotos mit einer maximalen Auflösung von '.round(processableImageSize()/1000000, 2).' MegaPixeln verarbeiten.</li>
				<li>Die hochgeladenen Dateien dürfen nicht größer als '.round(maxUploadSize()/pow(1024, 2), 2).'MB sein.</li>
                <li>
					Die Fotos werden automatisch auf eine Auflösung von '.$g_preferences['photo_save_scale'].' Pixel der
                	längeren Seite skaliert (andere Seite im Verhältnis) bevor sie gespeichert werden.
				</li>
				<li>Der Name der Dateien spielt keine Rolle, da sie automatisch mit fortlaufender Nummer benannt werden.</li>
                <li>
					Da auch bei schnellen Internetanbindungen das Hochladen von größeren Dateien einige
                	Zeit in Anspruch nehmen kann, empfehlen wir zunächst alle hoch zu ladenden Fotos in einen
                	Sammelordner zu kopieren und diese dann mit einer Fotobearbeitungssoftware auf '.$g_preferences['photo_save_scale'].' Pixel
                	(längere Seite) zu skalieren. Die JPG-Qualität sollte beim Abspeichern auf mindestens 90%
                	(also geringe Komprimierung) gestellt werden.
				</li>
			</ul>
            ';
        break;

    case 'album_help':
        echo '<h3>Was ist zu tun?</h3>
            Alle offenen Felder ausfüllen. Die Felder Album und Beginn sind Pflichtfelder. Ggf. auswählen
            welches Album das Neue untergeordnet werden soll, z.B. &bdquo;Tag 3&ldquo; in 
            &bdquo;Turnier 2010&ldquo; (solche Unterteilungen sind empfehlenswert bei vielen Fotos).
            Die Felder Ende und Fotografen sind optional. Nur freigegebene Alben sind für 
            Homepagebesucher sichtbar. Möchte man z.B. erst alle Fotos hochladen
            oder auch nur schon mal alle Daten eintragen, kann man die Freigabe einfach später setzen.
            Danach auf Speichern klicken.';
        break;

    case 'folder_not_found':
        echo 'Der zugehörige Ordner wurde nicht gefunden. Sollte er bewusst über FTP gelöscht worden sein
            oder nicht mehr die Möglichkeit bestehen ihn wieder herzustellen, bitte
            den Datensatz mit klick auf das Symbol <img src="'. THEME_PATH. '/icons/delete.png" style="vertical-align: top;" alt="delete" /> löschen.<br />
            Besuchern der Website ohne Fotoverwaltungsrecht, wird dieses Album nicht mehr angezeigt.';
        break;

    case 'not_approved':
        echo 'Das Album ist z.Zt. gesperrt und wird Homepagebesuchern deswegen nicht angezeigt. Zum Freigeben bitte
            das entsprechende Symbol <img src="'. THEME_PATH. '/icons/key.png" alt="key" /> in der Bearbeitungszeile nutzen.';
        break;

    //Captcha-Hilfen

    case 'captcha_help':
        echo ' <h3>Was ist das für ein Bestätigungscode?</h3>
            Hierbei handelt es sich um ein Captcha. Ein Captcha dient zur Spamerkennung. Mit Hilfe des Bildes 
            wird versucht festzustellen, ob das Formular von einem User oder einem Script/Spambot ausgefüllt wurde. <br /> 
            Bitte trage den im Bild angezeigten 4- bis 6-stelligen Code in das Formularfeld ein.';
        break;

    //Profil

    case 'profile_photo_up_help':
		echo '<h3>Was ist zu tun?</h3>
			<ul>
				<li>Auf den &bdquo;Durchsuchen&ldquo; Button klicken und die gewünschte Fotodatei von der Festplatte auswählen.</li>
				<li>Danach auf &bdquo;Foto hochladen&ldquo; klicken und ein wenig Geduld haben.</li>
			</ul>
			<h3>Einschränkungen:</h3>
			<ul>
				<li>Du solltest selbst auf dem Foto zu sehen sein.</li>
				<li>Das Foto muss im Format JPG oder PNG gespeichert sein.</li>
				<li>Der Server kann Fotos mit einer maximalen Auflösung von '.round(processableImageSize()/1000000, 2).' MegaPixeln verarbeiten.</li>
				<li>Die hochgeladene Datei darf nicht größer als '.round(maxUploadSize()/pow(1024, 2), 2).'MB sein.</li>
			</ul>
			';
		break;

    default:
        echo 'Es ist ein Fehler aufgetreten.';
        break;
}

if(isset($_GET['window']))
{
	echo '</div>
	</div>
	
	<ul class="iconTextLinkList">
	    <li>
	        <span class="iconTextLink">
	            <a href="javascript:self.parent.tb_remove()?\'\':\'\';"><img
	            src="'.THEME_PATH.'/icons/door_in.png" alt="Schließen" /></a>
	            <a href="javascript:self.parent.tb_remove()?\'\':\'\';">Schließen</a>
	        </span>
	    </li>
	</ul>';
	
	require(THEME_SERVER_PATH. '/overall_footer.php');
}
?>