<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
    function checkValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "dat_id":
            case "dat_usr_id":
            case "dat_usr_id_change":
                if(is_numeric($field_value) == false)
                {
                    $field_value = null;
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
    function initializeFields()
    {
        global $g_current_organization, $g_current_user;
        
        if(strlen($this->db_fields[$this->key_name]) > 0)
        {
            $this->db_fields['dat_last_change']   = date("Y-m-d H:i:s", time());
            $this->db_fields['dat_usr_id_change'] = $g_current_user->getValue("usr_id");
        }
        else
        {
            $this->db_fields['dat_timestamp']     = date("Y-m-d H:i:s", time());
            $this->db_fields['dat_usr_id']        = $g_current_user->getValue("usr_id");
            $this->db_fields['dat_org_shortname'] = $g_current_organization->getValue("org_shortname");
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
    
        $event->add_property('summary',     utf8_encode($this->db_fields['dat_headline']));
        $event->add_property('description', utf8_encode($this->db_fields['dat_description']));

        $event->add_property('dtstart', mysqldatetime("ymdThis", $this->db_fields['dat_begin']));
        $event->add_property('dtend',   mysqldatetime("ymdThis", $this->db_fields['dat_end']));
        $event->add_property('dtstamp', mysqldatetime("ymdThisZ", $this->db_fields['dat_timestamp']));

        $event->add_property('location', utf8_encode($this->db_fields['dat_location']));

        $cal->add_component($event);
        return $cal->serialize();    
    }    
}
?>