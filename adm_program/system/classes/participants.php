<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Participants
 * @brief This class gets information about participants and leaders of dates.
 *
 * This function is developed, to  read the participants and leaders of dates from database.
 * Participants and leaders can be counted or be written in an array with surname, firstname and leader-status.
 * Also the limit of participation can to be calculated.
 * This class is flexible in usage. It is possible to pass the paramter of the rolId when creating an instance
 * of this object. The Id will be checked and stored in the object. So no parameters are required when calling
 * a function.
 * Second possibility is to pass the Id to a function of this object. The stored value in current object will be overwritten.
 * This is recommended looping an array, for example, with various Id´s.
 */
class Participants
{
    private $rolId;                 ///< RolId of the current date of this object.
    private $leader;                ///< The number of leaders of the date in the current object
    private $count;                 ///< Counter of participants of the date in current object.
    private $order;                 ///< SQL order of results. Parameter 'ASC'/'DESC' (Default: 'ASC')
    public $memberDate;             ///< Array with surname, firstname of all participants of the date in current object.
    public $mDb;                    ///< db object must public because of session handling

    /**
     * Constructor that will initialize variables and check if $rolId is numeric
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $rolId    The role ID of a date
     */
    public function __construct(&$database, $rolId = 0)
    {
        $this->mDb =& $database;
        $this->clear();
        $this->checkId($rolId);
    }

    /**
     * This function checks the passed Id if it is numeric and compares it to the current object variable
     * If the values are different the current object variable will be updated with the new value
     * @param int $roleId
     * @return bool
     */
    private function checkId($roleId)
    {
        // check passed parameter and compare to current object
        if($this->rolId === -1 || ($this->rolId === 0 && $this->rolId !== $roleId))
        {
            $this->rolId = $roleId;
            return true;
        }

        return false;
    }

    /**
     * Initialize all parameters of the object
     */
    public function clear()
    {
        $this->count      = -1;
        $this->leader     = -1;
        $this->rolId      = -1;
        $this->order      = '';
        $this->memberDate = '';
    }

    /**
     * Count participants of the date.
     * @param int $rolId
     * @return int Returns the result of counted participants as numeric value in current object. Leaders are not counted!
     */
    public function getCount($rolId = 0)
    {
        if($rolId !== 0)
        {
            $this->clear();
            $this->checkId($rolId);
        }

        $sql = 'SELECT DISTINCT mem_usr_id, mem_leader
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = '.$this->rolId.'
                   AND mem_end   >= \''.DATE_NOW.'\'';
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
                ++$leader;
            }
        }
        // check if class variables $count and $leader are set to default flag.
        if($this->count === -1 && $this->leader === -1)
        {
            // Then Store the results in class variables.
            $this->count = count($numParticipants);
            $this->leader = $leader;
        }

        return $this->count - $this->leader;
    }

    /**
     * Get the limit of participants.
     * @param int $rolId
     * @return int Returns the limit of participants as numeric value of the current object. Leaders are not counted!
     */
    public function getLimit($rolId = 0)
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count === -1 && $this->leader === -1)
        {
            // get data from database
            $this->getCount($rolId);
        }

        return $this->count - $this->leader;
    }

    /**
     * Get the number of leaders.
     * @param int $rolId
     * @return int Returns the number of leaders as numeric value of the current object.
     */
    public function getNumLeaders($rolId = 0)
    {
        // check if class variables $count and $leader are set to default flag.
        if($this->count === -1 && $this->leader === -1)
        {
            // get data from database
            $this->getCount($rolId);
        }

        return $this->leader;
    }

    /**
     * Return all participants with surname,firstname and leader status as array
     * @param int    $roleId
     * @param string $order Values ASC/DESC Default: 'ASC'
     * @return array|false Returns all participants in an array with fieldnames ['surname'], ['firstname'], ['leader'].
     */
    public function getParticipantsArray($roleId = 0, $order = 'ASC')
    {
        global $gProfileFields;

        if (!in_array($order, array('ASC', 'DESC'), true))
        {
            return false;
        }

        $this->checkId($roleId);

        $this->order = $order;

        $sql = 'SELECT DISTINCT
                       surname.usd_value AS surname, firstname.usd_value AS firstname, mem_leader
                  FROM '.TBL_MEMBERS.'
             LEFT JOIN '. TBL_USER_DATA .' surname
                    ON surname.usd_usr_id = mem_usr_id
                   AND surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
             LEFT JOIN '. TBL_USER_DATA .' firstname
                    ON firstname.usd_usr_id = mem_usr_id
                   AND firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                 WHERE mem_rol_id = '.$this->rolId.'
              ORDER BY surname '.$this->order;
        $membersStatement = $this->mDb->query($sql);

        $participants = array();
        while ($row = $membersStatement->fetch())
        {
            $participants[] = array(
                'surname'   => $row['surname'],
                'firstname' => $row['firstname'],
                'leader'    => $row['mem_leader']
            );
        }
        $this->memberDate = $participants;

        return $this->memberDate;
    }
}
