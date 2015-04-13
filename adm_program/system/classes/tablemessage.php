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
	protected $msg_id;
	
	/** Constuctor that will create an object of a recordset of the table adm_messages. 
	 *  If the id is set than the specific message will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $msg_converation_id The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $msg_id = 0)
    {		
		$this->msg_id = $msg_id;
		
        parent::__construct($db, TBL_MESSAGES, 'msg', $this->msg_id);
    }

	/** Reads the number of all unread messages of this table
	 *  @return Number of unread messages of this table
	 */
    public function countUnreadMessageRecords($usr_id)
    {
		$sql = 'SELECT COUNT(1) as count FROM '.$this->tableName.' WHERE msg_part_id = 0 and msg_usr_id_receiver = '. $usr_id .' and msg_read = 1';
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['count'];
    }
	
	/** Reads the number of all conversations in this table
	 *  @return Number of conversations in this table
	 */
    public function countMessageConversations()
    {
		$sql = "SELECT MAX(msg_converation_id) as max_id FROM ". TBL_MESSAGES;
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['max_id'];
    }
	
	/** Reads the number of all messages in actual conversation
	 *  @return Number of all messages in actual conversation
	 */
    public function countMessageConversationParts()
    {
		$sql = "SELECT MAX(msg_part_id) as max_id FROM ".TBL_MESSAGES." 
			  where msg_converation_id = ".$this->getValue('msg_converation_id');
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['max_id'];
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
	 *  @return @b 1 if message is deleted or 0 if it is marked for other user to delete
	 */
    public function delete($usr_id)
    {
		$return = 0;
		if($this->getValue('msg_read') == 2)
		{
			$sql = "DELETE FROM ".TBL_MESSAGES."
			 WHERE msg_converation_id = ". $this->getValue('msg_converation_id');
			$this->db->query($sql);
			$return = 1;
		}
		elseif($this->getValue('msg_type') == 'EMAIL')
		{
			$sql = "DELETE FROM ". TBL_MESSAGES. "
			 WHERE msg_type = 'EMAIL' and msg_converation_id = ". $this->getValue('msg_converation_id') ." and (msg_usr_id_sender = ". $usr_id ."
			 or msg_usr_id_receiver = ". $usr_id ." )";
			$this->db->query($sql);
			$return = 1;
		}
		
		$other = $this->getValue('msg_usr_id_sender');
		if($other == $usr_id)
        {
			$other = $this->getValue('msg_usr_id_receiver');
		}
		
		$sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = 2, msg_timestamp = CURRENT_TIMESTAMP, msg_usr_id_sender = ".$usr_id.", msg_usr_id_receiver = ".$other."
         WHERE msg_part_id = 0 and msg_converation_id = ".$this->getValue('msg_converation_id');
		$this->db->query($sql);

		return $return;
    } 
}
?>
