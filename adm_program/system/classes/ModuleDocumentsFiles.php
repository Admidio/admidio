<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the documents and files module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleDocumentsFiles('admidio-groups-roles', $headline);
 * $page->createContentRegistrationList();
 * $page->show();
 * ```
 */
class ModuleDocumentsFiles extends HtmlPage
{
    /**
     * @var array Array with all read folders and files
     */
    protected $data = array();

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @throws SmartyException|AdmException
     */
    public function createContentList()
    {
        global $gSettingsManager, $gCurrentUser, $gL10n, $gDb;

        $templateData = array();

        foreach($this->data as $row) {
            $templateRow = array();
            /*
            $templateRow['category'] = $row['cat_name'];
            $templateRow['categoryOrder'] = $row['cat_sequence'];
            $templateRow['role'] = $row['rol_name'];
            $templateRow['roleUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $row['rol_uuid']));
            $templateRow['roleRights'] = array();
            if ($role->getValue('rol_assign_roles') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-user-tie', 'title' => $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'));
            }
            if ($role->getValue('rol_all_lists_view') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-list', 'title' => $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'));
            }
            if ($role->getValue('rol_approve_users') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-address-card', 'title' => $gL10n->get('SYS_RIGHT_APPROVE_USERS'));
            }
            if ($role->getValue('rol_mail_to_all') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-envelope', 'title' => $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'));
            }
            if ($role->getValue('rol_edit_user') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-user-friends', 'title' => $gL10n->get('SYS_RIGHT_EDIT_USER'));
            }
            if ($role->getValue('rol_profile') == 1) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-user', 'title' => $gL10n->get('SYS_RIGHT_PROFILE'));
            }
            if ($role->getValue('rol_announcements') == 1 && (int) $gSettingsManager->get('announcements_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-newspaper', 'title' => $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'));
            }
            if ($role->getValue('rol_dates') == 1 && (int) $gSettingsManager->get('events_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-calendar-alt', 'title' => $gL10n->get('SYS_RIGHT_DATES'));
            }
            if ($role->getValue('rol_photo') == 1 && (int) $gSettingsManager->get('photo_module_enabled') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-image', 'title' => $gL10n->get('SYS_RIGHT_PHOTOS'));
            }
            if ($role->getValue('rol_documents_files') == 1 && (int) $gSettingsManager->getBool('documents_files_module_enabled')) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-download', 'title' => $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'));
            }
            if ($role->getValue('rol_guestbook') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'fas fas fa-book', 'title' => $gL10n->get('SYS_RIGHT_GUESTBOOK'));
            }
            if ($role->getValue('rol_guestbook_comments') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0 && !$gSettingsManager->getBool('enable_gbook_comments4all')) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-comment', 'title' => $gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS'));
            }
            if ($role->getValue('rol_weblinks') == 1 && (int) $gSettingsManager->get('enable_weblinks_module') > 0) {
                $templateRow['roleRights'][] = array('icon' => 'fas fa-link', 'title' => $gL10n->get('SYS_RIGHT_WEBLINKS'));
            }

            switch ($role->getValue('rol_mail_this_role')) {
                case 0:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['emailToThisRole'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['emailToThisRole'] = $gL10n->get('SYS_ALSO_VISITORS');
                    break;
            }

            switch ($role->getValue('rol_view_memberships')) {
                case 0:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['viewMembership'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_LEADERS');
                    break;
            }

            switch ($role->getValue('rol_view_members_profiles')) {
                case 0:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('SYS_NOBODY');
                    break;
                case 1:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('SYS_ROLE_MEMBERS');
                    break;
                case 2:
                    $templateRow['viewMembersProfiles'] = $gL10n->get('ORG_REGISTERED_USERS');
                    break;
                case 3:
                    $templateRow['viewMembership'] = $gL10n->get('SYS_LEADERS');
                    break;
            }

            switch ($role->getValue('rol_leader_rights')) {
                case 0:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_NO_ADDITIONAL_RIGHTS');
                    break;
                case 1:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_ASSIGN_MEMBERS');
                    break;
                case 2:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_EDIT_MEMBERS');
                    break;
                case 3:
                    $templateRow['roleLeaderRights'] = $gL10n->get('SYS_ASSIGN_EDIT_MEMBERS');
                    break;
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $row['rol_id'])),
                'icon' => 'fas fa-list-alt',
                'tooltip' => $gL10n->get('SYS_SHOW_ROLE_MEMBERSHIP')
            );
            if ($this->roleType === $this::ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                $templateRow['actions'][] = array(
                    'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'row_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                    'icon' => 'fas fa-user-check',
                    'tooltip' => $gL10n->get('SYS_ACTIVATE_ROLE')
                );
            } elseif ($this->roleType === $this::ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                $templateRow['actions'][] = array(
                    'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'row_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                    'icon' => 'fas fa-user-slash',
                    'tooltip' => $gL10n->get('SYS_DEACTIVATE_ROLE')
                );
            }
            $templateRow['actions'][] = array(
                'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'row_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                'icon' => 'fas fa-trash-alt',
                'tooltip' => $gL10n->get('SYS_DELETE_ROLE')
            );
            $templateData[] = $templateRow;
            */
        }

        // initialize and set the parameter for DataTables
        $dataTables = new HtmlDataTables($this, 'documents-files-table');
        $dataTables->setDatatablesGroupColumn(1);
        $dataTables->disableDatatablesColumnsSort(array(3, 8));
        $dataTables->setDatatablesColumnsNotHideResponsive(array(8));
        $dataTables->createJavascript(count($this->data), 7);

        $this->assign('list', $templateData);
        $this->assign('l10n', $gL10n);
        $this->pageContent .= $this->fetch('modules/documents-files.list.tpl');
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The method will start at the
     * top documents and files folder of the current organization. The returned array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     */
    public function getUploadableFolderStructure(): array
    {
        global $gCurrentOrgId, $gDb, $gL10n;

        // read main documents folder
        $sqlFiles = 'SELECT fol_uuid, fol_id
                       FROM '.TBL_FOLDERS.'
                      WHERE fol_fol_id_parent IS NULL
                        AND fol_org_id = ? -- $gCurrentOrgId
                        AND fol_type   = \'DOCUMENTS\' ';
        $filesStatement = $gDb->queryPrepared($sqlFiles, array($gCurrentOrgId));

        $row = $filesStatement->fetch();

        $arrAllUploadableFolders = array($row['fol_uuid'] => $gL10n->get('SYS_DOCUMENTS_FILES'));

        return $this->readFoldersWithUploadRights($row['fol_id'], $arrAllUploadableFolders);
    }

    /**
     * Creates an array with all available folders and files.
     * @param string $folderUUID The UUID of the folder whose folders and files should be read.
     */
    public function readData(string $folderUUID = '')
    {
        global $gDb;

        $currentFolder = new TableFolder($gDb);
        $currentFolder->readDataByUuid($folderUUID);

        $this->data = array();
        $completeFolder = array(
            'folders' => $currentFolder->getSubfoldersWithProperties(),
            'files'   => $currentFolder->getFilesWithProperties()
        );

        foreach($completeFolder['folders'] as $folder) {
            $this->data[] = array(
                'folder' => true,
                'uuid' => $folder['fol_uuid'],
                'name' => $folder['fol_name'],
                'timestamp' => $folder['fol_timestamp'],
                'size' => 0,
                'counter' => 0,
                'existsInFileSystem' => $folder['fol_exists']
            );
        }

        foreach($completeFolder['files'] as $file) {
            $this->data[] = array(
                'folder' => false,
                'uuid' => $file['fil_uuid'],
                'name' => $file['fil_name'],
                'timestamp' => $file['fil_timestamp'],
                'size' => $file['fil_size'],
                'counter' => $file['fil_counter'],
                'existsInFileSystem' => $folder['fil_exists']
            );
        }
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @param string $folderID ID if the folder where the upload rights should be checked.
     * @param array<string,string> $arrAllUploadableFolders Array with all currently read uploadable folders. That
     *                         array will be supplemented with new folders of this method and then returned.
     * @param string $indent   The current indent for the visualisation of the folder structure.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     */
    private function readFoldersWithUploadRights(string $folderID, array $arrAllUploadableFolders, string $indent = ''): array
    {
        global $gDb;

        // read all folders
        $sqlFiles = 'SELECT fol_id
                       FROM '.TBL_FOLDERS.'
                      WHERE  fol_fol_id_parent = ? -- $folderID ';
        $filesStatement = $gDb->queryPrepared($sqlFiles, array($folderID));

        while($row = $filesStatement->fetch()) {
            $folder = new TableFolder($gDb, $row['fol_id']);

            if ($folder->hasUploadRight()) {
                $arrAllUploadableFolders[$folder->getValue('fol_uuid')] = $indent.'- '.$folder->getValue('fol_name');

                $arrAllUploadableFolders = $this->readFoldersWithUploadRights($row['fol_id'], $arrAllUploadableFolders, $indent.'&nbsp;&nbsp;&nbsp;');
            }
        }
        return $arrAllUploadableFolders;
    }
}
