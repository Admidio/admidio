<?php
/******************************************************************************
 * Anlegen neuer Mitglieder
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

echo '
<script type="text/javascript"><!--
function send()
{
    lastname = document.getElementById("lastname").value;
    firstname = document.getElementById("firstname").value;
    if(lastname.length > 0 && firstname.length > 0)
    {
        document.getElementById("admFormMembersCreateUser").action  = gRootPath + "/adm_program/administration/members/members_assign.php?lastname=" + lastname + "&firstname=" + firstname;
        document.getElementById("admFormMembersCreateUser").submit();
    }
    else
    {
		jQueryAlert("SYS_FIELDS_EMPTY");
    }
}
//--></script>

<form id="admFormMembersCreateUser" method="post" action="'.$g_root_path.'/adm_program/administration/members/members_assign.php" >
<div class="formLayout">
    <div class="formHead">'. $gL10n->get('MEM_CREATE_USER'). '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>'.$gL10n->get('MEM_INPUT_FIRSTNAME_LASTNAME').'</li>
            <li>
                <dl>
                    <dt><label for="lastname">'.$gL10n->get('SYS_LASTNAME').':</label></dt>
                    <dd>
                        <input type="text" id="lastname" name="lastname" style="width: 300px;" maxlength="100" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="firstname">'.$gL10n->get('SYS_FIRSTNAME').':</label></dt>
                    <dd>
                        <input type="text" id="firstname" name="firstname" style="width: 300px;" maxlength="100" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnAdd" type="button" onclick="send()"><img src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('MEM_CREATE_USER').'" />&nbsp;'.$gL10n->get('MEM_CREATE_USER').'</button>
        </div>
    </div>
</form>';
?>