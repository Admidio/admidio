<?php
/******************************************************************************
 * Grußkarte Funktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

require_once('../../system/classes/email.php');
require_once('../../system/classes/image.php');
require_once('../../libs/htmlawed/htmlawed.php');

class FunctionClass
{
	public $nameRecipientString 		= '';
	public $emailRecipientString 		= '';
	public $yourMessageString 			= '';
	public $newMessageReceivedString 	= '';
	public $greetingCardFrom			= '';
	public $greetingCardString			= '';

	function __construct($gL10n)
	{
		$this->nameRecipientString 			= $gL10n->get('ECA_RECIPIENT_NAME');
		$this->emailRecipientString 		= $gL10n->get('ECA_RECIPIENT_EMAIL');
		$this->yourMessageString 			= $gL10n->get('SYS_MESSAGE');
		$this->newMessageReceivedString 	= $gL10n->get('ECA_NEW_MESSAGE_RECEIVED');
		$this->greetingCardFrom				= $gL10n->get('ECA_A_ECARD_FROM');
		$this->greetingCardString			= $gL10n->get('ECA_GREETING_CARD');
		$this->sendToString					= $gL10n->get('SYS_TO');	
		$this->emailString					= $gL10n->get('SYS_EMAIL');	
	}
	
	// erstellt ein Javascript Template Array aus einem php Array 
	function createJSTemplateArray($data_array)
	{
		if( gettype($data_array) != "array")
			return '';
			
		$output = '[';
		$count = count($data_array);
		for($i = 0; $i < $count; ++$i)
		{
			$name = '';
			if(!is_integer($data_array[$i]) && strpos($data_array[$i],'.tpl') > 0)
			{
				$name = ucfirst(preg_replace("/[_-]/"," ",str_replace('.tpl','',$data_array[$i])));
			}
			elseif(is_integer($data_array[$i]))
			{
				$name = $data_array[$i];
			}
			else if(strpos($data_array[$i],'.') === false)
			{
				$name = $data_array[$i];
			}
			if($name != '')
			{
				//{'value', 'drop_text', 'drop_label'}
				$output .= ' ["'. $data_array[$i] .'","'. $name .'","'. $name.'"]';
				if( $i + 1 < $count )
					$output .= ',';
			}
		}
		return $output. ' ]';
	}

	function getCCRecipients($ecard,$max_cc_recipients)
	{
		$Versandliste = array();
		for($i=1;$i<=$max_cc_recipients;$i++)
		{
			if(isset($ecard['name_ccrecipient_'.$i]) != '' && isset($ecard['email_ccrecipient_'.$i]) != '')
			{
				array_push($Versandliste,array($ecard['name_ccrecipient_'.$i],$ecard['email_ccrecipient_'.$i]));
			}
		}
		return $Versandliste;
	}
	
	// oeffnet ein File und gibt alle Zeilen als Array zurueck
	// Uebergabe:
	//          $filepath .. Der Pfad zu dem File
	function getElementsFromFile($filepath)
	{
		$elementsFromFile = array();
		$list = fopen($filepath, 'r');
		while (!feof($list))
		{
			array_push($elementsFromFile,trim(fgets($list)));
		}
		return $elementsFromFile;   
	}
	
	function getfilenames($directory) 
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
	 
	/** Funktionen fuers sammeln,parsen und versenden der Informationen von der ecard_form **/
	
	// Diese Funktion holt alle Variablen ab und speichert sie in einem array
	function getVars() 
	{
	  global $_POST,$_GET;
	  foreach ($_POST as $key => $value) 
	  {
		global $$key;
		$$key = $value;
	  }
	  foreach ($_GET as $key => $value) 
	  {
		global $$key;
		$$key = $value;
	  }
	}
	// Diese Funktion holt das Template aus dem uebergebenen Verzeichnis und liefert die Daten und einen error state zurueck
	// Uebergabe:
	//      $template_name  .. der Name des Template
	//      $tmpl_folder    .. der Name des Ordner wo das Template vorhanden ist
	function getEcardTemplate($template_name,$tmpl_folder) 
	{
		$error = false;
		$file_data = '';
		$fpread = @fopen($tmpl_folder.$template_name, 'r');
		if (!$fpread) 
		{
		  $error = true;
		} 
		else 
		{
			while(! feof($fpread) ) 
			{
				$file_data .= fgets($fpread, 4096);
			}
			fclose($fpread);
		}
		return array($error,$file_data);
	}
	/*
	// Diese Funktion ersetzt alle im Template enthaltenen Platzhalter durch die dementsprechenden Informationen
	// Uebergabe:
	//      $ecard              ..  array mit allen Informationen die in den inputs der Form gespeichert sind
	//      $ecard_data         ..  geparste Information von dem Grußkarten Template
	//      $root_path          ..  der Pfad zu admidio Verzeichnis
	//      $user               ..  das User-Objekt (z.B. $gCurrentUser)
	//      $empfaenger_name    ..  der Name des Empfaengers
	//      $empfaenger_email   ..  die Email des Empfaengers
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
	function parseEcardTemplate($ecard,$ecardMessage,$ecard_data,$root_path,&$user,$empfaenger_name,$empfaenger_email) 
	{
        global $gCurrentUser;

		// Falls der Name des Empfaenger nicht vorhanden ist wird er fuer die Vorschau ersetzt
		if(strip_tags(trim($empfaenger_name)) == '')
		{
		  $empfaenger_name  = '< '.$this->nameRecipientString.' >';
		}
		// Falls die Email des Empfaenger nicht vorhanden ist wird sie fuer die Vorschau ersetzt
		if(strip_tags(trim($empfaenger_email)) == '')
		{
		  $empfaenger_email = '< '.$this->emailRecipientString.' >';
		}
		// Falls die Nachricht nicht vorhanden ist wird sie fuer die Vorschau ersetzt
		if(trim($ecardMessage) == '')
		{
		  $ecardMessage = '< '.$this->yourMessageString.' >';
		}
		// Hier wird der Pfad zum Admidio Verzeichnis ersetzt
		$ecard_data = preg_replace ('/<%g_root_path%>/',            $root_path, $ecard_data);
		// Hier wird der Pfad zum aktuellen Template Verzeichnis ersetzt
		$ecard_data = preg_replace ('/<%theme_root_path%>/',        THEME_PATH, $ecard_data);
		// Hier wird der Sender Name, Email und Id ersetzt
		$ecard_data = preg_replace ('/<%ecard_sender_id%>/',        $user->getValue('usr_id'), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_sender_email%>/',     $user->getValue('EMAIL'), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_sender_name%>/',      $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $ecard_data);
		// Hier wird der Empfaenger Name und Email ersetzt
		$ecard_data = preg_replace ('/<%ecard_reciepient_email%>/', htmlentities(utf8_decode($empfaenger_email)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_reciepient_name%>/',  htmlentities(utf8_decode($empfaenger_name)), $ecard_data);
		// Hier wird der Bildname ersetzt
		$ecard_data = preg_replace ('/<%ecard_image_name%>/',       $ecard['image_name'], $ecard_data);
		
		$ecard_data = preg_replace ('/<%ecard_greeting_card_from%>/'	, htmlentities(utf8_decode($this->greetingCardFrom)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_greeting_card_string%>/'	, htmlentities(utf8_decode($this->greetingCardString)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_to_string%>/'				, htmlentities(utf8_decode($this->sendToString)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_email_string%>/'			, htmlentities(utf8_decode($this->emailString)), $ecard_data); 
			
		// make html in description secure
		$ecardMessage = htmLawed(stripslashes($ecardMessage), array('safe' => 1));

		// Hier wird die Nachricht ersetzt
		$ecard_data = preg_replace ('/<%ecard_message%>/',  $ecardMessage, $ecard_data);

		return $ecard_data;
	}
	// Diese Funktion ruft die Mail Klasse auf und uebergibt ihr die zu sendenden Daten
	// Uebergabe:
	//      $ecard              .. array mit allen Informationen die in den inputs der Form gespeichert sind
	//      $ecard_html_data    .. geparste Daten vom Template
	//      $empfaenger_name    .. der Name des Empfaengers
	//      $empfaenger_email   .. die Email des Empfaengers
	//		$cc_emfaenger		.. eine Liste der CC-Empfaenger
	//		$photo_server_path  .. der Pfad wo die Bilder in der Grußkarte am Server liegen
	function sendEcard($ecard,$ecard_html_data,$empfaenger_name,$empfaenger_email,$cc_empfaenger, $photo_server_path) 
	{
		global $gPreferences;
		$img_photo_path = '';
	
		$email = new Email();
		$email->setSender($ecard['email_sender'],$ecard['name_sender']);
		$email->setSubject($this->newMessageReceivedString);
		$email->addRecipient($empfaenger_email,$empfaenger_name);
		for($i=0;$i<count($cc_empfaenger);$i++)
		{
			$email->addCopy($cc_empfaenger[$i][1],$cc_empfaenger[$i][0]);
		}
		
		// alle Bilder werden aus dem Template herausgeholt, damit diese als Anhang verschickt werden koennen
		if (preg_match_all("/(<img.*src=\")(.*)(\".*>)/Uim", $ecard_html_data, $matchArray)) 
		{
			$matchArray[0] = $this->deleteDoubleEntries($matchArray[0]);
			$matchArray[2] = $this->deleteDoubleEntries($matchArray[2]);
			for ($i=0; $i < count($matchArray[0]); ++$i) 
			{
				// anstelle der URL muss nun noch der Server-Pfad gesetzt werden
				$img_server_path = str_replace(THEME_PATH, THEME_SERVER_PATH, $matchArray[2][$i]);
				$img_server_path = str_replace($GLOBALS['g_root_path'], SERVER_PATH, $img_server_path);

				// wird das Bild aus photo_show.php generiert, dann den uebergebenen Pfad zum Bild einsetzen
				if(strpos($img_server_path, 'photo_show.php') !== false)
				{
					$img_server_path = $photo_server_path;
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
				
					$image_sized = new Image($photo_server_path);
					$image_sized->scale($gPreferences['ecard_card_picture_width'],$gPreferences['ecard_card_picture_height']);
					$image_sized->copyToFile(null, $img_server_path);
				}
	
				// Bild als Anhang an die Mail haengen
				if($img_name != 'none.jpg' && strlen($img_name) > 0)
				{
					$uid = md5(uniqid($img_name.time()));
					$email->addAttachment($img_server_path, $img_name, 'image/'.$img_type, 'inline', $uid);
					$ecard_html_data = str_replace($matchArray[2][$i],'cid:'.$uid,$ecard_html_data);
				}
			}
		}
		
		$email->setText($ecard_html_data);
		$email->sendDataAsHtml();
		$return_code = $email->sendEmail();
	
		// nun noch das von der Groesse angepasste Bild loeschen
		unlink($img_photo_path);
		return $return_code;
	}
	// Diese Funktion eleminiert in einem einfachen Array doppelte Einträge
	// Uebergabe
	//      $array  .. Das zu dursuchende Array()
	function deleteDoubleEntries($array)
	{
		$array = array_unique($array);
		$new_array = array();
		$i = 0;
		foreach($array as $item)
		{
			$new_array[$i] = $item;
			$i++;
		}
	
		return $new_array;
	}
}
?>