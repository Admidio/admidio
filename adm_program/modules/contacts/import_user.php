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
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Users\Entity\UserImport;
use Ramsey\Uuid\Uuid;

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
        // check form field input and sanitized it from malicious content
        $contactsImportAssignFieldsForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $contactsImportAssignFieldsForm->validate($_POST);

        // go through each line from the file one by one and create the user in the DB
        $line = reset($_SESSION['import_data']);
        $userImport = new UserImport($gDb, $gProfileFields);
        $identifyUserByUuid = false;
        $firstRowTitle = array_key_exists('first_row', $_POST);
        $startRow = 0;
        $countImportNewUser = 0;
        $countImportEditUser = 0;
        $countImportEditRole = 0;
        $importMessage = '';
        $importMessages = array();
        $userCounted = false;
        $importedFields = array();
        // array matches the profile field ids with the columns of the import file
        $importProfileFields = array();

        // create array with all profile fields that where assigned to columns of the import file
        foreach ($_POST as $formFieldId => $importFileColumn) {
            if ($importFileColumn !== ''
                && (Uuid::isValid($formFieldId) || strpos($formFieldId, 'usr_') !== false)) {
                if ($formFieldId === 'usr_uuid') {
                    $identifyUserByUuid = true;
                }
                $importProfileFields[$formFieldId] = (int)$importFileColumn;
            }
        }

        // Determine dependent roles
        $depRoles = RoleDependency::getParentRoles($gDb, (int)$_SESSION['rol_id']);

        if ($firstRowTitle) {
            // skip first line, because here are the column names
            $line = next($_SESSION['import_data']);
            $startRow = 1;
        }

        // set execution time to 10 minutes because we have a lot to do
        PhpIniUtils::startNewExecutionTimeLimit(600);

        for ($i = $startRow, $iMax = count($_SESSION['import_data']); $i < $iMax; ++$i) {
            $userCounted = false;
            $userLoginName = '';
            $userPassword = '';
            $userImport->clear();

            $userImport->setImportMode((int)$_SESSION['user_import_mode']);
            if ($identifyUserByUuid) {
                $userImport->readDataByUuid($line[$importProfileFields['usr_uuid']] ?? '');
            } else {
                $userImport->readDataByFirstnameLastName(
                    trim($line[$importProfileFields[$gProfileFields->getProperty('FIRST_NAME', 'usf_uuid')]]),
                    trim($line[$importProfileFields[$gProfileFields->getProperty('LAST_NAME', 'usf_uuid')]])
                );
            }

            foreach ($line as $columnKey => $columnValue) {
                if (empty($columnValue)) {
                    $columnValue = '';
                }

                // get usf id or database column name
                $assignedFieldColumnId = array_search($columnKey, $importProfileFields);
                // remove spaces and html tags
                $columnValue = trim(strip_tags($columnValue));

                if (Uuid::isValid($assignedFieldColumnId)) {
                    // special case for date columns from Excel. We read the data without format
                    // so excel give us an integer for the date that must be converted
                    if ($gProfileFields->getPropertyByUuid($assignedFieldColumnId, 'usf_type') === 'DATE' && is_numeric($columnValue)) {
                        $columnValue = date($gSettingsManager->getString('system_date'), \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($columnValue));
                    }

                    $userImport->setValue($gProfileFields->getPropertyByUuid($assignedFieldColumnId, 'usf_name_intern'), $columnValue);
                } else {
                    // remember username and password and add it later to the user
                    if ($assignedFieldColumnId === 'usr_login_name') {
                        $userLoginName = $columnValue;
                    } elseif ($assignedFieldColumnId === 'usr_password') {
                        $userPassword = $columnValue;
                    }
                }
            }

            // add login data to the user
            if ($userLoginName !== '' && $userPassword !== '') {
                try {
                    $userImport->setLoginData($userLoginName, $userPassword);
                } catch (Exception $e) {
                    $importMessages[] = $e->getMessage();
                }
            }

            if ($userImport->isNewRecord()) {
                ++$countImportNewUser;
                $userCounted = true;
            }

            // save imported data of the user in database
            if ($userImport->save() && !$userCounted) {
                ++$countImportEditUser;
                $userCounted = true;
            }

            // assign role membership to user
            $role = new Role($gDb, (int)$_SESSION['rol_id']);
            $role->startMembership($userImport->getValue('usr_id'));
            ++$countImportEditRole;

            $line = next($_SESSION['import_data']);
        }

        // initialize session parameters
        $_SESSION['role'] = '';
        $_SESSION['user_import_mode'] = '';
        $_SESSION['import_data'] = '';

        if (count($importMessages) > 0) {
            $importMessage = '<h4>' . $gL10n->get('SYS_LOG') . '</h4><br />';
            $importMessage .= implode('<br />', $importMessages);
        }

        $_SESSION['import_log'] = array(
            'countImportNewUser' => $countImportNewUser,
            'countImportEditUser' => $countImportEditUser,
            'countImportEditRole' => $countImportEditRole,
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
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
