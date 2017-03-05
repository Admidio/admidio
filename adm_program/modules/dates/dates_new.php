<?php
/**
 ***********************************************************************************************
 * Create and edit dates
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_id   - ID of the event that should be edited
 * headline - Headline for the event
 *            (Default) Events
 * copy : true - The event of the dat_id will be copied and the base for this new event
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getDateId   = admFuncVariableIsValid($_GET, 'dat_id',   'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCopy     = admFuncVariableIsValid($_GET, 'copy',     'bool');

// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if(!$gCurrentUser->editDates())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// lokale Variablen der Uebergabevariablen initialisieren
$dateRegistrationPossible = false;
$dateCurrentUserAssigned  = false;

// set headline of the script
if($getCopy)
{
    $headline = $gL10n->get('SYS_COPY_VAR', $getHeadline);
    $mode = 5;
}
elseif($getDateId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
    $mode = 5;
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
    $mode = 1;
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create date object
$date = new TableDate($gDb);

if(isset($_SESSION['dates_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben

    // first set date and time field to a datetime and add this to date class
    $_SESSION['dates_request']['dat_begin'] = $_SESSION['dates_request']['date_from'].' '.$_SESSION['dates_request']['date_from_time'];
    $_SESSION['dates_request']['dat_end']   = $_SESSION['dates_request']['date_to'].' '.$_SESSION['dates_request']['date_to_time'];

    $date->setArray($_SESSION['dates_request']);

    // get the selected roles for visibility
    $dateRoles = $_SESSION['dates_request']['date_roles'];

    // check if a registration to this event is possible
    if(array_key_exists('date_registration_possible', $_SESSION['dates_request']))
    {
        $dateRegistrationPossible = (bool) $_SESSION['dates_request']['date_registration_possible'];
    }

    // check if current user is assigned to this date
    if(array_key_exists('date_current_user_assigned', $_SESSION['dates_request']))
    {
        $dateCurrentUserAssigned = (bool) $_SESSION['dates_request']['date_current_user_assigned'];
    }

    unset($_SESSION['dates_request']);
}
else
{
    // read all roles that could see this event
    if($getDateId === 0)
    {
        // bei neuem Termin Datum mit aktuellen Daten vorbelegen
        $now = new DateTime();
        $oneHourOffset = new DateInterval('PT1H');
        $twoHourOffset = new DateInterval('PT2H');
        $beginDate = $now->add($oneHourOffset)->format('Y-m-d H:00:00');
        $endDate   = $now->add($twoHourOffset)->format('Y-m-d H:00:00');
        $date->setValue('dat_begin', $beginDate);
        $date->setValue('dat_end',   $endDate);

        // a new event will be visible for all users per default
        $date->setVisibleRoles(array(0));
        $dateRoles = array(0);
    }
    else
    {
        $date->readDataById($getDateId);

        // get the saved roles for visibility
        $dateRoles = $date->getVisibleRoles();

        // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
        if(!$date->editRight())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }

    // check if a registration to this event is possible
    if($date->getValue('dat_rol_id') > 0)
    {
        $dateRegistrationPossible = true;
    }
    // check if current user is assigned to this date
    $dateCurrentUserAssigned = $gCurrentUser->isLeaderOfRole($date->getValue('dat_rol_id'));
}

if($date->getValue('dat_rol_id') > 0)
{
    $dateRoleID = $date->getValue('dat_rol_id');
    $role = new TableRoles($gDb, $dateRoleID);
}
else
{
    $dateRoleID = '0';
    $role = new TableRoles($gDb);
}

// create html page object
$page = new HtmlPage($headline);

$page->addJavascriptFile('adm_program/system/js/date-functions.js');
$page->addJavascript('
    // Funktion blendet Zeitfelder ein/aus
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
        if ($("#date_registration_possible:checked").val() !== undefined) {
            $("#date_current_user_assigned_group").show("slow");
            $("#dat_max_members_group").show("slow");
            $("#date_right_list_view_group").show("slow");
            $("#date_right_send_mail_group").show("slow");
        } else {
            $("#date_current_user_assigned_group").hide();
            $("#dat_max_members_group").hide();
            $("#date_right_list_view_group").hide();
            $("#date_right_send_mail_group").hide();
        }
    }

    // Funktion belegt das Datum-bis entsprechend dem Datum-Von
    function setDateTo() {
        var dateFrom = Date.parseDate($("#date_from").val(), "'.$gPreferences['system_date'].'");
        var dateTo   = Date.parseDate($("#date_to").val(), "'.$gPreferences['system_date'].'");

        if (dateFrom.getTime() > dateTo.getTime()) {
            $("#date_to").val($("#date_from").val());
            $("#date_to").datepicker("update");
        }
    }

    function setLocationCountry() {
        if ($("#dat_location").val().length > 0) {
            $("#dat_country_group").show();
            $("#dat_country").focus();
        } else {
            $("#dat_country_group").hide();
        }
    }');

$page->addJavascript('
    var dateRoleID = '.$dateRoleID.';

    setAllDay();
    setDateParticipation();
    setLocationCountry();

    $("#date_registration_possible").click(function() { setDateParticipation(); });
    $("#dat_all_day").click(function() { setAllDay(); });
    $("#dat_location").change(function() { setLocationCountry(); });
    $("#date_from").change(function() { setDateTo(); });

    // if date participation should be removed than ask user
    $("#btn_save").click(function (event) {
        event.preventDefault();

        if (dateRoleID > 0 && $("#date_registration_possible").is(":checked") == false) {
            var msg_result = confirm("'.$gL10n->get('DAT_REMOVE_APPLICATION').'");
            if(msg_result) {
                $("#dates_edit_form").submit();
            }
        } else {
            $("#dates_edit_form").submit();
        }
    });', true);

// add back link to module menu
$datesMenu = $page->getMenu();
$datesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('dates_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/dates/dates_function.php?dat_id='.$getDateId.'&amp;mode='.$mode.'&amp;copy='.$getCopy, $page);

$form->openGroupBox('gb_title_location', $gL10n->get('SYS_TITLE').' & '.$gL10n->get('DAT_LOCATION'));
$form->addInput('dat_headline', $gL10n->get('SYS_TITLE'), $date->getValue('dat_headline'), array('maxLength' => 100, 'property' => FIELD_REQUIRED));

// if a map link should be shown in the event then show help text and a field where the user could choose the country
if($gPreferences['dates_show_map_link'] == true)
{
    $form->addInput('dat_location', $gL10n->get('DAT_LOCATION'), $date->getValue('dat_location'), array('maxLength' => 100, 'helpTextIdLabel' => 'DAT_LOCATION_LINK'));

    if(strlen($date->getValue('dat_country')) === 0 && $getDateId === 0)
    {
        $date->setValue('dat_country', $gPreferences['default_country']);
    }
    $form->addSelectBox('dat_country', $gL10n->get('SYS_COUNTRY'), $gL10n->getCountries(), array('defaultValue' => $date->getValue('dat_country', 'database')));
}
else
{
    $form->addInput('dat_location', $gL10n->get('DAT_LOCATION'), $date->getValue('dat_location'), array('maxLength' => 100));
}

// if room selection is activated then show a selectbox with all rooms
if($gPreferences['dates_show_rooms'] == true)
{
    if($gDbType === 'mysql')
    {
        $sql = 'SELECT room_id, CONCAT(room_name, \' (\', room_capacity, \'+\', IFNULL(room_overhang, \'0\'), \')\')
                  FROM '.TBL_ROOMS.'
              ORDER BY room_name';
    }
    else
    {
        $sql = 'SELECT room_id, room_name || \' (\' || room_capacity || \'+\' || COALESCE(room_overhang, \'0\') || \')\'
                  FROM '.TBL_ROOMS.'
              ORDER BY room_name';
    }
    $form->addSelectBoxFromSql('dat_room_id', $gL10n->get('SYS_ROOM'), $gDb, $sql, array('defaultValue' => $date->getValue('dat_room_id')));
}
$form->closeGroupBox();

$form->openGroupBox('gb_period_calendar', $gL10n->get('SYS_PERIOD').' & '.$gL10n->get('DAT_CALENDAR'));
$form->addCheckbox('dat_all_day', $gL10n->get('DAT_ALL_DAY'), (bool) $date->getValue('dat_all_day'));
$form->addInput('date_from', $gL10n->get('SYS_START'), $date->getValue('dat_begin', $gPreferences['system_date'].' '.$gPreferences['system_time']), array('type' => 'datetime', 'property' => FIELD_REQUIRED));
$form->addInput('date_to', $gL10n->get('SYS_END'), $date->getValue('dat_end', $gPreferences['system_date'].' '.$gPreferences['system_time']), array('type' => 'datetime', 'property' => FIELD_REQUIRED));
$form->addSelectBoxForCategories('dat_cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', 'EDIT_CATEGORIES',
                                 array('property' => FIELD_REQUIRED, 'defaultValue' => $date->getValue('dat_cat_id')));
$form->closeGroupBox();

$form->openGroupBox('gb_visibility_registration', $gL10n->get('DAT_VISIBILITY').' & '.$gL10n->get('SYS_REGISTRATION'));
// add a multiselectbox to the form where the user can choose all roles that should see this event
// first read all relevant roles from database and create an array with them
$sql = 'SELECT rol_id, rol_name, cat_name
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_valid   = 1
           AND rol_visible = 1
           AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
      ORDER BY cat_sequence, rol_name';
$firstEntry = array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null);
$form->addSelectBoxFromSql('date_roles', $gL10n->get('DAT_VISIBLE_TO'), $gDb, $sql, array('property'     => FIELD_REQUIRED,
                                                                                          'defaultValue' => $dateRoles,
                                                                                          'multiselect'  => true,
                                                                                          'firstEntry'   => $firstEntry));

$form->addCheckbox('dat_highlight', $gL10n->get('DAT_HIGHLIGHT_DATE'), (bool) $date->getValue('dat_highlight'));

// if current organization has a parent organization or is child organizations then show option to set this announcement to global
if($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->hasChildOrganizations())
{
    // show all organizations where this organization is mother or child organization
    $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
    $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

    $form->addCheckbox('dat_global', $gL10n->get('SYS_ENTRY_MULTI_ORGA'), (bool) $date->getValue('dat_global'), array('helpTextIdLabel' => array('SYS_DATA_GLOBAL', $organizations)));
}
$form->addCheckbox('date_registration_possible', $gL10n->get('DAT_REGISTRATION_POSSIBLE'), $dateRegistrationPossible, array('helpTextIdLabel' => 'DAT_LOGIN_POSSIBLE'));
$form->addCheckbox('date_current_user_assigned', $gL10n->get('DAT_PARTICIPATE_AT_DATE'), $dateCurrentUserAssigned, array('helpTextIdLabel' => 'DAT_PARTICIPATE_AT_DATE_DESC'));
$form->addInput('dat_max_members', $gL10n->get('DAT_PARTICIPANTS_LIMIT'), $date->getValue('dat_max_members'),
                array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 99999, 'helpTextIdLabel' => 'DAT_MAX_MEMBERS'));
$form->addCheckbox('date_right_list_view', $gL10n->get('DAT_RIGHT_VIEW_PARTICIPANTS'), (bool) $role->getValue('rol_this_list_view'));
$form->addCheckbox('date_right_send_mail', $gL10n->get('DAT_RIGHT_MAIL_PARTICIPANTS'), (bool) $role->getValue('rol_mail_this_role'));
$form->closeGroupBox();

$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('dat_description', '', $date->getValue('dat_description'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($date->getValue('dat_usr_id_create'), $date->getValue('dat_timestamp_create'), $date->getValue('dat_usr_id_change'), $date->getValue('dat_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
