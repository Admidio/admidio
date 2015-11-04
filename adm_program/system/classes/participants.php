<?php
/******************************************************************************/
/** @class Participants
 *  @brief This class gets information about participants and leaders of dates.
 *
 *  This function is developed, to  read the participants and leaders of dates from database.
 *  Participants and leaders can be counted or be written in an array with surname, firstname and leader-status.
 *  Also the limit of participation can to be calculated.
 *  This class is flexible in usage. It is possible to pass the paramter of the rolId when creating an instance
 *  of this object. The Id will be checked and stored in the object. So no parameters are required when calling
 *  a function.
 *  Second possibility is to pass the Id to a function of this object. The stored value in current object will be overwritten.
 *  This is recommended looping an array, for example, with various Id´s.
 */
 /******************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *******************************************************************************/

class Participants
{
    private $rolId;                 ///< RolId of the current date of this object.
    private $leader;                ///< The number of leaders of the date in the current object
    private $count;                 ///< Counter of participants of the date in current object.
    private $order;                 ///< SQL order of results. Parameter 'ASC'/'DESC' (Deafault: 'ASC')
    public $memberDate;             ///< Array with surname, firstname of all participants of the date in current object.
    public $mDb;                    ///< db object must public because of session handling

    /** Constructor that will initialize variables and check if $rolId is numeric
     *  @param object $database Object of the class Database. This should be the default global object @b $gDb.
     *  @param int    $rolId    The role ID of a date
     */
    public function __construct(&$database, $rolId = 0)
    {
        $this->mDb =& $database;
        $this->clear();
        $this->checkId($rolId);
    }

    public function __destruct()
    {
        unset($this);
    }

    /**
     *  This function checks the passed Id if it is numeric and compares it to the current object variable
     *  If the values are different the current object variable will be updated with the new value
     */
    private function checkId($rolId)
    {
        // check passed parameter and compare to current object
        if(is_numeric($rolId) && $this->rolId == -1
            || is_numeric($rolId)
            && $this->rolId == 0
            && $this->rolId != $rolId)
        {
            $this->rolId = $rolId;
            return true;
        }

        else
        {
            return false;
        }
    }

    /**
     *  Initialize all parameters of the object
     */
    public function clear()
    {
        $this->count      =  -1;
        $this->leader     =  -1;
        $this->rolId      =  -1;
        $this->order      =  '';
        $this->memberDate =  '';
    }

    /**
     *  Count participants of the date.
     *  @return Returns the result of counted participants as numeric value in current object. Leaders are not counted!
     */
    public function getCount($rolId = 0)
    {
        if($rolId != 0)
        {
            $this->clear();
            $this->checkId($rolId);
        }

        $sql = 'SELECT DISTINCT mem_usr_id, mem_leader
                FROM '.TBL_MEMBERS.'
                WHERE mem_rol_id = '.$this->rolId.'
                AND mem_end    >= \''.DATE_NOW.'\'';
        $membersStatement = $this->mDb->query($sql);

        // Write all member Id´s and leader status in an array
        $numParticipants = array();

        while ($row = $membersStatement->fetch())
        {
            $numParticipants [] = array('member' => $row['mem_usr_id'], 'leader' => $row['mem_leader']);
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
        $this->count = count($numParticipants);
        $this->leader = $leader;

        return $this->count - $this->leader;
        }
    }

    /**
     *  Get the limit of participants.
     *  @return Returns the limit of participants as numeric value of the current object. Leaders are not counted!
     */
    public function getLimit($rolId = 0)
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count == -1 && $this->leader == -1)
        {
            // get data from database
            $this->getCount($rolId);
            // Store the results in class variables.
            $this->count = count($numParticipants);
            $this->leader = $leader;
        }

        return $this->count - $this->leader;
    }

    /**
     *  Get the number of leaders.
     *  @return Returns the number of leaders as numeric value of the current object.
     */
    public function getNumLeaders($rolId = 0)
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count == -1 && $this->leader == -1)
        {
            // get data from database
            $this->getCount($rolId);
            // Store the results in class variables.
            $this->count = count($numParticipants);
            $this->leader = $leader;
        }

        return $this->leader;
    }

    /**
     *  Return all participants with surname,firstname and leader status as array
     *  @param $order Values ASC/DESC Default: 'ASC'
     *  @return Returns all participants in an array with fieldnames ['surname'], ['firstname'], ['leader'].
     */
    public function getParticipantsArray($rolId = 0, $order = 'ASC')
    {
        $participants = '';
        global $gProfileFields;

        $this->checkId($rolId);

        if(!in_array($order, array('ASC', 'DESC')))
        {
            return false;
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
            $membersStatement = $this->mDb->query($sql);

            while ($row = $membersStatement->fetch())
            {
                $participants[] = array('surname' => $row['surname'], 'firstname' => $row['firstname'], 'leader' => $row['mem_leader']);
            }

            $this->memberDate = $participants;
            return $this->memberDate;
        }
    }
}
