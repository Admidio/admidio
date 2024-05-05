<?php
/**
 ***********************************************************************************************
 * Import users from a csv file
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

use Ramsey\Uuid\Uuid;

try {
    $_SESSION['import_csv_request'] = $_POST;

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    // only authorized users can import users
    if (!$gCurrentUser->editUsers()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // Lastname und firstname are mandatory fields
    if (strlen($_POST[$gProfileFields->getProperty('LAST_NAME', 'usf_uuid')]) === 0) {
        throw new AdmException('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('LAST_NAME', 'usf_name')));
    }
    if (strlen($_POST[$gProfileFields->getProperty('FIRST_NAME', 'usf_uuid')]) === 0) {
        throw new AdmException('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('FIRST_NAME', 'usf_name')));
    }

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
            $userImport->readDataByUuid($line[$importProfileFields['usr_uuid']]);
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
                // special case for date columns from excel. We read the data without format
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
            } catch (AdmException|Exception $e) {
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
        $role = new TableRoles($gDb, (int)$_SESSION['rol_id']);
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

    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts.php');
    $gMessage->show($gL10n->get('SYS_IMPORT_SUCCESSFUL', array($countImportNewUser, $countImportEditUser, $countImportEditRole, $importMessage)));
    // => EXIT
} catch (AdmException $e) {
    $e->showHtml();
}
