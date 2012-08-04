<?php
/******************************************************************************
 * Class manages access to database table adm_guestbook_comments
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Gaestebuchkommentarobjekt zu erstellen. 
 * Eine Gaestebuchkommentar kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * moderate()       - guestbook entry will be published, if moderate mode is set
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableGuestbookComment extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_guestbook_comments. 
	 *  If the id is set than the specific guestbook comment will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $gbc_id The recordset of the guestbook comment with this id will be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $gbc_id = 0)
    {
		// read also data of assigned guestbook entry
		$this->connectAdditionalTable(TBL_GUESTBOOK, 'gbo_id', 'gbc_gbo_id');

        parent::__construct($db, TBL_GUESTBOOK_COMMENTS, 'gbc', $gbc_id);
    }
	
    public function getValue($field_name, $format = '')
    {
        if($field_name == 'gbc_text')
        {
			if(isset($this->dbColumns['gbc_text']) == false)
			{
				$value = '';
			}
			elseif($format == 'plain')
			{
				$value = html_entity_decode(strStripTags($this->dbColumns['gbc_text']));
			}
			else
			{
				$value = $this->dbColumns['gbc_text'];
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
        $this->setValue('gbc_locked', '0');
        $this->save();
    }  
	
    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;
        
        if($this->new_record)
        {
            $this->setValue('gbc_org_id', $gCurrentOrganization->getValue('org_id'));
            $this->setValue('gbc_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        parent::save($updateFingerPrint);
    }

    // validates the value and adapts it if necessary
    public function setValue($field_name, $field_value, $check_value = true)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == 'gbc_email')
            {
                $field_value = admStrToLower($field_value);
                if (!strValidCharacters($field_value, 'email'))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie nicht gesetzt
                    return false;
                }
            }
        }
		
        if($field_name == 'gbc_text')
        {
            return parent::setValue($field_name, $field_value, false);
        }
		
        return parent::setValue($field_name, $field_value);
    } 
}
?>