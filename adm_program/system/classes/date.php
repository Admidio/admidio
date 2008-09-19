<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
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

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class Date extends TableAccess
{
    // Konstruktor
    function Date(&$db, $date_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_DATES;
        $this->column_praefix = "dat";
        
        if($date_id > 0)
        {
            $this->getDate($date_id);
        }
        else
        {
            $this->clear();
        }
    }
    
    function _setValue($field_name, &$field_value)
    {
        if($field_name == "dat_end" && $this->db_fields['dat_all_day'] == 1)
        {
            // hier muss bei ganztaegigen Terminen das bis-Datum um einen Tag hochgesetzt werden
            // damit der Termin bei SQL-Abfragen richtig beruecksichtigt wird
            list($year, $month, $day, $hour, $minute, $second) = split("[- :]", $field_value);
            $field_value = date("Y-m-d H:i:s", mktime($hour, $minute, $second, $month, $day, $year) + 86400);
        }
    }
    
    function _getValue($field_name)
    {
        $value = $this->db_fields[$field_name];
        
        if($field_name == "dat_end" && $this->db_fields['dat_all_day'] == 1)
        {
            list($year, $month, $day, $hour, $minute, $second) = split("[- :]", $this->db_fields['dat_end']);
            $value = date("Y-m-d H:i:s", mktime($hour, $minute, $second, $month, $day, $year) - 86400);
        }
        
        return $value;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("dat_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("dat_usr_id", $g_current_user->getValue("usr_id"));
            $this->setValue("dat_org_shortname", $g_current_organization->getValue("org_shortname"));
        }
        else
        {
            $this->setValue("dat_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("dat_usr_id_change", $g_current_user->getValue("usr_id"));
        }
    }
   
    // gibt einen Termin im iCal-Format zurueck
    function getIcal($domain)
    {
        $prodid = "-//www.admidio.org//Admidio" . ADMIDIO_VERSION . "//DE";
        $uid = mysqldatetime("ymdThis", $this->db_fields['dat_timestamp']) . "+" . $this->db_fields['dat_usr_id'] . "@" . $domain;
        
        $ical = "BEGIN:VCALENDAR\n".
                "METHOD:PUBLISH\n".
                "PRODID:". $prodid. "\n".
                "VERSION:2.0\n".
                "BEGIN:VEVENT\n".
                "UID:". $uid. "\n".
                "SUMMARY:". $this->db_fields['dat_headline']. "\n".
                "DESCRIPTION:". $this->db_fields['dat_description']. "\n".
                "DTSTAMP:". mysqldatetime("ymdThisZ", $this->db_fields['dat_timestamp']). "\n".
                "LOCATION:". $this->db_fields['dat_location']. "\n";
        if($this->db_fields['dat_all_day'] == 1)
        {
            $ical .= "DTSTART;VALUE=DATE:". mysqldate("ymd", $this->db_fields['dat_begin']). "\n".
                     "DTEND;VALUE=DATE:". mysqldate("ymd", $this->db_fields['dat_end']). "\n";
        }
        else
        {
            $ical .= "DTSTART:". mysqldatetime("ymdThis", $this->db_fields['dat_begin']). "\n".
                     "DTEND:". mysqldatetime("ymdThis", $this->db_fields['dat_end']). "\n";
        }
        $ical .= "END:VEVENT\n".
                 "END:VCALENDAR";

        return $ical;
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