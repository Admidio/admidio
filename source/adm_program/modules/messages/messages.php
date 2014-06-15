<?php
/******************************************************************************
 * messages main page
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id    - send message to the given user ID
 * subject   - subject of the message
 * msg_id    - ID of the message -> just for answers
 * rol_id    - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
 * carbon_copy - 1 (Default) Checkbox "Kopie an mich senden" ist gesetzt
 *             - 0 Checkbox "Kopie an mich senden" ist NICHT gesetzt
 * show_members : 0 - (Default) show active members of role
 *                1 - show former members of role
 *                2 - show active and former members of role
 *
 *****************************************************************************/

require_once('../../system/common.php');

$formerMembers = 0;

// Initialize and check the parameters
$getMsgType     = admFuncVariableIsValid($_GET, 'msg_type', 'string', '');
$getUserId      = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getSubject     = admFuncVariableIsValid($_GET, 'subject', 'html', '');
$getMsgId       = admFuncVariableIsValid($_GET, 'msg_id', 'numeric', 0);
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getCarbonCopy  = admFuncVariableIsValid($_GET, 'carbon_copy', 'boolean', 1);
$getDeliveryConfirmation  = admFuncVariableIsValid($_GET, 'delivery_confirmation', 'boolean', 0);
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric', 0);


// check if the call of the page was allowed by settings
if ($gPreferences['enable_mail_module'] != 1 && $getMsgType != 'PM')
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// check if the call of the page was allowed by settings
if ($gPreferences['enable_pm_module'] != 1 && $getMsgType == 'PM')
{
    // message if the sending of PM is not allowed
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// check for valid login
if (!$gValidLogin && $getMsgType == 'PM')
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// check for valid call of PM system
if ($getUserId == 0 && $getMsgType == 'PM')
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// check if user has email address for sending a email
if ($gValidLogin && $getMsgType != 'PM' && strlen($gCurrentUser->getValue('EMAIL')) == 0)
{
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

// Update the read status of the message
if ($getMsgId > 0)
{
	$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_user1read = '0'
            WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId." and msg_usrid1 = '".$gCurrentUser->getValue('usr_id')."'";
    $gDb->query($sql);
	
	$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_user2read = '0'
            WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId." and msg_usrid2 = '".$gCurrentUser->getValue('usr_id')."'";
    $gDb->query($sql);
}

if ($getUserId > 0)
{
	//usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
	$user = new User($gDb, $gProfileFields, $getUserId);

	// if an User ID is given, we need to check if the actual user is alowed to contact this user  
	if((  $gCurrentUser->editUsers() == false
	   && isMember($user->getValue('usr_id')) == false)
	|| strlen($user->getValue('usr_id')) == 0 )
	{
		$gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
	}
}

if ($getMsgType == 'PM')
{
	if ($getMsgId > 0)
	{
		$sql = "SELECT msg_id1, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp 
				  FROM ". TBL_MESSAGES. "
				 WHERE msg_id2 > 0 AND msg_id1 = ". $getMsgId ."
				 and msg_type = 'PM'
				 ORDER BY msg_id2 DESC";

		$result = $gDb->query($sql);
	}

	$form_values['name']         = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
	$form_values['subject']      = $getSubject;
		
	if (strlen($getSubject) > 0)
	{
		$headline = $gL10n->get('MAI_SUBJECT').': '.$getSubject;
	}
	else
	{
		$headline = $gL10n->get('PMS_SEND_PM');
	}

	// add current url to navigation stack
	$gNavigation->addUrl(CURRENT_URL, $headline);

	// create html page object
	$page = new HtmlPage();

	// show back link
	$page->addHtml($gNavigation->getHtmlBackButton());

	// show headline of module
	$page->addHeadline($headline);

	$formParam = 'msg_type=PM';

	// id must be send to the next script
	$formParam .= '&usr_id='.$getUserId;

	if ($getMsgId > 0)
	{
	$formParam .= '&'.'msg_id='.$getMsgId;
	}

	// show form
	$form = new HtmlForm('pm_send_form', $g_root_path.'/adm_program/modules/messages/messages_send.php?'.$formParam, $page, true);
	$form->openGroupBox('gb_pm_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

	// Username to send the PM to
	$form->addTextInput('msg_to', $gL10n->get('SYS_TO'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), 50, FIELD_DISABLED);

	$form->closeGroupBox();

	$form->openGroupBox('gb_pm_message', $gL10n->get('SYS_MESSAGE'));

	if(strlen($getSubject) > 0)
	{
	$form->addTextInput('subject', $gL10n->get('MAI_SUBJECT'), $getSubject, 77, FIELD_DISABLED);
	}
	else
	{
	$form->addTextInput('subject', $gL10n->get('MAI_SUBJECT'), '', 77, FIELD_MANDATORY);
	}

	$form->addMultilineTextInput('msg_body', $gL10n->get('SYS_PM'), null, 10, 254);

	$form->closeGroupBox();

	$form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), THEME_PATH.'/icons/email.png');

	// add form to html page
	$page->addHtml($form->show(false));

	// list history of this PM
	if(isset($result))
	{
		while ($row = $gDb->fetch_array($result)) {
		
			if ($row['msg_usrid1'] == $gCurrentUser->getValue('usr_id'))
			{
				$sentUser = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
			}
			else
			{
				$sentUser = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
			}

			
			$page->addHtml('
			<div class="admBoxLayout" id="gbo_'.$row['msg_id1'].'">
				<div class="admBoxHead">
					<div class="admBoxHeadLeft">
						<img src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$sentUser.'" />'.$sentUser);

					$page->addHtml('</div>

					<div class="admBoxHeadRight">'.$row['msg_timestamp']. '&nbsp;');

					$page->addHtml('</div>
				</div>

				<div class="admBoxBody">'.
					nl2br($row['msg_message']));

				$page->addHtml('</div>
			</div>');
			
		}
	}
}
else
{
	if ($getUserId > 0)
	{
		// besitzt der User eine gueltige E-Mail-Adresse
		if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
		{
			$gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
		}

		$userEmail = $user->getValue('EMAIL');
	}
	elseif ($getRoleId > 0)
	{
		// wird eine bestimmte Rolle aufgerufen, dann pruefen, ob die Rechte dazu vorhanden sind

		$sqlConditions = ' AND rol_id = '.$getRoleId;

		$sql = 'SELECT rol_mail_this_role, rol_name, rol_id, 
					   (SELECT COUNT(1)
						  FROM '.TBL_MEMBERS.'
						 WHERE mem_rol_id = rol_id
						   AND (  mem_begin > \''.DATE_NOW.'\'
							   OR mem_end   < \''.DATE_NOW.'\')) as former
				  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
				 WHERE rol_cat_id    = cat_id
				   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
					   OR cat_org_id IS NULL)'.
					   $sqlConditions;
		$result = $gDb->query($sql);
		$row    = $gDb->fetch_array($result);

		// Ausgeloggte duerfen nur an Rollen mit dem Flag "alle Besucher der Seite" Mails schreiben
		// Eingeloggte duerfen nur an Rollen Mails schreiben, zu denen sie berechtigt sind
		// Rollen muessen zur aktuellen Organisation gehoeren
		if(($gValidLogin == false && $row['rol_mail_this_role'] != 3)
		|| ($gValidLogin == true  && $gCurrentUser->mailRole($row['rol_id']) == false)
		|| $row['rol_id']  == null)
		{
			$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
		}

		$rollenName = $row['rol_name'];
		$rollenID   = $getRoleId;
		$formerMembers = $row['former'];
	}

	// Wenn die letzte URL in der Zuruecknavigation die des Scriptes message_send.php ist,
	// dann soll das Formular gefuellt werden mit den Werten aus der Session
	if (strpos($gNavigation->getUrl(),'message_send.php') > 0 && isset($_SESSION['message_request']))
	{
		// Das Formular wurde also schon einmal ausgefüllt,
		// da der User hier wieder gelandet ist nach der Mailversand-Seite
		$form_values = strStripSlashesDeep($_SESSION['message_request']);
		unset($_SESSION['message_request']);
		$gNavigation->deleteLastUrl();
	}
	else
	{
		$form_values['name']         = '';
		$form_values['mailfrom']     = '';
		$form_values['subject']      = $getSubject;
		$form_values['msg_body']     = '';
		$form_values['rol_id']       = 0;
		$form_values['carbon_copy']  = $getCarbonCopy;
		$form_values['delivery_confirmation']  = $getDeliveryConfirmation;
		$form_values['show_members'] = $getShowMembers;
	}

	if (strlen($getSubject) > 0)
	{
		$headline = $getSubject;
	}
	else
	{
		$headline = $gL10n->get('MAI_SEND_EMAIL');
	}

	// add current url to navigation stack
	if($getUserId == 0 && $getRoleId == 0)
	{
		$gNavigation->clear();
	}
	$gNavigation->addUrl(CURRENT_URL, $headline);

	// create html page object
	$page = new HtmlPage();

	if($gValidLogin == true)
	{
		$page->addJavascript('
			// if role has former members show select box where user can choose to write email also to former members
			function showMembers(initialize) {
				fadeIn = "";
				if(initialize == false) {
					fadeIn = "slow";
				}
				
				if($("#rol_id").val() > 0) {
					// check if role has former members
					$.get("'.$g_root_path.'/adm_program/administration/roles/roles_function.php?mode=9&rol_id="+ $("#rol_id").val(), function(data) {
						if(data == "1") {
							$("#admShowMembers").show(fadeIn);
						} 
						else {
							$("#admShowMembers").hide(fadeIn);
						}
					});
				}
				else {
					$("#admShowMembers").hide(fadeIn);
				}
			}');
		$page->addJavascript('
			$("#rol_id").change(function() {showMembers(false)});    
			showMembers(true);', true);
	}

	// show back link
	if($getUserId > 0 || $getRoleId > 0)
	{
		$page->addHtml($gNavigation->getHtmlBackButton());
	}

	// show headline of module
	$page->addHeadline($headline);

	$formParam = '';

	// if user id is set then this id must be send to the next script
	if($getUserId > 0)
	{
		$formParam .= 'usr_id='.$getUserId.'&';
	}
	// if subject was set as param then send this subject to next script
	if (strlen($getSubject) > 0)
	{
		$formParam .= 'subject='.$getSubject.'&';
	}


	// show form
	$form = new HtmlForm('mail_send_form', $g_root_path.'/adm_program/modules/messages/messages_send.php?'.$formParam, $page, true);
	$form->openGroupBox('gb_mail_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
	if ($getUserId > 0)
	{
		// usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
		$form->addTextInput('msg_to', $gL10n->get('SYS_TO'), $userEmail, 50, FIELD_DISABLED);
	}
	elseif ($getRoleId > 0)
	{
		// Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
		$form->addSelectBox('rol_id', $gL10n->get('SYS_TO'), array($rollenID => $rollenName), FIELD_MANDATORY, $rollenID);
	}
	else
	{
		// keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
		if ($gValidLogin)
		{
			// alle Rollen auflisten,
			// an die im eingeloggten Zustand Mails versendet werden duerfen
			$sql = 'SELECT rol_id, rol_name, cat_name 
					  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
					 WHERE rol_valid   = 1
					   AND rol_cat_id  = cat_id
					   AND cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
					 ORDER BY cat_sequence, rol_name ';
		}
		else
		{
			// alle Rollen auflisten,
			// an die im nicht eingeloggten Zustand Mails versendet werden duerfen
			$sql = 'SELECT rol_id, rol_name, cat_name 
					  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
					 WHERE rol_mail_this_role = 3
					   AND rol_valid  = 1
					   AND rol_cat_id = cat_id
					   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					 ORDER BY cat_sequence, rol_name ';
		}
		$form->addSelectBoxFromSql('rol_id', $gL10n->get('SYS_TO'), $gDb, $sql, FIELD_MANDATORY, $form_values['rol_id'], true, 'MAI_SEND_MAIL_TO_ROLE');
	}

	// add a selectbox where you can choose to which members (active, former) you want to send the mail
	if (($getUserId == 0 && $gValidLogin == true && $getRoleId == 0)
	||  ($getRoleId  > 0 && $formerMembers > 0))
	{
		$selectBoxEntries = array(0 => $gL10n->get('LST_ACTIVE_MEMBERS'), 1 => $gL10n->get('LST_FORMER_MEMBERS'), 2 => $gL10n->get('LST_ACTIVE_FORMER_MEMBERS'));
		$form->addSelectBox('show_members', null, $selectBoxEntries, FIELD_DEFAULT, $form_values['show_members']);
	}

	$form->addLine();

	if ($gCurrentUser->getValue('usr_id') > 0)
	{
		$form->addTextInput('name', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'), 50, FIELD_DISABLED);
		$form->addTextInput('mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'), 50, FIELD_DISABLED);
	}
	else
	{
		$form->addTextInput('name', $gL10n->get('MAI_YOUR_NAME'), $form_values['name'], 50, FIELD_MANDATORY);
		$form->addTextInput('mailfrom', $gL10n->get('MAI_YOUR_EMAIL'), $form_values['mailfrom'], 50, FIELD_MANDATORY);
	}

	// show option to send a copy to your email address only for registered users because of spam abuse
	if($gValidLogin)
	{
		$form->addCheckbox('carbon_copy', $gL10n->get('MAI_SEND_COPY'), $form_values['carbon_copy']);
	}

	// if preference is set then show a checkbox where the user can request a delivery confirmation for the email
	if (($gCurrentUser->getValue('usr_id') > 0 && $gPreferences['mail_delivery_confirmation']==2) || $gPreferences['mail_delivery_confirmation']==1)
	{
		$form->addCheckbox('delivery_confirmation', $gL10n->get('MAI_DELIVERY_CONFIRMATION'), $form_values['delivery_confirmation']);
	}

	$form->closeGroupBox();

	$form->openGroupBox('gb_mail_message', $gL10n->get('SYS_MESSAGE'));
	$form->addTextInput('subject', $gL10n->get('MAI_SUBJECT'), $form_values['subject'], 77, FIELD_MANDATORY);

	// Nur eingeloggte User duerfen Attachments anhaengen...
	if (($gValidLogin) && ($gPreferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
	{
		$form->addFileUpload('btn_add_attachment', $gL10n->get('MAI_ATTACHEMENT'), ($gPreferences['max_email_attachment_size'] * 1024), true, $gL10n->get('MAI_ADD_ATTACHEMENT'), true, FIELD_DEFAULT, array('MAI_MAX_ATTACHMENT_SIZE', Email::getMaxAttachementSize('mb')));
	}

	// add textfield or ckeditor to form
	if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
	{
		$form->addEditor('msg_body', null, $form_values['msg_body']);
	}
	else
	{
		$form->addMultilineTextInput('msg_body', $gL10n->get('SYS_TEXT'), null, 10);
	}

	$form->closeGroupBox();

	// if captchas are enabled then visitors of the website must resolve this
	if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
	{
		$form->openGroupBox('gb_confirmation_of_input', $gL10n->get('SYS_CONFIRMATION_OF_INPUT'));
		$form->addCaptcha('captcha', $gPreferences['captcha_type']);
		$form->closeGroupBox();
	}

	$form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), THEME_PATH.'/icons/email.png');

	// add form to html page and show page
	$page->addHtml($form->show(false));
}
// show page
$page->show();

?>