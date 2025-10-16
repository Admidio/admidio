<?php
/**
 ***********************************************************************************************
 * Show announcements pages and handle user input
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode : cards  - Show a card list with all announcement.
 *        list   - Show a table list with all announcement.
 *        edit   - Create or edit an announcement
 *        save   - Save form data of an announcement
 *        delete - Delete an announcement
 * announcement_uuid : UUID of the topic that should be shown.
 * category_uuid : Array of category UUIDs whose topics should be shown
 * copy   = true : Announcement of the announcement_uuid will be copied and the base for this new announcement
 * offset        : Position of query recordset where the visual output should start
 ***********************************************************************************************
 */

use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\AnnouncementsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'cards',
            'validValues' => array('cards', 'list', 'edit', 'save', 'delete')
        )
    );
    $getAnnouncementUUID = admFuncVariableIsValid($_GET, 'announcement_uuid', 'uuid');
    $getCategoryUUID = admFuncVariableIsValid($_GET, 'category_uuid', 'uuid');
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');
    $getOffset = admFuncVariableIsValid($_GET, 'offset', 'int');

    // check if module is active
    if ($gSettingsManager->getInt('announcements_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ($gSettingsManager->getInt('announcements_module_enabled') === 1
        && !in_array($getMode, array('cards', 'list')) && !$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    } elseif ($gSettingsManager->getInt('announcements_module_enabled') === 2 && !$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'cards':
            $page = new AnnouncementsPresenter($getCategoryUUID);
            $page->createCards($getOffset);
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-chat-dots-fill');
            $page->show();
            break;

        case 'list':
            $page = new AnnouncementsPresenter($getCategoryUUID);
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-chat-dots-fill');
            $page->show();
            break;

        case 'edit':
            $page = new AnnouncementsPresenter();
            $page->createEditForm($getAnnouncementUUID, $getCopy);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save':
            $announcementsService = new AnnouncementsService($gDb);
            $announcementsService->save($getAnnouncementUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            $announcementsService = new AnnouncementsService($gDb);
            $announcementsService->delete($getAnnouncementUUID);

            echo json_encode(array('status' => 'success'));
            break;
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('save', 'delete')));
}
