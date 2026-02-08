<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of announcements
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gDb, $gProfileFields, $gCurrentUser;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Announcements\Entity\Announcement;
use Admidio\Categories\Entity\Category;
use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Infrastructure\Language;

header('Content-Type: application/json; charset=utf-8');
$endpointName = 'announcement/list';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

try {
    // Check if announcements module is enabled
    if (!$gSettingsManager->getBool('announcements_module_enabled')) {
        admidioApiError('Announcements module is disabled', 403, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ]);
    }

    // Get optional category filter
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'string', ['defaultValue' => '']);
    $getLimit   = admFuncVariableIsValid($_GET, 'limit', 'int', ['defaultValue' => 10]);
    $getOffset  = admFuncVariableIsValid($_GET, 'offset', 'int', ['defaultValue' => 0]);

    // Get all visible category IDs for the current user
    $visibleCatIds = $gCurrentUser->getAllVisibleCategories('ANN');
    
    if (empty($visibleCatIds)) {
        // User has no visible announcement categories
        echo json_encode(['announcements' => []]);
        exit();
    }

    // Create object for announcements
    $announcementsModule = new AnnouncementsService($gDb);
    
    // Set parameters
    if ($getCatUuid !== '') {
        $announcementsModule = new AnnouncementsService($gDb, $getCatUuid);
    }
    
    // Fetch data using the module class
    $announcementsData = $announcementsModule->findAll($getOffset, $getLimit);
    
    $announcements = [];
    $announcementObj = new Announcement($gDb);

    foreach ($announcementsData as $row) {
        // Load data into TableAnnouncement object for easy access and handling
        $announcementObj->clear();
        $announcementObj->setArray($row);
    
        $catName = $announcementObj->getValue('cat_name');
        if (Language::isTranslationStringId($catName)) {
            $catName = $gL10n->get($catName);
    }

        $creatorName = $row['create_firstname'] . ' ' . $row['create_surname'];
        $changerName = $row['change_firstname'] . ' ' . $row['change_surname'];

        $announcements[] = [
            'id' => (int)$announcementObj->getValue('ann_id'),
            'uuid' => $announcementObj->getValue('ann_uuid'),
            'headline' => $announcementObj->getValue('ann_headline'),
            'description' => (string)$announcementObj->getValue('ann_description'), // Helper handles HTML
            'category' => [
        'id' => (int)$announcementObj->getValue('ann_cat_id'),
        'uuid' => $announcementObj->getValue('cat_uuid'),
        'name' => $catName
            ],
            'creator' => [
        'id' => (int)$announcementObj->getValue('ann_usr_id_create'),
        'name' => $creatorName
            ],
            'created_at' => date('Y-m-d H:i', strtotime($announcementObj->getValue('ann_timestamp_create'))),
            'changed_by' => !empty($announcementObj->getValue('ann_usr_id_change')) ? [
        'id' => (int)$announcementObj->getValue('ann_usr_id_change'),
        'name' => $changerName
            ] : null,
            'changed_at' => !empty($announcementObj->getValue('ann_timestamp_change')) ? date('Y-m-d H:i', strtotime($announcementObj->getValue('ann_timestamp_change'))) : null
        ];
    }

    echo json_encode(['announcements' => $announcements]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ]);
}
