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
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'change')));

// in ajax mode only return simple text on error
if($getMode == 'change')
{
    $gMessage->showHtmlTextOnly(true);
}

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

                    // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
                    if($user->getValue('usr_id') == $gCurrentUser->getValue('usr_id'))
                    {
                        $gCurrentUser->setValue('usr_password', $_POST['new_password']);
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
elseif($getMode == 'html')
{
    /***********************************************************************/
    /* Show password form */
    /***********************************************************************/

    // show headline 
    echo '<script type="text/javascript"><!--
    $(document).ready(function(){
        $("body").on("shown.bs.modal", ".modal", function () { $("#password_form:first *:input[type!=hidden]:first").focus(); });
        
        $("#password_form").submit(function(event) {
            var action = $(this).attr("action");
            $("#password_form .form-alert").hide();
        
            // disable default form submit
            event.preventDefault();
            
            $.ajax({
                type:    "POST",
                url:     action,
                data:    $(this).serialize(),
                success: function(data) {
                    if(data == "success") {
                        $("#password_form .form-alert").attr("class", "alert alert-success form-alert");
                        $("#password_form .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('PRO_PASSWORD_CHANGED').'</strong>");
                        $("#password_form .form-alert").fadeIn("slow");
                        setTimeout("$(\"#admidio_modal\").modal(\"hide\");",2000);	
                    }
                    else {
                        $("#password_form .form-alert").attr("class", "alert alert-danger form-alert");
                        $("#password_form .form-alert").fadeIn();
                        $("#password_form .form-alert").html("<span class=\"glyphicon glyphicon-remove\"></span>"+data);
                    }
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
        $form->addInput('old_password', $gL10n->get('PRO_CURRENT_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_MANDATORY));
        $form->addLine();
        $form->addInput('new_password', $gL10n->get('PRO_NEW_PASSWORD'), null, array('type' => 'password', 'property' => FIELD_MANDATORY, 'helpTextIdLabel' => 'PRO_PASSWORD_DESCRIPTION'));
        $form->addInput('new_password_confirm', $gL10n->get('SYS_REPEAT'), null, array('type' => 'password', 'property' => FIELD_MANDATORY));
        $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png', 'class' => ' col-sm-offset-3'));
        $form->show();
    echo '</div>';
}

?>