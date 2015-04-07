<?php
/******************************************************************************
 * Class manages access to database table adm_messages
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * This class manages the set, update and delete in the table adm_messages 
 *
 *****************************************************************************/ 

class TableMessage extends TableAccess
{
	protected $msg_converation_id;
	
	/** Constuctor that will create an object of a recordset of the table adm_messages. 
	 *  If the id is set than the specific message will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $msg_converation_id The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $msg_converation_id = 0)
    {
		$sql = "SELECT msg_id FROM ". TBL_MESSAGES. "
         WHERE msg_part_id = 0 and msg_converation_id = ". $msg_converation_id;
		$result = $db->query($sql);
		$row = $db->fetch_array($result);
		
		$this->msg_id     = $row['msg_id'];
		$this->msg_converation_id = $msg_converation_id;
		
        parent::__construct($db, TBL_MESSAGES, 'msg', $this->msg_id);
    }

	/** Reads the number of all records of this table
	 *  @return Number of records of this table
	 */
    public function countUnreadMessageRecords($usr_id)
    {
		$sql = 'SELECT COUNT(1) as count FROM '.$this->tableName.' WHERE msg_part_id = 0 and msg_usr_id_receiver = '. $usr_id .' and msg_read = 1';
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['count'];
    }
	
    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue The new value that should be stored in the database field
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setReadValue($usr_id)
    {
		$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = '0' 
            WHERE msg_id = ".$this->msg_id." and msg_usr_id_receiver = ".$usr_id;
        $return = $this->db->query($sql);

		return $return;
    }
	
    /** Deletes the selected message with all associated fields. 
	 *  After that the class will be initialize.
	 *  @return @b true if no error occurred
	 */
    public function delete($usr_id)
    {
		if($this->getValue('msg_read') == 2)
		{
			$sql = "DELETE FROM ".TBL_MESSAGES."
			 WHERE msg_converation_id = ". $this->msg_converation_id;
			$this->db->query($sql);
		}
		
		$other = $this->getValue('msg_usr_id_sender');
		if($other == $usr_id)
        {
			$other = $this->getValue('msg_usr_id_receiver');
		}
		
		$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = 2, msg_timestamp = CURRENT_TIMESTAMP, msg_usr_id_sender = ".$usr_id.", msg_usr_id_receiver = ".$other."
         WHERE msg_part_id = 0 and msg_converation_id = ".$this->msg_converation_id;
		$this->db->query($sql);

		$sql = "DELETE FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'EMAIL' and msg_converation_id = ". $this->msg_converation_id ." and (msg_usr_id_sender = ". $usr_id ."
		 or msg_usr_id_receiver = ". $usr_id ." )";
        $this->db->query($sql);

    } 
}
?>
