<?php
/******************************************************************************/
/** @class Participants
 *  @brief This class gets information about participants and leaders of dates.
 * 
 *  This function is developed, to  read the participants and leaders of dates from database.
 *  Participants and leaders can be counted or be written in an array with surname, firstname and leader-status.
 *  Also the limit of participation can to be calculated.
 */
 /******************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  Author:      : Thomas-RCV
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *******************************************************************************/

class Participants
{   
    private $rolId;                 ///< RolId of the current date of this object.
    private $leader;                ///< The number of leaders of the date in the current object
    private $count;                 ///< Counter of participants of the date in current object.
    private $order;                 ///< SQL order of results. Parameter 'ASC'/'DESC' (Deafault: 'ASC') 
    public  $memberDate;            ///< Array with surname, firstname of all participants of the date in current object.
    public 	$mDb;				    ///< db object must public because of session handling
    
    /** constructor that will initialize variables and check if $rolId is numeric 
     *  else @b FALSE will be returned. 
	 *  @param $db Database object (should be @b $gDb)
	 *  @param $rolId The Role ID of a date
     */
    public function __construct(&$db, $rolId)
    {
        $this->mDb        =& $db;
        $this->count      =  -1;
        $this->leader     =  -1;
        $this->order      =  '';
        $this->memberDate =  '';
        
        if(!is_numeric($rolId))
        {
            return FALSE;
        }
        else
        {
            $this->rolId = $rolId;
        }
    }
    
    public function __destruct ()
    {
        unset ($this);
    }
    
    /**
     *  Count participants of the date.
     *  @return Returns the result of counted participants as numeric value in current object. Leaders are not counted!
     */
    public function getCount()
    {   
        $sql = 'SELECT DISTINCT mem_usr_id, mem_leader
                FROM '.TBL_MEMBERS.'
                WHERE mem_rol_id = '.$this->rolId.'
                AND mem_end    >= \''.DATE_NOW.'\'';

        $result = $this->mDb->query($sql);
        
        // Write all member Id´s and leader status in an array
        $numParticipants = array();
        
        while ($row = $this->mDb->fetch_array($result))
        {
            $numParticipants [] = array ('member' => $row['mem_usr_id'], 'leader' => $row['mem_leader']);
        }
        
        // count the number of leaders of the date
        $leader = 0;
        foreach ($numParticipants as $member)
        {
            if($member['leader'] != 0)
            {
                $leader ++;
            }
        }
        // check if class variables $count and $leader are set to default flag.
        if($this->count == -1 && $this->leader == -1)
        {
        // Then Store the results in class variables.
        $this->count = count ($numParticipants);
        $this->leader = $leader;
        
        return $this->count - $this->leader;
        }
    }

    /**
     *  Get the limit of participants.
     *  @return Returns the limit of participants as numeric value of the current object. Leaders are not counted!
     */
    public function getLimit()
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count == -1 && $this->leader == -1)
        {
            // get data from database
            $this->getCount();
            // Store the results in class variables.
            $this->count = count ($numParticipants);
            $this->leader = $leader;
        }
        
        return $this->count - $this->leader;
    }

    /**
     *  Get the number of leaders.
     *  @return Returns the number of leaders as numeric value of the current object.
     */
    public function getNumLeaders()
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count == -1 && $this->leader == -1)
        {
            // get data from database
            $this->getCount();
            // Store the results in class variables.
            $this->count = count ($numParticipants);
            $this->leader = $leader;
        }

        return $this->leader;
    }
    
    /**
     *  Return all participants with surname,firstname and leader status as array
     *  @param $order Values ASC/DESC Default: 'ASC'
     *  @return Returns all participants in an array with fieldnames ['surname'], ['firstname'], ['leader'].
     */
    public function getParticipantsArray($order = 'ASC')
    {
        global $gProfileFields;
        
        if(!in_array($order, array('ASC', 'DESC')))
        {
            return FALSE;
        }
        else
        {
            $this->order = $order;
            
            $sql = 'SELECT DISTINCT
                        surname.usd_value as surname, firstname.usd_value as firstname, mem_leader
                    FROM '.TBL_MEMBERS.'
                        LEFT JOIN '. TBL_USER_DATA .' surname
                            ON surname.usd_usr_id = mem_usr_id
                            AND surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                        LEFT JOIN '. TBL_USER_DATA .' firstname
                            ON firstname.usd_usr_id = mem_usr_id
                            AND firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                    WHERE mem_rol_id = '.$this->rolId.'
                    ORDER BY surname '.$this->order.' ';
            
            $result = $this->mDb->query($sql);

            while ($row = $this->mDb->fetch_array($result))
            {
                $participants[] = array ('surname' => $row['surname'], 'firstname' => $row['firstname'], 'leader' => $row['mem_leader']);
            }

            $this->memberDate = $participants;
            return $this->memberDate;
        }
    }      
}      
?>