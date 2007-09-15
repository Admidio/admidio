<?php
/******************************************************************************
 * Grußkarte Funktionen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
/****************** includes *************************************************/
 include('mail.class.php');
 
/****************** Funktionen für ecard_form ********************************/
 
// rechnet die propotionale Größe eines Bildes aus
// dh. wenn man ein Bild mit der max Auflösung 600x400 haben will
// übergibt mann der Funktion die max_w und max_h und bekommt die propotionale Größe zurück
function getPropotionalSize($src_w, $src_h, $max_w, $max_h)
{
	$return_val['width']=$src_w;
	$return_val['height']=$src_h;
	if($max_w < $src_w || $max_h < $src_h)
	{
		$return_val['width']=$max_w;
		$return_val['height']=$max_h;
		if($src_w >= $src_h)
		{ 
			$return_val['height'] = round(($max_w*$src_h)/$src_w);
		}
		else 
		{
			$return_val['width']  = ($max_h*$src_w)/$src_h;
		}
	}
	return $return_val;
}
// gibt ein Menü für die Einstellungen des Template aus
// Übergabe: 
// 			$data_array			.. Daten für die Einstellungen in einem Array
//			$name_ecard_input	.. Name des Ecards inputs
//			$width				.. die Größe des Menüs
//			$schowfont			.. wenn gesetzt bekommen die Menü Einträge einen universellen font-style
function getMenueSettings($data_array,$name_ecard_input,$width,$schowfont)
{
	$temp_data = "";
	echo  '<select size="1" onchange="getSetting(\''.$name_ecard_input.'\',this.value)" style="width:'.$width.'px;">';
	for($i=0; $i<count($data_array);$i++)
	{
		$temp_name = explode(".", $data_array[$i]);
		
		if ($i == 0 && $schowfont != "true")
		{
			echo '<option value="'.$data_array[$i].'" selected=\'selected\'>'.$temp_name[0].'</option>';
		}
		else if($schowfont != "true")
		{
			echo '<option value="'.$data_array[$i].'">'.$temp_name[0].'</option>';
		}
		else
		{
			echo '<option value="'.$data_array[$i].'" style="font-family:'.$temp_name[0].';">'.$temp_name[0].'</option>';
		}
		
	}
	echo  '</select>';
	return '<input type="hidden" name="'.$name_ecard_input.'" value="'.$data_array[0].'" />';
}
// gibt ein Menü für die Einstellungen des Template aus
// Übergabe: 
// 			$data_array			.. Daten für die Einstellungen in einem Array
//			$name_ecard_input	.. Name des Ecards inputs
function getColorSettings($data_array,$name_ecard_input,$anz)
{
	$temp_data = "";
	echo  '<table border="0" cellpadding="1" cellspacing="1" summary="colorTable"><tr>';
	for($i=0; $i<count($data_array);$i++)
	{
		if (!is_integer(($i+1)/$anz))
		{
		    echo '<td style="height:20px; width:17px; background-color: '.$data_array[$i].'; cursor:pointer;" onclick="javascript: getSetting(\''.$name_ecard_input.'\',\''.$data_array[$i].'\');"></td>';
		}
		else if( $i == 0 )
		{
			echo '<td style="height:20px; width:17px; background-color: '.$data_array[$i].'; cursor:pointer;" onclick="javascript: getSetting(\''.$name_ecard_input.'\',\''.$data_array[$i].'\');"></td>';
		}
		else
		{
			echo '<td style="height:20px; width:17px; background-color: '.$data_array[$i].'; cursor:pointer;" onclick="javascript: getSetting(\''.$name_ecard_input.'\',\''.$data_array[$i].'\');"></td></tr><tr>';
		}
		
	}
	echo  '</tr></table>';
	return '<input type="hidden" name="'.$name_ecard_input.'" value="'.$data_array[0].'" />';
}
// gibt die ersten Einstellungen des Template aus
// Übergabe: 
// 			$first_value_array			.. Daten für die Einstellungen in einem Array
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
 
/** Funktionen fürs sammeln,parsen und versenden der Informationen von der ecard_form **/

// Diese Funktion holt alle Variablen ab und speichert sie in einem array
function getVars() 
{
  global $HTTP_POST_VARS,$HTTP_GET_VARS;
  foreach ($HTTP_POST_VARS as $key => $value) 
  {
    global $$key;
    $$key = $value;
  }
  foreach ($HTTP_GET_VARS as $key => $value) 
  {
    global $$key;
    $$key = $value;
  }
}
// Diese Funktion holt das Template aus dem übergebenen Verzeichnis und liefert die Daten und einen error state zurück
// Übergabe:
//		$template_name	.. der Name des Template
//		$tmpl_folder	.. der Name des Ordner wo das Template vorhanden ist
function getEcardTemplate($template_name,$tmpl_folder) 
{
	$error = false;
	$file_data = "";
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
// Diese Funktion ersetzt alle im Template enthaltenen Platzhalter durch die dementsprechenden Informationen
// Übergabe:
//		$ecard				..	array mit allen Informationen die in den inputs der Form gespeichert sind
//		$ecard_data			..	geparste Information von dem Grußkarten Template
//		$root_path			..	der Pfad zu admidio Verzeichnis
//		$usr_id				..	die User id
//		$proportional_width	..	die proportionale Breite des Bildes für das Template
//		$propotional_height	..	die proportionale Höhe des Bildes für das Template
//
// Ersetzt werden folgende Platzhalter
//		
//		Admidio Pfad:			<%g_root_path%>	
//		Style Eigenschaften:	<%ecard_font%>				<%ecard_font_size%>			<%ecard_font_color%> <%ecard_font_bold%> <%ecard_font_italic%>
//		Empfänger Daten:		<%ecard_reciepient_email%>	<%ecard_reciepient_name%>
//		Sender Daten:			<%ecard_sender_id%>			<%ecard_sender_email%> 		<%ecard_sender_name%>
//		Bild Daten:				<%ecard_image_width%>		<%ecard_image_height%> 		<%ecard_image_name%>
//		Nachricht:				<%ecard_message%>
function parseEcardTemplate($ecard,$ecard_data,$root_path,$usr_id,$propotional_width,$propotional_height) 
{   
	// Falls der Name des Empfänger nicht vorhanden ist wird er für die Vorschau ersetzt
	if(strip_tags(trim($ecard["name_recepient"])) == "")
	{
	  $ecard["name_recepient"]	= "< Empf&auml;nger Name >";
	}
	// Falls die Email des Empfänger nicht vorhanden ist wird sie für die Vorschau ersetzt
	if(strip_tags(trim($ecard["email_recepient"])) == "")
	{
	  $ecard["email_recepient"]	= "< Empf&auml;nger E-Mail >";
	}
	// Falls die Nachricht nicht vorhanden ist wird sie für die Vorschau ersetzt
	if($ecard["message"] == "")
	{
	  $ecard["message"]			= "< Deine Nachricht >";
	}
	// Hier wird der Pfad zum Admidio Verzeichnis ersetzt
	$ecard_data = preg_replace ("/<%g_root_path%>/",			$root_path, $ecard_data);
	// Hier wird die Style Eigenschaften ersetzt
	$ecard_data = preg_replace ("/<%ecard_font%>/",				$ecard["schriftart_name"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_font_size%>/",		$ecard["schrift_size"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_font_color%>/",		$ecard["schrift_farbe"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_font_bold%>/",		$ecard["schrift_style_bold"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_font_italic%>/",		$ecard["schrift_style_italic"], $ecard_data);
	// Hier wird der Sender Name, Email und Id ersetzt
	$ecard_data = preg_replace ("/<%ecard_sender_id%>/",		$usr_id, $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_sender_email%>/",		$ecard["email_sender"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_sender_name%>/",		$ecard["name_sender"], $ecard_data);
	// Hier wird der Empfänger Name und Email ersetzt
	$ecard_data = preg_replace ("/<%ecard_reciepient_email%>/", $ecard["email_recepient"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_reciepient_name%>/", 	$ecard["name_recepient"], $ecard_data);
	// Hier wird die Bild Breite, Höhe und Name ersetzt
	$ecard_data = preg_replace ("/<%ecard_image_width%>/",		$propotional_width, $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_image_height%>/",		$propotional_height, $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_image_name%>/",		$ecard["image_name"], $ecard_data);
	// Hier wird die Nachricht ersetzt
	$ecard_data = preg_replace ("/<%ecard_message%>/", 			preg_replace ("/\r?\n/", "<br />\n", htmlspecialchars($ecard["message"])), $ecard_data);
    // Hier werden die Umlaute ersetzt
	$ecard_data = preg_replace ("/ü\ö\ä\Ü\Ö\Ä\ß/","/&uuml;\&ouml;\&auml;\&Uuml;\&Ouml;\&Auml;\&szlig;/", $ecard_data);
	// Die fertig geparsten Daten werden jetzt nurnoch als Return Wert zurückgeliefert
	return $ecard_data;
}
// Diese Funktion ruft die Mail Klasse auf und übergibt ihr die zu sendenden Daten
// Übergabe:
//		$ecard				.. array mit allen Informationen die in den inputs der Form gespeichert sind
//		$ecard_html_data	.. geparste Daten vom Template
//		$ecard_plain_data	.. plain Text der vom User in der DB eingestellt werden kann (er wird am Anfang im Body Bereich der Mail hinzugefügt)
function sendEcard($ecard,$ecard_html_data,$ecard_plain_data) 
{
	// Einstellungen für From To setzen
	$HMTLMail = new MailClass($ecard["email_recepient"],'Grußkarte von '.$ecard["name_sender"]);
	$HMTLMail->setFromName($ecard["name_sender"]);
	$HMTLMail->setFromAddr($ecard["email_sender"]);

	// Body Teil hinzufügen zur Mail
	$HMTLMail->addTextPlainBodyPart($ecard_plain_data);
	
	// Die HTML Grußkarte vorsichtshalber an die Mail anhängen darum muss vorerstmal ein File erstellt werden das dann später angehängt wird
	$Datei = "Grußkarte.html";
	$FilePointer = fopen($Datei, "w+");
	fwrite($FilePointer, $ecard_html_data);
	fclose($FilePointer);
	// Das File mit dem Inhalt der Grußkarte anhängen
	$HMTLMail->attachFile($Datei,"text/plain","attachment");
	unlink($Datei);
	// Bilder die im HTML Teil der Grußkarte gefunden werden, werden integriert 
	if (preg_match_all("/(<img.*src=\")(.*)(\".*>)/Uim", $ecard_html_data, $matchArray)) 
	{
		for ($i=0; $i < count($matchArray[0]); ++$i) 
		{	
			$tmp_ext  = substr(strrchr($matchArray[2][$i], '.'), 1);
			$tmp_img_name_array  = explode('/',$matchArray[2][$i]);
			$tmp_img_name = $tmp_img_name_array[count($tmp_img_name_array)-1];	
			if($tmp_img_name != "none.jpg" && $tmp_img_name != "")
			{
				$uid = md5(uniqid($tmp_img_name.time()));
				$HMTLMail->attachFile($matchArray[2][$i],"image/".$tmp_ext."","inline","",$uid.".".$tmp_ext);
				$ecard_html_data = str_replace($matchArray[2][$i],"cid:".$uid.".".$tmp_ext,$ecard_html_data);
			}
		}
	}
	// Body Teil hinzufügen zur Mail
	$HMTLMail->addHTMLBodyPart($ecard_html_data);
	
	// Build and send the mail
	$result = $HMTLMail->BuildAndSendMessage();
	return $result;
}
// Diese Funktion überprüft den übergebenen String auf eine gültige E-mail Addresse und gibt True oder False zurück
// Übergabe
//		$email	.. Die Email die geprüft werden soll
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
?>