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
    function setValue($field_name, $field_value)
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
        parent::setValue($field_name, $field_value);
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
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
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("gbo_timestamp")) + 900)
            || $g_current_user->getValue("usr_id") != $this->getValue("gbo_usr_id") )
            {
                $this->setValue("gbo_timestamp_change", date("Y-m-d H:i:s", time()));
                $this->setValue("gbo_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
    }
    
    // die Methode wird innerhalb von delete() aufgerufen
    function delete()
    {
        //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
        $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_gbo_id = ". $this->db_fields['gbo_id'];
        $result = $this->db->query($sql);
        
        return parent::delete();
    }    
}
?>