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
 * $orga = new TblOrganization($g_adm_con);
 *
 * Mit der Funktion getOrganization($shortname) kann die gewuenschte Organisation
 * ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * update()         - Die Organisation wird mit den geaenderten Daten in die Datenbank 
 *                    zurueckgeschrieben
 * insert()         - Eine neue Organisation wird in die Datenbank geschrieben
 * clear()          - Die Klassenvariablen werden neu initialisiert
 * getPreferences() - gibt ein Array mit allen organisationsspezifischen Einstellungen
 *                    aus adm_preferences zurueck
 * getReferenceOrganizations($child = true, $parent = true)
 *                  - Gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
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

class Organization
{
    var $db_connection;
    
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle

    // Konstruktor
    function Organization($connection, $organization = 0)
    {
        $this->db_connection = $connection;
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
        if(is_numeric($organization))
        {
        	$condition = " org_id = $organization ";
        }
        else
        {
            $organization = addslashes($organization);
            $condition = " org_shortname LIKE '$organization' ";
        }

        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " 
                 WHERE $condition ";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        if($row = mysql_fetch_array($result, MYSQL_ASSOC))
        {
            // Daten in das Klassenarray schieben
            foreach($row as $key => $value)
            {
                if(is_null($value))
                {
                    $this->db_fields[$key] = "";
                }
                else
                {
                    $this->db_fields[$key] = $value;
                }
            }
        }        
    }

    // alle Klassenvariablen wieder zuruecksetzen
   function clear()
   {
        $this->db_fields_changed = false;
        
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = "";
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_ORGANIZATIONS;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                $this->db_fields[$row['Field']] = "";
            }
        }
    }

    // Funktion uebernimmt alle Werte eines Arrays in das Field-Array
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->db_fields[$field] = $value;
        }
    }
    
    // Funktion setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        if(strlen($field_value) > 0)
        {
            // Plausibilitaetspruefungen
            switch($field_name)
            {
                case "org_id":
                case "org_org_id_parent":
                    if(is_numeric($field_value) == false
                    || $field_value == 0)
                    {
                        $field_value = "";
                    }
                    break;
            }
        }

        if(isset($this->db_fields[$field_name])
        && $field_value != $this->db_fields[$field_name])
        {
            $this->db_fields[$field_name] = $field_value;
            $this->db_fields_changed      = true;
        }
    }

    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }
    
    // die Funktion speichert die Organisationsdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        if(is_numeric($this->db_fields['org_id']) || strlen($this->db_fields['org_id']) == 0)
        {
            if($this->db_fields_changed || strlen($this->db_fields['org_id']) == 0)
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "org_id" && strpos($key, "org_") === 0) 
                    {
                        if($this->db_fields['org_id'] == 0)
                        {
                            if(strlen($value) > 0)
                            {
                                // Daten fuer ein Insert aufbereiten
                                $sql_field_list = $sql_field_list. " $item_connection $key ";
                                if(is_numeric($value))
                                {
                                    $sql_value_list = $sql_value_list. " $item_connection $value ";
                                }
                                else
                                {
                                    $value = addSlashes($value);
                                    $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                                }
                            }
                        }
                        else
                        {
                            // Daten fuer ein Update aufbereiten
                            if(strlen($value) == 0 || is_null($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = NULL ";
                            }
                            elseif(is_numeric($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = $value ";
                            }
                            else
                            {
                                $value = addSlashes($value);
                                $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ",";
                        }
                    }
                }

                if($this->db_fields['org_id'] > 0)
                {
                    $sql = "UPDATE ". TBL_ORGANIZATIONS. " SET $sql_field_list 
                             WHERE org_id = ". $this->db_fields['org_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
                else
                {
                    $sql = "INSERT INTO ". TBL_ORGANIZATIONS. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['org_id'] = mysql_insert_id($this->db_connection);
                }
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }    
    
    // gibt ein Array mit allen organisationsspezifischen Einstellungen
    // aus adm_preferences zurueck
    function getPreferences()
    {
        $sql    = "SELECT * FROM ". TBL_PREFERENCES. "
                    WHERE prf_org_id = ". $this->db_fields['org_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $preferences = array();
        while($prf_row = mysql_fetch_array($result))
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
					$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = $value
					         WHERE prf_org_id = ". $this->db_fields['org_id']. "
							   AND prf_name   = '$key' ";
					error_log($sql);	
			        $result = mysql_query($sql, $this->db_connection);
			        db_error($result,__FILE__,__LINE__);
    			}
    		}
    		else
    		{
    			// Parameter existiert noch nicht in DB
				$sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
						VALUES   (". $this->db_fields['org_id']. ", '$key', '$value') ";
		        error_log($sql);				
		        $result = mysql_query($sql, $this->db_connection);
		        db_error($result,__FILE__,__LINE__);
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
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);
        
        while($row = mysql_fetch_object($result))
        {
            if($longname == true)
            {
                $arr_child_orgas[$row->org_id] = $row->org_longname;
            }
            else
            {
                $arr_child_orgas[$row->org_id] = $row->org_shortname;
            }
        }
        return $arr_child_orgas;
    }
}
?>