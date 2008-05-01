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
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $link = new Weblink($g_db);
 *
 * Mit der Funktion getWeblink($lnk_id) kann nun der gewuenschte Link ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array 
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Link wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Der aktuelle Link wird aus der Datenbank geloescht
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

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
            $this->getWeblink($lnk_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function getWeblink($lnk_id)
    {
        global $g_current_organization;
        
        $tables    = TBL_CATEGORIES;
        $condition = "     lnk_id     = $lnk_id 
                       AND lnk_cat_id = cat_id
                       AND cat_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($lnk_id, $condition, $tables);
    }
    
    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Methode wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
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
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("lnk_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("lnk_usr_id", $g_current_user->getValue("usr_id"));
        }
        else
        {
            $this->setValue("lnk_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("lnk_usr_id_change", $g_current_user->getValue("usr_id"));
        }
    }   
}
?>