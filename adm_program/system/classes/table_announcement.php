<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_announcements
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen. 
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getDescription($type = 'HTML') - liefert die Beschreibung je nach Type zurueck
 *                 type = 'PLAIN'  : reiner Text ohne Html oder BBCode
 *                 type = 'HTML'   : BB-Code in HTML umgewandelt
 *                 type = 'BBCODE' : Beschreibung mit BBCode-Tags
 * editRight()       - prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableAnnouncement extends TableAccess
{
    protected $bbCode;

    // Konstruktor
    public function __construct(&$db, $ann_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ANNOUNCEMENTS;
        $this->column_praefix = 'ann';
        
        if($ann_id > 0)
        {
            $this->readData($ann_id);
        }
        else
        {
            $this->clear();
        }
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

            $description = $this->bbCode->parse($this->getValue('ann_description'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('ann_description'));
        }
        return $description;
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue('ann_timestamp_create', DATETIME_NOW);
            $this->setValue('ann_usr_id_create', $g_current_user->getValue('usr_id'));
            $this->setValue('ann_org_shortname', $g_current_organization->getValue('org_shortname'));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('ann_timestamp_create')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('ann_usr_id_create') )
            {
                $this->setValue('ann_timestamp_change', DATETIME_NOW);
                $this->setValue('ann_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }
        parent::save();
    }
    
    // prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
    public function editRight()
    {
        global $g_current_organization;
        
        // Ankuendigung der eigenen Orga darf bearbeitet werden
        if($this->getValue('ann_org_shortname') == $g_current_organization->getValue('org_shortname'))
        {
            return true;
        }
        // Ankuendigung von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->getValue('ann_global') == true
        && $g_current_organization->isChildOrganization($this->getValue('ann_org_shortname')))
        {
            return true;
        }
    
        return false;
    }
}
?>