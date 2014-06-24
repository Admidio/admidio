<?php
/******************************************************************************
 * Save organization preferences
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode     : 1 - Save organization preferences
 *            2 - show welcome dialog for new organization
 *            3 - create new organization
 * form         - The name of the form preferences that were submitted.
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1);
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

if($getMode == 1)
{
    $gMessage->showHtmlTextOnly(true);
}

// only webmasters are allowed to edit organization preferences or create new organizations
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

switch($getMode)
{
case 1:
    $_SESSION['organization_request'] = $_POST;
    
    // first check the fields of the submitted form
    
    switch($getForm)
    {
        case 'common':
            $checkboxes = array('enable_rss','enable_auto_login','enable_password_recovery','system_js_editor_enabled','system_search_similar');
            
            if(strlen($_POST['theme']) == 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_ADMIDIO_THEME')));
            }
        
            if(is_numeric($_POST['logout_minutes']) == false || $_POST['logout_minutes'] <= 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER')));
            }
            
            if(isset($_POST['enable_auto_login']) == false && $gPreferences['enable_auto_login'] == 1)
            {
                // if auto login was deactivated than delete all saved logins
                $sql = 'DELETE FROM '.TBL_AUTO_LOGIN;
                $gDb->query($sql);
                $gPreferences[$key] = $value;
            }
            break;

        case 'regional_settings':
            $checkboxes = array('system_organization_select','system_show_all_users');

            if(strlen($_POST['org_longname']) == 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
            }

            if(strlen($_POST['system_language']) != 2)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_LANGUAGE')));
            }

            if(strlen($_POST['system_date']) == 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATE_FORMAT')));
            }

            if(strlen($_POST['system_time']) == 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_TIME_FORMAT')));
            }
            break;
        
        default:
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    
    // check every checkbox if a value was committed
    // if no value is found then set 0 because 0 will not be committed in a html checkbox element
    foreach($checkboxes as $key => $value)
    {
        if(isset($_POST[$value]) == false || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }
    
    // then update the database with the new values

    foreach($_POST as $key => $value)
    {
        // Elmente, die nicht in adm_preferences gespeichert werden hier aussortieren
        if($key != 'save')
        {
            if(strpos($key, 'org_') === 0)
            {
                $gCurrentOrganization->setValue($key, $value);
            }
            elseif(strpos($key, 'SYSMAIL_') === 0)
            {
                $text = new TableText($gDb);
                $text->readDataByColumns(array('txt_org_id' => $gCurrentOrganization->getValue('org_id'), 'txt_name' => $key));
                $text->setValue('txt_text', $value);
                $text->save();
            }
            elseif($key == 'forum_pw' && $value == '0000')
            {
                // Forumpassword hier gesondert behandeln, da es nicht angezeigt werden soll
                // 0000 bedeutet, dass das PW sich nicht veraendert hat
                $gPreferences[$key] = $gPreferences[$key];
            }
            else
            {
                $gPreferences[$key] = $value;
            }
        }
    }

    // alle Daten nun speichern
    $ret_code = $gCurrentOrganization->save();
    if($ret_code != 0)
    {
        $gCurrentOrganization->clear();
        $gMessage->show($gL10n->get('SYS_ERROR_DATABASE_ACCESS', $ret_code));
    }

    $gCurrentOrganization->setPreferences($gPreferences);

    // refresh language if neccessary
    if($gL10n->getLanguage() != $gPreferences['system_language'])
    {
        $gL10n->setLanguage($gPreferences['system_language']);
    }

    // clean up
    unset($_SESSION['organization_request']);
    unset($_SESSION['gForum']);
    $gCurrentSession->renewOrganizationObject();

    echo "success";

    // *******************************************************************************
    // Pruefen, ob alle notwendigen Felder gefuellt sind
    // *******************************************************************************
/*

    if(strlen($_POST['email_administrator']) == 0)
    {
        $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $gL10n->get('SYS_SYSTEM_MAILS')));
    }
    else
    {
        $_POST['email_administrator'] = admStrToLower($_POST['email_administrator']);
        if(!strValidCharacters($_POST['email_administrator'], 'email'))
        {
            $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS')));
        }
    }

    if(strlen($_POST['mail_sendmail_address']) > 0)
    {
        $_POST['mail_sendmail_address'] = admStrToLower($_POST['mail_sendmail_address']);
        if(!strValidCharacters($_POST['mail_sendmail_address'], 'email'))
        {
            $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('MAI_SENDER_EMAIL')));
        }
    }


    if(is_numeric($_POST['weblinks_redirect_seconds']) == false || $_POST['weblinks_redirect_seconds'] < 0)
    {
        $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('LNK_DISPLAY_REDIRECT'), $gL10n->get('LNK_WEBLINKS')));
    }

    // check every checkbox if a value was committed
    // if no value is found then set 0 because 0 will not be committed in a html checkbox element

    $checkboxes = array('dates_show_calendar_select'
                       ,'dates_show_map_link'
                       ,'dates_show_rooms'
                       ,'enable_system_mails'
                       ,'enable_email_notification'
                       ,'enable_mail_captcha'
                       ,'enable_registration_captcha'
                       ,'enable_registration_admin_mail'
                       ,'enable_rss'
                       ,'enable_dates_ical'
                       ,'enable_auto_login'
                       ,'enable_password_recovery'
                       ,'enable_download_module'
                       ,'enable_intial_comments_loading'
                       ,'enable_mail_module'
					   ,'enable_pm_module'
                       ,'enable_guestbook_captcha'
                       ,'enable_ecard_module'
                       ,'enable_forum_interface'
                       ,'enable_gbook_comments4all'
                       ,'enable_ecard_module'
                       ,'forum_export_user'
                       ,'forum_link_intern'
                       ,'forum_set_admin'
                       ,'forum_sqldata_from_admidio'
                       ,'lists_hide_overview_details'
                       ,'mail_html_registered_users'
                       ,'mail_sender_into_to'
                       ,'mail_smtp_auth'
                       ,'photo_download_enabled'
                       ,'photo_keep_original'
                       ,'photo_upload_mode'
                       ,'profile_log_edit_fields'
                       ,'profile_show_map_link'
                       ,'profile_show_roles'
                       ,'profile_show_former_roles'
                       ,'profile_show_extern_roles'
                       ,'system_js_editor_enabled'
                       ,'system_organization_select'
                       ,'system_search_similar'
                       ,'system_show_all_users'
                       );

    foreach($checkboxes as $key => $value)
    {
        if(isset($_POST[$value]) == false || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }

    // Forumverbindung testen
    if(isset($_POST['enable_forum_interface']) && $_POST['enable_forum_interface'] == 1 && $_POST['forum_sqldata_from_admidio'] == 0)
    {
        if($_POST['forum_sqldata_from_admidio'] == 0 && (strlen($_POST['forum_srv']) == 0 || strlen($_POST['forum_usr']) == 0 || strlen($_POST['forum_pw']) == 0 || strlen($_POST['forum_db']) == 0 ))
        {
            $gMessage->show($gL10n->get('SYS_FORUM_ACCESS_DATA'));
        }
        else
        {
            // Password 0000 ist aus Sicherheitsgruenden ein Dummy und bedeutet, dass es sich nicht geaendert hat
            if($_POST['forum_pw'] == '0000')
            {
                $_POST['forum_pw'] = $gPreferences['forum_pw'];
            }

            $forum_test = Forum::createForumObject($_POST['forum_version']);

            if($_POST['forum_sqldata_from_admidio'] == 0)
            {
                $connect_id = $forum_test->connect($_POST['forum_srv'], $_POST['forum_usr'], $_POST['forum_pw'], $_POST['forum_db'], $gDb);
            }
            else
            {
                $connect_id = $forum_test->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $_POST['forum_db'], $gDb);
            }
            if($connect_id == false)
            {
                $gMessage->show($gL10n->get('SYS_FORUM_DB_CONNECTION_FAILED'));
            }
        }
    }

    // *******************************************************************************
    // Organisation updaten
    // *******************************************************************************

    $text = new TableText($gDb);

    // Einstellungen speichern

    foreach($_POST as $key => $value)
    {
        // Elmente, die nicht in adm_preferences gespeichert werden hier aussortieren
        if($key != 'version' && $key != 'save')
        {
            if(strpos($key, 'org_') === 0)
            {
                $gCurrentOrganization->setValue($key, $value);
            }
            elseif(strpos($key, 'SYSMAIL_') === 0)
            {
                $text->readDataByColumns(array('txt_org_id' => $gCurrentOrganization->getValue('org_id'), 'txt_name' => $key));
                $text->setValue('txt_text', $value);
                $text->save();
            }
            elseif($key == 'forum_pw' && $value == '0000')
            {
                // Forumpassword hier gesondert behandeln, da es nicht angezeigt werden soll
                // 0000 bedeutet, dass das PW sich nicht veraendert hat
                $gPreferences[$key] = $gPreferences[$key];
            }
            elseif($key == 'enable_auto_login' && $value == 0 && $gPreferences['enable_auto_login'] == 1)
            {
                // if deactivate auto login than delete all saved logins
                $sql = 'DELETE FROM '.TBL_AUTO_LOGIN;
                $gDb->query($sql);
                $gPreferences[$key] = $value;
            }
            else
            {
                $gPreferences[$key] = $value;
            }
        }
    }

    // alle Daten nun speichern
    $ret_code = $gCurrentOrganization->save();
    if($ret_code != 0)
    {
        $gCurrentOrganization->clear();
        $gMessage->show($gL10n->get('SYS_ERROR_DATABASE_ACCESS', $ret_code));
    }

    $gCurrentOrganization->setPreferences($gPreferences);

    // refresh language if neccessary
    if($gL10n->getLanguage() != $gPreferences['system_language'])
    {
        $gL10n->setLanguage($gPreferences['system_language']);
    }

    // clean up
    unset($_SESSION['organization_request']);
    unset($_SESSION['gForum']);
    $gCurrentSession->renewOrganizationObject();

    // zur Ausgangsseite zurueck
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    */
    break;
    
case 2:
    if(isset($_SESSION['add_organization_request']))
    {
        $formValues = strStripSlashesDeep($_SESSION['add_organization_request']);
        unset($_SESSION['add_organization_request']);
    }
    else
    {
        $formValues['orgaShortName'] = '';
        $formValues['orgaLongName']  = '';
    }

    // show html header
    $gLayout['title'] = $gL10n->get('INS_ADD_ANOTHER_ORGANIZATION');
    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    // show individual module html content
    echo '
    <div class="formLayout" id="user_delete_message_form">
        <div class="formHead">'.$gL10n->get('INS_ADD_ANOTHER_ORGANIZATION').'</div>
        <div class="formBody">
            '.$gL10n->get('ORG_NEW_ORGANIZATION_DESC').'

            <form action="organization_function.php?mode=3" method="post">
                <div class="groupBox">
                    <div class="groupBoxHeadline">'.$gL10n->get('INS_NAME_OF_ORGANIZATION').'</div>
                    <div class="groupBoxBody">
                        <ul class="formFieldList">
                            <li>
                                <dl>
                                    <dt><label for="orgaShortName">'.$gL10n->get('SYS_NAME_ABBREVIATION').':</label></dt>
                                    <dd><input type="text" name="orgaShortName" id="orgaShortName" style="width: 80px;" maxlength="10" value="'.$formValues['orgaShortName'].'" /></dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for="orgaLongName">'.$gL10n->get('SYS_NAME').':</label></dt>
                                    <dd><input type="text" name="orgaLongName" id="orgaLongName" style="width: 250px;" maxlength="60" value="'.$formValues['orgaLongName'].'" /></dd>
                                </dl>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="formSubmit">
                    <button id="btnBack" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" />&nbsp;'.$gL10n->get('SYS_BACK').'</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button id="btnForward" type="submit"><img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('INS_SET_UP_ORGANIZATION').'" />&nbsp;'.$gL10n->get('INS_SET_UP_ORGANIZATION').'</button>
                </div>
            </form>
        </div>
    </div>';

    // show html footer
    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
    break;
    
case 3:
    /******************************************************/
    /* Create basic data for new organization in database */
    /******************************************************/
    $_SESSION['add_organization_request'] = strStripSlashesDeep($_POST);

    // form fields are not filled
    if(strlen($_POST['orgaShortName']) == 0
    || strlen($_POST['orgaLongName']) == 0 )
    {
        $gMessage->show($gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'));
    }

    // check if orga shortname exists
    $organization = new Organization($gDb, $_POST['orgaShortName']);
    if($organization->getValue('org_id') > 0)
    {
        $gMessage->show($gL10n->get('INS_ORGA_SHORTNAME_EXISTS', $_POST['orgaShortName']));
    }

    // set execution time to 2 minutes because we have a lot to do :)
    // there should be no error output because of safe mode
    @set_time_limit(120);

    $gDb->startTransaction();

    // create new organization
    $newOrganization = new Organization($gDb, $_POST['orgaShortName']);
    $newOrganization->setValue('org_longname', $_POST['orgaLongName']);
    $newOrganization->setValue('org_shortname', $_POST['orgaShortName']);
    $newOrganization->setValue('org_homepage', $_SERVER['HTTP_HOST']);
    $newOrganization->save();
    
    // write all preferences from preferences.php in table adm_preferences
    require_once('../../installation/db_scripts/preferences.php');

    // set the administrator email adress to the email of the current user
    $orga_preferences['email_administrator'] = $gCurrentUser->getValue('EMAIL');

    // create all necessary data for this organization
    $newOrganization->setPreferences($orga_preferences, false);
    $newOrganization->createBasicData($gCurrentUser->getValue('usr_id'));

	// if installation of second organization than show organization select at login
	if($gCurrentOrganization->countAllRecords() == 2)
	{
		$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = 1
				 WHERE prf_name = \'system_organization_select\' ';
		$gDb->query($sql);
	}

    $gDb->endTransaction();

    // show html header
    $gLayout['title'] = $gL10n->get('INS_SETUP_WAS_SUCCESSFUL');
    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    // show individual module html content
    echo '
    <div class="formLayout" id="user_delete_message_form" style="width: 400px">
        <div class="formHead">'.$gL10n->get('INS_SETUP_WAS_SUCCESSFUL').'</div>
        <div class="formBody">
            <p align="left"> '.$gL10n->get('ORG_ORGANIZATION_SUCCESSFULL_ADDED', $_POST['orgaLongName']).'</p>
            <button id="btnForward" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/administration/organization.php\'"><img src="'.THEME_PATH.'/icons/forward.png" alt="'.$gL10n->get('SYS_FORWARD').'" />&nbsp;'.$gL10n->get('SYS_FORWARD').'</button>
        </div>
    </div>';

    // show html footer
    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
    
    // clean up
    unset($_SESSION['add_organization_request']);
    break;
}
?>