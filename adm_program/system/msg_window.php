<?php
/******************************************************************************
 * Popup-Fenster mit Informationen
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * message_id    - ID des Sprachtextes, der angezeigt werden soll
 * message_title - (optional) Titel des Fensters (Default: Hinweis)
 * message_var1  - (optional) Text, der innerhalb einer Meldung angezeigt werden kann
 * inline        - true wenn die Nachricht nicht in einem separaten Fenster angezeigt wird
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_rooms.php');

// lokale Variablen der Uebergabevariablen initialisieren
$req_message_id    = '';
$req_message_title = '';
$req_message_var1  = '';
$inlineView        = false;

// Uebergabevariablen pruefen

if(isset($_GET['message_id']) && strlen($_GET['message_id']) > 0)
{
    $req_message_id = strStripTags($_GET['message_id']);
}
else
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['message_title']))
{
    $req_message_title = strStripTags($_GET['message_title']);
}
else
{
    $req_message_title = $g_l10n->get('SYS_NOTE');
}

if(isset($_GET['message_var1']))
{
    $req_message_var1 = strStripTags($_GET['message_var1']);
}

if (isset($_GET["inline"]) && $_GET["inline"] == true)
{
    $inlineView = true;
}

// Html-Kopf ausgeben
if($inlineView)
{
    // Html des Modules ausgeben
    echo '
    <div class="formLayout" id="message_window">
            <div class="formHead">'.$req_message_title.'</div>
            <div class="formBody">';
}

switch ($req_message_id)
{
    case 'bbcode':
        echo $g_l10n->get('SYS_PHR_BBCODE_DESC').'<br /><br />
              <table class="tableList" style="width: auto;" cellspacing="0">
                 <tr>
                    <th style="width: 155px;">'.$g_l10n->get('SYS_EXAMPLE').'</th>
                    <th>'.$g_l10n->get('SYS_BBCODE').'</th>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>', '</b>').'</td>
                    <td>'.$g_l10n->get('SYS_BOLD_TEXT_REPRESENT', '<b>[b]</b>', '<b>[/b]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_UNDERLINE_TEXT', '<u>', '</u>').'</td>
                    <td>'.$g_l10n->get('SYS_UNDERLINE_TEXT', '<b>[u]</b>', '<b>[/u]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_TEXT_ITALIC', '<i>', '</i>').'</td>
                    <td>'.$g_l10n->get('SYS_TEXT_ITALIC', '<b>[i]</b>', '<b>[/i]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_REPRESENT_LARGE_TEXT', '<span style="font-size: 14pt;">', '</span>').'</td>
                    <td>'.$g_l10n->get('SYS_REPRESENT_LARGE_TEXT', '<b>[big]</b>', '<b>[/big]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_REPRESENT_SMALL_TEXT', '<span style="font-size: 8pt;">', '</span>').'</td>
                    <td>'.$g_l10n->get('SYS_REPRESENT_SMALL_TEXT', '<b>[small]</b>', '<b>[/small]</b>').'</td>
                 </tr>
                 <tr>
                    <td style="text-align: center;">'.$g_l10n->get('SYS_CENTERED_TEXT_REPRESENT').'</td>
                    <td><b>[center]</b>'.$g_l10n->get('SYS_CENTERED_TEXT_REPRESENT').'<b>[/center]</b></td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SET_LINK', '<a href="http://www.admidio.org">', '</a>').'</td>
                    <td>'.$g_l10n->get('SYS_SET_LINK', '<b>[url=</b>http://www.admidio.org<b>]</b>', '<b>[/url]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<a href="mailto:webmaster@admidio.org">', '</a>').'</td>
                    <td>'.$g_l10n->get('SYS_SPECIFY_EMAIL_ADDRESS', '<b>[email=</b>webmaster@admidio.org<b>]</b>', '<b>[/email]</b>').'</td>
                 </tr>
                 <tr>
                    <td>'.$g_l10n->get('SYS_SHOW_IMAGE', '<img src="'.THEME_PATH.'/images/admidio_logo_20.png" alt="logo" />').'</td>
                    <td>'.$g_l10n->get('SYS_SHOW_IMAGE', '<b>[img]</b>http://www.admidio.org/bild.jpg<b>[/img]</b>').'</td>
                 </tr>
              </table>';
        break;

    case 'CAT_PHR_CATEGORY_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$g_current_organization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));
        echo $g_l10n->get(strtoupper($req_message_id), $organizations);
        break;

    case 'SYS_PHR_DATA_GLOBAL':
        // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
        $organizations = '- '.$g_current_organization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $g_current_organization->getReferenceOrganizations(true, true, true));
        echo $g_l10n->get(strtoupper($req_message_id), $organizations);
        break;
    
    case 'room_detail':
        if(is_numeric($req_message_var1))
        {
            $room = new TableRooms($g_db);
            $room->readData($req_message_var1);
            echo '
            <table>
                <tr>
                    <td><strong>'.$g_l10n->get('SYS_ROOM').':</strong></td>
                    <td>'.$room->getValue('room_name').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('ROO_CAPACITY').':</strong></td>
                    <td>'.$room->getValue('room_capacity').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('ROO_OVERHANG').':</strong></td>
                    <td>'.$room->getValue('room_overhang').'</td>
                </tr>
                <tr>
                    <td><strong>'.$g_l10n->get('SYS_DESCRIPTION').':</strong></td>
                    <td>'.$room->getDescription('HTML').'</td>
                </tr>
            </table>';
        }
        break;

    case 'user_field_description':
        echo $g_current_user->getProperty($req_message_var1, 'usf_description');
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
        // im Standardfall wird mit der ID der Text aus der Sprachdatei gelesen
        // falls die Textvariable gefuellt ist, pruefen ob dies auch eine ID aus der Sprachdatei ist
        $msg_var1 = '';
        if(strlen($req_message_var1) > 0)
        {
            $msg_var1 = $g_l10n->get($req_message_var1);
            if(strlen($msg_var1) == 0)
            {
                $msg_var1 = $req_message_var1;
            }
        }
        echo $g_l10n->get(strtoupper($req_message_id), $msg_var1);
        break;
}

if($inlineView)
{
    echo '</div>
    </div>';
}
?>