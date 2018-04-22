<?php
/**
 ***********************************************************************************************
 * Class handle role rights, cards and other things of users
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handles all the user data and the rights. This is used for the current login user and for other users of the database.
 */
class User extends TableAccess
{
    const MAX_INVALID_LOGINS = 3;

    /**
     * @var bool
     */
    protected $administrator;
    /**
     * @var ProfileFields object with current user field structure
     */
    protected $mProfileFieldsData;
    /**
     * @var array<string,bool> Array with all roles rights and the status of the current user e.g. array('rol_assign_roles'  => '0', 'rol_approve_users' => '1' ...)
     */
    protected $rolesRights  = array();
    /**
     * @var array<int,bool> Array with all roles and a flag if the user could view this role e.g. array('role_id_1' => '1', 'role_id_2' => '0' ...)
     */
    protected $listViewRights = array();
    /**
     * @var array<int,bool> Array with all roles and a flag if the user could write a mail to this role e.g. array('role_id_1' => '1', 'role_id_2' => '0' ...)
     */
    protected $listMailRights = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned
     */
    protected $rolesMembership = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned and is leader (key = role_id; value = rol_leader_rights)
     */
    protected $rolesMembershipLeader = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned and is not a leader of the role
     */
    protected $rolesMembershipNoLeader = array();
    /**
     * @var int the organization for which the rights are read, could be changed with method **setOrganization**
     */
    protected $organizationId;
    /**
     * @var bool Flag if the user has the right to assign at least one role
     */
    protected $assignRoles;
    /**
     * @var bool If this flag is set then a user can save changes to the user if he hasn't the necessary rights
     */
    protected $saveChangesWithoutRights;
    /**
     * @var array<int,bool> Array with all user ids where the current user is allowed to edit the profile.
     */
    protected $usersEditAllowed = array();
    /**
     * @var array<int,array<string,int|bool>> Array with all users to whom the current user has a relationship
     */
    protected $relationships = array();
    /**
     * @var bool Flag if relationships for this user were checked
     */
    protected $relationshipsChecked = false;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database      $database   Object of the class Database. This should be the default global object **$gDb**.
     * @param ProfileFields $userFields An object of the ProfileFields class with the profile field structure
     *                                  of the current organization. This could be the default object **$gProfileFields**.
     * @param int           $userId     The id of the user who should be loaded. If id isn't set than an empty
     *                                  object with no specific user is created.
     */
    public function __construct(Database $database, ProfileFields $userFields = null, $userId = 0)
    {
        global $gCurrentOrganization;

        if ($userFields !== null)
        {
            $this->mProfileFieldsData = clone $userFields; // create explicit a copy of the object (param is in PHP5 a reference)
        }

        $this->organizationId = (int) $gCurrentOrganization->getValue('org_id');

        parent::__construct($database, TBL_USERS, 'usr', $userId);
    }

    /**
     * Checks if the current user is allowed to view a profile field of the user of the parameter.
     * It will check if the current user could view the profile field category. Within the own profile
     * you can view profile fields of hidden categories. We will also check if the current user
     * could edit the **$user** profile so the current user could also view hidden fields.
     * @param User   $user            User object of the user that should be checked if the current user can view his profile field.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @return bool Return true if the current user is allowed to view this profile field of **$user**.
     */
    public function allowedViewProfileField(User $user, $fieldNameIntern)
    {
        return $user->mProfileFieldsData->isVisible($fieldNameIntern, $this->hasRightEditProfile($user));
    }

    /**
     * Assign the user to all roles that have set the flag **rol_default_registration**.
     * These flag should be set if you want that every new user should get this role.
     */
    public function assignDefaultRoles()
    {
        global $gMessage, $gL10n;

        $this->db->startTransaction();

        // every user will get the default roles for registration, if the current user has the right to assign roles
        // than the roles assignment dialog will be shown
        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_default_registration = 1
                   AND cat_org_id = ? -- $this->organizationId';
        $defaultRolesStatement = $this->db->queryPrepared($sql, array($this->organizationId));

        if ($defaultRolesStatement->rowCount() === 0)
        {
            $gMessage->show($gL10n->get('PRO_NO_DEFAULT_ROLE'));
            // => EXIT
        }

        while ($rolId = $defaultRolesStatement->fetchColumn())
        {
            // starts a membership for role from now
            $this->setRoleMembership($rolId);
        }

        $this->db->endTransaction();
    }

    /**
     * @param string $mode      'set' or 'edit'
     * @param int $id           Id of the role for which the membership should be set,
     *                          or id of the current membership that should be edited.
     * @param string $startDate New start date of the membership. Default will be **DATE_NOW**.
     * @param string $endDate   New end date of the membership. Default will be **31.12.9999**
     * @param bool   $leader    If set to **1** then the member will be leader of the role and
     *                          might get more rights for this role.
     * @return bool Return **true** if the membership was successfully added/edited.
     */
    private function changeRoleMembership($mode, $id, $startDate, $endDate, $leader)
    {
        if ($startDate === '' || $endDate === '')
        {
            return false;
        }

        $minStartDate = $startDate;
        $maxEndDate   = $endDate;

        if ($mode === 'set')
        {
            // subtract 1 day from start date so that we find memberships that ends yesterday
            // these memberships can be continued with new date
            $oneDayOffset = new \DateInterval('P1D');

            $startDate = \DateTime::createFromFormat('Y-m-d', $startDate)->sub($oneDayOffset)->format('Y-m-d');
            // add 1 to max date because we subtract one day if a membership ends
            if ($endDate !== DATE_MAX)
            {
                $endDate = \DateTime::createFromFormat('Y-m-d', $endDate)->add($oneDayOffset)->format('Y-m-d');
            }
        }

        $this->db->startTransaction();

        // search for membership with same role and user and overlapping dates
        if ($mode === 'set')
        {
            $member = new TableMembers($this->db);

            $sql = 'SELECT *
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $id
                       AND mem_usr_id = ? -- $this->getValue(\'usr_id\')
                       AND mem_begin <= ? -- $endDate
                       AND mem_end   >= ? -- $startDate
                  ORDER BY mem_begin ASC';
            $queryParams = array(
                $id,
                $this->getValue('usr_id'),
                $endDate,
                $startDate
            );
        }
        else
        {
            $member = new TableMembers($this->db, $id);

            $sql = 'SELECT *
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_id    <> ? -- $id
                       AND mem_rol_id = ? -- $member->getValue(\'mem_rol_id\')
                       AND mem_usr_id = ? -- $this->getValue(\'usr_id\')
                       AND mem_begin <= ? -- $endDate
                       AND mem_end   >= ? -- $startDate
                  ORDER BY mem_begin ASC';
            $queryParams = array(
                $id,
                $member->getValue('mem_rol_id'),
                $this->getValue('usr_id'),
                $endDate,
                $startDate
            );
        }
        $membershipStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($membershipStatement->rowCount() === 1)
        {
            // one record found than update this record
            $row = $membershipStatement->fetch();
            $member->setArray($row);

            // save new start date if an earlier date exists
            if (strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
            {
                $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
            }

            if ($mode === 'set')
            {
                // save new end date if an later date exists
                // but only if end date is greater than the begin date otherwise the membership should be deleted
                if (strcmp($member->getValue('mem_end', 'Y-m-d'),   $maxEndDate) > 0
                &&  strcmp($member->getValue('mem_begin', 'Y-m-d'), $maxEndDate) < 0)
                {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }
            }
            else
            {
                // save new end date if an later date exists
                if (strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0)
                {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }
            }
        }
        elseif ($membershipStatement->rowCount() > 1)
        {
            // several records found then read min and max date and delete all records
            while ($row = $membershipStatement->fetch())
            {
                $member->clear();
                $member->setArray($row);

                // save new start date if an earlier date exists
                if (strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
                {
                    $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
                }

                // save new end date if an later date exists
                if (strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0)
                {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }

                // delete existing entry because a new overlapping entry will be created
                $member->delete();
            }
            $member->clear();
        }

        if (strcmp($minStartDate, $maxEndDate) > 0)
        {
            // if start date is greater than end date than delete membership
            if ($member->getValue('mem_id') > 0)
            {
                $member->delete();
            }
            $returnStatus = true;
        }
        else
        {
            // save membership to database
            if ($mode === 'set')
            {
                $member->setValue('mem_rol_id', $id);
                $member->setValue('mem_usr_id', $this->getValue('usr_id'));
            }
            $member->setValue('mem_begin', $minStartDate);
            $member->setValue('mem_end', $maxEndDate);

            if ($leader !== null)
            {
                $member->setValue('mem_leader', $leader);
            }
            $returnStatus = $member->save();
        }

        $this->db->endTransaction();
        $this->renewRoleData();

        return $returnStatus;
    }

    /**
     * Method reads all relationships of the user and will store them in an array. Also the
     * relationship property if the user can edit the profile of the other user will be stored
     * for later checks within this class.
     * @return bool Return true if relationships could be checked.
     */
    private function checkRelationshipsRights()
    {
        global $gSettingsManager;

        if ((int) $this->getValue('usr_id') === 0 || !$gSettingsManager->getBool('members_enable_user_relations'))
        {
            return false;
        }

        if(!$this->relationshipsChecked && count($this->relationships) === 0)
        {
            // read all relations of the current user
            $sql = 'SELECT urt_id, urt_edit_user, ure_usr_id2
                      FROM '.TBL_USER_RELATIONS.'
                INNER JOIN '.TBL_USER_RELATION_TYPES.'
                        ON urt_id = ure_urt_id
                     WHERE ure_usr_id1  = ? -- $this->getValue(\'usr_id\') ';
            $queryParams = array($this->getValue('usr_id'));
            $relationsStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $relationsStatement->fetch())
            {
                $this->relationships[] = array(
                    'relation_type' => (int) $row['urt_id'],
                    'user_id'       => (int) $row['ure_usr_id2'],
                    'edit_user'     => (bool) $row['urt_edit_user']
                );
            }

            $this->relationshipsChecked = true;
        }

        return true;
    }

    /**
     * The method reads all roles where this user has a valid membership and checks the rights of
     * those roles. It stores all rights that the user get at last through one role in an array.
     * In addition the method checks which roles lists the user could see in an separate array.
     * Also an array with all roles where the user has the right to write an email will be stored.
     * The method considered the role leader rights of each role if this is set and the current
     * user is a leader in a role.
     * @param string $right The database column name of the right that should be checked. If this param
     *                      is not set then only the arrays are filled.
     * @return bool Return true if a special right should be checked and the user has this right.
     */
    public function checkRolesRight($right = null)
    {
        if ((int) $this->getValue('usr_id') === 0)
        {
            return false;
        }

        if (count($this->rolesRights) === 0)
        {
            $this->assignRoles = false;
            $tmpRolesRights = array(
                'rol_all_lists_view'     => false,
                'rol_announcements'      => false,
                'rol_approve_users'      => false,
                'rol_assign_roles'       => false,
                'rol_dates'              => false,
                'rol_download'           => false,
                'rol_edit_user'          => false,
                'rol_guestbook'          => false,
                'rol_guestbook_comments' => false,
                'rol_mail_to_all'        => false,
                'rol_photo'              => false,
                'rol_profile'            => false,
                'rol_weblinks'           => false
            );

            // read all roles of the organization and join the membership if user is member of that role
            $sql = 'SELECT *
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                 LEFT JOIN '.TBL_MEMBERS.'
                        ON mem_rol_id = rol_id
                       AND mem_usr_id = ? -- $this->getValue(\'usr_id\')
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                     WHERE rol_valid  = 1
                       AND (  cat_org_id = ? -- $this->organizationId
                           OR cat_org_id IS NULL )';
            $queryParams = array($this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
            $rolesStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $rolesStatement->fetch())
            {
                $rolId = (int) $row['rol_id'];
                $memLeader = (bool) $row['mem_leader'];

                if ($row['mem_usr_id'] > 0)
                {
                    // Sql selects all roles. Only consider roles where user is a member.
                    if ($memLeader)
                    {
                        $rolLeaderRights = (int) $row['rol_leader_rights'];

                        // if user is leader in this role than add role id and leader rights to array
                        $this->rolesMembershipLeader[$rolId] = $rolLeaderRights;

                        // if role leader could assign new members then remember this setting
                        // roles for confirmation of dates should be ignored
                        if ($row['cat_name_intern'] !== 'EVENTS'
                        && ($rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT))
                        {
                            $this->assignRoles = true;
                        }
                    }
                    else
                    {
                        $this->rolesMembershipNoLeader[] = $rolId;
                    }

                    // add role to membership array
                    $this->rolesMembership[] = $rolId;

                    // Rechte der Rollen in das Array uebertragen,
                    // falls diese noch nicht durch andere Rollen gesetzt wurden
                    foreach ($tmpRolesRights as $key => &$value)
                    {
                        if (!$value && $row[$key] == '1')
                        {
                            $value = true;
                        }
                    }
                    unset($value);

                    // set flag assignRoles of user can manage roles
                    if ((int) $row['rol_assign_roles'] === 1)
                    {
                        $this->assignRoles = true;
                    }

                    // set administrator flag
                    if ((int) $row['rol_administrator'] === 1)
                    {
                        $this->administrator = true;
                    }
                }

                // Listenansichtseinstellung merken
                // Leiter duerfen die Rolle sehen
                if ($row['mem_usr_id'] > 0 && ($row['rol_this_list_view'] > 0 || $memLeader))
                {
                    // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                    $this->listViewRights[$rolId] = true;
                }
                elseif ((int) $row['rol_this_list_view'] === 2)
                {
                    // andere Rollen anschauen, wenn jeder sie sehen darf
                    $this->listViewRights[$rolId] = true;
                }
                else
                {
                    $this->listViewRights[$rolId] = false;
                }

                // Mailrechte setzen
                // Leiter duerfen der Rolle Mails schreiben
                if ($row['mem_usr_id'] > 0 && ($row['rol_mail_this_role'] > 0 || $memLeader))
                {
                    // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                    $this->listMailRights[$rolId] = true;
                }
                elseif ($row['rol_mail_this_role'] >= 2)
                {
                    // andere Rollen anschauen, wenn jeder sie sehen darf
                    $this->listMailRights[$rolId] = true;
                }
                else
                {
                    $this->listMailRights[$rolId] = false;
                }
            }
            $this->rolesRights = $tmpRolesRights;

            // ist das Recht 'alle Listen einsehen' gesetzt, dann dies auch im Array bei allen Rollen setzen
            if ($this->rolesRights['rol_all_lists_view'])
            {
                $this->listViewRights = array_fill_keys(array_keys($this->listViewRights), true);
            }

            // ist das Recht 'allen Rollen EMails schreiben' gesetzt, dann dies auch im Array bei allen Rollen setzen
            if ($this->rolesRights['rol_mail_to_all'])
            {
                $this->listMailRights = array_fill_keys(array_keys($this->listMailRights), true);
            }
        }

        return $right === null || $this->rolesRights[$right];
    }

    /**
     * Check if a valid password is set for the user and return true if the correct password
     * was set. Optional the current session could be updated to a valid login session.
     * @param string $password             The password for the current user. This should not be encoded.
     * @param bool   $setAutoLogin         If set to true then this login will be stored in AutoLogin table
     *                                     and the user doesn't need to login another time with this browser.
     *                                     To use this functionality **$updateSessionCookies** must be set to true.
     * @param bool   $updateSessionCookies The current session will be updated to a valid login.
     *                                     If set to false then the login is only valid for the current script.
     * @param bool   $updateHash           If set to true the code will check if the current password hash uses
     *                                     the best hashing algorithm. If not the password will be rehashed with
     *                                     the new algorithm. If set to false the password will not be rehashed.
     * @param bool   $isAdministrator      If set to true check if user is admin of organization.
     * @throws AdmException in case of errors. exception->text contains a string with the reason why the login failed.
     *                     Possible reasons: SYS_LOGIN_MAX_INVALID_LOGIN
     *                                       SYS_LOGIN_NOT_ACTIVATED
     *                                       SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION
     *                                       SYS_LOGIN_USER_NO_ADMINISTRATOR
     *                                       SYS_LOGIN_USERNAME_PASSWORD_INCORRECT
     * @return true Return true if login was successful
     */
    public function checkLogin($password, $setAutoLogin = false, $updateSessionCookies = true, $updateHash = true, $isAdministrator = false)
    {
        global $gLogger, $gSettingsManager, $gCurrentSession, $gSessionId, $installedDbVersion, $gL10n;

        if ($this->hasMaxInvalidLogins())
        {
            throw new AdmException($gL10n->get('SYS_LOGIN_MAX_INVALID_LOGIN'));
        }

        if (!PasswordUtils::verify($password, $this->getValue('usr_password')))
        {
            $incorrectLoginMessage = $this->handleIncorrectPasswordLogin();

            throw new AdmException($gL10n->get($incorrectLoginMessage));
        }

        if (!$this->getValue('usr_valid'))
        {
            $gLogger->warning('AUTHENTICATION: User is not activated!', array('username' => $this->getValue('usr_login_name')));

            throw new AdmException($gL10n->get('SYS_LOGIN_NOT_ACTIVATED'));
        }

        $orgLongname = $this->getOrgLongname();

        if (!$this->isMemberOfOrganization($orgLongname))
        {
            throw new AdmException($gL10n->get('SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION', array($orgLongname)));
        }

        if ($isAdministrator && version_compare($installedDbVersion, '2.4', '>=') && !$this->isAdminOfOrganization($orgLongname))
        {
            throw new AdmException($gL10n->get('SYS_LOGIN_USER_NO_ADMINISTRATOR', array($orgLongname)));
        }

        if ($updateHash)
        {
            $this->rehashIfNecessary($password);
        }

        if ($updateSessionCookies)
        {
            $gCurrentSession->setValue('ses_usr_id', $this->getValue('usr_id'));
            $gCurrentSession->save();
        }

        // should the user stayed logged in automatically, than the cookie would expire in one year
        if ($setAutoLogin && $gSettingsManager->getBool('enable_auto_login'))
        {
            $gCurrentSession->setAutoLogin();
        }
        else
        {
            $this->setValue('usr_last_session_id', null);
        }

        if ($updateSessionCookies)
        {
            // set cookie for session id
            Session::setCookie(COOKIE_PREFIX . '_SESSION_ID', $gSessionId);

            // count logins and update login dates
            $this->saveChangesWithoutRights();
            $this->updateLoginData();
        }

        return true;
    }

    /**
     * Additional to the parent method the user profile fields and all
     * user rights and role memberships will be initialized
     * @return void
     */
    public function clear()
    {
        parent::clear();

        // new user should be valid (except registration)
        $this->setValue('usr_valid', 1);
        $this->columnsValueChanged = false;

        if ($this->mProfileFieldsData instanceof ProfileFields)
        {
            // data of all profile fields will be deleted, the internal structure will not be destroyed
            $this->mProfileFieldsData->clearUserData();
        }

        $this->administrator = false;
        $this->relationshipsChecked = false;

        // initialize rights arrays
        $this->usersEditAllowed = array();
        $this->renewRoleData();
        $this->saveChangesWithoutRights = false;
    }

    /**
     * Deletes the selected user of the table and all the many references in other tables.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        global $gCurrentUser;

        $usrId     = (int) $this->getValue('usr_id');
        $currUsrId = (int) $gCurrentUser->getValue('usr_id');

        $sqlQueries = array();

        $sqlQueries[] = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                            SET ann_usr_id_create = NULL
                          WHERE ann_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                            SET ann_usr_id_change = NULL
                          WHERE ann_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_DATES.'
                            SET dat_usr_id_create = NULL
                          WHERE dat_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_DATES.'
                            SET dat_usr_id_change = NULL
                          WHERE dat_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_FOLDERS.'
                            SET fol_usr_id = NULL
                          WHERE fol_usr_id = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_FILES.'
                            SET fil_usr_id = NULL
                          WHERE fil_usr_id = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_GUESTBOOK.'
                            SET gbo_usr_id_create = NULL
                          WHERE gbo_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_GUESTBOOK.'
                            SET gbo_usr_id_change = NULL
                          WHERE gbo_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_LINKS.'
                            SET lnk_usr_id_create = NULL
                          WHERE lnk_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_LINKS.'
                            SET lnk_usr_id_change = NULL
                          WHERE lnk_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_LISTS.'
                            SET lst_usr_id = NULL
                          WHERE lst_global = 1
                            AND lst_usr_id = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_PHOTOS.'
                            SET pho_usr_id_create = NULL
                          WHERE pho_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_PHOTOS.'
                            SET pho_usr_id_change = NULL
                          WHERE pho_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_ROLES.'
                            SET rol_usr_id_create = NULL
                          WHERE rol_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_ROLES.'
                            SET rol_usr_id_change = NULL
                          WHERE rol_usr_id_change = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_ROLE_DEPENDENCIES.'
                            SET rld_usr_id = NULL
                          WHERE rld_usr_id = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_USER_LOG.'
                            SET usl_usr_id_create = NULL
                          WHERE usl_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_USERS.'
                            SET usr_usr_id_create = NULL
                          WHERE usr_usr_id_create = '.$usrId;

        $sqlQueries[] = 'UPDATE '.TBL_USERS.'
                            SET usr_usr_id_change = NULL
                          WHERE usr_usr_id_change = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_LIST_COLUMNS.'
                          WHERE lsc_lst_id IN (SELECT lst_id
                                                 FROM '.TBL_LISTS.'
                                                WHERE lst_usr_id = '.$usrId.'
                                                  AND lst_global = 0)';

        $sqlQueries[] = 'DELETE FROM '.TBL_LISTS.'
                          WHERE lst_global = 0
                            AND lst_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_GUESTBOOK_COMMENTS.'
                          WHERE gbc_usr_id_create = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MEMBERS.'
                          WHERE mem_usr_id = '.$usrId;

        // MySQL couldn't create delete statement with same table in subquery.
        // Therefore we fill a temporary table with all ids that should be deleted and reference on this table
        $sqlQueries[] = 'DELETE FROM '.TBL_IDS.'
                          WHERE ids_usr_id = '.$currUsrId;

        $sqlQueries[] = 'INSERT INTO '.TBL_IDS.'
                                (ids_usr_id, ids_reference_id)
                         SELECT '.$currUsrId.', msc_msg_id
                           FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE msc_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE msc_msg_id IN (SELECT ids_reference_id
                                                 FROM '.TBL_IDS.'
                                                WHERE ids_usr_id = '.$currUsrId.')';

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES.'
                          WHERE msg_id IN (SELECT ids_reference_id
                                             FROM '.TBL_IDS.'
                                            WHERE ids_usr_id = '.$currUsrId.')';

        $sqlQueries[] = 'DELETE FROM '.TBL_IDS.'
                          WHERE ids_usr_id = '.$currUsrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE msc_msg_id IN (SELECT msg_id
                                                 FROM '.TBL_MESSAGES.'
                                                WHERE msg_usr_id_sender = '.$usrId.')';

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES.'
                          WHERE msg_usr_id_sender = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_REGISTRATIONS.'
                          WHERE reg_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_AUTO_LOGIN.'
                          WHERE atl_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_SESSIONS.'
                          WHERE ses_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_USER_LOG.'
                          WHERE usl_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_USER_DATA.'
                          WHERE usd_usr_id = '.$usrId;

        $this->db->startTransaction();

        foreach ($sqlQueries as $sqlQuery)
        {
            $this->db->query($sqlQuery); // TODO add more params
        }

        $returnValue = parent::delete();

        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * delete all user data of profile fields; user record will not be deleted
     * @return void
     */
    public function deleteUserFieldData()
    {
        $this->mProfileFieldsData->deleteUserData();
    }

    /**
     * Edit an existing role membership of the current user. If the new date range contains
     * a future or past membership of the same role then the two memberships will be merged.
     * In opposite to setRoleMembership this method is useful to end a membership earlier.
     * @param int    $memberId  Id of the current membership that should be edited.
     * @param string $startDate New start date of the membership. Default will be **DATE_NOW**.
     * @param string $endDate   New end date of the membership. Default will be **DATE_MAX**
     * @param bool   $leader    If set to **1** then the member will be leader of the role and
     *                          might get more rights for this role.
     * @return bool Return **true** if the membership was successfully edited.
     */
    public function editRoleMembership($memberId, $startDate = DATE_NOW, $endDate = DATE_MAX, $leader = null)
    {
        return $this->changeRoleMembership('edit', $memberId, $startDate, $endDate, $leader);
    }

    /**
     * Creates an array with all categories of one type where the user has the right to edit them
     * @param string $categoryType The type of the category that should be checked e.g. ANN, USF or DAT
     * @return array<int,int> Array with categories ids where user has the right to edit them
     */
    public function getAllEditableCategories($categoryType)
    {
        $queryParams = array($categoryType, $this->organizationId);

        if(($categoryType === 'ANN' && $this->editAnnouncements())
        || ($categoryType === 'DAT' && $this->editDates())
        || ($categoryType === 'LNK' && $this->editWeblinksRight())
        || ($categoryType === 'USF' && $this->editUsers())
        || ($categoryType === 'ROL' && $this->assignRoles()))
        {
            $condition = '';
        }
        else
        {
            $rolIdParams = array_merge(array(0), $this->getRoleMemberships());
            $queryParams = array_merge($queryParams, $rolIdParams);
            $condition = '
                AND ( EXISTS (SELECT 1
                                  FROM ' . TBL_ROLES_RIGHTS . '
                            INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . '
                                    ON rrd_ror_id = ror_id
                                 WHERE ror_name_intern = \'category_edit\'
                                   AND rrd_object_id   = cat_id
                                   AND rrd_rol_id IN ('.Database::getQmForValues($rolIdParams).') )
                    )';
        }

        $sql = 'SELECT cat_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $categoryType
                   AND (  cat_org_id IS NULL
                       OR cat_org_id = ? ) -- $this->organizationId
                       ' . $condition;
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $arrEditableCategories = array();
        while ($catId = $pdoStatement->fetchColumn())
        {
            $arrEditableCategories[] = (int) $catId;
        }

        return $arrEditableCategories;
    }

    /**
     * @param array<int,bool> $rightsList
     * @return array<int,int>
     */
    private function getAllRolesWithRight(array $rightsList)
    {
        $this->checkRolesRight();

        $visibleRoles = array();

        foreach ($rightsList as $roleId => $hasRight)
        {
            if ($hasRight)
            {
                $visibleRoles[] = $roleId;
            }
        }

        return $visibleRoles;
    }

    /**
     * Creates an array with all roles where the user has the right to mail them
     * @return array<int,int> Array with role ids where user has the right to mail them
     */
    public function getAllMailRoles()
    {
        return $this->getAllRolesWithRight($this->listMailRights);
    }

    /**
     * Creates an array with all categories of one type where the user has the right to view them
     * @param string $categoryType The type of the category that should be checked e.g. ANN, USF or DAT
     * @return array<int,int> Array with categories ids where user has the right to view them
     */
    public function getAllVisibleCategories($categoryType)
    {
        $queryParams = array($categoryType, $this->organizationId);

        if(($categoryType === 'ANN' && $this->editAnnouncements())
        || ($categoryType === 'DAT' && $this->editDates())
        || ($categoryType === 'LNK' && $this->editWeblinksRight())
        || ($categoryType === 'USF' && $this->editUsers())
        || ($categoryType === 'ROL' && $this->assignRoles()))
        {
            $condition = '';
        }
        else
        {
            $rolIdParams = array_merge(array(0), $this->getRoleMemberships());
            $queryParams = array_merge($queryParams, $rolIdParams);
            $condition = '
                AND ( EXISTS (SELECT 1
                                FROM ' . TBL_ROLES_RIGHTS . '
                          INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . '
                                  ON rrd_ror_id = ror_id
                               WHERE ror_name_intern = \'category_view\'
                                 AND rrd_object_id   = cat_id
                                 AND rrd_rol_id IN ('.Database::getQmForValues($rolIdParams).') )
                      OR NOT EXISTS (SELECT 1
                                       FROM ' . TBL_ROLES_RIGHTS . '
                                 INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . '
                                         ON rrd_ror_id = ror_id
                                      WHERE ror_name_intern = \'category_view\'
                                        AND rrd_object_id   = cat_id )
                    )';
        }

        $sql = 'SELECT cat_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $categoryType
                   AND (  cat_org_id IS NULL
                       OR cat_org_id = ? ) -- $this->organizationId
                       ' . $condition;
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $arrVisibleCategories = array();
        while ($catId = $pdoStatement->fetchColumn())
        {
            $arrVisibleCategories[] = (int) $catId;
        }

        return $arrVisibleCategories;
    }

    /**
     * Creates an array with all roles where the user has the right to view them
     * @return array<int,int> Array with role ids where user has the right to view them
     */
    public function getAllVisibleRoles()
    {
        return $this->getAllRolesWithRight($this->listViewRights);
    }

    /**
     * Returns the id of the organization this user object has been assigned.
     * This is in the default case the default organization of the config file.
     * @return int Returns the id of the organization this user object has been assigned
     */
    public function getOrganization()
    {
        return $this->organizationId;
    }

    /**
     * Gets the longname of this organization.
     * @return string Returns the longname of the organization.
     */
    private function getOrgLongname()
    {
        $sql = 'SELECT org_longname
                  FROM '.TBL_ORGANIZATIONS.'
                 WHERE org_id = ?';
        $orgStatement = $this->db->queryPrepared($sql, array($this->organizationId));

        return $orgStatement->fetchColumn();
    }

    /**
     * Returns data from the user to improve dictionary attack check
     * @return array<int,string>
     */
    public function getPasswordUserData()
    {
        $userData = array(
            // Names
            $this->getValue('FIRST_NAME'),
            $this->getValue('LAST_NAME'),
            $this->getValue('usr_login_name'),
            // Birthday
            $this->getValue('BIRTHDAY', 'Y'), // YYYY
            $this->getValue('BIRTHDAY', 'md'), // MMDD
            $this->getValue('BIRTHDAY', 'dm'), // DDMM
            // Email
            $this->getValue('EMAIL'),
            // Address
            $this->getValue('STREET'),
            $this->getValue('CITY'),
            $this->getValue('POSTCODE'),
            $this->getValue('COUNTRY')
        );

        if (!function_exists('filterEmptyStrings'))
        {
            /**
             * @param string $value
             * @return bool
             */
            function filterEmptyStrings($value)
            {
                return $value !== '';
            }
        }

        return array_filter($userData, 'filterEmptyStrings');
    }

    /**
     * Returns an array with all role ids where the user is a member.
     * @return array<int,int> Returns an array with all role ids where the user is a member.
     */
    public function getRoleMemberships()
    {
        $this->checkRolesRight();

        return $this->rolesMembership;
    }

    /**
     * Returns an array with all role ids where the user is a member and not a leader of the role.
     * @return array<int,int> Returns an array with all role ids where the user is a member and not a leader of the role.
     */
    public function getRoleMembershipsNoLeader()
    {
        $this->checkRolesRight();

        return $this->rolesMembershipNoLeader;
    }

    /**
     * Get the value of a column of the database table if the column has the praefix **usr_**
     * otherwise the value of the profile field of the table adm_user_data will be returned.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read or the internal unique profile field name
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column or the value of adm_user_fields
     *               If the value was manipulated before with **setValue** than the manipulated value is returned.
     *
     * **Code example:**
     * ```
     * // reads data of adm_users column
     * $loginname = $gCurrentUser->getValue('usr_login_name');
     * // reads data of adm_user_fields
     * $email = $gCurrentUser->getValue('EMAIL');
     * ```
     */
    public function getValue($columnName, $format = '')
    {
        global $gSettingsManager;

        if (!StringUtils::strStartsWith($columnName, 'usr_'))
        {
            return $this->mProfileFieldsData->getValue($columnName, $format);
        }

        if ($columnName === 'usr_photo' && (int) $gSettingsManager->get('profile_photo_storage') === 0)
        {
            $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . (int) $this->getValue('usr_id') . '.jpg';
            if (is_file($file))
            {
                return file_get_contents($file);
            }
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * Creates a vcard with all data of this user object
     * (Windows XP address book can't process utf8, so vcard output is iso-8859-1)
     * @return string Returns the vcard as a string
     */
    public function getVCard()
    {
        global $gSettingsManager, $gCurrentUser;

        $vCard = array(
            'BEGIN:VCARD',
            'VERSION:2.1'
        );

        if ($gCurrentUser->allowedViewProfileField($this, 'FIRST_NAME'))
        {
            $vCard[] = 'N;CHARSET=ISO-8859-1:' .
                utf8_decode($this->getValue('LAST_NAME',  'database')) . ';' .
                utf8_decode($this->getValue('FIRST_NAME', 'database')) . ';;;';
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'LAST_NAME'))
        {
            $vCard[] = 'FN;CHARSET=ISO-8859-1:' .
                utf8_decode($this->getValue('FIRST_NAME')) . ' ' .
                utf8_decode($this->getValue('LAST_NAME'));
        }
        if ($this->getValue('usr_login_name') !== '')
        {
            $vCard[] = 'NICKNAME;CHARSET=ISO-8859-1:' . utf8_decode($this->getValue('usr_login_name'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'PHONE'))
        {
            $vCard[] = 'TEL;HOME;VOICE:' . $this->getValue('PHONE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'MOBILE'))
        {
            $vCard[] = 'TEL;CELL;VOICE:' . $this->getValue('MOBILE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'FAX'))
        {
            $vCard[] = 'TEL;HOME;FAX:' . $this->getValue('FAX');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'STREET')
        &&  $gCurrentUser->allowedViewProfileField($this, 'CITY')
        &&  $gCurrentUser->allowedViewProfileField($this, 'POSTCODE')
        &&  $gCurrentUser->allowedViewProfileField($this, 'COUNTRY'))
        {
            $vCard[] = 'ADR;CHARSET=ISO-8859-1;HOME:;;' .
                utf8_decode($this->getValue('STREET',  'database')) . ';' .
                utf8_decode($this->getValue('CITY',     'database')) . ';;' .
                utf8_decode($this->getValue('POSTCODE', 'database')) . ';' .
                utf8_decode($this->getValue('COUNTRY',  'database'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'WEBSITE'))
        {
            $vCard[] = 'URL;HOME:' . $this->getValue('WEBSITE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'BIRTHDAY'))
        {
            $vCard[] = 'BDAY:' . $this->getValue('BIRTHDAY', 'Ymd');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'EMAIL'))
        {
            $vCard[] = 'EMAIL;PREF;INTERNET:' . $this->getValue('EMAIL');
        }
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $this->getValue('usr_id') . '.jpg';
        if ((int) $gSettingsManager->get('profile_photo_storage') === 1 && is_file($file))
        {
            $imgHandle = fopen($file, 'rb');
            if ($imgHandle !== false)
            {
                $base64Image = base64_encode(fread($imgHandle, filesize($file)));
                fclose($imgHandle);

                $vCard[] = 'PHOTO;ENCODING=BASE64;TYPE=JPEG:' . $base64Image;
            }
        }
        if ((int) $gSettingsManager->get('profile_photo_storage') === 0 && $this->getValue('usr_photo') !== '')
        {
            $vCard[] = 'PHOTO;ENCODING=BASE64;TYPE=JPEG:' . base64_encode($this->getValue('usr_photo'));
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($gCurrentUser->allowedViewProfileField($this, 'GENDER') && $this->getValue('GENDER') > 0)
        {
            if ((int) $this->getValue('GENDER') === 1)
            {
                $xGender = 'Male';
                $xWabGender = 2;
            }
            else
            {
                $xGender = 'Female';
                $xWabGender = 1;
            }

            $vCard[] = 'X-GENDER:' . $xGender;
            $vCard[] = 'X-WAB-GENDER:' . $xWabGender;
        }
        if ($this->getValue('usr_timestamp_change') !== '')
        {
            $vCard[] = 'REV:' . $this->getValue('usr_timestamp_change', 'Ymd\This');
        }

        $vCard[] = 'END:VCARD';

        return implode("\r\n", $vCard) . "\r\n";
    }

    /**
     * returns true if a column of user table or profile fields has changed
     * @return bool
     */
    public function hasColumnsValueChanged()
    {
        return parent::hasColumnsValueChanged() || $this->mProfileFieldsData->hasColumnsValueChanged();
    }

    /**
     * Checks if the maximum of invalid logins is reached.
     * @return bool Returns true if the maximum of invalid logins is reached.
     */
    private function hasMaxInvalidLogins()
    {
        global $gLogger;

        // if within 15 minutes 3 wrong login took place -> block user account for 15 minutes
        $now = new \DateTime();
        $minutesOffset = new \DateInterval('PT15M');
        $minutesBefore = $now->sub($minutesOffset);
        $dateInvalid = \DateTime::createFromFormat('Y-m-d H:i:s', $this->getValue('usr_date_invalid', 'Y-m-d H:i:s'));

        if ($this->getValue('usr_number_invalid') < self::MAX_INVALID_LOGINS || $minutesBefore->getTimestamp() > $dateInvalid->getTimestamp())
        {
            return false;
        }

        $loggingObject = array(
            'username'      => $this->getValue('usr_login_name'),
            'numberInvalid' => (int) $this->getValue('usr_number_invalid'),
            'dateInvalid'   => $this->getValue('usr_date_invalid', 'Y-m-d H:i:s')
        );
        $gLogger->warning('AUTHENTICATION: Maximum number of invalid logins!', $loggingObject);

        $this->clear();

        return true;
    }

    /**
     * Checks if the current user is allowed to edit the profile of the user of the parameter.
     * If will check if user can generally edit all users or if he is a group leader and can edit users
     * of a special role where **$user** is a member or if it's the own profile and he could edit this.
     * @param User  $user            User object of the user that should be checked if the current user can edit his profile.
     * @param bool  $checkOwnProfile If set to **false** than this method don't check the role right to edit the own profile.
     * @return bool Return **true** if the current user is allowed to edit the profile of the user from **$user**.
     */
    public function hasRightEditProfile(User $user, $checkOwnProfile = true)
    {
        if (!$user instanceof self)
        {
            return false;
        }

        $usrId  = (int) $this->getValue('usr_id');
        $userId = (int) $user->getValue('usr_id');

        // edit own profile ?
        if ($usrId > 0 && $usrId === $userId && $checkOwnProfile && $this->checkRolesRight('rol_profile'))
        {
            return true;
        }

        // first check if user is in cache
        if (array_key_exists($userId, $this->usersEditAllowed))
        {
            return $this->usersEditAllowed[$userId];
        }

        $returnValue = false;

        if ($this->editUsers())
        {
            $returnValue = true;
        }
        else
        {
            if (count($this->rolesMembershipLeader) > 0)
            {
                // leaders are not allowed to edit profiles of other leaders but to edit their own profile
                if ($usrId === $userId)
                {
                    // check if current user is a group leader of a role where $user is only a member
                    $rolesMembership = $user->getRoleMemberships();
                }
                else
                {
                    // check if current user is a group leader of a role where $user is only a member and not a leader
                    $rolesMembership = $user->getRoleMembershipsNoLeader();
                }

                foreach ($this->rolesMembershipLeader as $roleId => $leaderRights)
                {
                    // is group leader of role and has the right to edit users ?
                    if ($leaderRights > 1 && in_array($roleId, $rolesMembership, true))
                    {
                        $returnValue = true;
                        break;
                    }
                }
            }
        }

        // check if user has a relationship to current user and is allowed to edit him
        if(!$returnValue && $this->checkRelationshipsRights())
        {
            foreach($this->relationships as $relationshipUser)
            {
                if($relationshipUser['user_id'] === $userId && $relationshipUser['edit_user'])
                {
                    $returnValue = true;
                    break;
                }
            }
        }

        // add result into cache
        $this->usersEditAllowed[$userId] = $returnValue;

        return $returnValue;
    }

    /**
     * @param array<int,bool> $rightsList
     * @param string          $rightName
     * @param int             $roleId
     * @return bool
     */
    private function hasRightRole(array $rightsList, $rightName, $roleId)
    {
        // if user has right to view all lists then he could also view this role
        if ($this->checkRolesRight($rightName))
        {
            return true;
        }

        // check if user has the right to view this role
        return array_key_exists($roleId, $rightsList) && $rightsList[$roleId];
    }

    /**
     * Checks if the current user has the right to send an email to the role.
     * @param int $roleId Id of the role that should be checked.
     * @return bool Return **true** if the user has the right to send an email to the role.
     */
    public function hasRightSendMailToRole($roleId)
    {
        return $this->hasRightRole($this->listMailRights, 'rol_mail_to_all', $roleId);
    }

    /**
     * Checks the necessary rights if this user could view former roles members. Therefore
     * the user must also have the right to view the role. So you must also check this right.
     * @param int $roleId Id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view former roles members
     */
    public function hasRightViewFormerRolesMembers($roleId)
    {
        global $gSettingsManager;

        if((int) $gSettingsManager->get('lists_show_former_members') !== 1
        && ($this->checkRolesRight('rol_assign_roles')
        || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(1, 3), true))))
        {
            return true;
        }
        elseif((int) $gSettingsManager->get('lists_show_former_members') !== 2
        && ($this->checkRolesRight('rol_edit_user')
        || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(2, 3), true))))
        {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current user is allowed to view the profile of the user of the parameter.
     * If will check if user has edit rights with method editProfile or if the user is a member
     * of a role where the current user has the right to view profiles.
     * @param User $user User object of the user that should be checked if the current user can view his profile.
     * @return bool Return **true** if the current user is allowed to view the profile of the user from **$user**.
     */
    public function hasRightViewProfile(User $user)
    {
        global $gValidLogin;

        // if user is allowed to edit the profile then he can also view it
        if ($this->hasRightEditProfile($user))
        {
            return true;
        }

        // every user is allowed to view his own profile
        if ((int) $user->getValue('usr_id') === (int) $this->getValue('usr_id') && (int) $this->getValue('usr_id') > 0)
        {
            return true;
        }

        // Benutzer, die alle Listen einsehen duerfen, koennen auch alle Profile sehen
        if ($this->checkRolesRight('rol_all_lists_view'))
        {
            return true;
        }

        $sql = 'SELECT rol_id, rol_this_list_view
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = 1
                   AND mem_usr_id = ? -- $user->getValue(\'usr_id\')
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND (  cat_org_id = ? -- $this->organizationId
                       OR cat_org_id IS NULL ) ';
        $queryParams = array($this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $listViewStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($listViewStatement->rowCount() > 0)
        {
            while ($row = $listViewStatement->fetch())
            {
                $rolId = (int) $row['rol_id'];
                $rolThisListView = (int) $row['rol_this_list_view'];

                if ($gValidLogin && $rolThisListView === 2)
                {
                    // alle angemeldeten Benutzer duerfen Rollenlisten/-profile sehen
                    return true;
                }

                if ($rolThisListView === 1 && array_key_exists($rolId, $this->listViewRights) && $this->listViewRights[$rolId])
                {
                    // nur Rollenmitglieder duerfen Rollenlisten/-profile sehen
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user of this object has the right to view the role that is set in the parameter.
     * @param int $roleId The id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view the role otherwise **false**.
     */
    public function hasRightViewRole($roleId)
    {
        return $this->hasRightRole($this->listViewRights, 'rol_all_lists_view', $roleId);
    }

    /**
     * Handles the incorrect given login password.
     * @return string Return string with the reason why the login failed.
     */
    private function handleIncorrectPasswordLogin()
    {
        global $gLogger;

        // log invalid logins
        if ($this->getValue('usr_number_invalid') >= self::MAX_INVALID_LOGINS)
        {
            $this->setValue('usr_number_invalid', 1);
        }
        else
        {
            $this->setValue('usr_number_invalid', $this->getValue('usr_number_invalid') + 1);
        }

        $this->setValue('usr_date_invalid', DATETIME_NOW);
        $this->saveChangesWithoutRights();
        $this->save(false); // don't update timestamp // TODO Exception handling

        $loggingObject = array(
            'username'      => $this->getValue('usr_login_name'),
            'numberInvalid' => (int) $this->getValue('usr_number_invalid'),
            'dateInvalid'   => $this->getValue('usr_date_invalid', 'Y-m-d H:i:s')
        );

        if ($this->getValue('usr_number_invalid') >= self::MAX_INVALID_LOGINS)
        {
            $this->clear();

            $gLogger->warning('AUTHENTICATION: Maximum number of invalid logins!', $loggingObject);

            return 'SYS_LOGIN_MAX_INVALID_LOGIN';
        }

        $this->clear();

        $gLogger->warning('AUTHENTICATION: Incorrect username/password!', $loggingObject);

        return 'SYS_LOGIN_USERNAME_PASSWORD_INCORRECT';
    }

    /**
     * Checks if the user is assigned to the role **Administrator**
     * @return bool Returns **true** if the user is a member of the role **Administrator**
     */
    public function isAdministrator()
    {
        $this->checkRolesRight();

        return $this->administrator;
    }

    /**
     * Checks if this user is an admin of this organization.
     * @param string $orgLongname The longname of this organization.
     * @return bool Return true if user is admin of this organization.
     */
    private function isAdminOfOrganization($orgLongname)
    {
        global $gLogger, $installedDbVersion;

        // Deprecated: Fallback for updates from v3.0 and v3.1
        if (version_compare($installedDbVersion, '3.2', '>='))
        {
            $administratorColumn = 'rol_administrator';
        }
        else
        {
            $administratorColumn = 'rol_webmaster';
        }

        // Check if user is currently member of a role of an organisation
        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = ? -- $this->getValue(\'usr_id\')
                   AND rol_valid  = 1
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND cat_org_id = ? -- $this->organizationId
                   AND '.$administratorColumn.' = 1';
        $queryParams = array((int) $this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($pdoStatement->rowCount() > 0)
        {
            return true;
        }

        $loggingObject = array(
            'username'     => $this->getValue('usr_login_name'),
            'organisation' => $orgLongname
        );

        $gLogger->warning('AUTHENTICATION: User is no administrator!', $loggingObject);

        return false;
    }

    /**
     * check if user is leader of a role
     * @param int $roleId
     * @return bool
     */
    public function isLeaderOfRole($roleId)
    {
        return array_key_exists($roleId, $this->rolesMembershipLeader);
    }

    /**
     * Checks if this user is a member of this organization.
     * @param string $orgLongname The longname of this organization.
     * @return bool Return true if user is member of this organization.
     */
    private function isMemberOfOrganization($orgLongname)
    {
        global $gLogger;

        // Check if user is currently member of a role of an organisation
        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = ? -- $this->getValue(\'usr_id\')
                   AND rol_valid  = 1
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND cat_org_id = ? -- $this->organizationId';
        $queryParams = array((int) $this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($pdoStatement->rowCount() > 0)
        {
            return true;
        }

        $loggingObject = array(
            'username'     => $this->getValue('usr_login_name'),
            'organisation' => $orgLongname
        );

        $gLogger->warning('AUTHENTICATION: User is not member in this organisation!', $loggingObject);

        return false;
    }

    /**
     * check if user is member of a role
     * @param int $roleId
     * @return bool
     */
    public function isMemberOfRole($roleId)
    {
        return in_array($roleId, $this->rolesMembership, true);
    }

    /**
     * If this method is called than all further calls of method **setValue** will not check the values.
     * The values will be stored in database without any inspections!
     * @return void
     */
    public function noValueCheck()
    {
        $this->mProfileFieldsData->noValueCheck();
    }

    /**
     * Reads a user record out of the table adm_users in database selected by the unique user id.
     * Also all profile fields of the object **mProfileFieldsData** will be read.
     * @param int $userId Unique id of the user that should be read
     * @return bool Returns **true** if one record is found
     */
    public function readDataById($userId)
    {
        if (parent::readDataById($userId))
        {
            // read data of all user fields from current user
            $this->mProfileFieldsData->readUserData($userId, $this->organizationId);
            return true;
        }

        return false;
    }

    /**
     * Rehashes the password of the user if necessary.
     * @param string $password The password for the current user. This should not be encoded.
     * @return bool Returns true if password was rehashed.
     */
    private function rehashIfNecessary($password)
    {
        global $gLogger;

        if (!PasswordUtils::needsRehash($this->getValue('usr_password')))
        {
            return false;
        }

        $this->saveChangesWithoutRights();
        $this->setPassword($password);
        $this->save(); // TODO Exception handling

        $gLogger->info('AUTHENTICATION: Password rehashed!', array('username' => $this->getValue('usr_login_name')));

        return true;
    }

    /**
     * Initialize all rights and role membership arrays so that all rights and
     * role memberships will be read from database if another method needs them
     * @return void
     */
    public function renewRoleData()
    {
        // initialize rights arrays
        $this->rolesRights     = array();
        $this->listViewRights  = array();
        $this->listMailRights  = array();
        $this->rolesMembership = array();
        $this->rolesMembershipLeader   = array();
        $this->rolesMembershipNoLeader = array();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's a new
     * record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * First save recordset and then save all user fields. After that the session of this got a renew for the user object.
     * If the user doesn't have the right to save data of this user than an exception will be thrown.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_changed**
     * @throws AdmException
     * @return bool
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession, $gCurrentUser;

        // if current user is not new and is not allowed to edit this user
        // and saveChangesWithoutRights isn't true than throw exception
        if (!$this->saveChangesWithoutRights && $this->getValue('usr_id') > 0 && !$gCurrentUser->hasRightEditProfile($this))
        {
            throw new AdmException('The profile data of user ' . $this->getValue('FIRST_NAME') . ' '
                . $this->getValue('LAST_NAME') . ' could not be saved because you don\'t have the right to do this.');
        }

        $this->db->startTransaction();

        // if new user then set create id
        $updateCreateUserId = false;
        if ((int) $this->getValue('usr_id') === 0 && (int) $gCurrentUser->getValue('usr_id') === 0)
        {
            $updateCreateUserId = true;
            $updateFingerPrint  = false;
        }

        // if value of a field changed then update timestamp of user object
        if ($this->mProfileFieldsData instanceof ProfileFields && $this->mProfileFieldsData->hasColumnsValueChanged())
        {
            $this->columnsValueChanged = true;
        }

        $returnValue = parent::save($updateFingerPrint);

        // if this was an registration then set this user id to create user id
        if ($updateCreateUserId)
        {
            $this->setValue('usr_timestamp_create', DATETIME_NOW);
            $this->setValue('usr_usr_id_create', $this->getValue('usr_id'));
            $returnValue = $returnValue && parent::save($updateFingerPrint);
        }

        if ($this->mProfileFieldsData instanceof ProfileFields)
        {
            // save data of all user fields
            $this->mProfileFieldsData->saveUserData((int) $this->getValue('usr_id'));
        }

        if ($this->columnsValueChanged && $gCurrentSession instanceof Session)
        {
            // now set user object in session of that user to invalid,
            // because he has new data and maybe new rights
            $gCurrentSession->renewUserObject($this->getValue('usr_id'));
        }
        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * If this method is set then a user can save changes to the user if he hasn't the necessary rights
     * @return void
     */
    public function saveChangesWithoutRights()
    {
        $this->saveChangesWithoutRights = true;
    }

    /**
     * Set the id of the organization which should be used in this user object.
     * The organization is used to read the rights of the user. If **setOrganization** isn't called
     * than the default organization **gCurrentOrganization** is set for the current user object.
     * @param int $organizationId Id of the organization
     * @return void
     */
    public function setOrganization($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->rolesRights    = array();
    }

    /**
     * Set a new value for a password column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $newPassword   The new value that should be stored in the database field
     * @param bool   $isNewPassword Should the column password or new_password be set
     * @param bool   $doHashing     Should the password get hashed before inserted. Default is true
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setPassword($newPassword, $isNewPassword = false, $doHashing = true)
    {
        global $gSettingsManager, $gPasswordHashAlgorithm;

        $columnName = 'usr_password';

        if ($isNewPassword)
        {
            $columnName = 'usr_new_password';
        }

        if (!$doHashing)
        {
            return parent::setValue($columnName, $newPassword, false);
        }

        // get the saved cost value that fits your server performance best and rehash your password
        $cost = 10;
        if (isset($gSettingsManager) && $gSettingsManager->has('system_hashing_cost'))
        {
            $cost = (int) $gSettingsManager->getInt('system_hashing_cost');
        }

        $newPasswordHash = PasswordUtils::hash($newPassword, $gPasswordHashAlgorithm, array('cost' => $cost));

        if ($newPasswordHash === false)
        {
            return false;
        }

        return parent::setValue($columnName, $newPasswordHash, false);
    }

    /**
     * Create a new membership to a role for the current user. If the date range contains
     * a future or past membership of the same role then the two memberships will be merged.
     * In opposite to setRoleMembership this method can't be used to end a membership earlier!
     * @param int    $roleId    Id of the role for which the membership should be set.
     * @param string $startDate Start date of the membership. Default will be **DATE_NOW**.
     * @param string $endDate   End date of the membership. Default will be **31.12.9999**
     * @param bool   $leader    If set to **1** then the member will be leader of the role and
     *                          might get more rights for this role.
     * @return bool Return **true** if the membership was successfully added.
     */
    public function setRoleMembership($roleId, $startDate = DATE_NOW, $endDate = DATE_MAX, $leader = null)
    {
        return $this->changeRoleMembership('set', $roleId, $startDate, $endDate, $leader);
    }

    /**
     * Set a new value for a column of the database table if the column has the prefix **usr_**
     * otherwise the value of the profile field of the table adm_user_data will set.
     * If the user log is activated than the change of the value will be logged in **adm_user_log**.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                           internal unique profile field name
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will
     *                           not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     *
     * **Code example:**
     * ```
     * // set data of adm_users column
     * $gCurrentUser->getValue('usr_login_name', 'Admidio');
     * // reads data of adm_user_fields
     * $gCurrentUser->getValue('EMAIL', 'webmaster@admidio.org');
     * ```
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gCurrentUser, $gSettingsManager;

        // users data from adm_users table
        if (StringUtils::strStartsWith($columnName, 'usr_'))
        {
            // don't change user password; use $user->setPassword()
            if ($columnName === 'usr_password' || $columnName === 'usr_new_password')
            {
                return false;
            }

            // username should not contain special characters
            if ($checkValue && $columnName === 'usr_login_name' && $newValue !== '' && !strValidCharacters($newValue, 'noSpecialChar'))
            {
                return false;
            }

            return parent::setValue($columnName, $newValue, $checkValue);
        }

        // user data from adm_user_fields table
        $oldFieldValue = $this->mProfileFieldsData->getValue($columnName, 'database');
        $newValue = (string) $newValue;

        // format of date will be local but database hase stored Y-m-d format must be changed for compare
        if($this->mProfileFieldsData->getProperty($columnName, 'usf_type') === 'DATE')
        {
            $date = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $newValue);

            if($date !== false)
            {
                $newValue = $date->format('Y-m-d');
            }
        }

        // only to a update if value has changed
        if ($oldFieldValue === $newValue)
        {
            return true;
        }

        $usrId = (int) $this->getValue('usr_id');

        $returnCode = false;

        // Disabled fields can only be edited by users with the right "edit_users" except on registration.
        // Here is no need to check hidden fields because we check on save() method that only users who
        // can edit the profile are allowed to save and change data.
        if (($usrId === 0 && (int) $gCurrentUser->getValue('usr_id') === 0)
        ||  (int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 0
        || ((int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 1
            && $gCurrentUser->hasRightEditProfile($this, false)))
        {
            $returnCode = $this->mProfileFieldsData->setValue($columnName, $newValue);
        }

        // Nicht alle Aenderungen werden geloggt. Ausnahmen:
        // Felder, die mit usr_ beginnen
        // Felder, die sich nicht geändert haben
        // Wenn usr_id ist 0 (der User neu angelegt wird; Das wird bereits dokumentiert)
        if ($returnCode && $usrId > 0 && $gSettingsManager->getBool('profile_log_edit_fields'))
        {
            $logEntry = new TableAccess($this->db, TBL_USER_LOG, 'usl');
            $logEntry->setValue('usl_usr_id', $usrId);
            $logEntry->setValue('usl_usf_id', $this->mProfileFieldsData->getProperty($columnName, 'usf_id'));
            $logEntry->setValue('usl_value_old', $oldFieldValue);
            $logEntry->setValue('usl_value_new', $newValue);
            $logEntry->setValue('usl_comm', '');
            $logEntry->save();
        }

        return $returnCode;
    }

    /**
     * set value for column usd_value of field
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should get a new value.
     * @param mixed  $fieldValue
     * @return bool
     */
    public function setProfileFieldsValue($fieldNameIntern, $fieldValue)
    {
        return $this->mProfileFieldsData->setValue($fieldNameIntern, $fieldValue);
    }

    /**
     * Update login data for this user. These are timestamps of last login and reset count
     * and timestamp of invalid logins.
     * @return void
     */
    public function updateLoginData()
    {
        $this->saveChangesWithoutRights();
        $this->setValue('usr_last_login',   $this->getValue('usr_actual_login', 'Y-m-d H:i:s'));
        $this->setValue('usr_number_login', $this->getValue('usr_number_login') + 1);
        $this->setValue('usr_actual_login', DATETIME_NOW);
        $this->setValue('usr_date_invalid', null);
        $this->setValue('usr_number_invalid', 0);
        $this->save(false); // Zeitstempel nicht aktualisieren // TODO Exception handling
    }

    /**
     * Funktion prueft, ob der angemeldete User Ankuendigungen anlegen und bearbeiten darf
     * @return bool
     */
    public function editAnnouncements()
    {
        return $this->checkRolesRight('rol_announcements');
    }

    /**
     * Funktion prueft, ob der angemeldete User Registrierungen bearbeiten und zuordnen darf
     * @return bool
     */
    public function approveUsers()
    {
        return $this->checkRolesRight('rol_approve_users');
    }

    /**
     * Checks if the user has the right to assign members to at least one role.
     * @return bool Return **true** if the user can assign members to at least one role.
     */
    public function assignRoles()
    {
        $this->checkRolesRight();

        return $this->assignRoles;
    }

    /**
     * Checks if the user has the right to manage roles. Therefore he must be a member
     * of a role with the right **rol_manage_roles**.
     * @return bool Return **true** if the user can manage roles.
     */
    public function manageRoles()
    {
        return $this->checkRolesRight('rol_assign_roles');
    }

    /**
     * Funktion prueft, ob der angemeldete User Termine anlegen und bearbeiten darf
     * @return bool
     */
    public function editDates()
    {
        return $this->checkRolesRight('rol_dates');
    }

    /**
     * Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf
     * @return bool
     */
    public function editDownloadRight()
    {
        return $this->checkRolesRight('rol_download');
    }

    /**
     * Funktion prueft, ob der angemeldete User fremde Benutzerdaten bearbeiten darf
     * @return bool
     */
    public function editUsers()
    {
        return $this->checkRolesRight('rol_edit_user');
    }

    /**
     * Funktion prueft, ob der angemeldete User Gaestebucheintraege loeschen und editieren darf
     * @return bool
     */
    public function editGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook');
    }

    /**
     * Funktion prueft, ob der angemeldete User Gaestebucheintraege kommentieren darf
     * @return bool
     */
    public function commentGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook_comments');
    }

    /**
     * Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf
     * @return bool
     */
    public function editPhotoRight()
    {
        return $this->checkRolesRight('rol_photo');
    }

    /**
     * Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
     * @return bool
     */
    public function editWeblinksRight()
    {
        return $this->checkRolesRight('rol_weblinks');
    }
}
