<?php
/******************************************************************************
 * The class creates a member object that manages the membership of roles and
 * the access to database table adm_members
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * startMembership($leader = "")
 *                      - starts a membership for the assigned role and user 
 *                        from now until 31.12.9999
 * stopMembership()  	- stops a membership now for the assigned role and user
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableMembers extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_members. 
	 *  If the id is set than the specific membership will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $mem_id The recordset of the membership with this id will be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $mem_id = 0)
    {
        parent::__construct($db, TBL_MEMBERS, 'mem', $mem_id);
    }

	/** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
	 *  a new record or if only an update is neccessary. The update statement will only update
	 *  the changed columns. If the table has columns for creator or editor than these column
	 *  with their timestamp will be updated.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
	 */
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
			$this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
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
			$this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
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