<?php
/******************************************************************************
 * Import-Assistent fuer Benutzerdaten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show($g_l10n->get('SYS_PHR_SERVER_NO_UPLOAD'));
}

// Html-Kopf ausgeben
$g_layout['title']  = 'Benutzer importieren';
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
    <div class="formHead">Benutzer aus Datei importieren</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>Format:</dt>
                    <dd>CSV</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="userfile">Datei ausw&auml;hlen:</label></dt>
                    <dd><input id="userfile" name="userfile" size="30" type="file" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="rol_id">Rolle zuordnen:</label></dt>
                    <dd>';
                        // Combobox mit allen Rollen ausgeben, die der Benutzer sehen darf
                        // Rollen mit der Rollenzuordnungsberechtigung werden nur angezeigt, wenn der User die Rechte schon hat
                        echo generateRoleSelectBox(0,'',1);

                        echo '&nbsp;
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=role_assign&amp;inline=true"><img 
			                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=role_assign\',this)" onmouseout="ajax_hideTooltip()"
			                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <label for="user_import_mode">Bereits existierende Benutzer</label>&nbsp;
                <select size="1" id="user_import_mode" name="user_import_mode">
                    <option value="1" selected="selected">nicht bearbeiten</option>
                    <option value="2">duplizieren</option>
                    <option value="3">ersetzen</option>
                    <option value="4">ergänzen</option>
                </select>&nbsp;
                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MEM_PHR_IDENTIFY_USERS&amp;inline=true"><img 
	                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MEM_PHR_IDENTIFY_USERS\',this)" onmouseout="ajax_hideTooltip()"
	                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button name="zurueck" type="button" value="zurueck" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="Zurück" />&nbsp;Zurück</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id="weiter" type="submit" value="weiter" tabindex="2">Weiter&nbsp;<img src="'. THEME_PATH. '/icons/forward.png" alt="Weiter" /></button>
        </div>
    </div>
</div>
</form>';
    
require(THEME_SERVER_PATH. '/overall_footer.php');

?>