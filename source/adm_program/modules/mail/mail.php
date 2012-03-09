<?php
/******************************************************************************
 * E-Mails verschicken
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
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
require_once('../../system/classes/email.php');
require_once('../../system/classes/form_elements.php');

if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
{
	// create an object of ckeditor and replace textarea-element
	require_once('../../system/classes/ckeditor_special.php');
	$ckEditor = new CKEditorSpecial();
}

$formerMembers = 0;

// Initialize and check the parameters
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getUserId      = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getRoleName    = admFuncVariableIsValid($_GET, 'role_name', 'string', '');
$getCategory    = admFuncVariableIsValid($_GET, 'cat', 'string', '');
$getSubject     = admFuncVariableIsValid($_GET, 'subject', 'string', '');
$getBody        = admFuncVariableIsValid($_GET, 'body', 'string', '');
$getCarbonCopy  = admFuncVariableIsValid($_GET, 'carbon_copy', 'boolean', 1);
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric', 0);

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse für nicht eigeloggte Benutzer geladen werden
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1 && $gPreferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

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
if (strpos($_SESSION['navigation']->getUrl(),'mail_send.php') > 0 && isset($_SESSION['mail_request']))
{
    // Das Formular wurde also schon einmal ausgefüllt,
    // da der User hier wieder gelandet ist nach der Mailversand-Seite
    $form_values = strStripSlashesDeep($_SESSION['mail_request']);
    unset($_SESSION['mail_request']);

    $_SESSION['navigation']->deleteLastUrl();
}
else
{
    $form_values['name']         = '';
    $form_values['mailfrom']     = '';
    $form_values['subject']      = $getSubject;
    $form_values['mail_body']    = $getBody;
    $form_values['rol_id']       = '';
    $form_values['carbon_copy']  = $getCarbonCopy;
    $form_values['show_members'] = $getShowMembers;
}

// Seiten fuer Zuruecknavigation merken
if($getUserId == 0 && $getRoleId == 0)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Focus auf das erste Eingabefeld setzen
if ($getUserId == 0 && $getRoleId == 0 && strlen($getRoleName)  == 0)
{
    $focusField = 'rol_id';
}
else if($gCurrentUser->getValue('usr_id') == 0)
{
    $focusField = 'name';
}
else
{
    $focusField = 'subject';
}

// Html-Kopf ausgeben
if (strlen($getSubject) > 0)
{
    $gLayout['title'] = $getSubject;
}
else
{
    $gLayout['title'] = $gL10n->get('MAI_SEND_EMAIL');
}

$gLayout['header'] =  '
<script type="text/javascript"><!--
    // neue Zeile mit Button zum Hinzufuegen von Dateipfaden einblenden
    function addAttachment()
    {
        new_attachment = document.createElement("input");
        $(new_attachment).attr("type", "file");
        $(new_attachment).attr("name", "userfile[]");
        $(new_attachment).css("display", "block");
        $(new_attachment).css("width", "90%");
        $(new_attachment).css("margin-bottom", "5px");
        $(new_attachment).hide();
        $("#add_attachment").before(new_attachment);
        $(new_attachment).show("slow");
    }
    
    function showMembers(initialize)
    {
        fadeIn = "";
        if(initialize == false)
        {
            fadeIn = "slow";
        }
 	    rolId = $("#rol_id").val();
 	    
 	    if(rolId > 0)
 	    {
            // Schauen, ob die Rolle ehemalige Mitglieder besitzt
            $.get("'.$g_root_path.'/adm_program/administration/roles/roles_function.php?mode=9&rol_id="+ rolId, function(data) {
                if(data == "1")
                {
                    $("#admShowMembers").show(fadeIn);
                }
                else
                {
                    $("#admShowMembers").hide(fadeIn);
                }
            });
        }
        else
        {
            $("#admShowMembers").hide(fadeIn);
        }
    }

    $(document).ready(function() 
	{
        $("#'.$focusField.'").focus();
    
       	$("#rol_id").change(function() {showMembers(false)});    
        showMembers(true);
 	}); 	
//--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');
echo '
<form action="'.$g_root_path.'/adm_program/modules/mail/mail_send.php?';
    // usr_id wird mit GET uebergeben,
    // da keine E-Mail-Adresse von mail_send angenommen werden soll
    if($getUserId > 0)
    {
        echo 'usr_id='.$getUserId.'&';
    }
	if (strlen($getSubject) > 0)
	{
		echo 'subject='.$getSubject.'&';
	}
    echo '" method="post" enctype="multipart/form-data">

    <div class="formLayout" id="write_mail_form">
        <div class="formHead">'. $gLayout['title']. '</div>
        <div class="formBody">
			<div class="groupBox" id="admMailContactDetails">
				<div class="groupBoxHeadline" id="admMailContactDetailsHead">
					<a class="iconShowHide" href="javascript:showHideBlock(\'admMailContactDetailsBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
					id="admMailContactDetailsBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_CONTACT_DETAILS').'
				</div>

				<div class="groupBoxBody" id="admMailContactDetailsBody">
					<ul class="formFieldList">
						<li>
							<dl>
								<dt><label for="rol_id">'.$gL10n->get('SYS_TO').':</label></dt>
								<dd>';
									if ($getUserId > 0)
									{
										// usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
										echo '<input type="text" disabled="disabled" id="mailto" name="mailto" style="width: 90%;" maxlength="50" value="'.$userEmail.'" />
										<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
									}
									elseif ($getRoleId > 0 || (strlen($getRoleName) > 0 && strlen($getCategory) > 0) )
									{
										// Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
										echo '<select size="1" id="rol_id" name="rol_id"><option value="'.$rollenID.'" selected="selected">'.$rollenName.'</option></select>
										<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
									}
									else
									{
										// keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
										echo '<select size="1" id="rol_id" name="rol_id">';
										if ($form_values['rol_id'] == "")
										{
											echo '<option value="" selected="selected">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
										}

										if ($gValidLogin)
										{
											// alle Rollen auflisten,
											// an die im eingeloggten Zustand Mails versendet werden duerfen
											$sql = 'SELECT rol_name, rol_id, cat_name 
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
											$sql = 'SELECT rol_name, rol_id, cat_name 
													  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
													 WHERE rol_mail_this_role = 3
													   AND rol_valid  = 1
													   AND rol_cat_id = cat_id
													   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
													 ORDER BY cat_sequence, rol_name ';
										}
										$result = $gDb->query($sql);
										$act_category = '';

										while ($row = $gDb->fetch_array($result))
										{
											if(!$gValidLogin || ($gValidLogin && $gCurrentUser->mailRole($row['rol_id'])))
											{
												// if text is a translation-id then translate it
												if(strpos($row['cat_name'], '_') == 3)
												{
													$row['cat_name'] = $gL10n->get(admStrToUpper($row['cat_name']));
												}

												if($act_category != $row['cat_name'])
												{
													if(strlen($act_category) > 0)
													{
														echo '</optgroup>';
													}
													echo '<optgroup label="'.$row['cat_name'].'">';
													$act_category = $row['cat_name'];
												}
												echo '<option value="'.$row['rol_id'].'" ';
												if (isset($form_values['rol_id']) 
												&& $row['rol_id'] == $form_values['rol_id'])
												{
													echo ' selected="selected" ';
												}
												echo '>'.$row['rol_name'].'</option>';
											}
										}

										echo '</optgroup>
										</select>
										<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
										<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MAI_SEND_MAIL_TO_ROLE&amp;inline=true"><img 
											onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MAI_SEND_MAIL_TO_ROLE\',this)" onmouseout="ajax_hideTooltip()"
											class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
									}
								echo'
								</dd>
							</dl>
						</li>';
						
						if (($getUserId == 0 && $gValidLogin == true && $getRoleId == 0)
						||  ($getRoleId  > 0 && $formerMembers > 0))
						{
							echo '
							<li>
								<dl id="admShowMembers">
									<dt>&nbsp;</dt>
									<dd>';
        								$selectBoxEntries = array(0 => $gL10n->get('LST_ACTIVE_MEMBERS'), 1 => $gL10n->get('LST_FORMER_MEMBERS'), 2 => $gL10n->get('LST_ACTIVE_FORMER_MEMBERS'));
        								echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form_values['show_members'], 'show_members');
									echo '</dd>
								</dl>
							</li>';
						}
						
						echo '<li>
							<hr />
						</li>
						<li>
							<dl>
								<dt><label for="name">'.$gL10n->get('MAI_YOUR_NAME').':</label></dt>
								<dd>';
									if ($gCurrentUser->getValue('usr_id') > 0)
									{
									   echo '<input type="text" id="name" name="name" disabled="disabled" style="width: 90%;" maxlength="50" value="'. $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'). '" />';
									}
									else
									{
									   echo '<input type="text" id="name" name="name" style="width: 200px;" maxlength="50" value="'. $form_values['name']. '" />
									   <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
									}
								echo '</dd>
							</dl>
						</li>
						<li>
							<dl>
								<dt><label for="mailfrom">'.$gL10n->get('MAI_YOUR_EMAIL').':</label></dt>
								<dd>';
									if ($gCurrentUser->getValue('usr_id') > 0)
									{
									   echo '<input type="text" id="mailfrom" name="mailfrom" disabled="disabled" style="width: 90%;" maxlength="50" value="'. $gCurrentUser->getValue('EMAIL'). '" />';
									}
									else
									{
									   echo '<input type="text" id="mailfrom" name="mailfrom" style="width: 90%;" maxlength="50" value="'. $form_values['mailfrom']. '" />
									   <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
									}
								echo '</dd>
							</dl>
						</li>
						<li>
							<dl>
								<dt>&nbsp;</dt>
								<dd>
									<input type="checkbox" id="carbon_copy" name="carbon_copy" value="1" ';
									if (isset($form_values['carbon_copy']) && $form_values['carbon_copy'] == 1)
									{
										echo ' checked="checked" ';
									}
									echo ' /> <label for="carbon_copy">'.$gL10n->get('MAI_SEND_COPY').'</label>
								</dd>
							</dl>
						</li>
					</ul>
				</div>
			</div>
				
			<div class="groupBox" id="admMailMessage">
				<div class="groupBoxHeadline" id="admMailMessageHead">
					<a class="iconShowHide" href="javascript:showHideBlock(\'admMailMessageBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
					id="admMailMessageBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_MESSAGE').'
				</div>

				<div class="groupBoxBody" id="admMailMessageBody">
					<ul class="formFieldList">
						<li>
							<dl>
								<dt><label for="subject">'.$gL10n->get('MAI_SUBJECT').':</label></dt>
								<dd><input type="text" id="subject" name="subject" style="width: 90%;" maxlength="77" value="'. $form_values['subject']. '" />
									<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
								</dd>
							</dl>
						</li>';

						// Nur eingeloggte User duerfen Attachments anhaengen...
						if (($gValidLogin) && ($gPreferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
						{
							echo '
							<li>
								<dl>
									<dt><label for="add_attachment">'.$gL10n->get('MAI_ATTACHEMENT').'</label></dt>
									<dd id="attachments">
										<input type="hidden" name="MAX_FILE_SIZE" value="' . ($gPreferences['max_email_attachment_size'] * 1024) . '" />
										<span id="add_attachment" class="iconTextLink" style="display: block;">
											<a href="javascript:addAttachment()"><img
											src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('MAI_ADD_ATTACHEMENT').'" /></a>
											<a href="javascript:addAttachment()">'.$gL10n->get('MAI_ADD_ATTACHEMENT').'</a>
											<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MAI_MAX_ATTACHMENT_SIZE&amp;message_var1='. Email::getMaxAttachementSize('mb').'&amp;inline=true"><img 
												onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MAI_MAX_ATTACHMENT_SIZE&amp;message_var1='. Email::getMaxAttachementSize('mb').'\',this)" onmouseout="ajax_hideTooltip()"
												class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
										</span>
									</dd>
								</dl>
							</li>';
						}

						echo '
						<li>
							<div>';
									if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
									{
										echo $ckEditor->createEditor('mail_body', $form_values['mail_body']);
									}
									else
									{
									   echo '<textarea id="mail_body" name="mail_body" style="width: 99%;" rows="10" cols="45">'. $form_values['mail_body']. '</textarea>';
									}
							echo '</div>
						</li>
					</ul>
				</div>
			</div>';

			// Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
			// falls es in den Orgaeinstellungen aktiviert wurde...
			if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
			{
				echo '<div class="groupBox" id="admConfirmationOfEntry">
					<div class="groupBoxHeadline" id="admConfirmationOfEntryHead">
						<a class="iconShowHide" href="javascript:showHideBlock(\'admConfirmationOfEntryBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
						id="admConfirmationOfEntryBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_CONFIRMATION_OF_INPUT').'
					</div>
		
					<div class="groupBoxBody" id="admConfirmationOfEntryBody">
						<ul class="formFieldList">
							<li>
								<dl>
									<dt>&nbsp;</dt>
									<dd>';
										if($gPreferences['captcha_type']=='pic')
										{
											echo '<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />';
											$captcha_label = $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
											$captcha_description = 'SYS_CAPTCHA_DESCRIPTION';
										}
										else if($gPreferences['captcha_type']=='calc')
										{
											$captcha = new Captcha();
											$captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4'));
											$captcha_label = $gL10n->get('SYS_CAPTCHA_CALC');
											$captcha_description = 'SYS_CAPTCHA_CALC_DESCRIPTION';
										}
									echo '
									</dd>
								</dl>
							</li>
							<li>
								<dl>
									<dt><label for="captcha">'.$captcha_label.':</label></dt>
									<dd>
										<input type="text" id="captcha" name="captcha" style="width: 200px;" maxlength="8" value="" />
										<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
										<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$captcha_description.'&amp;inline=true"><img 
											onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$captcha_description.'\',this)" onmouseout="ajax_hideTooltip()"
											class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
									</dd>
								</dl>
							</li>
						</ul>
					</div>
				</div>';
			}

            echo '<div class="formSubmit">
                <button id="btnSend" type="submit"><img src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_SEND').'" />&nbsp;'.$gL10n->get('SYS_SEND').'</button>
            </div>
        </div>
    </div>
</form>';

if($getUserId > 0 || $getRoleId > 0)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>