<?php

namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Infrastructure\Database;
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
     * Create form to edit the current folder or create a new folder.
     * @param string $fileUUID UUID of the file that should be renamed
     * @throws Exception
     */
    public function createFileRenameForm(string $fileUUID): void
    {
        global $gL10n, $gCurrentSession, $gDb;

        $this->setHtmlID('adm_documents_files_rename_file');
        $this->setHeadline($gL10n->get('SYS_EDIT_FILE'));

        // check the rights of the current folder
        // user must be administrator or must have the right to upload files
        $targetFolder = new Folder($gDb);
        $targetFolder->getFolderForDownload($this->folderUUID);

        if (!$targetFolder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // get recordset of current file from database
        $file = new File($gDb);
        $file->getFileForDownload($fileUUID);
        $originalName = pathinfo($file->getValue('fil_name'), PATHINFO_FILENAME);

        ChangelogService::displayHistoryButton($this, 'filefolder', 'files,roles_rights_data', true, array('uuid' => $fileUUID));

        // create html form
        $form = new FormPresenter(
            'adm_documents_file_rename_form',
            'modules/documents-files.rename.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'file_rename_save', 'folder_uuid' => $this->folderUUID, 'file_uuid' => $fileUUID)),
            $this
        );
        $form->addInput(
            'adm_file_type',
            $gL10n->get('SYS_FILE_TYPE'),
            pathinfo($file->getValue('fil_name'), PATHINFO_EXTENSION),
            array('property' => FormPresenter::FIELD_DISABLED, 'class' => 'form-control-small')
        );
        $form->addInput(
            'adm_previous_name',
            $gL10n->get('SYS_PREVIOUS_NAME'),
            $originalName,
            array('property' => FormPresenter::FIELD_DISABLED)
        );
        $form->addInput(
            'adm_new_name',
            $gL10n->get('SYS_NEW_NAME'),
            $originalName,
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'SYS_FILE_NAME_RULES')
        );
        $form->addMultilineTextInput(
            'adm_new_description',
            $gL10n->get('SYS_DESCRIPTION'),
            $file->getValue('fil_description'),
            4,
            array('maxLength' => 255)
        );
        $form->addSubmitButton(
            'adm_btn_rename',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $this->assignSmartyVariable('nameUserCreated', $file->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $file->getValue('fil_timestamp'));

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create form to move a folder or file to a new folder.
     * @param string $fileUUID UUID of the file that should be moved. If a folder should be moved, this parameter is empty.
     * @throws Exception
     */
    public function createFolderFileMoveForm(string $fileUUID = ''): void
    {
        global $gL10n, $gCurrentSession, $gDb;

        $this->setHtmlID('adm_documents_files_move_folder_file');
        $this->setHeadline($gL10n->get('SYS_EDIT_FOLDER'));

        // check the rights of the current folder
        // user must be administrator or must have the right to upload files
        $targetFolder = new Folder($gDb);
        $targetFolder->getFolderForDownload($this->folderUUID);

        if (!$targetFolder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // set headline and description
        if ($fileUUID !== '') {
            $file = new File($gDb);
            $file->readDataByUuid($fileUUID);
            $this->setHeadline($gL10n->get('SYS_MOVE_FILE'));
            $this->assignSmartyVariable('description', $gL10n->get('SYS_MOVE_FILE_DESC', array($file->getValue('fil_name'))));
        } else {
            $folder = new Folder($gDb);
            $folder->readDataByUuid($this->folderUUID);
            $this->setHeadline($gL10n->get('SYS_MOVE_FOLDER'));
            $this->assignSmartyVariable('description', $gL10n->get('SYS_MOVE_FOLDER_DESC', array($folder->getValue('fol_name'))));
        }

        $documentsService = new DocumentsService($gDb, $this->folderUUID);
        $folders = $documentsService->getUploadableFolderStructure();

        // create html form
        $form = new FormPresenter(
            'adm_documents_files_move_file',
            'modules/documents-files.move.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'move_save', 'folder_uuid' => $this->folderUUID, 'file_uuid' => $fileUUID)),
            $this
        );
        $form->addSelectBox(
            'adm_destination_folder_uuid',
            $gL10n->get('SYS_MOVE_TO'),
            $folders,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => $this->folderUUID,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addSubmitButton(
            'adm_btn_move',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create form to edit the current folder or create a new folder.
     * @throws Exception
     */
    public function createFolderNewForm(): void
    {
        global $gL10n, $gCurrentSession;

        $this->setHtmlID('adm_documents_files_new_folder');
        $this->setHeadline($gL10n->get('SYS_CREATE_FOLDER'));


        // erst prüfen, ob der User auch die entsprechenden Rechte hat
        if (!$this->folder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $parentFolderName = $this->folder->getValue('fol_name');

        $this->assignSmartyVariable('parentFolderName', $parentFolderName);

        // show form
        $form = new FormPresenter(
            'adm_new_folder_form',
            'modules/documents-files.folder.new.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'new_folder_save', 'folder_uuid' => $this->folderUUID)),
            $this
        );
        $form->addInput(
            'adm_folder_name',
            $gL10n->get('SYS_NAME'),
            '',
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addMultilineTextInput(
            'adm_folder_description',
            $gL10n->get('SYS_DESCRIPTION'),
            '',
            4,
            array('maxLength' => 4000)
        );
        $form->addSubmitButton(
            'adm_btn_create',
            $gL10n->get('SYS_CREATE_FOLDER'),
            array('icon' => 'bi-plus-circle-fill', 'class' => 'offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create form to show and edit the folder view and upload permissions.
     * @throws Exception
     */
    public function createFolderPermissionsForm(): void
    {
        global $gCurrentSession, $gCurrentUser, $gL10n, $gDb, $gCurrentOrgId;

        // first check whether the user also has the appropriate rights
        if (!$gCurrentUser->administrateDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->setHtmlID('adm_documents_files_folder_permissions');
        $this->setHeadline($gL10n->get('SYS_SET_FOLDER_PERMISSIONS'));

        $rolesViewRightParentFolder = array();
        $sqlRolesViewRight = '';

        // get recordset of current folder from database
        $folder = new Folder($gDb);
        $folder->getFolderForDownload($this->folderUUID);

        // read parent folder
        if ($folder->getValue('fol_fol_id_parent')) {
            // get recordset of parent folder from database
            $parentFolder = new Folder($gDb, (int)$folder->getValue('fol_fol_id_parent'));
            $parentFolder->getFolderForDownload($parentFolder->getValue('fol_uuid'));

            // get assigned roles of the parent folder
            $rolesViewRightParentFolder = $parentFolder->getViewRolesIds();
            if (count($rolesViewRightParentFolder) > 0) {
                $sqlRolesViewRight = ' AND rol_id IN (' . Database::getQmForValues($rolesViewRightParentFolder) . ')';
            }
        }

        // if parent folder has access for all roles then read all roles from database
        $sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                   FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . '
                     ON cat_id = rol_cat_id
                  WHERE rol_valid  = true
                    AND rol_system = false
                        ' . $sqlRolesViewRight . '
                    AND cat_org_id = ? -- $gCurrentOrgId
                    AND cat_name_intern <> \'EVENTS\'
               ORDER BY cat_sequence, rol_name';
        $sqlDataView = array(
            'query' => $sqlViewRoles,
            'params' => array_merge($rolesViewRightParentFolder, array($gCurrentOrgId))
        );

        $firstEntryViewRoles = '';

        if (count($rolesViewRightParentFolder) === 0) {
            $firstEntryViewRoles = array('0', $gL10n->get('SYS_ALL') . ' (' . $gL10n->get('SYS_ALSO_VISITORS') . ')', null);
        }

        // get assigned roles of this folder
        $roleViewSet = $folder->getViewRolesIds();

        // if no roles are assigned then set "all users" as default
        if (count($roleViewSet) === 0) {
            $roleViewSet[] = 0;
        }

        // get assigned roles of this folder
        $roleUploadSet = $folder->getUploadRolesIds();

        // if no roles are assigned then set "all users" as default
        if (count($roleUploadSet) === 0) {
            $roleUploadSet[] = '';
        }

        // read all download module administrator roles
        $sqlAdminRoles = 'SELECT rol_name
                    FROM ' . TBL_ROLES . '
              INNER JOIN ' . TBL_CATEGORIES . '
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
        $this->assignSmartyVariable('folderName', $folder->getValue('fol_name'));

        ChangelogService::displayHistoryButton($this, 'folder ', 'folders,files,roles_rights_data', !empty($getAnnUuid), array('uuid' => $this->folderUUID));

        // show form
        $form = new FormPresenter(
            'adm_folder_permissions_form',
            'modules/documents-files.folder.permissions.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'permissions_save', 'folder_uuid' => $this->folderUUID)),
            $this
        );
        $form->addSelectBoxFromSql(
            'adm_roles_view_right',
            $gL10n->get('SYS_VISIBLE_FOR'),
            $gDb,
            $sqlDataView,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => $roleViewSet,
                'multiselect' => true,
                'firstEntry' => $firstEntryViewRoles
            )
        );
        $form->addSelectBoxFromSql(
            'adm_roles_upload_right',
            $gL10n->get('SYS_UPLOAD_FILES'),
            $gDb,
            $sqlDataView,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => $roleUploadSet,
                'multiselect' => true,
                'placeholder' => $gL10n->get('SYS_NO_ADDITIONAL_PERMISSIONS_SET')
            )
        );
        $form->addInput(
            'adm_administrators',
            $gL10n->get('SYS_ADMINISTRATORS'),
            implode(', ', $adminRoles),
            array('property' => FormPresenter::FIELD_DISABLED, 'helpTextId' => $gL10n->get('SYS_ADMINISTRATORS_DESC', array($gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'))))
        );
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        // add form to html page and show page
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create form to edit the current folder or create a new folder.
     * @throws Exception
     */
    public function createFolderRenameForm(): void
    {
        global $gL10n, $gCurrentSession, $gDb;

        $this->setHtmlID('adm_documents_files_rename_folder');
        $this->setHeadline($gL10n->get('SYS_EDIT_FOLDER'));

        // check the rights of the current folder
        // user must be administrator or must have the right to upload files
        $targetFolder = new Folder($gDb);
        $targetFolder->getFolderForDownload($this->folderUUID);

        if (!$targetFolder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // main folder should not be renamed
        if ($targetFolder->getValue('fol_fol_id_parent') === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        ChangelogService::displayHistoryButton($this, 'filefolder', 'folders,roles_rights_data', true, array('uuid' => $this->folderUUID));

        // create html form
        $form = new FormPresenter(
            'adm_documents_folder_rename',
            'modules/documents-files.rename.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'folder_rename_save', 'folder_uuid' => $this->folderUUID)),
            $this
        );
        $form->addInput(
            'adm_previous_name',
            $gL10n->get('SYS_PREVIOUS_NAME'),
            $targetFolder->getValue('fol_name'),
            array('property' => FormPresenter::FIELD_DISABLED)
        );
        $form->addInput(
            'adm_new_name',
            $gL10n->get('SYS_NEW_NAME'),
            $targetFolder->getValue('fol_name'),
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'SYS_FILE_NAME_RULES')
        );
        $form->addMultilineTextInput(
            'adm_new_description',
            $gL10n->get('SYS_DESCRIPTION'),
            $targetFolder->getValue('fol_description'),
            4,
            array('maxLength' => 255)
        );
        $form->addSubmitButton(
            'adm_btn_rename',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $this->assignSmartyVariable('nameUserCreated', $this->folder->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $this->folder->getValue('fol_timestamp'));

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
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
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'new_folder', 'folder_uuid' => $currentFolder->getValue('fol_uuid'))),
                    'bi-plus-circle-fill'
                );

                if ($currentFolder->getValue('fol_fol_id_parent') > 0) {
                    $this->addPageFunctionsMenuItem(
                        'menu_item_documents_edit_folder',
                        $gL10n->get('SYS_EDIT_FOLDER'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php',
                            array(
                                'mode' => 'folder_rename',
                                'folder_uuid' => $currentFolder->getValue('fol_uuid')
                            )),
                        'bi-pencil-square'
                    );
                }
            }

            if ($gCurrentUser->administrateDocumentsFiles()) {
                $this->addPageFunctionsMenuItem(
                    'menu_item_documents_permissions',
                    $gL10n->get('SYS_PERMISSIONS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'permissions', 'folder_uuid' => $currentFolder->getValue('fol_uuid'))),
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
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files.php', array('folder_uuid' => $row['uuid']));
                $templateRow['icon'] = 'bi bi-folder-fill';
                $templateRow['title'] = $gL10n->get('SYS_FOLDER');

                if ($this->folder->hasUploadRight()) {
                    if ($gCurrentUser->administrateDocumentsFiles()) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'permissions', 'folder_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-shield-lock',
                            'tooltip' => $gL10n->get('SYS_PERMISSIONS')
                        );
                    }
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php',
                                array(
                                    'mode' => 'folder_rename',
                                    'folder_uuid' => $row['uuid']
                                )),
                            'icon' => 'bi bi-pencil-square',
                            'tooltip' => $gL10n->get('SYS_EDIT_FOLDER')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'move', 'folder_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-folder-symlink',
                            'tooltip' => $gL10n->get('SYS_MOVE_FOLDER')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'row_' . $row['uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/modules/documents-files.php',
                                array('mode' => 'folder_delete', 'folder_uuid' => $row['uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($row['name'])),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE_FOLDER')
                    );
                }
            } else {
                $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files.php', array('mode' => 'download', 'file_uuid' => $row['uuid'], 'view' => 1));
                $templateRow['icon'] = 'bi '.FileSystemUtils::getFileIcon($row['name']);
                $templateRow['title'] = $gL10n->get('SYS_FILE');

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'download', 'file_uuid' => $row['uuid'])),
                    'icon' => 'bi bi-download',
                    'tooltip' => $gL10n->get('SYS_DOWNLOAD_FILE')
                );

                if ($this->folder->hasUploadRight()) {
                    if ($row['existsInFileSystem']) {
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php',
                                array(
                                    'mode' => 'file_rename',
                                    'folder_uuid' => $this->folder->getValue('fol_uuid'),
                                    'file_uuid' => $row['uuid']
                                )),
                            'icon' => 'bi bi-pencil-square',
                            'tooltip' => $gL10n->get('SYS_EDIT')
                        );
                        $templateRow['actions'][] = array(
                            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php', array('mode' => 'move', 'folder_uuid' => $this->folder->getValue('fol_uuid'), 'file_uuid' => $row['uuid'])),
                            'icon' => 'bi bi-folder-symlink',
                            'tooltip' => $gL10n->get('SYS_MOVE_FILE')
                        );
                    }
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'row_' . $row['uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/modules/documents-files.php',
                                array('mode' => 'file_delete', 'file_uuid' => $row['uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($row['name'])),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE_FILE')
                    );
                }

            }

            $templateRow['uuid'] = $row['uuid'];
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
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files.php', array('mode' => 'add', 'folder_uuid' => $this->folder->getValue('fol_uuid'), 'name' => $row['name']));

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
