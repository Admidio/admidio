<?php
/******************************************************************************
 * Save organization preferences
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode     : 1 - Save organization preferences
 *            2 - show welcome dialog for new organization
 *            3 - create new organization
 *            4 - show phpinfo()
 * form         - The name of the form preferences that were submitted.
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('defaultValue' => 1));
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
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
    $checkboxes = array();

    try
    {
        // first check the fields of the submitted form

        switch($getForm)
        {
            case 'common':
                $checkboxes = array('enable_rss','enable_auto_login','enable_password_recovery','system_js_editor_enabled','system_search_similar');

                if(admStrIsValidFileName($_POST['theme']) == false
                || file_exists(SERVER_PATH. '/adm_themes/'.$_POST['theme'].'/index.html') == false)
                {
                    $gMessage->show($gL10n->get('ORG_INVALID_THEME'));
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

            case 'organization':
                $checkboxes = array('system_organization_select');

                if(strlen($_POST['org_longname']) == 0)
                {
                    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
                }
                break;

            case 'regional_settings':
                if(admStrIsValidFileName($_POST['system_language']) == false
                || file_exists(SERVER_PATH. '/adm_program/languages/'.$_POST['system_language'].'.xml') == false)
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

            case 'registration':
                $checkboxes = array('enable_registration_captcha', 'enable_registration_admin_mail');
                break;

            case 'email_dispatch':
                $checkboxes = array('mail_sender_into_to', 'mail_smtp_auth');
                break;

            case 'system_notification':
                $checkboxes = array('enable_system_mails', 'enable_email_notification');

                if(strlen($_POST['email_administrator']) == 0)
                {
                    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS')));
                }
                else
                {
                    $_POST['email_administrator'] = admStrToLower($_POST['email_administrator']);
                    if(!strValidCharacters($_POST['email_administrator'], 'email'))
                    {
                        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS')));
                    }
                }
                break;

            case 'captcha':
                break;

            case 'announcements':
                break;

            case 'user_management':
                $checkboxes = array('members_show_all_users');
                break;

            case 'downloads':
                $checkboxes = array('enable_download_module');
                break;

            case 'guestbook':
                $checkboxes = array('enable_guestbook_captcha', 'enable_gbook_comments4all', 'enable_intial_comments_loading');
                break;

            case 'lists':
                $checkboxes = array('lists_hide_overview_details');
                break;

            case 'messages':
                $checkboxes = array('enable_mail_module', 'enable_pm_module', 'enable_pm_module', 'enable_mail_captcha', 'mail_html_registered_users', 'mail_into_to');

                if(strlen($_POST['mail_sendmail_address']) > 0)
                {
                    $_POST['mail_sendmail_address'] = admStrToLower($_POST['mail_sendmail_address']);
                    if(!strValidCharacters($_POST['mail_sendmail_address'], 'email'))
                    {
                        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('MAI_SENDER_EMAIL')));
                    }
                }
                break;

            case 'photos':
                $checkboxes = array('photo_download_enabled', 'photo_keep_original');
                break;

            case 'profile':
                $checkboxes = array('profile_log_edit_fields', 'profile_show_map_link', 'profile_show_roles', 'profile_show_former_roles', 'profile_show_extern_roles');
                break;

            case 'events':
                $checkboxes = array('enable_dates_ical', 'dates_show_map_link', 'dates_show_rooms');
                break;

            case 'links':
                if(is_numeric($_POST['weblinks_redirect_seconds']) == false || $_POST['weblinks_redirect_seconds'] < 0)
                {
                    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('LNK_DISPLAY_REDIRECT')));
                }
                break;

            case 'inventory':
                break;

            default:
                $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
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
    $gCurrentOrganization->save();

    $gCurrentOrganization->setPreferences($gPreferences);

    // refresh language if necessary
    if($gL10n->getLanguage() != $gPreferences['system_language'])
    {
        $gL10n->setLanguage($gPreferences['system_language']);
    }

    // clean up
    $gCurrentSession->renewOrganizationObject();

    echo 'success';
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
        $formValues['orgaEmail']     = '';
    }

    $headline = $gL10n->get('INS_ADD_ORGANIZATION');

    // create html page object
    $page = new HtmlPage($headline);

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // add back link to module menu
    $organizationNewMenu = $page->getMenu();
    $organizationNewMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    $page->addHtml('<p class="lead">'.$gL10n->get('ORG_NEW_ORGANIZATION_DESC').'</p>');

    // show form
    $form = new HtmlForm('add_new_organization_form', $g_root_path.'/adm_program/modules/preferences/preferences_function.php?mode=3', $page);
    $form->addInput('orgaShortName', $gL10n->get('SYS_NAME_ABBREVIATION'), $formValues['orgaShortName'], array('maxLength' => 10, 'property' => FIELD_MANDATORY, 'class' => 'form-control-small'));
    $form->addInput('orgaLongName', $gL10n->get('SYS_NAME'), $formValues['orgaLongName'], array('maxLength' => 50, 'property' => FIELD_MANDATORY));
    $form->addInput('orgaEmail', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $formValues['orgaEmail'], array('type' => 'email', 'maxLength' => 50, 'property' => FIELD_MANDATORY));
    $form->addSubmitButton('btn_foward', $gL10n->get('INS_SET_UP_ORGANIZATION'), array('icon' => THEME_PATH.'/icons/database_in.png', 'class' => ' col-sm-offset-3'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
    break;

case 3:
    /******************************************************/
    /* Create basic data for new organization in database */
    /******************************************************/
    $_SESSION['add_organization_request'] = strStripSlashesDeep($_POST);

    // form fields are not filled
    if(strlen($_POST['orgaShortName']) == 0
    || strlen($_POST['orgaLongName']) == 0)
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
    $orga_preferences['email_administrator'] = $_POST['orgaEmail'];

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

    // create html page object
    $page = new HtmlPage();

    // add headline and title of module
    $page->addHeadline($gL10n->get('INS_SETUP_WAS_SUCCESSFUL'));

    $page->addHtml('<p class="lead">'.$gL10n->get('ORG_ORGANIZATION_SUCCESSFULL_ADDED', $_POST['orgaLongName']).'</p>');

    // show form
    $form = new HtmlForm('add_new_organization_form', $g_root_path.'/adm_program/modules/preferences/preferences.php', $page);
    $form->addSubmitButton('btn_foward', $gL10n->get('SYS_NEXT'), array('icon' => THEME_PATH.'/icons/forward.png'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();


    // clean up
    unset($_SESSION['add_organization_request']);
    break;

case 4:
    // show php info page
    echo phpinfo();
    break;
}
?>
