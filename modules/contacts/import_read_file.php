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

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $postImportFormat = admFuncVariableIsValid(
        $_POST,
        'format',
        'string',
        array('requireValue' => true,
            'validValues' => array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'))
    );
    $postImportCoding = admFuncVariableIsValid(
        $_POST,
        'import_coding',
        'string',
        array('validValues' => array('', 'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'))
    );
    $postSeparator = admFuncVariableIsValid(
        $_POST,
        'import_separator',
        'string',
        array('validValues' => array('', ',', ';', '\t', '|'))
    );
    $postEnclosure = admFuncVariableIsValid(
        $_POST,
        'import_enclosure',
        'string',
        array('validValues' => array('', 'AUTO', '"', '\|'))
    );
    $postWorksheet = admFuncVariableIsValid($_POST, 'import_sheet', 'string');
    $postRoleUUID = admFuncVariableIsValid($_POST, 'import_role_uuid', 'uuid');
    $postUserImportMode = admFuncVariableIsValid($_POST, 'user_import_mode', 'int', array('requireValue' => true));

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

    // read file using the phpSpreadsheet library
    $_SESSION['rol_id'] = (int)$role->getValue('rol_id');
    $_SESSION['user_import_mode'] = $postUserImportMode;

    switch ($postImportFormat) {
        case 'XLSX':
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            break;

        case 'XLS':
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            break;

        case 'ODS':
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
            break;

        case 'CSV':
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            if ($postImportCoding === 'GUESS') {
                $postImportCoding = \PhpOffice\PhpSpreadsheet\Reader\Csv::guessEncoding($importfile);
            } elseif ($postImportCoding === '') {
                $postImportCoding = 'UTF-8';
            }
            $reader->setInputEncoding($postImportCoding);

            if ($postSeparator != '') {
                $reader->setDelimiter($postSeparator);
            }

            if ($postEnclosure != 'AUTO') {
                $reader->setEnclosure($postEnclosure);
            }
            break;

        case 'HTML':
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
            break;

        case 'AUTO':
        default:
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($importfile);
            break;
    }

    // TODO: Better error handling if file cannot be loaded (phpSpreadsheet apparently does not always use exceptions)
    if (isset($reader) and !is_null($reader)) {
        $spreadsheet = $reader->load($importfile);
        // Read specified sheet (passed as argument/param)
        if (is_numeric($postWorksheet)) {
            $sheet = $spreadsheet->getSheet($postWorksheet);
        } elseif (!empty($postWorksheet)) {
            $sheet = $spreadsheet->getSheetByName($postWorksheet);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }

        if (empty($sheet)) {
            throw new Exception('SYS_IMPORT_SHEET_NOT_EXISTS', array($postWorksheet));
        } else {
            // read data to array without any format
            $_SESSION['import_data'] = $sheet->toArray(null, true, false);
        }
    }

    echo json_encode(array(
        'status' => 'success',
        'url' => ADMIDIO_URL . FOLDER_MODULES . '/contacts/import_column_config.php'
    ));
    exit();
} catch (Exception|\PhpOffice\PhpSpreadsheet\Exception|Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
