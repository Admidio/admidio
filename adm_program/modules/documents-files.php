<?php
/**
 ***********************************************************************************************
 * Show content of the documents module and handle the navigation
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode : list         - Show all documents and files of the current folder.
 *        topic        - Assign registration to a user who is already a member of the organization
 * folder_uuid : UUID of the current folder that should be shown
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\DocumentsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'list',
            'validValues' => array('list')
        )
    );
    $getFolderUUID = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');

    // Check if module is activated
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    switch ($getMode) {
        case 'list':
            // create html page object
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createList();
            if ($getFolderUUID !== '') {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            } else {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-file-earmark-arrow-down-fill');
            }
            $page->show();
            break;

        case 'topic':
            // create html page object
            $page = new ForumTopicPresenter($getTopicUUID);
            $page->createCards($getOffset);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'topic_edit':
            // create html page object
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
    }
} catch (Throwable $e) {
    if ($e->getMessage() === 'LOGIN') {
        require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/login_valid.php');
    } else {
        $gMessage->show($e->getMessage());
    }
}
