<?php
/**
 ***********************************************************************************************
 * Prepare values of import form for further processing
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Service\ContactImportService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $postImportFormat = admFuncVariableIsValid(
        $_POST,
        'format',
        'string',
        array('requireValue' => true,
            'validValues' => ContactImportService::inputFormats())
    );
    $postImportCoding = admFuncVariableIsValid(
        $_POST,
        'import_coding',
        'string',
        array('validValues' => array_merge(array(''), ContactImportService::INPUT_ENCODINGS))
    );
    $postSeparator = admFuncVariableIsValid(
        $_POST,
        'import_separator',
        'string',
        array('validValues' => array_merge(array(''), array_keys(ContactImportService::CSV_DELIMITERS)))
    );
    $postEnclosure = admFuncVariableIsValid(
        $_POST,
        'import_enclosure',
        'string',
        array('validValues' => array_merge(array(''), ContactImportService::CSV_ENCLOSURES))
    );
    $postWorksheet = admFuncVariableIsValid($_POST, 'import_sheet', 'string');
    $postRoleUUID = admFuncVariableIsValid($_POST, 'import_role_uuid', 'uuid');
    $postUserImportMode = admFuncVariableIsValid(
        $_POST,
        'user_import_mode',
        'int',
        array('requireValue' => true, 'validValues' => array_values(ContactImportService::IMPORT_MODES))
    );

    // only authorized users should import users
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // check form field input and sanitized it from malicious content
    $contactsImportForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $contactsImportForm->validate($_POST);

    $importfile = $_FILES['userfile']['tmp_name'][0];
    if (strlen($importfile) === 0) {
        throw new Exception('SYS_FIELD_EMPTY', array('SYS_FILE'));
    } elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
        // check the filesize against the server settings
        throw new Exception('SYS_FIELD_EMPTY', array(ini_get('upload_max_filesize')));
    } elseif (!file_exists($importfile) || !is_uploaded_file($importfile)) {
        // check if a file was really uploaded
        throw new Exception('SYS_FILE_NOT_EXIST');
    } elseif ($postRoleUUID === '') {
        throw new Exception('SYS_FIELD_EMPTY', array('SYS_ROLE'));
    }

    // Read in the role and check whether the user can select it and thereby not possibly
    // get a role assignment right if he did not have it before.
    $role = new Role($gDb);
    $role->readDataByUuid($postRoleUUID);

    if (!$gCurrentUser->hasRightViewRole((int)$role->getValue('rol_id'))
        || (!$gCurrentUser->isAdministratorRoles() && !$role->getValue('rol_default_registration'))) {
        throw new Exception('SYS_ROLE_SELECT_RIGHT', array($role->getValue('rol_name')));
    }

    // Keep the web wizard responsible for upload/session handling, but use the same data-oriented
    // import service as the CLI for spreadsheet parsing.
    $_SESSION['rol_id'] = (int)$role->getValue('rol_id');
    $_SESSION['user_import_mode'] = $postUserImportMode;

    $importService = new ContactImportService($gDb, $gProfileFields);
    $_SESSION['import_data'] = $importService->readFile(
        $importfile,
        $postImportFormat,
        $postImportCoding,
        $postSeparator,
        $postEnclosure,
        $postWorksheet
    );

    echo json_encode(array(
        'status' => 'success',
        'url' => ADMIDIO_URL . FOLDER_MODULES . '/contacts/import_column_config.php'
    ));
    exit();
} catch (Throwable $e) {
    handleException($e, true);
}
