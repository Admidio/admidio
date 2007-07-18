<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_sessions
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu ein Sessionobjekt zu erstellen.
 * Eine Session kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $session = new Session($g_adm_con);
 *
 * Mit der Funktion getSession($session_id) kann die gewuenschte Session ausgelesen
 * werden. Die Session ID ist hierbei allerdings der eindeutige String aus der PHP-Session
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) 
 *                         - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id)   - Rolle wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die gewaehlte Rolle wird aus der Datenbank geloescht
 * renewUserObject()      - diese Funktion stoesst ein Neueinlesen des User-Objekts an
 * renewOrganizationObject() 
 *                        - diese Funktion stoesst ein Neueinlesen des Organisations-Objekts an
 * tableCleanup($max_inactive_time)         
 *                        - Funktion loescht Datensaetze aus der Session-Tabelle 
 *                          die nicht mehr gebraucht werden
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

class Session
{
    var $db_connection;
    
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle

    // Konstruktor
    function Session($connection, $session = 0)
    {
        $this->db_connection = $connection;
        if(strlen($session) > 0)
        {
            $this->getSession($session);
        }
        else
        {
            $this->clear();
        }
    }

    // Session mit der uebergebenen Session-ID aus der Datenbank auslesen
    function getSession($session)
    {
        $this->clear();
        
        if(is_numeric($session))
        {
            $sql = "SELECT * 
                      FROM ". TBL_SESSIONS. "
                     WHERE ses_id    = $session ";
        }
        else
        {
            $sql = "SELECT * 
                      FROM ". TBL_SESSIONS. "
                     WHERE ses_session = '$session' ";
        }
        
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
            $sql = "SHOW COLUMNS FROM ". TBL_SESSIONS;
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
                case "ses_id":
                case "ses_org_id":
                case "ses_usr_id":
                    if(is_numeric($field_value) == false
                    || $field_value == 0)
                    {
                        $field_value = "";
                    }
                    break;

                case "ses_renew":
                    if(is_numeric($field_value) == false)
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
    
    // die Funktion speichert die Rollendaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        if(is_numeric($this->db_fields['ses_id']) || strlen($this->db_fields['ses_id']) == 0)
        {
            global $g_current_organization;
            if(strlen($this->db_fields['ses_id']) == 0)
            {
                // Default-Daten vorbelegen
                $this->db_fields['ses_org_id']     = $g_current_organization->getValue("org_id");
                $this->db_fields['ses_begin']      = date("Y-m-d H:i:s", time());
                $this->db_fields['ses_timestamp']  = date("Y-m-d H:i:s", time());
                $this->db_fields['ses_ip_address'] = $_SERVER['REMOTE_ADDR'];
            }
            else
            {
                $this->db_fields['ses_timestamp'] = date("Y-m-d H:i:s", time());
            }
            
            if($this->db_fields_changed || strlen($this->db_fields['ses_id']) == 0)
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "ses_id" && strpos($key, "ses_") === 0) 
                    {
                        if($this->db_fields['ses_id'] == 0)
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

                if($this->db_fields['ses_id'] > 0)
                {
                    $sql = "UPDATE ". TBL_SESSIONS. " SET $sql_field_list 
                             WHERE ses_id = ". $this->db_fields['ses_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
                else
                {
                    $sql = "INSERT INTO ". TBL_SESSIONS. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['ses_id'] = mysql_insert_id($this->db_connection);
                }
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }    

    // aktuelle Session loeschen
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_SESSIONS. " 
                    WHERE ses_id = ". $this->db_fields['ses_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
        return 0;
    }
    
    
    // diese Funktion stoesst ein Neueinlesen des User-Objekts beim naechsten Seitenaufruf an
    function renewUserObject()
    {
        $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_renew = 1 ";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);
        error_log($sql);
    }
    
    // diese Funktion stoesst ein Neueinlesen des Organisations-Objekts beim naechsten Seitenaufruf an
    function renewOrganizationObject()
    {
        $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_renew = 2 ";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);
    }
    
    // diese Funktion loescht Datensaetze aus der Session-Tabelle die nicht mehr gebraucht werden
    function tableCleanup($max_inactive_time)
    {
        // Zeitpunkt bestimmen, ab dem die Sessions geloescht werden, mind. 1 Stunde
        if($max_inactive_time > 60)
        {
            $date_session_delete = time() - $max_inactive_time * 60;
        }
        else
        {
            $date_session_delete = time() - 60 * 60;
        }
            
        $sql    = "DELETE FROM ". TBL_SESSIONS. " 
                    WHERE ses_timestamp < '". date("Y.m.d H:i:s", $date_session_delete). "'";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);        
    }
}
?>