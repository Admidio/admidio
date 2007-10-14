<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_sessions
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Sessionobjekt zu erstellen.
 * Eine Session kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $session = new Session($g_db);
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
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class Session extends TableAccess
{
    // Konstruktor
    function Session(&$db, $session = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_SESSIONS;
        $this->column_praefix = "ses";
        $this->key_name       = "ses_id";
        $this->auto_increment = true;
        
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
        // wurde ses_session_id uebergeben, dann die SQL-Bedingung anpassen
        if(is_numeric($session) == false)
        {
            $condition = " ses_session_id = '$session' ";
        }       
        
        $this->readData($session, $condition);
    }

    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "ses_id":
            case "ses_org_id":
            case "ses_usr_id":
                if(is_numeric($field_value) == false
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;
                    
            case "ses_renew":
                if(is_numeric($field_value) == false)
                {
                    $field_value = "";
                    return false;
                }
                break;   
        }       
        return true;
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        if($this->new_record)
        {
            // Insert
            global $g_current_organization;
            $this->setValue("ses_org_id", $g_current_organization->getValue("org_id"));
            $this->setValue("ses_begin", date("Y-m-d H:i:s", time()));
            $this->setValue("ses_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("ses_ip_address", $_SERVER['REMOTE_ADDR']);
        }
        else
        {
            // Update
            $this->db_fields['ses_timestamp'] = date("Y-m-d H:i:s", time());
        }
    }  

    // diese Funktion stoesst ein Neueinlesen des User-Objekts bei allen angemeldeten
    // Usern beim naechsten Seitenaufruf an
    function renewUserObject()
    {
        $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_renew = 1 ";
        $this->db->query($sql);
    }
    
    // diese Funktion stoesst ein Neueinlesen des Organisations-Objekts bei allen angemeldeten
    // Usern beim naechsten Seitenaufruf an
    function renewOrganizationObject()
    {
        $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_renew = 2 ";
        $this->db->query($sql);
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
        $this->db->query($sql);
    }
}
?>