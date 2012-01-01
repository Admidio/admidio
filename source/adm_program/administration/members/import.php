<?php
/******************************************************************************
 * Import assistant for user data
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/form_elements.php');

// nur berechtigte User duerfen User importieren
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

if(isset($_SESSION['import_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $form = $_SESSION['import_request'];
    unset($_SESSION['import_request']);
}
else
{
	$form['user_import_mode'] = 1;
	$form['import_coding']    = 'iso-8859-1';
	$form['import_role_id']   = 0;
}

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('MEM_IMPORT_USERS');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#userfile").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form id="form_import" action="'.$g_root_path.'/adm_program/administration/members/import_function.php" method="post" enctype="multipart/form-data">
<div class="formLayout" id="import_form">
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$gL10n->get('MEM_FORMAT').':</dt>
                    <dd>CSV</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="userfile">'.$gL10n->get('MEM_CHOOSE_FILE').':</label></dt>
                    <dd><input type="file" id="userfile" name="userfile" style="width: 90%" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="coding">'.$gL10n->get('MEM_CODING').':</label></dt>
					<dd>';
						$selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
						echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form['import_coding'], 'import_coding');
					echo '</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="import_role_id">'.$gL10n->get('MEM_ASSIGN_ROLE').':</label></dt>
                    <dd>';
                        // Combobox mit allen Rollen ausgeben, die der Benutzer sehen darf
                        // Rollen mit der Rollenzuordnungsberechtigung werden nur angezeigt, wenn der User die Rechte schon hat
                        echo FormElements::generateRoleSelectBox($form['import_role_id'],'import_role_id',1);

                        echo '&nbsp;
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MEM_ASSIGN_ROLE_FOR_IMPORT&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MEM_ASSIGN_ROLE_FOR_IMPORT\',this)" onmouseout="ajax_hideTooltip()"
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="user_import_mode">'.$gL10n->get('MEM_EXISTING_USERS').':</label>&nbsp;</dt>
                    <dd>';
                    	$selectBoxEntries = array(1 => $gL10n->get('MEM_NOT_EDIT'), 2 => $gL10n->get('MEM_DUPLICATE'), 3 => $gL10n->get('MEM_REPLACE'), 4 => $gL10n->get('MEM_COMPLEMENT'));
						echo FormElements::generateDynamicSelectBox($selectBoxEntries, $form['user_import_mode'], 'user_import_mode');
						echo '&nbsp;
						<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MEM_IDENTIFY_USERS&amp;inline=true"><img 
							onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MEM_IDENTIFY_USERS\',this)" onmouseout="ajax_hideTooltip()"
							class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
					</dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnBack" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" />&nbsp;'.$gL10n->get('SYS_BACK').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnForward" type="submit">'.$gL10n->get('SYS_NEXT').'&nbsp;<img src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_NEXT').'" /></button>
        </div>
    </div>
</div>
</form>';
    
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>