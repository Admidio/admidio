<?php
/******************************************************************************
 * Grußkarte Funktionen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

require_once('../../system/classes/email.php');
require_once('../../system/classes/image.php');
require_once('../../system/classes/ubb_parser.php');

class FunctionClass
{
	public $nameRecipientString 		= "";
	public $emailRecipientString 		= "";
	public $yourMessageString 			= "";
	public $newMessageReceivedString 	= "";
	public $greetingCardFrom			= "";
	public $greetingCardString			= "";
	function __construct($g_l10n)
	{
		$this->nameRecipientString 			= $g_l10n->get("ECA_RECIPIENT_NAME");
		$this->emailRecipientString 		= $g_l10n->get("ECA_RECIPIENT_EMAIL");
		$this->yourMessageString 			= $g_l10n->get("SYS_MESSAGE");
		$this->newMessageReceivedString 	= $g_l10n->get("ECA_NEW_MESSAGE_RECEIVED");
		$this->greetingCardFrom				= $g_l10n->get("ECA_A_ECARD_FROM");
		$this->greetingCardString			= $g_l10n->get("ECA_GREETING_CARD");
		$this->sendToString					= $g_l10n->get("SYS_TO");	
		$this->emailString					= $g_l10n->get("SYS_EMAIL");	
	}
	// gibt ein Menue fuer die Einstellungen des Template aus
	// Uebergabe: 
	//          $data_array         .. Daten fuer die Einstellungen in einem Array
	//          $name_ecard_input   .. Name des Ecards inputs
	//          $width              .. die Groeße des Menues
	//          $first_value        .. der Standart Wert oder eingestellte Wert vom Benutzer
	//          $schowfont          .. wenn gesetzt bekommen die Menue Eintraege einen universellen font-style
	function getMenueSettings($data_array,$name_ecard_input,$first_value,$width,$schowfont)
	{
		echo  '<select size="1" onchange="ecardJS.getSetting(\''.$name_ecard_input.'\',this.value)" style="width:'.$width.'px;">';
		for($i=0; $i<count($data_array);$i++)
		{
			$name = "";
			if(!is_integer($data_array[$i]) && strpos($data_array[$i],'.tpl') > 0)
			{
				$name = ucfirst(preg_replace("/[_-]/"," ",str_replace(".tpl","",$data_array[$i])));
			}
			elseif(is_integer($data_array[$i]))
			{
				$name = $data_array[$i];
			}
			else if(strpos($data_array[$i],'.') === false)
			{
				$name = $data_array[$i];
			}
			if($name != "")
			{
				if (strcmp($data_array[$i],$first_value) == 0 && $schowfont != "true")
				{
					echo '<option value="'.$data_array[$i].'" selected=\'selected\'>'.$name.'</option>';
				}
				else if($schowfont != "true")
				{
					echo '<option value="'.$data_array[$i].'">'.$name.'</option>';
				}
				else if (strcmp($data_array[$i],$first_value) == 0)
				{
					echo '<option value="'.$data_array[$i].'" selected=\'selected\' style="font-family:'.$name.';">'.$name.'</option>';
				}
				else
				{
					echo '<option value="'.$data_array[$i].'" style="font-family:'.$name.';">'.$name.'</option>';
				}
			}
			
		}
		echo  '</select>';
		return '<input type="hidden" name="'.$name_ecard_input.'" value="'.$first_value.'" />';
	}
	// gibt ein Menue fuer die Einstellungen des Template aus
	// Uebergabe: 
	//          $data_array         .. Daten fuer die Einstellungen in einem Array
	//          $name_ecard_input   .. Name des Ecards inputs
	function getColorSettings($data_array,$name_ecard_input,$anz,$first_value)
	{
		echo  '<table border="0" cellpadding="1" cellspacing="1" summary="colorTable"><tr>';
		for($i=0; $i<count($data_array);$i++)
		{   
			if (!is_integer(($i+1)/$anz))
			{
				echo '<td style="height:20px; width:17px; background-color: '.$data_array[$i].'; cursor:pointer;" onclick="javascript: ecardJS.getSetting(\''.$name_ecard_input.'\',\''.$data_array[$i].'\');"></td>';
			}
			else
			{
				echo '<td style="height:20px; width:17px; background-color: '.$data_array[$i].'; cursor:pointer;" onclick="javascript: ecardJS.getSetting(\''.$name_ecard_input.'\',\''.$data_array[$i].'\');"></td>';
				if($i<count($data_array)-1)
				{
					echo '</tr><tr>';
				}
			}       
		}
		echo  '</tr></table>';
		return '<input type="hidden" name="'.$name_ecard_input.'" value="'.$first_value.'" />';
	}
	// gibt die ersten Einstellungen des Template aus
	// Uebergabe: 
	//          $first_value_array          .. Daten fuer die Einstellungen in einem Array
	function getFirstSettings($first_value_array)
	{
		foreach($first_value_array as $item)
		{
			if( $item[0] != "")
			{
				echo $item[0];
			}
			else
			{
				echo '<input type="hidden" name="'.$item[2].'" value="" />';
			}
		}
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
	//      $usr_id             ..  die User id
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
	function parseEcardTemplate($ecard,$ecard_data,$root_path,$usr_id,$empfaenger_name,$empfaenger_email,$bbcode_enable) 
	{   
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
		if(trim($ecard['message']) == '')
		{
		  $ecard['message']         = '< '.$this->yourMessageString.' >';
		}
		// Hier wird der Pfad zum Admidio Verzeichnis ersetzt
		$ecard_data = preg_replace ('/<%g_root_path%>/',            $root_path, $ecard_data);
		// Hier wird der Pfad zum aktuellen Template Verzeichnis ersetzt
		$ecard_data = preg_replace ('/<%theme_root_path%>/',        THEME_PATH, $ecard_data);
		// Hier wird die Style Eigenschaften ersetzt
		$ecard_data = preg_replace ('/<%ecard_font%>/',             $ecard['schriftart_name'], $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_font_size%>/',        $ecard['schrift_size'], $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_font_color%>/',       $ecard['schrift_farbe'], $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_font_bold%>/',        $ecard['schrift_style_bold'], $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_font_italic%>/',      $ecard['schrift_style_italic'], $ecard_data);
		// Hier wird der Sender Name, Email und Id ersetzt
		$ecard_data = preg_replace ('/<%ecard_sender_id%>/',        $usr_id, $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_sender_email%>/',     utf8_decode($ecard['email_sender']), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_sender_name%>/',      htmlentities(utf8_decode($ecard['name_sender'])), $ecard_data);
		// Hier wird der Empfaenger Name und Email ersetzt
		$ecard_data = preg_replace ('/<%ecard_reciepient_email%>/', utf8_decode($empfaenger_email), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_reciepient_name%>/',  htmlentities(utf8_decode($empfaenger_name)), $ecard_data);
		// Hier wird der Bildname ersetzt
		$ecard_data = preg_replace ('/<%ecard_image_name%>/',       $ecard['image_name'], $ecard_data);
		
		$ecard_data = preg_replace ('/<%ecard_greeting_card_from%>/'	, htmlentities(utf8_decode($this->greetingCardFrom)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_greeting_card_string%>/'	, htmlentities(utf8_decode($this->greetingCardString)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_to_string%>/'				, htmlentities(utf8_decode($this->sendToString)), $ecard_data);
		$ecard_data = preg_replace ('/<%ecard_email_string%>/'			, htmlentities(utf8_decode($this->emailString)), $ecard_data); 
		
	
		// Hier wird die Nachricht ersetzt
		if ($bbcode_enable)
		{
			$bbcode = new ubbParser();
			// BBCode wird geparsed, Zeilenumbrueche werden mit XBRX kodiert, um nach der htmlentities wieder zu Zeilenumbruechen decodiert zu werden
			$ecard_data =  preg_replace ('/<%ecard_message%>/',$bbcode->parse(nl2br(htmlentities(utf8_decode(preg_replace ("/\r?\n/", "\n",$ecard['message']))))), $ecard_data);
		}
		else
		{
			$ecard_data = preg_replace ('/<%ecard_message%>/',  nl2br(htmlentities(utf8_decode(preg_replace ("/\r?\n/", "\n", $ecard['message'])))), $ecard_data);
		}
		return $ecard_data;
	}
	// Diese Funktion ruft die Mail Klasse auf und uebergibt ihr die zu sendenden Daten
	// Uebergabe:
	//      $ecard              .. array mit allen Informationen die in den inputs der Form gespeichert sind
	//      $ecard_html_data    .. geparste Daten vom Template
	//      $sender_name        .. der Name des Senders
	//      $sender_email       .. die Email des Senders
	//      $empfaenger_name    .. der Name des Empfaengers
	//      $empfaenger_email   .. die Email des Empfaengers
	function sendEcard($ecard,$ecard_html_data,$empfaenger_name,$empfaenger_email,$cc_empfaenger, $photo_server_path) 
	{
		global $g_preferences;
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
					$image_sized->scale($g_preferences['ecard_card_picture_width'],$g_preferences['ecard_card_picture_height']);
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
		$email->setDataAsHtml();
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
	// Diese Funktion ueberprueft den uebergebenen String auf eine gueltige E-mail Addresse und gibt True oder False zurueck
	// Uebergabe
	//      $email  .. Die Email die geprueft werden soll
	function checkEmail($email) 
	{
		if (preg_match ("/(@.*@)|(\.\.)|(@\.)|(\.@)|(^\.)/", $email) || !preg_match ("/^.+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/", $email)) 
		{
			$mail_ok = false;
		} 
		else 
		{
			$mail_ok = true;
		}
		return $mail_ok;
	}
}
?>