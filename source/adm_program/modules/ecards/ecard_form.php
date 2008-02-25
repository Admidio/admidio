<?php
/******************************************************************************
 * Grußkarte Form
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:		id der Veranstaltung deren Bilder angezeigt werden sollen
 * photo:       Name des Bildes ohne(.jpg) spaeter -> (admidio/adm_my_files/photos/<* Gallery *>/$_GET['photo'].jpg)
 * usr_id:		Die Benutzer id an dem die Grußkarte gesendet werden soll
 *
 *****************************************************************************/

require_once("../../system/photo_event_class.php");
require_once("../../system/common.php");
require_once("../photos/photo_function.php");
require_once("ecard_function.php");


$email_versand_liste		= array(); // Array wo alle Empfaenger aufgelistet werden (jedoch keine zusaetzlichen);
$email_versand_liste_all	= array(); // Array wo alle Empfaenger aufgelistet werden (inklusive zusaetzlichen);
$error_msg					= "";
$font_sizes 				= array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"); 
$font_colors 				= getElementsFromFile('../../system/schriftfarben.txt');  
$fonts 						= getElementsFromFile('../../system/schriftarten.txt');
$templates 					= getfilenames(THEME_SERVER_PATH. "/ecard_templates");
$msg_error_1				= "ecard_send_error";
$msg_error_2 				= "ecard_feld_error";

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
// pruefen ob User eingeloggt ist
if(!$g_valid_login)
{
 $g_message->show("invalid");
} 
//ID Pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]))
{
    $pho_id = $_GET["pho_id"];
}
else 
{
    $pho_id = NULL;
}

unset($_SESSION['photo_event_request']);

//Wurde keine Veranstaltung uebergeben kann das Navigationsstack zurückgesetzt werden
if ($pho_id == NULL)
{
    $_SESSION['navigation']->clear();
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_event']) && $_SESSION['photo_event']->getValue("pho_id") == $pho_id)
{
    $photo_event =& $_SESSION['photo_event'];
    $photo_event->db =& $g_db;
}
else
{
    // einlesen der Veranstaltung falls noch nicht in Session gespeichert
    $photo_event = new PhotoEvent($g_db);
    if($pho_id > 0)
    {
        $photo_event->getPhotoEvent($pho_id);
    }

    $_SESSION['photo_event'] =& $photo_event;
}

// pruefen, ob Veranstaltung zur aktuellen Organisation gehoert
if($pho_id > 0 && $photo_event->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("invalid");
} 


if ($g_valid_login && !isValidEmailAddress($g_current_user->getValue("E-Mail")))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->addVariableContent("$g_root_path/adm_program/modules/profile/profile.php", 1, false);
    $g_message->show("profile_mail");
}
if(!isset($_GET["photo"]))
{
    $g_message->show("invalid");
}

if (isset($_GET["usr_id"]))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_valid_login)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show("invalid");
    }

    if (is_numeric($_GET["usr_id"]) == false)
    {
        $g_message->show("invalid");
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden    
    if((  $g_current_user->editUser() == false
       && isMember($user->getValue("usr_id")) == false)
    || strlen($user->getValue("usr_id")) == 0 )
    {
        $g_message->show("usrid_not_found");
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue("E-Mail")))
    {
        $g_message->show("usrmail_not_found");
    }

    $user_email = $user->getValue("E-Mail");
	$user_name  = $user->getValue("Vorname")." ".$user->getValue("Nachname");
}

$popup_height = $g_preferences['photo_show_height']+210;
$popup_width  = $g_preferences['photo_show_width']+70;
$bild         = $_REQUEST['photo'];

// Wenn der übergebene Bildernamen und die daszugehörige Photogallerie Id
// gültig ist dann wird der komplete Pfad für das Bild generiert
if(is_numeric($bild) && isset($_GET['pho_id']))
{
    $ordner_foto		= "/adm_my_files/photos/".$photo_event->getValue("pho_begin")."_".$photo_event->getValue("pho_id");
    $ordner_url			= $g_root_path.$ordner_foto;
    $bild_url			= "".$ordner_url."/".$_REQUEST['photo'].".jpg";
}
// Wenn nur der Bildernamen übergeben wird ist die Übergabe ungültig
if(is_numeric($bild) && !isset($_GET['pho_id']))
{
	$g_message->show("invalid");
}
// Wenn weder die Übergabe des Bildes noch die Photogallery id nummerisch sind -> ungültiger Aufruf
if(!is_numeric($bild) || !is_numeric($_GET['pho_id']))
{
	$g_message->show("invalid");
}
// Wenn ein Bilderpfad generiert worden ist dann können die Proportionalen Größen berechnet werden
if(isset($bild_url))
{
	list($width, $height)	= getimagesize($bild_url);
	$propotional_size_card  = array();
	$propotional_size_view	= array();
	$propotional_size_card	= getPropotionalSize($width, $height, $g_preferences['ecard_card_picture_width'], $g_preferences['ecard_card_picture_height']);
	$propotional_size_view	= getPropotionalSize($width, $height, $g_preferences['ecard_view_width'], $g_preferences['ecard_view_height']);
}

// ruf die Funktion auf die alle Post und Get Variablen parsed
getVars();
$ecard_send = false;
// Wenn versucht wird die Grußkarte zu versenden werden die notwendigen FElder geprüft und wenn alles okay ist wird das Template geparsed und die Grußkarte weggeschickt
if (! empty($submit_action)) 
{
	// Wenn die Felder Name E-mail von dem Empaenger und Sender nicht leer sind
    if ( checkEmail($ecard["email_recipient"]) && checkEmail($ecard["email_sender"]) 
	&& ($ecard["email_recipient"] != "") && ($ecard["name_sender"] != "") )    
	{
		// Wenn die Nachricht größer ist als die maximal Laenge wird sie zurückgestutzt
	    if (strlen($ecard["message"]) > $g_preferences['ecard_text_length']) 
		{
	        $ecard["message"] = substr($ecard["message"],0,$g_preferences['ecard_text_length']-1);
	    }
		// Template wird geholt
		list($error,$ecard_data_to_parse) = getEcardTemplate($ecard["template_name"],THEME_SERVER_PATH. "/ecard_templates/");
		// Wenn es einen Error gibt ihn ausgeben
	    if ($error) 
	    {
		    $error_msg = $msg_error_1;
	    } 
		// Wenn nicht dann die Grußkarte versuchen zu versenden
	    else 
	    {
			// Es wird geprüft ob der Benutzer der ganzen Rolle eine Grußkarte schicken will
			$rolle = str_replace(array("Rolle_","@rolle.com"),"",$ecard["email_recipient"]);
			// Wenn nicht dann Name und Email des Empfaengers zur versand Liste hinzufügen
			if(!is_numeric($rolle))
			{
				array_push($email_versand_liste,array($ecard["name_recipient"],$ecard["email_recipient"]));
				$email_versand_liste_all = array_merge($email_versand_liste,getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']));
				$ecard_html_data = parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$propotional_size_card['width'],$propotional_size_card['height'],$ecard["name_recipient"],$ecard["email_recipient"]);
				$result = sendEcard($ecard,$ecard_html_data,$ecard["name_recipient"],$ecard["email_recipient"],$email_versand_liste_all);
				// Wenn die Grußkarte erfolgreich gesendet wurde 
				if ($result) 
				{
					$ecard_send = true;
				} 
				// Wenn nicht dann die dementsprechende Error Nachricht ausgeben
				else 
				{
					$error_msg = $msg_error_1;
				}
			}
			// Wenn schon dann alle Namen und die duzugehörigen Emails auslesen und in die versand Liste hinzufügen
			else
			{
				$sql = "SELECT first_name.usd_value as first_name, last_name.usd_value as last_name, 
                     email.usd_value as email, rol_name
                FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               RIGHT JOIN ". TBL_USER_DATA. " as email
                  ON email.usd_usr_id = usr_id
                 AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                 AND LENGTH(email.usd_value) > 0
                LEFT JOIN ". TBL_USER_DATA. " as last_name
                  ON last_name.usd_usr_id = usr_id
                 AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                LEFT JOIN ". TBL_USER_DATA. " as first_name
                  ON first_name.usd_usr_id = usr_id
                 AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
               WHERE rol_id            = ". $rolle. "
                 AND rol_cat_id        = cat_id
                 AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_valid         = 1 
				AND email.usd_usr_id = email.usd_usr_id
				ORDER BY last_name, first_name";
		
				$result 	  		= $g_db->query($sql);
				$firstvalue_name 	= "";
				$firstvalue_email 	= "";
				$i	= 0 ;
				while ($row = $g_db->fetch_object($result))
				{
					if($i<1)
					{
						$firstvalue_name  = "Gruppe: \"".$row->rol_name."\"";
						$firstvalue_email = "-";
						
					}
					array_push($email_versand_liste,array("".$row->first_name." ".$row->last_name."",$row->email));
					$i++;
				}
				$email_versand_liste_all = array_merge($email_versand_liste,getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']));
				$ecard_html_data = parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$propotional_size_card['width'],$propotional_size_card['height'],$firstvalue_name,$firstvalue_email);
				$result = sendEcard($ecard,$ecard_html_data,$firstvalue_name,$firstvalue_email,$email_versand_liste_all);
				// Wenn die Grußkarte erfolgreich gesendet wurde 
				if ($result) 
				{
					$ecard_send = true;
				} 
				// Wenn nicht dann die dementsprechende Error Nachricht ausgeben
				else 
				{
					$error_msg = $msg_error_1;
				}
				
			}
	   }
	}
	// Wenn die Felder leer sind oder ungültig dann eine dementsprechente Error Nachricht ausgeben
	else 
	{
        $error_msg = $msg_error_2;
	}
} 
// Wenn noch keine Anfrage zum versenden der Grußkarte vorhanden ist das Grußkarten Bild setzten
else 
{
    $ecard["image_name"] = "$g_root_path/adm_program/modules/photos/photo_show.php?pho_id=".$pho_id."&amp;pic_nr=".$photo."&amp;pho_begin=".$photo_event->getValue("pho_begin")."&amp;scal=".$propotional_size_card['height']."&amp;side=y";
}

/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
if(! empty($submit_action))
{
    $g_layout['title'] = "Grußkarte wegschicken";
}
else
{
    $g_layout['title'] = "Grußkarte bearbeiten";
}
//Lightbox-Mode
$g_layout['header'] = "";
if($g_preferences['photo_show_mode']==1)
{
    $g_layout['header'] .= "
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/prototype.js\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/lightbox/lightbox.js\"></script>
        <link rel=\"stylesheet\" href=\"".THEME_PATH."/css/photos.css\" type=\"text/css\" media=\"screen\" />";
}
$javascript='
    <script language="javascript" type="text/javascript">
	    var basedropdiv = \'basedropdownmenu\';
		var dropdiv = \'dropdownmenu\';
		var externdiv = \'extern\';
		var switchdiv = \'externSwitch\';
		var max_recipients = '.$g_preferences['ecard_cc_recipients'].';
		var now_recipients = 0;
        function popup_win(theURL,winName,winOptions) 
		{
             win = window.open(theURL,winName,winOptions);
             win.focus();
        }
        function sendEcard() 
        {
            if (check()) 
            { 
                document.ecard_form.action                 = "'.$HTTP_SERVER_VARS["PHP_SELF"].'?'.$_SERVER['QUERY_STRING'].'";
                document.ecard_form.target                 = "_self";
                document.ecard_form["submit_action"].value = "send";
                document.ecard_form.submit(); 
            }
            else
            {
                document.ecard_form["submit_action"].value = "";
            }
        } 
        function check() 
		{
            var error         = false;
            var error_message = "Du hast die folgenden, für die\nGrußkarte notwendigen Eingabefelder\nnicht bzw. nicht richtig ausgefüllt:\n\n";

            if (document.ecard_form["ecard[name_sender]"] && document.ecard_form["ecard[name_sender]"].value == "") 
			{
                error = true;
                error_message += "- Name des Absenders\n";
            } 
 
            if (document.ecard_form["ecard[email_sender]"] && (document.ecard_form["ecard[email_sender]"].value == "") || 
               (echeck(document.ecard_form["ecard[email_sender]"].value) == false)) 
			{
                error = true;
                error_message += "- E-Mail des Absenders\n";
            }
  
            if (document.ecard_form["ecard[name_recipient]"].value == "" || document.ecard_form["ecard[name_recipient]"].value == "< Empfänger Name >") 
			{
                error = true;
                error_message += "- Name des Empfängers\n";
            } 
            if ((document.ecard_form["ecard[email_recipient]"].value == "") || 
               (echeck(document.ecard_form["ecard[email_recipient]"].value) == false)) 
			{
                error = true;
                error_message += "- E-Mail des Empfängers\n";
        	}
        	if (document.ecard_form["ecard[message]"].value == "") 
			{
				error = true;
				error_message += "- Eine Nachricht\n";
			}
			for(var i=1; i <= now_recipients; i++)
			{
				var namedoc = document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"];	
				var emaildoc = 	document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"];
				var message = "";
				var goterror = false;		
				if(namedoc)
				{
					if(namedoc.value == "")
					{
						message += " - Name des "+[i]+". CC - Empfängers \n";
						error = true;
						goterror = true;
					}
				}
				if(emaildoc)
				{
					if(emaildoc.value == "" || !echeck(emaildoc.value))
					{
						message += " - E-Mail des "+[i]+". CC - Empfängers \n";
						error = true;
						goterror = true;
					}
				}
				if(goterror && i==1)
				{
					error_message += "\nCC - Empfänger\n-------------------------------\n"+message;
				}
				else if(goterror)
				{
					error_message += "-------------------------------\n"+message;
				}
			}
			if (error) 
			{
				error_message += "\n\nBitte füll die genannten Eingabefelder\nvollständig aus und klick dann erneut\nauf \'Abschicken\'.";
				alert(error_message);
				return false;  // Formular wird nicht abgeschickt.
			} 
			else 
			{
				return true;  // Formular wird abgeschickt.
			}
			return false;
		} // Ende function check()
		function echeck(str) 
		{
			var at="@"
			var dot="."
			var lat=str.indexOf(at)
			var lstr=str.length
			var ldot=str.indexOf(dot)
			if (str.indexOf(at)==-1){
			return false
			}
			
			if (str.indexOf(at)==-1 || str.indexOf(at)==0 || str.indexOf(at)==lstr){
			return false
			}
			
			if (str.indexOf(dot)==-1 || str.indexOf(dot)==0 || str.indexOf(dot)==lstr){
			return false
			}
			
			if (str.indexOf(at,(lat+1))!=-1){
			return false
			}
			
			if (str.substring(lat-1,lat)==dot || str.substring(lat+1,lat+2)==dot){
			return false
			}
			
			if (str.indexOf(dot,(lat+2))==-1){
			return false
			}
			
			if (str.indexOf(" ")!=-1){
			return false
			}
			
			return true					
		}
		function makePreview() 
		{
			document.ecard_form.action = "ecard_preview.php?width='.$propotional_size_card['width'].'&height='.$propotional_size_card['height'].'";
			popup_win(\'\',\'ecard_preview\',\'resizable=yes,scrollbars=yes,width=1024,height=1024\');
			document.ecard_form.target = "ecard_preview";
			document.ecard_form.submit();
		}
        function blendout(id)
		{
		    if(document.getElementById(id).value == "< Empfänger Name >" || document.getElementById(id).value == "< Empfänger E-Mail >")
			{
				document.getElementById(id).value = "";
			}
		}
		function blendin(id,type)
		{
		    if(document.getElementById(id).value == "" && type == 1)
			{
				document.getElementById(id).value = "< Empfänger Name >";
			}
			else if(document.getElementById(id).value == "" && type == 2)
			{
				document.getElementById(id).value = "< Empfänger E-Mail >";
				document.getElementById(id).style.color = "black";
				document.getElementById(\'Menue\').style.height = "49px";
				document.getElementById(\'wrong\').style.display = "none";
				document.getElementById(\'wrong\').innerHTML = "";
			}
			else if(document.getElementById(id).value != "" && document.getElementById(id).value != "< Empfänger E-Mail >"&& type == 2)
			{
				if(!echeck(document.getElementById(id).value))
				{
					document.getElementById(id).style.color = "red";
					document.getElementById(\'wrong\').style.display = "block";
					document.getElementById(\'Menue\').style.height = "75px";
					document.getElementById(\'wrong\').innerHTML = "E-mail Adresse scheint falsch zu sein!";
				}
				else
				{
					document.getElementById(id).style.color = "black";
					document.getElementById(\'Menue\').style.height = "49px";
					document.getElementById(\'wrong\').style.display = "none";
					document.getElementById(\'wrong\').innerHTML = "";
				}
			}
		}
		function countMax() 
		{
			max  = '.$g_preferences['ecard_text_length'].';
			wert = max - document.ecard_form["ecard[message]"].value.length;
			if(document.ecard_form["ecard[message]"].value.length > max)
			{
				var txtvalue = document.ecard_form["ecard[message]"].value;
				document.ecard_form["ecard[message]"].value = txtvalue.substr(0, max);
			}
			if (wert < 0) 
			{
				alert("Die Nachricht darf maximal " + max + " Zeichen lang sein.!");
				wert = 0;
				document.ecard_form["ecard[message]"].value = document.ecard_form["ecard[message]"].value.substring(0,max);
				document.getElementById(\'counter\').innerHTML = \'<b>\' + wert + \'<\/b>\';
				wert = 0;
			} 
			else 
			{
			    var zwprodukt = max - document.ecard_form["ecard[message]"].value.length;
				document.getElementById(\'counter\').innerHTML = \'<b>\' + zwprodukt + \'<\/b>\';
			}
		} // Ende function countMax()

		function macheRequest(seite,divId)
		{
			var xmlHttp;
			try
			{
				// Firefox, Opera 8.0+, Safari
				xmlHttp=new XMLHttpRequest();
			}
			catch (e)
			{
				// Internet Explorer
				try
				{
					xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
				}
				catch (e)
				{
					try
					{
						xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
					}
					catch (e)
					{
						alert("Your browser does not support AJAX!");
						return false;
					}
				}
			}
			xmlHttp.onreadystatechange=function()
			{
				if(xmlHttp.readyState==4)
				{
					document.getElementById(divId).innerHTML = xmlHttp.responseText;
				}
			}
			xmlHttp.open("GET",seite,true);
			xmlHttp.send(null);
		}
		function getMenu()
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?base=1\' , \'basedropdownmenu\' );
			
		}
		function getMenuRecepientName()
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?rol_id=\'+ document.ecard_form.rol_id.value , \'dropdownmenu\' );
		}
		function getMenuRecepientNameEmail(usr_id)
		{
			macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=\'+ usr_id + \'&rol_id=\'+ document.ecard_form.rol_id.value, \'dropdownmenu\' );
			document.getElementById(externdiv).innerHTML = "&nbsp;";
		}
		function saveData()
		{
			var savedata = new Array();
			for(var i=1; i <= now_recipients; i++)
			{
				var namedoc = document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"];	
				var emaildoc = 	document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"];
				if(namedoc)
				{	
					namedoc = document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"].value;
				}
				else
				{
					namedoc = "";
				}
				if(emaildoc)
				{
					emaildoc = 	document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"].value;
				}
				else
				{
					emaildoc = "";
				}
				savedata[i] = new Array();
				savedata[i][0] = emaildoc;
				savedata[i][1]  = namedoc;
			}
			return savedata;
		}
		function restoreSavedData(saved_data)
		{
			var i = 0;
			for (var i = 0; i < saved_data.length; i++)
			{				
				var namedoc = document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"];	
				var emaildoc = 	document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"];
				if(emaildoc)
				{
					emaildoc.value = saved_data[i][0];
				}
				if(namedoc)
				{	
					namedoc.value = saved_data[i][1];
				}
			}
			saved_data = "";
		}	
		function addRecipient()
		{
			if (now_recipients < max_recipients)
			{
				now_recipients++;
				var data	= \'<div id="\'+ [now_recipients] +\'">\';
				data += \'<table id="table_\'+ [now_recipients] +\'" border="0" summary="data\'+ [now_recipients] +\'">\';
				data += \'<tr>\';	
				data += \'<td style="width:30px;" align="right">\'+ [now_recipients] +\'. <\/td>\';			
				data += \'<td style="width:150px; padding-left:10px;"><input name="ecard[name_ccrecipient_\'+ [now_recipients] +\']" size="15" maxlength="50" style="width: 150px;" value="" type="text" /><\/td>\';    
				data += \'<td style="width:150px; padding-left:10px;"><input name="ecard[email_ccrecipient_\'+ [now_recipients] +\']" size="15" maxlength="50" style="width: 150px;" value="" type="text" /><\/td>\';    
				data += \'<\/tr><\/table>\';
				data += \'<\/div>\';
				var saved_data = new Array();
				saved_data = saveData();
				document.getElementById(\'ccrecipientContainer\').innerHTML += data ;
				restoreSavedData(saved_data);
				saved_data = "";
				if (now_recipients > 0)
				{
					document.getElementById(\'btn_del\').style.display = "block";
					document.getElementById(\'btn_delall\').style.display = "block";
					document.getElementById(\'btn_delContent\').style.display = "block";
					document.getElementById(\'moreRecipient\').style.display = "block";
					document.getElementById(\'getmoreRecipient\').innerHTML = "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">Keine weiteren Empf.<\/a>";
				}	
			}
			if (now_recipients+1 > max_recipients)
			{
				document.getElementById(\'btn_add\').disabled = true;
			}
		
		}
		function delContent()
		{
			var anzrecipients = now_recipients;
			var x = window.confirm("Bist du sicher das du den Inhalt von allen löschen willst?")
			if (x)
			{
				for(var i=1; i <= now_recipients; i++)
				{
					var namedoc = document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"];	
					var emaildoc = 	document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"];
					if(namedoc)
					{	
						document.ecard_form["ecard[name_ccrecipient_"+[i]+"]"].value = "";
					}
					if(emaildoc)
					{
						document.ecard_form["ecard[email_ccrecipient_"+[i]+"]"].value = "";
					}
				}
			}
		}
		function delRecipient()
		{	
			if (now_recipients > 0)
			{	
				
				var d = document.getElementById(\'ccrecipientContainer\');
				var olddiv = document.getElementById(now_recipients);
				d.removeChild(olddiv);
				now_recipients--;
			}
			else
			{
				now_recipients = 0;
			}
			if (now_recipients == 0)
			{
				if(document.getElementById(\'getmoreRecipient\').innerHTML == "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">Keine weiteren Empf.<\/a>")
				{
					showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');
				}
				document.getElementById(\'btn_del\').style.display = "none";
				document.getElementById(\'btn_delall\').style.display = "none";
				document.getElementById(\'btn_delContent\').style.display = "none";
				document.getElementById(\'moreRecipient\').style.display = "none";
				document.getElementById(\'getmoreRecipient\').innerHTML = "<a href=\"javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');\">Mehr Empfänger<\/a>";
			}
			
			if (now_recipients <= max_recipients)
			{
				document.getElementById(\'btn_add\').disabled = false;
			}
		
		}
		function delAllRecipients(t)
		{
			var anzrecipients = now_recipients;
			if(!t)
			{
				var x = window.confirm("Bist du sicher das du alle löschen willst?")
			}
			if (x || t)
			{
				for (var i = 0; i < anzrecipients; i++)
				{
					delRecipient();
				}
			}
		}
		function getTextStyle(textdiv)
		{
		 	var schrift_size = document.ecard_form["ecard[schrift_size]"].value;
			var schrift = document.ecard_form["ecard[schriftart_name]"].value;
			var schrift_farbe = document.ecard_form["ecard[schrift_farbe]"].value;
			var schrift_bold = "";
			var schrift_italic = "";
			if(document.ecard_form.Bold.checked)
			{
				schrift_bold = "bold"
			}
			if(document.ecard_form.Italic.checked)
			{
				schrift_italic = "italic";
			}
			var schrift_farbe = document.ecard_form["ecard[schrift_farbe]"].value;
			var schrift_farbe = document.ecard_form["ecard[schrift_farbe]"].value;
			document.getElementById(textdiv).style.font = schrift_bold + \' \'+ schrift_italic + \' \'+ schrift_size + \'px \'+schrift;
			document.getElementById(textdiv).style.color = schrift_farbe;	
		}
		function getSetting(name,input_value)
		{		
			document.ecard_form[name].value = input_value;	
			getTextStyle(\'Nachricht\');	
		}
		function showHideMoreRecipient(divLayer,divMenu)
		{
			if(document.getElementById(divLayer).style.display == "none")
			{
				document.getElementById(divLayer).style.display = "block";
				document.getElementById(divMenu).innerHTML = "<a href=\"javascript:showHideMoreRecipient(divLayer,divMenu);\">Keine weiteren Empf.<\/a>";
				addRecipient();
			}
			else
			{
				document.getElementById(divLayer).style.display = "none";
				document.getElementById(divMenu).innerHTML = "<a href=\"javascript:showHideMoreRecipient(divLayer,divMenu);\">Mehr Empfänger<\/a>";
				delAllRecipients(\'ja\');
			}
		}
		function showHideMoreSettings(divLayerSetting,divMenuSetting)
		{
			if(document.getElementById(divLayerSetting).style.display == "none")
			{
				document.getElementById(divLayerSetting).style.display = "block";
				document.getElementById(divMenuSetting).innerHTML = "<a href=\"javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');\">Einstellungen ausblenden<\/a>";
				window.scrollBy(0,5000);
			}
			else
			{
				document.getElementById(divLayerSetting).style.display = "none";
				document.getElementById(divMenuSetting).innerHTML = "<a href=\"javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');\">Einstellungen einblenden<\/a>";
			}
		}
		function getExtern()
		{
		    if(document.getElementById(basedropdiv).style.display == "none")
			{
				document.getElementById(basedropdiv).style.display = \'block\';
				document.getElementById(dropdiv).style.display = \'block\';
				document.getElementById(externdiv).style.display = \'none\';
				document.getElementById(externdiv).innerHTML = \'<input type="hidden" name="ecard[email_recipient]" value="< Empfänger E-Mail >" /><input type="hidden" name="ecard[name_recipient]"  value="< Empfänger Name >" />\';
				getMenu();
				document.getElementById(switchdiv).innerHTML = \'<a href="javascript:getExtern();">externer Empfänger<\/a>\';
			}
			else if(document.getElementById(basedropdiv).style.display == "block") 
			{
			    macheRequest(\''.$g_root_path.'/adm_program/modules/ecards/ecard_drawdropmenue.php?usrid=extern\', \'extern\' );
				document.getElementById(basedropdiv).style.display = \'none\';
				document.getElementById(dropdiv).style.display = \'none\';
				document.getElementById(externdiv).style.display = \'block\';
				document.getElementById(basedropdiv).innerHTML  = "&nbsp;";
				document.getElementById(dropdiv).innerHTML  = "&nbsp;";
				document.getElementById(switchdiv).innerHTML = \'<a href="javascript:getExtern();">interner Empfänger<\/a>\';
			}
			
			if(document.getElementById(\'wrong\'))
			{
				document.getElementById(\'wrong\').style.display = "none";
				document.getElementById(\'wrong\').innerHTML = "";
				document.getElementById(\'Menue\').style.height = "49px";
			}
		}
	</script>';
$g_layout['header'] .= $javascript;



//Photomodulspezifische CSS laden
$g_layout["header"] = $g_layout['header']."<link rel=\"stylesheet\" href=\"". THEME_PATH. "/photos.css\" type=\"text/css\" media=\"screen\" />";
 

require(THEME_SERVER_PATH. "/overall_header.php");

echo '

<div class="formLayout" id="profile_form">
<noscript>
	<div align="center">
		<div style="background-image: url(\''.THEME_PATH.'/images/error.png\'); 
					background-repeat: no-repeat;
					background-position: 5px 5px;
					border:1px solid #ccc; 
					padding:5px;
					background-color: #FFFFE0; 
					padding-left: 28px;
					text-align:left;">
		 Um eine Grußkarte versenden zu können wird Javascript benötigt!<br/>
		 Bitte aktiviere Javascript um eine Grußkarte versenden zu können! 
		 </div>
	</div>
</noscript>
    <div class="formHead">'. $g_layout['title']. '</div>
	<div class="formBody">
	<div>';
if (empty($submit_action))
{   
	 //Popup-Mode
	if($g_preferences['photo_show_mode']==0)
	{
		echo "<img onclick=\"window.open('$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=".$_REQUEST['photo']."&pho_id=".$_REQUEST['pho_id']."','msg','height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" 
			 src=\"$g_root_path/adm_program/modules/photos/photo_show.php?pho_id=".$pho_id."&amp;pic_nr=".$photo."&amp;pho_begin=".$photo_event->getValue("pho_begin")."&amp;scal=".$propotional_size_view['height']."&amp;side=y\" width=\"".$propotional_size_view['width']."\" height=\"".$propotional_size_view['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"".$g_organization." - Grußkarte\" />";
	}
	//Lightbox-Mode
	if($g_preferences['photo_show_mode']==1)
	{
		echo "<a href=\"".$bild_url."\" rel=\"lightbox[roadtrip]\" title=\"".$photo_event->getValue("pho_name")."\"><img src=\"$g_root_path/adm_program/modules/photos/photo_show.php?pho_id=".$pho_id."&amp;pic_nr=".$photo."&amp;pho_begin=".$photo_event->getValue("pho_begin")."&amp;scal=".$propotional_size_view['height']."&amp;side=y\" width=\"".$propotional_size_view['width']."\" height=\"".$propotional_size_view['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"".$g_organization." - Grußkarte\" /></a>";
	}
	
	//Gleichesfenster-Mode
	if($g_preferences['photo_show_mode']==2)
	{
		echo "<img onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=".$_REQUEST['photo']."&pho_id=$pho_id'\" src=\"$g_root_path/adm_program/modules/photos/photo_show.php?pho_id=".$pho_id."&amp;pic_nr=".$photo."&amp;pho_begin=".$photo_event->getValue("pho_begin")."&amp;scal=".$propotional_size_view['height']."&amp;side=y\" width=\"".$propotional_size_view['width']."\" height=\"".$propotional_size_view['height']."\" style=\"border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;\" alt=\"".$g_organization." - Grußkarte\" />";
	}      
    if ($error_msg != "")
	{
		$g_message->show($error_msg);
	}
	  
		echo' <form name="ecard_form" action="#" method="post">
			  <input type="hidden" name="ecard[image_name]" value="'; if (! empty($ecard["image_name"])) echo $ecard["image_name"]; echo'" />
			  <input type="hidden" name="submit_action" value="" />
			  <ul class="formFieldList">
			   <li>
                    <hr />
                </li>
               <li>
                 <dl>
                   <dt>
					<label>An:</label>
					';
					if($g_preferences['enable_ecard_cc_recipients'])
					{	
						echo '<div id="getmoreRecipient" style="padding-top:18px;">
						<a href="javascript:showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');">Mehr Empfänger</a>
						</div>';
					}	
				   echo'        
				   </dt>
                   <dd id="Menue" style="height:49px; width:370px;">';
							if (array_key_exists("usr_id", $_GET))
                            {
                                // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
								echo '<div id="basedropdownmenu" style="display:block; margin-bottom:3px;">
									 </div>
									 <div id="dropdownmenu" style="display:block;">
								     </div>
									 <div id="extern">
										<input type="text" class="readonly" readonly="readonly" name="ecard[name_recipient]" style="margin-bottom:3px; width: 200px;" maxlength="50" value="'.$user_name.'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
                                echo '<input type="text" class="readonly" readonly="readonly" name="ecard[email_recipient]" style="width: 330px;" maxlength="50" value="'.$user_email.'"><span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
									 </div>';
								
                            }
                            else
                            {
							   echo '<div id="externSwitch" style="float:right; padding-left:5px; position:relative;">
										 <a href="javascript:getExtern()">externer Empfänger</a>
								     </div>
									 <div id="basedropdownmenu" style="display:block; padding-bottom:3px;">
									     <script language="javascript" type="text/javascript">getMenu();</script>
									 </div>
									 <div id="dropdownmenu" style="display:block;">
								     </div>
								     <div id="extern">
										<input type="hidden" name="ecard[email_recipient]" value="" />
										<input type="hidden" name="ecard[name_recipient]"  value="" />
									 </div>
									  <div id="wrong" style="width:300px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:5px; border:1px solid #ccc;padding:5px;background-color: #FFFFE0; padding-left: 28px;display:none;"></div>';
                            }
                            echo '
                        </dd>
                    </dl>
                </li>
				<li>
					<div id="moreRecipient" style="display:none;">
					<hr />
						<dl>
							<dt>Weitere Empfänger:</dt>
							<dd>
								<div id="buttons" style="width:370px; border:0px;">
									<table summary="TableButtons" border="0" style="width:370px;">
										<tr>
										<td><a href="javascript:addRecipient();" id="btn_add" >hinzufügen</a></td>
										<td style="padding-left:10px;"><a href="javascript:delRecipient();" id="btn_del" style="display:none;">löschen</a></td>
										<td style="padding-left:10px;"><a href="javascript:delAllRecipients();" id="btn_delall" style="display:none;">alle löschen</a></td>
										<td style="padding-left:10px;"><a href="javascript:delContent();" id="btn_delContent" style="display:none;">Inhalt löschen</a></td>
										</tr>
									</table>
								</div>
								<div id="TableTitle" style="width:350px; border:0px; padding-top:10px;">
									<table summary="TableccContailer" border="0">
										<tr>
											<td style="width:30px;">Anzahl</td>
											<td style="width:150px;" align="center">Name</td>
											<td style="width:150px;" align="center">Email</td>
										</tr>
									</table>
								</div>
								<div id="ccrecipientContainer" style="width:490px; border:0px;">
								</div>
						</dl>
					</div>
				</li>
				<li>
                    <hr />
                </li>
			    <li>
                    <dl>
                        <dt><label>Absender:</label></dt>
                        <dd>
			              <input type="text" name="ecard[name_sender]" size="25" class="readonly" readonly="readonly" maxlength="50" style="width: 200px;" value="'; 
							if (! empty($ecard["name_sender"]) && !$g_current_user->getValue("Nachname"))
							{
							   echo $ecard["name_sender"]; 
							}
						    else
							{ 
							   echo $g_current_user->getValue("Vorname")." ".$g_current_user->getValue("Nachname");
							}
					      echo'" />
						  <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
			            </dd>
                    </dl>
                </li>
				 <li>
                    <dl>
                        <dt><label>E-Mail:</label></dt>
                        <dd>
						   <input type="text" name="ecard[email_sender]" size="25" class="readonly" readonly="readonly" maxlength="40" style="width: 330px;"  value="';
							if (! empty($ecard["email_sender"]) && !$g_current_user->getValue("E-Mail"))
							{
							  echo $ecard["email_sender"];
							}
							else 
							{
							  echo $g_current_user->getValue("E-Mail"); 
							}
						    echo'" />
							<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
			            </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
			    <li>
                    <dl>
                        <dt>
						    <label>Nachricht:</label>';
							if($g_preferences['enable_ecard_text_length'])
							{
								echo '<div style="width:125px; padding:5px 0px 5px 35px; background-image: url(\''.THEME_PATH.'/icons/warning16.png\'); background-repeat: no-repeat;background-position: 5px 5px;border:1px solid #ccc; margin:70px 0px 28px 0px;  background-color: #FFFFE0;">
									noch&nbsp;<div id="counter" style="border:0px; display:inline;"><b>'; echo $g_preferences['ecard_text_length'].'</b></div>&nbsp;Zeichen
								</div>';
							}
							echo '<div id="getmoreSettings" style="';
							if($g_preferences['enable_ecard_text_length'])
							{
								echo 'padding-top:28px;';
							}
							else
							{
								echo 'padding-top:153px;';
							}
							echo '">
								<a href="javascript:showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');">Einstellungen einblenden</a>
							</div>	
						</dt>
                        <dd>
							<textarea id="Nachricht" style="width: 330px; height: 180px; overflow:auto; font:'.$g_preferences['ecard_text_size'].'px '.$g_preferences['ecard_text_font'].'; color:'.$g_preferences['ecard_text_color'].'; wrap:virtual;" rows="10" cols="45" name="ecard[message]"';
							if($g_preferences['enable_ecard_text_length'])
							{
							echo' onfocus="javascript:countMax();" onclick="javascript:countMax();" onchange="javascript:countMax();" onkeydown="javascript:countMax();" onkeyup="javascript:countMax();" onkeypress="javascript:countMax();"';
							}
							echo' >';
					  		if (! empty($ecard["message"])) 
							{
						 		echo ''.$ecard["message"].''; 
							}
					   echo'</textarea>
					        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
			           </dd>
                    </dl>
                </li>
			    <li>
					<div id="moreSettings" style="display:none;">
					<hr />
                    <dl>
                        <dt>
						    <label>Einstellungen:</label>
						</dt>
                        <dd>';
						    $first_value_array = array();
							echo'<table cellpadding="5" cellspacing="0" summary="Einstellungen" style="width:350px;"  border="0px">
								<tr>
								  <td>Template:</td>
								  <td>Schriftart:</td>
								  <td>Schriftgröße:</td>
								</tr>
								<tr>
									<td>';
										array_push($first_value_array,array(getMenueSettings($templates,"ecard[template_name]",$g_preferences['ecard_template'],"120","false"),"ecard[template_name]"));
									echo '</td>
									<td>';
										array_push($first_value_array,array(getMenueSettings($fonts,"ecard[schriftart_name]",$g_preferences['ecard_text_font'],"120","true"),"ecard[schriftart_name]"));
									echo '</td>
									<td>';
										array_push($first_value_array,array(getMenueSettings($font_sizes,"ecard[schrift_size]",$g_preferences['ecard_text_size'],"50","false"),"ecard[schrift_size]"));
								    echo  '</td>
							    </tr>
								<tr>
								  <td>Schriftfarbe:</td>
								  <td style="padding-left:40px;">Style:</td>
								  <td></td>
								</tr>
								<tr>
									<td>';
										array_push($first_value_array,array(getColorSettings($font_colors,"ecard[schrift_farbe]","8",$g_preferences['ecard_text_color']),"ecard[schrift_farbe]"));
									echo '</td>
									<td colspan="2" style="padding-left:40px;">';
										echo '<b>Bold: </b><input name="Bold" value="bold" onclick="javascript: getSetting(\'ecard[schrift_style_bold]\',this.value);" type="checkbox" />											  <i>Italic: </i><input name="Italic" value="italic" onclick="javascript: getSetting(\'ecard[schrift_style_italic]\',this.value);" type="checkbox" />'; 					
									echo '</td>
							    </tr>
							</table>';
							getFirstSettings($first_value_array);
							echo '<input type="hidden" name="ecard[schrift_style_bold]" value="" />';		
							echo '<input type="hidden" name="ecard[schrift_style_italic]" value="" />';			
						echo '</dd>
                    </dl>
					</div>
                </li>
			</ul> 
			<hr />
			</form>
			<div style="display:inline;">
				<button onclick="makePreview()" value="vorschau"><img src="'. THEME_PATH. '/icons/eye.png" alt="Vorschau" />&nbsp;Vorschau</button>
			</div>
			<div style="display:inline;">
				<button onclick="sendEcard()" value="abschicken"><img src="'. THEME_PATH. '/icons/email.png" alt="Abschicken" />&nbsp;Abschicken</button>
			</div>';
} 
else 
{     
	echo'<br />
	<div align="center">
		<div style="text-align:center; 
		width:380px; 
		height:30px;
		background-image: url(\''.THEME_PATH.'/icons/ok_big.png\'); 
		background-repeat: no-repeat;
		background-position: 13px 13px;
		margin-top:5px; 
		border:1px solid #ccc;
		padding:20px 0px 5px 35px;
		background-color: #FFFFE0;  
		vertical-align:middle;">
			<span style="font-size:16px; font-weight:bold">Deine Grußkarte wurde erfolgreich versendet.</span>
		</dv>
	</div>
	<br /><br />
	
	<table cellpadding="0" cellspacing="0" border="0" summary="Erfolg" align="center">
	<tr>
		<td align="left" colspan="2"><b>Absender:</b></td>
	</tr>
	<tr>
		<td align="left" style="padding-right:5px;">'; echo $ecard["name_sender"].',</td><td align="left">'.$ecard["email_sender"]; echo'</td>
	</tr>
	<tr>
		<td align="left">&nbsp;</td>
	</tr>
	<tr>
		<td align="left" colspan="2"><b>Empfänger:</b></td>
	</tr><tr>';
	foreach($email_versand_liste as $item)
	{
			$i=0;
			foreach($item as $item2)
			{
					if (!is_integer(($i+1)/2))
					{
						echo '<td align="left"  style="padding-right:5px;">'; echo $item2.',</td></td>'; 
					}
					else
					{
						echo'<td align="left"  style="padding-right:5px;">'; echo $item2.'</td></tr><tr>';
					}
					$i++;
			}
	}			
	echo '</tr>';
	$Liste = array();
	$Liste = getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
	if(count($Liste)>0)
	{
		echo '<tr><td>&nbsp;</td></tr><tr><td colspan="2"><b>Zusätzliche Empfänger:</b></td></tr><tr>';
		foreach($Liste as $item)
		{
			$i=0;
			foreach($item as $item2)
			{
				if (!is_integer(($i+1)/2))
				{
					echo '<td align="left">'.$item2.',</td>'; 
				}
				else
				{
					echo'<td align="left">'.$item2.'</td></tr><tr>';
				}
				$i++;
			}
		}
	}
	echo '</tr></table><br /><br/>';
}  
echo "</div></div></div>";
/************************Buttons********************************/
//Uebersicht
if($photo_event->getValue("pho_id") > 0)
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\"></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>";
}

/***************************Seitenende***************************/
require(THEME_SERVER_PATH. "/overall_footer.php");
?>