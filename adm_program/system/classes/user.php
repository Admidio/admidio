<?php
/**
 ***********************************************************************************************
 * Class handle role rights, cards and other things of users
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * deleteUserFieldData()    - delete all user data of profile fields;
 *                            user record will not be deleted
 * getListViewRights()  - Liefert ein Array mit allen Rollen und der
 *                        Berechtigung, ob der User die Liste einsehen darf
 *                      - aehnlich getProperty, allerdings suche ueber usf_id
 * getVCard()           - Es wird eine vCard des Users als String zurueckgegeben
 * setRoleMembership($roleId, $startDate = DATE_NOW, $endDate = '9999-12-31', $leader = '')
 *                      - set a role membership for the current user
 *                        if memberships to this user and role exists within the period than merge them to one new membership
 * viewProfile          - Ueberprueft ob der User das Profil eines uebrgebenen
 *                        Users einsehen darf
 * viewRole             - Ueberprueft ob der User eine uebergebene Rolle(Liste)
 *                        einsehen darf
 * isWebmaster()        - gibt true/false zurueck, falls der User Mitglied der
 *                        Rolle "Webmaster" ist
 *
 *****************************************************************************/
class User extends TableUsers
{
    protected $webmaster;

    public $mProfileFieldsData;                   ///< object with current user field structure
    public $roles_rights = array();               ///< Array with all roles rights and the status of the current user e.g. array('rol_assign_roles'  => '0', 'rol_approve_users' => '1' ...)
    protected $list_view_rights = array();        ///< Array with all roles and a flag if the user could view this role e.g. array('role_id_1' => '1', 'role_id_2' => '0' ...)
    protected $role_mail_rights = array();        ///< Array with all roles and a flag if the user could write a mail to this role e.g. array('role_id_1' => '1', 'role_id_2' => '0' ...)
    protected $rolesMembership  = array();        ///< Array with all roles who the user is assigned
    protected $rolesMembershipLeader = array();   ///< Array with all roles who the user is assigned and is leader (key = role_id; value = rol_leader_rights)
    protected $rolesMembershipNoLeader = array(); ///< Array with all roles who the user is assigned and is not a leader of the role
    protected $organizationId;                    ///< the organization for which the rights are read, could be changed with method @b setOrganization
    protected $assignRoles;                       ///< Flag if the user has the right to assign at least one role
    protected $saveChangesWithoutRights;          ///< If this flag is set then a user can save changes to the user if he hasn't the necessary rights
    protected $usersEditAllowed = array();        ///< Array with all user ids where the current user is allowed to edit the profile.

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param object $database   Object of the class Database. This should be the default global object @b $gDb.
     * @param object $userFields An object of the ProfileFields class with the profile field structure
     *                           of the current organization. This could be the default object @b $gProfileFields.
     * @param int    $userId     The id of the user who should be loaded. If id isn't set than an empty object with
     *                           no specific user is created.
     */
    public function __construct(&$database, $userFields, $userId = 0)
    {
        global $gCurrentOrganization;

        $this->mProfileFieldsData = clone $userFields; // create explicit a copy of the object (param is in PHP5 a reference)
        $this->mProfileFieldsData->setDatabase($database);

        $this->organizationId = $gCurrentOrganization->getValue('org_id');
        parent::__construct($database, $userId);
    }

    /**
     * Assign the user to all roles that have set the flag @b rol_default_registration.
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
                   AND cat_org_id = '.$this->organizationId;
        $defaultRolesStatement = $this->db->query($sql);

        if($defaultRolesStatement->rowCount() === 0)
        {
            $gMessage->show($gL10n->get('PRO_NO_DEFAULT_ROLE'));
        }

        while($row = $defaultRolesStatement->fetch())
        {
            // starts a membership for role from now
            $this->setRoleMembership($row['rol_id']);
        }

        $this->db->endTransaction();
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
    public function checkRolesRight($right = '')
    {
        if($this->getValue('usr_id') > 0)
        {
            if(count($this->roles_rights) === 0)
            {
                $this->assignRoles = false;
                $tmp_roles_rights  = array(
                    'rol_assign_roles'       => '0',
                    'rol_approve_users'      => '0',
                    'rol_announcements'      => '0',
                    'rol_dates'              => '0',
                    'rol_download'           => '0',
                    'rol_edit_user'          => '0',
                    'rol_guestbook'          => '0',
                    'rol_guestbook_comments' => '0',
                    'rol_mail_to_all'        => '0',
                    'rol_photo'              => '0',
                    'rol_profile'            => '0',
                    'rol_weblinks'           => '0',
                    'rol_all_lists_view'     => '0',
                    'rol_inventory'          => '0'
                );

                // Alle Rollen der Organisation einlesen und ggf. Mitgliedschaft dazu joinen
                $sql = 'SELECT *
                          FROM '.TBL_ROLES.'
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                     LEFT JOIN '.TBL_MEMBERS.'
                            ON mem_rol_id = rol_id
                           AND mem_usr_id = '.$this->getValue('usr_id').'
                           AND mem_begin <= \''.DATE_NOW.'\'
                           AND mem_end    > \''.DATE_NOW.'\'
                         WHERE rol_valid  = 1
                           AND (  cat_org_id = '.$this->organizationId.'
                               OR cat_org_id IS NULL ) ';
                $rolesStatement = $this->db->query($sql);

                while($row = $rolesStatement->fetch())
                {
                    if($row['mem_usr_id'] > 0)
                    {
                        // Sql selects all roles. Only consider roles where user is a member.
                        if($row['mem_leader'] == 1)
                        {
                            // if user is leader in this role than add role id and leader rights to array
                            $this->rolesMembershipLeader[$row['rol_id']] = $row['rol_leader_rights'];

                            // if role leader could assign new members then remember this setting
                            // roles for confirmation of dates should be ignored
                            if($row['cat_name_intern'] !== 'CONFIRMATION_OF_PARTICIPATION'
                            && ($row['rol_leader_rights'] == ROLE_LEADER_MEMBERS_ASSIGN || $row['rol_leader_rights'] == ROLE_LEADER_MEMBERS_ASSIGN_EDIT))
                            {
                                $this->assignRoles = true;
                            }
                        }
                        else
                        {
                            $this->rolesMembershipNoLeader[] = $row['rol_id'];
                        }

                        // add role to membership array
                        $this->rolesMembership[] = $row['rol_id'];

                        // Rechte der Rollen in das Array uebertragen,
                        // falls diese noch nicht durch andere Rollen gesetzt wurden
                        foreach($tmp_roles_rights as $key => $value)
                        {
                            if($value == '0' && $row[$key] == '1')
                            {
                                $tmp_roles_rights[$key] = '1';
                            }
                        }

                        // set flag assignRoles of user can manage roles
                        if($row['rol_assign_roles'] == 1)
                        {
                            $this->assignRoles = true;
                        }

                        // Webmasterflag setzen
                        if($row['rol_webmaster'] == 1)
                        {
                            $this->webmaster = 1;
                        }
                    }

                    // Listenansichtseinstellung merken
                    // Leiter duerfen die Rolle sehen
                    if($row['mem_usr_id'] > 0 && ($row['rol_this_list_view'] > 0 || $row['mem_leader'] == 1))
                    {
                        // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    elseif($row['rol_this_list_view'] == 2)
                    {
                        // andere Rollen anschauen, wenn jeder sie sehen darf
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    else
                    {
                        $this->list_view_rights[$row['rol_id']] = 0;
                    }

                    // Mailrechte setzen
                    // Leiter duerfen der Rolle Mails schreiben
                    if($row['mem_usr_id'] > 0 && ($row['rol_mail_this_role'] > 0 || $row['mem_leader'] == 1))
                    {
                        // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                        $this->role_mail_rights[$row['rol_id']] = 1;
                    }
                    elseif($row['rol_mail_this_role'] >= 2)
                    {
                        // andere Rollen anschauen, wenn jeder sie sehen darf
                        $this->role_mail_rights[$row['rol_id']] = 1;
                    }
                    else
                    {
                        $this->role_mail_rights[$row['rol_id']] = 0;
                    }
                }
                $this->roles_rights = $tmp_roles_rights;

                // ist das Recht 'alle Listen einsehen' gesetzt, dann dies auch im Array bei allen Rollen setzen
                if($this->roles_rights['rol_all_lists_view'])
                {
                    foreach($this->list_view_rights as $key => $value)
                    {
                        $this->list_view_rights[$key] = 1;
                    }
                }

                // ist das Recht 'allen Rollen EMails schreiben' gesetzt, dann dies auch im Array bei allen Rollen setzen
                if($this->roles_rights['rol_mail_to_all'])
                {
                    foreach($this->role_mail_rights as $key => $value)
                    {
                        $this->role_mail_rights[$key] = 1;
                    }
                }

            }

            if($right === '' || $this->roles_rights[$right] == 1)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a valid password is set for the user and return true if the correct password
     * was set. Optional the current session could be updated to a valid login session.
     * @param string $password             The password for the current user. This should not be encoded.
     * @param bool   $setAutoLogin         If set to true then this login will be stored in AutoLogin table
     *                                     and the user doesn't need to login another time with this browser.
     *                                     To use this functionality @b $updateSessionCookies must be set to true.
     * @param bool   $updateSessionCookies The current session will be updated to a valid login.
     *                                     If set to false then the login is only valid for the current script.
     * @param bool   $updateHash           If set to true the code will check if the current password hash uses
     *                                     the best hashing algorithm. If not the password will be rehashed with
     *                                     the new algorithm. If set to false the password will not be rehashed.
     * @param bool   $isWebmaster          If set to true the code will check if the current password hash uses
     * @return true|string Return true if login was successful and a string with the reason why the login failed.
     *                     Possible reasons: SYS_LOGIN_MAX_INVALID_LOGIN
     *                                       SYS_LOGIN_NOT_ACTIVATED
     *                                       SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION
     *                                       SYS_LOGIN_USER_NO_WEBMASTER
     *                                       SYS_LOGIN_USERNAME_PASSWORD_INCORRECT
     */
    public function checkLogin($password, $setAutoLogin = false, $updateSessionCookies = true, $updateHash = true, $isWebmaster = false)
    {
        global $gPreferences, $gCookiePraefix, $gCurrentSession, $gSessionId, $installedDbVersion;

        $invalidLoginCount = $this->getValue('usr_number_invalid');

        // if within 15 minutes 3 wrong login took place -> block user account for 15 minutes
        if ($invalidLoginCount >= 3 && time() - strtotime($this->getValue('usr_date_invalid', 'Y-m-d H:i:s')) < 60 * 15)
        {
            $this->clear();
            return 'SYS_LOGIN_MAX_INVALID_LOGIN';
        }

        $currHash = $this->getValue('usr_password');

        if (PasswordHashing::verify($password, $currHash))
        {
            // Password correct

            // if user is not activated/valid return error message
            if (!$this->getValue('usr_valid'))
            {
                return 'SYS_LOGIN_NOT_ACTIVATED';
            }

            $sqlWebmaster = '';
            // only check for webmaster role if version > 2.3 because before we don't have that flag
            if($isWebmaster && version_compare($installedDbVersion, '2.4.0') === 1)
            {
                $sqlWebmaster = ', rol_webmaster';
            }

            // Check if user is currently member of a role of an organisation
            $sql = 'SELECT DISTINCT mem_usr_id'.$sqlWebmaster.'
                      FROM '.TBL_MEMBERS.'
                INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE mem_usr_id = '.$this->getValue('usr_id').'
                       AND rol_valid  = 1
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'
                       AND cat_org_id = '.$this->organizationId;
            $userStatement = $this->db->query($sql);

            if ($userStatement->rowCount() === 0)
            {
                return 'SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION';
            }

            $userRow = $userStatement->fetch();
            if ($isWebmaster && version_compare($installedDbVersion, '2.4.0') === 1 && $userRow['rol_webmaster'] == 0)
            {
                return 'SYS_LOGIN_USER_NO_WEBMASTER';
            }

            // Rehash password if the hash is outdated and rehashing is enabled
            if ($updateHash && PasswordHashing::needsRehash($currHash))
            {
                $this->setPassword($password);
                $this->save();
            }

            if ($updateSessionCookies)
            {
                $gCurrentSession->setValue('ses_usr_id', $this->getValue('usr_id'));
                $gCurrentSession->save();
            }

            // should the user stayed logged in automatically, than the cookie would expire in one year
            if ($setAutoLogin && $gPreferences['enable_auto_login'] == 1)
            {
                $gCurrentSession->setAutoLogin();
            }
            else
            {
                $this->setValue('usr_last_session_id', null);
            }

            if ($updateSessionCookies)
            {
                // set cookie for session id and remove ports from domain
                $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
                setcookie($gCookiePraefix. '_ID', $gSessionId, 0, '/', $domain, 0);

                // count logins and update login dates
                $this->saveChangesWithoutRights();
                $this->updateLoginData();
            }

            return true;
        }
        else
        {
            // Password wrong

            // log invalid logins
            if ($invalidLoginCount >= 3)
            {
                $this->setValue('usr_number_invalid', 1);
            }
            else
            {
                $this->setValue('usr_number_invalid', $this->getValue('usr_number_invalid') + 1);
            }

            $this->setValue('usr_date_invalid', DATETIME_NOW);
            $this->saveChangesWithoutRights();
            $this->save(false); // don't update timestamp
            $this->clear();

            if ($this->getValue('usr_number_invalid') >= 3)
            {
                return 'SYS_LOGIN_MAX_INVALID_LOGIN';
            }
            else
            {
                return 'SYS_LOGIN_USERNAME_PASSWORD_INCORRECT';
            }
        }
    }

    /**
     * Additional to the parent method the user profile fields and all
     * user rights and role memberships will be initialized
     * @return void
     */
    public function clear()
    {
        parent::clear();

        // die Daten der Profilfelder werden geloescht, die Struktur bleibt
        $this->mProfileFieldsData->clearUserData();

        $this->webmaster = 0;

        // initialize rights arrays
        $this->usersEditAllowed = array();
        $this->renewRoleData();
        $this->saveChangesWithoutRights = false;
    }

    /**
     * returns true if a column of user table or profile fields has changed
     * @return bool
     */
    public function columnsValueChanged()
    {
        if($this->columnsValueChanged || $this->mProfileFieldsData->columnsValueChanged)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * delete all user data of profile fields; user record will not be deleted
     * @return void
     */
    public function deleteUserFieldData()
    {
        $this->db->startTransaction();

        // delete every entry from adm_users_data
        foreach($this->mProfileFieldsData->mUserData as $field)
        {
            $field->delete();
        }

        $this->mProfileFieldsData->mUserData = array();
        $this->db->endTransaction();
    }

    /**
     * Edit an existing role membership of the current user. If the new date range contains
     * a future or past membership of the same role then the two memberships will be merged.
     * In opposite to setRoleMembership this method is useful to end a membership earlier.
     * @param int         $memberId  Id of the current membership that should be edited.
     * @param string      $startDate New start date of the membership. Default will be @b DATE_NOW.
     * @param string      $endDate   New end date of the membership. Default will be @b 9999-12-31
     * @param bool|string $leader    If set to @b 1 then the member will be leader of the role and
     *                               might get more rights for this role.
     * @return bool Return @b true if the membership was successfully edited.
     */
    public function editRoleMembership($memberId, $startDate = DATE_NOW, $endDate = '9999-12-31', $leader = '')
    {
        $minStartDate = $startDate;
        $maxEndDate   = $endDate;

        $member = new TableMembers($this->db, $memberId);

        if($startDate === '' || $startDate === '')
        {
            return false;
        }
        $this->db->startTransaction();

        // search for membership with same role and user and overlapping dates
        $sql = 'SELECT *
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_id    <> '.$memberId.'
                   AND mem_rol_id = '.$member->getValue('mem_rol_id').'
                   AND mem_usr_id = '.$this->getValue('usr_id').'
                   AND mem_begin <= \''.$endDate.'\'
                   AND mem_end   >= \''.$startDate.'\'
              ORDER BY mem_begin ASC';
        $membershipStatement = $this->db->query($sql);

        if($membershipStatement->rowCount() === 1)
        {
            // one record found than update this record
            $row = $membershipStatement->fetch();
            $member->setArray($row);

            // save new start date if an earlier date exists
            if(strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
            {
                $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
            }

            // save new end date if an later date exists
            if(strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0)
            {
                $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
            }
        }
        elseif($membershipStatement->rowCount() > 1)
        {
            // several records found then read min and max date and delete all records
            while($row = $membershipStatement->fetch())
            {
                $member->clear();
                $member->setArray($row);

                // save new start date if an earlier date exists
                if(strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
                {
                    $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
                }

                // save new end date if an later date exists
                if(strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0)
                {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }

                // delete existing entry because a new overlapping entry will be created
                $member->delete();
            }
            $member->clear();
        }

        if(strcmp($minStartDate, $maxEndDate) > 0)
        {
            // if start date is greater than end date than delete membership
            if($member->getValue('mem_id') > 0)
            {
                $member->delete();
            }
        }
        else
        {
            // save membership to database
            $member->setValue('mem_begin', $minStartDate);
            $member->setValue('mem_end', $maxEndDate);
            if($leader !== '')
            {
                $member->setValue('mem_leader', $leader);
            }
            $member->save();
        }

        $this->db->endTransaction();
        $this->renewRoleData();

        return true;
    }

    /**
     * Creates an array with all roles where the user has the right to mail them
     * @return array Array with role ids where user has the right to mail them
     */
    public function getAllMailRoles()
    {
        $visibleRoles = array();
        $this->checkRolesRight();

        foreach($this->role_mail_rights as $role => $right)
        {
            if($right == 1)
            {
                $visibleRoles[] = $role;
            }
        }
        return $visibleRoles;
    }

    /**
     * Creates an array with all roles where the user has the right to view them
     * @return array Array with role ids where user has the right to view them
     */
    public function getAllVisibleRoles()
    {
        $visibleRoles = array();
        $this->checkRolesRight();

        foreach($this->list_view_rights as $role => $right)
        {
            if($right == 1)
            {
                $visibleRoles[] = $role;
            }
        }
        return $visibleRoles;
    }

    /**
     * Returns the id of the organization this user object has been assigned.
     * This is in the default case the default organization of the config file.
     * @return int Returns the id of the organization this user object has been assigned
     */
    public function getOrganization()
    {
        if($this->organizationId > 0)
        {
            return $this->organizationId;
        }
        else
        {
            return 0;
        }
    }

    /**
     * Returns an array with all role ids where the user is a member.
     * @return int[] Returns an array with all role ids where the user is a member.
     */
    public function getRoleMemberships()
    {
        $this->checkRolesRight();
        return $this->rolesMembership;
    }

    /**
     * Returns an array with all role ids where the user is a member
     * and not a leader of the role.
     * @return int[] Returns an array with all role ids where the user is a member
     *         and not a leader of the role.
     */
    public function getRoleMembershipsNoLeader()
    {
        $this->checkRolesRight();
        return $this->rolesMembershipNoLeader;
    }

    /**
     * Get the value of a column of the database table if the column has the praefix @b usr_
     * otherwise the value of the profile field of the table adm_user_data will be returned.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read or the internal unique profile field name
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return mixed Returns the value of the database column or the value of adm_user_fields
     *               If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @par Examples
     * @code  // reads data of adm_users column
     * $loginname = $gCurrentUser->getValue('usr_login_name');
     * // reads data of adm_user_fields
     * $email = $gCurrentUser->getValue('EMAIL'); @endcode
     */
    public function getValue($columnName, $format = '')
    {
        global $gPreferences;

        if(strpos($columnName, 'usr_') === 0)
        {
            if($columnName === 'usr_photo' && $gPreferences['profile_photo_storage'] == 0
            && file_exists(SERVER_PATH.'/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg'))
            {
                return file_get_contents(SERVER_PATH.'/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg');
            }
            else
            {
                return parent::getValue($columnName, $format);
            }
        }
        else
        {
            return $this->mProfileFieldsData->getValue($columnName, $format);
        }
    }

    /**
     * Creates a vcard with all data of this user object @n
     * (Windows XP address book can't process utf8, so vcard output is iso-8859-1)
     * @param bool $allowedToEditProfile If set to @b true than logged in user is allowed to edit profiles
     *                                   so he can see more data in the vcard
     * @return string Returns the vcard as a string
     */
    public function getVCard($allowedToEditProfile = false)
    {
        global $gPreferences;

        $vcard  = 'BEGIN:VCARD'."\r\n";
        $vcard .= 'VERSION:2.1'."\r\n";
        if($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('FIRST_NAME', 'usf_hidden') == 0))
        {
            $vcard .= 'N;CHARSET=ISO-8859-1:' . utf8_decode($this->getValue('LAST_NAME', 'database')). ';'. utf8_decode($this->getValue('FIRST_NAME', 'database')) . ";;;\r\n";
        }
        if($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('LAST_NAME', 'usf_hidden') == 0))
        {
            $vcard .= 'FN;CHARSET=ISO-8859-1:'. utf8_decode($this->getValue('FIRST_NAME')) . ' '. utf8_decode($this->getValue('LAST_NAME')) . "\r\n";
        }
        if (strlen($this->getValue('usr_login_name')) > 0)
        {
            $vcard .= 'NICKNAME;CHARSET=ISO-8859-1:' . utf8_decode($this->getValue('usr_login_name')). "\r\n";
        }
        if (strlen($this->getValue('PHONE')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('PHONE', 'usf_hidden') == 0)))
        {
            $vcard .= 'TEL;HOME;VOICE:' . $this->getValue('PHONE'). "\r\n";
        }
        if (strlen($this->getValue('MOBILE')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('MOBILE', 'usf_hidden') == 0)))
        {
            $vcard .= 'TEL;CELL;VOICE:' . $this->getValue('MOBILE'). "\r\n";
        }
        if (strlen($this->getValue('FAX')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('FAX', 'usf_hidden') == 0)))
        {
            $vcard .= 'TEL;HOME;FAX:' . $this->getValue('FAX'). "\r\n";
        }
        if($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('ADDRESS', 'usf_hidden') == 0 && $this->mProfileFieldsData->getProperty('CITY', 'usf_hidden') == 0
        && $this->mProfileFieldsData->getProperty('POSTCODE', 'usf_hidden') == 0  && $this->mProfileFieldsData->getProperty('COUNTRY', 'usf_hidden') == 0))
        {
            $vcard .= 'ADR;CHARSET=ISO-8859-1;HOME:;;' . utf8_decode($this->getValue('ADDRESS', 'database')). ';' . utf8_decode($this->getValue('CITY', 'database')). ';;' . utf8_decode($this->getValue('POSTCODE', 'database')). ';' . utf8_decode($this->getValue('COUNTRY', 'database')). "\r\n";
        }
        if (strlen($this->getValue('WEBSITE')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('WEBSITE', 'usf_hidden') == 0)))
        {
            $vcard .= 'URL;HOME:' . $this->getValue('WEBSITE'). "\r\n";
        }
        if (strlen($this->getValue('BIRTHDAY')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('BIRTHDAY', 'usf_hidden') == 0)))
        {
            $vcard .= 'BDAY:' . $this->getValue('BIRTHDAY', 'Ymd') . "\r\n";
        }
        if (strlen($this->getValue('EMAIL')) > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('EMAIL', 'usf_hidden') == 0)))
        {
            $vcard .= 'EMAIL;PREF;INTERNET:' . $this->getValue('EMAIL'). "\r\n";
        }
        if (file_exists(SERVER_PATH.'/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg') && $gPreferences['profile_photo_storage'] == 1)
        {
            $img_handle = fopen(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg', 'rb');
            $vcard .= 'PHOTO;ENCODING=BASE64;TYPE=JPEG:'.base64_encode(fread($img_handle, filesize(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg'))). "\r\n";
            fclose($img_handle);
        }
        if (strlen($this->getValue('usr_photo')) > 0 && $gPreferences['profile_photo_storage'] == 0)
        {
            $vcard .= 'PHOTO;ENCODING=BASE64;TYPE=JPEG:'.base64_encode($this->getValue('usr_photo')). "\r\n";
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($this->getValue('GENDER') > 0
        && ($allowedToEditProfile || (!$allowedToEditProfile && $this->mProfileFieldsData->getProperty('GENDER', 'usf_hidden') == 0)))
        {
            if($this->getValue('GENDER') == 1)
            {
                $wab_gender = 2;
            }
            else
            {
                $wab_gender = 1;
            }
            $vcard .= 'X-WAB-GENDER:' . $wab_gender . "\r\n";
        }
        if (strlen($this->getValue('usr_timestamp_change')) > 0)
        {
            $vcard .= 'REV:' . $this->getValue('usr_timestamp_change', 'ymdThis') . "\r\n";
        }

        $vcard .= 'END:VCARD'."\r\n";
        return $vcard;
    }

     /**
      * Checks if the current user is allowed to edit the profile of the user of the parameter.
      * If will check if user can generally edit all users or if he is a group leader and can edit users
      * of a special role where @b $user is a member or if it's the own profile and he could edit this.
      * @param object $user            User object of the user that should be checked if the current user can edit his profile.
      * @param bool   $checkOwnProfile If set to @b false than this method don't check the role right to edit the own profile.
      * @return bool Return @b true if the current user is allowed to edit the profile of the user from @b $user.
      */
     public function hasRightEditProfile(&$user, $checkOwnProfile = true)
    {
        $returnValue = false;

        if(is_object($user))
        {
            // edit own profile ?
            if($user->getValue('usr_id') === $this->getValue('usr_id')
            && $this->getValue('usr_id') > 0 && $checkOwnProfile)
            {
                $edit_profile = $this->checkRolesRight('rol_profile');

                if($edit_profile == 1)
                {
                    return true;
                }
            }

            // first check if user is in cache
            if(array_key_exists($user->getValue('usr_id'), $this->usersEditAllowed))
            {
                return $this->usersEditAllowed[$user->getValue('usr_id')];
            }

            if($this->editUsers())
            {
                $returnValue = true;
            }
            else
            {
                if(count($this->rolesMembershipLeader) > 0)
                {
                    // leaders are not allowed to edit profiles of other leaders but to edit their own profile
                    if($user->getValue('usr_id') == $this->getValue('usr_id'))
                    {
                        // check if current user is a group leader of a role where $user is only a member
                        $rolesMembership = $user->getRoleMemberships();
                    }
                    else
                    {
                        // check if current user is a group leader of a role where $user is only a member and not a leader
                        $rolesMembership = $user->getRoleMembershipsNoLeader();
                    }

                    foreach($this->rolesMembershipLeader as $roleId => $leaderRights)
                    {
                        // is group leader of role and has the right to edit users ?
                        if(in_array($roleId, $rolesMembership, true) && $leaderRights > 1)
                        {
                            $returnValue = true;
                        }
                    }
                }
            }

            // add result into cache
            $this->usersEditAllowed[$user->getValue('usr_id')] = $returnValue;
        }

        return $returnValue;
    }

    /**
     * Checks if the current user has the right to send an email to the role.
     * @param int $roleId Id of the role that should be checked.
     * @return bool Return @b true if the user has the right to send an email to the role.
     */
    public function hasRightSendMailToRole($roleId)
    {
        if(is_numeric($roleId))
        {
            // Abfrage ob der User durch irgendeine Rolle das Recht bekommt alle Listen einzusehen
            if($this->checkRolesRight('rol_mail_to_all'))
            {
                return true;
            }
            else
            {
                // Falls er das Recht nicht hat Kontrolle ob fuer eine bestimmte Rolle
                if(isset($this->role_mail_rights[$roleId]) && $this->role_mail_rights[$roleId] > 0)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if the current user is allowed to view the profile of the user of the parameter.
     * If will check if user has edit rights with method editProfile or if the user is a member
     * of a role where the current user has the right to view profiles.
     * @param object $user User object of the user that should be checked if the current user can view his profile.
     * @return bool Return @b true if the current user is allowed to view the profile of the user from @b $user.
     */
    public function hasRightViewProfile($user)
    {
        if(is_object($user))
        {
            // Hat ein User Profileedit rechte, darf er es natuerlich auch sehen
            if($this->hasRightEditProfile($user))
            {
                return true;
            }
            else
            {
                // Benutzer, die alle Listen einsehen duerfen, koennen auch alle Profile sehen
                if($this->checkRolesRight('rol_all_lists_view'))
                {
                    return true;
                }
                else
                {
                    $sql = 'SELECT rol_id, rol_this_list_view
                              FROM '.TBL_MEMBERS.'
                        INNER JOIN '.TBL_ROLES.'
                                ON rol_id = mem_rol_id
                        INNER JOIN '.TBL_CATEGORIES.'
                                ON cat_id = rol_cat_id
                             WHERE rol_valid  = 1
                               AND mem_begin <= \''.DATE_NOW.'\'
                               AND mem_end    > \''.DATE_NOW.'\'
                               AND mem_usr_id = '.$user->getValue('usr_id').'
                               AND (  cat_org_id = '.$this->organizationId.'
                                   OR cat_org_id IS NULL ) ';
                    $listViewStatement = $this->db->query($sql);

                    if($listViewStatement->rowCount() > 0)
                    {
                        while($row = $listViewStatement->fetch())
                        {
                            if($row['rol_this_list_view'] == 2)
                            {
                                // alle angemeldeten Benutzer duerfen Rollenlisten/-profile sehen
                                return true;
                            }
                            elseif($row['rol_this_list_view'] == 1 && isset($this->list_view_rights[$row['rol_id']]))
                            {
                                // nur Rollenmitglieder duerfen Rollenlisten/-profile sehen
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if the user of this object has the right to view the role that is set in the parameter.
     * @param int|string $roleId The id of the role that should be checked.
     * @return bool Return @b true if the user has the right to view the role otherwise @b false.
     */
    public function hasRightViewRole($roleId)
    {
        // if user has right to view all lists then he could also view this role
        if($this->checkRolesRight('rol_all_lists_view'))
        {
            return true;
        }
        else
        {
            // check if user has the right to view this role
            if(isset($this->list_view_rights[$roleId]) && $this->list_view_rights[$roleId] > 0)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * check if user is leader of a role
     * @param int|string $roleId
     * @return bool
     */
    public function isLeaderOfRole($roleId)
    {
        if(array_key_exists($roleId, $this->rolesMembershipLeader))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * check if user is member of a role
     * @param int|string $roleId
     * @return bool
     */
    public function isMemberOfRole($roleId)
    {
        if(in_array($roleId, $this->rolesMembership, true))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Checks if the user is assigned to the role @b Webmaster
     * @return bool Returns @b true if the user is a member of the role @b Webmaster
     */
    public function isWebmaster()
    {
        $this->checkRolesRight();
        return $this->webmaster;
    }

    /**
     * If this method is called than all further calls of method @b setValue will not check the values.
     * The values will be stored in database without any inspections!
     * @return void
     */
    public function noValueCheck()
    {
        $this->mProfileFieldsData->noValueCheck();
    }

    /**
     * Reads a user record out of the table adm_users in database selected by the unique user id.
     * Also all profile fields of the object @b mProfileFieldsData will be read.
     * @param int|string $userId Unique id of the user that should be read
     * @return bool Returns @b true if one record is found
     */
    public function readDataById($userId)
    {
        if(parent::readDataById($userId))
        {
            // read data of all user fields from current user
            $this->mProfileFieldsData->readUserData($userId, $this->organizationId);
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Initialize all rights and role membership arrays so that all rights and
     * role memberships will be read from database if another method needs them
     * @return void
     */
    public function renewRoleData()
    {
        // initialize rights arrays
        $this->roles_rights     = array();
        $this->list_view_rights = array();
        $this->role_mail_rights = array();
        $this->rolesMembership  = array();
        $this->rolesMembershipLeader   = array();
        $this->rolesMembershipNoLeader = array();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's a new
     * record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * First save recordset and then save all user fields. After that the session of this got a renew for the user object.
     * If the user doesn't have the right to save data of this user than an exception will be thrown.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset
     *                                if table has columns like @b usr_id_create or @b usr_id_changed
     * @throws AdmException
     * @return bool
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession, $gCurrentUser;

        $fields_changed = $this->columnsValueChanged;
        $updateCreateUserId = false;

        // if current user is new or is allowed to edit this user than save data
        if($this->getValue('usr_id') == 0 || $gCurrentUser->hasRightEditProfile($this) || $this->saveChangesWithoutRights)
        {
            $this->db->startTransaction();

            // if new user then set create id
            if($this->getValue('usr_id') == 0 && $gCurrentUser->getValue('usr_id') == 0)
            {
                $updateCreateUserId = true;
                $updateFingerPrint  = false;
            }

            // if value of a field changed then update timestamp of user object
            if($this->mProfileFieldsData->columnsValueChanged)
            {
                $this->columnsValueChanged = true;
            }

            $returnValue = parent::save($updateFingerPrint);

            // if this was an registration then set this user id to create user id
            if($updateCreateUserId)
            {
                $this->setValue('usr_timestamp_create', DATETIME_NOW);
                $this->setValue('usr_usr_id_create', $this->getValue('usr_id'));
                $returnValue = parent::save($updateFingerPrint);
            }

            // save data of all user fields
            $this->mProfileFieldsData->saveUserData($this->getValue('usr_id'));

            if($fields_changed && is_object($gCurrentSession))
            {
                // now set user object in session of that user to invalid,
                // because he has new data and maybe new rights
                $gCurrentSession->renewUserObject($this->getValue('usr_id'));
            }
            $this->db->endTransaction();

            return $returnValue;
        }
        else
        {
            throw new AdmException('The profile data of user '.$this->getValue('FIRST_NAME').' '
                .$this->getValue('LAST_NAME').' could not be saved because you don\'t have the right to do this.');
        }
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
     * The organization is used to read the rights of the user. If @b setOrganization isn't called
     * than the default organization @b gCurrentOrganization is set for the current user object.
     * @param int $organizationId Id of the organization
     * @return void
     */
    public function setOrganization($organizationId)
    {
        if(is_numeric($organizationId))
        {
            $this->organizationId = $organizationId;
            $this->roles_rights   = array();
        }
    }

    /**
     * Create a new membership to a role for the current user. If the date range contains
     * a future or past membership of the same role then the two memberships will be merged.
     * In opposite to setRoleMembership this method can't be used to end a membership earlier!
     * @param int         $roleId    Id of the role for which the membership should be set.
     * @param string      $startDate Start date of the membership. Default will be @b DATE_NOW.
     * @param string      $endDate   End date of the membership. Default will be @b 31.12.9999
     * @param bool|string $leader    If set to @b 1 then the member will be leader of the role and
     *                               might get more rights for this role.
     * @return bool Return @b true if the membership was successfully added.
     */
    public function setRoleMembership($roleId, $startDate = DATE_NOW, $endDate = '9999-12-31', $leader = '')
    {
        $minStartDate = $startDate;
        $maxEndDate   = $endDate;

        $member = new TableMembers($this->db);

        if($startDate === '' || $endDate === '')
        {
            return false;
        }
        $this->db->startTransaction();

        // subtract 1 day from start date so that we find memberships that ends yesterday
        // these memberships can be continued with new date
        $oneDayDateInterval = new DateInterval('P1D');

        $startDate = DateTime::createFromFormat('Y-m-d', $startDate)->sub($oneDayDateInterval)->format('Y-m-d');
        // add 1 to max date because we subtract one day if a membership ends
        if($endDate !== '9999-12-31')
        {
            $endDate = DateTime::createFromFormat('Y-m-d', $endDate)->add($oneDayDateInterval)->format('Y-m-d');
        }

        // search for membership with same role and user and overlapping dates
        $sql = 'SELECT *
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = '.$roleId.'
                   AND mem_usr_id = '.$this->getValue('usr_id').'
                   AND mem_begin <= \''.$endDate.'\'
                   AND mem_end   >= \''.$startDate.'\'
              ORDER BY mem_begin ASC';
        $membershipStatement = $this->db->query($sql);

        if($membershipStatement->rowCount() === 1)
        {
            // one record found than update this record
            $row = $membershipStatement->fetch();
            $member->setArray($row);

            // save new start date if an earlier date exists
            if(strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
            {
                $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
            }

            // save new end date if an later date exists
            // but only if end date is greater than the begin date otherwise the membership should be deleted
            if(strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0
            && strcmp($member->getValue('mem_begin', 'Y-m-d'), $maxEndDate) < 0)
            {
                $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
            }
        }
        elseif($membershipStatement->rowCount() > 1)
        {
            // several records found then read min and max date and delete all records
            while($row = $membershipStatement->fetch())
            {
                $member->clear();
                $member->setArray($row);

                // save new start date if an earlier date exists
                if(strcmp($minStartDate, $member->getValue('mem_begin', 'Y-m-d')) > 0)
                {
                    $minStartDate = $member->getValue('mem_begin', 'Y-m-d');
                }

                // save new end date if an later date exists
                if(strcmp($member->getValue('mem_end', 'Y-m-d'), $maxEndDate) > 0)
                {
                    $maxEndDate = $member->getValue('mem_end', 'Y-m-d');
                }

                // delete existing entry because a new overlapping entry will be created
                $member->delete();
            }
            $member->clear();
        }

        if(strcmp($minStartDate, $maxEndDate) > 0)
        {
            // if start date is greater than end date than delete membership
            if($member->getValue('mem_id') > 0)
            {
                $member->delete();
            }
            $returnStatus = true;
        }
        else
        {
            // save membership to database
            $member->setValue('mem_rol_id', $roleId);
            $member->setValue('mem_usr_id', $this->getValue('usr_id'));
            $member->setValue('mem_begin', $minStartDate);
            $member->setValue('mem_end', $maxEndDate);
            if($leader !== '')
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
     * Set a new value for a column of the database table if the column has the prefix @b usr_
     * otherwise the value of the profile field of the table adm_user_data will set.
     * If the user log is activated than the change of the value will be logged in @b adm_user_log.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                            internal unique profile field name
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will
     *                            not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     * @par Examples
     * @code  // set data of adm_users column
     *                           $gCurrentUser->getValue('usr_login_name', 'Admidio');
     *                           // reads data of adm_user_fields
     *                           $gCurrentUser->getValue('EMAIL', 'webmaster@admidio.org'); @endcode
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gCurrentUser, $gPreferences;

        $returnCode    = true;
        $oldFieldValue = $this->mProfileFieldsData->getValue($columnName, 'database');

        if(strpos($columnName, 'usr_') !== 0)
        {
            // user data from adm_user_fields table

            // only to a update if value has changed
            if(strcmp($newValue, $oldFieldValue) !== 0)
            {
                // Disabled fields can only be edited by users with the right "edit_users" except on registration.
                // Here is no need to check hidden fields because we check on save() method that only users who
                // can edit the profile are allowed to save and change data.
                if($this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') == 0
                || ($this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') == 1
                   && $gCurrentUser->hasRightEditProfile($this, false))
                || ($gCurrentUser->getValue('usr_id') == 0 && $this->getValue('usr_id') == 0))
                {
                    $returnCode = $this->mProfileFieldsData->setValue($columnName, $newValue);
                }
            }
        }
        else
        {
            // users data from adm_users table
            $returnCode = parent::setValue($columnName, $newValue);
        }
        $newFieldValue = $this->mProfileFieldsData->getValue($columnName, 'database');

        // Nicht alle Aenderungen werden geloggt. Ausnahmen:
        // usr_id ist Null, wenn der User neu angelegt wird. Das wird bereits dokumentiert.
        // Felder, die mit usr_ beginnen, werden nicht geloggt
        // Falls die Feldwerte sich nicht geaendert haben, wird natuerlich ebenfalls nicht geloggt
        if($gPreferences['profile_log_edit_fields'] == 1 && $this->getValue('usr_id') != 0
        && strpos($columnName, 'usr_') === false && $newFieldValue !== $oldFieldValue
        && $returnCode === true)
        {
            $logEntry = new TableAccess($this->db, TBL_USER_LOG, 'usl');
            $logEntry->setValue('usl_usr_id', $this->getValue('usr_id'));
            $logEntry->setValue('usl_usf_id', $this->mProfileFieldsData->getProperty($columnName, 'usf_id'));
            $logEntry->setValue('usl_value_old', $oldFieldValue);
            $logEntry->setValue('usl_value_new', $newFieldValue);
            $logEntry->setValue('usl_comm', '');
            $logEntry->save();
        }
        return $returnCode;
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
     * @return bool Return @b true if the user can assign members to at least one role.
     */
    public function assignRoles()
    {
        $this->checkRolesRight();
        return $this->assignRoles;
    }

    /**
     * Checks if the user has the right to manage roles. Therefore he must be a member
     * of a role with the right @b rol_manage_roles.
     * @return bool Return @b true if the user can manage roles.
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

    /**
     * Funktion prueft, ob der angemeldete User das Inventory verwalten darf
     * @return bool
     */
    public function editInventory()
    {
        return $this->checkRolesRight('rol_inventory');
    }
}
