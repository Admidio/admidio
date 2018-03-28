<?php
/**
 ***********************************************************************************************
 * Enter firstname and surname and checks if member already exists
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

echo '
<script type="text/javascript">
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#form_members_create_user:first *:input[type!=hidden]:first").focus();
    });

    $("#form_members_create_user").submit(function(event) {
        var action = $(this).attr("action");
        var formMembersAlert = $("#form_members_create_user .form-alert");
        formMembersAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {
                    formMembersAlert.attr("class", "alert alert-success form-alert");
                    formMembersAlert.html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('MEM_USER_COULD_BE_CREATED').'</strong>");
                    formMembersAlert.fadeIn("slow");
                    setTimeout(function() {
                        self.location.href = "'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => 1)).'&lastname=" + $("#lastname").val() + "&firstname=" + $("#firstname").val();
                    }, 2500);
                } else {
                    if (data.length > 1000) {
                        $(".modal-body").html(data);
                    } else {
                        formMembersAlert.attr("class", "alert alert-danger form-alert");
                        formMembersAlert.fadeIn();
                        formMembersAlert.html("<span class=\"glyphicon glyphicon-exclamation-sign\"></span>" + data);
                    }
                }
            }
        });
    });
</script>

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('MEM_CREATE_USER').'</h4>
</div>
<div class="modal-body">
    <p class="lead">'.$gL10n->get('MEM_INPUT_FIRSTNAME_LASTNAME').'</p>';

    $form = new HtmlForm('form_members_create_user', ADMIDIO_URL.FOLDER_MODULES.'/members/members_assign.php');
    $form->addInput(
        'lastname', $gL10n->get('SYS_LASTNAME'), '',
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
    );
    $form->addInput(
        'firstname', $gL10n->get('SYS_FIRSTNAME'), '',
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
    );
    $form->addSubmitButton(
        'btn_add', $gL10n->get('MEM_CREATE_USER'),
        array('icon' => THEME_URL.'/icons/add.png', 'class' => ' col-sm-offset-3')
    );
    echo $form->show();
echo '</div>';
