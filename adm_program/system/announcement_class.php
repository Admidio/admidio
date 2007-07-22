<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_announcements
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen. 
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new Announcement($g_adm_con);
 *
 * Mit der Funktion getAnnouncement($ann_id) kann nun die gewuenschte Ankuendigung ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array 
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Ankuendigung wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die aktuelle Ankuendigung wird aus der Datenbank geloescht
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

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Announcement extends TableAccess
{
    // Konstruktor
    function Announcement($connection, $ann_id = 0)
    {
        $this->db_connection  = $connection;
        $this->table_name     = TBL_ANNOUNCEMENTS;
        $this->column_praefix = "ann";
        $this->key_name       = "ann_id";
        
        if($ann_id > 0)
        {
            $this->getAnnouncement($ann_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function getAnnouncement($ann_id)
    {
        $this->readData($ann_id);
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
	// und ungueltige Werte auf leer setzt
	// die Funktion wird innerhalb von setValue() aufgerufen
    function checkValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "ann_id":
            case "ann_usr_id":
            case "ann_usr_id_change":
                if(is_numeric($field_value) == false)
                {
                    $field_value = null;
                    return false;
                }
                break;

            case "ann_global":
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
            $this->db_fields['ann_last_change']   = date("Y-m-d H:i:s", time());
            $this->db_fields['ann_usr_id_change'] = $g_current_user->getValue("usr_id");
        }
        else
        {
            $this->db_fields['ann_timestamp']     = date("Y-m-d H:i:s", time());
            $this->db_fields['ann_usr_id']        = $g_current_user->getValue("usr_id");
            $this->db_fields['ann_org_shortname'] = $g_current_organization->getValue("org_shortname");
        }
    }
}
?>