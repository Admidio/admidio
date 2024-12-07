<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode     : list     - (default) Show page with a list of all menu entries
 *             edit     - Show form to create or edit a menu entry
 *             save     - Save the data of the form
 *             delete   - Delete menu entry
 *             sequence - Change sequence for parameter men_id
 * uuid      : UUID of the menu entry that should be edited
 * direction : Direction to change the sequence of the menu entry
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\UI\View\Menu;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit', 'save', 'delete', 'sequence')));
    $getMenuUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');

    // check rights to use this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'list':
            $headline = $gL10n->get('SYS_MENU');
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-menu-button-wide-fill');

            // create html page object
            $page = new Menu('adm_menu_configuration', $headline);
            $page->createList();
            $page->show();
            break;

        case 'edit':
            if ($getMenuUUID !== '') {
                $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_MENU')));
            } else {
                $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_MENU')));
            }
            $gNavigation->addUrl(CURRENT_URL, $headline);

            // create html page object
            $page = new Menu('adm_menu_configuration_edit', $headline);
            $page->createEditForm($getMenuUUID);
            $page->show();
            break;

        case 'save':
            $menuModule = new \Admidio\Menu\Service\Menu($gDb, $getMenuUUID);
            $menuModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // delete menu entry

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new MenuEntry($gDb);
            $menu->readDataByUuid($getMenuUUID);
            $menu->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new MenuEntry($gDb);
            $menu->readDataByUuid($getMenuUUID);
            $menu->moveSequence($postDirection);
            echo json_encode(array('status' => 'success'));
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('save', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
