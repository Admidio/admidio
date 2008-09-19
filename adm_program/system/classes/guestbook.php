<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_guestbook
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Gaestebucheintragsobjekt zu erstellen. 
 * Eine Gaestebucheintrag kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $guestbook = new Guestbook($g_db);
 *
 * Mit der Funktion readData($gbo_id) kann nun der gewuenschte Gaestebucheintrag ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array 
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Gaestebucheintrag wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Der aktuelle Gaestebucheintrag wird aus der Datenbank geloescht
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class Guestbook extends TableAccess
{
    // Konstruktor
    function Guestbook(&$db, $gbo_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_GUESTBOOK;
        $this->column_praefix = "gbo";
        
        if($gbo_id > 0)
        {
            $this->readData($gbo_id);
        }
        else
        {
            $this->clear();
        }
    }
    
    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Methode wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, &$field_value)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == "gbo_homepage")
            {
                // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
                if (substr($field_value, 0, 7) != 'http://' && substr($field_value, 0, 8) != 'https://' )
                {
                    $field_value = "http://". $field_value;
                }
            }
            elseif($field_name == "gbo_email")
            {
                if (!isValidEmailAddress($field_value))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie einfach auf null gesetzt
                    $field_value = "";
                }
            }
        }
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("gbo_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("gbo_usr_id", $g_current_user->getValue("usr_id"));
            $this->setValue("gbo_org_id", $g_current_organization->getValue("org_id"));
            $this->setValue("gbo_ip_address", $_SERVER['REMOTE_ADDR']);
        }
        else
        {
            $this->setValue("gbo_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("gbo_usr_id_change", $g_current_user->getValue("usr_id"));
        }
    }
    
    // die Methode wird innerhalb von delete() aufgerufen
    function _delete()
    {
        //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
        $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_gbo_id = ". $this->db_fields['gbo_id'];
        $result = $this->db->query($sql);
        
        return true;
    }    
}
?>