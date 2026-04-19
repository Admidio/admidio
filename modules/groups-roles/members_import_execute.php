<?php
/**
 ***********************************************************************************************
 * Execute import of members to a role
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
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Service\RoleMembersImportService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('requireValue' => true, 'directOutput' => true));

    if (!isset($_SESSION['role_members_import'])) {
        throw new Exception('SYS_NO_IMPORT_DATA');
    }

    $importData = $_SESSION['role_members_import'];

    if ($importData['role_uuid'] !== $getRoleUuid) {
        throw new Exception('SYS_ROLE_MISMATCH');
    }

    $role = new Role($gDb);
    $role->readDataByUuid($getRoleUuid);

    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $selectedRows = [];
    if (isset($_POST['import_row']) && is_array($_POST['import_row'])) {
        $selectedRows = array_map('intval', $_POST['import_row']);
    }

    $importForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $importForm->validate($_POST);

    PhpIniUtils::startNewExecutionTimeLimit(600);

    $importService = new RoleMembersImportService($gDb, $role);
    $importService->setParsedData($importData['parsed_data'], $importData['headers']);
    $importService->setIdentifyMethod($importData['identify_method']);

    if (isset($importData['preview'])) {
        $importService->validate();
    } else {
        $autoMapping = $importService->autoDetectFieldMapping();
        $importService->setFieldMapping($autoMapping);
        $importService->validate();
    }

    $result = $importService->executeImport($selectedRows);

    $_SESSION['members_import_log'] = array(
        'role_uuid' => $getRoleUuid,
        'role_name' => $role->getValue('rol_name'),
        'count_success' => $result['count_success'],
        'count_errors' => $result['count_errors'],
        'log' => $result['log'],
        'timestamp' => date('Y-m-d H:i:s'),
    );

    unset($_SESSION['role_members_import']);

    echo json_encode(array(
        'status' => 'success',
        'url' => ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_import.php?mode=log&role_uuid=' . $getRoleUuid
    ));
    exit();
} catch (Throwable $e) {
    handleException($e, true);
}
