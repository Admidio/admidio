<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der angezeigt werden soll
 * mode:   1 - Neuen Termin anlegen/aendern
 *         2 - Termin loeschen
 *         4 - Termin im iCal-Format exportieren
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/table_roles.php');
require_once('../../system/classes/table_rooms.php');


// Config-Area fuer Roles, mit denen eine neue Rolle angelegt wird wenn eine Anmeldung moeglich ist
$role_attributes = array(
    'rol_cat_id'            => null,
    'rol_name'               => null,
    'rol_description'        => null,
    'rol_assign_roles'       => 0,
    'rol_approve_users'      => 0,
    'rol_announcements'      => 0,
    'rol_dates'              => 0,
    'rol_download'           => 0,
    'rol_edit_user'          => 0,
    'rol_guestbook'          => 0,
    'rol_guestbook_comments' => 0,
    'rol_inventory'          => 0,
    'rol_mail_to_all'        => 0,
    'rol_mail_this_role'     => 0,
    'rol_photo'              => 0,
    'rol_profile'            => 0,
    'rol_weblinks'           => 0,
    'rol_this_list_view'     => 0,
    'rol_all_lists_view'     => 0,
    'rol_start_date'         => null,
    'rol_start_time'         => null,
    'rol_end_date'           => null,
    'rol_end_time'           => null,
    'rol_weekday'            => null,
    'rol_location'           => null,
    'rol_max_members'        => null,
    'rol_cost'               => null,
    'rol_cost_period'        => null,
    'rol_usr_id_create'      => null,
    'rol_timestamp_create'   => null,
    'rol_usr_id_change'      => null,
    'rol_timestamp_change'   => null,
    'rol_valid'              => 1,
    'rol_visible'            => '0',
    'rol_system'             => 0
    
);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

if($_GET['mode'] != 4 || $g_preferences['enable_dates_module'] == 2)
{
    // Alle Funktionen, ausser Exportieren, duerfen nur eingeloggte User
    require('../../system/login_valid.php');
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDates() && $_GET['mode'] != 4)
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_dat_id = $_GET['dat_id'];
}

if(is_numeric($_GET['mode']) == false
    || $_GET['mode'] < 1 || $_GET['mode'] > 5)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Terminobjekt anlegen
$date = new TableDate($g_db);

if($req_dat_id > 0)
{
    $date->readData($req_dat_id);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false )
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

if($_GET['mode'] == 1)  // Neuen Termin anlegen/aendern
{
    $_SESSION['dates_request'] = $_REQUEST;
    
    if(isset($_POST['dat_rol_id']))
    {
        $_SESSION['dates_request']['keep_rol_id'] = 1;
    }
    else
    {
        $_SESSION['dates_request']['keep_rol_id'] = 0;
    }
    
    // ------------------------------------------------
    // pruefen ob alle notwendigen Felder gefuellt sind
    // ------------------------------------------------
    
    if(strlen($_POST['dat_headline']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Überschrift'));
    }
    if(strlen($_POST['dat_description']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Beschreibung'));
    }
    if(strlen($_POST['date_from']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Datum Beginn'));
    }
    if(strlen($_POST['date_to']) == 0 && $_POST['dat_repeat_type'] == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Datum Ende'));
    }
    if(strlen($_POST['time_from']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Uhrzeit Beginn'));
    }
    if(strlen($_POST['time_to']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Uhrzeit Ende'));
    }
    if(strlen($_POST['dat_cat_id']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Kalender'));
    }

    if(isset($_POST['dat_all_day']))
    {
        $_POST['time_from'] = '00:00';
        $_POST['time_to']   = '00:00'; // Ganztägig ist nur logisch bei 23:59 Uhr (rn)
        $date->setValue('dat_all_day', 1);
    }
    
    if(!is_array($_POST['dat_visible_for']))
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY','Sichtbarkeit'));
    }
    
    // das Land nur zusammen mit dem Ort abspeichern
    if(strlen($_POST['dat_location']) == 0)
    {
        $_POST['dat_country'] = '';
    }

    // ------------------------------------------------
    // Datum und Uhrzeit auf Gueltigkeit pruefen
    // ------------------------------------------------

    $startDateTime = new DateTimeExtended($_POST['date_from'].' '.$_POST['time_from'], $g_preferences['system_date'].' '.$g_preferences['system_time']);

    if($startDateTime->valid())
    {
        // Datum & Uhrzeit formatiert zurueckschreiben
        $date->setValue('dat_begin', $startDateTime->getDateTimeEnglish());
    }
    else
    {
        // Fehler: pruefen, ob Datum oder Uhrzeit falsches Format hat
        $startDateTime->setDateTime($_POST['date_from'], $g_preferences['system_date']);
        if($startDateTime->valid())
        {
            $g_message->show($g_l10n->get('SYS_PHR_DATE_INVALID', 'Datum Beginn', $g_preferences['system_date']));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_TIME_INVALID', 'Uhrzeit Beginn', $g_preferences['system_time']));
        }
    }

    // wenn Datum-bis nicht gefüllt ist, dann mit Datum-von nehmen
    if(strlen($_POST['date_to'])   == 0)
    {
        $_POST['date_to'] = $_POST['date_from'];
    }
    if(strlen($_POST['time_to']) == 0)
    {
        $_POST['time_to'] = $_POST['time_from'];
    }
    
    $endDateTime = new DateTimeExtended($_POST['date_to'].' '.$_POST['time_to'], $g_preferences['system_date'].' '.$g_preferences['system_time']);

    if($endDateTime->valid())
    {
        // Datum & Uhrzeit formatiert zurueckschreiben
        $date->setValue('dat_end', $endDateTime->getDateTimeEnglish());
    }
    else
    {
        // Fehler: pruefen, ob Datum oder Uhrzeit falsches Format hat
        $endDateTime->setDateTime($_POST['date_to'], $g_preferences['system_date']);
        if($endDateTime->valid())
        {
            $g_message->show($g_l10n->get('SYS_PHR_DATE_INVALID', 'Datum Ende', $g_preferences['system_date']));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_TIME_INVALID', 'Uhrzeit Ende', $g_preferences['system_time']));
        }
    }   
    
    // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
    if($startDateTime->getTimestamp() > $endDateTime->getTimestamp())
    {
        $g_message->show($g_l10n->get('SYS_PHR_DATE_END_BEFORE_BEGIN'));
    }

    if(isset($_POST['dat_global']) == false)
    {
        $_POST['dat_global'] = 0;
    }
    if(isset($_POST['dat_all_day']) == false)
    {
        $_POST['dat_all_day'] = 0;
    }

    // ------------------------------------------------
    // Prüfen ob gewaehlter Raum bereits zu dem Termin reserviert ist
    // ------------------------------------------------
    
    if($g_preferences['dates_show_rooms'] == 1)
    {
        if($_POST['dat_room_id'] > 0)
        {
            $sql = 'SELECT COUNT(dat_id) AS is_reserved FROM '.TBL_DATES.' WHERE ('.
                    '(dat_begin <= "'.$date_begin.'" AND dat_end >= "'.$date_begin.'") OR '.
                    '(dat_begin <= "'.$date_end.'" AND dat_end >= "'.$date_end.'") OR '.
                    '(dat_begin >= "'.$date_begin.'" AND dat_end <= "'.$date_end.'") '.
                    ') AND dat_room_id = "'.(int)$_POST['dat_room_id'].'" AND dat_id != "'.(int)$req_dat_id.'"';
            $result = $g_db->query($sql);
            $row = $g_db->fetch_object($result);
            if($row->is_reserved) 
            {
                $g_message->show($g_l10n->get('DAT_PHR_ROOM_RESERVED'));
            }
            
            $date->setValue('dat_room_id',$_POST['dat_room_id']);
            $room = new TableRooms($g_db);
            $room->readData($_POST['dat_room_id']);
            $number = intval($room->getValue('room_capacity')) + intval($room->getValue('room_overhang'));
            $date->setValue('dat_max_members', $number);
            if($_POST['dat_max_members']<$number && $_POST['dat_max_members']>0)
            {
                $date->setValue('dat_max_members', $_POST['dat_max_members']);
            }
        }
    }
    else
    {
        $date->setValue('dat_max_members', $_POST['dat_max_members']);
    }

    // -----------------------------------
    // Termin in Datenbank schreiben
    // -----------------------------------

    // POST Variablen in das Termin-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'dat_') === 0)
        {
            $date->setValue($key, $value);
        }
    }

    $return_code = $date->save();

    // -----------------------------------
    // Sichbarkeit der Rollen wegschreiben
    // -----------------------------------

    if($req_dat_id > 0)
    {
        // erst einmal alle bisherigen Rollenzuordnungen loeschen, damit alles neu aufgebaut werden kann
        $sql='DELETE FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id = '.$date->getValue('dat_id');
        $g_db->query($sql);
    }

    if(is_array($_POST['dat_visible_for']))
    {
        // nun alle Rollenzuordnungen wegschreiben
        $modes = $_POST['dat_visible_for'];
        $date_role = new TableAccess($g_db, TBL_DATE_ROLE, 'dtr');

        foreach($modes as $value)
        {
            $date_role->setValue('dtr_dat_id', $date->getValue('dat_id'));
            $date_role->setValue('dtr_rol_id', $value);
            $date_role->save();
            $date_role->clear();
        }
        $date->visible_for = $modes;
    }
    else    //nichts ausgewählt
    {
        $date->delete();
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY'));
    }

    
    // ----------------------------------------
    // ggf. Rolle fuer Anmeldungen wegschreiben
    // ----------------------------------------
    
    if($req_dat_id == 0)
    {           
        // Rollenobjekt anlegen, wenn Anmeldung möglich und kein Bearbeiten-Modus
        if(isset($_POST['dat_rol_id']))
        {
            $role = new TableRoles($g_db);  
            
            $rol_cat_id             = $date->getValue('dat_cat_id');
            $rol_name               = 'Terminzusage_'. $date->getValue('dat_id');
            $rol_timestamp_create   = $date->getValue('dat_timestamp_create');
            $role_attributes['rol_cat_id'] = $rol_cat_id;
            $role_attributes['rol_name'] = $rol_name;
            $role_attributes['rol_timestamp_create'] = $rol_timestamp_create;
            
            // Rolle mit Daten aus Termin befüllen
            foreach($role_attributes as $key => $value)
            {
                $role->setValue($key, $value);
            }
            
            // Rolle in Datenbank speichern
            $return_code2 = $role->save();
            if($return_code < 0 || $return_code2 < 0)
            {
                $date->delete();
                $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
            }
            
            // dat_rol_id anpassen (Referenz zwischen date und role)
            $date->setValue('dat_rol_id', $role->getValue('rol_id'));
            $return_code = $date->save();
            if($return_code < 0)
            {
                $role->delete();
                $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
            }
            
            //Termin-Ersteller als Member und Leader eintragen
            $member = new TableMembers($g_db);
            $member->startMembership($role->getValue('rol_id'), $_SESSION['g_current_user']->getValue('usr_id'), 1);
        }
        
        //Kontingentierung anlegen
        $max_members_role = $_POST['dat_max_members_role'];
        
        // Angegebene Kontingente aufsummieren
        $sum = 0;
        $count_max_members_role = 0;
        foreach($max_members_role as $rol_id => $max_members)
        {
            if($max_members != '' && !$date->isVisibleFor($rol_id))
            {
                $date->delete();
                $g_message->show($g_l10n->get('DAT_PHR_QUOTA_FOR_ROLE'));
            }
            else
            {
                $sum += $max_members;
                if($max_members != '')
                {
                    $count_max_members_role++;
                }
            }
        }
        
        if($count_max_members_role == count($date->visible_for) && $sum != $date->getValue('dat_max_members'))
        {
            $date->delete();
            $g_message->show($g_l10n->get('DAT_PHR_QUOTA_AND_MAX_MEMBERS_MUST_MATCH'));
        }
        
        if($sum > 0 && $date->getValue('dat_max_members') != '' && $date->getValue('dat_max_members') > 0)
        {
            // Nur eintragen wenn die Teilnehmerbegrenzung größer oder gleich der insgesamt
            // Teilnehmerbegrenzung ist
            if($sum <= $date->getValue('dat_max_members'))
            {
                foreach($max_members_role as $rol_id => $max_members)
                {
                    if(is_numeric($rol_id) && is_numeric($max_members))
                    {
                        $sql = 'INSERT INTO '.TBL_DATE_MAX_MEMBERS.' (`dmm_dat_id` , `dmm_rol_id` , `dmm_max_members`) VALUES ('.$date->getValue('dat_id').', '.$rol_id.', '.$max_members.'); ';
                        $g_db->query($sql);
                    }
                }
            }
            else
            {
                $date->delete();
                $g_message->show($g_l10n->get('DAT_PHR_QUOTA_EXCEEDED'));
            }
        }
        elseif($sum > 0 && ($date->getValue('dat_max_members') == '' || $date->getValue('dat_max_members') == 0))
        {
            $date->delete();
            $g_message->show($g_l10n->get('DAT_PHR_QUOTA_WITH_MAXIMUM'));
        }
    }
    else    // im Bearbeiten-Modus
    {
        if(isset($_POST['dat_rol_id']))
        {
            $role = new TableRoles($g_db);
            $role->readData($date->getValue('dat_rol_id'));
            $rol_cat_id             = $date->getValue('dat_cat_id');
            $rol_name               = 'Terminzusage_'. $date->getValue('dat_id');
            $rol_timestamp_create   = $date->getValue('dat_timestamp_create');
            $role_attributes['rol_cat_id'] = $rol_cat_id;
            $role_attributes['rol_name'] = $rol_name;
            $role_attributes['rol_timestamp_create'] = $rol_timestamp_create;
            // Rolle mit Daten aus Termin befüllen
            foreach($role_attributes as $key => $value)
            {
                $role->setValue($key, $value);
            }
            $role->save();
            $date->setValue('dat_rol_id', $role->getValue('rol_id'));
            $date->save();
            $member = new TableMembers($g_db);
            $member->startMembership($role->getValue('rol_id'), $_SESSION['g_current_user']->getValue('usr_id'), 1);
        }
        else
        {
            
            $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_rol_id = "'.$date->getValue('dat_rol_id').'"';
            $g_db->query($sql);
            $sql = 'DELETE FROM '.TBL_ROLES.' WHERE rol_id = "'.$date->getValue('dat_rol_id').'"';
            $g_db->query($sql);
            $sql = 'DELETE FROM '.TBL_DATE_MAX_MEMBERS.' WHERE dmm_dat_id = "'.$date->getValue('dat_id').'"';
            $g_db->query($sql);
            $date->setValue('dat_rol_id', null);
            $date->setValue('dat_max_members', null);
            $date->save();
        }
        
        if($date->getValue('dat_max_members') != '')
        {
            // Kontingentierung ändern
            $max_members_role = $_POST['dat_max_members_role'];
            
            // Angegebene Kontingente aufsummieren
            $sum = 0;
            foreach($max_members_role as $rol_id => $max_members)
            {
                if($max_members != '' && !$date->isVisibleFor($rol_id))
                {
                    $g_message->show($g_l10n->get('DAT_PHR_QUOTA_FOR_ROLE'));
                }
                else
                {
                    $sum += $max_members;
                    if($max_members != '')
                    {
                        $count_max_members_role++;
                    }
                }
            }
            
            if($count_max_members_role == count($date->visible_for) && $sum != $date->getValue('dat_max_members'))
            {
                $g_message->show($g_l10n->get('DAT_PHR_QUOTA_AND_MAX_MEMBERS_MUST_MATCH'));
            }
            
            if($sum > 0 && ($date->getValue('dat_max_members') == '' || $date->getValue('dat_max_members') == 0))
            {
                $g_message->show($g_l10n->get('DAT_PHR_QUOTA_WITH_MAXIMUM'));
            }
            
            if(($sum > 0 && $date->getValue('dat_max_members') != '' && $date->getValue('dat_max_members') > 0) || $sum == 0)
            {
                $sql = 'DELETE FROM '.TBL_DATE_MAX_MEMBERS.' WHERE `dmm_dat_id` = '.$date->getValue('dat_id');
                $g_db->query($sql);
                
                // Nur eintragen wenn die Teilnehmerbegrenzung größer oder gleich der insgesamt Teilnehmerbegrenzung ist
                if($sum <= $date->getValue('dat_max_members'))
                {
                    foreach($max_members_role as $rol_id => $max_members)
                    {
                        if(is_numeric($rol_id) && is_numeric($max_members))
                        {
                            $sql = 'INSERT INTO '.TBL_DATE_MAX_MEMBERS.' (`dmm_dat_id` , `dmm_rol_id` , `dmm_max_members`) VALUES ('.$date->getValue('dat_id').', '.$rol_id.', '.$max_members.'); ';
                            $g_db->query($sql);
                        }
                    }
                }
                else
                {
                    $g_message->show($g_l10n->get('DAT_PHR_QUOTA_EXCEEDED'));
                }
            }
        }
        else
        {
            $sql = 'DELETE FROM '.TBL_DATE_MAX_MEMBERS.' WHERE dmm_dat_id = "'.$date->getValue('dat_id').'"';
            $g_db->query($sql);
        }
    }
    unset($_SESSION['dates_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}
elseif($_GET['mode'] == 2)  // Termin loeschen
{
    // Termin loeschen, wenn dieser zur aktuellen Orga gehoert
    if($date->getValue('cat_org_id') == $g_current_organization->getValue('org_id'))
    {
         //member bzw. Teilnahme/Rolle löschen
        $date->delete();
        
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($_GET['mode'] == 4)  // Termin im iCal-Format exportieren
{
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename='. $date->getValue('dat_headline'). '.ics');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}

?>