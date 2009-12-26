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
 * getDescription($type = 'HTML') - liefert die Beschreibung je nach Type zurueck
 *                 type = 'PLAIN'  : reiner Text ohne Html oder BBCode
 *                 type = 'HTML'   : BB-Code in HTML umgewandelt
 *                 type = 'BBCODE' : Beschreibung mit BBCode-Tags
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableWeblink extends TableAccess
{
    protected $bbCode;

    // Konstruktor
    public function __construct(&$db, $lnk_id = 0)
    {
        parent::__construct($db, TBL_LINKS, 'lnk', $lnk_id);
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($lnk_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        global $g_current_organization;
        
        $sql_additional_tables .= TBL_CATEGORIES;
        $sql_where_condition   .= '     lnk_id     = '.$lnk_id.' 
                                    AND lnk_cat_id = cat_id
                                    AND cat_org_id = '. $g_current_organization->getValue('org_id');
        parent::readData($lnk_id, $sql_where_condition, $sql_additional_tables);
    }
    
    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    public function setValue($field_name, $field_value)
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

    // liefert die Beschreibung je nach Type zurueck
    // type = 'PLAIN'  : reiner Text ohne Html oder BBCode
    // type = 'HTML'   : BB-Code in HTML umgewandelt
    // type = 'BBCODE' : Beschreibung mit BBCode-Tags
    public function getDescription($type = 'HTML')
    {
        global $g_preferences;
        $description = '';

        // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
        if($g_preferences['enable_bbcode'] == 1 && $type != 'BBCODE')
        {
            if(is_object($this->bbCode) == false)
            {
                $this->bbCode = new ubbParser();
            }

            $description = $this->bbCode->parse($this->getValue('lnk_description'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('lnk_description'));
        }
        return $description;
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save()
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