<?php
/******************************************************************************
 * The class creates a member object that manages the membership of roles and
 * the access to database table adm_members
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getMembership($roleId, $userId)
 *                      - search for an existing membership of roleId and userId
 *                        and fill the class with the result
 * startMembership($leader = "")
 *                      - starts a membership for the assigned role and user 
 *                        from now until 31.12.9999
 * stopMembership()  	- stops a membership now for the assigned role and user
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableMembers extends TableAccess
{
    // Constructor
    public function __construct(&$db, $mem_id = 0)
    {
        parent::__construct($db, TBL_MEMBERS, 'mem', $mem_id);
    }
	
	// search for an existing membership of roleId and userId
	// and fill the class with the result
	public function getMembership($roleId, $userId)
	{
		if(is_numeric($roleId) && is_numeric($userId))
		{
			$this->clear();
			
			$sql = 'SELECT * FROM '.TBL_MEMBERS.'
			         WHERE mem_rol_id = '.$roleId.'
					   AND mem_usr_id = '.$userId.'
					   AND mem_begin <= \''.DATE_NOW.'\'
					   AND mem_end   >= \''.DATE_NOW.'\'';
			$this->db->query($sql);
			
			if($row = $this->db->fetch_array())
			{
				$this->setArray($row);
			
				return true;
			}
			else
			{
				$this->setValue('mem_rol_id', $roleId);
				$this->setValue('mem_usr_id', $userId);
			}
		}
		return false;
	}

    // Speichert die Mitgliedschaft und aktualisiert das
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;
        $fields_changed = $this->columnsValueChanged;
        
        parent::save($updateFingerPrint);
        
        if($fields_changed && is_object($gCurrentSession))
        {
            // einlesen des entsprechenden Userobjekts, da Aenderungen 
            // bei den Rollen vorgenommen wurden 
            $gCurrentSession->renewUserObject($this->getValue('mem_usr_id'));
        }
    } 
    
	// starts a membership for the assigned role and user from now until 31.12.9999
    public function startMembership($roleId = 0, $userId = 0, $leader = '')
    {
		// if role and user is set, than search for this membership and load data into class
		if(is_numeric($roleId) && is_numeric($userId) && $roleId > 0 && $userId > 0)
		{
			$this->getMembership($roleId, $userId);
		}
		
		if($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0)
		{
			// Beginn nicht ueberschreiben, wenn schon existiert
			if(strcmp($this->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0
			|| $this->new_record)
			{
				$this->setValue('mem_begin', DATE_NOW);
			}

			// Leiter sollte nicht ueberschrieben werden, wenn nicht uebergeben wird
			if(strlen($leader) == 0)
			{
				if($this->new_record == true)
				{
					$this->setValue('mem_leader', 0);
				}
			}
			else
			{
				$this->setValue('mem_leader', $leader);
			}

			$this->setValue('mem_end', '9999-12-31');
			
			if($this->columnsValueChanged)
			{
				$this->save();
				return true;
			}
		}
        return false;
    }

	// stops a membership now for the assigned role and user
    public function stopMembership($roleId = 0, $userId = 0)
    {
		// if role and user is set, than search for this membership and load data into class
		if(is_numeric($roleId) && is_numeric($userId) && $roleId > 0 && $userId > 0)
		{
			$this->getMembership($roleId, $userId);
		}

        if($this->new_record == false && $this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0)
        {
			// subtract one day, so that user leaves role immediately
            $newEndDate = date('Y-m-d', time() - (24 * 60 * 60));

            // only stop membership if there is an actual membership
			// the actual date must be after the beginning 
			// and the actual date must be before the end date
            if(strcmp(date('Y-m-d', time()), $this->getValue('mem_begin', 'Y-m-d')) >= 0
            && strcmp($this->getValue('mem_end', 'Y-m-d'), $newEndDate) >= 0)
            {
				// if start date is greater than end date than delete membership
				if(strcmp($this->getValue('mem_begin', 'Y-m-d'), $newEndDate) >= 0)
				{
					$this->delete();
					$this->clear();
				}
				else
				{
					$this->setValue('mem_end', $newEndDate);
				
					// stop leader
					if($this->getValue('mem_leader')==1)
					{
						$this->setValue('mem_leader', 0);
					}
					
					$this->save();
				}
                return true;
            }
        }
        return false;
    }
}
?>