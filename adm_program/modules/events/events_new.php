<?php
/**
 ***********************************************************************************************
 * Create and edit events
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_uuid - UUID of the event that should be edited
 * copy : true - The event of the dat_id will be copied and the base for this new event
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getEventUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'string');
$getCopy     = admFuncVariableIsValid($_GET, 'copy', 'bool');

// check if module is active
if ((int) $gSettingsManager->get('events_module_enabled') === 0) {
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize local parameters
$eventParticipationPossible = false;
$eventCurrentUserAssigned   = false;
$roleViewSet                = array();
$flagDateRightListView      = false;
$flagDateRightSendMail      = false;

// set headline of the script
if ($getCopy) {
    $headline = $gL10n->get('SYS_COPY_VAR', array($gL10n->get('SYS_EVENT')));
} elseif ($getEventUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_EVENT');
} else {
    $headline = $gL10n->get('SYS_CREATE_EVENT');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create event object
$event = new TableEvent($gDb);

if (isset($_SESSION['events_request'])) {
    // By wrong input, the user returned to this form now write the previously entered contents into the object

    // first set date and time field to a datetime within system format and add this to event class
    $_SESSION['events_request']['dat_begin']    = $_SESSION['events_request']['event_from'].' '.$_SESSION['events_request']['event_from_time'];
    $_SESSION['events_request']['dat_end']      = $_SESSION['events_request']['event_to'].' '.$_SESSION['events_request']['event_to_time'];
    if ((string) $_SESSION['events_request']['event_deadline'] !== '') {
        $_SESSION['events_request']['dat_deadline'] = $_SESSION['events_request']['event_deadline'] . ' ' . $_SESSION['events_request']['event_deadline_time'];
    }
    $eventDescription = admFuncVariableIsValid($_SESSION['events_request'], 'dat_description', 'html');
    $event->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['events_request'])));
    $event->setValue('dat_description', $eventDescription);

    // get the selected roles for visibility
    if (isset($_SESSION['events_request']['adm_event_participation_right']) && $_SESSION['events_request']['adm_event_participation_right'] !== '') {
        $roleViewSet = $_SESSION['events_request']['adm_event_participation_right'];
    }

    if (array_key_exists('event_participation_possible', $_SESSION['events_request'])) {
        $eventParticipationPossible = (bool) $_SESSION['events_request']['event_participation_possible'];
    }
    if (array_key_exists('event_current_user_assigned', $_SESSION['events_request'])) {
        $eventCurrentUserAssigned = (bool) $_SESSION['events_request']['event_current_user_assigned'];
    }
    if (array_key_exists('event_right_list_view', $_SESSION['events_request'])) {
        $flagDateRightListView = (bool) $_SESSION['events_request']['event_right_list_view'];
    }
    if (array_key_exists('event_right_send_mail', $_SESSION['events_request'])) {
        $flagDateRightSendMail = (bool) $_SESSION['events_request']['event_right_send_mail'];
    }

    unset($_SESSION['events_request']);
} else {
    if ($getEventUuid !== '') {
        // read data from database
        $event->readDataByUuid($getEventUuid);

        // get assigned roles of this event
        $eventParticipationRolesObject = new RolesRights($gDb, 'event_participation', (int) $event->getValue('dat_id'));
        $roleViewSet = $eventParticipationRolesObject->getRolesIds();

        // check if the current user could edit this event
        if (!$event->isEditable()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // check if a participation to this event is possible
        if ((int) $event->getValue('dat_rol_id') > 0) {
            $eventParticipationPossible = true;
            $role = new TableRoles($gDb, (int) $event->getValue('dat_rol_id'));
            $flagDateRightListView = (bool) $role->getValue('rol_view_memberships');
            $flagDateRightSendMail = (bool) $role->getValue('rol_mail_this_role');
        }

        // check if current user is assigned to this event
        $eventCurrentUserAssigned = $gCurrentUser->isLeaderOfRole((int) $event->getValue('dat_rol_id'));
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('EVT')) === 0) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // For new events preset event with current event
        $now = new DateTime();
        $oneHourOffset = new DateInterval('PT1H');
        $twoHourOffset = new DateInterval('PT2H');
        $beginDate = $now->add($oneHourOffset)->format('Y-m-d H:00:00');
        $endDate   = $now->add($twoHourOffset)->format('Y-m-d H:00:00');
        $event->setValue('dat_begin', $beginDate);
        $event->setValue('dat_end', $endDate);
    }
}

// create html page object
$page = new HtmlPage('admidio-events-edit', $headline);

$page->addJavascript('
    /**
     * Function hides/show date and time fields
     */
    function setAllDay() {
        if ($("#dat_all_day:checked").val() !== undefined) {
            $("#event_from_time").hide();
            $("#event_to_time").hide();
        } else {
            $("#event_from_time").show("slow");
            $("#event_to_time").show("slow");
        }
    }

    function setEventParticipation() {
        if ($("#event_participation_possible:checked").val() !== undefined) {
            $("#adm_event_participation_right_group").addClass("admidio-form-group-required");
            $("#adm_event_participation_right_group").show("slow");
            $("#event_current_user_assigned_group").show("slow");
            $("#dat_max_members_group").show("slow");
            $("#event_right_list_view_group").show("slow");
            $("#event_right_send_mail_group").show("slow");
            $("#dat_allow_comments_group").show("slow");
            $("#dat_additional_guests_group").show("slow");
            $("#event_deadline_group").show("slow");
        } else {
            $("#adm_event_participation_right_group").hide();
            $("#event_current_user_assigned_group").hide();
            $("#dat_max_members_group").hide();
            $("#event_right_list_view_group").hide();
            $("#event_right_send_mail_group").hide();
            $("#dat_allow_comments_group").hide();
            $("#dat_additional_guests_group").hide();
            $("#event_deadline_group").hide("slow");
        }
    }

    function setLocationCountry() {
        if ($("#dat_location").val().length > 0) {
            $("#dat_country_group").show();
            $("#dat_country").focus();
        } else {
            $("#dat_country_group").hide();
        }
    }
');

$page->addJavascript(
    '
    var eventParticipationPossible = ' . ($eventParticipationPossible ? 1 : 0) .';

    setAllDay();
    setEventParticipation();
    setLocationCountry();

    $("#event_participation_possible").click(function() {
        setEventParticipation();
    });
    $("#dat_all_day").click(function() {
        setAllDay();
    });
    $("#dat_location").change(function() {
        setLocationCountry();
    });
    $("#event_from").change(function() {
        if ($("#event_from").val() > $("#event_to").val()) {
            $("#event_to").val($("#event_from").val());
        }
    });

    // if event participation should be removed than ask user
    $("#btn_save").click(function(event) {
        event.preventDefault();

        if (eventParticipationPossible == 1 && $("#event_participation_possible").is(":checked") === false) {
            var msg_result = confirm("'.$gL10n->get('SYS_REMOVE_EVENT_REGISTRATION').'");
            if (msg_result) {
                $("#event_edit_form").submit();
            }
        } else {
            $("#event_edit_form").submit();
        }
    });',
    true
);

// show form
$form = new HtmlForm('event_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/events/events_function.php', array('dat_uuid' => $getEventUuid, 'mode' => 1, 'copy' => $getCopy)), $page);

$form->openGroupBox('gb_title_location', $gL10n->get('SYS_TITLE').' & '.$gL10n->get('SYS_VENUE'));
$form->addInput(
    'dat_headline',
    $gL10n->get('SYS_TITLE'),
    $event->getValue('dat_headline'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);

// if a map link should be shown in the event then show help text and a field where the user could choose the country
if ($gSettingsManager->getBool('events_show_map_link')) {
    $form->addInput(
        'dat_location',
        $gL10n->get('SYS_VENUE'),
        (string) $event->getValue('dat_location'),
        array('maxLength' => 100, 'helpTextIdLabel' => 'SYS_VENUE_LINK')
    );

    if (!$event->getValue('dat_country') && $getEventUuid === '') {
        $event->setValue('dat_country', $gSettingsManager->getString('default_country'));
    }
    $form->addSelectBox(
        'dat_country',
        $gL10n->get('SYS_COUNTRY'),
        $gL10n->getCountries(),
        array('defaultValue' => $event->getValue('dat_country', 'database'))
    );
} else {
    $form->addInput(
        'dat_location',
        $gL10n->get('SYS_VENUE'),
        $event->getValue('dat_location'),
        array('maxLength' => 100)
    );
}

// if room selection is activated then show a select box with all rooms
if ($gSettingsManager->getBool('events_rooms_enabled')) {
    if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
        $sql = 'SELECT room_id, CONCAT(room_name, \' (\', room_capacity, \'+\', IFNULL(room_overhang, \'0\'), \')\')
                  FROM '.TBL_ROOMS.'
              ORDER BY room_name';
    } else {
        $sql = 'SELECT room_id, room_name || \' (\' || room_capacity || \'+\' || COALESCE(room_overhang, \'0\') || \')\'
                  FROM '.TBL_ROOMS.'
              ORDER BY room_name';
    }
    $form->addSelectBoxFromSql(
        'dat_room_id',
        $gL10n->get('SYS_ROOM'),
        $gDb,
        $sql,
        array('defaultValue' => (int) $event->getValue('dat_room_id'))
    );
}
$form->closeGroupBox();

$form->openGroupBox('gb_period_calendar', $gL10n->get('SYS_PERIOD').' & '.$gL10n->get('SYS_CALENDAR'));
$form->addCheckbox('dat_all_day', $gL10n->get('SYS_ALL_DAY'), (bool) $event->getValue('dat_all_day'));
$form->addInput(
    'event_from',
    $gL10n->get('SYS_START'),
    $event->getValue('dat_begin', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'event_to',
    $gL10n->get('SYS_END'),
    $event->getValue('dat_end', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'cat_uuid',
    $gL10n->get('SYS_CALENDAR'),
    $gDb,
    'EVT',
    HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $event->getValue('cat_uuid'))
);
$form->closeGroupBox();

$form->openGroupBox('gb_visibility_registration', $gL10n->get('SYS_VISIBILITY').' & '.$gL10n->get('SYS_REGISTRATION'));
$form->addCheckbox('dat_highlight', $gL10n->get('SYS_HIGHLIGHT_EVENT'), (bool) $event->getValue('dat_highlight'));
$form->addCheckbox(
    'event_participation_possible',
    $gL10n->get('SYS_REGISTRATION_POSSIBLE'),
    $eventParticipationPossible,
    array('helpTextIdLabel' => 'SYS_ENABLE_EVENT_REGISTRATION')
);

// add a multi select box to the form where the user can choose all roles whose members could participate in this event
// read all roles of the current organization
$sqlViewRoles = 'SELECT rol_id, rol_name, cat_name
                   FROM '.TBL_ROLES.'
             INNER JOIN '.TBL_CATEGORIES.'
                     ON cat_id = rol_cat_id
                  WHERE rol_valid  = true
                    AND rol_system = false
                    AND cat_name_intern <> \'EVENTS\'
                    AND cat_org_id = ? -- $gCurrentOrgId
               ORDER BY cat_sequence, rol_name';
$sqlDataView = array(
    'query'  => $sqlViewRoles,
    'params' => array($gCurrentOrgId)
);

// show select box with all assigned roles
$form->addSelectBoxFromSql(
    'adm_event_participation_right',
    $gL10n->get('SYS_REGISTRATION_POSSIBLE_FOR'),
    $gDb,
    $sqlDataView,
    array(
        'defaultValue' => $roleViewSet,
        'multiselect'  => true
    )
);
$form->addCheckbox(
    'event_current_user_assigned',
    $gL10n->get('SYS_PARTICIPATE_AT_EVENT'),
    $eventCurrentUserAssigned,
    array('helpTextIdLabel' => 'SYS_PARTICIPATE_AT_EVENT_DESC')
);
$form->addCheckbox(
    'dat_allow_comments',
    $gL10n->get('SYS_ALLOW_USER_COMMENTS'),
    (bool) $event->getValue('dat_allow_comments'),
    array('helpTextIdLabel' => 'SYS_ALLOW_USER_COMMENTS_DESC')
);
$form->addCheckbox(
    'dat_additional_guests',
    $gL10n->get('SYS_ALLOW_ADDITIONAL_GUESTS'),
    (bool) $event->getValue('dat_additional_guests'),
    array('helpTextIdLabel' => 'SYS_ALLOW_ADDITIONAL_GUESTS_DESC')
);
$form->addInput(
    'dat_max_members',
    $gL10n->get('SYS_PARTICIPANTS_LIMIT'),
    (int) $event->getValue('dat_max_members'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextIdLabel' => 'SYS_EVENT_MAX_MEMBERS')
);
$form->addInput(
    'event_deadline',
    $gL10n->get('SYS_DEADLINE'),
    $event->getValue('dat_deadline', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'helpTextIdLabel' => 'SYS_EVENT_DEADLINE_DESC')
);
$form->addCheckbox('event_right_list_view', $gL10n->get('SYS_RIGHT_VIEW_PARTICIPANTS'), $flagDateRightListView);
$form->addCheckbox('event_right_send_mail', $gL10n->get('SYS_RIGHT_MAIL_PARTICIPANTS'), $flagDateRightSendMail);
$form->closeGroupBox();

$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('dat_description', '', $event->getValue('dat_description'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $event->getValue('dat_usr_id_create'),
    $event->getValue('dat_timestamp_create'),
    (int) $event->getValue('dat_usr_id_change'),
    $event->getValue('dat_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
