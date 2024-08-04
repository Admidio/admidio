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
    $postMenuUUID = admFuncVariableIsValid($_POST, 'uuid', 'uuid');
    $postMode = admFuncVariableIsValid($_POST, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));

    // check rights
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // create menu object
    $menu = new TableMenu($gDb);

    if ($postMenuUUID !== '') {
        $menu->readDataByUuid($postMenuUUID);
    }

    // create menu or update it
    if ($postMode === 'edit') {
        if (isset($_SESSION['menuEditForm'])) {
            $menuEditForm = $_SESSION['menuEditForm'];
            $menuEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        $postIdParent = admFuncVariableIsValid($_POST, 'men_men_id_parent', 'int');
        $postComId = admFuncVariableIsValid($_POST, 'men_com_id', 'int');
        $postName = admFuncVariableIsValid($_POST, 'men_name', 'string', array('default' => ''));
        $postDesc = admFuncVariableIsValid($_POST, 'men_description', 'string', array('default' => ''));
        $postUrl = admFuncVariableIsValid($_POST, 'men_url', 'string', array('default' => ''));
        $postIcon = admFuncVariableIsValid($_POST, 'men_icon', 'string', array('default' => ''));

        // within standard menu items the url should not be changed
        if ($menu->getValue('men_standard')) {
            $postUrl = $menu->getValue('men_url');
        }

        // check url here because it could be a real url or a relative local url
        if (!StringUtils::strValidCharacters($postUrl, 'url')
            && !preg_match('=^[^*;:~<>|\"\\\\]+$=', $postUrl)) {
            throw new AdmException('SYS_URL_INVALID_CHAR', array('SYS_URL'));
        }

        $gDb->startTransaction();

        $menu->setValue('men_icon', $postIcon);
        $menu->setValue('men_men_id_parent', $postIdParent);
        $menu->setValue('men_name', $postName);
        $menu->setValue('men_description', $postDesc);
        if (!$menu->getValue('men_standard')) {
            $menu->setValue('men_url', $postUrl);
            $menu->setValue('men_com_id', $postComId);
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

        if ($gNavigation->count() > 1) {
            $gNavigation->deleteLastUrl();
        } else {
            $gNavigation->addUrl($gHomepage, 'Home');
        }

        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($postMode === 'delete') {
        // delete menu

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        if ($menu->delete()) {
            echo 'done';
        }
        exit();
    } elseif ($postMode === 'sequence') {
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
    if (in_array($postMode, array('delete', 'sequence'))) {
        echo $e->getMessage();
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
