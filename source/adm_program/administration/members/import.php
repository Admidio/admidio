<?php
/******************************************************************************
 * Import-Assistent fuer Benutzerdaten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show($g_l10n->get('SYS_SERVER_NO_UPLOAD'));
}

// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('MEM_IMPORT_USERS');
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#userfile").focus();
        }); 
    //--></script>';
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form id="form_import" action="'.$g_root_path.'/adm_program/administration/members/import_function.php" method="post" enctype="multipart/form-data">
<div class="formLayout" id="import_form">
    <div class="formHead">'.$g_layout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$g_l10n->get('MEM_FORMAT').':</dt>
                    <dd>CSV</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="userfile">'.$g_l10n->get('MEM_CHOOSE_FILE').':</label></dt>
                    <dd><input id="userfile" name="userfile" size="30" type="file" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="coding">'.$g_l10n->get('MEM_CODING').':</label></dt>
                <dd><select size="1" name="coding">
                        <option value="ansi" selected="selected">'.$g_l10n->get('MEM_ANSI').'</option>
                        <option value="utf8">'.$g_l10n->get('MEM_UTF8').'</option>
                    </select></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="rol_id">'.$g_l10n->get('MEM_ASSIGN_ROLE').':</label></dt>
                    <dd>';
                        // Combobox mit allen Rollen ausgeben, die der Benutzer sehen darf
                        // Rollen mit der Rollenzuordnungsberechtigung werden nur angezeigt, wenn der User die Rechte schon hat
                        echo generateRoleSelectBox(0,'',1);

                        echo '&nbsp;
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MEM_ASSIGN_ROLE_FOR_IMPORT&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MEM_ASSIGN_ROLE_FOR_IMPORT\',this)" onmouseout="ajax_hideTooltip()"
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="user_import_mode">'.$g_l10n->get('MEM_EXISTING_USERS').':</label>&nbsp;</dt>
                    <dd><select size="1" id="user_import_mode" name="user_import_mode">
                        <option value="1" selected="selected">'.$g_l10n->get('MEM_NOT_EDIT').'</option>
                        <option value="2">'.$g_l10n->get('MEM_DUPLICATE').'</option>
                        <option value="3">'.$g_l10n->get('MEM_REPLACE').'</option>
                        <option value="4">'.$g_l10n->get('MEM_COMPLEMENT').'</option>
                    </select>&nbsp;
                    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MEM_IDENTIFY_USERS&amp;inline=true"><img 
                        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MEM_IDENTIFY_USERS\',this)" onmouseout="ajax_hideTooltip()"
                        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a></dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnBack" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" />&nbsp;'.$g_l10n->get('SYS_BACK').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnForward" type="submit" tabindex="2">'.$g_l10n->get('SYS_NEXT').'&nbsp;<img src="'. THEME_PATH. '/icons/forward.png" alt="'.$g_l10n->get('SYS_NEXT').'" /></button>
        </div>
    </div>
</div>
</form>';
    
require(THEME_SERVER_PATH. '/overall_footer.php');

?>