<?php
/**
 ***********************************************************************************************
 * Change password
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id           : Id of the user whose password should be changed
 * mode    - html   : Default mode to show a html form to change the password
 *           change : Change password in database
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

header('Content-type: text/html; charset=utf-8');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'int',    array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'string', array('defaultValue' => 'html', 'validValues' => array('html', 'change')));

// in ajax mode only return simple text on error
if($getMode === 'change')
{
    $gMessage->showHtmlTextOnly(true);
}
else
{
    $gMessage->showInModaleWindow();
}

$user = new User($gDb, $gProfileFields, $getUserId);

// only the own password could be individual set.
// Webmaster could only send a generated password or set a password if no password was set before
if(!isMember($getUserId)
|| (!$gCurrentUser->isWebmaster() && $gCurrentUser->getValue('usr_id') != $getUserId)
|| ($gCurrentUser->isWebmaster() && $user->getValue('usr_password') !== '' && $user->getValue('EMAIL') === '' && $gPreferences['enable_system_mails'] == 1))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if($getMode === 'change')
{
    if($gCurrentUser->isWebmaster() && $gCurrentUser->getValue('usr_id') != $getUserId)
    {
        $oldPassword = '';
    }
    else
    {
        $oldPassword = $_POST['old_password'];
    }

    $newPassword        = $_POST['new_password'];
    $newPasswordConfirm = $_POST['new_password_confirm'];

    /***********************************************************************/
    /* Handle form input */
    /***********************************************************************/
    if(($oldPassword !== '' || $gCurrentUser->isWebmaster())
    &&  $newPassword !== '' && $newPasswordConfirm !== '')
    {
        if(strlen($newPassword) >= 8)
        {
            if ($newPassword === $newPasswordConfirm)
            {
                // check if old password is correct.
                // Webmaster could change password of other users without this verification.
                if(PasswordHashing::verify($oldPassword, $user->getValue('usr_password')) || $gCurrentUser->isWebmaster() && $gCurrentUser->getValue('usr_id') != $getUserId)
                {
                    $user->setPassword($newPassword);
                    $user->save();

                    // if password of current user changed, then update value in current session
                    if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
                    {
                        $gCurrentUser->setPassword($newPassword);
                    }

                    $phrase = 'success';
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
elseif($getMode === 'html')
{
    /***********************************************************************/
    /* Show password form */
    /***********************************************************************/

    echo '<script type="text/javascript"><!--
    $(document).ready(function() {
        $("body").on("shown.bs.modal", ".modal", function () { $("#password_form:first *:input[type!=hidden]:first").focus(); });

        $("#password_form").submit(function(event) {
            var action = $(this).attr("action");
            $("#password_form .form-alert").hide();

            // disable default form submit
            event.preventDefault();

            $.post(action, $(this).serialize(), function(data) {
                if(data === "success") {
                    $("#password_form .form-alert").attr("class", "alert alert-success form-alert");
                    $("#password_form .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('PRO_PASSWORD_CHANGED').'</strong>");
                    $("#password_form .form-alert").fadeIn("slow");
                    setTimeout("$(\"#admidio_modal\").modal(\"hide\");",2000);
                } else {
                    $("#password_form .form-alert").attr("class", "alert alert-danger form-alert");
                    $("#password_form .form-alert").fadeIn();
                    $("#password_form .form-alert").html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>"+data);
                }
            });
        });
    });
    --></script>

    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">'.$gL10n->get('PRO_EDIT_PASSWORD').'</h4>
    </div>
    <div class="modal-body">';
        // show form
        $form = new HtmlForm('password_form', $g_root_path. '/adm_program/modules/profile/password.php?usr_id='.$getUserId.'&amp;mode=change');
        if($gCurrentUser->getValue('usr_id') == $getUserId)
        {
            // to change own password user must enter the valid old password for verification
            // TODO Future: 'minLength' => 8
            $form->addInput('old_password', $gL10n->get('PRO_CURRENT_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_REQUIRED));
            $form->addLine();
        }
        $form->addInput('new_password', $gL10n->get('PRO_NEW_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_REQUIRED, 'minLength' => 8, 'helpTextIdInline' => 'PRO_PASSWORD_DESCRIPTION'));
        $form->addInput('new_password_confirm', $gL10n->get('SYS_REPEAT'), null, array('type' => 'password', 'property' => FIELD_REQUIRED, 'minLength' => 8));
        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
        $form->show();
    echo '</div>';
}
