<?php
/**
 ***********************************************************************************************
 * Configure download folder rights
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid : UUID of the current folder to configure the rights
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string', array('requireValue' => true));

$headline = $gL10n->get('SYS_SET_FOLDER_PERMISSIONS');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('documents_files_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->adminDocumentsFiles()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

$rolesViewRightParentFolder = array();
$sqlRolesViewRight          = '';
$sqlRolesUploadRight        = '';

try {
    // get recordset of current folder from database
    $folder = new TableFolder($gDb);
    $folder->getFolderForDownload($getFolderUuid);

    // read parent folder
    if ($folder->getValue('fol_fol_id_parent')) {
        // get recordset of parent folder from database
        $parentFolder = new TableFolder($gDb, (int) $folder->getValue('fol_fol_id_parent'));
        $parentFolder->getFolderForDownload($parentFolder->getValue('fol_uuid'));

        // get assigned roles of the parent folder
        $rolesViewRightParentFolder = $parentFolder->getRoleViewArrayOfFolder();
        if (count($rolesViewRightParentFolder) > 0) {
            $sqlRolesViewRight = ' AND rol_id IN (' . Database::getQmForValues($rolesViewRightParentFolder) . ')';
        }
    }
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// if parent folder has access for all roles then read all roles from database
$sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                   FROM '.TBL_ROLES.'
             INNER JOIN '.TBL_CATEGORIES.'
                     ON cat_id = rol_cat_id
                  WHERE rol_valid  = true
                    AND rol_system = false
                        '.$sqlRolesViewRight.'
                    AND cat_org_id = ? -- $gCurrentOrgId
                    AND cat_name_intern <> \'EVENTS\'
               ORDER BY cat_sequence, rol_name';
$sqlDataView = array(
    'query'  => $sqlViewRoles,
    'params' => array_merge($rolesViewRightParentFolder, array($gCurrentOrgId))
);

$firstEntryViewRoles = '';

if (count($rolesViewRightParentFolder) === 0) {
    $firstEntryViewRoles = array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null);
}

// get assigned roles of this folder
$roleViewSet = $folder->getRoleViewArrayOfFolder();

// if no roles are assigned then set "all users" as default
if (count($roleViewSet) === 0) {
    $roleViewSet[] = 0;
}

// get assigned roles of this folder
$roleUploadSet = $folder->getRoleUploadArrayOfFolder();

// if no roles are assigned then set "all users" as default
if (count($roleUploadSet) === 0) {
    $roleUploadSet[] = '';
}

// read all download module administrator roles
$sqlAdminRoles = 'SELECT rol_name
                    FROM '.TBL_ROLES.'
              INNER JOIN '.TBL_CATEGORIES.'
                      ON cat_id = rol_cat_id
                   WHERE rol_valid    = true
                     AND rol_documents_files = true
                     AND cat_org_id   = ? -- $gCurrentOrgId
                ORDER BY cat_sequence, rol_name';
$statementAdminRoles = $gDb->queryPrepared($sqlAdminRoles, array($gCurrentOrgId));

$adminRoles = array();
while ($row = $statementAdminRoles->fetch()) {
    $adminRoles[] = $row['rol_name'];
}

// create html page object
$page = new HtmlPage('admidio-documents-files-config-folder', $headline);

$page->addHtml('<p class="lead">'.$gL10n->get('SYS_ROLE_ACCESS_PERMISSIONS_DESC', array($folder->getValue('fol_name'))).'</p>');

// show form
$form = new HtmlForm('folder_rights_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '7', 'folder_uuid' => $getFolderUuid)), $page);
$form->addSelectBoxFromSql(
    'adm_roles_view_right',
    $gL10n->get('SYS_VISIBLE_FOR'),
    $gDb,
    $sqlDataView,
    array(
        'property'     => HtmlForm::FIELD_REQUIRED,
        'defaultValue' => $roleViewSet,
        'multiselect'  => true,
        'firstEntry'   => $firstEntryViewRoles
    )
);
$form->addSelectBoxFromSql(
    'adm_roles_upload_right',
    $gL10n->get('SYS_UPLOAD_FILES'),
    $gDb,
    $sqlDataView,
    array(
        'property'     => HtmlForm::FIELD_REQUIRED,
        'defaultValue' => $roleUploadSet,
        'multiselect'  => true,
        'placeholder'  => $gL10n->get('SYS_NO_ADDITIONAL_PERMISSIONS_SET')
    )
);
$form->addStaticControl(
    'adm_administrators',
    $gL10n->get('SYS_ADMINISTRATORS'),
    implode(', ', $adminRoles),
    array('helpTextIdLabel' => $gL10n->get('SYS_ADMINISTRATORS_DESC', array($gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'))))
);
$form->addSubmitButton(
    'btn_save',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
