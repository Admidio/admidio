<?php
/******************************************************************************
 * Send mails
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id    - E-Mail an den entsprechenden Benutzer schreiben
 * role_name - E-Mail an alle Mitglieder der Rolle schreiben
 * cat       - In Kombination mit dem Rollennamen muss auch der Kategoriename uebergeben werden
 * rol_id    - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
 * subject   - Betreff der E-Mail
 * body      - Inhalt der E-Mail
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
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getUserId      = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getRoleName    = admFuncVariableIsValid($_GET, 'role_name', 'string', '');
$getCategory    = admFuncVariableIsValid($_GET, 'cat', 'string', '');
$getSubject     = admFuncVariableIsValid($_GET, 'subject', 'string', '');
$getBody        = admFuncVariableIsValid($_GET, 'body', 'html', '');
$getCarbonCopy  = admFuncVariableIsValid($_GET, 'carbon_copy', 'boolean', 1);
$getDeliveryConfirmation  = admFuncVariableIsValid($_GET, 'delivery_confirmation', 'boolean', 0);
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric', 0);

// Pruefungen, ob die Seite regulaer aufgerufen wurde
if ($gPreferences['enable_mail_module'] != 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}


if ($gValidLogin && strlen($gCurrentUser->getValue('EMAIL')) == 0)
{
    // der eingeloggte Benutzer hat in seinem Profil keine Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

//Falls ein Rollenname uebergeben wurde muss auch der Kategoriename uebergeben werden und umgekehrt...
if ( (strlen($getRoleName)  > 0 && strlen($getCategory) == 0) 
||   (strlen($getRoleName) == 0 && strlen($getCategory)  > 0) )
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}


if ($getUserId > 0)
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$gValidLogin)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $getUserId);

    // darf auf die User-Id zugegriffen werden    
    if((  $gCurrentUser->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0 )
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }

    $userEmail = $user->getValue('EMAIL');
}
elseif ($getRoleId > 0 || (strlen($getRoleName) > 0 && strlen($getCategory) > 0))
{
    // wird eine bestimmte Rolle aufgerufen, dann pruefen, ob die Rechte dazu vorhanden sind

    if($getRoleId > 0)
    {
        $sqlConditions = ' AND rol_id = '.$getRoleId;
    }
    else
    {
        $sqlConditions = ' AND UPPER(rol_name) = UPPER(\''.$getRoleName.'\')
                           AND UPPER(cat_name) = UPPER(\''.$getCategory.'\')';
    }

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

// Wenn die letzte URL in der Zuruecknavigation die des Scriptes mail_send.php ist,
// dann soll das Formular gefuellt werden mit den Werten aus der Session
if (strpos($gNavigation->getUrl(),'mail_send.php') > 0 && isset($_SESSION['mail_request']))
{
    // Das Formular wurde also schon einmal ausgefüllt,
    // da der User hier wieder gelandet ist nach der Mailversand-Seite
    $form_values = strStripSlashesDeep($_SESSION['mail_request']);
    unset($_SESSION['mail_request']);
    $gNavigation->deleteLastUrl();
}
else
{
    $form_values['name']         = '';
    $form_values['mailfrom']     = '';
    $form_values['subject']      = $getSubject;
    $form_values['mail_body']    = $getBody;
    $form_values['rol_id']       = 0;
    $form_values['carbon_copy']  = $getCarbonCopy;
    $form_values['delivery_confirmation']  = $getDeliveryConfirmation;
    $form_values['show_members'] = $getShowMembers;
}

// Seiten fuer Zuruecknavigation merken
if($getUserId == 0 && $getRoleId == 0)
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
if (strlen($getSubject) > 0)
{
    $gLayout['title'] = $getSubject;
}
else
{
    $gLayout['title'] = $gL10n->get('MAI_SEND_EMAIL');
}

if($gValidLogin == true)
{
	$gLayout['header'] =  '
	<script type="text/javascript"><!--
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
		}

		$(document).ready(function() {
			$("#rol_id").change(function() {showMembers(false)});    
			showMembers(true);
		}); 	
	//--></script>';
}

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// show back link
if($getUserId > 0 || $getRoleId > 0)
{
    echo $gNavigation->getHtmlBackButton();
}

// show headline of module
echo '<h1 class="admHeadline">'.$gLayout['title'].'</h1>';

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
$form = new Form('mail_send_form', $g_root_path.'/adm_program/modules/mail/mail_send.php?'.$formParam, true);
$form->openGroupBox('gb_mail_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));
if ($getUserId > 0)
{
	// usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
    $form->addTextInput('mailto', $gL10n->get('SYS_TO'), $userEmail, 50, FIELD_DISABLED);
}
elseif ($getRoleId > 0 || (strlen($getRoleName) > 0 && strlen($getCategory) > 0) )
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
    $form->addEditor('mail_body', null, $form_values['mail_body']);
}
else
{
    $form->addMultilineTextInput('mail_body', $gL10n->get('SYS_TEXT'), null, 10);
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
$form->show();

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>