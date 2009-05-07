<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_links
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Linkobjekt zu erstellen. 
 * Eine Weblink kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getDescriptionWithBBCode() 
 *                   - liefert die Beschreibung mit dem originalen BBCode zurueck
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableWeblink extends TableAccess
{
    var $bbCode;

    // Konstruktor
    function TableWeblink(&$db, $lnk_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_LINKS;
        $this->column_praefix = 'lnk';
        
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
        $condition = '     lnk_id     = '.$lnk_id.' 
                       AND lnk_cat_id = cat_id
                       AND cat_org_id = '. $g_current_organization->getValue('org_id');
        parent::readData($lnk_id, $condition, $tables);
    }
    
    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    function setValue($field_name, $field_value)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == 'lnk_url')
            {
                // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
                if (substr($field_value, 0, 7) != 'http://' && substr($field_value, 0, 8) != 'https://' )
                {
                    $field_value = 'http://'. $field_value;
                }
            }
        }
        parent::setValue($field_name, $field_value);
    }

    // liefert die Beschreibung mit dem originalen BBCode zurueck
    // das einfache getValue liefert den geparsten BBCode in HTML zurueck
    function getDescriptionWithBBCode()
    {
        return parent::getValue('lnk_description');
    }

    function getValue($field_name)
    {
        global $g_preferences;
    
        // innerhalb dieser Methode kein getValue nutzen, da sonst eine Endlosschleife erzeugt wird !!!
        $value = $this->dbColumns[$field_name];

        if($field_name == 'lnk_description')
        {
            // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
            if($g_preferences['enable_bbcode'] == 1)
            {
                if(is_object($this->bbCode) == false)
                {
                    $this->bbCode = new ubbParser();
                }            
                return $this->bbCode->parse(parent::getValue($field_name, $value));
            }
            else
            {
                return nl2br(parent::getValue($field_name, $value));
            }        
        }
        return parent::getValue($field_name, $value);
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    function save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue('lnk_timestamp_create', DATETIME_NOW);
            $this->setValue('lnk_usr_id_create', $g_current_user->getValue('usr_id'));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('lnk_timestamp_create')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('lnk_usr_id_create') )
            {
                $this->setValue('lnk_timestamp_change', DATETIME_NOW);
                $this->setValue('lnk_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }
        parent::save();
    }   
}
?>