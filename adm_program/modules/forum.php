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
 * category_uuid : Array of category UUIDs whose topics should be shown
 ***********************************************************************************************
 */

use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\ForumPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // check if module is active
    if (!$gSettingsManager->getBool('forum_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'cards', 'validValues' => array('cards', 'list', 'topic', 'topic_edit', 'topic_save', 'topic_delete', 'post_edit', 'post_save', 'post_delete')));
    $getTopicUUID = admFuncVariableIsValid($_GET, 'topic_uuid', 'uuid');
    $getCategoryUUID = admFuncVariableIsValid($_GET, 'category_uuid', 'uuid');

    switch ($getMode) {
        case 'cards':
            $headline = $gL10n->get('SYS_FORUM');
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-chat-dots-fill');

            // create html page object
            $page = new ForumPresenter('adm_forum_cards', $headline);
            $page->createForumCards();
            $page->show();
            break;

        case 'list':
            $headline = $gL10n->get('SYS_MENU');
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-chat-dots-fill');

            // create html page object
            $page = new ForumPresenter('adm_forum_list', $headline);
            $page->createForumList();
            $page->show();
            break;

        case 'topic_edit':
            if ($getTopicUUID !== '') {
                $headline = $gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_TOPIC')));
            } else {
                $headline = $gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_TOPIC')));
            }
            $gNavigation->addUrl(CURRENT_URL, $headline);

            // create html page object
            $page = new ForumPresenter('adm_forum_topic_edit', $headline);
            $page->createTopicEditForm($getTopicUUID);
            $page->show();
            break;

        case 'topic_save':
            $menuModule = new ForumService($gDb);
            $menuModule->saveTopic($getTopicUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'topic_delete':
            // delete menu entry

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $menu = new MenuEntry($gDb);
            $menu->readDataByUuid($getMenuUUID);
            $menu->delete();
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
