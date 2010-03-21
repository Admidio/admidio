<?php
/******************************************************************************
 * Grußkarte Form Bearbeitung
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('ecard_function.php');
if ($g_preferences['enable_bbcode'] == 1)
{
    require('../../system/bbcode.php');
}

$funcClass 					= new FunctionClass($g_l10n);
$email_versand_liste        = array(); // Array wo alle Empfaenger aufgelistet werden (jedoch keine zusaetzlichen);
$email_versand_liste_cc     = array(); // Array wo alle CC Empfaenger aufgelistet werden;
$font_sizes                 = array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30');
$font_colors                = $funcClass->getElementsFromFile('../../system/schriftfarben.txt');
$fonts                      = $funcClass->getElementsFromFile('../../system/schriftarten.txt');
$templates                  = $funcClass->getfilenames(THEME_SERVER_PATH. '/ecard_templates/');
$template                   = THEME_SERVER_PATH. '/ecard_templates/';
$error_msg                  = "";
$msg_send_error             = $g_l10n->get('ECA_SEND_ERROR');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $error_msg = $g_l10n->get('SYS_PHR_MODULE_DISABLED');
}
// pruefen ob User eingeloggt ist
if(!$g_valid_login)
{
	$error_msg = $g_l10n->get('SYS_INVALID_PAGE_VIEW');
}

// ruf die Funktion auf die alle Post und Get Variablen parsed
$funcClass->getVars();
$ecard_send = false;
// Wenn versucht wird die Grußkarte zu versenden werden die notwendigen FElder geprüft und wenn alles okay ist wird das Template geparsed und die Grußkarte weggeschickt

// Wenn die Felder Name E-mail von dem Empaenger und Sender nicht leer und gültig sind
if ( isValidEmailAddress($ecard['email_recipient']) && isValidEmailAddress($ecard['email_sender'])
&& ($ecard['email_recipient'] != '') && ($ecard['name_sender'] != '') && empty($error_msg))
{
	// Wenn die Nachricht größer ist als die maximal Laenge wird sie zurückgestutzt
	if (strlen($ecard['message']) > $g_preferences['ecard_text_length'])
	{
		$ecard['message'] = substr($ecard['message'],0,$g_preferences['ecard_text_length']-1);
	}
	// Template wird geholt
	list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($ecard['template_name'],$template);
	// Wenn es einen Error gibt ihn ausgeben
	if ($error)
	{
		$error_msg = $msg_send_error;
	}
	// Wenn nicht dann die Grußkarte versuchen zu versenden
	else
	{
		// Es wird geprüft ob der Benutzer der ganzen Rolle eine Grußkarte schicken will
		$rolle = str_replace(array('Rolle_','@rolle.com'),'',$ecard['email_recipient']);
		// Wenn nicht dann Name und Email des Empfaengers zur versand Liste hinzufügen
		if(!is_numeric($rolle))
		{
			array_push($email_versand_liste,array($ecard['name_recipient'],$ecard['email_recipient']));
			$email_versand_liste_cc = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
			$ecard_html_data = $funcClass->parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue('usr_id'),$ecard['name_recipient'],$ecard['email_recipient'],$g_preferences['enable_bbcode']);
			$result = $funcClass->sendEcard($ecard,$ecard_html_data,$ecard['name_recipient'],$ecard['email_recipient'],$email_versand_liste_cc, $ecard['image_serverPath']);
			// Wenn die Grußkarte erfolgreich gesendet wurde
			if ($result)
			{
				$ecard_send = true;
			}
			// Wenn nicht dann die dementsprechende Error Nachricht ausgeben
			else
			{
				$error_msg = $msg_send_error;
			}
		}
		// Wenn schon dann alle Namen und die duzugehörigen Emails auslesen und in die versand Liste hinzufügen
		else
		{
			$sql = 'SELECT first_name.usd_value as first_name, last_name.usd_value as last_name,
						   email.usd_value as email, rol_name
					  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
					 RIGHT JOIN '. TBL_USER_DATA. ' as email
						ON email.usd_usr_id = usr_id
					   AND email.usd_usf_id = '. $g_current_user->getProperty('EMAIL', 'usf_id'). '
					   AND LENGTH(email.usd_value) > 0
					  LEFT JOIN '. TBL_USER_DATA. ' as last_name
						ON last_name.usd_usr_id = usr_id
					   AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id'). '
					  LEFT JOIN '. TBL_USER_DATA. ' as first_name
						ON first_name.usd_usr_id = usr_id
					   AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id'). '
					 WHERE rol_id           = '. $rolle. '
					   AND rol_cat_id       = cat_id
					   AND cat_org_id       = '. $g_current_organization->getValue('org_id'). '
					   AND mem_rol_id       = rol_id
					   AND mem_begin       <= "'.DATE_NOW.'"
					   AND mem_end          > "'.DATE_NOW.'"
					   AND mem_usr_id       = usr_id
					   AND usr_valid        = 1
					   AND email.usd_usr_id = email.usd_usr_id
					 ORDER BY last_name, first_name';

			$result             = $g_db->query($sql);
			$firstvalue_name    = '';
			$firstvalue_email   = '';
			$i  = 0 ;
			while ($row = $g_db->fetch_object($result))
			{
				if($i<1)
				{
					$firstvalue_name  = 'Rolle: '.$row->rol_name;
					$firstvalue_email = '-';

				}
				if($row->first_name != '' && $row->last_name != '' && $row->email !='')
				{
					array_push($email_versand_liste,array(''.$row->first_name.' '.$row->last_name.'',$row->email));
				}
				$i++;
			}
			$email_versand_liste_cc = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
			$ecard_html_data = $funcClass->parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$g_current_user->getValue("usr_id"),$firstvalue_name,$firstvalue_email,$g_preferences['enable_bbcode']);
			$b=0;
			foreach($email_versand_liste as $item)
			{                       
				if($b<1)
				{
					$result = $funcClass->sendEcard($ecard,$ecard_html_data,$email_versand_liste[$b][0],$email_versand_liste[$b][1],$email_versand_liste_cc,$ecard['image_serverPath']);
				}
				else
				{
					$result = $funcClass->sendEcard($ecard,$ecard_html_data,$email_versand_liste[$b][0],$email_versand_liste[$b][1],array(), $ecard['image_serverPath']);
				}
				// Wenn die Grußkarte erfolgreich gesendet wurde
				if ($result)
				{
					$ecard_send = true;
				}
				// Wenn nicht dann die dementsprechende Error Nachricht ausgeben
				else
				{
					$error_msg = $msg_send_error;
				}
				$b++;               
			}

		}
   }
}
// Wenn die Felder leer sind oder ungültig dann eine dementsprechente Error Nachricht ausgeben
else
{
	if(empty($error_msg))
	{
		$error_msg = $g_l10n->get('ECA_FIELD_ERROR');
	}
}
echo'
<div class="formLayout">
    <div class="formHead">'.$g_l10n->get("ECA_GREETING_CARD_SEND"). '</div>
    <div class="formBody">
		<div style="text-align: center;">
			<div style="text-align:center;
			width:auto;
			height:30px;
			margin-top:5px;
			padding:20px 0px 5px 5px;
			background-color: #FFFFE0;';
			if($error_msg == '')
			{
				echo 'border:1px solid #ccc;';
			}
			else
			{
				echo 'border:1px solid #FF0000;';
			}
			echo 'vertical-align:middle;">
				<span style="font-size:16px; font-weight:bold">';
				if($error_msg == '')
				{
					echo $g_l10n->get("ECA_PHR_SUCCESSFULLY_SEND");
				}
				else
				{
					echo $g_l10n->get("ECA_PHR_NOT_SUCCESSFULLY_SEND");
				}
				echo'</span>
			</dv>
		</div>
		<br /><br />';
		if ($error_msg == '')
		{
			echo'<table cellpadding="0" cellspacing="0" border="0" summary="Erfolg" style="text-align: center;">
			<tr>
				<td style="text-align: left;" colspan="2"><b>'.$g_l10n->get("SYS_SENDER").':</b></td>
			</tr>
			<tr>
				<td style="padding-right:5px; text-align: left;">'. $ecard['name_sender'].',</td><td style="text-align: left;">'.$ecard['email_sender'].'</td>
			</tr>
			<tr>
				<td style="text-align: left;">&nbsp;</td>
			</tr>
			<tr>
				<td style="text-align: left;" colspan="2"><b>'.$g_l10n->get("SYS_RECIPIENT").':</b></td>
			</tr><tr>';
			foreach($email_versand_liste as $item)
			{
					$i=0;
					foreach($item as $item2)
					{
							if (!is_integer(($i+1)/2))
							{
								echo '<td style="padding-right:5px; text-align: left;">'. $item2.',</td></td>';
							}
							else
							{
								echo'<td style="padding-right:5px; text-align: left;">'. $item2.'</td></tr><tr>';
							}
							$i++;
					}
			}
			echo '</tr>';
			$Liste = array();
			$Liste = $funcClass->getCCRecipients($ecard,$g_preferences['ecard_cc_recipients']);
			if(count($Liste)>0)
			{
				echo '<tr><td>&nbsp;</td></tr><tr><td colspan="2" style="text-align: left;"><b>'.$g_l10n->get("ECA_MORE_RECIPIENTS").':</b></td></tr><tr>';
				foreach($Liste as $item)
				{
					$i=0;
					foreach($item as $item2)
					{
						if (!is_integer(($i+1)/2))
						{
							echo '<td style="text-align: left;">'.$item2.',</td>';
						}
						else
						{
							echo'<td style="text-align: left;">'.$item2.'</td></tr><tr>';
						}
						$i++;
					}
				}
			}
			echo '</tr></table>';
		}
		else
		{
			echo $error_msg;
		}
echo '<br /><br/></div></div></div>';
?>