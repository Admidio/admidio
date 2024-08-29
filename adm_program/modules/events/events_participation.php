<?php
/**
 ***********************************************************************************************
 * Participation modal window for events
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * dat_uuid  - UUID of the event
 * user_uuid - UUID of the user whose participation detail shall be set or changed
 ***********************************************************************************************
 */
use Admidio\Exception;
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'uuid', array('requireValue' => true));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

    // Initialize local variables
    $disableAdditionalGuests = Form::FIELD_HIDDEN;
    $disableComments = Form::FIELD_HIDDEN;

    // Get the date object
    $event = new TableEvent($gDb);
    $event->readDataByUuid($getEventUuid);

    // Get the fingerprint of calling user. If is not the user itself check the requesting user whether it has the permission to edit the states
    if ($gCurrentUser->getValue('usr_uuid') === $getUserUuid) {
        if (!$event->allowedToParticipate()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        if (!$gCurrentUser->isAdministrator() && !$gCurrentUser->isLeaderOfRole((int)$event->getValue('dat_rol_id'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    // Read participants
    $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
    $participantsArray = $participants->getParticipantsArray();

    // If extended options for participation are allowed then show in form
    if ((int)$event->getValue('dat_allow_comments') === 1 || (int)$event->getValue('dat_additional_guests') === 1) {
        if ((int)$event->getValue('dat_allow_comments') === 1) {
            $disableComments = Form::FIELD_DEFAULT;
        }
        if ((int)$event->getValue('dat_additional_guests') === 1) {
            $disableAdditionalGuests = Form::FIELD_DEFAULT;
        }
    }

    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    $member = new TableMembers($gDb);
    $member->readDataByColumns(array('mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));

    // Write header with charset utf8
    header('Content-type: text/html; charset=utf-8');

    // Define form
    $participationForm = new Form('eventsParticipationEditForm','modules/events.participation.edit.tpl', '#');
    $participationForm->addMultilineTextInput(
        'dat_comment',
        $gL10n->get('SYS_COMMENT'),
        $member->getValue('mem_comment'),
        6,
        array('class' => 'form-control', 'maxLength' => 1000, 'property' => $disableComments)
    );
    $participationForm->addInput(
        'additional_guests',
        $gL10n->get('SYS_SEAT_AMOUNT'),
        (int)$member->getValue('mem_count_guests'),
        array('class' => 'form-control', 'type' => 'number', 'property' => $disableAdditionalGuests)
    );
    $participationForm->addButton(
        'btn_attend',
        $gL10n->get('SYS_PARTICIPATE'),
        array('icon' => 'bi-check-circle-fill', 'class' => 'admidio-event-approval-state-attend')
    );

    if ($gSettingsManager->getBool('events_may_take_part')) {
        $participationForm->addButton(
            'btn_tentative',
            $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE'),
            array('icon' => 'bi-question-circle-fill', 'class' => 'admidio-event-approval-state-tentative')
        );
    }

    $participationForm->addButton(
        'btn_refuse',
        $gL10n->get('SYS_CANCEL'),
        array('icon' => 'bi-x-circle-fill', 'class' => 'admidio-event-approval-state-cancel')
    );

    $smarty = HtmlPage::createSmartyObject();
    $smarty->assign('urlFormAction', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events_function.php', array('dat_uuid' => $getEventUuid, 'user_uuid' => $getUserUuid, 'mode' => '')));
    $smarty->assign('eventHeadline', $event->getValue('dat_headline'));
    $smarty->assign('eventPeriod', $event->getDateTimePeriod());
    $participationForm->addToSmarty($smarty);
    $gCurrentSession->addFormObject($participationForm);
    echo $smarty->fetch('modules/events.participation.edit.tpl');
} catch (Exception $e) {
    $gMessage->showInModalWindow();
    $gMessage->show($e->getMessage());
}
