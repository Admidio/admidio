<?php
/**
 ***********************************************************************************************
 * Create and edit dates
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_uuid - UUID of the event that should be edited
 * headline - Headline for the event
 *            (Default) Events
 * copy : true - The event of the dat_id will be copied and the base for this new event
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getDateUuid = admFuncVariableIsValid($_GET, 'dat_uuid', 'string');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCopy     = admFuncVariableIsValid($_GET, 'copy', 'bool');

// check if module is active
if ((int) $gSettingsManager->get('enable_dates_module') === 0) {
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize local parameters
$dateParticipationPossible = false;
$dateCurrentUserAssigned   = false;
$roleViewSet               = array();
$flagDateRightListView     = false;
$flagDateRightSendMail     = false;

// set headline of the script
if ($getCopy) {
    $headline = $gL10n->get('SYS_COPY_VAR', array($getHeadline));
} elseif ($getDateUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_VAR', array($getHeadline));
} else {
    $headline = $gL10n->get('SYS_CREATE_VAR', array($getHeadline));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create date object
$date = new TableDate($gDb);

if (isset($_SESSION['dates_request'])) {
    // By wrong input, the user returned to this form now write the previously entered contents into the object

    // first set date and time field to a datetime within system format and add this to date class
    $_SESSION['dates_request']['dat_begin']    = $_SESSION['dates_request']['date_from'].' '.$_SESSION['dates_request']['date_from_time'];
    $_SESSION['dates_request']['dat_end']      = $_SESSION['dates_request']['date_to'].' '.$_SESSION['dates_request']['date_to_time'];
    if ((string) $_SESSION['dates_request']['date_deadline'] !== '') {
        $_SESSION['dates_request']['dat_deadline'] = $_SESSION['dates_request']['date_deadline'] . ' ' . $_SESSION['dates_request']['date_deadline_time'];
    }
    $dateDescription = admFuncVariableIsValid($_SESSION['dates_request'], 'dat_description', 'html');
    $date->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['dates_request'])));
    $date->setValue('dat_description', $dateDescription);

    // get the selected roles for visibility
    if (isset($_SESSION['dates_request']['adm_event_participation_right']) && $_SESSION['dates_request']['adm_event_participation_right'] !== '') {
        $roleViewSet = $_SESSION['dates_request']['adm_event_participation_right'];
    }

    if (array_key_exists('date_participation_possible', $_SESSION['dates_request'])) {
        $dateParticipationPossible = (bool) $_SESSION['dates_request']['date_participation_possible'];
    }
    if (array_key_exists('date_current_user_assigned', $_SESSION['dates_request'])) {
        $dateCurrentUserAssigned = (bool) $_SESSION['dates_request']['date_current_user_assigned'];
    }
    if (array_key_exists('date_right_list_view', $_SESSION['dates_request'])) {
        $flagDateRightListView = (bool) $_SESSION['dates_request']['date_right_list_view'];
    }
    if (array_key_exists('date_right_send_mail', $_SESSION['dates_request'])) {
        $flagDateRightSendMail = (bool) $_SESSION['dates_request']['date_right_send_mail'];
    }

    unset($_SESSION['dates_request']);
} else {
    if ($getDateUuid !== '') {
        // read data from database
        $date->readDataByUuid($getDateUuid);

        // get assigned roles of this event
        $eventParticipationRolesObject = new RolesRights($gDb, 'event_participation', (int) $date->getValue('dat_id'));
        $roleViewSet = $eventParticipationRolesObject->getRolesIds();

        // check if the current user could edit this event
        if (!$date->isEditable()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // check if a participation to this event is possible
        if ((int) $date->getValue('dat_rol_id') > 0) {
            $dateParticipationPossible = true;
            $role = new TableRoles($gDb, (int) $date->getValue('dat_rol_id'));
            $flagDateRightListView = (bool) $role->getValue('rol_view_memberships');
            $flagDateRightSendMail = (bool) $role->getValue('rol_mail_this_role');
        }

        // check if current user is assigned to this date
        $dateCurrentUserAssigned = $gCurrentUser->isLeaderOfRole((int) $date->getValue('dat_rol_id'));
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('DAT')) === 0) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // For new events preset date with current date
        $now = new DateTime();
        $oneHourOffset = new DateInterval('PT1H');
        $twoHourOffset = new DateInterval('PT2H');
        $beginDate = $now->add($oneHourOffset)->format('Y-m-d H:00:00');
        $endDate   = $now->add($twoHourOffset)->format('Y-m-d H:00:00');
        $date->setValue('dat_begin', $beginDate);
        $date->setValue('dat_end', $endDate);
    }
}

// create html page object
$page = new HtmlPage('admidio-events-edit', $headline);

$page->addJavascriptFile(ADMIDIO_URL . '/adm_program/system/js/date-functions.js');
$page->addJavascript('
    /**
     * Function hides/show date and time fields
     */
    function setAllDay() {
        if ($("#dat_all_day:checked").val() !== undefined) {
            $("#date_from_time").hide();
            $("#date_to_time").hide();
        } else {
            $("#date_from_time").show("slow");
            $("#date_to_time").show("slow");
        }
    }

    function setDateParticipation() {
        if ($("#date_participation_possible:checked").val() !== undefined) {
            $("#adm_event_participation_right_group").addClass("admidio-form-group-required");
            $("#adm_event_participation_right_group").show("slow");
            $("#date_current_user_assigned_group").show("slow");
            $("#dat_max_members_group").show("slow");
            $("#date_right_list_view_group").show("slow");
            $("#date_right_send_mail_group").show("slow");
            $("#dat_allow_comments_group").show("slow");
            $("#dat_additional_guests_group").show("slow");
            $("#date_deadline_group").show("slow");
        } else {
            $("#adm_event_participation_right_group").hide();
            $("#date_current_user_assigned_group").hide();
            $("#dat_max_members_group").hide();
            $("#date_right_list_view_group").hide();
            $("#date_right_send_mail_group").hide();
            $("#dat_allow_comments_group").hide();
            $("#dat_additional_guests_group").hide();
            $("#date_deadline_group").hide("slow");
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
    var dateParticipationPossible = ' . ($dateParticipationPossible ? 1 : 0) .';

    setAllDay();
    setDateParticipation();
    setLocationCountry();

    $("#date_participation_possible").click(function() {
        setDateParticipation();
    });
    $("#dat_all_day").click(function() {
        setAllDay();
    });
    $("#dat_location").change(function() {
        setLocationCountry();
    });
    $("#date_from").change(function() {
        if ($("#date_from").val() > $("#date_to").val()) {
            $("#date_to").val($("#date_from").val());
        }
    });

    // if date participation should be removed than ask user
    $("#btn_save").click(function(event) {
        event.preventDefault();

        if (dateParticipationPossible == 1 && $("#date_participation_possible").is(":checked") === false) {
            var msg_result = confirm("'.$gL10n->get('DAT_REMOVE_APPLICATION').'");
            if (msg_result) {
                $("#dates_edit_form").submit();
            }
        } else {
            $("#dates_edit_form").submit();
        }
    });',
    true
);

// show form
$form = new HtmlForm('dates_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php', array('dat_uuid' => $getDateUuid, 'mode' => 1, 'copy' => $getCopy)), $page);

$form->openGroupBox('gb_title_location', $gL10n->get('SYS_TITLE').' & '.$gL10n->get('DAT_LOCATION'));
$form->addInput(
    'dat_headline',
    $gL10n->get('SYS_TITLE'),
    $date->getValue('dat_headline'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);

// if a map link should be shown in the event then show help text and a field where the user could choose the country
if ($gSettingsManager->getBool('dates_show_map_link')) {
    $form->addInput(
        'dat_location',
        $gL10n->get('DAT_LOCATION'),
        $date->getValue('dat_location'),
        array('maxLength' => 100, 'helpTextIdLabel' => 'DAT_LOCATION_LINK')
    );

    if (!$date->getValue('dat_country') && $getDateUuid === '') {
        $date->setValue('dat_country', $gSettingsManager->getString('default_country'));
    }
    $form->addSelectBox(
        'dat_country',
        $gL10n->get('SYS_COUNTRY'),
        $gL10n->getCountries(),
        array('defaultValue' => $date->getValue('dat_country', 'database'))
    );
} else {
    $form->addInput(
        'dat_location',
        $gL10n->get('DAT_LOCATION'),
        $date->getValue('dat_location'),
        array('maxLength' => 100)
    );
}

// if room selection is activated then show a select box with all rooms
if ($gSettingsManager->getBool('dates_show_rooms')) {
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
        array('defaultValue' => (int) $date->getValue('dat_room_id'))
    );
}
$form->closeGroupBox();

$form->openGroupBox('gb_period_calendar', $gL10n->get('SYS_PERIOD').' & '.$gL10n->get('DAT_CALENDAR'));
$form->addCheckbox('dat_all_day', $gL10n->get('DAT_ALL_DAY'), (bool) $date->getValue('dat_all_day'));
$form->addInput(
    'date_from',
    $gL10n->get('SYS_START'),
    $date->getValue('dat_begin', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'date_to',
    $gL10n->get('SYS_END'),
    $date->getValue('dat_end', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'cat_uuid',
    $gL10n->get('DAT_CALENDAR'),
    $gDb,
    'DAT',
    HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $date->getValue('cat_uuid'))
);
$form->closeGroupBox();

$form->openGroupBox('gb_visibility_registration', $gL10n->get('DAT_VISIBILITY').' & '.$gL10n->get('SYS_REGISTRATION'));
$form->addCheckbox('dat_highlight', $gL10n->get('DAT_HIGHLIGHT_DATE'), (bool) $date->getValue('dat_highlight'));
$form->addCheckbox(
    'date_participation_possible',
    $gL10n->get('DAT_REGISTRATION_POSSIBLE'),
    $dateParticipationPossible,
    array('helpTextIdLabel' => 'DAT_LOGIN_POSSIBLE')
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
    $gL10n->get('DAT_REGISTRATION_POSSIBLE_FOR'),
    $gDb,
    $sqlDataView,
    array(
        'defaultValue' => $roleViewSet,
        'multiselect'  => true
    )
);
$form->addCheckbox(
    'date_current_user_assigned',
    $gL10n->get('DAT_PARTICIPATE_AT_DATE'),
    $dateCurrentUserAssigned,
    array('helpTextIdLabel' => 'DAT_PARTICIPATE_AT_DATE_DESC')
);
$form->addCheckbox(
    'dat_allow_comments',
    $gL10n->get('DAT_ALLOW_USER_COMMENTS'),
    (bool) $date->getValue('dat_allow_comments'),
    array('helpTextIdLabel' => 'DAT_ALLOW_USER_COMMENTS_DESC')
);
$form->addCheckbox(
    'dat_additional_guests',
    $gL10n->get('DAT_ALLOW_ADDITIONAL_GUESTS'),
    (bool) $date->getValue('dat_additional_guests'),
    array('helpTextIdLabel' => 'DAT_ALLOW_ADDITIONAL_GUESTS_DESC')
);
$form->addInput(
    'dat_max_members',
    $gL10n->get('DAT_PARTICIPANTS_LIMIT'),
    (int) $date->getValue('dat_max_members'),
    array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'step' => 1, 'helpTextIdLabel' => 'DAT_MAX_MEMBERS')
);
$form->addInput(
    'date_deadline',
    $gL10n->get('DAT_DEADLINE'),
    $date->getValue('dat_deadline', $gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
    array('type' => 'datetime', 'helpTextIdLabel' => 'SYS_EVENT_DEADLINE_DESC')
);
$form->addCheckbox('date_right_list_view', $gL10n->get('DAT_RIGHT_VIEW_PARTICIPANTS'), $flagDateRightListView);
$form->addCheckbox('date_right_send_mail', $gL10n->get('DAT_RIGHT_MAIL_PARTICIPANTS'), $flagDateRightSendMail);
$form->closeGroupBox();

$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('dat_description', '', $date->getValue('dat_description'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $date->getValue('dat_usr_id_create'),
    $date->getValue('dat_timestamp_create'),
    (int) $date->getValue('dat_usr_id_change'),
    $date->getValue('dat_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
