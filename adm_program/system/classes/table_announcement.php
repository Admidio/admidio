<?php
/******************************************************************************
 * Class manages access to database table adm_announcements
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen. 
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * editRight()       - prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableAnnouncement extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $ann_id = 0)
    {
        parent::__construct($db, TBL_ANNOUNCEMENTS, 'ann', $ann_id);
    }
    
    // prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
    public function editRight()
    {
        global $gCurrentOrganization;
        
        // Ankuendigung der eigenen Orga darf bearbeitet werden
        if($this->getValue('ann_org_shortname') == $gCurrentOrganization->getValue('org_shortname'))
        {
            return true;
        }
        // Ankuendigung von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->getValue('ann_global') == true
        && $gCurrentOrganization->isChildOrganization($this->getValue('ann_org_shortname')))
        {
            return true;
        }
    
        return false;
    }
    
    public function getValue($field_name, $format = '')
    {
        if($field_name == 'ann_description')
        {
			if(isset($this->dbColumns['ann_description']) == false)
			{
				$value = '';
			}

			elseif($format == 'plain')
			{
				$value = html_entity_decode(strStripTags($this->dbColumns['ann_description']), ENT_QUOTES, 'UTF-8');
			}
			else
			{
				$value = $this->dbColumns['ann_description'];
			}
        }
        else
        {
            $value = parent::getValue($field_name, $format);
        }

        return $value;
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;
        
        if($this->new_record)
        {
            $this->setValue('ann_org_shortname', $gCurrentOrganization->getValue('org_shortname'));
        }

        parent::save($updateFingerPrint);
    }
    
    public function setValue($field_name, $field_value, $check_value = true)
    {
        if($field_name == 'ann_description')
        {
            return parent::setValue($field_name, $field_value, false);
        }
        return parent::setValue($field_name, $field_value);
    }
}
?>