<?php
/**
 ***********************************************************************************************
 * Import users from a csv file
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

$_SESSION['import_csv_request'] = $_POST;

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

// only authorized users can import users
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Lastname und firstname are mandatory fields
if (strlen($_POST['usf-'.$gProfileFields->getProperty('LAST_NAME', 'usf_id')]) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('LAST_NAME', 'usf_name'))));
    // => EXIT
}
if (strlen($_POST['usf-'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id')]) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('FIRST_NAME', 'usf_name'))));
    // => EXIT
}

// go through each line from the file one by one and create the user in the DB
$line = reset($_SESSION['import_data']);
$userImport = new UserImport($gDb, $gProfileFields);
$firstRowTitle = array_key_exists('first_row', $_POST);
$startRow = 0;
$countImportNewUser  = 0;
$countImportEditUser = 0;
$countImportEditRole = 0;
$userCounted = false;
$importedFields = array();
// array matches the profile field ids with the columns of the import file
$importProfileFields = array();

// create array with all profile fields that where assigned to columns of the import file
foreach ($_POST as $formFieldId => $importFileColumn) {
    // normal profile fields
    if (strpos($formFieldId, 'usf-') !== false && $importFileColumn !== '') {
        $importProfileFields[(int) substr($formFieldId, 4)] = (int) $importFileColumn;
    }
    // username and password
    elseif (strpos($formFieldId, 'usr_') !== false && $importFileColumn !== '') {
        $importProfileFields[$formFieldId] = $importFileColumn;
    }
}

// Determine dependent roles
$depRoles = RoleDependency::getParentRoles($gDb, (int) $_SESSION['rol_id']);

if ($firstRowTitle) {
    // skip first line, because here are the column names
    $line = next($_SESSION['import_data']);
    $startRow = 1;
}

// set execution time to 10 minutes because we have a lot to do
PhpIniUtils::startNewExecutionTimeLimit(600);

for ($i = $startRow, $iMax = count($_SESSION['import_data']); $i < $iMax; ++$i) {
    $userCounted   = false;
    $userLoginName = '';
    $userPassword  = '';
    $userImport->clear();

    $userImport->setImportMode((int) $_SESSION['user_import_mode']);
    $userImport->readDataByFirstnameLastName(
        $line[$importProfileFields[$gProfileFields->getProperty('FIRST_NAME', 'usf_id')]],
        $line[$importProfileFields[$gProfileFields->getProperty('LAST_NAME', 'usf_id')]]
    );

    foreach ($line as $columnKey => $columnValue) {
        // get usf id or database column name
        $assignedFieldColumnId = array_search($columnKey, $importProfileFields);
        // remove spaces and html tags
        $columnValue = trim(strip_tags($columnValue));

        if (is_int($assignedFieldColumnId)) {
            $userImport->setValue($gProfileFields->getPropertyById($assignedFieldColumnId, 'usf_name_intern'), $columnValue);
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
        $userImport->setLoginData($userLoginName, $userPassword);
    }

    if ($userImport->isNewRecord()) {
        ++$countImportNewUser;
        $userCounted = true;
    }

    if ($userImport->save() && !$userCounted) {
        ++$countImportEditUser;
        $userCounted = true;
    }

    // assign role membership to user
    if ($userImport->setRoleMembership((int) $_SESSION['rol_id'])) {
        ++$countImportEditRole;
    }

    // assign dependent role memberships to user
    foreach ($depRoles as $depRole) {
        $userImport->setRoleMembership($depRole);
    }

    $line = next($_SESSION['import_data']);
}

// initialize session parameters
$_SESSION['role']             = '';
$_SESSION['user_import_mode'] = '';
$_SESSION['import_data']      = '';

$gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members.php');
$gMessage->show($gL10n->get('SYS_IMPORT_SUCCESSFUL', array($countImportNewUser, $countImportEditUser, $countImportEditRole)));
// => EXIT
