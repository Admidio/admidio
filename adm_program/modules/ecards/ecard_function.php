<?php
/******************************************************************************
 * Ecard functions
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

class FunctionClass
{
    public $nameRecipientString        = '';
    public $emailRecipientString        = '';
    public $yourMessageString            = '';
    public $newMessageReceivedString    = '';
    public $greetingCardFrom            = '';
    public $greetingCardString            = '';

    public function __construct($gL10n)
    {
        $this->nameRecipientString            = $gL10n->get('ECA_RECIPIENT_NAME');
        $this->emailRecipientString        = $gL10n->get('ECA_RECIPIENT_EMAIL');
        $this->yourMessageString            = $gL10n->get('SYS_MESSAGE');
        $this->newMessageReceivedString    = $gL10n->get('ECA_NEW_MESSAGE_RECEIVED');
        $this->greetingCardFrom                = $gL10n->get('ECA_A_ECARD_FROM');
        $this->greetingCardString            = $gL10n->get('ECA_GREETING_CARD');
        $this->sendToString                    = $gL10n->get('SYS_TO');
        $this->emailString                    = $gL10n->get('SYS_EMAIL');
    }

    public function getFileNames($directory)
    {
        $array_files    = array();
        $i              = 0;
        if($curdir = opendir($directory))
        {
            while($file = readdir($curdir))
            {
                if($file != '.' && $file != '..')
                {
                    $array_files[$i] = $file;
                    $i++;
                }
            }
        }
        closedir($curdir);
        return $array_files;
    }

    // Diese Funktion holt das Template aus dem uebergebenen Verzeichnis und liefert die Daten und einen error state zurueck
    // Uebergabe:
    //      $template_name  .. der Name des Template
    //      $tmpl_folder    .. der Name des Ordner wo das Template vorhanden ist
    public function getEcardTemplate($template_name, $tmpl_folder)
    {
        $file_data = '';
        $fpread = @fopen($tmpl_folder.$template_name, 'r');
        if (!$fpread)
        {
          return '';
        }
        else
        {
            while(! feof($fpread))
            {
                $file_data .= fgets($fpread, 4096);
            }
            fclose($fpread);
        }
        return $file_data;
    }

    /*
    // Diese Funktion ersetzt alle im Template enthaltenen Platzhalter durch die dementsprechenden Informationen
    // Uebergabe:
    //      $ecard            ..  array mit allen Informationen die in den inputs der Form gespeichert sind
    //      $ecard_data       ..  geparste Information von dem Grußkarten Template
    //      $recipientName    ..  der Name des Empfaengers
    //      $recipientEmail   ..  die Email des Empfaengers
    //
    // Ersetzt werden folgende Platzhalter
    //
    //      Admidio Pfad:           <%g_root_path%>
    //      Template Verzeichnis    <%template_root_path%>
    //      Style Eigenschaften:    <%ecard_font%>              <%ecard_font_size%>         <%ecard_font_color%> <%ecard_font_bold%> <%ecard_font_italic%>
    //      Empfaenger Daten:       <%ecard_reciepient_email%>  <%ecard_reciepient_name%>
    //      Sender Daten:           <%ecard_sender_id%>         <%ecard_sender_email%>      <%ecard_sender_name%>
    //      Bild Daten:             <%ecard_image_width%>       <%ecard_image_height%>      <%ecard_image_name%>
    //      Nachricht:              <%ecard_message%>
    */
    public function parseEcardTemplate($imageName, $ecardMessage, $ecard_data, $recipientName, $recipientEmail)
    {
        global $gCurrentUser, $g_root_path;

        // Falls der Name des Empfaenger nicht vorhanden ist wird er fuer die Vorschau ersetzt
        if(strip_tags(trim($recipientName)) == '')
        {
          $recipientName  = '< '.$this->nameRecipientString.' >';
        }

        // Falls die Email des Empfaenger nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if(strip_tags(trim($recipientEmail)) == '')
        {
          $recipientEmail = '< '.$this->emailRecipientString.' >';
        }

        // Falls die Nachricht nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if(trim($ecardMessage) == '')
        {
          $ecardMessage = '< '.$this->yourMessageString.' >';
        }

        $pregRepArray = array();

        // Hier wird der Pfad zum Admidio Verzeichnis ersetzt
        $pregRepArray["/<%g_root_path%>/"]                        = $g_root_path;
        // Hier wird der Pfad zum aktuellen Template Verzeichnis ersetzt
        $pregRepArray["/<%theme_root_path%>/"]                    = THEME_PATH;
        // Hier wird der Sender Name, Email und Id ersetzt
        $pregRepArray["/<%ecard_sender_id%>/"]                    = $gCurrentUser->getValue('usr_id');
        $pregRepArray["/<%ecard_sender_email%>/"]                = $gCurrentUser->getValue('EMAIL');
        $pregRepArray["/<%ecard_sender_name%>/"]                = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
        // Hier wird der Empfaenger Name und Email ersetzt
        $pregRepArray["/<%ecard_reciepient_email%>/"]        = htmlentities($recipientEmail, ENT_COMPAT, 'UTF-8');
        $pregRepArray["/<%ecard_reciepient_name%>/"]            = htmlentities($recipientName, ENT_COMPAT, 'UTF-8');
        // Hier wird der Bildname ersetzt
        $pregRepArray["/<%ecard_image_name%>/"]                = $imageName;

        $pregRepArray["/<%ecard_greeting_card_from%>/"]        = htmlentities($this->greetingCardFrom, ENT_COMPAT, 'UTF-8');
        $pregRepArray["/<%ecard_greeting_card_string%>/"]    = htmlentities($this->greetingCardString, ENT_COMPAT, 'UTF-8');
        $pregRepArray["/<%ecard_to_string%>/"]                    = htmlentities($this->sendToString, ENT_COMPAT, 'UTF-8');
        $pregRepArray["/<%ecard_email_string%>/"]                = htmlentities($this->emailString, ENT_COMPAT, 'UTF-8');

        // make html in description secure
        $ecardMessage = htmLawed(stripslashes($ecardMessage), array('safe' => 1));

        // Hier wird die Nachricht ersetzt
        $pregRepArray["/<%ecard_message%>/"]                    = $ecardMessage;

        $ecard_data = preg_replace(array_keys($pregRepArray), array_values($pregRepArray), $ecard_data);

        return $ecard_data;
    }

    // Diese Funktion ruft die Mail Klasse auf und uebergibt ihr die zu sendenden Daten
    // Uebergabe:
    //      $ecard            .. array mit allen Informationen die in den inputs der Form gespeichert sind
    //      $ecardHtmlData    .. geparste Daten vom Template
    //      $recipientName    .. der Name des Empfaengers
    //      $recipientEmail   .. die Email des Empfaengers
    //      $photoServerPath  .. der Pfad wo die Bilder in der Grußkarte am Server liegen
    public function sendEcard($senderName, $senderEmail, $ecardHtmlData, $recipientName, $recipientEmail, $photoServerPath)
    {
        global $gPreferences;
        $img_photo_path = '';
        $returnCode = true;

        $email = new Email();
        $email->setSender($senderEmail, $senderName);
        $email->setSubject($this->newMessageReceivedString);
        $email->addRecipient($recipientEmail, $recipientName);

        // alle Bilder werden aus dem Template herausgeholt, damit diese als Anhang verschickt werden koennen
        if (preg_match_all("/(<img.*src=\")(.*)(\".*>)/Uim", $ecardHtmlData, $matchArray))
        {
            //$matchArray[0] = $this->deleteDoubleEntries($matchArray[0]);
            //$matchArray[2] = $this->deleteDoubleEntries($matchArray[2]);
            $matchArray[0] = array_unique($matchArray[0]);
            $matchArray[2] = array_unique($matchArray[2]);

            for ($i=0; $i < count($matchArray[0]); ++$i)
            {
                // anstelle der URL muss nun noch der Server-Pfad gesetzt werden
                $img_server_path = str_replace(THEME_PATH, THEME_SERVER_PATH, $matchArray[2][$i]);
                $img_server_path = str_replace($GLOBALS['g_root_path'], SERVER_PATH, $img_server_path);

                // wird das Bild aus photo_show.php generiert, dann den uebergebenen Pfad zum Bild einsetzen
                if(strpos($img_server_path, 'photo_show.php') !== false)
                {
                    $img_server_path = $photoServerPath;
                }
                // Bildnamen und Typ ermitteln
                $img_name = substr(strrchr($img_server_path, '/'), 1);
                $img_type = substr(strrchr($img_name, '.'), 1);

                // das zu versendende eigentliche Bild, muss noch auf das entsprechende Format angepasst werden
                if(strpos($matchArray[2][$i], 'photo_show.php') !== false)
                {
                    $img_name = 'picture.'. $img_type;
                    $img_name_intern = substr(md5(uniqid($img_name.time())), 0, 8). '.'. $img_type;
                    $img_server_path = SERVER_PATH. '/adm_my_files/photos/'. $img_name_intern;
                    $img_photo_path  = $img_server_path;

                    $image_sized = new Image($photoServerPath);
                    $image_sized->scale($gPreferences['ecard_card_picture_width'], $gPreferences['ecard_card_picture_height']);
                    $image_sized->copyToFile(null, $img_server_path);
                }

                // Bild als Anhang an die Mail haengen
                if($img_name != 'none.jpg' && $img_name !== '')
                {
                    $uid = md5(uniqid($img_name.time()));
                    try
                    {
                        $email->AddEmbeddedImage($img_server_path, $uid, $img_name, $encoding = 'base64', 'image/'.$img_type);
                    }
                    catch (phpmailerException $e)
                    {
                       $returnCode = $e->errorMessage();
                    }
                    $ecardHtmlData = str_replace($matchArray[2][$i], 'cid:'.$uid, $ecardHtmlData);
                }
            }
        }

        $email->setText($ecardHtmlData);
        $email->sendDataAsHtml();

        if($returnCode==true)
        {
            $returnCode = $email->sendEmail();
        }

        // nun noch das von der Groesse angepasste Bild loeschen
        unlink($img_photo_path);
        return $returnCode;
    }
}
