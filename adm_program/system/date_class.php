<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new Date($g_db);
 *
 * Mit der Funktion getDate($dat_id) kann nun der gewuenschte Termin ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array 
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Termin wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Der gewaehlte Termin wird aus der Datenbank geloescht
 * getIcal()              - gibt einen Termin im iCal-Format zurueck
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/libs/bennu/bennu.inc.php");
require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Date extends TableAccess
{
    // Konstruktor
    function Date(&$db, $date_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_DATES;
        $this->column_praefix = "dat";
        $this->key_name       = "dat_id";
        $this->auto_increment = true;
        
        if($date_id > 0)
        {
            $this->getDate($date_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function getDate($date_id)
    {
        $this->readData($date_id);
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "dat_id":
            case "dat_usr_id":
            case "dat_usr_id_change":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;

            case "dat_global":
                if($field_value != 1)
                {
                    $field_value = 0;
                    return false;
                }
                break; 
        }       
        return true;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->db_fields['dat_timestamp']     = date("Y-m-d H:i:s", time());
            $this->db_fields['dat_usr_id']        = $g_current_user->getValue("usr_id");
            $this->db_fields['dat_org_shortname'] = $g_current_organization->getValue("org_shortname");
        }
        else
        {
            $this->db_fields['dat_last_change']   = date("Y-m-d H:i:s", time());
            $this->db_fields['dat_usr_id_change'] = $g_current_user->getValue("usr_id");
        }
    }
   
    // gibt einen Termin im iCal-Format zurueck
    function getIcal($domain)
    {
        $cal = new iCalendar;
        $event = new iCalendar_event;
        $cal->add_property('METHOD','PUBLISH');
        $prodid = "-//www.admidio.org//Admidio" . ADMIDIO_VERSION . "//DE";
        $cal->add_property('PRODID',$prodid);
        $uid = mysqldatetime("ymdThis", $this->db_fields['dat_timestamp']) . "+" . $this->db_fields['dat_usr_id'] . "@" . $domain;
        $event->add_property('uid', $uid);
    
        $event->add_property('summary',     $this->db_fields['dat_headline']);
        $event->add_property('description', $this->db_fields['dat_description']);

        $event->add_property('dtstart', mysqldatetime("ymdThis", $this->db_fields['dat_begin']));
        $event->add_property('dtend',   mysqldatetime("ymdThis", $this->db_fields['dat_end']));
        $event->add_property('dtstamp', mysqldatetime("ymdThisZ", $this->db_fields['dat_timestamp']));

        $event->add_property('location', $this->db_fields['dat_location']);

        $cal->add_component($event);
        return $cal->serialize();    
    }    
    
    // prueft, ob der Termin von der aktuellen Orga bearbeitet werden darf
    function editRight()
    {
        global $g_current_organization;
        
        // Termine der eigenen Orga darf bearbeitet werden
        if($this->db_fields['dat_org_shortname'] == $g_current_organization->getValue("org_shortname"))
        {
            return true;
        }
        // Termine von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->db_fields['dat_global'] == true
        && $g_current_organization->isChildOrganization($this->db_fields['dat_org_shortname']))
        {
            return true;
        }
    
        return false;
    }    
}
?>