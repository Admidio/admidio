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
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * editRight()       - prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

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
            $this->readData($ann_id);
        }
        else
        {
            $this->clear();
        }
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("ann_timestamp_create", date("Y-m-d H:i:s", time()));
            $this->setValue("ann_usr_id_create", $g_current_user->getValue("usr_id"));
            $this->setValue("ann_org_shortname", $g_current_organization->getValue("org_shortname"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("ann_timestamp_create")) + 900)
            || $g_current_user->getValue("usr_id") != $this->getValue("ann_usr_id_create") )
            {
                $this->setValue("ann_timestamp_change", date("Y-m-d H:i:s", time()));
                $this->setValue("ann_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
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