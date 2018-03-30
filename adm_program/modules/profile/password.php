<?php
/**
 ***********************************************************************************************
 * Change password
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id           : Id of the user whose password should be changed
 * mode    - html   : Default mode to show a html form to change the password
 *           change : Change password in database
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

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
    $gMessage->showInModalWindow();
}

$user = new User($gDb, $gProfileFields, $getUserId);
$currUsrId = (int) $gCurrentUser->getValue('usr_id');

// only the own password could be individual set.
// Administrator could only send a generated password or set a password if no password was set before
if((int) $gCurrentUser->getValue('usr_id') !== $getUserId
&& (!isMember($getUserId)
|| (!$gCurrentUser->isAdministrator() && $currUsrId !== $getUserId)
|| ($gCurrentUser->isAdministrator() && $user->getValue('EMAIL') !== '' && $gSettingsManager->getBool('enable_system_mails'))))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if($getMode === 'change')
{
    if($gCurrentUser->isAdministrator() && $currUsrId !== $getUserId)
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
    if(($oldPassword !== '' || $gCurrentUser->isAdministrator())
    &&  $newPassword !== '' && $newPasswordConfirm !== '')
    {
        if(strlen($newPassword) >= PASSWORD_MIN_LENGTH)
        {
            if (PasswordUtils::passwordStrength($newPassword, $user->getPasswordUserData()) >= $gSettingsManager->getInt('password_min_strength'))
            {
                if ($newPassword === $newPasswordConfirm)
                {
                    // check if old password is correct.
                    // Administrator could change password of other users without this verification.
                    if (PasswordUtils::verify($oldPassword, $user->getValue('usr_password'))
                    || ($gCurrentUser->isAdministrator() && $currUsrId !== $getUserId))
                    {
                        $user->saveChangesWithoutRights();
                        $user->setPassword($newPassword);
                        $user->save();

                        // if password of current user changed, then update value in current session
                        if ($currUsrId === (int) $user->getValue('usr_id'))
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
                $phrase = $gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH');
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

    $zxcvbnUserInputs = json_encode($user->getPasswordUserData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $passwordStrengthLevel = 1;
    if ($gSettingsManager->getInt('password_min_strength'))
    {
        $passwordStrengthLevel = $gSettingsManager->getInt('password_min_strength');
    }

    echo '<script type="text/javascript">
        $(function() {
            $("body").on("shown.bs.modal", ".modal", function() {
                $("#password_form:first *:input[type!=hidden]:first").focus();

                $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4 * '.$passwordStrengthLevel.')");

                $("#new_password").keyup(function(e) {
                    var result = zxcvbn(e.target.value, ' . $zxcvbnUserInputs . ');
                    var cssClasses = ["progress-bar-danger", "progress-bar-danger", "progress-bar-warning", "progress-bar-info", "progress-bar-success"];

                    var progressBar = $("#admidio-password-strength .progress-bar");
                    progressBar.attr("aria-valuenow", result.score * 25);
                    progressBar.css("width", result.score * 25 + "%");
                    progressBar.removeClass(cssClasses.join(" "));
                    progressBar.addClass(cssClasses[result.score]);
                });
            });

            $("#password_form").submit(function(event) {
                var action = $(this).attr("action");
                var passwordFormAlert = $("#password_form .form-alert");
                passwordFormAlert.hide();

                // disable default form submit
                event.preventDefault();

                $.post(action, $(this).serialize(), function(data) {
                    if (data === "success") {
                        passwordFormAlert.attr("class", "alert alert-success form-alert");
                        passwordFormAlert.html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('PRO_PASSWORD_CHANGED').'</strong>");
                        passwordFormAlert.fadeIn("slow");
                        setTimeout(function() {
                            $("#admidio_modal").modal("hide");
                        }, 2000);
                    } else {
                        passwordFormAlert.attr("class", "alert alert-danger form-alert");
                        passwordFormAlert.fadeIn();
                        passwordFormAlert.html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                    }
                });
            });
        });
    </script>

    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">'.$gL10n->get('PRO_EDIT_PASSWORD').'</h4>
    </div>
    <div class="modal-body">';
        // show form
        $form = new HtmlForm('password_form', safeUrl(ADMIDIO_URL. FOLDER_MODULES.'/profile/password.php', array('usr_id' => $getUserId, 'mode' => 'change')));
        if($currUsrId === $getUserId)
        {
            // to change own password user must enter the valid old password for verification
            // TODO Future: 'minLength' => PASSWORD_MIN_LENGTH
            $form->addInput(
                'old_password', $gL10n->get('PRO_CURRENT_PASSWORD'), '',
                array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED)
            );
            $form->addLine();
        }
        $form->addInput(
            'new_password', $gL10n->get('PRO_NEW_PASSWORD'), '',
            array(
                'type'             => 'password',
                'property'         => HtmlForm::FIELD_REQUIRED,
                'minLength'        => PASSWORD_MIN_LENGTH,
                'passwordStrength' => true,
                'passwordUserData' => $user->getPasswordUserData(),
                'helpTextIdInline' => 'PRO_PASSWORD_DESCRIPTION'
            )
        );
        $form->addInput(
            'new_password_confirm', $gL10n->get('SYS_REPEAT'), '',
            array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
        );
        $form->addSubmitButton(
            'btn_save', $gL10n->get('SYS_SAVE'),
            array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
        );
        echo $form->show();
    echo '</div>';
}
