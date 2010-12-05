<?php
/******************************************************************************
 * Anlegen neuer Mitglieder
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

echo '
<script type="text/javascript"><!--
function send()
{
    lastname = document.getElementById("lastname").value;
    firstname = document.getElementById("firstname").value;
    if(lastname.length > 0 && firstname.length > 0)
    {
        document.getElementById("frmMembersCreateUser").action  = gRootPath + "/adm_program/administration/members/members_assign.php?lastname=" + lastname + "&firstname=" + firstname;
        document.getElementById("frmMembersCreateUser").submit();
    }
    else
    {
        alert("'.$g_l10n->get('SYS_FIELDS_EMPTY').'");
    }
}
//--></script>

<form id="frmMembersCreateUser" method="post" action="'.$g_root_path.'/adm_program/administration/members/members_assign.php" >
<div class="formLayout">
    <div class="formHead">'. $g_l10n->get('MEM_CREATE_USER'). '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>'.$g_l10n->get('MEM_INPUT_FIRSTNAME_LASTNAME').'</li>
            <li>
                <dl>
                    <dt><label for="lastname">'.$g_l10n->get('SYS_LASTNAME').':</label></dt>
                    <dd>
                        <input type="text" id="lastname" name="lastname" style="width: 300px;" tabindex="1" maxlength="100" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="firstname">'.$g_l10n->get('SYS_FIRSTNAME').':</label></dt>
                    <dd>
                        <input type="text" id="firstname" name="firstname" style="width: 300px;" tabindex="1" maxlength="100" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnAdd" type="button" onclick="send()"><img src="'.THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get('MEM_CREATE_USER').'" />&nbsp;'.$g_l10n->get('MEM_CREATE_USER').'</button>
        </div>
    </div>
</form>';
?>