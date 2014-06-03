<?php
/******************************************************************************
 * Change password
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id           : Id of the user whose password should be changed
 * mode    - html   : Default mode to show a html form to change the password
 *           change : Change password in database
 *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

header('Content-type: text/html; charset=utf-8'); 

$gMessage->showThemeBody(false);
 
// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', 'html', false, array('html', 'change'));

$user = new User($gDb, $gProfileFields, $getUserId);

// only the own password could be individual set. Webmaster could only send a generated password.
if(isMember($getUserId) == false
|| ($gCurrentUser->isWebmaster() == false && $gCurrentUser->getValue('usr_id') != $getUserId)
|| ($gCurrentUser->isWebmaster() == true  && strlen($user->getValue('usr_login_name')) > 0 && strlen($user->getValue('EMAIL')) > 0) && $gPreferences['enable_system_mails'] == 1)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}


if($getMode == 'change')
{
    /***********************************************************************/
    /* Formular verarbeiten */
    /***********************************************************************/
    if($gCurrentUser->isWebmaster() && $gCurrentUser->getValue('usr_id') != $getUserId )
    {
        $_POST['old_password'] = '';
    }
    
    if( (strlen($_POST['old_password']) > 0 || $gCurrentUser->isWebmaster() )
    && strlen($_POST['new_password']) > 0
    && strlen($_POST['new_password_confirm']) > 0)
    {
        if(strlen($_POST['new_password']) > 5)
        {
            if ($_POST['new_password'] == $_POST['new_password_confirm'])
            {
                // check if old password is correct. 
                // Webmaster could change password of other users without this verification.
                if($user->checkPassword($_POST['old_password']) || $gCurrentUser->isWebmaster() && $gCurrentUser->getValue('usr_id') != $getUserId )
                {
                    $user->setValue('usr_password', $_POST['new_password']);
                    $user->save();

                    // Paralell im Forum aendern, wenn Forum aktiviert ist
                    if($gPreferences['enable_forum_interface'])
                    {
                        $gForum->userSave($user->getValue('usr_login_name'), $user->getValue('usr_password'), $user->getValue('EMAIL'), '', 3);
                    }

                    // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
                    if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
                    {
                        $gCurrentUser->setValue('usr_password', $user->getValue('usr_password'));
                    }

                    $gMessage->setForwardUrl('javascript:self.parent.tb_remove()');
                    $phrase = $gL10n->get('PRO_PASSWORD_CHANGED')."<SAVED/>";
                }
                else
                {
                    $phrase = $gL10n->get('PRO_PASSWORD_OLD_WRONG');
                }
            }
            else
            {
                $phrase = $gL10n->get('PRO_PASSWORDS_NOT_EQUAL');
            }
        }
        else
        {
            $phrase = $gL10n->get('PRO_PASSWORD_LENGTH');
        }
    }
    else
    {
        $phrase = $gL10n->get('SYS_FIELDS_EMPTY');
    }

    echo $phrase;
}
elseif($getMode == 'html')
{
    /***********************************************************************/
    /* Show password form */
    /***********************************************************************/

    // show headline 
    echo '
    <div class="admPopupWindow">
        <h1 class="admHeadline">'.$gL10n->get('PRO_EDIT_PASSWORD').'</h1>';
        // show form
        $form = new HtmlForm('password_form', $g_root_path. '/adm_program/modules/profile/password.php?usr_id='.$getUserId.'&amp;mode=change');
        $form->addPasswordInput('old_password', $gL10n->get('PRO_CURRENT_PASSWORD'), FIELD_MANDATORY);
        $form->addLine();
        $form->addPasswordInput('new_password', $gL10n->get('PRO_NEW_PASSWORD'), FIELD_MANDATORY, 'PRO_PASSWORD_DESCRIPTION');
        $form->addPasswordInput('new_password_confirm', $gL10n->get('SYS_REPEAT'), FIELD_MANDATORY);
        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
        $form->show();
    echo '</div>';
}

?>