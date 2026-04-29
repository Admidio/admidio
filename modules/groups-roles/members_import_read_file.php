<?php
/**
 ***********************************************************************************************
 * Read and parse import file for role members
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * role_uuid  : UUID of role to which members should be imported
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Service\ImportFileParser;
use Admidio\Roles\Service\RoleMembersImportService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('requireValue' => true, 'directOutput' => true));

    $postFormat = admFuncVariableIsValid(
        $_POST,
        'format',
        'string',
        array('requireValue' => true, 'validValues' => array('AUTO', 'CSV', 'JSON'))
    );
    $postImportCoding = admFuncVariableIsValid(
        $_POST,
        'import_coding',
        'string',
        array('validValues' => array('', 'UTF-8', 'CP1252', 'ISO-8859-1'))
    );
    $postSeparator = admFuncVariableIsValid(
        $_POST,
        'import_separator',
        'string',
        array('validValues' => array('', ',', ';', '\t', '|'))
    );
    $postIdentifyMethod = admFuncVariableIsValid(
        $_POST,
        'identify_method',
        'string',
        array('requireValue' => true, 'validValues' => array('email', 'uuid', 'login', 'name'))
    );
    $postFirstRowHeader = admFuncVariableIsValid(
        $_POST,
        'first_row_header',
        'bool',
        array('defaultValue' => true)
    );

    $role = new Role($gDb);
    $role->readDataByUuid($getRoleUuid);

    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $contactsImportForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $contactsImportForm->validate($_POST);

    $importfile = $_FILES['userfile']['tmp_name'][0] ?? '';
    if (strlen($importfile) === 0) {
        throw new Exception('SYS_FIELD_EMPTY', array('SYS_FILE'));
    } elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
        throw new Exception('SYS_FIELD_EMPTY', array(ini_get('upload_max_filesize')));
    } elseif (!file_exists($importfile) || !is_uploaded_file($importfile)) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    $parserFormat = ImportFileParser::FORMAT_AUTO;
    if ($postFormat === 'CSV') {
        $parserFormat = ImportFileParser::FORMAT_CSV;
    } elseif ($postFormat === 'JSON') {
        $parserFormat = ImportFileParser::FORMAT_JSON;
    }

    $parser = new ImportFileParser($importfile, $parserFormat);

    if ($postImportCoding !== '') {
        $parser->setEncoding($postImportCoding);
    }
    if ($postSeparator !== '') {
        $parser->setDelimiter($postSeparator);
    }

    $parser->parse();

    $_SESSION['role_members_import'] = array(
        'role_uuid' => $getRoleUuid,
        'role_id' => (int)$role->getValue('rol_id'),
        'parsed_data' => $parser->getParsedData(),
        'headers' => $parser->getHeaders(),
        'identify_method' => $postIdentifyMethod,
        'first_row_header' => $postFirstRowHeader,
        'format' => $parser->getFormat(),
        'row_count' => $parser->getRowCount(),
    );

    echo json_encode(array(
        'status' => 'success',
        'url' => ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_import_preview.php?role_uuid=' . $getRoleUuid
    ));
    exit();
} catch (Throwable $e) {
    handleException($e, true);
}
