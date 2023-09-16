<?php
/**
 ***********************************************************************************************
 * Prepare values of import form for further processing
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$postImportFormat   = admFuncVariableIsValid(
    $_POST,
    'format',
    'string',
    array('requireValue' => true,
        'validValues' => array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'))
);
$postImportCoding   = admFuncVariableIsValid(
    $_POST,
    'import_coding',
    'string',
    array('validValues' => array('', 'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'))
);
$postSeparator      = admFuncVariableIsValid(
    $_POST,
    'import_separator',
    'string',
    array('validValues' => array('', ',', ';', '\t', '|'))
);
$postEnclosure      = admFuncVariableIsValid(
    $_POST,
    'import_enclosure',
    'string',
    array('validValues' => array('', 'AUTO', '"', '\|'))
);
$postWorksheet      = admFuncVariableIsValid($_POST, 'import_sheet', 'string');
$postRoleId         = admFuncVariableIsValid($_POST, 'import_role_id', 'int');
$postUserImportMode = admFuncVariableIsValid($_POST, 'user_import_mode', 'int', array('requireValue' => true));

$_SESSION['import_request'] = $_POST;
unset($_SESSION['import_csv_request']);

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

// only authorized users should import users
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$importfile = $_FILES['userfile']['tmp_name'][0];
if (strlen($importfile) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FILE'))));
// => EXIT
} elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
    // check the filesize against the server settings
    $gMessage->show($gL10n->get('SYS_FILE_TO_LARGE_SERVER', array($gSettingsManager->getInt('max_file_upload_size'))));
// => EXIT
} elseif (!file_exists($importfile) || !is_uploaded_file($importfile)) {
    // check if a file was really uploaded
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
// => EXIT
} elseif ($postRoleId === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_ROLE'))));
    // => EXIT
}

// Read in the role and check whether the user can select it and thereby not possibly
// get a role assignment right if he did not have it before.
$role = new TableRoles($gDb, $postRoleId);

if (!$gCurrentUser->hasRightViewRole((int) $role->getValue('rol_id'))
|| (!$gCurrentUser->manageRoles() && $role->getValue('rol_assign_roles') == false)) {
    $gMessage->show($gL10n->get('SYS_ROLE_SELECT_RIGHT', array($role->getValue('rol_name'))));
    // => EXIT
}

// read file using the phpSpreadsheet library
$_SESSION['rol_id']           = (int) $role->getValue('rol_id');
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
    try {
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
            $gMessage->show($gL10n->get('SYS_IMPORT_SHEET_NOT_EXISTS', array($postWorksheet)));
        // => EXIT
        } else {
            // read data to array without any format
            $_SESSION['import_data'] = $sheet->toArray(null, true, false);
        }
    } catch (\PhpOffice\PhpSpreadsheet\Exception | \Exception $e) {
        $gMessage->show($e->getMessage());
        // => EXIT
    } catch (AdmException $e) {
        $e->showText();
        // => EXIT
    }
}

admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/members/import_column_config.php');
// => EXIT
