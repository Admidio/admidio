<?php
/******************************************************************************
 * Configure download folder rights
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Id of the current folder to configure the rights
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', array('requireValue' => true));

$headline = $gL10n->get('DOW_SET_FOLDER_PERMISSIONS');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

try
{
    // get recordset of current folder from databse
    $folder = new TableFolder($gDb);
    $folder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
	$e->showHtml();
}

//Parentordner holen
$parentRoleSet = null;

if ($folder->getValue('fol_fol_id_parent')) 
{
    try
    {
        // get recordset of parent folder from databse
        $parentFolder = new TableFolder($gDb);
        $parentFolder->getFolderForDownload($folder->getValue('fol_fol_id_parent'));
    }
    catch(AdmException $e)
    {
    	$e->showHtml();
    }

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

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('$("#fol_public").click(function() {showBlock("adm_roles_box");});
                      $("#btn_save").click(function () {sendForm();});', true);
$page->addJavascript('
    function showBlock(elementID) {
        if($("#" + elementID).css("display") == "none") {
            $("#" + elementID).show("slow");
        }
        else {
            $("#" + elementID).hide("slow");
        }
    }


    // add all selected roles from the denied box to the allowed box
    function addRoles() {
        $("#adm_denied_roles option:selected").each(function () {
            $("#adm_allowed_roles").append(
                $("<option></option>").val(this.value).html(this.text)
            );
            this.remove();
        });
    }

    // add all selected roles from the allowed box to the denied box
    function removeRoles() {
        $("#adm_allowed_roles option:selected").each(function () {
            $("#adm_denied_roles").append(
                $("<option></option>").val(this.value).html(this.text)
            );
            this.remove();
        });
    }

    function sendForm() {
        var allowed_roles = document.getElementById("adm_allowed_roles");

        allowed_roles.multiple = true;

        for (var i = 0; i < allowed_roles.options.length; i++) {
            allowed_roles.options[i].selected = true;
        }

        $("#adm_form_folder_rights").submit();
    }');

// add back link to module menu
$folderConfigMenu = $page->getMenu();
$folderConfigMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('adm_form_folder_rights', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id='.$getFolderId, $page, array('type' => 'vertical'));
$fieldMode = FIELD_DEFAULT;

if($folder->getValue('fol_fol_id_parent') && $parentFolder->getValue('fol_public') == 0)
{
    $fieldMode = FIELD_DISABLED;
}

if($folder->getValue('fol_public') == 0)
{
    $checkboxValue = 1;
}
else
{
    $checkboxValue = 0;
    $page->addJavascript('$("#adm_roles_box").hide();', true);
}

$htmlRoleSelection = '
    <div class="col-sm-5 form-group">
        <label for="adm_denied_roles"><img class="admidio-icon-info" src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DOW_NO_ACCESS').'" title="'.$gL10n->get('DOW_NO_ACCESS').'" />'.$gL10n->get('DOW_NO_ACCESS').'</label>
        <select id="adm_denied_roles" name="DeniedRoles" class="form-control" multiple="multiple" size="8" style="max-width: 300px;">';
        for($i=0; $i < count($parentRoleSet); $i++) 
        {
            $nextRole = $parentRoleSet[$i];

            if ($roleSet == null || in_array($nextRole, $roleSet) == false) 
            {
                $htmlRoleSelection .= '<option value="'. $nextRole['rol_id']. '">'. $nextRole['rol_name']. '</option>';
            }
        }

        $htmlRoleSelection .= '
        </select>
    </div>
    <div class="col-sm-2" style="text-align: center;">
        <br /><br /><br />
        <a class="admidio-icon-link" href="javascript:removeRoles()"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_REMOVE_ROLE').'" title="'.$gL10n->get('SYS_REMOVE_ROLE').'" /></a>
        <a class="admidio-icon-link" href="javascript:addRoles()"><img 
            src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_ADD_ROLE').'" title="'.$gL10n->get('SYS_ADD_ROLE').'" /></a>
    </div>
    <div class="col-sm-5 form-group">
        <label for="adm_allowed_roles"><img class="admidio-icon-info" src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DOW_ACCESS_ALLOWED').'" title="'.$gL10n->get('DOW_ACCESS_ALLOWED').'" />'.$gL10n->get('DOW_ACCESS_ALLOWED').'</label>
        <select id="adm_allowed_roles" name="AllowedRoles[]" class="form-control" multiple="multiple" size="8" style="max-width: 300px;">';
        for($i=0; $i<count($roleSet); $i++) {

            $nextRole = $roleSet[$i];
            $htmlRoleSelection .= '<option value="'. $nextRole['rol_id']. '">'. $nextRole['rol_name']. '</option>';
        }
        $htmlRoleSelection .= '
        </select>
    </div>';

$form->addCheckbox('fol_public', $gL10n->get('DOW_NO_PUBLIC_ACCESS'), $checkboxValue, array('property' => $fieldMode, 'helpTextIdLabel' => 'DOW_PUBLIC_DOWNLOAD_FLAG', 'icon' => THEME_PATH. '/icons/lock.png'));
$form->openGroupBox('adm_roles_box', $gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS'));
$form->addDescription($gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS_DESC'));
$form->addCustomContent(null, $htmlRoleSelection);
$form->closeGroupBox();
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
?>