<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode : cards        - Show a list with all open registrations that should be approved.
 *        list         - Show users with similar names with the option to assign the registration to them.
 *        topic        - Assign registration to a user who is already a member of the organization
 *        topic_edit   - Assign registration to a user who is NOT yet a member of the organization
 *        topic_save   - Delete user account
 *        topic_delete - Delete user account
 *        post_edit    - Create new user and assign roles automatically without dialog
 *        post_save    - Registration does not need to be assigned, simply send login data
 *        post_delete  - Registration does not need to be assigned, simply send login data
 * topic_uuid     : UUID of the topic that should be shown.
 * category_uuids : Array of category UUIDs whose topics should be shown
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SystemMail;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\View\Forum;

try {
    require_once(__DIR__ . '/../system/common.php');

    // check if module is active
    if (!$gSettingsManager->getBool('forum_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('cards', 'list', 'topic', 'topic_edit', 'topic_save', 'topic_delete', 'post_edit', 'post_save', 'post_delete')));
    $getTopicUUID = admFuncVariableIsValid($_GET, 'topic_uuid', 'uuid');
    $getCategoryUUIDs = admFuncVariableIsValid($_GET, 'category_uuids', 'array');

    switch ($getMode) {
        case 'cards':
            $headline = $gL10n->get('SYS_MENU');
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-menu-button-wide-fill');

            // create html page object
            $page = new Forum('adm_menu_configuration', $headline);
            $page->createList();
            $page->show();
            break;

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
            $menuModule = new MenuService($gDb, $getMenuUUID);
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
    if (in_array($getMode, array('topic_save', 'topic_delete', 'post_save', 'post_delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
