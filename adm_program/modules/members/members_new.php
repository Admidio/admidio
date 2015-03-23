<?php
/******************************************************************************
 * Enter firstname and surname and checks if member already exists
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

echo '
<script type="text/javascript"><!--
$(document).ready(function(){
    $("body").on("shown.bs.modal", ".modal", function () { $("#form_members_create_user:first *:input[type!=hidden]:first").focus(); });

    $("#form_members_create_user").submit(function(event) {
        var action = $(this).attr("action");
        $("#form_members_create_user .form-alert").hide();
    
        // disable default form submit
        event.preventDefault();
        
        $.ajax({
            type:    "POST",
            url:     action,
            data:    $(this).serialize(),
            success: function(data) {
                if(data == "success") {
                    $("#form_members_create_user .form-alert").attr("class", "alert alert-success form-alert");
                    $("#form_members_create_user .form-alert").html("<span class=\"glyphicon glyphicon-ok\"></span><strong>'.$gL10n->get('MEM_USER_COULD_BE_CREATED').'</strong>");
                    $("#form_members_create_user .form-alert").fadeIn("slow");
                    setTimeout(function () {
                        self.location.href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname=" + $("#lastname").val() + "&firstname=" + $("#firstname").val();
                    },2500);	
                }
                else {
                    if(data.length > 1000) {
                        $("#popup_members_new").html(data);
                    }
                    else {
                        $("#form_members_create_user .form-alert").attr("class", "alert alert-danger form-alert");
                        $("#form_members_create_user .form-alert").fadeIn();
                        $("#form_members_create_user .form-alert").html("<span class=\"glyphicon glyphicon-remove\"></span>"+data);
                    }
                }
            }
        });    
    });
});
//--></script>

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('MEM_CREATE_USER').'</h4>
</div>
<div class="modal-body">    
    <p class="lead">'.$gL10n->get('MEM_INPUT_FIRSTNAME_LASTNAME').'</p>';
    
    $form = new HtmlForm('form_members_create_user', $g_root_path.'/adm_program/modules/members/members_assign.php');
    $form->addInput('lastname', $gL10n->get('SYS_LASTNAME'), null, array('maxLength' => 100, 'property' => FIELD_MANDATORY));
    $form->addInput('firstname', $gL10n->get('SYS_FIRSTNAME'), null, array('maxLength' => 100, 'property' => FIELD_MANDATORY));
    $form->addSubmitButton('btn_add', $gL10n->get('MEM_CREATE_USER'), array('icon' => THEME_PATH.'/icons/add.png', 'class' => ' col-sm-offset-3'));
    $form->show();
echo '</div>';

?>