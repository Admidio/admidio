<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_roles
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Rollenobjekt zu erstellen.
 * Eine Rolle kann ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Informationen der Rolle sowie der zugehoerigen Kategorie
 * ausgelesen. Geschrieben werden aber nur die Rollendaten
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * setInactive()          - setzt die Rolle auf inaktiv
 * setActive()            - setzt die Rolle wieder auf aktiv
 * countVacancies($count_leaders = false) - gibt die freien Plaetze der Rolle zurueck
 *                          dies ist interessant, wenn rol_max_members gesetzt wurde
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableRole extends TableAccess
{
    // Konstruktor
    function TableRole(&$db, $role = "")
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ROLES;
        $this->column_praefix = "rol";
        
        if(strlen($role) > 0)
        {
            $this->readData($role);
        }
        else
        {
            $this->clear();
        }
    }

    // Rolle mit der uebergebenen ID oder dem Rollennamen aus der Datenbank auslesen
    function readData($role)
    {
        global $g_current_organization;

        if(is_numeric($role))
        {
            $condition = " rol_id = $role ";
        }
        else
        {
            $role = addslashes($role);
            $condition = " rol_name LIKE '$role' ";
        }
        
        $tables    = TBL_CATEGORIES;
        $condition = $condition. " AND rol_cat_id = cat_id
                                   AND cat_org_id = ". $g_current_organization->getValue("org_id");
        parent::readData($role, $condition, $tables);
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_user, $g_current_session;
        $fields_changed = $this->db_fields_changed;
        
        
        if($this->new_record)
        {
            $this->setValue("rol_timestamp_create", date("Y-m-d H:i:s", time()));
            $this->setValue("rol_usr_id_create", $g_current_user->getValue("usr_id"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("rol_timestamp_create")) + 900)
            || $g_current_user->getValue("usr_id") != $this->getValue("rol_usr_id_create") )
            {
                $this->setValue("rol_timestamp_change", date("Y-m-d H:i:s", time()));
                $this->setValue("rol_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }        

        parent::save();

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl. 
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
        }
    }
    
    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
    // die Funktion wird innerhalb von delete() aufgerufen
    function delete()
    {
        global $g_current_session;
        
        // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl. 
        // eine Rechteaenderung vorgenommen wurde
        $g_current_session->renewUserObject();
            
        // die Rolle "Webmaster" darf nicht geloescht werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "DELETE FROM ". TBL_ROLE_DEPENDENCIES. " 
                        WHERE rld_rol_id_parent = ". $this->db_fields['rol_id']. "
                           OR rld_rol_id_child  = ". $this->db_fields['rol_id'];
            $this->db->query($sql);

            $sql    = "DELETE FROM ". TBL_MEMBERS. " 
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $this->db->query($sql);
            
            return parent::delete();
        }
        else
        {
            return false;
        }   
    }
    
    // aktuelle Rolle wird auf inaktiv gesetzt
    function setInactive()
    {
        global $g_current_session;
        
        // die Rolle "Webmaster" darf nicht auf inaktiv gesetzt werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                                                  , mem_end   = '".date("Y-m-d", time())."'
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1 ";
            $this->db->query($sql);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 0
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $this->db->query($sql);
            
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl. 
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
        
            return 0;
        }
        return -1;
    }

    // aktuelle Rolle wird auf aktiv gesetzt
    function setActive()
    {
        global $g_current_session;
        
        // die Rolle "Webmaster" ist immer aktiv
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 1
                                                  , mem_end   = NULL
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $this->db->query($sql);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 1
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $this->db->query($sql);
            
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl. 
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
            
            return 0;
        }
        return -1;
    }
    
    // die Funktion gibt die Anzahl freier Plaetze zurueck
    // ist rol_max_members nicht gesetzt so wird immer 999 zurueckgegeben
    function countVacancies($count_leaders = false)
    {
        if($this->db_fields['rol_max_members'] > 0)
        {
            $sql    = "SELECT mem_usr_id FROM ". TBL_MEMBERS. "
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1";
            if($count_leaders == false)
            {
                $sql = $sql. " AND mem_leader = 0 ";
            }
            $this->db->query($sql);
            
            $num_members = $this->db->num_rows();            
            return $this->db_fields['rol_max_members'] - $num_members;
        }
        return 999;
    }
}
?>