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
 *         3 - zum Termin anmelden
 *         4 - vom Termin abmelden
 *         5 - Eintrag fuer Sichtbarkeit erzeugen
 *         6 - Termin im iCal-Format exportieren
 * count:  Nummer dere Rollenauswahlbox, die angezeigt werden soll
 * rol_id: vorselektierte Rolle der Rollenauswahlbox
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/table_roles.php');
require_once('../../system/classes/table_rooms.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

if(($_GET['mode'] != 3 && $_GET['mode'] != 6) || $g_preferences['enable_dates_module'] == 2)
{
    // Alle Funktionen, ausser Exportieren und anmelden, duerfen nur eingeloggte User
    require_once('../../system/login_valid.php');
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDates() && $_GET['mode'] != 3 && $_GET['mode'] != 4 && $_GET['mode'] != 6)
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id = 0;
$req_rol_id = 0;

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

if(isset($_GET['count']) && is_numeric($_GET['count']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_rol_id = $_GET['rol_id'];
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
    
    // ------------------------------------------------
    // pruefen ob alle notwendigen Felder gefuellt sind
    // ------------------------------------------------
    
    if(strlen($_POST['dat_headline']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_TITLE')));
    }
    if(strlen($_POST['dat_description']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_DESCRIPTION')));
    }
    if(strlen($_POST['date_from']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_START')));
    }
    if(strlen($_POST['date_to']) == 0 && $_POST['dat_repeat_type'] == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_END')));
    }
    if(strlen($_POST['time_from']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_TIME').' '.$g_l10n->get('SYS_START')));
    }
    if(strlen($_POST['time_to']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('SYS_TIME').' '.$g_l10n->get('SYS_END')));
    }
    if(strlen($_POST['dat_cat_id']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DAT_CALENDAR')));
    }

    if(isset($_POST['dat_all_day']))
    {
        $_POST['time_from'] = '00:00';
        $_POST['time_to']   = '00:00'; // Ganztägig ist nur logisch bei 23:59 Uhr (rn)
        $date->setValue('dat_all_day', 1);
    }
    if(isset($_POST['role_1']) == false || $_POST['role_1'] == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $g_l10n->get('DAT_VISIBLE_TO')));
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
            $g_message->show($g_l10n->get('SYS_PHR_DATE_INVALID', $g_l10n->get('SYS_START'), $g_preferences['system_date']));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_TIME_INVALID', $g_l10n->get('SYS_TIME').' '.$g_l10n->get('SYS_START'), $g_preferences['system_time']));
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
            $g_message->show($g_l10n->get('SYS_PHR_DATE_INVALID', $g_l10n->get('SYS_END'), $g_preferences['system_date']));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_TIME_INVALID', $g_l10n->get('SYS_TIME').' '.$g_l10n->get('SYS_END'), $g_preferences['system_time']));
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
    if(isset($_POST['date_login']) == false)
    {
        $_POST['date_login'] = 0;
    }
	
    // ------------------------------------------------
    // Prüfen ob gewaehlter Raum bereits zu dem Termin reserviert ist
    // ------------------------------------------------
    
    if($g_preferences['dates_show_rooms'] == 1)
    {
        if($_POST['dat_room_id'] > 0)
        {
            $sql = 'SELECT COUNT(dat_id) AS is_reserved 
                      FROM '.TBL_DATES.' 
                     WHERE dat_begin <= "'.$endDateTime->getDateTimeEnglish().'"
                       AND dat_end   >= "'.$startDateTime->getDateTimeEnglish().'"
                       AND dat_room_id = '.$_POST['dat_room_id'].' 
                       AND dat_id     <> '.$req_dat_id;
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
			// Raumname für Benachrichtigung
			$raum = $room->getValue('room_name');
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

    // nun alle Rollenzuordnungen wegschreiben
    $roleCount = 1;
    $arrRoles  = array();
    while(isset($_POST['role_'.$roleCount]))
    {
        if($_POST['role_'.$roleCount] != 0)
        {
            $arrRoles[] = $_POST['role_'.$roleCount];
        }
        $roleCount++;
    }

    // Termin in Datenbank speichern
    $date->setVisibleRoles($arrRoles);
    $return_code = $date->save();
	
	if($return_code == 0)
	{	
		// Benachrichtigungs-Email für neue Einträge
		if($g_preferences['enable_email_notification'] == 1)
		{
			// Daten für Benachrichtigung zusammenstellen
			if($_POST['date_from'] == $_POST['date_to'])
			{$datum = $_POST['date_from'];}
			else
			{$datum = $_POST['date_from']. ' - '.$_POST['date_to'];}
			
			if(isset($_POST['dat_all_day']))
			{$zeit = $g_l10n->get('DAT_ALL_DAY');}
			else
			{$zeit = $_POST['time_from']. ' - '. $_POST['time_to'];}
			
			$sql_cal = 'SELECT cat_name FROM '.TBL_CATEGORIES.' 
                 WHERE cat_id = '.$_POST['dat_cat_id'];
			$g_db->query($sql_cal);
			$row_cal = $g_db->fetch_array();
			$calendar = $row_cal['cat_name'];
			
			if(strlen($_POST['dat_location']) > 0)
			{$ort = $_POST['dat_location'];}
			else
			{$ort = 'n/a';}	
			
			if($_POST['dat_room_id'] == 0)
			{$raum = 'n/a';}
			
			if(strlen($_POST['dat_max_members']) > 0)
			{$teilnehmer = $_POST['dat_max_members'];}
			else
			{$teilnehmer = 'n/a';}
			
			$message_part1 = str_replace("<br />","\n", $g_l10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART1', $g_current_organization->getValue('org_longname'), $_POST['dat_headline'], $datum. ' ('. $zeit. ')', $calendar));
			$message_part2 = str_replace("<br />","\n", $g_l10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART2', $ort, $raum, $teilnehmer, $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME')));
			$message_part3 = str_replace("<br />","\n", $g_l10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART3', date("d.m.Y H:m", time())));
			EmailNotification($g_preferences['email_administrator'], $g_current_organization->getValue('org_shortname'). ": ".$g_l10n->get('DAT_EMAIL_NOTIFICATION_TITLE'), $message_part1.$message_part2.$message_part3, $g_current_user->getValue('FIRST_NAME').' '.$g_current_user->getValue('LAST_NAME'), $g_current_user->getValue('EMAIL'));		
		}
	}
    
    // ----------------------------------------
    // ggf. Rolle fuer Anmeldungen wegschreiben
    // ----------------------------------------         

    if($_POST['date_login'] == 1 && strlen($date->getValue('dat_rol_id')) == 0)
    {
        // Kategorie fuer Terminbestaetigungen einlesen
        $sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' 
                 WHERE cat_name LIKE "'.$g_l10n->get('SYS_CONFIRMATION_OF_PARTICIPATION').'"';
        $g_db->query($sql);
        $row = $g_db->fetch_array();

        // Rolle fuer die Anmeldung anlegen
        $role = new TableRoles($g_db);
        $role->setValue('rol_cat_id', $row['cat_id']);
        $role->setValue('rol_name', $g_l10n->get('DAT_DATE').' '. $date->getValue('dat_begin').' - '.$date->getValue('dat_id'));
        $role->setValue('rol_description', $date->getValue('dat_headline'));
        $role->setValue('rol_this_list_view', '1'); // Rollenmitglieder duerfen Liste einsehen
        $role->setValue('rol_visible', '0');
        
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
    elseif($_POST['date_login'] == 0 && $date->getValue('dat_rol_id') > 0)
    {
        // die Anmeldungsmoeglichkeit wurde wieder abgewaehlt -> Rolle loeschen
        $role = new TableRoles($g_db, $date->getValue('dat_rol_id'));
        $role->delete();
        
        $date->setValue('dat_rol_id', '');
        $date->save();
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
elseif($_GET['mode'] == 3)  // Benutzer zum Termin anmelden
{
    $member = new TableMembers($g_db);
    $member->startMembership($date->getValue('dat_rol_id'),$g_current_user->getValue('usr_id'));

    $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
    $g_message->show($g_l10n->get('DAT_PHR_ATTEND_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $g_l10n->get('DAT_ATTEND'));
}
elseif($_GET['mode'] == 4)  // Benutzer vom Termin abmelden
{
    $sql = 'DELETE FROM '.TBL_MEMBERS.' 
             WHERE mem_rol_id = "'.$date->getValue('dat_rol_id').'" 
               AND mem_usr_id = "'.$g_current_user->getValue('usr_id').'"';
    $g_db->query($sql);

    $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
    $g_message->show($g_l10n->get('DAT_PHR_CANCEL_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $g_l10n->get('DAT_ATTEND'));
}
elseif($_GET['mode'] == 5)  // Eintrag fuer Sichtbarkeit erzeugen
{
    $label = '';
    error_log('count'.$_GET['count']);
    if($_GET['count'] == 1)
    {
        $label = $g_l10n->get('DAT_VISIBLE_TO').':';
    }
    echo '<dl id="roleID_'.$_GET['count'].'">
        <dt>'.$label.'</dt>
        <dd>'.generateRoleSelectBox($req_rol_id, 'role_'.$_GET['count'], 0, 1);
            if($_GET['count'] > 1)
            {
                echo '<a href="javascript:removeRoleSelection(\'roleID_'.$_GET['count'].'\')"><img 
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('DAT_ADD_ROLE').'" /></a>';
            }
        echo '</dd>
    </dl>';
    exit();
}
elseif($_GET['mode'] == 6)  // Termin im iCal-Format exportieren
{
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename='. $date->getValue('dat_headline'). '.ics');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}

?>