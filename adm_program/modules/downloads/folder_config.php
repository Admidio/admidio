<?php
/**
 ***********************************************************************************************
 * Configure download folder rights
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_id : Id of the current folder to configure the rights
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int', array('requireValue' => true));

$headline = $gL10n->get('DOW_SET_FOLDER_PERMISSIONS');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

try
{
    // get recordset of current folder from database
    $folder = new TableFolder($gDb);
    $folder->getFolderForDownload($getFolderId);

    // Parentordner holen
    $rolesViewRightParentFolder = array();
    $rolesUploadRightParentFolder = array();
    $sqlRolesViewRight = '';
    $sqlRolesUploadRight = '';

    if ($folder->getValue('fol_fol_id_parent'))
    {
        // get recordset of parent folder from database
        $parentFolder = new TableFolder($gDb);
        $parentFolder->getFolderForDownload($folder->getValue('fol_fol_id_parent'));

        // get assigned roles of the parent folder
        $rolesViewRightParentFolder = $parentFolder->getRoleViewArrayOfFolder();
        if(count($rolesViewRightParentFolder) > 0)
        {
            $sqlRolesViewRight = ' AND rol_id IN ('.implode(',', $rolesViewRightParentFolder).')';
        }

        // get assigned roles of the parent folder
        $rolesUploadRightParentFolder = $parentFolder->getRoleUploadArrayOfFolder();
        if(count($rolesUploadRightParentFolder) > 0)
        {
            $sqlRolesUploadRight = ' AND rol_id IN ('.implode(',', $rolesUploadRightParentFolder).')';
        }
    }
}
catch(AdmException $e)
{
    $e->showHtml();
}

// wenn der uebergeordnete Ordner keine Rollen gesetzt hat sind alle erlaubt
// alle aus der DB aus lesen
$sqlRoles =  'SELECT *
                FROM '.TBL_ROLES.'
          INNER JOIN '.TBL_CATEGORIES.'
                  ON cat_id = rol_cat_id
               WHERE rol_valid  = 1
                 AND rol_system = 0
                     '.$sqlRolesViewRight.'
                 AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
            ORDER BY rol_name';
$rolesViewStatement = $gDb->query($sqlRoles);

if (count($rolesViewRightParentFolder) === 0)
{
    $parentRoleViewSet[] = array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null);
}

while($rowViewRoles = $rolesViewStatement->fetchObject())
{
    // Jede Rolle wird nun dem Array hinzugefuegt
    $parentRoleViewSet[] = array($rowViewRoles->rol_id, $rowViewRoles->rol_name, $rowViewRoles->cat_name);
}

// get assigned roles of this folder
$roleViewSet = $folder->getRoleViewArrayOfFolder();

// if no roles are assigned then set "all users" as default
if(count($roleViewSet) === 0)
{
    $roleViewSet[] = 0;
}

// wenn der uebergeordnete Ordner keine Rollen gesetzt hat sind alle erlaubt
// alle aus der DB aus lesen
$sqlRoles =  'SELECT *
                FROM '.TBL_ROLES.'
          INNER JOIN '.TBL_CATEGORIES.'
                  ON cat_id = rol_cat_id
               WHERE rol_valid  = 1
                 AND rol_system = 0
                     '.$sqlRolesUploadRight.'
                 AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
            ORDER BY rol_name';
$rolesUploadStatement = $gDb->query($sqlRoles);

while($rowUploadRoles = $rolesUploadStatement->fetchObject())
{
    // Jede Rolle wird nun dem Array hinzugefuegt
    $parentRoleUploadSet[] = array($rowUploadRoles->rol_id, $rowUploadRoles->rol_name, $rowUploadRoles->cat_name);
}

// get assigned roles of this folder
$roleUploadSet = $folder->getRoleUploadArrayOfFolder();

// if no roles are assigned then set "all users" as default
if(count($roleUploadSet) === 0)
{
    $roleUploadSet[] = '';
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$folderConfigMenu = $page->getMenu();
$folderConfigMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<p class="lead">'.$gL10n->get('DOW_ROLE_ACCESS_PERMISSIONS_DESC', $folder->getValue('fol_name'), $gL10n->get('ROL_RIGHT_DOWNLOAD')).'</p>');

// show form
$form = new HtmlForm('folder_rights_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id='.$getFolderId, $page);
$form->addSelectBox(
    'adm_roles_view_right',
    $gL10n->get('DAT_VISIBLE_TO'),
    $parentRoleViewSet,
    array(
        'property'     => FIELD_REQUIRED,
        'defaultValue' => $roleViewSet,
        'multiselect'  => true
    )
);
$form->addSelectBox(
    'adm_roles_upload_right',
    $gL10n->get('DOW_UPLOAD_FILES'),
    $parentRoleUploadSet,
    array(
        'property'     => FIELD_REQUIRED,
        'defaultValue' => $roleUploadSet,
        'multiselect'  => true
    )
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon'  => THEME_PATH.'/icons/disk.png',
                                                                  'class' => ' col-sm-offset-3'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
