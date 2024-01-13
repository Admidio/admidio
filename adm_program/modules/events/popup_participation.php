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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'string', array('requireValue' => true));
$getUserUuid  = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

// Initialize local variables
$disableAdditionalGuests = HtmlForm::FIELD_HIDDEN;
$disableComments         = HtmlForm::FIELD_HIDDEN;
$gMessage->showInModalWindow();

// Get the date object
$event = new TableEvent($gDb);
$event->readDataByUuid($getEventUuid);

// Get the fingerprint of calling user. If is not the user itself check the requesting user whether it has the permission to edit the states
if ($gCurrentUser->getValue('usr_uuid') === $getUserUuid) {
    if (!$event->allowedToParticipate()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
} else {
    if (!$gCurrentUser->isAdministrator() && !$gCurrentUser->isLeaderOfRole((int) $event->getValue('dat_rol_id'))) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

// Read participants
$participants = new Participants($gDb, (int) $event->getValue('dat_rol_id'));
$participantsArray = $participants->getParticipantsArray();

// If extended options for participation are allowed then show in form
if ((int) $event->getValue('dat_allow_comments') === 1 || (int) $event->getValue('dat_additional_guests') === 1) {
    if ((int) $event->getValue('dat_allow_comments') === 1) {
        $disableComments = HtmlForm::FIELD_DEFAULT;
    }
    if ((int) $event->getValue('dat_additional_guests') === 1) {
        $disableAdditionalGuests = HtmlForm::FIELD_DEFAULT;
    }
}

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

try {
    $member = new TableMembers($gDb);
    $member->readDataByColumns(array('mem_rol_id' => (int)$event->getValue('dat_rol_id'), 'mem_usr_id' => $user->getValue('usr_id')));
} catch (AdmException $e) {
    $e->showHtml();
}

// Write header with charset utf8
header('Content-type: text/html; charset=utf-8');

// Add javascript
echo '<script>
    $("button[id^=btn_attend_]").click(function() {
        // Select current form and action attribute
        var submitParticipationForm = $(this).get(0).form;
        var formAction = $(submitParticipationForm).attr("action");

        // add value 3 to mode attribute in link for participation
        $(submitParticipationForm).attr("action", formAction + 3);
        submitParticipationForm.submit();
    });

    $("button[id^=btn_tentative_]").click(function() {
        var submitParticipationForm = $(this).get(0).form;
        var formAction = $(submitParticipationForm).attr("action");

        $(submitParticipationForm).attr("action", formAction + 7);
        submitParticipationForm.submit();
    });

    $("button[id^=btn_refuse_]").click(function() {
        var submitParticipationForm = $(this).get(0).form;
        var formAction = $(submitParticipationForm).attr("action");

        $(submitParticipationForm).attr("action", formAction + 4);
        submitParticipationForm.submit();
    });
</script>';

// Define form
$participationForm = new HtmlForm(
    'participate_form_'. $getEventUuid,
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/events/events_function.php', array('dat_uuid' => $getEventUuid, 'user_uuid' => $getUserUuid, 'mode' => '')),
    null,
    array('type' => 'default', 'method' => 'post', 'setFocus' => false)
);
$participationForm->addHtml('
    <div class="modal-header">
        <h3 class="modal-title">' .$gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION') . '</h3>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <div class="modal-body">
        <h5>' .$event->getValue('dat_headline'). ': ' .$event->getDateTimePeriod(). '</h5>
    ');
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
    (int) $member->getValue('mem_count_guests'),
    array('class' => 'form-control', 'type' => 'number', 'property' => $disableAdditionalGuests)
);
$participationForm->addHtml('</div><div class="modal-footer">');
$participationForm->openButtonGroup();
$participationForm->addButton(
    'btn_attend_' . $getEventUuid,
    $gL10n->get('SYS_PARTICIPATE'),
    array('icon' => 'fa-check-circle', 'class' => 'admidio-event-approval-state-attend')
);

if ($gSettingsManager->getBool('events_may_take_part')) {
    $participationForm->addButton(
        'btn_tentative_' . $getEventUuid,
        $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE'),
        array('icon' => 'fa-question-circle', 'class' => 'admidio-event-approval-state-tentative')
    );
}

$participationForm->addButton(
    'btn_refuse_' . $getEventUuid,
    $gL10n->get('SYS_CANCEL'),
    array('icon' => 'fa-times-circle', 'class' => 'admidio-event-approval-state-cancel')
);
$participationForm->closeButtonGroup();
$participationForm->addHtml('</div></div>');
// Output form
echo $participationForm->show();
