<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 *
 * Diese Klasse dient dazu einen Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new Date($g_adm_con);
 *
 * Mit der Funktion getDate($dat_id) kann nun der gewuenschte Termin ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * update($login_user_id) - Termin wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben
 * insert($login_user_id) - Ein neuer Termin wird in die Datenbank geschrieben
 * delete()               - Der gewaehlte User wird aus der Datenbank geloescht
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

include(SERVER_PATH. "/adm_program/libs/bennu/bennu.inc.php");

class Date
{
    var $db_connection;
    var $db_fields = array();    
    
    // Konstruktor
    function Date($connection, $date_id = 0)
    {
        $this->db_connection = $connection;
        if($date_id > 0)
        {
            $this->getRole($date_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function getDate($date_id)
    {
        $this->clear();
        
        if($date_id > 0 && is_numeric($date_id))
        {
            $sql    = "SELECT * FROM ". TBL_DATES. " WHERE dat_id = $date_id";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
    
            if($row = mysql_fetch_array($result, MYSQL_ASSOC))
            {
                // Daten in das Klassenarray schieben
                foreach($row as $key => $value)
                {
                    $this->db_fields[$key] = $value;
                }
            }
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = null;
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_DATES;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                $this->db_fields[$row['Field']] = null;
            }
        }
    }

    // Funktion setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        if(strlen($field_value) == 0)
        {
            $field_value = null;
        }
        
        // Plausibilitaetspruefungen
        switch($field_name)
        {
            case "dat_id":
            case "dat_usr_id":
            case "dat_usr_id_change":
                if(is_numeric($field_value) == false)
                {
                    $field_value = null;
                }
                break;
            
            case "dat_global":
                if($field_value != 1)
                {
                    $field_value = 0;
                }
                break;
        }
                
        $this->db_fields[$field_name] = $field_value;
    }

    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }
    
    // aktuelle Rollendaten in der Datenbank updaten
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function update($login_user_id)
    {
        if(count($this->db_fields)    > 0
        && $this->db_fields['dat_id'] > 0 
        && $login_user_id             > 0 
        && is_numeric($this->db_fields['dat_id'])
        && is_numeric($login_user_id))
        {
            $act_date = date("Y-m-d H:i:s", time());

            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->db_fields as $key => $value)
            {
                // rol_id soll nicht im Update erscheinen
                if($key != "dat_id") 
                {
                    // jetzt noch Spezialfaelle abhandeln
                    switch($key)
                    {
                        case "dat_last_change":
                            $sql_field_list = $sql_field_list. " $item_connection $key = '$act_date' ";
                            break;

                        case "dat_usr_id_change":
                            $sql_field_list = $sql_field_list. " $item_connection $key = $login_user_id ";
                            break;

                        default:
                            $sql_field_list = $sql_field_list. " $item_connection $key = \{$key} ";
                            break;
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }
            $sql = "UPDATE ". TBL_DATES. " SET $sql_field_list WHERE dat_id = {dat_id} ";
            $sql = prepareSQL($sql, $this->db_fields);
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            return 0;
        }
        return -1;
    }

    // aktuelle Rollendaten neu in der Datenbank schreiben
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function insert($login_user_id)
    {
        global $g_organization;
        
        if($login_user_id > 0 
        && is_numeric($login_user_id)
        && (  isset($this->db_fields['dat_id']) == false
           || $this->db_fields['dat_id']        == 0 ))
        {
            $act_date = date("Y-m-d H:i:s", time());

            // SQL-Update-Statement zusammenbasteln
            $item_connection = "";
            $sql_field_list  = "";
            $sql_value_list  = "";

            // Schleife ueber alle DB-Felder und diese dem Insert hinzufuegen 
            foreach($this->db_fields as $key => $value)
            {
                // rol_id soll nicht im Insert erscheinen
                if($key != "dat_id" && strlen($value) > 0) 
                {
                    $sql_field_list = $sql_field_list. " $item_connection $key ";
                    if(is_numeric($value))
                    {
                        $sql_value_list = $sql_value_list. " $item_connection $value ";
                    }
                    else
                    {
                        $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                    }

                    if(strlen($item_connection) == 0)
                    {
                        $item_connection = ",";
                    }
                }
            }
            
            // Felder hinzufuegen, die zwingend erforderlich sind
            if(isset($this->db_fields['dat_org_shortname']) == false)
            {
                $sql_field_list = $sql_field_list. ", dat_org_shortname ";
                $sql_value_list = $sql_value_list. ", '$g_organization' ";
            }
            if(isset($this->db_fields['dat_timestamp']) == false)
            {
                $sql_field_list = $sql_field_list. ", dat_timestamp ";
                $sql_value_list = $sql_value_list. ", '$act_date' ";
            }
            if(isset($this->db_fields['dat_usr_id']) == false)
            {
                $sql_field_list = $sql_field_list. ", dat_usr_id ";
                $sql_value_list = $sql_value_list. ", $login_user_id ";
            }
            
            $sql = "INSERT INTO ". TBL_DATES. " ($sql_field_list) VALUES ($sql_value_list) ";
            $sql = prepareSQL($sql, $this->db_fields);
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            $this->db_fields['dat_id'] = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }    
    
    // aktuellen Benutzer loeschen   
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_DATES. " 
                    WHERE dat_id = ". $this->db_fields['dat_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
    }
   
    // gibt einen Termin im iCal-Format zurueck
    function getIcal($domain)
    {
        $cal = new iCalendar;
        $event = new iCalendar_event;
        $cal->add_property('METHOD','PUBLISH');
        $prodid = "-//www.admidio.org//Admidio" . ADMIDIO_VERSION . "//DE";
        $cal->add_property('PRODID',$prodid);
        $uid = mysqldatetime("ymdThis", $this->db_fields['timestamp']) . "+" . $this->db_fields['usr_id'] . "@" . $domain;
        $event->add_property('uid', $uid);
    
        $event->add_property('summary',     utf8_encode($this->db_fields['headline']));
        $event->add_property('description', utf8_encode($this->db_fields['description']));

        $event->add_property('dtstart', mysqldatetime("ymdThis", $this->db_fields['begin']));
        $event->add_property('dtend',   mysqldatetime("ymdThis", $this->db_fields['end']));
        $event->add_property('dtstamp', mysqldatetime("ymdThisZ", $this->db_fields['timestamp']));

        $event->add_property('location', utf8_encode($this->db_fields['location']));

        $cal->add_component($event);
        return $cal->serialize();    
    }    
}
?>
