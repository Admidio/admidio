<?php
/**
 ***********************************************************************************************
 * Import users from a file
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode     - import : Import users from the file
 *             log    : Show import log of the last import
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Users\Service\ContactImportService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('import', 'log')));

    // only authorized users can import users
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode === 'import') {
        // Validate the web form and convert its UUID-based field assignments to the service's
        // canonical internal-name mapping. The actual import workflow is shared with the CLI.
        $contactsImportAssignFieldsForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $contactsImportAssignFieldsForm->validate($_POST);
        $firstRowTitle = array_key_exists('first_row', $_POST);

        $importService = new ContactImportService($gDb, $gProfileFields);
        $mapping = $importService->resolveWebMapping($formValues);
        $result = $importService->importRows(
            $_SESSION['import_data'],
            $mapping,
            (int)$_SESSION['rol_id'],
            (int)$_SESSION['user_import_mode'],
            $firstRowTitle
        );

        // initialize session parameters
        $_SESSION['role'] = '';
        $_SESSION['user_import_mode'] = '';
        $_SESSION['import_data'] = '';

        $importMessages = array();
        foreach ($result['rows'] as $rowResult) {
            if ($rowResult['messages'] !== '') {
                $importMessages[] = 'Row ' . $rowResult['row'] . ': ' . $rowResult['messages'];
            }
        }

        $importMessage = '';
        if (count($importMessages) > 0) {
            $importMessage = '<h4>' . $gL10n->get('SYS_LOG') . '</h4><br />';
            $importMessage .= implode('<br />', $importMessages);
        }

        $_SESSION['import_log'] = array(
            'countImportNewUser' => $result['new'],
            'countImportEditUser' => $result['updated'],
            'countImportEditRole' => $result['memberships'],
            'importMessage' => $importMessage
        );

        echo json_encode(array(
            'status' => 'success',
            'url' => ADMIDIO_URL . FOLDER_MODULES . '/contacts/import_user.php?mode=log'
        ));
        exit();
    } elseif ($getMode === 'log') {
        $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts.php');
        $gMessage->show($gL10n->get('SYS_IMPORT_SUCCESSFUL',
            array(
                $_SESSION['import_log']['countImportNewUser'],
                $_SESSION['import_log']['countImportEditUser'],
                $_SESSION['import_log']['countImportEditRole'],
                $_SESSION['import_log']['importMessage']
            )
        ));
        // => EXIT
    }
} catch (Throwable $e) {
    handleException($e, true);
}
