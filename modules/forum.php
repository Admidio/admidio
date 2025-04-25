<?php
/**
 ***********************************************************************************************
 * Show forum pages and handle user input
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode : cards        - Show a card list with all topics.
 *        list         - Show a table list with all topics.
 *        topic        - Show a topic with all posts
 *        topic_edit   - Edit a topic
 *        topic_save   - Save form data of a topic
 *        topic_delete - Delete a topic
 *        post_edit    - Create or edit a post
 *        post_save    - Save form data of a post
 *        post_delete  - Delete a post
 * topic_uuid    : UUID of the topic that should be shown.
 * post_uuid     : UUID of the post that should be shown.
 * category_uuid : Array of category UUIDs whose topics should be shown
 * offset        : Position of query recordset where the visual output should start
 ***********************************************************************************************
 */

use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Forum\Service\ForumService;
use Admidio\Forum\Service\ForumTopicService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\ForumPostPresenter;
use Admidio\UI\Presenter\ForumPresenter;
use Admidio\UI\Presenter\ForumTopicPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => $gSettingsManager->getString('forum_view'),
            'validValues' => array('cards', 'list', 'topic', 'topic_edit', 'topic_save', 'topic_delete', 'post_edit', 'post_save', 'post_delete')
        )
    );
    $getTopicUUID = admFuncVariableIsValid($_GET, 'topic_uuid', 'uuid');
    $getPostUUID = admFuncVariableIsValid($_GET, 'post_uuid', 'uuid');
    $getCategoryUUID = admFuncVariableIsValid($_GET, 'category_uuid', 'uuid');
    $getOffset = admFuncVariableIsValid($_GET, 'offset', 'int');

    // check if module is active
    if ($gSettingsManager->getInt('forum_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ($gSettingsManager->getInt('forum_module_enabled') === 1
        && !in_array($getMode, array('cards', 'list', 'topic')) && !$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'cards':
            $page = new ForumPresenter($getCategoryUUID);
            $page->createCards($getOffset);
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-chat-dots-fill');
            $page->show();
            break;

        case 'list':
            $page = new ForumPresenter($getCategoryUUID);
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-chat-dots-fill');
            $page->show();
            break;

        case 'topic':
            $page = new ForumTopicPresenter($getTopicUUID);
            $page->createCards($getOffset);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'topic_edit':
            $page = new ForumTopicPresenter($getTopicUUID);
            $page->createEditForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'topic_save':
            $forumService = new ForumService($gDb);
            $forumService->saveTopic($getTopicUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'topic_delete':
            // delete forum topic with all posts

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $topic = new Topic($gDb);
            $topic->readDataByUuid($getTopicUUID);
            $topic->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'post_edit':
            $page = new ForumPostPresenter($getPostUUID);
            $page->createEditForm($getTopicUUID);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'post_save':
            $forumService = new ForumTopicService($gDb);
            $postUUID = $forumService->savePost($getPostUUID, $getTopicUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl() . '#adm_post_' . $postUUID));
            break;

        case 'post_delete':
            // delete forum post

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $post = new Post($gDb);
            $post->readDataByUuid($getPostUUID);
            $post->delete();
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
