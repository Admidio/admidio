<?php 
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Objekt einer Organisation zu erstellen. 
 * Eine Organisation kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $orga = new TblOrganization($g_db);
 *
 * Mit der Funktion getOrganization($shortname) kann die gewuenschte Organisation
 * ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) 
 *                        - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id)   - Rolle wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die aktuelle Orga wird aus der Datenbank geloescht
 * getPreferences()       - gibt ein Array mit allen organisationsspezifischen Einstellungen
 *                          aus adm_preferences zurueck
 * getReferenceOrganizations($child = true, $parent = true)
 *                        - Gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
 * isChildOrganization($organization)
 *                        - prueft ob die uebergebene Orga Kind der aktuellen Orga ist
 * hasChildOrganizations()- prueft, ob die Orga Kinderorganisationen besitzt
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

class Organization extends TableAccess
{
    var $b_check_childs;        // Flag, ob schon nach Kinderorganisationen gesucht wurde
    var $child_orgas = array(); // Array mit allen Kinderorganisationen
    
    // Konstruktor
    function Organization(&$db, $organization = "")
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ORGANIZATIONS;
        $this->column_praefix = "org";
        $this->key_name       = "org_id";
        
        if(strlen($organization) > 0)
        {
            $this->getOrganization($organization);
        }
        else
        {
            $this->clear();
        }
    }

    // Organisation mit der uebergebenen ID oder der Kurzbezeichnung aus der Datenbank auslesen
    function getOrganization($organization)
    {
        $condition = "";
        
        // wurde org_shortname uebergeben, dann die SQL-Bedingung anpassen
        if(is_numeric($organization) == false)
        {
            $organization = addslashes($organization);
            $condition = " org_shortname LIKE '$organization' ";
        }
        
        $this->readData($organization, $condition);
    }
    
    // interne Funktion, die spezielle Daten des Organizationobjekts loescht
    // die Funktion wird innerhalb von clear() aufgerufen
    function _clear()
    {
        $this->b_check_childs = false;
        $this->child_orgas    = array();
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "org_id":
            case "org_org_id_parent":
                if(is_numeric($field_value) == false
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;
        }       
        return true;
    }
        
    // gibt ein Array mit allen organisationsspezifischen Einstellungen
    // aus adm_preferences zurueck
    function getPreferences()
    {
        $sql    = "SELECT * FROM ". TBL_PREFERENCES. "
                    WHERE prf_org_id = ". $this->db_fields['org_id'];
        $result = $this->db->query($sql);

        $preferences = array();
        while($prf_row = $this->db->fetch_array($result))
        {
            $preferences[$prf_row['prf_name']] = $prf_row['prf_value'];
        }
        
        return $preferences;
    }
    
    // die Funktion schreibt alle Parameter aus dem uebergebenen Array
    // zurueck in die Datenbank, dabei werden nur die veraenderten oder
    // neuen Parameter geschrieben
    // $update : bestimmt, ob vorhandene Werte aktualisiert werden
    function setPreferences($preferences, $update = true)
    {
        $db_preferences = $this->getPreferences();

        foreach($preferences as $key => $value)
        {
            if(array_key_exists($key, $db_preferences))
            {
                if($update == true
                && $value  != $db_preferences[$key])
                {
                    // Pref existiert in DB, aber Wert hat sich geaendert
                    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '$value'
                             WHERE prf_org_id = ". $this->db_fields['org_id']. "
                               AND prf_name   = '$key' ";
                    $this->db->query($sql);
                }
            }
            else
            {
                // Parameter existiert noch nicht in DB
                $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                        VALUES   (". $this->db_fields['org_id']. ", '$key', '$value') ";
                $this->db->query($sql);
            }
        }
    }
    
    // gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
    // Ueber die Variablen $child und $parent kann die ermittlen der 
    // Eltern bzw. Kinderorgas deaktiviert werden
    //
    // org_id ist der Schluessel und org_shortname der Wert des Arrays
    // falls $longname = true gesetzt ist, ist org_longname der Wert des Arrays
    function getReferenceOrganizations($child = true, $parent = true, $longname = false)
    {
        $arr_child_orgas = array();
    
        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                 WHERE ";
        if($child == true)
        {
            $sql .= " org_org_id_parent = ". $this->db_fields['org_id'];
        }
        if($parent == true
        && $this->db_fields['org_org_id_parent'] > 0)
        {
            if($child == true)
            {
                $sql .= " OR ";
            }
            $sql .= " org_id = ". $this->db_fields['org_org_id_parent'];
        }
        $this->db->query($sql);
        
        while($row = $this->db->fetch_array())
        {
            if($longname == true)
            {
                $arr_child_orgas[$row->org_id] = $row['org_longname'];
            }
            else
            {
                $arr_child_orgas[$row->org_id] = $row['org_shortname'];
            }
        }
        return $arr_child_orgas;
    }
    
    // prueft, ob die uebergebene Orga (ID oder Shortname) ein Kind
    // der aktuellen Orga ist
    function isChildOrganization($organization)
    {
        if($this->b_check_childs == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->child_orgas = $this->getReferenceOrganizations(true, false);
            $this->b_check_childs = true;
        }
        
        if(is_numeric($organization))
        {
            // org_id wurde uebergeben
            $ret_code = array_key_exists($organization, $this->child_orgas);
        }
        else
        {
            // org_shortname wurde uebergeben
            $ret_code = in_array($organization, $this->child_orgas);
        }
        return $ret_code;
    }
    
    // prueft, ob die Orga Kinderorganisationen besitzt
    function hasChildOrganizations()
    {
        if($this->b_check_childs == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->child_orgas = $this->getReferenceOrganizations(true, false);
            $this->b_check_childs = true;
        }

        if(count($this->child_orgas) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
?>