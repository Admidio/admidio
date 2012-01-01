<?php
/******************************************************************************
 * Ordnerberechtigungen konfigurieren
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Ordner Id des uebergeordneten Ordners
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_folder.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', null, true);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

//Folderobject erstellen
$folder = new TableFolder($gDb);
$folder->getFolderForDownload($getFolderId);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$folder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//NavigationsLink erhalten
$navigationBar = $folder->getNavigationForDownload();

//Parentordner holen
$parentRoleSet = null;
if ($folder->getValue('fol_fol_id_parent')) {
    $parentFolder = new TableFolder($gDb);
    $parentFolder->getFolderForDownload($folder->getValue('fol_fol_id_parent'));
    //Rollen des uebergeordneten Ordners holen
    $parentRoleSet = $parentFolder->getRoleArrayOfFolder();

}

if ($parentRoleSet == null) 
{
	//wenn der uebergeordnete Ordner keine Rollen gesetzt hat sind alle erlaubt
	//alle aus der DB aus lesen
	$sql_roles = 'SELECT *
					 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
					WHERE rol_valid  = 1
					  AND rol_system = 0
					  AND rol_cat_id = cat_id
					  AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					ORDER BY rol_name';
	$result_roles = $gDb->query($sql_roles);

	while($row_roles = $gDb->fetch_object($result_roles))
	{
		//Jede Rolle wird nun dem Array hinzugefuegt
		$parentRoleSet[] = array(
							'rol_id'        => $row_roles->rol_id,
							'rol_name'      => $row_roles->rol_name);
	}
}


//aktuelles Rollenset des Ordners holen
$roleSet = $folder->getRoleArrayOfFolder();

// Html-Kopf ausgeben
$gLayout['title'] = $gL10n->get('DOW_SET_FOLDER_PERMISSIONS');

$gLayout['header'] = '
    <script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#fol_public").focus();
	 	});

        // Scripts fuer Rollenbox
        function hinzufuegen()
        {
            var allowed_roles = document.getElementById("admAllowedRoles");
            var denied_roles  = document.getElementById("admDeniedRoles");

            if (denied_roles.selectedIndex >= 0) {
                NeuerEintrag = new Option(denied_roles.options[denied_roles.selectedIndex].text, denied_roles.options[denied_roles.selectedIndex].value, false, true);
                denied_roles.options[denied_roles.selectedIndex] = null;
                allowed_roles.options[allowed_roles.length] = NeuerEintrag;
            }
        }

        function entfernen()
        {
            var allowed_roles = document.getElementById("admAllowedRoles");
            var denied_roles  = document.getElementById("admDeniedRoles");

            if (allowed_roles.selectedIndex >= 0)
            {
                NeuerEintrag = new Option(allowed_roles.options[allowed_roles.selectedIndex].text, allowed_roles.options[allowed_roles.selectedIndex].value, false, true);
                allowed_roles.options[allowed_roles.selectedIndex] = null;
                denied_roles.options[denied_roles.length] = NeuerEintrag;
            }
        }

        function absenden()
        {
            var allowed_roles = document.getElementById("admAllowedRoles");

            allowed_roles.multiple = true;

            for (var i = 0; i < allowed_roles.options.length; i++)
            {
                allowed_roles.options[i].selected = true;
            }

            $("#admFormFolderRights").submit();
        }
    //--></script>';


require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form id="admFormFolderRights" method="post" action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id='.$getFolderId.'">
<div class="formLayout" id="edit_download_folder_form" >
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody">'.
        $navigationBar.'
        <div class="groupBox">
            <div class="groupBoxBody" >
                <ul class="formFieldList">
                    <li>
                        <div>
                            <input type="checkbox" id="fol_public" name="fol_public" ';
                            if($folder->getValue('fol_public') == 0)
                            {
                                echo ' checked="checked" ';
                            }
                            if($folder->getValue('fol_fol_id_parent') && $parentFolder->getValue('fol_public') == 0)
                            {
                                echo ' disabled="disabled" ';
                            }
                            echo ' value="0" onclick="showHideBlock(\'admRolesBox\', \'\', \'\');" />
                            <label for="fol_public"><img src="'. THEME_PATH. '/icons/lock.png" alt="'.$gL10n->get('DOW_NO_PUBLIC_ACCESS').'" /></label>&nbsp;
                            <label for="fol_public">'.$gL10n->get('DOW_NO_PUBLIC_ACCESS').'</label>
                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_PUBLIC_DOWNLOAD_FLAG&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_PUBLIC_DOWNLOAD_FLAG\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';

                            //Der Wert der DisabledCheckbox muss mit einem versteckten Feld uebertragen werden.
                            if($folder->getValue('fol_fol_id_parent') && $parentFolder->getValue('fol_public') == 0)
                            {
                                echo '<input type=hidden id="fol_public_hidden" name="fol_public" value='. $parentFolder->getValue('fol_public'). ' />';
                            }

                        echo '
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="groupBox" id="admRolesBox" ';
            if($folder->getValue('fol_public') == 1)
            {
                echo ' style="display: none;" ';
            }
            echo '>
            <div class="groupBoxHeadline">'.$gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS').'</div>
            <div class="groupBoxBody" ><p>'.$gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS_DESC').'</p>
                <div style="text-align: left; float: left;">
                    <div><img class="iconInformation" src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DOW_NO_ACCESS').'" title="'.$gL10n->get('DOW_NO_ACCESS').'" />'.$gL10n->get('DOW_NO_ACCESS').'</div>
                    <div>
                        <select id="admDeniedRoles" size="8" style="width: 200px;">';
                        for($i=0; $i < count($parentRoleSet); $i++) 
                        {
                            $nextRole = $parentRoleSet[$i];

                            if ($roleSet == null || in_array($nextRole, $roleSet) == false) 
                            {
                                echo '<option value="'. $nextRole['rol_id']. '">'. $nextRole['rol_name']. '</option>';
                            }
                        }

                        echo '
                        </select>
                    </div>
                </div>
                <div style="float: left;" class="verticalIconList">
                    <ul>
                        <li>
                            <a class="iconLink" href="javascript:hinzufuegen()"><img 
                                src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_ADD_ROLE').'" title="'.$gL10n->get('SYS_ADD_ROLE').'" /></a>
                        </li>
                        <li>
                            <a class="iconLink" href="javascript:entfernen()"><img
                                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_REMOVE_ROLE').'" title="'.$gL10n->get('SYS_REMOVE_ROLE').'" /></a>
                        </li>
                    </ul>
                </div>
                <div>
                    <div><img class="iconInformation" src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DOW_ACCESS_ALLOWED').'" title="'.$gL10n->get('DOW_ACCESS_ALLOWED').'" />'.$gL10n->get('DOW_ACCESS_ALLOWED').'</div>
                    <div>
                        <select id="admAllowedRoles" name="AllowedRoles[]" size="8" style="width: 200px;">';
                        for($i=0; $i<count($roleSet); $i++) {

                            $nextRole = $roleSet[$i];
                            echo '<option value="'. $nextRole['rol_id']. '">'. $nextRole['rol_name']. '</option>';
                        }
                        echo '
                        </select>
                    </div>
                </div>
            </div>
        </div>


        <div class="formSubmit">
            <button id="btnSave" type="button" onclick="absenden()">
            <img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />
            &nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>