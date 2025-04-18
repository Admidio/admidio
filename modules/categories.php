<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all categories
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode  : list     - (default) Show page with a list of all categories
 *         edit     - Show form to create or edit a category
 *         save     - Save the data of the form
 *         delete   - Delete a category
 *         sequence - Change sequence of a category
 * type  : Type of categories that could be maintained
 *         ANN = Categories for announcements
 *         EVT = Calendars for events
 *         FOT = Categories for forum topics
 *         LNK = Categories for weblinks
 *         ROL = Categories for roles
 *         USF = Categories for profile fields
 * uuid  : UUID of the category that should be edited
 * direction : Direction to change the sequence of the category
 ****************************************************************************/

use Admidio\Categories\Entity\Category;
use Admidio\Categories\Service\CategoryService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\UI\Presenter\CategoriesPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit', 'save', 'delete', 'sequence')));
    $getType = admFuncVariableIsValid($_GET, 'type', 'string', array('validValues' => array('ANN', 'AWA', 'EVT', 'FOT', 'LNK', 'ROL', 'USF')));
    $getCategoryUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');

    // check rights of the type
    if (($getType === 'ANN' && !$gCurrentUser->isAdministratorAnnouncements())
        || ($getType === 'AWA' && !$gCurrentUser->isAdministratorUsers())
        || ($getType === 'EVT' && !$gCurrentUser->isAdministratorEvents())
        || ($getType === 'FOT' && !$gCurrentUser->isAdministratorForum())
        || ($getType === 'LNK' && !$gCurrentUser->isAdministratorWeblinks())
        || ($getType === 'ROL' && !$gCurrentUser->isAdministratorRoles())
        || ($getType === 'USF' && !$gCurrentUser->isAdministratorUsers())) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (in_array($getType, array('edit', 'save', 'delete'))) {
        // check if this category is editable by the current user and current organization
        if (!$category->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    switch ($getMode) {
        case 'list':
            $gNavigation->addUrl(CURRENT_URL, ($getType === 'EVT' ? $gL10n->get('SYS_CALENDARS') : $gL10n->get('SYS_CATEGORIES') ));
            $page = new CategoriesPresenter('adm_categories_list');
            $page->createList($getType);
            $page->show();
            break;

        case 'edit':
            // set headline of the script
            if ($getCategoryUUID !== '') {
                if ($getType === 'EVT') {
                    $headlineSuffix = $gL10n->get('SYS_EDIT_CALENDAR');
                } else {
                    $headlineSuffix = $gL10n->get('SYS_EDIT_CATEGORY');
                }
            } else {
                if ($getType === 'EVT') {
                    $headlineSuffix = $gL10n->get('SYS_CREATE_CALENDAR');
                } else {
                    $headlineSuffix = $gL10n->get('SYS_CREATE_CATEGORY');
                }
            }
            $gNavigation->addUrl(CURRENT_URL, $headlineSuffix);
            $page = new CategoriesPresenter('adm_categories_edit');
            $page->createEditForm($getType, $getCategoryUUID);
            $page->show();
            break;

        case 'save':
            $categoriesModule = new CategoryService($gDb, $getType, $getCategoryUUID);
            $categoriesModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // delete menu entry

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new Category($gDb);
            $menu->readDataByUuid($getCategoryUUID);
            $menu->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new Category($gDb);
            $menu->readDataByUuid($getCategoryUUID);
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
