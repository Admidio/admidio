<?php
/******************************************************************************
 * Form for sending ecards
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id:      Id of photo album whose image you want to send
 * photo_nr:    Number of the photo of the choosen album
 * usr_id:      (optional) Id of the user who should receive the ecard
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('ecard_function.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', null, true);
$showPage	= admFuncVariableIsValid($_GET, 'show_page', 'numeric', 1);

// Initialisierung lokaler Variablen
$funcClass 	 = new FunctionClass($gL10n);
$templates   = $funcClass->getFileNames(THEME_SERVER_PATH. '/ecard_templates/');
$template    = THEME_SERVER_PATH. '/ecard_templates/';
$headline    = $gL10n->get('ECA_GREETING_CARD_EDIT');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//URL auf Navigationstack ablegen
$gNavigation->addUrl(CURRENT_URL, $headline);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $gDb;
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photo_album = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photo_album->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && $photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}  

if ($gValidLogin && strlen($gCurrentUser->getValue('EMAIL')) == 0)
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

if ($getUserId > 0)
{
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
}

// create an object of ckeditor and replace textarea-element
$ckEditor = new CKEditorSpecial();

// ruf die Funktion auf die alle Post und Get Variablen parsed
$funcClass->getVars();

// create html page object
$page = new HtmlPage();

$page->addJavascriptFile($g_root_path.'/adm_program/modules/ecards/ecard.js');
$page->addJavascriptFile($g_root_path.'/adm_program/system/js/form.js');
$page->addJavascript('
			var ecardJS = new ecardJSClass();
			ecardJS.max_recipients			= '.$gPreferences['ecard_cc_recipients'].';
			ecardJS.nameOfRecipient_Text	= "'.$gL10n->get('ECA_NAME_OF_RECIPIENT', $var1='[VAR1]').'";
			ecardJS.emailOfRecipient_Text	= "'.$gL10n->get('ECA_EMAIL_OF_RECIPIENT', $var1='[VAR1]').'";
			ecardJS.message_Text			= "'.$gL10n->get('ECA_THE_MESSAGE').'";
			ecardJS.recipient_Text			= "'.$gL10n->get('SYS_RECIPIENT').'";
			ecardJS.recipientName_Text		= "'.$gL10n->get('ECA_RECIPIENT_NAME').'";
			ecardJS.recipientEmail_Text		= "'.$gL10n->get('ECA_RECIPIENT_EMAIL').'";
			ecardJS.emailLookInvalid_Text	= "'.$gL10n->get('ECA_EMAIL_LOOKS_INVALID').'";
			ecardJS.contentIsLoading_Text	= "'.$gL10n->get('ECA_CONTENT_LOADING').'";
			ecardJS.moreRecipients_Text		= "'.$gL10n->get('ECA_MORE_RECIPIENTS').'";
			ecardJS.noMoreRecipients_Text	= "'.$gL10n->get('ECA_NO_MORE_RECIPIENTS').'";
			ecardJS.blendInSettings_Text	= "'.$gL10n->get('ECA_BLEND_IN_SETTINGS').'";
			ecardJS.blendOutSettings_Text	= "'.$gL10n->get('ECA_BLEND_OUT_SETTINGS').'";
			ecardJS.internalRecipient_Text	= "'.$gL10n->get('ECA_INTERNAL_RECIPIENT').'";
			ecardJS.messageTooLong			= "'.$gL10n->get('ECA_MESSAGE_TOO_LONG',$var1='[MAX]').'";
			ecardJS.loading_Text			= "'.$gL10n->get('SYS_LOADING_CONTENT').'";
			ecardJS.send_Text				= "'.$gL10n->get('SYS_SEND').'";
			ecardJS.template_Text			= "'.$gL10n->get('ECA_TEMPLATE').'";
			ecardJS.templates				= '.$funcClass->createJSTemplateArray($templates).';
			ecardJS.sendDoneURL			= "'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'&photo_nr='.$showPage.'"; 
			
			ecardJS.init();
');

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$ecardMenu = new HtmlNavbar('menu_ecard_form', $headline, $page);
$ecardMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($ecardMenu->show(false));

// show form
$form = new HtmlForm('ecard_form', null, $page);
$form->addInput('submit_action', null, '', array('type' => 'hidden'));
$form->addInput('ecard_image_name', null, $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'], array('type' => 'hidden'));

$form->openGroupBox('gb_layout', $gL10n->get('ECA_LAYOUT'));
    $form->addCustomContent($gL10n->get('SYS_PHOTO'), '
        <a rel="colorboxImage" href="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'"><img 
            src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'].'" 
            class="imageFrame" alt="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'"  title="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'" />
        </a>');
    $templates = admFuncGetDirectoryEntries(THEME_SERVER_PATH.'/ecard_templates');
    foreach($templates as $key => $templateName)
    {
        $templates[$key] = ucfirst(preg_replace('/[_-]/',' ',str_replace('.tpl', '', $templateName)));
    }
    $form->addSelectBox('ecard_template', $gL10n->get('ECA_TEMPLATE'), $templates, array('property' => FIELD_MANDATORY));
$form->closeGroupBox();
$form->openGroupBox('gb_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

    // create list with all possible recipients

    // list all roles where login users could send mails to
    $arrayMailRoles = $gCurrentUser->getAllMailRoles();
    
    $sql = 'SELECT rol_id, rol_name 
              FROM '. TBL_ROLES. '
             WHERE rol_id IN ('.implode(',', $arrayRoles).')
             ORDER BY rol_name ';

    $result = $gDb->query($sql);
    while($row = $gDb->fetch_array($result))
    {
        $list[] = array('groupID: '.$row['rol_id'], $row['rol_name'], $gL10n->get('SYS_ROLES'));
    }

    // select all users
    $arrayRoles = array_merge($arrayMailRoles, $gCurrentUser->getAllVisibleRoles());
    $arrayUniqueRoles = array_unique($arrayRoles);

    $sql   = 'SELECT usr_id, first_name.usd_value as first_name, last_name.usd_value as last_name, 
                     email.usd_value as email
                FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
                JOIN '. TBL_USER_DATA. ' as email
                  ON email.usd_usr_id = usr_id
                 AND LENGTH(email.usd_value) > 0
                JOIN '.TBL_USER_FIELDS.' as field
                  ON field.usf_id = email.usd_usf_id
                 AND field.usf_type = \'EMAIL\'
                LEFT JOIN '. TBL_USER_DATA. ' as last_name
                  ON last_name.usd_usr_id = usr_id
                 AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                LEFT JOIN '. TBL_USER_DATA. ' as first_name
                  ON first_name.usd_usr_id = usr_id
                 AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
               WHERE mem_usr_id  = usr_id
                 AND mem_rol_id IN ('.implode(',', $arrayUniqueRoles).')
                 AND mem_begin <= \''.DATE_NOW.'\'
                 AND mem_end    > \''.DATE_NOW.'\'                
                 AND usr_valid   = 1
            GROUP BY usr_id, first_name.usd_value, last_name.usd_value, email.usd_value
            ORDER BY first_name, last_name';        
    $result = $gDb->query($sql);

    while ($row = $gDb->fetch_array($result)) 
    {
        $list[] = array($row['usr_id'], $row['first_name'].' '.$row['last_name']. ' ('.$row['email'].')', $gL10n->get('SYS_MEMBERS'));
    }
        
	$form->addSelectBox('recipient', $gL10n->get('SYS_TO'), $list, array('property' => FIELD_MANDATORY, 
                        'showContextDependentFirstEntry' => false, 'multiselect' => true));
    $form->addLine();
    $form->addInput('name_from', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'), array('maxLength' => 50, 'property' => FIELD_DISABLED));
    $form->addInput('mail_from', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'), array('maxLength' => 50, 'property' => FIELD_MANDATORY));
$form->closeGroupBox();
$form->openGroupBox('gb_message', $gL10n->get('SYS_MESSAGE'));
    $form->addEditor('ecard_message', null, null, array('property' => FIELD_MANDATORY, 'toolbar' => 'AdmidioGuestbook'));
$form->closeGroupBox();
$form->openButtonGroup();
    $form->addButton('btn_ecard_preview', $gL10n->get('SYS_PREVIEW'), array('icon' => THEME_PATH. '/icons/eye.png'));
    $form->addButton('btn_ecard_submit', $gL10n->get('SYS_SEND'), array('icon' => THEME_PATH. '/icons/email.png'));
$form->closeButtonGroup();

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
exit();
echo '
<div class="formLayout">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admEcardPhoto">
			<div class="groupBoxHeadline" id="admEcardPhotoHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admEcardPhotoBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admEcardPhotoBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_PHOTO').'
			</div>

			<div class="groupBoxBody" id="admEcardPhotoBody">
				<ul class="formFieldList">
					<li>
						<div>
							<a rel="colorboxImage" href="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'"><img 
								src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'].'" 
								class="imageFrame" alt="'.$gL10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'"  title="'.$gL10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'" />
							</a>
						</div>
					</li>
				</ul>
			</div>
		</div>

		<form id="ecard_form" method="post">
			<input type="hidden" name="ecard[image_name]" value="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'].'" />
			<input type="hidden" name="ecard[image_serverPath]" value="'.SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id').'/'.$getPhotoNr.'.jpg" />
			<input type="hidden" name="ecard[submit_action]" value="" />
			<input type="hidden" name="ecard[template_name]" value="'.$gPreferences['ecard_template'].'" />

			<div class="groupBox" id="admMailContactDetails">
				<div class="groupBoxHeadline" id="admMailContactDetailsHead">
					<a class="iconShowHide" href="javascript:showHideBlock(\'admMailContactDetailsBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
					id="admMailContactDetailsBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_CONTACT_DETAILS').'
				</div>

				<div class="groupBoxBody" id="admMailContactDetailsBody">
					<ul class="formFieldList">
						<li>
							<dl>
								<dt>
									<label>'.$gL10n->get("SYS_TO").':</label>
								</dt>
								<dd id="Menue">';
									if ($getUserId > 0)
									{
										// usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
										echo '<div id="extern">
												<input type="text" readonly="readonly" name="ecard[name_recipient]" style="display: none;" value="'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'">
												<input type="text" disabled="disabled" style="margin-bottom:3px; width: 200px;" maxlength="50" value="'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'"><span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
												<input type="text" readonly="readonly" name="ecard[email_recipient]" style="display: none;" value="'.$user->getValue('EMAIL').'">
												<input type="text" disabled="disabled" maxlength="50" value="'.$user->getValue('EMAIL').'"><span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
											 </div>';

									}
									else
									{
									   echo '<div id="externSwitch" style="float:right; padding-left:5px; position:relative;"></div>
											 <div id="basedropdownmenu" style="display:block; padding-bottom:3px;"></div>
											 <div id="dropdownmenu" style="display:block;"></div>
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
						<li>';
                        if($gPreferences['enable_ecard_cc_recipients'])
                        {
                            echo '<div id="getmoreRecipient">
                            <a href="javascript:ecardJS.showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');">'.$gL10n->get("ECA_MORE_RECIPIENTS").'</a>
                            </div>';
                        }
                        echo'
						</li>
						<li>
							<div id="moreRecipient" style="display:none;">
							<hr />
								<dl>
									<dt>'.$gL10n->get("ECA_MORE_RECIPIENTS").':</dt>
									<dd>
										<table summary="TableccContainer" border="0" >
											<tr>
												<td style="width:150px; text-align: left;">'.$gL10n->get("SYS_NAME").'</td>
												<td style="width:200px; padding-left:14px; text-align: left;">'.$gL10n->get("SYS_EMAIL").'</td>
											</tr>
										</table>
										<div id="ccrecipientContainer" style="width:490px; border:0px; text-align: left;"></div>
										<table summary="TableCCRecipientSettings" border="0">
												<tr>
													<td style="text-align: left;"><span class="iconTextLink"><a href="javascript:ecardJS.addRecipient()"><img src="'. THEME_PATH.'/icons/add.png" alt="'.$gL10n->get("SYS_ADD_RECIPIENTS").'" /></a><a href="javascript:ecardJS.addRecipient()">'.$gL10n->get("SYS_ADD_RECIPIENTS").'</a></span></td>
												</tr>
										</table>
									</dd>
								</dl>
							</div>
						</li>
						<li>
							<hr />
						</li>
						<li>
							<dl>
								<dt><label>'.$gL10n->get('SYS_SENDER').':</label></dt>
								<dd><input type="text" disabled="disabled" maxlength="50" style="width: 90%;" value="'.$gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME').'" /></dd>
							</dl>
						</li>
						 <li>
							<dl>
								<dt><label>'.$gL10n->get('SYS_EMAIL').':</label></dt>
								<dd><input type="text" disabled="disabled" maxlength="50" style="width: 90%;"  value="'.$gCurrentUser->getValue('EMAIL').'" /></dd>
							</dl>
						</li>
					</ul>
				</div>
			</div>
			
			<div class="groupBox" id="admMessage">
				<div class="groupBoxHeadline" id="admMessageHead">
					<a class="iconShowHide" href="javascript:showHideBlock(\'admMessageBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
					id="admMessageBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_MESSAGE').'
				</div>

				<div class="groupBoxBody" id="admMessageBody">
					<ul class="formFieldList">
						<li>
							 '.$ckEditor->createEcardEditor('admEcardMessage', '', 'AdmidioEcard').'
							 <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
						</li>
					</ul>
				</div>
			</div>

			<div class="formSubmit">
				<button id="btnPreview" type="button"><img 
					src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('SYS_PREVIEW').'" />&nbsp;'.$gL10n->get('SYS_PREVIEW').'</button>&nbsp;&nbsp;&nbsp;&nbsp;
				<button id="ecardSubmit" onclick="javascript:ecardJS.sendEcard();" type="button"><img 
					src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_SEND').'" />&nbsp;'.$gL10n->get('SYS_SEND').'</button>
			</div>
		</form>
	</div>
</div>';

?>