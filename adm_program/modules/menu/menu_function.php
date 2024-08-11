<?php
/**
 ***********************************************************************************************
 * Various functions for categories
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * uuid : UUID of the menu that should be edited
 * mode      : edit - Create or edit menu
 *             delete - Delete menu
 *             sequence - Change sequence for parameter men_id
 * direction : Direction to change the sequence of the menu entry
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getMenuUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));

    // check rights
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // create menu object
    $menu = new TableMenu($gDb);

    if ($getMenuUUID !== '') {
        $menu->readDataByUuid($getMenuUUID);
    }

    // create menu or update it
    if ($getMode === 'edit') {
        // within standard menu items the url should not be changed
        if ($menu->getValue('men_standard')) {
            $_POST['men_com_id'] = $menu->getValue('men_com_id');
            $_POST['men_url'] = $menu->getValue('men_url');
        }

        // check form field input and sanitized it from malicious content
        if (isset($_SESSION['menuEditForm'])) {
            $menuEditForm = $_SESSION['menuEditForm'];
            $formValues = $menuEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        // check url here because it could be a real url or a relative local url
        if (!StringUtils::strValidCharacters($_POST['men_url'], 'url')
            && !preg_match('=^[^*;:~<>|\"\\\\]+$=', $_POST['men_url'])) {
            throw new AdmException('SYS_URL_INVALID_CHAR', array('SYS_URL'));
        }

        $gDb->startTransaction();

        // write form values in menu object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'men_')) {
                $menu->setValue($key, $value);
            }
        }
        $returnCode = $menu->save();

        // save changed roles rights of the menu
        if (isset($_POST['menu_view'])) {
            $menuViewRoles = array_map('intval', $_POST['menu_view']);
        } else {
            $menuViewRoles = array();
        }

        $rightMenuView = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
        $rightMenuView->saveRoles($menuViewRoles);

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // delete menu

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        $menu->delete();
        echo json_encode(array('status' => 'success'));
        exit();
    } elseif ($getMode === 'sequence') {
        // Update menu sequence
        $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableMenu::MOVE_UP, TableMenu::MOVE_DOWN)));

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        if ($menu->moveSequence($postDirection)) {
            echo 'done';
        } else {
            echo 'Sequence could not be changed.';
        }
        exit();
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'sequence') {
        echo $e->getMessage();
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
