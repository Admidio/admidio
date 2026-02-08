<?php
global $gDb, $gProfileFields, $gCurrentUser, $gL10n;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Events\Entity\Event;
use Admidio\Users\Entity\User;
use Admidio\Events\ValueObject\Participants;
use Admidio\Roles\Entity\Membership;

header('Content-Type: application/json; charset=utf-8');
$endpointName = 'event/participate';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admidioApiError('POST required', 405, array(
    'endpoint' => $endpointName,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ));
}

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$rawPayload = $isMultipart ? ($_POST['payload'] ?? '') : file_get_contents('php://input');
$payload = json_decode((string) $rawPayload, true);
if (!is_array($payload)) {
    admidioApiError('Invalid payload', 400, array('endpoint' => $endpointName));
}

// Check if events module is enabled
if (!$gSettingsManager->getBool('events_module_enabled')) {
    admidioApiError('Events module is disabled', 403, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ]);
}
$getEventUuid = trim((string) ($payload['dat_uuid'] ?? ''));
$mode = (int) ($payload['mode'] ?? '');
$comment = trim((string) ($payload['comment'] ?? ''));

if ($mode === '' || !in_array($mode, array(3,4), true)) {
    admidioApiError('Event Mode is required', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}
$participationPossible = true;
$outputMessage = "";
$event = new Event($gDb);
$event->readDataByUuid($getEventUuid);

// read user data
$user = new User($gDb, $gProfileFields);
$user->readDataById($currentUserId);
try {
    if ($event->isNewRecord() || (int)$event->getValue('dat_rol_id') < 0) {
        admidioApiError('Event not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'dat_uuid' => $getEventUuid
        ));
    }
    $member = new Membership($gDb);
    $participants = new Participants($gDb, (int) $event->getValue('dat_rol_id'));
    if ($event->possibleToParticipate() || $participants->isLeader($currentUserId)) {
        $member->readDataByColumns(array('mem_rol_id' => (int) $event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
        $member->setValue('mem_comment', $comment);

        if ($member->isNewRecord()) {
            $member->setValue('mem_begin', DATE_NOW);
    }

        if ($event->getValue('dat_max_members') > 0) {
            $totalMembers = $participants->getCount();

            if ($totalMembers >= $event->getValue('dat_max_members') || !$participants->isMemberOfEvent($user->getValue('usr_id'))) {
                $participationPossible = false; // Participation Limit exceeded and user refused
                admidioApiError('Maximum number of role members to participate', 404, array(
                    'endpoint' => $endpointName,
                    'user_id' => $currentUserId,
                    'dat_uuid' => $getEventUuid
                ));
            }
    }
        $member->save();

        // change the participation status, it's always possible to cancel the participation
        if ($participationPossible || $mode === 4) {
            switch ($mode) {
                case 3:  // User attends to the event
                    $member->startMembership((int) $event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_YES);
                    $outputMessage = "You signed up for the event";
                    break;

                case 4:  // User cancel the event
                    if ($gSettingsManager->getBool('events_save_cancellations')) {
                        // Set user status to refused
                        $member->startMembership((int) $event->getValue('dat_rol_id'), $user->getValue('usr_id'), null, Participants::PARTICIPATION_NO);
                    } else {
                        // Delete entry
                        $member->deleteMembership((int) $event->getValue('dat_rol_id'), $user->getValue('usr_id'));
                    }

                    $outputMessage = "You canceled the participation for the event";
                    break;
            }
    }

        echo json_encode(array(
            'event' => array(
        'uuid' => $event->getValue('dat_uuid'),
        'headline' => $event->getValue('dat_headline'),
        'begin' => $event->getValue('dat_begin'),
        'message' => $outputMessage,
            )
        ));

    }else{
        admidioApiError('Registration not allowed', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'dat_uuid' => $getEventUuid
        ));
    }
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ]);
}
