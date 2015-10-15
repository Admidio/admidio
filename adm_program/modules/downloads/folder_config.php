<?php
/******************************************************************************
 * Configure download folder rights
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
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
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $g_organization));
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
$parentRoleSet = array();

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

    // get assigned roles of the parent folder
    $parentRoleSet = $parentFolder->getRoleArrayOfFolder(true);
}

if (count($parentRoleSet) === 0)
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
    $rolesStatement = $gDb->query($sql_roles);

    $parentRoleSet[] = array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null);

    while($row_roles = $rolesStatement->fetchObject())
    {
        //Jede Rolle wird nun dem Array hinzugefuegt
        $parentRoleSet[] = array($row_roles->rol_id, $row_roles->rol_name, $row_roles->cat_name);
    }
}
else
{
    // create new array with numeric keys for logic of method addSelectBox
    $newParentRoleSet = array();

    foreach($parentRoleSet as $role)
    {
        $newParentRoleSet[] = array($role['rol_id'], $role['rol_name'], null);
    }

    $parentRoleSet = $newParentRoleSet;
}

// get assigned roles of this folder
$roleSet = $folder->getRoleArrayOfFolder();

// if no roles are assigned then set "all users" as default
if(count($roleSet) === 0)
{
    $roleSet[] = 0;
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$folderConfigMenu = $page->getMenu();
$folderConfigMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<p class="lead">'.$gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS_DESC', $folder->getValue('fol_name')).'</p>');

// show form
$form = new HtmlForm('folder_rights_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id='.$getFolderId, $page);
$form->addSelectBox('adm_allowed_roles', $gL10n->get('DAT_VISIBLE_TO'), $parentRoleSet, array('property' => FIELD_REQUIRED,
                    'defaultValue' => $roleSet, 'multiselect' => true));
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png', 'class' => ' col-sm-offset-3'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
?>
