<?php
global $gDb, $gProfileFields, $gL10n;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Events\Entity\Event;
use Admidio\Infrastructure\Language;
use Admidio\Events\ValueObject\Participants;
use Admidio\Categories\Entity\Category;

header('Content-Type: application/json; charset=utf-8');
$endpointName = 'event/list';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

try {
    // Check if events module is enabled
    if (!$gSettingsManager->getBool('events_module_enabled')) {
        admidioApiError('Events module is disabled', 403, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ]);
    }

    // Get optional category filter
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'string', ['defaultValue' => '']);
    $start = admFuncVariableIsValid($_GET, 'start', 'string');
    $end = admFuncVariableIsValid($_GET, 'end', 'string');
    $getLimit   = admFuncVariableIsValid($_GET, 'limit', 'int', ['defaultValue' => 10]);
    $getOffset  = admFuncVariableIsValid($_GET, 'offset', 'int', ['defaultValue' => 0]);

    // Get all visible category IDs for the current user
    $visibleCatIds = $currentUser->getAllVisibleCategories('EVT');
    
    if (empty($visibleCatIds)) {
        // User has no visible events
        echo json_encode(['events' => []]);
        exit();
    }

    // Create object for events
    $eventsModule = new ModuleEvents();
    $eventsModule->setDateRange($start, $end);
    
    // Set parameters
    if ($getCatUuid !== '') {
        $category = new Category($gDb);
        if ($category->readDataByUuid($getCatUuid)) {
            $eventsModule->setParameter('cat_id', $category->getValue('cat_id'));
    }
    }
    
    // Fetch data using the module class
    $eventsData = $eventsModule->getDataSet($getOffset, $getLimit);
    
    $events = [];
    $event = new Event($gDb);

    foreach ($eventsData['recordset'] as $row) {
        // Load data into TableEvent object for easy access and handling
        $event->clear();
        $event->setArray($row);
        $participantsArray = array();
        $outputNumberMembers = '';
        $outputNumberLeaders = '';
    
        $catName = $event->getValue('cat_name');
        if (Language::isTranslationStringId($catName)) {
            $catName = $gL10n->get($catName);
    }

        $creatorName = $row['create_name'] ?? '';
        $changerName = $row['change_name'] ?? '';
        $rolId = (int)$event->getValue('dat_rol_id');
        $eventUuid = (string)$event->getValue('dat_uuid');
        $allow_registration = false;
        $show_participants = false;

        if ($rolId > 0) {
            $participants = new Participants($gDb, $rolId);

            // check the rights if the user is allowed to view the participants, or he is allowed to participate
            if ($currentUser->hasRightViewRole($rolId)
        || $row['mem_leader'] == 1
        || $currentUser->isAdministratorEvents()
        || $event->allowedToParticipate()) {
            $outputNumberMembers = $participants->getCount();
            $outputNumberLeaders = $participants->getNumLeaders();
            $participantsArray = $participants->getParticipantsArray();
            }
            $show_participants = ($currentUser->isAdministratorEvents() || !$event->deadlineExceeded()) && count($participantsArray) > 0 && ($currentUser->isAdministratorEvents() || $participants->isMemberOfEvent($currentUserId));
            $allow_registration = $event->possibleToParticipate();
    }
    

        $events[] = [
            'id' => (int)$event->getValue('dat_id'),
            'uuid' => $eventUuid,
            'headline' => $event->getValue('dat_headline'),
            'description' => (string)$event->getValue('dat_description'),
            'begin'   => $event->getValue('dat_begin'),
            'end'   => $event->getValue('dat_end'),
            'all_day'   => $event->getValue('dat_all_day'),
            'deadline'   => $event->getValue('dat_deadline'),
            'location'   => $event->getValue('dat_location'),
            'country'   => $event->getValue('dat_country'),
            'is_allow_registration' => !empty($rolId),
            'allow_registration' => $allow_registration,
            'deadline_exceed' => !empty($rolId) ? $event->deadlineExceeded() : true,
            'mem_usr_id' => $event->getValue('member_date_role'),
            'member_approval_state' => $event->getValue('member_approval_state'),
            'show_comments' => (bool) $event->getValue('dat_allow_comments'),
            'mem_comment' => $event->getValue('comment'),
            'show_participants' => $show_participants,
            'participants' => $participantsArray,
            'category' => [
        'id' => (int)$event->getValue('dat_cat_id'),
        'uuid' => $event->getValue('cat_uuid'),
        'name' => $catName
            ],
            'canDownload' => true,
            'download' => array(
        'url' => SecurityUtils::encodeUrl(
                    FOLDER_MODULES . '/events/events_function.php',
                    array('dat_uuid' => $eventUuid, 'mode' => 'export')
        ),
            ),
            'creator' => [
        'id' => (int)$event->getValue('dat_usr_id_create'),
        'name' => $creatorName
            ],
            'created_at' => date('Y-m-d H:i', strtotime($event->getValue('dat_timestamp_create'))),
            'changed_by' => !empty($event->getValue('dat_usr_id_change')) ? [
        'id' => (int)$event->getValue('dat_usr_id_change'),
        'name' => $changerName
            ] : null,
            'changed_at' => !empty($event->getValue('dat_timestamp_change')) ? date('Y-m-d H:i', strtotime($event->getValue('dat_timestamp_change'))) : null
        ];
    }

    echo json_encode(['events' => $events]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ]);
}
