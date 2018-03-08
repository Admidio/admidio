<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class gets information about participants and leaders of dates.
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
    /**
     * @var int Counter of participants of the date in current object.
     */
    private $count = -1;
    /**
     * @var int The number of leaders of the date in the current object
     */
    private $leader = -1;
    /**
     * @var int RolId of the current date of this object.
     */
    private $rolId = -1;
    /**
     * @var string SQL order of results. Parameter 'ASC'/'DESC' (Default: 'ASC')
     */
    private $order = '';
    /**
     * @var array<int,array<string,string,int,bool>> Array with surname, firstname of all participants of the date in current object.
     */
    private $memberDate = array();
    /**
     * @var Database db object must public because of session handling
     */
    private $db;

    /**
     * Constructor that will initialize variables and check if $rolId is numeric
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $rolId    The role ID of a date
     */
    public function __construct(Database $database, $rolId = 0)
    {
        $this->db =& $database;
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
        $this->memberDate = array();
    }

    /**
     * Count participants of the date.
     * @param int $rolId
     * @return int Returns the result of count participants as numeric value in current object. Leaders are not counted!
     */
    public function getCount($rolId = 0)
    {
        if ($rolId !== 0)
        {
            $this->clear();
            $this->checkId($rolId);
        }

        $sql = 'SELECT DISTINCT mem_usr_id, mem_leader, mem_approved, mem_count_guests
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $this->rolId
                   AND mem_end   >= ? -- DATE_NOW
                   AND (mem_approved IS NULL
                            OR mem_approved < 3)';

        $membersStatement = $this->db->queryPrepared($sql, array($this->rolId, DATE_NOW));

        // Write all member Id´s and leader status in an array
        $numParticipants = array();

        while ($row = $membersStatement->fetch())
        {
            $numParticipants[] = array(
                'member'            => (int) $row['mem_usr_id'],
                'leader'            => (bool) $row['mem_leader'],
                'count_guests'      => (int) $row['mem_count_guests']
            );
        }

        // count total number of participants and leaders of the date
        $leader = 0;
        $totalCount = 0;
        foreach ($numParticipants as $member)
        {
            if($member['leader'])
            {
                ++$leader;
            }

            $totalCount = $totalCount + $member['count_guests'] + 1;
        }
        // check if class variables $count and $leader are set to default flag.
        if($this->count === -1 && $this->leader === -1)
        {
            // Then store the results in class variables.
            $this->count = $totalCount;
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
     * Return all participants with surname,firstname, leader and approval status as array
     * @param int    $roleId
     * @param string $order Values ASC/DESC Default: 'ASC'
     * @return false|array<int,array<string,string|int|bool>> Returns all participants in an array with fieldnames ['usrId'], ['surname'], ['firstname'], ['leader'], ['approved'].
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
                       surname.usd_value AS surname, firstname.usd_value AS firstname, mem_leader, mem_usr_id, mem_approved
                  FROM '.TBL_MEMBERS.'
             LEFT JOIN '.TBL_USER_DATA.' AS surname
                    ON surname.usd_usr_id = mem_usr_id
                   AND surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
             LEFT JOIN '.TBL_USER_DATA.' AS firstname
                    ON firstname.usd_usr_id = mem_usr_id
                   AND firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                 WHERE mem_rol_id = ? -- $this->rolId
              ORDER BY surname '.$this->order;
        $queryParams = array(
            (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $this->rolId
        );
        $membersStatement = $this->db->queryPrepared($sql, $queryParams);

        $participants = array();
        while ($row = $membersStatement->fetch())
        {
            $participants[(int) $row['mem_usr_id']] = array(
                'usrId'     => (int) $row['mem_usr_id'],
                'surname'   => $row['surname'],
                'firstname' => $row['firstname'],
                'leader'    => (bool) $row['mem_leader'],
                'approved'  => (int) $row['mem_approved']
            );
        }
        $this->memberDate = $participants;

        return $this->memberDate;
    }

    /**
     * Look for an user ID exists in the current participants array. If the user Id exists the check the approval state of the user. If not disagreed ( Integer 3 ) User is member of the event role
     * @param int    $userId
     * @return bool Returns true if userID is found and approval state is not set to disagreement (value: 3)
     */
    public function isMemberOfEvent($userId)
    {
        // Read participants of current event role
        $eventMember = $this->getParticipantsArray($this->rolId);
        // Search for user in array
        foreach($eventMember as $participant)
        {
            if ($participant['usrId'] === (int) $userId)
            {
                // is member of the event
                if ($participant['usrId']['approved'] !== 3)
                {
                    return true;
                }
            }
        }
        return false;
    }
}
