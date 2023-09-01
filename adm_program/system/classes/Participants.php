<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class gets information about participants and leaders of dates.
 *
 * This function is developed, to  read the participants and leaders of dates from database.
 * Participants and leaders can be counted or be written in an array with surname, firstname and leader-status.
 * Also, the limit of participation can to be calculated.
 * This class is flexible in usage. It is possible to pass the parameter of the rolId when creating an instance
 * of this object. The ID will be checked and stored in the object. So no parameters are required when calling
 * a function.
 * Second possibility is to pass the ID to a function of this object. The stored value in current object will be overwritten.
 * This is recommended looping an array, for example, with various ID´s.
 */
class Participants
{
    public const PARTICIPATION_UNKNOWN = 0;
    public const PARTICIPATION_MAYBE   = 1;
    public const PARTICIPATION_YES     = 2;
    public const PARTICIPATION_NO      = 3;

    /**
     * @var int Counter of participants of the date in current object.
     */
    private $count = -1;
    /**
     * @var int The number of leaders of the date in the current object
     */
    private $leader = -1;
    /**
     * @var int The number of leaders of the date in the current object
     */
    private $leaders = array();
    /**
     * @var int RolId of the current date of this object.
     */
    private $roleId;
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
     * Constructor that will initialize variables.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $roleId    The ID of the participants role of the date
     */
    public function __construct(Database $database, int $roleId = 0)
    {
        $this->db =& $database;
        $this->clear();
        $this->roleId = $roleId;
    }

    /**
     * Initialize all parameters of the object
     */
    public function clear()
    {
        $this->count      = -1;
        $this->leader     = -1;
        $this->roleId     = -1;
        $this->order      = '';
        $this->leaders    = array();
        $this->memberDate = array();
    }

    /**
     * Count participants of the date. The count will not include the leaders of the role.
     * @return int Returns the result of count participants as numeric value in current object. Leaders are not counted!
     */
    public function getCount(): int
    {
        if ($this->count === -1 && $this->leader === -1) {
            $sql = 'SELECT DISTINCT mem_usr_id, mem_leader, mem_approved, mem_count_guests
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $this->roleId
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                       AND (mem_approved IS NULL
                                OR mem_approved < 3)';

            $membersStatement = $this->db->queryPrepared($sql, array($this->roleId, DATE_NOW, DATE_NOW));

            // Write all member ID´s and leader status in an array
            $numParticipants = array();

            while ($row = $membersStatement->fetch()) {
                $numParticipants[] = array(
                    'member'            => (int) $row['mem_usr_id'],
                    'leader'            => (bool) $row['mem_leader'],
                    'count_guests'      => (int) $row['mem_count_guests']
                );
            }

            // count total number of participants and leaders of the date
            $leader = 0;
            $totalCount = 0;
            foreach ($numParticipants as $member) {
                if ($member['leader']) {
                    ++$leader;
                    $this->leaders[] = $member['member'];
                }

                $totalCount = $totalCount + $member['count_guests'] + 1;
            }

            $this->count  = $totalCount;
            $this->leader = $leader;
        }

        return $this->count - $this->leader;
    }

    /**
     * Get the number of leaders.
     * @return int Returns the number of leaders as numeric value of the current object.
     */
    public function getNumLeaders(): int
    {
        // check if the count must be determined
        if ($this->count === -1 && $this->leader === -1) {
            $this->getCount();
        }

        return $this->leader;
    }

    /**
     * Return all participants with surname,firstname, leader and approval status as array
     * @param string $order Values ASC/DESC Default: 'ASC'
     * @return false|array<int,array<string,string|int|bool>> Returns all participants in an array with field names ['usrId'], ['surname'], ['firstname'], ['leader'], ['approved'].
     */
    public function getParticipantsArray(string $order = 'ASC')
    {
        global $gProfileFields;

        if (!in_array($order, array('ASC', 'DESC'), true)) {
            return false;
        }

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
                 WHERE mem_rol_id = ? -- $this->roleId
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
              ORDER BY surname '.$this->order;
        $queryParams = array(
            (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $this->roleId, DATE_NOW, DATE_NOW
        );
        $membersStatement = $this->db->queryPrepared($sql, $queryParams);

        $participants = array();
        while ($row = $membersStatement->fetch()) {
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
     * Check if the given user is leader of this participation.
     * @param int $userId ID if the user whose participation should be checked.
     * @return bool Returns true if the user is leader of the event participation.
     */
    public function isLeader(int $userId): bool
    {
        // check if the count must be determined
        if ($this->count === -1 && $this->leader === -1) {
            $this->getCount();
        }

        return in_array($userId, $this->leaders);
    }

    /**
     * Look for a user ID exists in the current participants array. If the user ID exists the check the approval state of the user. If not disagreed ( Integer 3 ) User is member of the event role
     * @param int $userId
     * @return bool Returns true if userID is found and approval state is not set to disagreement (value: 3)
     */
    public function isMemberOfEvent(int $userId): bool
    {
        // Read participants of current event role
        $eventMember = $this->getParticipantsArray();
        // Search for user in array
        foreach ($eventMember as $participant) {
            if ($participant['usrId'] === $userId) {
                // is member of the event
                if ($participant['approved'] != self::PARTICIPATION_NO) {
                    return true;
                }
            }
        }
        return false;
    }
}
