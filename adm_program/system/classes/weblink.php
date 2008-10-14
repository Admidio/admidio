<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_links
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Linkobjekt zu erstellen. 
 * Eine Weblink kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class Weblink extends TableAccess
{
    // Konstruktor
    function Weblink(&$db, $lnk_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_LINKS;
        $this->column_praefix = "lnk";
        
        if($lnk_id > 0)
        {
            $this->readData($lnk_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function readData($lnk_id)
    {
        global $g_current_organization;
        
        $tables    = TBL_CATEGORIES;
        $condition = "     lnk_id     = $lnk_id 
                       AND lnk_cat_id = cat_id
                       AND cat_org_id = ". $g_current_organization->getValue("org_id");
        parent::readData($lnk_id, $condition, $tables);
    }
    
    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Methode wird innerhalb von setValue() aufgerufen
    function setValue($field_name, $field_value)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == "lnk_url")
            {
                // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
                if (substr($field_value, 0, 7) != 'http://' && substr($field_value, 0, 8) != 'https://' )
                {
                    $field_value = "http://". $field_value;
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
            $this->setValue("lnk_timestamp_create", date("Y-m-d H:i:s", time()));
            $this->setValue("lnk_usr_id_create", $g_current_user->getValue("usr_id"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(strtotime($this->getValue("lnk_timestamp_change")) > (strtotime($this->getValue("lnk_timestamp_create")) + 900)
            || $this->getValue("lnk_usr_id_change") != $this->getValue("lnk_usr_id_create") )
            {
                $this->setValue("lnk_timestamp_change", date("Y-m-d H:i:s", time()));
                $this->setValue("lnk_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
    }   
}
?>