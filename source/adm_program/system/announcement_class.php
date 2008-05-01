<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_announcements
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen. 
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new Announcement($g_db);
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
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Announcement extends TableAccess
{
    // Konstruktor
    function Announcement(&$db, $ann_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ANNOUNCEMENTS;
        $this->column_praefix = "ann";
        
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
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("ann_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("ann_usr_id", $g_current_user->getValue("usr_id"));
            $this->setValue("ann_org_shortname", $g_current_organization->getValue("org_shortname"));
        }
        else
        {
            $this->setValue("ann_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("ann_usr_id_change", $g_current_user->getValue("usr_id"));
        }
    }
    
    // prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
    function editRight()
    {
        global $g_current_organization;
        
        // Ankuendigung der eigenen Orga darf bearbeitet werden
        if($this->db_fields['ann_org_shortname'] == $g_current_organization->getValue("org_shortname"))
        {
            return true;
        }
        // Ankuendigung von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->db_fields['ann_global'] == true
        && $g_current_organization->isChildOrganization($this->db_fields['ann_org_shortname']))
        {
            return true;
        }
    
        return false;
    }
}
?>