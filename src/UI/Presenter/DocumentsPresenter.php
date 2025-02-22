<?php

namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use HtmlDataTables;

/**
 * @brief Class with methods to display the module pages of the registration.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class DocumentsPresenter extends PagePresenter
{
    /**
     * @var string UUID of the folder for which the topics should be filtered.
     */
    protected string $folderUUID = '';
    /**
     * @var array Array with all read forum topics and their first post.
     */
    protected array $data = array();
    /**
     * @var Folder Object of the current folder
     */
    protected Folder $folder;

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $folderUUID UUID of the folder for which the documents should be filtered.
     * @throws Exception
     */
    public function __construct(string $folderUUID = '')
    {
        global $gDb;

        $this->folderUUID = $folderUUID;
        $this->folder = new Folder($gDb);
        $this->folder->getFolderForDownload($this->folderUUID);

        parent::__construct($folderUUID);
    }

    /**
     * Read all available folders and documents from the database and show an HTML list with them. For administrators
     * also the unregistered folders and files are shown.
     * @throws Exception
     * @throws \DateMalformedStringException|\Smarty\Exception
     */
    public function createList(): void
    {
        global $gCurrentUser, $gCurrentSession, $gL10n, $gDb, $gSettingsManager;

        // get recordset of current folder from database
        $currentFolder = new Folder($gDb);
        $currentFolder->getFolderForDownload($this->folderUUID);


        $this->setHtmlID('adm_documents_files');
        // set headline of the script
        if ($currentFolder->getValue('fol_fol_id_parent') == null) {
            $this->setHeadline($gL10n->get('SYS_DOCUMENTS_FILES'));
        } else {
            $this->setHeadline($currentFolder->getValue('fol_name'));
        }

        $documentService = new DocumentsService($gDb, $currentFolder->getValue('fol_uuid'));

        if ($currentFolder->hasUploadRight()) {
            // upload only possible if upload filesize > 0
            if ($gSettingsManager->getInt('documents_files_max_upload_size') > 0) {
                // show links for upload, create folder and folder configuration
                $this->addPageFunctionsMenuItem(
                    'menu_item_documents_upload_files',
                    $gL10n->get('SYS_UPLOAD_FILES'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/system/file_upload.php', array('module' => 'documents_files', 'uuid' => $currentFolder->getValue('fol_uuid'))),
                    'bi-upload'
                );

                $this->addPageFunctionsMenuItem(
                    'menu_item_documents_create_folder',
                    $gL10n->get('SYS_CREATE_FOLDER'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_new.php', array('folder_uuid' => $currentFolder->getValue('fol_uuid'))),
                    'bi-plus-circle-fill'
                );

                if ($currentFolder->getValue('fol_fol_id_parent') > 0) {
                    $this->addPageFunctionsMenuItem(
                        'menu_item_documents_edit_folder',
                        $gL10n->get('SYS_EDIT_FOLDER'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $currentFolder->getValue('fol_uuid'))),
                        'bi-pencil-square'
                    );
                }
            }

            if ($gCurrentUser->administrateDocumentsFiles()) {
                $this->addPageFunctionsMenuItem(
                    'menu_item_documents_permissions',
                    $gL10n->get('SYS_PERMISSIONS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_permissions.php', array('folder_uuid' => $currentFolder->getValue('fol_uuid'))),
                    'bi-shield-lock-fill'
                );
            }
            ChangelogService::displayHistoryButton($this, 'folder', 'folders,files,roles_rights_data', !empty($getAnnUuid), array('uuid' => $this->folderUUID));

        }

        $data = $documentService->findAll();
        $templateData = array();
        $infoAlert = '';

        if ($gCurrentUser->administrateDocumentsFiles()) {
            $infoAlert = $gL10n->get('SYS_DOCUMENTS_FILES_ROLES_VIEW', array(implode(', ', $this->folder->getViewRolesNames())));
        }

        // create array with all registered folders and files
        foreach($data as $row) {
            $templateRow = array();

            if ($row['folder']) {
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('folder_uuid' => $row['uuid']));
                $templateRow['icon'] = 'bi bi-folder-fill';
                $templateRow['title'] = $gL10n->get('SYS_FOLDER');

                if ($this->folder->hasUploadRight()) {
                    if ($gCurrentUser->administrateDocumentsFiles()) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_permissions.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-shield-lock',
                            'tooltip' => $gL10n->get('SYS_PERMISSIONS')
                        );
                    }
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-pencil-square',
                            'tooltip' => $gL10n->get('SYS_EDIT_FOLDER')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/move.php', array('folder_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-folder-symlink',
                            'tooltip' => $gL10n->get('SYS_MOVE_FOLDER')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'row_' . $row['uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/modules/documents-files/documents_files_function.php',
                                array('mode' => 'delete_folder', 'folder_uuid' => $row['uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE_FOLDER')
                    );
                }
            } else {
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/get_file.php', array('file_uuid' => $row['uuid'], 'view' => 1));
                $templateRow['icon'] = 'bi '.FileSystemUtils::getFileIcon($row['name']);
                $templateRow['title'] = $gL10n->get('SYS_FILE');

                if ($this->folder->hasUploadRight()) {
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $this->folder->getValue('fol_uuid'), 'file_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-pencil-square',
                            'tooltip' => $gL10n->get('SYS_EDIT')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/move.php', array('folder_uuid' => $this->folder->getValue('fol_uuid'), 'file_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-folder-symlink',
                            'tooltip' => $gL10n->get('SYS_MOVE_FILE')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'row_' . $row['uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/modules/documents-files/documents_files_function.php',
                                array('mode' => 'delete_file', 'file_uuid' => $row['uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'icon' => 'bi bi-trash',
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

        // create array with all unregistered folders and files
        $unregisteredFoldersFiles = $documentService->findUnregisteredFoldersFiles();
        $templateUnregisteredData = array();

        foreach($unregisteredFoldersFiles as $row) {
            $templateRow = array();

            if ($row['folder']) {
                $templateRow['icon'] = 'bi bi-folder-fill';
                $templateRow['title'] = $gL10n->get('SYS_FOLDER');

            } else {
                $templateRow['icon'] = 'bi '.FileSystemUtils::getFileIcon($row['name']);
                $templateRow['title'] = $gL10n->get('SYS_FILE');
            }

            $templateRow['folder'] = $row['folder'];
            $templateRow['name'] = $row['name'];
            $templateRow['size'] = $row['size'];
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => 'add', 'folder_uuid' => $this->folder->getValue('fol_uuid'), 'name' => $row['name']));

            $templateUnregisteredData[] = $templateRow;
        }

        // initialize and set the parameter for DataTables
        $dataTables = new HtmlDataTables($this, 'adm_documents_files_table');
        $dataTables->disableColumnsSort(array(1, 6));
        $dataTables->setColumnsNotHideResponsive(array(6));
        $dataTables->createJavascript(count($this->data), 6);

        $this->smarty->assign('infoAlert', $infoAlert);
        $this->smarty->assign('list', $templateData);
        $this->smarty->assign('unregisteredList', $templateUnregisteredData);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/documents-files.list.tpl');
    }
}
