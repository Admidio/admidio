<?php
/******************************************************************************
 * Class manages access to database table adm_guestbook
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Gaestebucheintragsobjekt zu erstellen. 
 * Eine Gaestebucheintrag kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * moderate()       - guestbook entry will be published, if moderate mode is set
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableGuestbook extends TableAccess
{
    // Constructor
    public function __construct(&$db, $gbo_id = 0)
    {
        parent::__construct($db, TBL_GUESTBOOK, 'gbo', $gbo_id);
    }
    
    // die Methode loescht den Gaestebucheintrag mit allen zugehoerigen Kommentaren
    public function delete()
    {
		$this->db->startTransaction();
		
        //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
        $sql = 'DELETE FROM '. TBL_GUESTBOOK_COMMENTS. ' WHERE gbc_gbo_id = '. $this->getValue('gbo_id');
        $result = $this->db->query($sql);
        
        $return = parent::delete();

		$this->db->endTransaction();
		return $return;
    }
    
    public function getValue($field_name, $format = '')
    {
        if($field_name == 'gbo_text')
        {
			if(isset($this->dbColumns['gbo_text']) == false)
			{
				$value = '';
			}
			elseif($format == 'plain')
			{
				$value = html_entity_decode(strStripTags($this->dbColumns['gbo_text']));
			}
			else
			{
				$value = $this->dbColumns['gbo_text'];
			}
        }
        else
        {
            $value = parent::getValue($field_name, $format);
        }
 
        return $value;
    }
    
    // guestbook entry will be published, if moderate mode is set
    function moderate()
    {
        // unlock entry
        $this->setValue('gbo_locked', '0');
        $this->save();
    }  

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;
        
        if($this->new_record)
        {
            $this->setValue('gbo_org_id', $gCurrentOrganization->getValue('org_id'));
            $this->setValue('gbo_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        parent::save($updateFingerPrint);
    }

    // validates the value and adapts it if necessary
    public function setValue($field_name, $field_value, $check_value = true)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == 'gbo_email')
            {
                $field_value = admStrToLower($field_value);
                if (!strValidCharacters($field_value, 'email'))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie nicht gesetzt
                    return false;
                }
            }
            elseif($field_name == 'gbo_homepage')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($field_value, 'url'))
                {
                    return false;
                }
                // Homepage noch mit http vorbelegen
                if(strpos(admStrToLower($field_value), 'http://')  === false
                && strpos(admStrToLower($field_value), 'https://') === false )
                {
                    $field_value = 'http://'. $field_value;
                }
            }
        }

        if($field_name == 'gbo_text')
        {
            return parent::setValue($field_name, $field_value, false);
        }

        return parent::setValue($field_name, $field_value);
    } 
}
?>