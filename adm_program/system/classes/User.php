<?php
/**
 ***********************************************************************************************
 * Class handle role rights, cards and other things of users
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handles all the user data and the rights. This is used for the current login user and for other users of the database.
 */
class User extends TableAccess
{
    public const MAX_INVALID_LOGINS = 3;

    /**
     * @var bool
     */
    protected $administrator;
    /**
     * @var ProfileFields object with current user field structure
     */
    protected $mProfileFieldsData;
    /**
     * @var array<string,bool> Array with all roles rights and the status of the current user e.g. array('rol_assign_roles' => false, 'rol_approve_users' => true ...)
     */
    protected $rolesRights = array();
    /**
     * @var array<int,bool> Array with all roles and a flag if the user could view this role e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected $rolesViewMemberships = array();
    /**
     * @var array<int,bool> Array with all roles and a flag if the user could view profiles of the roles members e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected $rolesViewProfiles = array();
    /**
     * @var array<int,bool> Array with all roles and a flag if the user could write a mail to this role e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected $rolesWriteMails = array();
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
     * @var bool Flag if the changes to the user data should be handled by the ChangeNotification service.
     */
    protected $changeNotificationEnabled;

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
        $this->changeNotificationEnabled = true;

        if ($userFields !== null) {
            $this->mProfileFieldsData = clone $userFields; // create explicit a copy of the object (param is in PHP5 a reference)
        }

        $this->organizationId = $GLOBALS['gCurrentOrgId'];

        parent::__construct($database, TBL_USERS, 'usr', $userId);
    }

    /**
     * Checks if the current user is allowed to edit a profile field of the user of the parameter.
     * @param User   $user            User object of the user that should be checked if the current user can view his profile field.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @return bool Return true if the current user is allowed to view this profile field of **$user**.
     */
    public function allowedEditProfileField(self $user, $fieldNameIntern)
    {
        return $this->hasRightEditProfile($user) && $user->mProfileFieldsData->isEditable($fieldNameIntern, $this->hasRightEditProfile($user));
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
    public function allowedViewProfileField(self $user, string $fieldNameIntern)
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
        // than the role assignment dialog will be shown
        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_default_registration = true
                   AND cat_org_id = ? -- $this->organizationId';
        $defaultRolesStatement = $this->db->queryPrepared($sql, array($this->organizationId));

        if ($defaultRolesStatement->rowCount() === 0) {
            $gMessage->show($gL10n->get('PRO_NO_DEFAULT_ROLE'));
            // => EXIT
        }

        while ($rolId = $defaultRolesStatement->fetchColumn()) {
            // starts a membership for role from now
            $this->setRoleMembership($rolId);
        }

        $this->db->endTransaction();
    }

    /**
     * @param string $mode      'set' or 'edit'
     * @param int $id           ID of the role for which the membership should be set,
     *                          or id of the current membership that should be edited.
     * @param string $startDate New start date of the membership. Default will be **DATE_NOW**.
     * @param string $endDate   New end date of the membership. Default will be **31.12.9999**
     * @param bool   $leader    If set to **1** then the member will be leader of the role and
     *                          might get more rights for this role.
     * @return bool Return **true** if the membership was successfully added/edited.
     */
    private function changeRoleMembership($mode, $id, $startDate, $endDate, $leader)
    {
        if ($startDate === '' || $endDate === '') {
            return false;
        }

        $usrId = (int) $this->getValue('usr_id');

        $minStartDate = $startDate;
        $maxEndDate   = $endDate;

        if ($mode === 'set') {
            // subtract 1 day from start date so that we find memberships that end yesterday
            // these memberships can be continued with new date
            $oneDayOffset = new \DateInterval('P1D');

            $startDate = \DateTime::createFromFormat('Y-m-d', $startDate)->sub($oneDayOffset)->format('Y-m-d');
            // add 1 to max date because we subtract one day if a membership ends
            if ($endDate !== DATE_MAX) {
                $endDate = \DateTime::createFromFormat('Y-m-d', $endDate)->add($oneDayOffset)->format('Y-m-d');
            }
        }

        $this->db->startTransaction();

        // search for membership with same role and user and overlapping dates
        if ($mode === 'set') {
            $member = new TableMembers($this->db);

            $sql = 'SELECT *
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $id
                       AND mem_usr_id = ? -- $usrId
                       AND mem_begin <= ? -- $endDate
                       AND mem_end   >= ? -- $startDate
                  ORDER BY mem_begin';
            $queryParams = array(
                $id,
                $usrId,
                $endDate,
                $startDate
            );
        } else {
            $member = new TableMembers($this->db, $id);

            $sql = 'SELECT *
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_id    <> ? -- $id
                       AND mem_rol_id = ? -- $member->getValue(\'mem_rol_id\')
                       AND mem_usr_id = ? -- $usrId
                       AND mem_begin <= ? -- $endDate
                       AND mem_end   >= ? -- $startDate
                  ORDER BY mem_begin';
            $queryParams = array(
                $id,
                $member->getValue('mem_rol_id'),
                $usrId,
                $endDate,
                $startDate
            );
        }
        $membershipStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($membershipStatement->rowCount() === 1) {
            // one record found than update this record
            $row = $membershipStatement->fetch();
            if (($mode === 'set') && ($row['mem_id'] > 0)) {
                $member = new TableMembers($this->db, $row['mem_id']);
            }
            $member->setArray($row);

            // save new start date if an earlier date exists
            if (strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0) {
                $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
            }

            if ($mode === 'set') {
                // save new end date if a later date exists
                // but only if end date is greater than the beginn date otherwise the membership should be deleted
                if (strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0
                &&  strcmp($member->getValue('mem_begin', 'Y-m-d'), $maxEndDate) < 0) {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }
            } else {
                // save new end date if a later date exists
                if (strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0) {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }
            }
        } elseif ($membershipStatement->rowCount() > 1) {
            // several records found then read min and max date and delete all records
            while ($row = $membershipStatement->fetch()) {
                $member->clear();
                $member->setArray($row);

                // save new start date if an earlier date exists
                if (strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0) {
                    $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
                }

                // save new end date if a later date exists
                if (strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0) {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }

                // delete existing entry because a new overlapping entry will be created
                $member->delete();
            }
            $member->clear();
        }

        if (strcmp($minStartDate, $maxEndDate) > 0) {
            // if start date is greater than end date than delete membership
            if ($member->getValue('mem_id') > 0) {
                $member->delete();
            }
            $returnStatus = true;
        } else {
            // save membership to database
            if ($mode === 'set') {
                $member->setValue('mem_rol_id', $id);
                $member->setValue('mem_usr_id', $usrId);
            }
            $member->setValue('mem_begin', $minStartDate);
            $member->setValue('mem_end', $maxEndDate);

            if ($leader !== null) {
                $member->setValue('mem_leader', $leader);
            }
            $returnStatus = $member->save();
        }

        $this->db->endTransaction();
        $this->renewRoleData();

        return $returnStatus;
    }

    /**
     * Method reads all relationships of the user and will store them in an array. The
     * relationship property if the user can edit the profile of the other user will be stored
     * for later checks within this class.
     * @return bool Return true if relationships could be checked.
     */
    private function checkRelationshipsRights()
    {
        global $gSettingsManager;

        if ((int) $this->getValue('usr_id') === 0 || !$gSettingsManager->getBool('members_enable_user_relations')) {
            return false;
        }

        if (!$this->relationshipsChecked && count($this->relationships) === 0) {
            // read all relations of the current user
            $sql = 'SELECT urt_id, urt_edit_user, ure_usr_id2
                      FROM '.TBL_USER_RELATIONS.'
                INNER JOIN '.TBL_USER_RELATION_TYPES.'
                        ON urt_id = ure_urt_id
                     WHERE ure_usr_id1  = ? -- $this->getValue(\'usr_id\') ';
            $queryParams = array((int) $this->getValue('usr_id'));
            $relationsStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $relationsStatement->fetch()) {
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
     * The method checks which role lists the user could see in a separate array.
     * An array with all roles where the user has the right to write an email will be stored.
     * The method considered the role leader rights of each role if this is set and the current
     * user is a leader in a role.
     * @param string $right The database column name of the right that should be checked. If this param
     *                      is not set then only the arrays are filled.
     * @return bool Return true if a special right should be checked and the user has this right.
     */
    public function checkRolesRight($right = null)
    {
        $sqlFetchedRows = array();

        if ((int) $this->getValue('usr_id') === 0) {
            return false;
        }

        if (count($this->rolesRights) === 0) {
            $this->assignRoles = false;
            $tmpRolesRights = array(
                'rol_all_lists_view'     => false,
                'rol_announcements'      => false,
                'rol_approve_users'      => false,
                'rol_assign_roles'       => false,
                'rol_dates'              => false,
                'rol_documents_files'    => false,
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
                     WHERE rol_valid  = true
                       AND (  cat_org_id = ? -- $this->organizationId
                           OR cat_org_id IS NULL )';
            $queryParams = array((int) $this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
            $rolesStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $rolesStatement->fetch()) {
                $roleId = (int) $row['rol_id'];
                $sqlFetchedRows[] = $row;

                if ($row['mem_usr_id'] > 0) {
                    // Sql selects all roles. Only consider roles where user is a member.
                    if ((bool) $row['mem_leader']) {
                        $rolLeaderRights = (int)$row['rol_leader_rights'];

                        // if user is leader in this role than add role id and leader rights to array
                        $this->rolesMembershipLeader[$roleId] = $rolLeaderRights;

                        // if role leader could assign new members then remember this setting
                        // roles for confirmation of dates should be ignored
                        if ($row['cat_name_intern'] !== 'EVENTS'
                            && ($rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)) {
                            $this->assignRoles = true;
                        }
                    } else {
                        $this->rolesMembershipNoLeader[] = $roleId;
                    }

                    // add role to membership array
                    $this->rolesMembership[] = $roleId;

                    // Transfer the rights of the roles into the array, if these have not yet been set by other roles
                    foreach ($tmpRolesRights as $key => &$value) {
                        if (!$value && $row[$key] == '1') {
                            $value = true;
                        }
                    }
                    unset($value);

                    // set flag assignRoles of user can manage roles
                    if ((int)$row['rol_assign_roles'] === 1) {
                        $this->assignRoles = true;
                    }

                    // set administrator flag
                    if ((int)$row['rol_administrator'] === 1) {
                        $this->administrator = true;
                    }
                }
            }
            $this->rolesRights = $tmpRolesRights;

            // go again through all roles, but now the rolesRights are set and can be evaluated
            foreach ($sqlFetchedRows as $sqlRow) {
                $roleId = (int) $sqlRow['rol_id'];
                $memLeader = (bool) $sqlRow['mem_leader'];

                if (array_key_exists('rol_view_memberships', $sqlRow)) {
                    // Remember roles view setting
                    if ((int) $sqlRow['rol_view_memberships'] === TableRoles::VIEW_ROLE_MEMBERS && $sqlRow['mem_usr_id'] > 0) {
                        // only role members are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                    } elseif ((int) $sqlRow['rol_view_memberships'] === TableRoles::VIEW_LOGIN_USERS) {
                        // all registered users are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                    } elseif ((int) $sqlRow['rol_view_memberships'] === TableRoles::VIEW_LEADERS && $memLeader) {
                        // only leaders are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                    } elseif ($this->rolesRights['rol_all_lists_view']) {
                        // user is allowed to view all roles than also view this membership
                        $this->rolesViewMemberships[] = $roleId;
                    }

                    // Remember profile view setting
                    if ((int) $sqlRow['rol_view_members_profiles'] === TableRoles::VIEW_ROLE_MEMBERS && $sqlRow['mem_usr_id'] > 0) {
                        // only role members are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                    } elseif ((int) $sqlRow['rol_view_members_profiles'] === TableRoles::VIEW_LOGIN_USERS) {
                        // all registered users are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                    } elseif ((int) $sqlRow['rol_view_members_profiles'] === TableRoles::VIEW_LEADERS && $memLeader) {
                        // only leaders are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                    } elseif ($this->rolesRights['rol_all_lists_view']) {
                        // user is allowed to view all roles than also view this membership
                        $this->rolesViewProfiles[] = $roleId;
                    }
                }

                // Set mail permissions
                if ($sqlRow['mem_usr_id'] > 0 && ($sqlRow['rol_mail_this_role'] > 0 || $memLeader)) {
                    // Leaders are allowed to write mails to the role
                    // Membership to the role and this is not locked, then look at it
                    $this->rolesWriteMails[] = $roleId;
                } elseif ($sqlRow['rol_mail_this_role'] >= 2) {
                    // look at other roles when everyone is allowed to see them
                    $this->rolesWriteMails[] = $roleId;
                } elseif ($this->rolesRights['rol_mail_to_all']) {
                    // user is allowed to write emails to all roles than also write to this role
                    $this->rolesWriteMails[] = $roleId;
                }
            }
        }

        return $right === null || $this->rolesRights[$right];
    }

    /**
     * Check if a valid password is set for the user and return true if the correct password
     * was set. Optional the current session could be updated to a valid login session.
     * @param string $password             The password for the current user. This should not be encoded.
     * @param bool   $setAutoLogin         If set to true then this login will be stored in AutoLogin table
     *                                     and the user doesn't need login to another time with this browser.
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
        global $gSettingsManager, $gCurrentSession, $installedDbVersion, $gL10n;

        if ($this->hasMaxInvalidLogins()) {
            throw new AdmException($gL10n->get('SYS_LOGIN_MAX_INVALID_LOGIN'));
        }

        if (!PasswordUtils::verify($password, $this->getValue('usr_password'))) {
            $incorrectLoginMessage = $this->handleIncorrectPasswordLogin();

            throw new AdmException($gL10n->get($incorrectLoginMessage));
        }

        if (!$this->getValue('usr_valid')) {
            throw new AdmException($gL10n->get('SYS_LOGIN_NOT_ACTIVATED'));
        }

        $orgLongName = $this->getOrgLongname();

        if (!$this->isMemberOfOrganization($orgLongName)) {
            throw new AdmException($gL10n->get('SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION', array($orgLongName)));
        }

        if ($isAdministrator && version_compare($installedDbVersion, '2.4', '>=') && !$this->isAdminOfOrganization($orgLongName)) {
            throw new AdmException($gL10n->get('SYS_LOGIN_USER_NO_ADMINISTRATOR', array($orgLongName)));
        }

        if ($updateHash) {
            $this->rehashIfNecessary($password);
        }

        if ($updateSessionCookies) {
            $gCurrentSession->setValue('ses_usr_id', (int) $this->getValue('usr_id'));
            $gCurrentSession->save();
        }

        // should the user stayed logged in automatically, then the cookie would expire in one year
        if ($setAutoLogin && $gSettingsManager->getBool('enable_auto_login')) {
            $gCurrentSession->setAutoLogin();
        } else {
            $this->setValue('usr_last_session_id', null);
        }

        if ($updateSessionCookies) {
            // set cookie for session id
            $gCurrentSession->regenerateId();
            Session::setCookie(COOKIE_PREFIX . '_SESSION_ID', $gCurrentSession->getValue('ses_session_id'));

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

        if ($this->mProfileFieldsData instanceof ProfileFields) {
            // data of all profile fields will be deleted, the internal structure will not be destroyed
            $this->mProfileFieldsData->clearUserData();
        }

        $this->administrator = false;
        $this->relationshipsChecked = false;

        // initialize rights arrays
        $this->usersEditAllowed = array();
        $this->renewRoleData();
    }

    /**
     * Deletes the selected user of the table and all the many references in other tables.
     * Also a notification that the user is deleted will be send if notification is enabled.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        global $gChangeNotification;

        $usrId = $this->getValue('usr_id');

        // only send notification if a valid user will be deleted
        if (is_object($gChangeNotification) && $this->getValue('usr_valid')) {
            // Register all non-empty fields for the notification
            $gChangeNotification->logUserDeletion($usrId, $this);
        }

        // first delete send messages from the user
        $sql = 'SELECT msg_id FROM ' . TBL_MESSAGES . ' WHERE msg_usr_id_sender = ? -- $usrId';
        $messagesStatement = $this->db->queryPrepared($sql, array($usrId));

        while ($row = $messagesStatement->fetch()) {
            $message = new TableMessage($this->db, $row['msg_id']);
            $message->delete();
        }

        // now delete every database entry where the user id is used
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
                          WHERE lst_global = true
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
                                                  AND lst_global = false)';

        $sqlQueries[] = 'DELETE FROM '.TBL_LISTS.'
                          WHERE lst_global = false
                            AND lst_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_GUESTBOOK_COMMENTS.'
                          WHERE gbc_usr_id_create = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MEMBERS.'
                          WHERE mem_usr_id = '.$usrId;

        // MySQL couldn't create delete statement with same table in a subquery.
        // Therefore, we fill a temporary table with all ids that should be deleted and reference on this table
        $sqlQueries[] = 'DELETE FROM '.TBL_IDS.'
                          WHERE ids_usr_id = '.$GLOBALS['gCurrentUserId'];

        $sqlQueries[] = 'INSERT INTO '.TBL_IDS.'
                                (ids_usr_id, ids_reference_id)
                         SELECT '.$GLOBALS['gCurrentUserId'].', msc_msg_id
                           FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE msc_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE msc_msg_id IN (SELECT ids_reference_id
                                                 FROM '.TBL_IDS.'
                                                WHERE ids_usr_id = '.$GLOBALS['gCurrentUserId'].')';

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_RECIPIENTS.'
                          WHERE msr_msg_id IN (SELECT ids_reference_id
                                                 FROM '.TBL_IDS.'
                                                WHERE ids_usr_id = '.$GLOBALS['gCurrentUserId'].')';

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES.'
                          WHERE msg_id IN (SELECT ids_reference_id
                                             FROM '.TBL_IDS.'
                                            WHERE ids_usr_id = '.$GLOBALS['gCurrentUserId'].')';

        $sqlQueries[] = 'DELETE FROM '.TBL_IDS.'
                          WHERE ids_usr_id = '.$GLOBALS['gCurrentUserId'];

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_RECIPIENTS.'
                          WHERE msr_usr_id = '.$usrId;

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                          WHERE NOT EXISTS (SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . '
                                            WHERE msr_msg_id = msc_msg_id)';

        $sqlQueries[] = 'DELETE FROM '.TBL_MESSAGES.'
                          WHERE NOT EXISTS (SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . '
                                            WHERE msr_msg_id = msg_id)';

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

        foreach ($sqlQueries as $sqlQuery) {
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
     * Changes to user data could be sent as a notification email to a specific role if this
     * function is enabled in the settings. If you want to suppress this logic you can
     * explicit disable it with this method for this user. So no changes to this user object will
     * result in a notification email.
     * @return void
     */
    public function disableChangeNotification()
    {
        $this->changeNotificationEnabled = false;
    }

    /**
     * Edit an existing role membership of the current user. If the new date range contains
     * a future or past membership of the same role then the two memberships will be merged.
     * In opposite to setRoleMembership this method is useful to end a membership earlier.
     * @param int    $memberId  ID of the current membership that should be edited.
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

        if (($categoryType === 'ANN' && $this->editAnnouncements())
        || ($categoryType === 'DAT' && $this->editDates())
        || ($categoryType === 'LNK' && $this->editWeblinksRight())
        || ($categoryType === 'USF' && $this->editUsers())
        || ($categoryType === 'ROL' && $this->manageRoles())) {
            $condition = '';
        } else {
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
        while ($catId = $pdoStatement->fetchColumn()) {
            $arrEditableCategories[] = (int) $catId;
        }

        return $arrEditableCategories;
    }

    /**
     * Creates an array with all roles where the user has the right to mail them
     * @return array<int,int> Array with role ids where user has the right to mail them
     * @deprecated 4.2.0:4.3.0 "getAllMailRoles()" is deprecated, use "getRolesWriteMails()" instead.
     */
    public function getAllMailRoles()
    {
        return $this->rolesWriteMails;
    }

    /**
     * Creates an array with all categories of one type where the user has the right to view them
     * @param string $categoryType The type of the category that should be checked e.g. ANN, USF or DAT
     * @return array<int,int> Array with categories ids where user has the right to view them
     */
    public function getAllVisibleCategories($categoryType)
    {
        $queryParams = array($categoryType, $this->organizationId);

        if (($categoryType === 'ANN' && $this->editAnnouncements())
        || ($categoryType === 'DAT' && $this->editDates())
        || ($categoryType === 'LNK' && $this->editWeblinksRight())
        || ($categoryType === 'USF' && $this->editUsers())
        || ($categoryType === 'ROL' && $this->assignRoles())) {
            $condition = '';
        } else {
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
        while ($catId = $pdoStatement->fetchColumn()) {
            $arrVisibleCategories[] = (int) $catId;
        }

        return $arrVisibleCategories;
    }

    /**
     * Creates an array with all roles where the user has the right to view them
     * @return array<int,int> Array with role ids where user has the right to view them
     * @deprecated 4.2.0:4.3.0 "getAllVisibleRoles()" is deprecated, use "getRolesViewMemberships()" instead.
     */
    public function getAllVisibleRoles()
    {
        return $this->rolesViewMemberships;
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
     * Returns an array with all roles where the user has the right to view the profiles of the role members
     * @return array<int,int> Array with role ids where user has the right to view the profiles of the role members
     */
    public function getRolesViewProfiles(): array
    {
        return $this->rolesViewProfiles;
    }

    /**
     * Returns an array with all roles where the user has the right to view the memberships
     * @return array<int,int> Array with role ids where user has the right to view the memberships
     */
    public function getRolesViewMemberships(): array
    {
        return $this->rolesViewMemberships;
    }

    /**
     * Returns an array with all roles where the user has the right to write an email to the role members
     * @return array<int,int> Array with role ids where user has the right to mail them
     */
    public function getRolesWriteMails()
    {
        return $this->rolesWriteMails;
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

        if (!function_exists('filterEmptyStrings')) {
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
     * **Code example**
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

        if (!str_starts_with($columnName, 'usr_')) {
            return $this->mProfileFieldsData->getValue($columnName, $format);
        }

        if ($columnName === 'usr_photo' && (int) $gSettingsManager->get('profile_photo_storage') === 0) {
            $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . (int) $this->getValue('usr_id') . '.jpg';
            if (is_file($file)) {
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

        if ($gCurrentUser->allowedViewProfileField($this, 'FIRST_NAME')) {
            $vCard[] = 'N;CHARSET=ISO-8859-1:' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('LAST_NAME', 'database')) . ';' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('FIRST_NAME', 'database')) . ';;;';
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'LAST_NAME')) {
            $vCard[] = 'FN;CHARSET=ISO-8859-1:' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('FIRST_NAME')) . ' ' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('LAST_NAME'));
        }
        if ($this->getValue('usr_login_name') !== '') {
            $vCard[] = 'NICKNAME;CHARSET=ISO-8859-1:' . iconv("UTF-8","ISO-8859-1", $this->getValue('usr_login_name'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'PHONE')) {
            $vCard[] = 'TEL;HOME;VOICE:' . $this->getValue('PHONE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'MOBILE')) {
            $vCard[] = 'TEL;CELL;VOICE:' . $this->getValue('MOBILE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'FAX')) {
            $vCard[] = 'TEL;HOME;FAX:' . $this->getValue('FAX');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'STREET')
        &&  $gCurrentUser->allowedViewProfileField($this, 'CITY')
        &&  $gCurrentUser->allowedViewProfileField($this, 'POSTCODE')
        &&  $gCurrentUser->allowedViewProfileField($this, 'COUNTRY')) {
            $vCard[] = 'ADR;CHARSET=ISO-8859-1;HOME:;;' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('STREET', 'database')) . ';' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('CITY', 'database')) . ';;' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('POSTCODE', 'database')) . ';' .
                iconv("UTF-8","ISO-8859-1", $this->getValue('COUNTRY', 'database'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'WEBSITE')) {
            $vCard[] = 'URL;HOME:' . $this->getValue('WEBSITE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'BIRTHDAY')) {
            $vCard[] = 'BDAY:' . $this->getValue('BIRTHDAY', 'Ymd');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'EMAIL')) {
            $vCard[] = 'EMAIL;PREF;INTERNET:' . $this->getValue('EMAIL');
        }
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . (int) $this->getValue('usr_id') . '.jpg';
        if ((int) $gSettingsManager->get('profile_photo_storage') === 1 && is_file($file)) {
            $imgHandle = fopen($file, 'rb');
            if ($imgHandle !== false) {
                $base64Image = base64_encode(fread($imgHandle, filesize($file)));
                fclose($imgHandle);

                $vCard[] = 'PHOTO;ENCODING=BASE64;TYPE=JPEG:' . $base64Image;
            }
        }
        if ((int) $gSettingsManager->get('profile_photo_storage') === 0 && $this->getValue('usr_photo') !== '') {
            $vCard[] = 'PHOTO;ENCODING=BASE64;TYPE=JPEG:' . base64_encode($this->getValue('usr_photo'));
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($gCurrentUser->allowedViewProfileField($this, 'GENDER') && $this->getValue('GENDER') > 0) {
            if ((int) $this->getValue('GENDER') === 1) {
                $xGender = 'Male';
                $xWabGender = 2;
            } else {
                $xGender = 'Female';
                $xWabGender = 1;
            }

            $vCard[] = 'X-GENDER:' . $xGender;
            $vCard[] = 'X-WAB-GENDER:' . $xWabGender;
        }
        if ($this->getValue('usr_timestamp_change') !== '') {
            $vCard[] = 'REV:' . $this->getValue('usr_timestamp_change', 'Ymd\This');
        }

        $vCard[] = 'END:VCARD';

        return implode("\r\n", $vCard) . "\r\n";
    }

    /**
     * Returns true if a column of user table or profile fields has changed
     * @return bool Returns true if a column of user table or profile fields has changed
     */
    public function hasColumnsValueChanged()
    {
        return parent::hasColumnsValueChanged() || $this->mProfileFieldsData->hasColumnsValueChanged();
    }

    /**
     * Check if the user has deposited an email. Therefore at least one profile field from type EMAIL
     * must have a value.
     * @return bool Return true if the user has deposited an email.
     */
    public function hasEmail()
    {
        if ($this->getValue('EMAIL') !== '') {
            return true;
        }

        foreach ($this->mProfileFieldsData->getProfileFields() as $profileField) {// => $profileFieldConfig)
            if ($profileField->getValue('usf_type') === 'EMAIL'
            && $this->mProfileFieldsData->getValue($profileField->getValue('usf_name_intern')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the maximum of invalid logins is reached.
     * @return bool Returns true if the maximum of invalid logins is reached.
     */
    private function hasMaxInvalidLogins()
    {
        // if within 15 minutes 3 wrong login took place -> block user account for 15 minutes
        $now = new \DateTime();
        $minutesOffset = new \DateInterval('PT15M');
        $minutesBefore = $now->sub($minutesOffset);

        if(is_null($this->getValue('usr_date_invalid', 'Y-m-d H:i:s'))) {
            $dateInvalid = $minutesBefore;
        } else {
            $dateInvalid = \DateTime::createFromFormat('Y-m-d H:i:s', $this->getValue('usr_date_invalid', 'Y-m-d H:i:s'));
        }

        if ($this->getValue('usr_number_invalid') < self::MAX_INVALID_LOGINS || $minutesBefore->getTimestamp() >= $dateInvalid->getTimestamp()) {
            return false;
        }

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
    public function hasRightEditProfile(self $user, $checkOwnProfile = true)
    {
        if (!$user instanceof self) {
            return false;
        }

        $usrId  = (int) $this->getValue('usr_id');
        $userId = (int) $user->getValue('usr_id');

        // edit own profile ?
        if ($usrId > 0 && $usrId === $userId && $checkOwnProfile && $this->checkRolesRight('rol_profile')) {
            return true;
        }

        // first check if user is in cache
        if (array_key_exists($userId, $this->usersEditAllowed)) {
            return $this->usersEditAllowed[$userId];
        }

        $returnValue = false;

        if ($this->editUsers()) {
            $returnValue = true;
        } else {
            if (count($this->rolesMembershipLeader) > 0) {
                // leaders are not allowed to edit profiles of other leaders but to edit their own profile
                if ($usrId === $userId) {
                    // check if current user is a group leader of a role where $user is only a member
                    $rolesMembership = $user->getRoleMemberships();
                } else {
                    // check if current user is a group leader of a role where $user is only a member and not a leader
                    $rolesMembership = $user->getRoleMembershipsNoLeader();
                }

                foreach ($this->rolesMembershipLeader as $roleId => $leaderRights) {
                    // is group leader of role and has the right to edit users ?
                    if ($leaderRights > 1 && in_array($roleId, $rolesMembership, true)) {
                        $returnValue = true;
                        break;
                    }
                }
            }
        }

        // check if user has a relationship to current user and is allowed to edit him
        if (!$returnValue && $this->checkRelationshipsRights()) {
            foreach ($this->relationships as $relationshipUser) {
                if ($relationshipUser['user_id'] === $userId && $relationshipUser['edit_user']) {
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
     * @param string $rightName
     * @param int $roleId
     * @return bool
     */
    private function hasRightRole(array $rightsList, string $rightName, int $roleId): bool
    {
        // if user has right to view all lists then he could also view this role
        if ($this->checkRolesRight($rightName)) {
            return true;
        }

        // check if user has the right to view this role
        return in_array($roleId, $rightsList);
    }

    /**
     * Checks if the current user has the right to send an email to the role.
     * @param int $roleId Id of the role that should be checked.
     * @return bool Return **true** if the user has the right to send an email to the role.
     */
    public function hasRightSendMailToRole(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesWriteMails, 'rol_mail_to_all', $roleId);
    }

    /**
     * Checks the necessary rights if this user could view former roles members. Therefore
     * the user must also have the right to view the role. So you must also check this right.
     * @param int $roleId Id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view former roles members
     */
    public function hasRightViewFormerRolesMembers(int $roleId): bool
    {
        global $gSettingsManager;

        if ((int) $gSettingsManager->get('groups_roles_show_former_members') !== 1
        && ($this->checkRolesRight('rol_assign_roles')
        || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(1, 3), true)))) {
            return true;
        } elseif ((int) $gSettingsManager->get('groups_roles_show_former_members') !== 2
        && ($this->checkRolesRight('rol_edit_user')
        || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(2, 3), true)))) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current user is allowed to view the profile of the user of the parameter.
     * It will check if user has edit rights with method **hasRightEditProfile** or if the user is a member
     * of a role where the current user has the right to view profiles.
     * @param User $user User object of the user that should be checked if the current user can view his profile.
     * @return bool Return **true** if the current user is allowed to view the profile of the user from **$user**.
     */
    public function hasRightViewProfile(self $user): bool
    {
        global $gValidLogin;

        // if user is allowed to edit the profile then he can also view it
        if ($this->hasRightEditProfile($user)) {
            return true;
        }

        // every user is allowed to view his own profile
        if ((int) $user->getValue('usr_id') === (int) $this->getValue('usr_id') && (int) $this->getValue('usr_id') > 0) {
            return true;
        }

        // Users who can see all lists can also see all profiles
        if ($this->checkRolesRight('rol_all_lists_view')) {
            return true;
        }

        $sql = 'SELECT rol_id, rol_view_members_profiles
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = true
                   AND mem_usr_id = ? -- $user->getValue(\'usr_id\')
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND (  cat_org_id = ? -- $this->organizationId
                       OR cat_org_id IS NULL ) ';
        $queryParams = array((int) $user->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $listViewStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($listViewStatement->rowCount() > 0) {
            while ($row = $listViewStatement->fetch()) {
                $rolId = (int)$row['rol_id'];
                $rolThisProfileView = (int)$row['rol_view_members_profiles'];

                if ($rolThisProfileView === TableRoles::VIEW_LOGIN_USERS && $gValidLogin) {
                    // all logged in users can see role lists/profiles
                    return true;
                } elseif ($rolThisProfileView === TableRoles::VIEW_ROLE_MEMBERS && in_array($rolId, $this->rolesViewProfiles)) {
                    // only role members can see role lists/profiles
                    return true;
                } elseif ($rolThisProfileView === TableRoles::VIEW_LEADERS && $this->isLeaderOfRole($rolId)) {
                    // only leaders of the role could view the profile of the role members
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user of this object has the right to view profiles of members from the role
     * that is set in the parameter.
     * @param int $roleId The id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view profiles of members from the role otherwise **false**.
     */
    public function hasRightViewProfiles(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesViewProfiles, 'rol_all_lists_view', $roleId);
    }

    /**
     * Check if the user of this object has the right to view the role that is set in the parameter.
     * @param int $roleId The id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view the role otherwise **false**.
     */
    public function hasRightViewRole(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesViewMemberships, 'rol_all_lists_view', $roleId);
    }

    /**
     * Handles the incorrect given login password.
     * @return string Return string with the reason why the login failed.
     */
    private function handleIncorrectPasswordLogin()
    {
        // log invalid logins
        if ($this->getValue('usr_number_invalid') >= self::MAX_INVALID_LOGINS) {
            $this->setValue('usr_number_invalid', 1);
        } else {
            $this->setValue('usr_number_invalid', $this->getValue('usr_number_invalid') + 1);
        }

        $this->setValue('usr_date_invalid', DATETIME_NOW);
        $this->saveChangesWithoutRights();
        $this->save(false); // don't update timestamp // TODO Exception handling

        if ($this->getValue('usr_number_invalid') >= self::MAX_INVALID_LOGINS) {
            $this->clear();

            return 'SYS_LOGIN_MAX_INVALID_LOGIN';
        }

        $this->clear();

        return 'SYS_LOGIN_USERNAME_PASSWORD_INCORRECT';
    }

    /**
     * Deletes all other sessions of the current user except the current session if there is already a current
     * session. All auto logins of the user will be removed. This method is useful if the user changed his
     * password or if unusual activities within the user account are noticed.
     * @return bool Returns true if all things could be done. Otherwise false is returned.
     */
    public function invalidateAllOtherLogins()
    {
        global $gCurrentUserId, $gCurrentSession;

        if(isset($gCurrentSession)) {
            // remove all sessions of the current user except the current session
            $sql = 'DELETE FROM ' . TBL_SESSIONS . '
                     WHERE ses_usr_id = ? -- $gCurrentUserId
                       AND ses_id    <> ? -- $gCurrentSession->getValue(\'ses_id\') ';
            $queryParams = array($gCurrentUserId, $gCurrentSession->getValue('ses_id'));
        } else {
            // remove all sessions of the current user
            $sql = 'DELETE FROM ' . TBL_SESSIONS . '
                     WHERE ses_usr_id = ? -- $gCurrentUserId ';
            $queryParams = array($gCurrentUserId);
        }
        $this->db->queryPrepared($sql, $queryParams);

        // remove all auto logins of the current user
        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN . '
                 WHERE atl_usr_id = ? -- $gCurrentUserId ';
        $queryParams = array($gCurrentUserId);
        $this->db->queryPrepared($sql, $queryParams);

        return true;
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
     * @param string $orgLongName The longname of this organization.
     * @return bool Return true if user is admin of this organization.
     */
    private function isAdminOfOrganization($orgLongName)
    {
        global $installedDbVersion;

        // Deprecated: Fallback for updates from v3.0 and v3.1
        if (version_compare($installedDbVersion, '3.2', '>=')) {
            $administratorColumn = 'rol_administrator';
        } else {
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
                   AND rol_valid  = true
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND cat_org_id = ? -- $this->organizationId
                   AND '.$administratorColumn.' = true ';
        $queryParams = array((int) $this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($pdoStatement->rowCount() > 0) {
            return true;
        }

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
     * @param string $orgLongName The longname of this organization.
     * @return bool Return true if user is member of this organization.
     */
    private function isMemberOfOrganization($orgLongName)
    {
        // Check if user is currently member of a role of an organisation
        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = ? -- $this->getValue(\'usr_id\')
                   AND rol_valid  = true
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND cat_org_id = ? -- $this->organizationId';
        $queryParams = array((int) $this->getValue('usr_id'), DATE_NOW, DATE_NOW, $this->organizationId);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($pdoStatement->rowCount() > 0) {
            return true;
        }

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
     * All profile fields of the object **mProfileFieldsData** will also be read. If no user was
     * found than the default values of all profile fields will be set.
     * @param int $userId Unique id of the user that should be read
     * @return bool Returns **true** if one record is found
     */
    public function readDataById($userId)
    {
        if (parent::readDataById($userId)) {
            // read data of all user fields from current user
            $this->mProfileFieldsData->readUserData($userId, $this->organizationId);
            return true;
        } else {
            $this->setDefaultValues();
        }

        return false;
    }

    /**
     * Reads a record out of the table in database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g. usr_uuid.
     * Per default all columns of the default table will be read and stored in the object. If no user
     * was found than the default values of all profile fields will be set.
     * Not every Admidio table has an uuid. Please check the database structure before you use this method.
     * @param int $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @see TableAccess#readData
     * @see TableAccess#readDataByColumns
     */
    public function readDataByUuid($uuid)
    {
        if (parent::readDataByUuid($uuid)) {
            // read data of all user fields from current user
            $this->mProfileFieldsData->readUserData($this->getValue('usr_id'), $this->organizationId);
            return true;
        } else {
            $this->setDefaultValues();
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
        if (!PasswordUtils::needsRehash($this->getValue('usr_password'))) {
            return false;
        }

        $this->saveChangesWithoutRights();
        $this->setPassword($password);
        $this->save(); // TODO Exception handling

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
        $this->rolesRights = array();
        $this->rolesViewMemberships = array();
        $this->rolesViewMemberships = array();
        $this->rolesWriteMails = array();
        $this->rolesMembership = array();
        $this->rolesMembershipLeader   = array();
        $this->rolesMembershipNoLeader = array();
    }

    /**
     * Reset the count of invalid logins. After that it's possible for the user to try another login.
     */
    public function resetInvalidLogins()
    {
        $this->setValue('usr_date_invalid', null);
        $this->setValue('usr_number_invalid', 0);
        $this->save(false); // Zeitstempel nicht aktualisieren // TODO Exception handling
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
        global $gCurrentSession, $gCurrentUser, $gChangeNotification;

        $usrId = $this->getValue('usr_id');

        // if current user is not new and is not allowed to edit this user
        // and saveChangesWithoutRights isn't true than throw exception
        if (!$this->saveChangesWithoutRights && $usrId > 0 && !$gCurrentUser->hasRightEditProfile($this)) {
            throw new AdmException('The profile data of user ' . $this->getValue('FIRST_NAME') . ' '
                . $this->getValue('LAST_NAME') . ' could not be saved because you don\'t have the right to do this.');
        }

        $this->db->startTransaction();

        // if new user then set create id and the uuid
        $updateCreateUserId = false;
        if ($usrId === 0) {
            if ($GLOBALS['gCurrentUserId'] === 0) {
                $updateCreateUserId = true;
                $updateFingerPrint  = false;
            }
        }

        // if value of a field changed then update timestamp of user object
        if ($this->mProfileFieldsData instanceof ProfileFields && $this->mProfileFieldsData->hasColumnsValueChanged()) {
            $this->columnsValueChanged = true;
        }

        $newRecord = $this->newRecord;

        $returnValue = parent::save($updateFingerPrint);
        $usrId = (int) $this->getValue('usr_id'); // if a new user was created get the new id

        // if this was an registration then set this user id to create user id
        if ($updateCreateUserId) {
            $this->setValue('usr_timestamp_create', DATETIME_NOW);
            $this->setValue('usr_usr_id_create', $usrId);
            $returnValue = $returnValue && parent::save($updateFingerPrint);
        }

        if ($this->mProfileFieldsData instanceof ProfileFields) {
            // save data of all user fields
            $this->mProfileFieldsData->saveUserData($usrId);
        }

        if ($this->columnsValueChanged && $gCurrentSession instanceof Session) {
            // now set reload the session of the user,
            // because he has new data and maybe new rights
            $gCurrentSession->reload($usrId);
        }
        // The record is a new record, which was just stored to the database
        // for the first time => record it as a user creation now
        if ($newRecord && $this->changeNotificationEnabled && is_object($gChangeNotification)) {
            // Register all non-empty fields for the notification
            $gChangeNotification->logUserCreation($usrId, $this);
        }

        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * Set the default values that are stored in the profile fields configuration to each profile field.
     * A default value will only be set if **usf_default_value** is not NULL.
     * @return void
     */
    public function setDefaultValues()
    {
        foreach ($this->mProfileFieldsData->getProfileFields() as $profileField) {
            $defaultValue = $profileField->getValue('usf_default_value');
            if($defaultValue !== '') {
                $this->setValue($profileField->getValue('usf_name_intern'), $defaultValue);
            }
        }
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
        if($organizationId !== $this->organizationId) {
            $this->organizationId = $organizationId;
            $this->renewRoleData();
        }
    }

    /**
     * Set a new value for a password column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $newPassword   The new value that should be stored in the database field
     * @param bool   $doHashing     Should the password get hashed before inserted. Default is true
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setPassword($newPassword, $doHashing = true)
    {
        global $gSettingsManager, $gPasswordHashAlgorithm, $gChangeNotification;

        if (!$doHashing) {
            if ($this->changeNotificationEnabled && is_object($gChangeNotification)) {
                $gChangeNotification->logUserChange(
                    (int) $this->getValue('usr_id'),
                    'usr_password',
                    $this->getValue('usr_password'),
                    $newPassword,
                    $this
                );
            }
            return parent::setValue('usr_password', $newPassword, false);
        }

        // get the saved cost value that fits your server performance best and rehash your password
        $options = array('cost' => 10);
        if (isset($gSettingsManager) && $gSettingsManager->has('system_hashing_cost')) {
            $options['cost'] = $gSettingsManager->getInt('system_hashing_cost');
        }

        $newPasswordHash = PasswordUtils::hash($newPassword, $gPasswordHashAlgorithm, $options);

        if ($newPasswordHash === false) {
            return false;
        }

        if ($this->changeNotificationEnabled && is_object($gChangeNotification)) {
            $gChangeNotification->logUserChange(
                (int) $this->getValue('usr_id'),
                'usr_password',
                $this->getValue('usr_password'),
                $newPasswordHash,
                $this
            );
        }
        if(parent::setValue('usr_password', $newPasswordHash, false)) {
            // for security reasons remove all sessions and auto login of the user
            return $this->invalidateAllOtherLogins();
        }

        return false;
    }

    /**
     * Set a value for a profile field. The value will be checked against typical conditions of the data type and
     * also against the custom regex if this is set. If an invalid value is set an AdmException will be thrown.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should get a new value.
     * @param mixed  $fieldValue      The new value that should be stored in the profile field.
     * @param bool   $checkValue      The value will be checked if it's valid. If set to **false** than the value will
     *                                not be checked.
     * @throws AdmException If an invalid value should be set.
     *                      exception->text contains a string with the reason why the login failed.
     * @return bool Return true if the value is valid and would be accepted otherwise return false or an exception.
     */
    public function setProfileFieldsValue($fieldNameIntern, $fieldValue, $checkValue = true)
    {
        return $this->mProfileFieldsData->setValue($fieldNameIntern, $fieldValue, $checkValue);
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
     * **Code example**
     * ```
     * // set data of adm_users column
     * $gCurrentUser->getValue('usr_login_name', 'Admidio');
     * // reads data of adm_user_fields
     * $gCurrentUser->getValue('EMAIL', 'administrator@admidio.org');
     * ```
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gSettingsManager, $gChangeNotification;

        // users data from adm_users table
        if (str_starts_with($columnName, 'usr_')) {
            // don't change user password; use $user->setPassword()
            if ($columnName === 'usr_password') {
                return false;
            }

            // username should not contain special characters
            if ($checkValue && $columnName === 'usr_login_name' && $newValue !== '' && !StringUtils::strValidCharacters($newValue, 'noSpecialChar')) {
                return false;
            }

            // only update if value has changed
            if ($this->getValue($columnName, 'database') == $newValue) {
                return true;
            }

            // For new records, do not immediately queue all changes for notification,
            // as the record might never be saved to the database (e.g. when
            // doing a check for an existing user)! => For new records,
            // log the changes only when $this->save is called!
            if (!$this->newRecord && $this->changeNotificationEnabled && is_object($gChangeNotification)) {
                $gChangeNotification->logUserChange(
                    $this->getValue('usr_id'),
                    $columnName,
                    $this->getValue($columnName),
                    $newValue,
                    $this
                );
            }

            return parent::setValue($columnName, $newValue, $checkValue);
        }

        // user data from adm_user_fields table (human-readable text representation and raw database value)
        $oldFieldValue = $this->mProfileFieldsData->getValue($columnName);
        $oldFieldValue_db = $this->mProfileFieldsData->getValue($columnName, 'database');
        $newValue = (string) $newValue;

        // format of date will be local but database hase stored Y-m-d format must be changed for compare
        if ($this->mProfileFieldsData->getProperty($columnName, 'usf_type') === 'DATE') {
            $date = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $newValue);

            if ($date !== false) {
                $newValue = $date->format('Y-m-d');
            }
        }

        // only update if value has changed
        if ($oldFieldValue_db === $newValue) {
            return true;
        }

        $returnCode = false;

        // Disabled fields can only be edited by users with the right "edit_users" except on registration.
        // Here is no need to check hidden fields because we check on save() method that only users who
        // can edit the profile are allowed to save and change data.
        if (($this->getValue('usr_id') === 0 && $GLOBALS['gCurrentUserId'] === 0)
        ||  (int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 0
        || ((int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 1
            && $GLOBALS['gCurrentUser']->hasRightEditProfile($this, false))
        || $this->saveChangesWithoutRights === true) {
            $returnCode = $this->mProfileFieldsData->setValue($columnName, $newValue);
        }

        // Nicht alle Aenderungen werden geloggt. Ausnahmen:
        // Felder, die mit usr_ beginnen (special case above)
        // Felder, die sich nicht geändert haben (check above)
        // Wenn usr_id ist 0 (der User neu angelegt wird; Das wird bereits dokumentiert) (check in logProfileChange)

        if ($returnCode && !$this->newRecord && is_object($gChangeNotification)) {
            $gChangeNotification->logProfileChange(
                $this->getValue('usr_id'),
                $this->mProfileFieldsData->getProperty($columnName, 'usf_id'),
                $columnName, // TODO: is $columnName the internal name or the human-readable?
                // Old and new values in human-readable version:
                $oldFieldValue,
                $this->mProfileFieldsData->getValue($columnName),
                // Old and new values in raw dtabase:
                $oldFieldValue_db,
                $newValue,
                $this
            );
        }

        return $returnCode;
    }

    /**
     * Update login data for this user. These are timestamps of last login and reset count
     * and timestamp of invalid logins.
     * @return void
     */
    public function updateLoginData()
    {
        $this->saveChangesWithoutRights();
        $this->setValue('usr_last_login', $this->getValue('usr_actual_login', 'Y-m-d H:i:s'));
        $this->setValue('usr_number_login', (int) $this->getValue('usr_number_login') + 1);
        $this->setValue('usr_actual_login', DATETIME_NOW);
        $this->save(false); // Zeitstempel nicht aktualisieren // TODO Exception handling

        $this->resetInvalidLogins();
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
     * Checks if the user has the right to assign members to at least one role. This method also returns
     * true if the user is a leader of the role and could assign other members to that role.
     * @return bool Return **true** if the user can assign members to at least one role.
     */
    public function assignRoles()
    {
        $this->checkRolesRight();

        return $this->assignRoles;
    }

    /**
     * Method checks if the current user is allowed to manage roles and therefore has
     * admin access to the groups and roles module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function manageRoles()
    {
        return $this->checkRolesRight('rol_assign_roles');
    }

    /**
     * Method checks if the current user is allowed to administrate the event module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function editDates()
    {
        return $this->checkRolesRight('rol_dates');
    }

    /**
     * Method checks if the current user is allowed to administrate the documents and files module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function adminDocumentsFiles()
    {
        return $this->checkRolesRight('rol_documents_files');
    }

    /**
     * Method checks if the current user is allowed to administrate other user profiles and therefore
     * has access to the user management module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function editUsers()
    {
        return $this->checkRolesRight('rol_edit_user');
    }

    /**
     * Method checks if the current user is allowed to administrate the guestbook module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function editGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook');
    }

    /**
     * Method checks if the current user is allowed to comment guestbook entries.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function commentGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook_comments');
    }

    /**
     * Method checks if the current user is allowed to administrate the photos module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function editPhotoRight()
    {
        return $this->checkRolesRight('rol_photo');
    }

    /**
     * Method checks if the current user is allowed to administrate the weblinks module.
     * @return bool Return true if the user is admin of the module otherwise false
     */
    public function editWeblinksRight()
    {
        return $this->checkRolesRight('rol_weblinks');
    }

    /**
     * Return the (internal) representation of this user's profile fields
     * @return object<ProfileFields> All profile fields of the user
     */
    public function getProfileFieldsData()
    {
        return $this->mProfileFieldsData;
    }
}
