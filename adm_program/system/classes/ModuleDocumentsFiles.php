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
     * @var array Array with all read folders and files
     */
    protected $unregisteredFoldersFiles = array();
    /**
     * @var TableFolder Object of the current folder
     */
    protected $folder;

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @throws SmartyException
     * @throws Exception
     */
    public function createContentList()
    {
        global $gCurrentUser, $gL10n;

        $templateData = array();
        $infoAlert = '';

        if ($gCurrentUser->adminDocumentsFiles()) {
            $infoAlert = $gL10n->get('SYS_DOCUMENTS_FILES_ROLES_VIEW', array(implode(', ', $this->folder->getViewRolesNames())));
        }

        foreach($this->data as $row) {
            $templateRow = array();

            if ($row['folder']) {
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('folder_uuid' => $row['uuid']));
                $templateRow['icon'] = 'fas fa-fw fa-folder-open';
                $templateRow['title'] = $gL10n->get('SYS_FOLDER');

                if ($this->folder->hasUploadRight()) {
                    if ($gCurrentUser->adminDocumentsFiles()) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_config.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'fas fa-lock',
                            'tooltip' => $gL10n->get('SYS_PERMISSIONS')
                        );
                    }
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'fas fa-edit',
                            'tooltip' => $gL10n->get('SYS_EDIT_FOLDER')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/move.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'fas fa-folder',
                            'tooltip' => $gL10n->get('SYS_MOVE_FOLDER')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php',
                                array('type' => 'fol', 'element_id' => 'row_'.$row['uuid'], 'name' => $row['name'], 'database_id' => $row['uuid'])),
                        'icon' => 'fas fa-trash-alt',
                        'tooltip' => $gL10n->get('SYS_DELETE_FOLDER')
                    );
                }
            } else {
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/get_file.php', array('file_uuid' => $row['uuid'], 'view' => 1));
                $templateRow['icon'] = 'fas fa-fw '.FileSystemUtils::getFileFontAwesomeIcon($row['name']);
                $templateRow['title'] = $gL10n->get('SYS_FILE');

                if ($this->folder->hasUploadRight()) {
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $this->folder->getValue('fol_uuid'), 'file_uuid' => $row['uuid'])),
                            'icon' => 'fas fa-edit',
                            'tooltip' => $gL10n->get('SYS_EDIT')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/move.php', array('folder_uuid' => $this->folder->getValue('fol_uuid'), 'file_uuid' => $row['uuid'])),
                            'icon' => 'fas fa-folder',
                            'tooltip' => $gL10n->get('SYS_MOVE_FILE')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php',
                            array('type' => 'fil', 'element_id' => 'row_'.$row['uuid'], 'name' => $row['name'], 'database_id' => $row['uuid'], 'database_id_2' => $this->folder->getValue('fol_uuid'))),
                        'icon' => 'fas fa-trash-alt',
                        'tooltip' => $gL10n->get('SYS_DELETE_FILE')
                    );
                }

            }

            $templateRow['id'] = $row['uuid'];
            $templateRow['folder'] = $row['folder'];
            $templateRow['name'] = $row['name'];
            $templateRow['description'] = $row['description'];
            $templateRow['timestamp'] = $row['timestamp'];
            $templateRow['size'] = $row['size'];
            $templateRow['counter'] = $row['counter'];
            $templateRow['existsInFileSystem'] = $row['existsInFileSystem'];

            $templateData[] = $templateRow;
        }

        $templateUnregisteredData = array();

        foreach($this->unregisteredFoldersFiles as $row) {
            $templateRow = array();

            if ($row['folder']) {
                $templateRow['icon'] = 'fas fa-fw fa-folder';
                $templateRow['title'] = $gL10n->get('SYS_FOLDER');

            } else {
                $templateRow['icon'] = 'fas fa-fw '.FileSystemUtils::getFileFontAwesomeIcon($row['name']);
                $templateRow['title'] = $gL10n->get('SYS_FILE');
            }

            $templateRow['folder'] = $row['folder'];
            $templateRow['name'] = $row['name'];
            $templateRow['size'] = $row['size'];
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '6', 'folder_uuid' => $this->folder->getValue('fol_uuid'), 'name' => $row['name']));

            $templateUnregisteredData[] = $templateRow;
        }

        // initialize and set the parameter for DataTables
        $dataTables = new HtmlDataTables($this, 'documents-files-table');
        $dataTables->disableDatatablesColumnsSort(array(1, 6));
        $dataTables->setDatatablesColumnsNotHideResponsive(array(6));
        $dataTables->createJavascript(count($this->data), 6);

        $this->assign('infoAlert', $infoAlert);
        $this->assign('list', $templateData);
        $this->assign('unregisteredList', $templateUnregisteredData);
        $this->assign('l10n', $gL10n);
        $this->pageContent .= $this->fetch('modules/documents-files.list.tpl');
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The method will start at the
     * top documents and files folder of the current organization. The returned array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     * @throws Exception
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
     * @throws Exception
     */
    public function readData(string $folderUUID)
    {
        global $gDb, $gSettingsManager;

        $this->folder = new TableFolder($gDb);
        $this->folder->readDataByUuid($folderUUID);

        $this->data = array();
        $completeFolder = array(
            'folders' => $this->folder->getSubfoldersWithProperties(),
            'files'   => $this->folder->getFilesWithProperties()
        );

        $completeFolder = $this->folder->addAdditionalToFolderContents($completeFolder);

        foreach($completeFolder['folders'] as $folder) {
            $this->data[] = array(
                'folder' => true,
                'uuid' => $folder['fol_uuid'],
                'name' => $folder['fol_name'],
                'description' => (string) $folder['fol_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $folder['fol_timestamp'])
                    ->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
                'size' => null,
                'counter' => null,
                'existsInFileSystem' => $folder['fol_exists']
            );
        }

        foreach($completeFolder['files'] as $file) {
            $this->data[] = array(
                'folder' => false,
                'uuid' => $file['fil_uuid'],
                'name' => $file['fil_name'],
                'description' => (string) $file['fil_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $file['fil_timestamp'])
                    ->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
                'size' => round($file['fil_size'] / 1024). ' kB&nbsp;',
                'counter' => $file['fil_counter'],
                'existsInFileSystem' => $file['fil_exists']
            );
        }

        if (array_key_exists('additionalFolders', $completeFolder)) {
            foreach ($completeFolder['additionalFolders'] as $folder) {
                $this->unregisteredFoldersFiles[] = array(
                    'folder' => true,
                    'name' => $folder['fol_name'],
                    'size' => null
                );
            }
        }

        if (array_key_exists('additionalFiles', $completeFolder)) {
            foreach ($completeFolder['additionalFiles'] as $file) {
                $this->unregisteredFoldersFiles[] = array(
                    'folder' => false,
                    'name' => $file['fil_name'],
                    'size' => round($file['fil_size'] / 1024). ' kB&nbsp;'
                );
            }
        }
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @param string $folderID ID if the folder where the upload rights should be checked.
     * @param array<string,string> $arrAllUploadableFolders Array with all currently read uploadable folders. That
     *                         array will be supplemented with new folders of this method and then returned.
     * @param string $indent The current indent for the visualisation of the folder structure.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     * @throws Exception
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
