<?php
/**
 ***********************************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * dat_id     - ID of the event that should be edited
 * mode   : 1 - Neuen Termin anlegen
 *          2 - Termin loeschen
 *          3 - zum Termin anmelden
 *          4 - vom Termin abmelden
 *          5 - Termin aendern
 *          6 - Termin im iCal-Format exportieren
 * rol_id : vorselektierte Rolle der Rollenauswahlbox
 * copy   : true - The event of the dat_id will be copied and the base for this new event
 * number_role_select : Nummer der Rollenauswahlbox, die angezeigt werden soll
 ***********************************************************************************************
 */
require_once('../../system/common.php');

if($_GET['mode'] == 2)
{
    $gMessage->showHtmlTextOnly(true);
}

// Initialize and check the parameters
$getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'int');
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'int');
$getCopy   = admFuncVariableIsValid($_GET, 'copy',   'bool');
$getNumberRoleSelect = admFuncVariableIsValid($_GET, 'number_role_select', 'int');

$originalDateId = 0;

// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if($getMode !== 6 || $gPreferences['enable_dates_module'] == 2)
{
    // Alle Funktionen, ausser Exportieren und anmelden, duerfen nur eingeloggte User
    require_once('../../system/login_valid.php');
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$gCurrentUser->editDates() && $getMode !== 3 && $getMode !== 4 && $getMode !== 6)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if($getCopy)
{
    $originalDateId = $getDateId;
    $getDateId      = 0;
}

// Terminobjekt anlegen
$date = new TableDate($gDb);

if($getDateId > 0)
{
    $date->readDataById($getDateId);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if(!$date->editRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getMode === 1 || $getMode === 5)  // Neuen Termin anlegen/aendern
{
    $_SESSION['dates_request'] = $_POST;

    // ------------------------------------------------
    // pruefen ob alle notwendigen Felder gefuellt sind
    // ------------------------------------------------

    if(strlen($_POST['dat_headline']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TITLE')));
        // => EXIT
    }
    if(strlen($_POST['date_from']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_START')));
        // => EXIT
    }
    if(strlen($_POST['date_to']) === 0 && $_POST['dat_repeat_type'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_END')));
        // => EXIT
    }
    if(strlen($_POST['date_from_time']) === 0 && !isset($_POST['dat_all_day']))
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START')));
        // => EXIT
    }
    if(strlen($_POST['date_to_time']) === 0 && !isset($_POST['dat_all_day']))
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END')));
        // => EXIT
    }
    if(strlen($_POST['dat_cat_id']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('DAT_CALENDAR')));
        // => EXIT
    }

    if(isset($_POST['dat_all_day']))
    {
        $midnightDateTime = DateTime::createFromFormat('Y-m-d H:i:s', '2000-01-01 00:00:00');
        $_POST['date_from_time'] = $midnightDateTime->format($gPreferences['system_time']);
        $_POST['date_to_time']   = $midnightDateTime->format($gPreferences['system_time']);
        $date->setValue('dat_all_day', 1);
    }
    else
    {
        $date->setValue('dat_all_day', 0);
    }

    if(!isset($_POST['date_roles']) || array_count_values($_POST['date_roles']) == 0)
    {
        $_SESSION['dates_request']['date_roles'] = '';
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('DAT_VISIBLE_TO')));
        // => EXIT
    }

    // das Land nur zusammen mit dem Ort abspeichern
    if(strlen($_POST['dat_location']) === 0)
    {
        $_POST['dat_country'] = '';
    }

    // ------------------------------------------------
    // Check valid format of date and time input
    // ------------------------------------------------

    $startDateTime = DateTime::createFromFormat($gPreferences['system_date'].' '.$gPreferences['system_time'], $_POST['date_from'].' '.$_POST['date_from_time']);
    if(!$startDateTime)
    {
        // Error: now check if date format or time format was wrong and show message
        $startDateTime = DateTime::createFromFormat($gPreferences['system_date'], $_POST['date_from']);

        if(!$startDateTime)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START'), $gPreferences['system_time']));
            // => EXIT
        }
    }
    else
    {
        // now write date and time with database format to date object
        $date->setValue('dat_begin', $startDateTime->format('Y-m-d H:i:s'));
    }

    // if date-to is not filled then take date-from
    if(strlen($_POST['date_to']) === 0)
    {
        $_POST['date_to'] = $_POST['date_from'];
    }
    if(strlen($_POST['date_to_time']) === 0)
    {
        $_POST['date_to_time'] = $_POST['date_from_time'];
    }

    $endDateTime = DateTime::createFromFormat($gPreferences['system_date'].' '.$gPreferences['system_time'], $_POST['date_to'].' '.$_POST['date_to_time']);

    if(!$endDateTime)
    {
        // Error: now check if date format or time format was wrong and show message
        $endDateTime = DateTime::createFromFormat($gPreferences['system_date'], $_POST['date_to']);

        if(!$endDateTime)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END'), $gPreferences['system_time']));
            // => EXIT
        }
    }
    else
    {
        // now write date and time with database format to date object
        $date->setValue('dat_end', $endDateTime->format('Y-m-d H:i:s'));
    }

    // DateTo should be greater than DateFrom (Timestamp must be less)
    if($startDateTime > $endDateTime)
    {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    if(!isset($_POST['dat_highlight']))
    {
        $_POST['dat_highlight'] = 0;
    }
    if(!isset($_POST['dat_global']))
    {
        $_POST['dat_global'] = 0;
    }
    if(!isset($_POST['dat_all_day']))
    {
        $_POST['dat_all_day'] = 0;
    }
    if(!isset($_POST['date_registration_possible']))
    {
        $_POST['date_registration_possible'] = 0;
    }
    if(!isset($_POST['dat_room_id']))
    {
        $_POST['dat_room_id'] = 0;
    }

    if(!is_numeric($_POST['dat_max_members']))
    {
        $_POST['dat_max_members'] = 0;
    }

    // make html in description secure
    $_POST['dat_description'] = admFuncVariableIsValid($_POST, 'dat_description', 'html');

    // ------------------------------------------------
    // Prüfen ob gewaehlter Raum bereits zu dem Termin reserviert ist
    // ------------------------------------------------

    if($gPreferences['dates_show_rooms'] == 1)
    {
        if($_POST['dat_room_id'] > 0)
        {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_DATES.'
                     WHERE dat_begin  <= \''.$endDateTime->format('Y-m-d H:i:s').'\'
                       AND dat_end    >= \''.$startDateTime->format('Y-m-d H:i:s').'\'
                       AND dat_room_id = '.(int) $_POST['dat_room_id'].'
                       AND dat_id     <> '.$getDateId;
            $datesStatement = $gDb->query($sql);

            if($datesStatement->fetchColumn())
            {
                $gMessage->show($gL10n->get('DAT_ROOM_RESERVED'));
                // => EXIT
            }

            $date->setValue('dat_room_id', $_POST['dat_room_id']);
            $room = new TableRooms($gDb);
            $room->readDataById($_POST['dat_room_id']);
            $number = (int) $room->getValue('room_capacity') + (int) $room->getValue('room_overhang');
            $date->setValue('dat_max_members', $number);
            if($_POST['dat_max_members']<$number && $_POST['dat_max_members']>0)
            {
                $date->setValue('dat_max_members', $_POST['dat_max_members']);
            }
            // Raumname für Benachrichtigung
            $raum = $room->getValue('room_name');
        }
    }

    // write all POST parameters into the date object
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'dat_') === 0)
        {
            $date->setValue($key, $value);
        }
    }

    // now save array with all roles that should see this event to date object
    $date->setVisibleRoles(array_map('intval', $_POST['date_roles']));

    // save event in database
    $return_code = $date->save();

    if($return_code === true && $gPreferences['enable_email_notification'] == 1)
    {
        // Benachrichtigungs-Email für neue Einträge

        // Daten für Benachrichtigung zusammenstellen
        if($_POST['date_from'] === $_POST['date_to'])
        {
            $datum = $_POST['date_from'];
        }
        else
        {
            $datum = $_POST['date_from'] . ' - ' . $_POST['date_to'];
        }

        if($_POST['dat_all_day'] != 0)
        {
            $zeit = $gL10n->get('DAT_ALL_DAY');
        }
        else
        {
            $zeit = $_POST['date_from_time']. ' - '. $_POST['date_to_time'];
        }

        $sql_cal = 'SELECT cat_name
                      FROM '.TBL_CATEGORIES.'
                     WHERE cat_id = '.(int) $_POST['dat_cat_id'];
        $pdoStatement = $gDb->query($sql_cal);
        $calendar = $pdoStatement->fetchColumn();

        if(strlen($_POST['dat_location']) > 0)
        {
            $ort = $_POST['dat_location'];
        }
        else
        {
            $ort = 'n/a';
        }

        if($_POST['dat_room_id'] == 0)
        {
            $raum = 'n/a';
        }

        if(strlen($_POST['dat_max_members']) > 0)
        {
            $teilnehmer = $_POST['dat_max_members'];
        }
        else
        {
            $teilnehmer = 'n/a';
        }

        try
        {
            $notification = new Email();

            if($getMode === 1)
            {
                $message = $gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART1', $gCurrentOrganization->getValue('org_longname'), $_POST['dat_headline'], $datum.' ('.$zeit.')', $calendar)
                          .$gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART2', $ort, $raum, $teilnehmer, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'))
                          .$gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART3', date($gPreferences['system_date'], time()));
                $notification->adminNotfication($gL10n->get('DAT_EMAIL_NOTIFICATION_TITLE'), $message,
                    $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
            else
            {
                $message = $gL10n->get('DAT_EMAIL_NOTIFICATION_CHANGE_MESSAGE_PART1', $gCurrentOrganization->getValue('org_longname'), $_POST['dat_headline'], $datum.' ('.$zeit.')', $calendar)
                          .$gL10n->get('DAT_EMAIL_NOTIFICATION_CHANGE_MESSAGE_PART2', $ort, $raum, $teilnehmer, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'))
                          .$gL10n->get('DAT_EMAIL_NOTIFICATION_CHANGE_MESSAGE_PART3', date($gPreferences['system_date'], time()));
                $notification->adminNotfication($gL10n->get('DAT_EMAIL_NOTIFICATION_CHANGE_TITLE'), $message,
                    $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
            }
        }
        catch(AdmException $e)
        {
            $e->showHtml();
        }
    }

    // ----------------------------------------
    // ggf. Rolle fuer Anmeldungen wegschreiben
    // ----------------------------------------

    if($_POST['date_registration_possible'] == 1 && strlen($date->getValue('dat_rol_id')) === 0)
    {
        // create role for participations
        if($getCopy)
        {
            // copy original role with their settings
            $sql = 'SELECT dat_rol_id
                      FROM '.TBL_DATES.'
                     WHERE dat_id = '.$originalDateId;
            $pdoStatement = $gDb->query($sql);

            $role = new TableRoles($gDb, (int) $pdoStatement->fetchColumn());
            $role->setValue('rol_id', '0');
        }
        else
        {
            // Kategorie fuer Terminbestaetigungen einlesen
            $sql = 'SELECT cat_id
                      FROM '.TBL_CATEGORIES.'
                     WHERE cat_name_intern LIKE \'CONFIRMATION_OF_PARTICIPATION\'';
            $pdoStatement = $gDb->query($sql);
            $role = new TableRoles($gDb);

            // these are the default settings for a date role
            $role->setValue('rol_cat_id', $pdoStatement->fetchColumn());
            // role members are allowed to view lists
            $role->setValue('rol_this_list_view', isset($_POST['date_right_list_view']) ? '1' : '0');
            // role members are allowed to send mail to this role
            $role->setValue('rol_mail_this_role', isset($_POST['date_right_send_mail']) ? '1' : '0');
            $role->setValue('rol_visible', '0');
            $role->setValue('rol_leader_rights', ROLE_LEADER_MEMBERS_ASSIGN);    // leaders are allowed to add or remove participations
            $role->setValue('rol_max_members', $_POST['dat_max_members']);
        }

        $role->setValue('rol_name', $gL10n->get('DAT_DATE').' '. $date->getValue('dat_begin', 'Y-m-d H:i').' - '.$date->getValue('dat_id'));
        $role->setValue('rol_description', $date->getValue('dat_headline'));

        // save role in database
        $return_code2 = $role->save();
        if($return_code < 0 || $return_code2 < 0)
        {
            $date->delete();
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }

        // dat_rol_id anpassen (Referenz zwischen date und role)
        $date->setValue('dat_rol_id', $role->getValue('rol_id'));
        $return_code = $date->save();
        if($return_code < 0)
        {
            $role->delete();
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
    elseif($_POST['date_registration_possible'] == 0 && $date->getValue('dat_rol_id') > 0)
    {
        // date participation was deselected -> delete flag in event and than delete role
        $role = new TableRoles($gDb, $date->getValue('dat_rol_id'));
        $date->setValue('dat_rol_id', '');
        $date->save();
        $role->delete();
    }
    elseif($_POST['date_registration_possible'] == 1 && $date->getValue('dat_rol_id') > 0)
    {
        // if event exists and you could register to this event then we must check
        // if the data of the role must be changed
        $role = new TableRoles($gDb, $date->getValue('dat_rol_id'));

        // only change name of role if no custom name was set
        if(strpos($role->getValue('rol_name'), $gL10n->get('DAT_DATE')) !== false)
        {
            $roleName = $gL10n->get('DAT_DATE').' '. $date->getValue('dat_begin', 'Y-m-d H:i').' - '.$date->getValue('dat_id');
        }
        else
        {
            $roleName = $role->getValue('rol_name');
        }

        $role->setValue('rol_name', $roleName);
        // role members are allowed to view lists
        $role->setValue('rol_this_list_view', isset($_POST['date_right_list_view']) ? '1' : '0');
        // role members are allowed to send mail to this role
        $role->setValue('rol_mail_this_role', isset($_POST['date_right_send_mail']) ? '1' : '0');
        $role->setValue('rol_max_members', $date->getValue('dat_max_members'));

        $role->save();
    }

    // check if flag is set that current user wants to participate as leader to the date
    if(isset($_POST['date_current_user_assigned']) && $_POST['date_current_user_assigned'] == 1
    && !$gCurrentUser->isLeaderOfRole($date->getValue('dat_rol_id')))
    {
        // user wants to participate -> add him to date
        $member = new TableMembers($gDb);
        $member->startMembership((int) $role->getValue('rol_id'), (int) $gCurrentUser->getValue('usr_id'), true);
    }
    elseif(!isset($_POST['date_current_user_assigned'])
    && $gCurrentUser->isMemberOfRole($date->getValue('dat_rol_id')))
    {
        // user does't want to participate as leader -> remove his participation as leader from the event,
        // dont remove the participation itself!
        $member = new TableMembers($gDb);
        $member->readDataByColumns(array('mem_rol_id' => $role->getValue('rol_id'), 'mem_usr_id' => $gCurrentUser->getValue('usr_id')));
        $member->setValue('mem_leader', 0);
        $member->save();
    }

    unset($_SESSION['dates_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif($getMode === 2)  // Termin loeschen
{
    // Termin loeschen, wenn dieser zur aktuellen Orga gehoert
    if((int) $date->getValue('cat_org_id') === (int) $gCurrentOrganization->getValue('org_id'))
    {
        // member bzw. Teilnahme/Rolle löschen
        $date->delete();

        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($getMode === 3)  // Benutzer zum Termin anmelden
{
    $member = new TableMembers($gDb);
    $member->startMembership((int) $date->getValue('dat_rol_id'), (int) $gCurrentUser->getValue('usr_id'));

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('DAT_ATTEND_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $gL10n->get('DAT_ATTEND'));
    // => EXIT
}
elseif($getMode === 4)  // Benutzer vom Termin abmelden
{
    $member = new TableMembers($gDb);
    $member->deleteMembership((int) $date->getValue('dat_rol_id'), (int) $gCurrentUser->getValue('usr_id'));

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('DAT_CANCEL_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $gL10n->get('DAT_ATTEND'));
    // => EXIT
}
elseif($getMode === 6)  // Termin im iCal-Format exportieren
{
    $filename = $date->getValue('dat_headline');

    // for IE the filename must have special chars in hexadecimal
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
    {
        $filename = urlencode($filename);
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="'. $filename. '.ics"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}
