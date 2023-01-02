<?php
/**
 ***********************************************************************************************
 * Various functions for mylist module
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * list_uuid : UUID of the list configuration that should be edited
 * mode      : 1 - Save list configuration
 *             2 - Save temporary list configuration and show list
 *             3 - Delete list configuration
 * name      : (optional) Name of the list that should be used to save list
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getListUuid = admFuncVariableIsValid($_GET, 'list_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));
$getName     = admFuncVariableIsValid($_GET, 'name', 'string');

$_SESSION['mylist_request'] = $_POST;

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('groups_roles_enable_module')
|| ($gSettingsManager->getInt('groups_roles_edit_lists') === 2 && !$gCurrentUser->checkRolesRight('rol_edit_user')) // users with the right to edit all profiles
|| ($gSettingsManager->getInt('groups_roles_edit_lists') === 3 && !$gCurrentUser->isAdministrator())) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// At least one field should be assigned
if (!isset($_POST['column1']) || strlen($_POST['column1']) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array('1. '.$gL10n->get('SYS_COLUMN'))));
    // => EXIT
}

// role must be filled when displaying
if ($getMode === 2
&& (!isset($_POST['sel_roles_ids']) || (int) $_POST['sel_roles_ids'] === 0 || !is_array($_POST['sel_roles_ids']))) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_ROLE'))));
    // => EXIT
}

if (!isset($_POST['sel_relationtype_ids'])) {
    $_POST['sel_relationtype_ids'] = array();
}

$list = new ListConfiguration($gDb);
if($getListUuid !== '') {
    $list->readDataByUuid($getListUuid);
}

// check if user has the rights to edit this list
if ($getMode !== 2) {
    // global lists can only be edited by administrator
    if ($list->getValue('lst_global') == 1 && !$gCurrentUser->isAdministrator()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
    } elseif ((int) $list->getValue('lst_usr_id') !== $gCurrentUserId
    && $list->getValue('lst_global') == 0 && $list->getValue('lst_id') > 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

// save list
if (in_array($getMode, array(1, 2), true)) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    $globalConfiguration = admFuncVariableIsValid($_POST, 'cbx_global_configuration', 'bool', array('defaultValue' => false));

    // go through all existing columns
    for ($columnNumber = 1; isset($_POST['column'. $columnNumber]); ++$columnNumber) {
        if (strlen($_POST['column'. $columnNumber]) > 0) {
            // add column to list and check if its a profile field or another column
            if(StringUtils::strStartsWith($_POST['column'. $columnNumber], 'usr_') || StringUtils::strStartsWith($_POST['column'. $columnNumber], 'mem_')) {
                $list->addColumn($_POST['column' . $columnNumber], $columnNumber, $_POST['sort' . $columnNumber], $_POST['condition' . $columnNumber]);
            } else {
                $list->addColumn($gProfileFields->getProperty($_POST['column' . $columnNumber], 'usf_id') , $columnNumber, $_POST['sort' . $columnNumber], $_POST['condition' . $columnNumber]);
            }
        } else {
            $list->deleteColumn($columnNumber, true);
        }
    }

    if ($getName !== '') {
        $list->setValue('lst_name', $getName);
    }

    // set list global only in save mode
    if ($getMode === 1 && $gCurrentUser->isAdministrator()) {
        $list->setValue('lst_global', $globalConfiguration);
    } else {
        $list->setValue('lst_global', 0);
    }

    $list->save();

    $listUuid = $list->getValue('lst_uuid');

    if ($getMode === 1) {
        // save new id to session so that we can restore the configuration with new list name
        $_SESSION['mylist_request']['sel_select_configuration'] = $listUuid;

        // go back to mylist configuration
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/groups-roles/mylist.php', array('list_uuid' => $listUuid)));
        // => EXIT
    }

    // redirect to general list page
    admRedirect(SecurityUtils::encodeUrl(
        ADMIDIO_URL . FOLDER_MODULES.'/groups-roles/lists_show.php',
        array(
            'list_uuid' => $listUuid,
            'mode'      => 'html',
            'rol_ids'   => implode(',', array_map('intval', $_POST['sel_roles_ids'])),
            'urt_ids'   => implode(',', $_POST['sel_relationtype_ids'])
        )
    ));
// => EXIT
} elseif ($getMode === 3) {
    try {
        // delete list configuration
        $list->delete();
        unset($_SESSION['mylist_request']);
    } catch (AdmException $e) {
        $e->showHtml();
        // => EXIT
    }

    // go back to list configuration
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/groups-roles/mylist.php');
    // => EXIT
}
