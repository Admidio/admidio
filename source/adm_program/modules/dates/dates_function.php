<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * dat_id : ID des Termins, der angezeigt werden soll
 * mode   : 1 - Neuen Termin anlegen/aendern
 *          2 - Termin loeschen
 *          3 - zum Termin anmelden
 *          4 - vom Termin abmelden
 *          5 - Eintrag fuer Sichtbarkeit erzeugen
 *          6 - Termin im iCal-Format exportieren
 * rol_id : vorselektierte Rolle der Rollenauswahlbox
 * number_role_select : Nummer der Rollenauswahlbox, die angezeigt werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/table_roles.php');
require_once('../../system/classes/table_rooms.php');
require_once('../../libs/htmlawed/htmlawed.php');
require_once(SERVER_PATH. '/adm_program/system/classes/email.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Initialize and check the parameters
$getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', 0);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getNumberRoleSelect = admFuncVariableIsValid($_GET, 'number_role_select', 'numeric');


if($getMode != 6 || $gPreferences['enable_dates_module'] == 2)
{
    // Alle Funktionen, ausser Exportieren und anmelden, duerfen nur eingeloggte User
    require_once('../../system/login_valid.php');
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$gCurrentUser->editDates() && $getMode != 3 && $getMode != 4 && $getMode != 6)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Terminobjekt anlegen
$date = new TableDate($gDb);

if($getDateId > 0)
{
    $date->readDataById($getDateId);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false )
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if($getMode == 1)  // Neuen Termin anlegen/aendern
{
    $_SESSION['dates_request'] = $_REQUEST;
    
    // ------------------------------------------------
    // pruefen ob alle notwendigen Felder gefuellt sind
    // ------------------------------------------------
    
    if(strlen($_POST['dat_headline']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TITLE')));
    }
    if(strlen($_POST['date_from']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_START')));
    }
    if(strlen($_POST['date_to']) == 0 && $_POST['dat_repeat_type'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_END')));
    }
    if(strlen($_POST['time_from']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START')));
    }
    if(strlen($_POST['time_to']) == 0 && isset($_POST['dat_all_day']) == false)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END')));
    }
    if(strlen($_POST['dat_cat_id']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('DAT_CALENDAR')));
    }

    if(isset($_POST['dat_all_day']))
    {
        $_POST['time_from'] = '00:00';
        $_POST['time_to']   = '00:00'; // Ganztägig ist nur logisch bei 23:59 Uhr (rn)
        $date->setValue('dat_all_day', 1);
    }
    else
    {
        $date->setValue('dat_all_day', 0);
    }
    
    if(isset($_POST['role_1']) == false || $_POST['role_1'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('DAT_VISIBLE_TO')));
    }

    // das Land nur zusammen mit dem Ort abspeichern
    if(strlen($_POST['dat_location']) == 0)
    {
        $_POST['dat_country'] = '';
    }

    // ------------------------------------------------
    // Datum und Uhrzeit auf Gueltigkeit pruefen
    // ------------------------------------------------

    $startDateTime = new DateTimeExtended($_POST['date_from'].' '.$_POST['time_from'], $gPreferences['system_date'].' '.$gPreferences['system_time']);

    if($startDateTime->valid())
    {
        // Datum & Uhrzeit formatiert zurueckschreiben
        $date->setValue('dat_begin', $startDateTime->getDateTimeEnglish());
    }
    else
    {
        // Fehler: pruefen, ob Datum oder Uhrzeit falsches Format hat
        $startDateTime->setDateTime($_POST['date_from'], $gPreferences['system_date']);
        if($startDateTime->valid())
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_START'), $gPreferences['system_date']));
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_START'), $gPreferences['system_time']));
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
    
    $endDateTime = new DateTimeExtended($_POST['date_to'].' '.$_POST['time_to'], $gPreferences['system_date'].' '.$gPreferences['system_time']);

    if($endDateTime->valid())
    {
        // Datum & Uhrzeit formatiert zurueckschreiben
        $date->setValue('dat_end', $endDateTime->getDateTimeEnglish());
    }
    else
    {
        // Fehler: pruefen, ob Datum oder Uhrzeit falsches Format hat
        $endDateTime->setDateTime($_POST['date_to'], $gPreferences['system_date']);
        if($endDateTime->valid())
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('SYS_END'), $gPreferences['system_date']));
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('SYS_TIME').' '.$gL10n->get('SYS_END'), $gPreferences['system_time']));
        }
    }   
    
    // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
    if($startDateTime->getTimestamp() > $endDateTime->getTimestamp())
    {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
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
	
	if(is_numeric($_POST['dat_max_members']) == false)
	{
		$_POST['dat_max_members'] = 0;
	}
    
    // make html in description secure
    $_POST['dat_description'] = htmLawed(stripslashes($_POST['dat_description']), array('safe' => 1));
	
    // ------------------------------------------------
    // Prüfen ob gewaehlter Raum bereits zu dem Termin reserviert ist
    // ------------------------------------------------
    
    if($gPreferences['dates_show_rooms'] == 1)
    {
        if($_POST['dat_room_id'] > 0)
        {
            $sql = 'SELECT COUNT(dat_id) AS is_reserved 
                      FROM '.TBL_DATES.' 
                     WHERE dat_begin  <= \''.$endDateTime->getDateTimeEnglish().'\'
                       AND dat_end    >= \''.$startDateTime->getDateTimeEnglish().'\'
                       AND dat_room_id = '.$_POST['dat_room_id'].' 
                       AND dat_id     <> '.$getDateId;
            $result = $gDb->query($sql);
            $row = $gDb->fetch_object($result);
            if($row->is_reserved) 
            {
                $gMessage->show($gL10n->get('DAT_ROOM_RESERVED'));
            }
            
            $date->setValue('dat_room_id',$_POST['dat_room_id']);
            $room = new TableRooms($gDb);
            $room->readDataById($_POST['dat_room_id']);
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

		// Daten für Benachrichtigung zusammenstellen
		if($_POST['date_from'] == $_POST['date_to'])
		{$datum = $_POST['date_from'];}
		else
		{$datum = $_POST['date_from']. ' - '.$_POST['date_to'];}
		
		if(isset($_POST['dat_all_day']))
		{$zeit = $gL10n->get('DAT_ALL_DAY');}
		else
		{$zeit = $_POST['time_from']. ' - '. $_POST['time_to'];}
		
		$sql_cal = 'SELECT cat_name FROM '.TBL_CATEGORIES.' 
             WHERE cat_id = '.$_POST['dat_cat_id'];
		$gDb->query($sql_cal);
		$row_cal = $gDb->fetch_array();
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
		
		$message = $gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART1', $gCurrentOrganization->getValue('org_longname'), $_POST['dat_headline'], $datum. ' ('. $zeit. ')', $calendar)
                  .$gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART2', $ort, $raum, $teilnehmer, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'))
                  .$gL10n->get('DAT_EMAIL_NOTIFICATION_MESSAGE_PART3', date($gPreferences['system_date'], time()));
        
        $notification = new Email();
        $notification->adminNotfication($gL10n->get('DAT_EMAIL_NOTIFICATION_TITLE'), $message, $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), $gCurrentUser->getValue('EMAIL'));
	}
    
    // ----------------------------------------
    // ggf. Rolle fuer Anmeldungen wegschreiben
    // ----------------------------------------         

    if($_POST['date_login'] == 1 && strlen($date->getValue('dat_rol_id')) == 0)
    {
        // Kategorie fuer Terminbestaetigungen einlesen
        $sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' 
                 WHERE cat_name_intern LIKE \'CONFIRMATION_OF_PARTICIPATION\'';
        $gDb->query($sql);
        $row = $gDb->fetch_array();

        // create role for participations
        $role = new TableRoles($gDb);
        $role->setValue('rol_cat_id', $row['cat_id']);
        $role->setValue('rol_name', $gL10n->get('DAT_DATE').' '. $date->getValue('dat_begin', 'Y-m-d H:i').' - '.$date->getValue('dat_id'));
        $role->setValue('rol_description', $date->getValue('dat_headline'));
        $role->setValue('rol_this_list_view', '1');	// role members are allowed to view lists
        $role->setValue('rol_visible', '0');
        $role->setValue('rol_leader_rights', ROLE_LEADER_MEMBERS_ASSIGN);	// leaders are allowed to add or remove participations
        $role->setValue('rol_max_members', $_POST['dat_max_members']);
        
        // save role in database
        $return_code2 = $role->save();
        if($return_code < 0 || $return_code2 < 0)
        {
            $date->delete();
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        
        // dat_rol_id anpassen (Referenz zwischen date und role)
        $date->setValue('dat_rol_id', $role->getValue('rol_id'));
        $return_code = $date->save();
        if($return_code < 0)
        { 
            $role->delete();
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        
		if(isset($_POST['date_assign_yourself']) && $_POST['date_assign_yourself'] == 1)
		{
			//Termin-Ersteller als Member und Leader eintragen
			$gCurrentUser->setRoleMembership($role->getValue('rol_id'), DATE_NOW, '9999-12-31', 1);
		}
    }
    elseif($_POST['date_login'] == 0 && $date->getValue('dat_rol_id') > 0)
    {
    	// date participation was deselected -> delete flag in event and than delete role
        $role = new TableRoles($gDb, $date->getValue('dat_rol_id'));
        $date->setValue('dat_rol_id', '');
        $date->save();
        $role->delete();
    }

    unset($_SESSION['dates_request']);
    $_SESSION['navigation']->deleteLastUrl();

    header('Location: '. $_SESSION['navigation']->getUrl());
    exit();
}
elseif($getMode == 2)  // Termin loeschen
{
    // Termin loeschen, wenn dieser zur aktuellen Orga gehoert
    if($date->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
    {
         //member bzw. Teilnahme/Rolle löschen
        $date->delete();
        
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
}
elseif($getMode == 3)  // Benutzer zum Termin anmelden
{   
    $member = new TableMembers($gDb);
	$member->startMembership($role->getValue('rol_id'), $gCurrentUser->getValue('usr_id'));

    $gMessage->setForwardUrl($_SESSION['navigation']->getUrl());
    $gMessage->show($gL10n->get('DAT_ATTEND_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $gL10n->get('DAT_ATTEND'));
}
elseif($getMode == 4)  // Benutzer vom Termin abmelden
{
    $sql = 'DELETE FROM '.TBL_MEMBERS.' 
             WHERE mem_rol_id = \''.$date->getValue('dat_rol_id').'\'
               AND mem_usr_id = \''.$gCurrentUser->getValue('usr_id').'\'';
    $gDb->query($sql);

    $gMessage->setForwardUrl($_SESSION['navigation']->getUrl());
    $gMessage->show($gL10n->get('DAT_CANCEL_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin')), $gL10n->get('DAT_ATTEND'));
}
elseif($getMode == 5)  // Eintrag fuer Sichtbarkeit erzeugen
{
    $label = '';

    if($getNumberRoleSelect == 1)
    {
        $label = $gL10n->get('DAT_VISIBLE_TO').':';
    }
    echo '<dl id="roleID_'.$getNumberRoleSelect.'">
        <dt>'.$label.'</dt>
        <dd>'.FormElements::generateRoleSelectBox($getRoleId, 'role_'.$getNumberRoleSelect, 0, 1);
            if($getNumberRoleSelect > 1)
            {
                echo '<a href="javascript:removeRoleSelection(\'roleID_'.$getNumberRoleSelect.'\')"><img 
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('DAT_ADD_ROLE').'" /></a>';
            }
        echo '</dd>
    </dl>';
    exit();
}
elseif($getMode == 6)  // Termin im iCal-Format exportieren
{
    $filename = $date->getValue('dat_headline');
    
    // for IE the filename must have special chars in hexadecimal 
    if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
    {
        $filename = urlencode($filename);
    }

    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="'. $filename. '.ics"');
    
    // neccessary for IE, because without it the download with SSL has problems
	header('Cache-Control: private');
	header('Pragma: public');

    echo $date->getIcal($_SERVER['HTTP_HOST']);
    exit();
}

?>