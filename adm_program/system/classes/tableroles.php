<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_roles
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableRoles
 * Diese Klasse dient dazu einen Rollenobjekt zu erstellen.
 * Eine Rolle kann ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Informationen der Rolle sowie der zugehoerigen Kategorie
 * ausgelesen. Geschrieben werden aber nur die Rollendaten
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * allowedToAssignMembers - checks if user is allowed to assign members to this role
 *                          requires userObject of user for this should be checked
 * allowedToEditMembers - checks if user is allowed to edit members of this role
 *                        requires userObject of user for this should be checked
 * countVacancies($count_leaders = false) - gibt die freien Plaetze der Rolle zurueck
 *                    dies ist interessant, wenn rol_max_members gesetzt wurde
 * hasFormerMembers() - Methode gibt true zurueck, wenn die Rolle ehemalige Mitglieder besitzt
 * setInactive()    - setzt die Rolle auf inaktiv
 * setActive()      - setzt die Rolle wieder auf aktiv
 * viewRole()       - diese Methode basiert auf viewRole des Usersobjekts, geht aber noch weiter
 *                    und prueft auch Rollen zu Terminen (hier muss man nicht Mitglied der Rolle
 *                    sein, sondern nur in einer Rolle sein, die den Termin sehen darf)
 */
class TableRoles extends TableAccess
{
    protected $countLeaders;    ///< number of leaders of this role
    protected $countMembers;    ///< number of members (without leaders) of this role

    /**
     * Constructor that will create an object of a recordset of the table adm_roles.
     * If the id is set than the specific role will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $rolId    The recordset of the role with this id will be loaded.
     *                            If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $rolId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'rol_cat_id');

        parent::__construct($database, TBL_ROLES, 'rol', $rolId);
    }

    /**
     * checks if user is allowed to assign members to this role
     * @param \User $user UserObject of user who should be checked
     * @return bool
     */
    public function allowedToAssignMembers(User $user)
    {
        // you aren't allowed to change membership of not active roles
        if ((int) $this->getValue('rol_valid') === 0)
        {
            return false;
        }

        if ($user->manageRoles())
        {
            // only administrators are allowed to assign new members to administrator role
            if ((int) $this->getValue('rol_administrator') === 0 || $user->isAdministrator())
            {
                return true;
            }
        }
        else
        {
            $rolLeaderRights = (int) $this->getValue('rol_leader_rights');

            // leader are allowed to assign members if it's configured in the role
            if (($rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
                && $user->isLeaderOfRole((int) $this->getValue('rol_id')))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * checks if user is allowed to edit members of this role
     * @param \User $user UserObject of user who should be checked
     * @return bool
     */
    public function allowedToEditMembers(User $user)
    {
        // you aren't allowed to edit users of not active roles
        if ((int) $this->getValue('rol_valid') === 0)
        {
            return false;
        }

        if ($user->editUsers())
        {
            return true;
        }

        $rolLeaderRights = (int) $this->getValue('rol_leader_rights');

        // leader are allowed to assign members if it's configured in the role
        if (($rolLeaderRights === ROLE_LEADER_MEMBERS_EDIT || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
        && $user->isMemberOfRole((int) $this->getValue('rol_id')))
        {
            return true;
        }

        return false;
    }

    /**
     * Calls clear() Method of parent class and initialize child class specific parameters
     */
    public function clear()
    {
        parent::clear();

        // initialize class members
        $this->countLeaders = -1;
        $this->countMembers = -1;
    }

    /**
     * Method determines the number of active leaders of this role
     * @return int Returns the number of leaders of this role
     */
    public function countLeaders()
    {
        if ($this->countLeaders === -1)
        {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = '.$this->getValue('rol_id').'
                       AND mem_leader = 1
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $countMembersStatement = $this->db->query($sql);

            $this->countLeaders = (int) $countMembersStatement->fetchColumn();
        }

        return $this->countLeaders;
    }

    /**
     * Method determines the number of active members (without leaders) of this role
     * @param int $exceptUserId UserId witch shouldn't be counted
     * @return int Returns the number of members of this role
     */
    public function countMembers($exceptUserId = null)
    {
        if ($this->countMembers === -1)
        {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id  = '.$this->getValue('rol_id').'
                       AND mem_usr_id <> '.$exceptUserId.'
                       AND mem_leader  = 0
                       AND mem_begin  <= \''.DATE_NOW.'\'
                       AND mem_end     > \''.DATE_NOW.'\'';
            $countMembersStatement = $this->db->query($sql);

            $this->countMembers = (int) $countMembersStatement->fetchColumn();
        }

        return $this->countMembers;
    }

    /**
     * die Funktion gibt die Anzahl freier Plaetze zurueck
     * ist rol_max_members nicht gesetzt so wird INF zurueckgegeben
     * @param bool $countLeaders
     * @return int|float
     */
    public function countVacancies($countLeaders = false)
    {
        $rolMaxMembers = $this->getValue('rol_max_members');

        if (!is_int($rolMaxMembers))
        {
            return INF;
        }

        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = '.$this->getValue('rol_id').'
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'';
        if (!$countLeaders)
        {
            $sql .= ' AND mem_leader = 0 ';
        }
        $membersStatement = $this->db->query($sql);

        return $rolMaxMembers - $membersStatement->rowCount();
    }

    /**
     * Deletes the selected role of the table and all references in other tables.
     * After that the class will be initialize.
     * @throws AdmException
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession, $gL10n, $gCurrentOrganization;

        $rolId = (int) $this->getValue('rol_id');

        if ((int) $this->getValue('rol_default_registration') === 1)
        {
            // checks if at least one other role has this flag, if not than this role couldn't be deleted
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_default_registration = 1
                       AND rol_id    <> '.$rolId.'
                       AND cat_org_id = '.$gCurrentOrganization->getValue('org_id');
            $countRolesStatement = $this->db->query($sql);

            if ((int) $countRolesStatement->fetchColumn() === 0)
            {
                throw new AdmException('ROL_DELETE_NO_DEFAULT_ROLE', $this->getValue('rol_name'), $gL10n->get('ROL_DEFAULT_REGISTRATION'));
            }
        }

        // users are not allowed to delete system roles
        if ((int) $this->getValue('rol_system') === 1)
        {
            throw new AdmException('ROL_DELETE_SYSTEM_ROLE', $this->getValue('rol_name'));
        }
        if ((int) $this->getValue('rol_administrator') === 1)
        {
            throw new AdmException('ROL_DELETE_ROLE', $gL10n->get('SYS_ADMINISTRATOR'));
        }

        $this->db->startTransaction();

        $sqlQueries = array();

        $sqlQueries[] = 'DELETE FROM '.TBL_ROLE_DEPENDENCIES.'
                          WHERE rld_rol_id_parent = '.$rolId.'
                             OR rld_rol_id_child  = '.$rolId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MEMBERS.'
                          WHERE mem_rol_id = '.$rolId;

        $sqlQueries[] = 'UPDATE '.TBL_DATES.' SET dat_rol_id = NULL
                          WHERE dat_rol_id = '.$rolId;

        $sqlQueries[] = 'DELETE FROM '.TBL_DATE_ROLE.'
                          WHERE dtr_rol_id = '.$rolId;

        $sqlQueries[] = 'DELETE FROM '.TBL_ROLES_RIGHTS_DATA.'
                          WHERE rrd_rol_id = '.$rolId;

        foreach ($sqlQueries as $sqlQuery)
        {
            $this->db->query($sqlQuery);
        }

        $return = parent::delete();

        $this->db->endTransaction();

        if ($gCurrentSession instanceof \Session)
        {
            // all active users must renew their user data because maybe their
            // rights have been changed if they where members of this role
            $gCurrentSession->renewUserObject();
        }

        return $return;
    }

    /**
     * Returns an array with all cost periods with full name in the specific language.
     * @param int $costPeriod The number of the cost period for which the name should be returned
     *                        (-1 = unique, 1 = annually, 2 = semiyearly, 4 = quarterly, 12 = monthly)
     * @return string[]|string Array with all cost or if param costPeriod is set than the full name of that cost period
     */
    public static function getCostPeriods($costPeriod = 0)
    {
        global $gL10n;

        $costPeriods = array(
            -1 => $gL10n->get('ROL_UNIQUELY'),
            1  => $gL10n->get('ROL_ANNUALLY'),
            2  => $gL10n->get('ROL_SEMIYEARLY'),
            4  => $gL10n->get('ROL_QUARTERLY'),
            12 => $gL10n->get('ROL_MONTHLY')
        );

        if ($costPeriod !== 0)
        {
            return $costPeriods[$costPeriod];
        }

        return $costPeriods;
    }

    /**
     * Read the id of the default list of this role. The list is stored in the column @b rol_lst_id.
     * If there is no list stored then the system default list will be returned
     * @return int Returns the default list id of this role
     */
    public function getDefaultList()
    {
        global $gPreferences;

        $defaultListId = (int) $this->getValue('rol_lst_id');

        // if default list is set, return it
        if ($defaultListId > 0)
        {
            return $defaultListId;
        }

        // read system default list configuration
        return (int) $gPreferences['lists_default_configuration'];
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return int|float|string|bool Returns the value of the database column.
     *                               If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        $value = parent::getValue($columnName, $format);

        // if text is a translation-id then translate it
        if ($columnName === 'cat_name' && $format !== 'database' && strpos($value, '_') === 3)
        {
            $value = $gL10n->get(admStrToUpper($value));
        }

        return $value;
    }

    /**
     * Checks if this role has former members
     * @return bool Returns @b true if the role has former memberships
     */
    public function hasFormerMembers()
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = '.$this->getValue('rol_id').'
                   AND (  mem_begin > \''.DATE_NOW.'\'
                       OR mem_end   < \''.DATE_NOW.'\')';
        $countMembersStatement = $this->db->query($sql);

        return $countMembersStatement->fetchColumn() > 0;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;

        $fieldsChanged = $this->columnsValueChanged;

        $returnValue = parent::save($updateFingerPrint);

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if ($fieldsChanged && $gCurrentSession instanceof \Session)
        {
            // all active users must renew their user data because maybe their
            // rights have been changed if they where members of this role
            $gCurrentSession->renewUserObject();
        }

        return $returnValue;
    }

    /**
     * @param bool $status
     * @return bool
     */
    private function toggleValid($status)
    {
        global $gCurrentSession;

        // die Systemrollem sind immer aktiv
        if ((int) $this->getValue('rol_system') === 0)
        {
            $sql = 'UPDATE '.TBL_ROLES.' SET rol_valid = '.(int) $status.'
                     WHERE rol_id = '.$this->getValue('rol_id');
            $this->db->query($sql);

            // all active users must renew their user data because maybe their
            // rights have been changed if they where members of this role
            $gCurrentSession->renewUserObject();

            return true;
        }

        return false;
    }

    /**
     * aktuelle Rolle wird auf aktiv gesetzt
     * @return bool
     */
    public function setActive()
    {
        return $this->toggleValid(true);
    }

    /**
     * aktuelle Rolle wird auf inaktiv gesetzt
     * @return bool
     */
    public function setInactive()
    {
        return $this->toggleValid(false);
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method @b save to store the new value to the database.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gCurrentOrganization;

        if ($columnName === 'rol_default_registration' && $newValue == '0' && $this->dbColumns[$columnName] == '1')
        {
            // checks if at least one other role has this flag
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_default_registration = 1
                       AND rol_id    <> '.$this->getValue('rol_id').'
                       AND cat_org_id = '.$gCurrentOrganization->getValue('org_id');
            $countRolesStatement = $this->db->query($sql);

            if ((int) $countRolesStatement->fetchColumn() === 0)
            {
                return false;
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * diese Methode basiert auf viewRole des Usersobjekts, geht aber noch weiter
     * und prueft auch Rollen zu Terminen (hier muss man nicht Mitglied der Rolle
     * sein, sondern nur in einer Rolle sein, die den Termin sehen darf)
     * @return bool
     */
    public function viewRole()
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin)
        {
            return false;
        }

        if ($this->getValue('cat_name_intern') !== 'CONFIRMATION_OF_PARTICIPATION')
        {
            return $gCurrentUser->hasRightViewRole($this->getValue('rol_id'));
        }

        if ((int) $this->getValue('rol_this_list_view') === 0)
        {
            return false;
        }

        // pruefen, ob der Benutzer Mitglied einer Rolle ist, die den Termin sehen darf
        $sql = 'SELECT dtr_rol_id
                  FROM '.TBL_DATE_ROLE.'
            INNER JOIN '.TBL_DATES.'
                    ON dat_id = dtr_dat_id
                 WHERE dat_rol_id = '.$this->getValue('rol_id').'
                   AND (  dtr_rol_id IS NULL
                       OR EXISTS (SELECT 1
                                    FROM '.TBL_MEMBERS.'
                                   WHERE mem_rol_id = dtr_rol_id
                                     AND mem_usr_id = '.$gCurrentUser->getValue('usr_id').'))';
        $memberDatesStatement = $this->db->query($sql);

        return $memberDatesStatement->rowCount() > 0;
    }
}
