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
 *         ROL = Categories for roles
 *         LNK = Categories for weblinks
 *         ANN = Categories for announcements
 *         USF = Categories for profile fields
 *         EVT = Calendars for events
 * uuid  : UUID of the category that should be edited
 * direction : Direction to change the sequence of the category
 ****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\UI\View\Categories;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit', 'save', 'delete', 'sequence')));
    $getType = admFuncVariableIsValid($_GET, 'type', 'string', array('validValues' => array('ROL', 'LNK', 'ANN', 'USF', 'EVT', 'AWA')));
    $getCategoryUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');

    // check rights of the type
    if (($getType === 'ROL' && !$gCurrentUser->manageRoles())
        || ($getType === 'LNK' && !$gCurrentUser->editWeblinksRight())
        || ($getType === 'ANN' && !$gCurrentUser->editAnnouncements())
        || ($getType === 'USF' && !$gCurrentUser->editUsers())
        || ($getType === 'EVT' && !$gCurrentUser->editEvents())
        || ($getType === 'AWA' && !$gCurrentUser->editUsers())) {
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
            $page = new Categories('adm_categories_list');
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
            $page = new Categories('adm_categories_edit');
            $page->createEditForm($getType, $getCategoryUUID);
            $page->show();
            break;

        case 'save':
            $categoriesModule = new \Admidio\Domain\Service\Categories($gDb, $getType, $getCategoryUUID);
            $categoriesModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // delete menu entry

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new TableCategory($gDb);
            $menu->readDataByUuid($getCategoryUUID);
            $menu->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableMenu::MOVE_UP, TableMenu::MOVE_DOWN)));

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new TableCategory($gDb);
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
