<?php
namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SystemMail;
use Admidio\Messages\Entity\Message;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Role;
use Admidio\Session\Entity\Session;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Changelog\Entity\LogChanges;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\QRServerProvider;

/**
 * @brief Class handle role rights, cards and other things of users
 *
 * Handles all the user data and the rights. This is used for the current login user and for
 * other users of the database.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class User extends Entity
{
    public const MAX_INVALID_LOGINS = 3;

    /**
     * @var bool
     */
    protected bool $administrator;
    /**
     * @var ProfileFields object with current user field structure
     */
    protected ProfileFields $mProfileFieldsData;
    /**
     * @var array<string,bool> Array with all roles rights and the status of the current user e.g. array('rol_assign_roles' => false, 'rol_approve_users' => true ...)
     */
    protected array $rolesRights = array();
    /**
     * @var array<int,int> Array with all roles e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected array $rolesViewMemberships = array();
    /**
     * @var array<int,int> Array with all roles e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected array $rolesViewProfiles = array();
    /**
     * @var array<int,string> Array with all UUIDs of roles e.g. array(0 => role_uuid_1, 1 => role_uuid_2, ...)
     */
    protected array $rolesViewMembershipsUUID = array();
    /**
     * @var array<int,string> Array with all UUIDs of roles e.g. array(0 => role_uuid_1, 1 => role_uuid_2, ...)
     */
    protected array $rolesViewProfilesUUID = array();
    /**
     * @var array<int,int> Array with all roles e.g. array(0 => role_id_1, 1 => role_id_2, ...)
     */
    protected array $rolesWriteMails = array();
    /**
     * @var array<int,string> Array with all UUIDs of roles e.g. array(0 => role_uuid_1, 1 => role_uuid_2, ...)
     */
    protected array $rolesWriteMailsUUID = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned
     */
    protected array $rolesMembership = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned and is leader (key = role_id; value = rol_leader_rights)
     */
    protected array $rolesMembershipLeader = array();
    /**
     * @var array<int,int> Array with all roles who the user is assigned and is not a leader of the role
     */
    protected array $rolesMembershipNoLeader = array();
    /**
     * @var int the organization for which the rights are read, could be changed with method **setOrganization**
     */
    protected int $organizationId;
    /**
     * @var bool Flag if the user has the right to assign at least one role
     */
    protected bool $assignRoles;
    /**
     * @var array<int,bool> Array with all user ids where the current user is allowed to edit the profile.
     */
    protected array $usersEditAllowed = array();
    /**
     * @var array<int,array<string,int|bool>> Array with all users to whom the current user has a relationship
     */
    protected array $relationships = array();
    /**
     * @var bool Flag if relationships for this user were checked
     */
    protected bool $relationshipsChecked = false;
    /**
     * @var bool Flag if the changes to the user data should be handled by the ChangeNotification service.
     */
    protected bool $changeNotificationEnabled;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param ProfileFields|null $userFields An object of the ProfileFields class with the profile field structure
     *                                  of the current organization. This could be the default object **$gProfileFields**.
     * @param int $userId The id of the user who should be loaded. If id isn't set than an empty
     *                                  object with no specific user is created.
     * @throws Exception
     */
    public function __construct(Database $database, ProfileFields $userFields = null, int $userId = 0)
    {
        $this->changeNotificationEnabled = true;

        if ($userFields !== null) {
            $this->mProfileFieldsData = clone $userFields; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            global $gProfileFields;
            $this->mProfileFieldsData = clone $gProfileFields; // create explicit a copy of the object (param is in PHP5 a reference)
        }

        $this->organizationId = $GLOBALS['gCurrentOrgId'];

        parent::__construct($database, TBL_USERS, 'usr', $userId);
    }

    /**
     * Checks if the current user is allowed to edit a profile field of the user of the parameter.
     * @param User $user User object of the user that should be checked if the current user can view his profile field.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @return bool Return true if the current user is allowed to view this profile field of **$user**.
     * @throws Exception
     */
    public function allowedEditProfileField(self $user, string $fieldNameIntern): bool
    {
        return $this->hasRightEditProfile($user) && $user->mProfileFieldsData->isEditable($fieldNameIntern, $this->hasRightEditProfile($user));
    }

    /**
     * Checks if the current user is allowed to view a profile field of the user of the parameter.
     * It will check if the current user could view the profile field category. Within the own profile
     * you can view profile fields of hidden categories. We will also check if the current user
     * could edit the **$user** profile so the current user could also view hidden fields.
     * @param User $user User object of the user that should be checked if the current user can view his profile field.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should be checked.
     * @return bool Return true if the current user is allowed to view this profile field of **$user**.
     * @throws Exception
     */
    public function allowedViewProfileField(self $user, string $fieldNameIntern): bool
    {
        return $user->mProfileFieldsData->isVisible($fieldNameIntern, $this->hasRightEditProfile($user));
    }

    /**
     * Assign the user to all roles that have set the flag **rol_default_registration**.
     * These flag should be set if you want that every new user should get this role.
     * @throws Exception
     */
    public function assignDefaultRoles()
    {
        global $gMessage, $gL10n;

        $this->db->startTransaction();

        // every user will get the default roles for registration, if the current user has the right to assign roles
        // than the role assignment dialog will be shown
        $sql = 'SELECT rol_id
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_default_registration = true
                   AND cat_org_id = ? -- $this->organizationId';
        $defaultRolesStatement = $this->db->queryPrepared($sql, array($this->organizationId));

        if ($defaultRolesStatement->rowCount() === 0) {
            $gMessage->show($gL10n->get('SYS_NO_DEFAULT_ROLE_FOR_USER'));
            // => EXIT
        }

        while ($rolId = $defaultRolesStatement->fetchColumn()) {
            // starts a membership for role from now
            $role = new Role($this->db, $rolId);
            $role->startMembership($this->getValue('usr_id'));
        }

        $this->db->endTransaction();
    }

    /**
     * Method reads all relationships of the user and will store them in an array. The
     * relationship property if the user can edit the profile of the other user will be stored
     * for later checks within this class.
     * @return bool Return true if relationships could be checked.
     * @throws Exception
     */
    private function checkRelationshipsRights(): bool
    {
        global $gSettingsManager;

        if ((int) $this->getValue('usr_id') === 0 || !$gSettingsManager->getBool('contacts_user_relations_enabled')) {
            return false;
        }

        if (!$this->relationshipsChecked && count($this->relationships) === 0) {
            // read all relations of the current user
            $sql = 'SELECT urt_id, urt_edit_user, ure_usr_id2
                      FROM ' . TBL_USER_RELATIONS . '
                INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                        ON urt_id = ure_urt_id
                     WHERE ure_usr_id1  = ? -- $this->getValue(\'usr_id\') ';
            $queryParams = array((int) $this->getValue('usr_id'));
            $relationsStatement = $this->db->queryPrepared($sql, $queryParams);

            while ($row = $relationsStatement->fetch()) {
                $this->relationships[] = array(
                    'relation_type' => (int) $row['urt_id'],
                    'user_id' => (int) $row['ure_usr_id2'],
                    'edit_user' => (bool) $row['urt_edit_user']
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
     * @param string|null $right The database column name of the right that should be checked. If this param
     *                      is not set then only the arrays are filled.
     * @return bool Return true if a special right should be checked and the user has this right.
     * @throws Exception
     */
    public function checkRolesRight(string $right = null): bool
    {
        $sqlFetchedRows = array();

        if ((int) $this->getValue('usr_id') === 0) {
            return false;
        }

        if (count($this->rolesRights) === 0) {
            $this->assignRoles = false;
            $tmpRolesRights = array(
                'rol_all_lists_view' => false,
                'rol_announcements' => false,
                'rol_approve_users' => false,
                'rol_assign_roles' => false,
                'rol_documents_files' => false,
                'rol_events' => false,
                'rol_edit_user' => false,
                'rol_forum_admin' => false,
                'rol_mail_to_all' => false,
                'rol_photo' => false,
                'rol_profile' => false,
                'rol_weblinks' => false
            );

            // read all roles of the organization and join the membership if user is member of that role
            $sql = 'SELECT *
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                 LEFT JOIN ' . TBL_MEMBERS . '
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
                    if ($row['mem_leader']) {
                        $rolLeaderRights = (int) $row['rol_leader_rights'];

                        // if user is leader in this role than add role id and leader rights to array
                        $this->rolesMembershipLeader[$roleId] = $rolLeaderRights;

                        // if role leader could assign new members then remember this setting
                        // roles for confirmation of events should be ignored
                        if (
                            $row['cat_name_intern'] !== 'EVENTS'
                            && ($rolLeaderRights === Role::ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
                        ) {
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
                    if ((int) $row['rol_assign_roles'] === 1) {
                        $this->assignRoles = true;
                    }

                    // set administrator flag
                    if ((int) $row['rol_administrator'] === 1) {
                        $this->administrator = true;
                    }
                }
            }
            $this->rolesRights = $tmpRolesRights;

            // go again through all roles, but now the rolesRights are set and can be evaluated
            foreach ($sqlFetchedRows as $sqlRow) {
                $roleId = (int) $sqlRow['rol_id'];
                $roleUUID = $sqlRow['rol_uuid'];
                $memLeader = (bool) $sqlRow['mem_leader'];

                if (array_key_exists('rol_view_memberships', $sqlRow)) {
                    // Remember roles view setting
                    if ((int) $sqlRow['rol_view_memberships'] === Role::VIEW_ROLE_MEMBERS && $sqlRow['mem_usr_id'] > 0) {
                        // only role members are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                        $this->rolesViewMembershipsUUID[] = $roleUUID;
                    } elseif ((int) $sqlRow['rol_view_memberships'] === Role::VIEW_LOGIN_USERS) {
                        // all registered users are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                        $this->rolesViewMembershipsUUID[] = $roleUUID;
                    } elseif ((int) $sqlRow['rol_view_memberships'] === Role::VIEW_LEADERS && $memLeader) {
                        // only leaders are allowed to view memberships
                        $this->rolesViewMemberships[] = $roleId;
                        $this->rolesViewMembershipsUUID[] = $roleUUID;
                    } elseif ($this->rolesRights['rol_all_lists_view']) {
                        // user is allowed to view all roles than also view this membership
                        $this->rolesViewMemberships[] = $roleId;
                        $this->rolesViewMembershipsUUID[] = $roleUUID;
                    }

                    // Remember profile view setting
                    if ((int) $sqlRow['rol_view_members_profiles'] === Role::VIEW_ROLE_MEMBERS && $sqlRow['mem_usr_id'] > 0) {
                        // only role members are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                        $this->rolesViewProfilesUUID[] = $roleUUID;
                    } elseif ((int) $sqlRow['rol_view_members_profiles'] === Role::VIEW_LOGIN_USERS) {
                        // all registered users are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                        $this->rolesViewProfilesUUID[] = $roleUUID;
                    } elseif ((int) $sqlRow['rol_view_members_profiles'] === Role::VIEW_LEADERS && $memLeader) {
                        // only leaders are allowed to view memberships
                        $this->rolesViewProfiles[] = $roleId;
                        $this->rolesViewProfilesUUID[] = $roleUUID;
                    } elseif ($this->rolesRights['rol_all_lists_view']) {
                        // user is allowed to view all roles than also view this membership
                        $this->rolesViewProfiles[] = $roleId;
                        $this->rolesViewProfilesUUID[] = $roleUUID;
                    }
                }

                // Set mail permissions
                if ($sqlRow['mem_usr_id'] > 0 && ($sqlRow['rol_mail_this_role'] > 0 || $memLeader)) {
                    // Leaders are allowed to write mails to the role
                    // Membership to the role and this is not locked, then look at it
                    $this->rolesWriteMails[] = $roleId;
                    $this->rolesWriteMailsUUID[] = $roleUUID;
                } elseif ($sqlRow['rol_mail_this_role'] >= 2) {
                    // look at other roles when everyone is allowed to see them
                    $this->rolesWriteMails[] = $roleId;
                    $this->rolesWriteMailsUUID[] = $roleUUID;
                } elseif ($this->rolesRights['rol_mail_to_all']) {
                    // user is allowed to write emails to all roles than also write to this role
                    $this->rolesWriteMails[] = $roleId;
                    $this->rolesWriteMailsUUID[] = $roleUUID;
                }
            }
        }

        return $right === null || $this->rolesRights[$right];
    }

    /**
     * Check if a valid password is set for the user and return true if the correct password
     * was set. Optional the current session could be updated to a valid login session.
     * @param string $password The password for the current user. This should not be encoded.
     * @param bool $setAutoLogin If set to true then this login will be stored in AutoLogin table
     *                                     and the user doesn't need login to another time with this browser.
     *                                     To use this functionality **$updateSessionCookies** must be set to true.
     * @param bool $updateSessionCookies The current session will be updated to a valid login.
     *                                     If set to false then the login is only valid for the current script.
     * @param bool $updateHash If set to true the code will check if the current password hash uses
     *                                     the best hashing algorithm. If not the password will be rehashed with
     *                                     the new algorithm. If set to false the password will not be rehashed.
     * @param bool $isAdministrator If set to true check if user is admin of organization.
     * @return true Return true if login was successful
     * @throws Exception in case of errors. exception->text contains a string with the reason why the login failed.
     * @throws Exception
     *                     Possible reasons: SYS_LOGIN_MAX_INVALID_LOGIN
     *                                       SYS_LOGIN_NOT_ACTIVATED
     *                                       SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION
     *                                       SYS_LOGIN_USER_NO_ADMINISTRATOR
     *                                       SYS_LOGIN_USERNAME_PASSWORD_INCORRECT
     *                                       SYS_SECURITY_CODE_INVALID
     */
    public function checkLogin(string $password, bool $setAutoLogin = false, bool $updateSessionCookies = true, bool $updateHash = true, bool $isAdministrator = false, string $totpCode = null): bool
    {
        if ($this->checkPassword($password) && $this->checkMembership($isAdministrator) && $this->checkTotp($totpCode)) {
            $this->updateSession($setAutoLogin, $updateSessionCookies);
            if ($updateHash) {
                $this->rehashIfNecessary($password);
            }
            return true;
        }
        return false;
    }

    private function checkPassword(string $password): bool
    {
        if ($this->hasMaxInvalidLogins()) {
            throw new Exception('SYS_LOGIN_MAX_INVALID_LOGIN');
        }

        if (!PasswordUtils::verify($password, $this->getValue('usr_password'))) {
            $incorrectLoginMessage = $this->handleIncorrectLogin('password');

            throw new Exception($incorrectLoginMessage);
        }
        return true;
    }

    public function checkMembership(bool $isAdministrator = false): bool
    {
        global $gSettingsManager, $gCurrentSession, $installedDbVersion;

        if (!$this->getValue('usr_valid')) {
            throw new Exception('SYS_LOGIN_NOT_ACTIVATED');
        }

        $orgLongName = $this->getOrgLongname();

        if (!$this->isMemberOfOrganization()) {
            throw new Exception('SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION', array($orgLongName));
        }

        if ($isAdministrator && version_compare($installedDbVersion, '2.4', '>=') && !$this->isAdminOfOrganization()) {
            throw new Exception('SYS_LOGIN_USER_NO_ADMINISTRATOR', array($orgLongName));
        }

        return true;
    }

    /**
     * Check the totp code of the current user. If the code is correct the session will be updated
     * @param string|null $totpCode The current totp code for the current user.
     * @return true Return true if totp code was correct
     * @throws Exception SYS_TFA_TOTP_CODE_MISSING
     * @throws Exception SYS_SECURITY_CODE_INVALID
     */
    private function checkTotp(string|null $totpCode): bool
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('two_factor_authentication_enabled')) {
            return true;
        }

        $secret = $this->getValue('usr_tfa_secret');
        // return true if user did not set up two factor authentication
        if (!$secret) {
            return true;
        }
        // return false if no totp code entered
        if (!$totpCode) {
            throw new Exception('SYS_TFA_TOTP_CODE_MISSING');
        }

        $tfa = new TwoFactorAuth(new QRServerProvider());
        if (!$tfa->verifyCode($secret, $totpCode)) {
            $incorrectLoginMessage = $this->handleIncorrectLogin('totp');

            throw new Exception($incorrectLoginMessage);
        }
        return true;
    }

    public function hasSetupTfa(): string
    {
        if ($this->getValue('usr_tfa_secret')) {
            return true;
        }
        return false;
    }

    private function updateSession(bool $setAutoLogin = false, bool $updateSessionCookies = true): bool
    {
        global $gSettingsManager, $gCurrentSession, $installedDbVersion;

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

            // count logins and update login events
            $this->saveChangesWithoutRights();
            $this->updateLoginData();
        }

        return true;
    }

    /**
     * Additional to the parent method the user profile fields and all
     * user rights and role memberships will be initialized
     * @return void
     * @throws Exception
     */
    public function clear()
    {
        parent::clear();

        // new user should be valid (except registration)
        $this->setValue('usr_valid', 1);
        $this->columnsValueChanged = false;

        if (isset($this->mProfileFieldsData)) {
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
     * Also, a notification that the user was deleted will be sent if notification is enabled.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
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
            $message = new Message($this->db, $row['msg_id']);
            $message->delete();
        }

        // now delete every database entry where the user id is used
        $sqlQueries = array();

        $sqlQueries[] = 'UPDATE ' . TBL_ANNOUNCEMENTS . '
                            SET ann_usr_id_create = NULL
                          WHERE ann_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_ANNOUNCEMENTS . '
                            SET ann_usr_id_change = NULL
                          WHERE ann_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_EVENTS . '
                            SET dat_usr_id_create = NULL
                          WHERE dat_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_EVENTS . '
                            SET dat_usr_id_change = NULL
                          WHERE dat_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_FOLDERS . '
                            SET fol_usr_id = NULL
                          WHERE fol_usr_id = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_FILES . '
                            SET fil_usr_id = NULL
                          WHERE fil_usr_id = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_FORUM_TOPICS . '
                            SET fot_usr_id_create = NULL
                          WHERE fot_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_FORUM_POSTS . '
                            SET fop_usr_id_create = NULL
                          WHERE fop_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_FORUM_POSTS . '
                            SET fop_usr_id_change = NULL
                          WHERE fop_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_LINKS . '
                            SET lnk_usr_id_create = NULL
                          WHERE lnk_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_LINKS . '
                            SET lnk_usr_id_change = NULL
                          WHERE lnk_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_LISTS . '
                            SET lst_usr_id = NULL
                          WHERE lst_global = true
                            AND lst_usr_id = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_PHOTOS . '
                            SET pho_usr_id_create = NULL
                          WHERE pho_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_PHOTOS . '
                            SET pho_usr_id_change = NULL
                          WHERE pho_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_ROLES . '
                            SET rol_usr_id_create = NULL
                          WHERE rol_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_ROLES . '
                            SET rol_usr_id_change = NULL
                          WHERE rol_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_ROLE_DEPENDENCIES . '
                            SET rld_usr_id = NULL
                          WHERE rld_usr_id = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_USERS . '
                            SET usr_usr_id_create = NULL
                            WHERE usr_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'UPDATE ' . TBL_USERS . '
                            SET usr_usr_id_change = NULL
                            WHERE usr_usr_id_change = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_LIST_COLUMNS . '
                          WHERE lsc_lst_id IN (SELECT lst_id
                                                 FROM ' . TBL_LISTS . '
                                                WHERE lst_usr_id = ' . $usrId . '
                                                AND lst_global = false)';

        $sqlQueries[] = 'DELETE FROM ' . TBL_LISTS . '
                          WHERE lst_global = false
                          AND lst_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_GUESTBOOK_COMMENTS . '
        WHERE gbc_usr_id_create = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_MEMBERS . '
                          WHERE mem_usr_id = ' . $usrId;

        // MySQL couldn't create delete statement with same table in a sub query.
        // Therefore, we fill a temporary table with all ids that should be deleted and reference on this table
        $sqlQueries[] = 'DELETE FROM ' . TBL_IDS . '
                          WHERE ids_usr_id = ' . $GLOBALS['gCurrentUserId'];

        $sqlQueries[] = 'INSERT INTO ' . TBL_IDS . '
                                (ids_usr_id, ids_reference_id)
                         SELECT ' . $GLOBALS['gCurrentUserId'] . ', msc_msg_id
                           FROM ' . TBL_MESSAGES_CONTENT . '
                          WHERE msc_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES_CONTENT . '
                          WHERE msc_msg_id IN (SELECT ids_reference_id
                                                 FROM ' . TBL_IDS . '
                                                WHERE ids_usr_id = ' . $GLOBALS['gCurrentUserId'] . ')';

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES_RECIPIENTS . '
                          WHERE msr_msg_id IN (SELECT ids_reference_id
                                                 FROM ' . TBL_IDS . '
                                                WHERE ids_usr_id = ' . $GLOBALS['gCurrentUserId'] . ')';

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES . '
                          WHERE msg_id IN (SELECT ids_reference_id
                                             FROM ' . TBL_IDS . '
                                            WHERE ids_usr_id = ' . $GLOBALS['gCurrentUserId'] . ')';

        $sqlQueries[] = 'DELETE FROM ' . TBL_IDS . '
                          WHERE ids_usr_id = ' . $GLOBALS['gCurrentUserId'];

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES_RECIPIENTS . '
                          WHERE msr_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES_CONTENT . '
                          WHERE NOT EXISTS (SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . '
                          WHERE msr_msg_id = msc_msg_id)';

        $sqlQueries[] = 'DELETE FROM ' . TBL_MESSAGES . '
                          WHERE NOT EXISTS (SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . '
                          WHERE msr_msg_id = msg_id)';

        $sqlQueries[] = 'DELETE FROM ' . TBL_REGISTRATIONS . '
                          WHERE reg_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_AUTO_LOGIN . '
        WHERE atl_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_SESSIONS . '
                          WHERE ses_usr_id = ' . $usrId;

        $sqlQueries[] = 'DELETE FROM ' . TBL_USER_DATA . '
                          WHERE usd_usr_id = ' . $usrId;

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
     * @throws Exception
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
     * Creates an array with all categories of one type where the user has the right to edit them
     * @param string $categoryType The type of the category that should be checked e.g. ANN, USF or DAT
     * @param string $idType The type of the id that should be returned e.g. id or uuid
     * @return array<int,int> Array with categories ids where user has the right to edit them
     * @throws Exception
     */
    public function getAllEditableCategories(string $categoryType, string $idType = 'id'): array
    {
        $queryParams = array($categoryType, $this->organizationId);

        if (
            ($categoryType === 'ANN' && $this->editAnnouncements())
            || ($categoryType === 'EVT' && $this->administrateEvents())
            || ($categoryType === 'FOT' && $this->administrateForum())
            || ($categoryType === 'LNK' && $this->editWeblinksRight())
            || ($categoryType === 'USF' && $this->editUsers())
            || ($categoryType === 'ROL' && $this->manageRoles())
        ) {
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
                                   AND rrd_rol_id IN (' . Database::getQmForValues($rolIdParams) . ') )
                    )';
        }

        $sql = 'SELECT cat_id, cat_uuid
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $categoryType
                   AND (  cat_org_id IS NULL
                       OR cat_org_id = ? ) -- $this->organizationId
                       ' . $condition;
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $arrEditableCategories = array();
        while ($row = $pdoStatement->fetch()) {
            if ($idType === 'uuid') {
                $arrEditableCategories[] = $row['cat_uuid'];
            } else {
                $arrEditableCategories[] = (int) $row['cat_id'];
            }
        }

        return $arrEditableCategories;
    }

    /**
     * Creates an array with all categories of one type where the user has the right to view them
     * @param string $categoryType The type of the category that should be checked e.g. ANN, USF or DAT
     * @param string $idType The type of the id that should be returned e.g. id or uuid
     * @return array<int,int> Array with categories ids where user has the right to view them
     * @throws Exception
     */
    public function getAllVisibleCategories(string $categoryType, string $idType = 'id'): array
    {
        $queryParams = array($categoryType, $this->organizationId);

        if (
            ($categoryType === 'ANN' && $this->editAnnouncements())
            || ($categoryType === 'EVT' && $this->administrateEvents())
            || ($categoryType === 'FOT' && $this->administrateForum())
            || ($categoryType === 'LNK' && $this->editWeblinksRight())
            || ($categoryType === 'USF' && $this->editUsers())
            || ($categoryType === 'ROL' && $this->assignRoles())
        ) {
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
                                 AND rrd_rol_id IN (' . Database::getQmForValues($rolIdParams) . ') )
                      OR NOT EXISTS (SELECT 1
                                       FROM ' . TBL_ROLES_RIGHTS . '
                                 INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . '
                                         ON rrd_ror_id = ror_id
                                      WHERE ror_name_intern = \'category_view\'
                                        AND rrd_object_id   = cat_id )
                    )';
        }

        $sql = 'SELECT cat_id, cat_uuid
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? -- $categoryType
                   AND (  cat_org_id IS NULL
                       OR cat_org_id = ? ) -- $this->organizationId
                       ' . $condition;
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $arrVisibleCategories = array();
        while ($row = $pdoStatement->fetch()) {
            if ($idType === 'uuid') {
                $arrVisibleCategories[] = $row['cat_uuid'];
            } else {
                $arrVisibleCategories[] = (int) $row['cat_id'];
            }
        }

        return $arrVisibleCategories;
    }

    /**
     * Returns the id of the organization this user object has been assigned.
     * This is in the default case the default organization of the config file.
     * @return int Returns the id of the organization this user object has been assigned
     */
    public function getOrganization(): int
    {
        return $this->organizationId;
    }

    /**
     * Gets the long name of this organization.
     * @return string Returns the long name of the organization.
     * @throws Exception
     */
    private function getOrgLongname(): string
    {
        $sql = 'SELECT org_longname
                  FROM ' . TBL_ORGANIZATIONS . '
                 WHERE org_id = ?';
        $orgStatement = $this->db->queryPrepared($sql, array($this->organizationId));

        return $orgStatement->fetchColumn();
    }

    /**
     * Returns an array with all UUIDs of roles where the user has the right to view the profiles of the role members
     * @return array<int,string> Array with role UUIDs where user has the right to view the profiles of the role members
     */
    public function getRolesViewProfiles(): array
    {
        return $this->rolesViewProfilesUUID;
    }

    /**
     * Returns an array with all roles where the user has the right to view the memberships
     * @return array<int,string> Array with role ids where user has the right to view the memberships
     */
    public function getRolesViewMemberships(): array
    {
        return $this->rolesViewMembershipsUUID;
    }

    /**
     * Returns an array with all UUIDs of roles where the user has the right to write an email to the role members
     * @return array<int,string> Array with role UUIDs where user has the right to mail them
     */
    public function getRolesWriteMails(): array
    {
        return $this->rolesWriteMailsUUID;
    }

    /**
     * Returns data from the user to improve dictionary attack check
     * @return array<int,string>
     * @throws Exception
     */
    public function getPasswordUserData(): array
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

        return array_filter($userData, function (string $value) {
            return $value !== '';
        });
    }

    /**
     * Returns an array with all role ids where the user is a member.
     * @return array<int,int> Returns an array with all role ids where the user is a member.
     * @throws Exception
     */
    public function getRoleMemberships(): array
    {
        $this->checkRolesRight();

        return $this->rolesMembership;
    }

    /**
     * Returns an array with all role ids where the user is a member and not a leader of the role.
     * @return array<int,int> Returns an array with all role ids where the user is a member and not a leader of the role.
     * @throws Exception
     */
    public function getRoleMembershipsNoLeader(): array
    {
        $this->checkRolesRight();

        return $this->rolesMembershipNoLeader;
    }

    /**
     * Get the value of a column of the database table if the column has the prefix **usr_**
     * otherwise the value of the profile field of the table adm_user_data will be returned.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read or the internal unique profile field name
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column or the value of adm_user_fields
     *               If the value was manipulated before with **setValue** than the manipulated value is returned.
     *
     * **Code example**
     * ```
     * // reads data of adm_users column
     * $loginName = $gCurrentUser->getValue('usr_login_name');
     * // reads data of adm_user_fields
     * $email = $gCurrentUser->getValue('EMAIL');
     * ```
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
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
     * Creates a vcard with all data of this user object (vCard 3.0, utf-8)
     * @return string Returns the vcard as a string
     * @throws Exception
     */
    public function getVCard(): string
    {
        global $gSettingsManager, $gCurrentUser, $gL10n;

        $vCard = array(
            'BEGIN:VCARD',
            'VERSION:3.0'
        );

        if ($gCurrentUser->allowedViewProfileField($this, 'FIRST_NAME')) {
            $vCard[] = 'N:' .
                $this->getValue('LAST_NAME', 'database') . ';' .
                $this->getValue('FIRST_NAME', 'database') . ';;;';
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'LAST_NAME')) {
            $vCard[] = 'FN:' .
                $this->getValue('FIRST_NAME') . ' ' .
                $this->getValue('LAST_NAME');
        }
        if ($this->getValue('usr_login_name') !== '') {
            $vCard[] = 'NICKNAME:' . $this->getValue('usr_login_name');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'PHONE') && $this->getValue('PHONE') !== '') {
            $vCard[] = 'TEL;TYPE=home,voice:' . $this->getValue('PHONE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'MOBILE') && $this->getValue('MOBILE') !== '') {
            $vCard[] = 'TEL;TYPE=cell,voice:' . $this->getValue('MOBILE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'FAX') && $this->getValue('FAX') !== '') {
            $vCard[] = 'TEL;TYPE=home,fax:' . $this->getValue('FAX');
        }
        if (
            $gCurrentUser->allowedViewProfileField($this, 'STREET')
            && $gCurrentUser->allowedViewProfileField($this, 'CITY')
            && $gCurrentUser->allowedViewProfileField($this, 'POSTCODE')
            && $gCurrentUser->allowedViewProfileField($this, 'COUNTRY')
        ) {
            $vCard[] = 'ADR;TYPE=home:;;' .
                $this->getValue('STREET', 'database') . ';' .
                $this->getValue('CITY', 'database') . ';;' .
                $this->getValue('POSTCODE', 'database') . ';' .
                $gL10n->getCountryName($this->getValue('COUNTRY', 'database'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'WEBSITE') && $this->getValue('WEBSITE') !== '') {
            $vCard[] = 'URL;TYPE=home:' . $this->getValue('WEBSITE');
        }

        if ($gCurrentUser->allowedViewProfileField($this, 'FACEBOOK') && $this->getValue('FACEBOOK') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=FACEBOOK:' . $this->getValue('FACEBOOK');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'YOUTUBE') && $this->getValue('YOUTUBE') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=YOUTUBE:' . $this->getValue('YOUTUBE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'SKYPE') && $this->getValue('SKYPE') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=SKYPE:' . $this->getValue('SKYPE');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'LINKEDIN') && $this->getValue('LINKEDIN') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=LINKEDIN:' . $this->getValue('LINKEDIN');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'INSTAGRAM') && $this->getValue('INSTAGRAM') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=INSTAGRAM:' . $this->getValue('INSTAGRAM');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'MASTODON') && $this->getValue('MASTODON') !== '') {
            $vCard[] = 'X-SOCIALPROFILE;TYPE=MASTODON:' . $this->getValue('MASTODON');
        }

        if ($gCurrentUser->allowedViewProfileField($this, 'BIRTHDAY') && $this->getValue('BIRTHDAY') !== '') {
            $vCard[] = 'BDAY:' . $this->getValue('BIRTHDAY', 'Y-m-d');
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'EMAIL') && $this->getValue('EMAIL') !== '') {
            $vCard[] = 'EMAIL;TYPE=home:' . $this->getValue('EMAIL');
        }
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . (int) $this->getValue('usr_id') . '.jpg';
        if ((int) $gSettingsManager->get('profile_photo_storage') === 1 && is_file($file)) {
            $imgHandle = fopen($file, 'rb');
            if ($imgHandle !== false) {
                $base64Image = base64_encode(fread($imgHandle, filesize($file)));
                fclose($imgHandle);
                $vCard[] = 'PHOTO;TYPE=JPEG;ENCODING=b:' . $base64Image;
            }
        }
        if ((int) $gSettingsManager->get('profile_photo_storage') === 0 && !is_null($this->getValue('usr_photo'))) {
            $vCard[] = 'PHOTO;TYPE=JPEG;ENCODING=b:' . base64_encode((string) $this->getValue('usr_photo'));
        }
        if ($gCurrentUser->allowedViewProfileField($this, 'GENDER') && (int) $this->getValue('GENDER', 'database') > 0) {
            // https://datatracker.ietf.org/doc/html/rfc6350#section-6.2.7
            if ((int) $this->getValue('GENDER', 'database') === 1) {
                $vCard[] = 'GENDER:M';
            } elseif ((int) $this->getValue('GENDER', 'database') === 2) {
                $vCard[] = 'GENDER:F';
            } elseif ((int) $this->getValue('GENDER', 'database') === 3) {
                $vCard[] = 'GENDER:O';
            }
        }
        if ($this->getValue('usr_timestamp_change') !== '') {
            $vCard[] = 'REV:' . $this->getValue('usr_timestamp_change', 'Ymd\THis\Z');
        } else {
            $vCard[] = 'REV:' . $this->getValue('usr_timestamp_create', 'Ymd\THis\Z');
        }
        $vCard[] = 'UID:urn:uuid:' . $this->getValue('usr_uuid');
        $vCard[] = 'END:VCARD';
        return implode("\r\n", $vCard) . "\r\n";
    }

    /**
     * Returns true if a column of user table or profile fields has changed
     * @return bool Returns true if a column of user table or profile fields has changed
     */
    public function hasColumnsValueChanged(): bool
    {
        return parent::hasColumnsValueChanged() || $this->mProfileFieldsData->hasColumnsValueChanged();
    }

    /**
     * Check if the user has deposited an email. Therefore, at least one profile field from type EMAIL
     * must have a value.
     * @return bool Return true if the user has deposited an email.
     * @throws Exception
     */
    public function hasEmail(): bool
    {
        if ($this->getValue('EMAIL') !== '') {
            return true;
        }

        foreach ($this->mProfileFieldsData->getProfileFields() as $profileField) {// => $profileFieldConfig)
            if (
                $profileField->getValue('usf_type') === 'EMAIL'
                && $this->mProfileFieldsData->getValue($profileField->getValue('usf_name_intern')) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the maximum of invalid logins is reached.
     * @return bool Returns true if the maximum of invalid logins is reached.
     * @throws Exception
     */
    private function hasMaxInvalidLogins(): bool
    {
        // if within 15 minutes 3 wrong login took place -> block user account for 15 minutes
        $now = new \DateTime();
        $minutesOffset = new \DateInterval('PT15M');
        $minutesBefore = $now->sub($minutesOffset);

        if (is_null($this->getValue('usr_date_invalid', 'Y-m-d H:i:s'))) {
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
     * Method will check if user can generally edit all users or if he is a group leader and can edit users
     * of a special role where **$user** is a member or if it's the own profile, and he could edit this.
     * @param User $user User object of the user that should be checked if the current user can edit his profile.
     * @param bool $checkOwnProfile If set to **false** than this method don't check the role right to edit the own profile.
     * @return bool Return **true** if the current user is allowed to edit the profile of the user from **$user**.
     * @throws Exception
     */
    public function hasRightEditProfile(self $user, bool $checkOwnProfile = true): bool
    {
        $usrId = (int) $this->getValue('usr_id');
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
     * @throws Exception
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
     * Checks if the current user has the right to send email to the role.
     * @param int $roleId ID of the role that should be checked.
     * @return bool Return **true** if the user has the right to send email to the role.
     * @throws Exception
     */
    public function hasRightSendMailToRole(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesWriteMails, 'rol_mail_to_all', $roleId);
    }

    /**
     * Checks the necessary rights if this user could view former roles members. Therefore,
     * the user must also have the right to view the role. So you must also check this right.
     * @param int $roleId ID of the role that should be checked.
     * @return bool Return **true** if the user has the right to view former roles members
     * @throws Exception
     */
    public function hasRightViewFormerRolesMembers(int $roleId): bool
    {
        global $gSettingsManager;

        if (
            (int) $gSettingsManager->get('groups_roles_show_former_members') !== 1
            && ($this->checkRolesRight('rol_assign_roles')
                || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(1, 3), true)))
        ) {
            return true;
        } elseif (
            (int) $gSettingsManager->get('groups_roles_show_former_members') !== 2
            && ($this->checkRolesRight('rol_edit_user')
                || ($this->isLeaderOfRole($roleId) && in_array($this->rolesMembershipLeader[$roleId], array(2, 3), true)))
        ) {
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
     * @throws Exception
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
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
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
                $rolId = (int) $row['rol_id'];
                $rolThisProfileView = (int) $row['rol_view_members_profiles'];

                if ($rolThisProfileView === Role::VIEW_LOGIN_USERS && $gValidLogin) {
                    // all logged-in users can see role lists/profiles
                    return true;
                } elseif ($rolThisProfileView === Role::VIEW_ROLE_MEMBERS && in_array($rolId, $this->rolesViewProfiles)) {
                    // only role members can see role lists/profiles
                    return true;
                } elseif ($rolThisProfileView === Role::VIEW_LEADERS && $this->isLeaderOfRole($rolId)) {
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
     * @throws Exception
     */
    public function hasRightViewProfiles(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesViewProfiles, 'rol_all_lists_view', $roleId);
    }

    /**
     * Check if the user of this object has the right to view the role that is set in the parameter.
     * @param int $roleId The id of the role that should be checked.
     * @return bool Return **true** if the user has the right to view the role otherwise **false**.
     * @throws Exception
     */
    public function hasRightViewRole(int $roleId): bool
    {
        return $this->hasRightRole($this->rolesViewMemberships, 'rol_all_lists_view', $roleId);
    }

    /**
     * Handles the incorrect given login password or totp code.
     * @param string $mode Mode to differentiate if password or totp code (2FA) is incorrect. Allowed values: 'password', 'totp'
     * @return string Return string with the reason why the login failed.
     * @throws Exception
     */
    private function handleIncorrectLogin(string $mode): string
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

        switch ($mode) {
            case 'password':
                return 'SYS_LOGIN_USERNAME_PASSWORD_INCORRECT';
            case 'totp':
                return 'SYS_SECURITY_CODE_INVALID';
        }
        throw new Exception('Unreachable Case');
    }

    /**
     * Deletes all other sessions of the current user except the current session if there is already a current
     * session. All auto logins of the user will be removed. This method is useful if the user changed his
     * password or if unusual activities within the user account are noticed.
     * @return bool Returns true if all things could be done. Otherwise, false is returned.
     * @throws Exception
     */
    public function invalidateAllOtherLogins(): bool
    {
        global $gCurrentUserId, $gCurrentSession;

        if (isset($gCurrentSession)) {
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
     * @throws Exception
     */
    public function isAdministrator(): bool
    {
        $this->checkRolesRight();

        return $this->administrator;
    }

    /**
     * Checks if this user is an admin of the organization that is set in this class.
     * @return bool Return true if user is admin of this organization.
     * @throws Exception
     */
    private function isAdminOfOrganization(): bool
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
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = ? -- $this->getValue(\'usr_id\')
                   AND rol_valid  = true
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND cat_org_id = ? -- $this->organizationId
                   AND ' . $administratorColumn . ' = true ';
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
    public function isLeaderOfRole(int $roleId): bool
    {
        return array_key_exists($roleId, $this->rolesMembershipLeader);
    }

    /**
     * Checks if this user is a member of the organization. The organization is the current organization of
     * the session or the organization that was set to this object by setOrganization().
     * @return bool Return true if user is member of the organization.
     * @throws Exception
     */
    public function isMemberOfOrganization(): bool
    {
        // Check if user is currently member of a role of an organisation
        $sql = 'SELECT mem_usr_id
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
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
    public function isMemberOfRole(int $roleId): bool
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
     * @param int $id Unique id of the user that should be read
     * @return bool Returns **true** if one record is found
     * @throws Exception
     */
    public function readDataById(int $id): bool
    {
        if (parent::readDataById($id)) {
            // read data of all user fields from current user
            $this->mProfileFieldsData->readUserData($id, $this->organizationId);
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
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     */
    public function readDataByUuid(string $uuid): bool
    {
        if (parent::readDataByUuid($uuid)) {
            if (isset($this->mProfileFieldsData)) {
                // read data of all user fields from current user
                $this->mProfileFieldsData->readUserData($this->getValue('usr_id'), $this->organizationId);
            }
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
     * @throws Exception
     */
    private function rehashIfNecessary(string $password): bool
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
        $this->rolesViewProfiles = array();
        $this->rolesViewMembershipsUUID = array();
        $this->rolesViewProfilesUUID = array();
        $this->rolesWriteMails = array();
        $this->rolesWriteMailsUUID = array();
        $this->rolesMembership = array();
        $this->rolesMembershipLeader = array();
        $this->rolesMembershipNoLeader = array();
    }

    /**
     * Reset the count of invalid logins. After that it's possible for the user to try another login.
     * @throws Exception
     */
    public function resetInvalidLogins()
    {
        $this->setValue('usr_date_invalid', null);
        $this->setValue('usr_number_invalid', 0);
        $this->save(false); // Zeitstempel nicht aktualisieren // TODO Exception handling
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's a new
     * record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * First save recordset and then save all user fields. After that the session of this got a renewal for the user object.
     * If the user doesn't have the right to save data of this user than an exception will be thrown.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentSession, $gCurrentUser, $gChangeNotification;

        $usrId = $this->getValue('usr_id');

        // if current user is not new and is not allowed to edit this user
        // and saveChangesWithoutRights isn't true then throw exception
        if (!$this->saveChangesWithoutRights && $usrId > 0 && !$gCurrentUser->hasRightEditProfile($this)) {
            throw new Exception('The profile data of user ' . $this->getValue('FIRST_NAME') . ' '
                . $this->getValue('LAST_NAME') . ' could not be saved because you don\'t have the right to do this.');
        }

        $this->db->startTransaction();

        // if new user then set create id and the uuid
        $updateCreateUserId = false;
        if ($usrId === 0) {
            if ($GLOBALS['gCurrentUserId'] === 0) {
                $updateCreateUserId = true;
                $updateFingerPrint = false;
            }
        }

        // if value of a field changed then update timestamp of user object
        if (isset($this->mProfileFieldsData) && $this->mProfileFieldsData->hasColumnsValueChanged()) {
            $this->columnsValueChanged = true;
        }

        $newRecord = $this->newRecord;

        $returnValue = parent::save($updateFingerPrint);
        $usrId = (int) $this->getValue('usr_id'); // if a new user was created get the new id

        // if this was a registration then set this user id to create user id
        if ($updateCreateUserId) {
            $this->setValue('usr_timestamp_create', DATETIME_NOW);
            $this->setValue('usr_usr_id_create', $usrId);
            $returnValue = $returnValue && parent::save($updateFingerPrint);
        }

        if (isset($this->mProfileFieldsData)) {
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
     * Method will search for other users in the database with a similar first name and last name. When using MySQL the
     * SQL function SOUNDEX will be used.
     * the following combinations within first name and last name will be checked:
     * 1. first name and last name are equal (under consideration of soundex)
     * 2. last name is equal and only first part of first name of existing members is equal
     * 3. last name is equal and only first part of first name of new registration member is equal
     * 4. last name is equal to first name and first name is equal to last name
     * @return array<int,int> Returns an array with the user IDs of all found similar users.
     * @throws Exception
     */
    public function searchSimilarUsers(): array
    {
        global $gSettingsManager;

        $foundUserIds = array();
        $lastName = $this->db->escapeString($this->getValue('LAST_NAME', 'database'));
        $firstName = $this->db->escapeString($this->getValue('FIRST_NAME', 'database'));

        // search for users with similar names (SQL function SOUNDEX only available in MySQL)
        if (DB_ENGINE === Database::PDO_ENGINE_MYSQL && $gSettingsManager->getBool('system_search_similar')) {
            $sqlSimilarName =
                '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(' . $lastName . '), 1, 4)
                AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(' . $firstName . '), 1, 4) )
             OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(' . $lastName . '), 1, 4)
                AND SUBSTRING(SOUNDEX(SUBSTRING(first_name.usd_value, 1, LOCATE(\' \', first_name.usd_value))), 1, 4) = SUBSTRING(SOUNDEX(' . $firstName . '), 1, 4) )
             OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(' . $lastName . '), 1, 4)
                AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(SUBSTRING(' . $firstName . ', 1, LOCATE(\' \', ' . $firstName . '))), 1, 4) )
             OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) = SUBSTRING(SOUNDEX(' . $firstName . '), 1, 4)
                AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) = SUBSTRING(SOUNDEX(' . $lastName . '), 1, 4) ) )';
        } else {
            $sqlSimilarName =
                '(  (   last_name.usd_value  = ' . $lastName . '
                AND first_name.usd_value = ' . $firstName . ')
             OR (   last_name.usd_value  = ' . $lastName . '
                AND SUBSTRING(first_name.usd_value, 1, POSITION(\' \' IN first_name.usd_value)) = ' . $firstName . ')
             OR (   last_name.usd_value  = ' . $lastName . '
                AND first_name.usd_value = SUBSTRING(' . $firstName . ', 1, POSITION(\' \' IN ' . $firstName . ')))
             OR (   last_name.usd_value  = ' . $firstName . '
                AND first_name.usd_value = ' . $lastName . ') )';
        }

        // select all users from the database that have the same first and last name
        $sql = 'SELECT usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name
                  FROM ' . TBL_USERS . '
            RIGHT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
            RIGHT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                 WHERE usr_valid = true
                   AND ' . $sqlSimilarName;
        $queryParams = array(
            $this->mProfileFieldsData->getProperty('LAST_NAME', 'usf_id'),
            $this->mProfileFieldsData->getProperty('FIRST_NAME', 'usf_id')
        );
        $usrStatement = $this->db->queryPrepared($sql, $queryParams);

        while ($row = $usrStatement->fetch()) {
            $foundUserIds[] = $row['usr_id'];
        }

        return $foundUserIds;
    }

    /**
     * If email support is enabled and the current user is administrator or has the right to approve new
     * registrations than this method will create a new password and send this to the user.
     * @return void
     * @throws Exception
     */
    public function sendNewPassword()
    {
        global $gSettingsManager, $gCurrentUser;

        // E-Mail support must be enabled
        // Only administrators are allowed to send new login data or users who want to approve login data
        if (
            $gSettingsManager->getBool('system_notifications_enabled')
            && ($gCurrentUser->isAdministrator() || $gCurrentUser->approveUsers())
        ) {
            // Generate new secure-random password and save it
            $password = SecurityUtils::getRandomString(PASSWORD_GEN_LENGTH, PASSWORD_GEN_CHARS);
            $this->setPassword($password);
            $this->save();

            // Send mail with login data to user
            $sysMail = new SystemMail($this->db);
            $sysMail->addRecipientsByUser($this->getValue('usr_uuid'));
            $sysMail->setVariable(1, $password);
            $sysMail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $this);
        } else {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * Set the default values that are stored in the profile fields configuration to each profile field.
     * A default value will only be set if **usf_default_value** is not NULL.
     * @return void
     * @throws Exception
     */
    public function setDefaultValues()
    {
        foreach ($this->mProfileFieldsData->getProfileFields() as $profileField) {
            $defaultValue = $profileField->getValue('usf_default_value');
            if ($defaultValue !== '') {
                $this->setValue($profileField->getValue('usf_name_intern'), $defaultValue);
            }
        }
    }

    /**
     * Set the id of the organization which should be used in this user object.
     * The organization is used to read the rights of the user. If **setOrganization** isn't called
     * than the default organization **gCurrentOrganization** is set for the current user object.
     * @param int $organizationId ID of the organization
     * @return void
     */
    public function setOrganization(int $organizationId)
    {
        if ($organizationId !== $this->organizationId) {
            $this->organizationId = $organizationId;
            $this->renewRoleData();
        }
    }

    /**
     * Set a new value for a password column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $newPassword The new value that should be stored in the database field
     * @param bool $doHashing Should the password get hashed before inserted. Default is true
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setPassword(string $newPassword, bool $doHashing = true): bool
    {
        global $gSettingsManager, $gPasswordHashAlgorithm, $gChangeNotification;

        if (!$doHashing) {
            if ($this->changeNotificationEnabled && is_object($gChangeNotification)) {
                $gChangeNotification->logUserChange(
                    (int) $this->getValue('usr_id'),
                    'usr_password',
                    $this->getValue('usr_password'),
                    $newPassword,
                    "MODIFIED",
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
                "MODIFIED",
                $this
            );
        }
        if (parent::setValue('usr_password', $newPasswordHash, false)) {
            // for security reasons remove all sessions and auto login of the user
            return $this->invalidateAllOtherLogins();
        }

        return false;
    }

    /**
     * Set a new value for a second factor secret column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string|null $newSecret The new value that should be stored in the database field
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setSecondFactorSecret(string|null $newSecret): bool
    {
        global $gChangeNotification;

        if ($this->changeNotificationEnabled && is_object($gChangeNotification)) {
            $gChangeNotification->logUserChange(
                (int) $this->getValue('usr_id'),
                'usr_tfa_secret',
                $this->getValue('usr_tfa_secret'),
                $newSecret || 'null',
                "MODIFIED",
                $this
            );
        }
        if (parent::setValue('usr_tfa_secret', $newSecret, false)) {
            // for security reasons remove all sessions and auto login of the user
            return $this->invalidateAllOtherLogins();
        }

        return false;
    }

    /**
     * Set a value for a profile field. The value will be checked against typical conditions of the data type and
     * also against the custom regex if this is set. If an invalid value is set an Exception will be thrown.
     * @param string $fieldNameIntern Expects the **usf_name_intern** of the field that should get a new value.
     * @param mixed $fieldValue The new value that should be stored in the profile field.
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will
     *                                not be checked.
     * @return bool Return true if the value is valid and would be accepted otherwise return false or an exception.
     * @throws Exception If an invalid value should be set.
     *                      exception->text contains a string with the reason why the login failed.
     */
    public function setProfileFieldsValue(string $fieldNameIntern, $fieldValue, bool $checkValue = true): bool
    {
        return $this->mProfileFieldsData->setValue($fieldNameIntern, $fieldValue, $checkValue);
    }

    /**
     * Set a new value for a column of the database table if the column has the prefix **usr_**
     * otherwise the value of the profile field of the table adm_user_data will set.
     * If the user log is activated than the change of the value will be logged in **adm_user_log**.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                           internal unique profile field name
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will
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
     * @throws Exception
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
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
                    (string) $this->getValue($columnName),
                    (string) $newValue,
                    "MODIFIED",
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
        } elseif (
            $this->mProfileFieldsData->getProperty($columnName, 'usf_type') === 'CHECKBOX'
            && $oldFieldValue === '' && $newValue === '0'
        ) {
            // don't change value if checkbox is not set and old value was emtpy
            $newValue = '';
        }

        // only update if value has changed
        if ($oldFieldValue_db === $newValue) {
            return true;
        }

        $returnCode = false;

        // Disabled fields can only be edited by users with the right "edit_users" except on registration.
        // Here is no need to check hidden fields because we check on save() method that only users who
        // can edit the profile are allowed to save and change data.
        if (
            ($this->getValue('usr_id') === 0 && $GLOBALS['gCurrentUserId'] === 0)
            || (int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 0
            || ((int) $this->mProfileFieldsData->getProperty($columnName, 'usf_disabled') === 1
                && $GLOBALS['gCurrentUser']->hasRightEditProfile($this, false))
            || $this->saveChangesWithoutRights === true
        ) {
            $returnCode = $this->mProfileFieldsData->setValue($columnName, $newValue);
        }

        // Not all changes are logged. Exceptions:
        // Fields starting with usr_ (special case above)
        // Fields that have not changed (check above)
        // If usr_id is 0 (the user is newly created; this is already documented) (check in logProfileChange)

        if ($returnCode && !$this->newRecord && is_object($gChangeNotification)) {
            $gChangeNotification->logProfileChange(
                $this->getValue('usr_id'),
                $this->mProfileFieldsData->getProperty($columnName, 'usf_id'),
                $columnName, // TODO: is $columnName the internal name or the human-readable?
                // Old and new values in human-readable version:
                (string) $oldFieldValue,
                (string) $this->mProfileFieldsData->getValue($columnName),
                // Old and new values in raw database:
                (string) $oldFieldValue_db,
                (string) $newValue,
                "MODIFIED",
                $this
            );
        }

        return $returnCode;
    }

    /**
     * Update login data for this user. These are timestamps of last login and reset count
     * and timestamp of invalid logins.
     * @return void
     * @throws Exception
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
     * Function checks if the logged-in user is allowed to create and edit announcements
     * @return bool
     * @throws Exception
     */
    public function editAnnouncements(): bool
    {
        return $this->checkRolesRight('rol_announcements');
    }

    /**
     * Function checks if the logged-in user is allowed to edit and assign registrations
     * @return bool
     * @throws Exception
     */
    public function approveUsers(): bool
    {
        return $this->checkRolesRight('rol_approve_users');
    }

    /**
     * Checks if the user has the right to assign members to at least one role. This method also returns
     * true if the user is a leader of the role and could assign other members to that role.
     * @return bool Return **true** if the user can assign members to at least one role.
     * @throws Exception
     */
    public function assignRoles(): bool
    {
        $this->checkRolesRight();

        return $this->assignRoles;
    }

    /**
     * Method checks if the current user is allowed to manage roles and therefore has
     * admin access to the groups and roles module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function manageRoles(): bool
    {
        return $this->checkRolesRight('rol_assign_roles');
    }

    /**
     * Method checks if the current user is allowed to administrate the event module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function administrateEvents(): bool
    {
        return $this->checkRolesRight('rol_events');
    }

    /**
     * Method checks if the current user is allowed to administrate the documents and files module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function administrateDocumentsFiles(): bool
    {
        return $this->checkRolesRight('rol_documents_files');
    }

    /**
     * Method checks if the current user is allowed to administrate the forum module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function administrateForum(): bool
    {
        return $this->checkRolesRight('rol_forum_admin');
    }

    /**
     * Method checks if the current user is allowed to administrate other user profiles and therefore
     * has access to the user management module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function editUsers(): bool
    {
        return $this->checkRolesRight('rol_edit_user');
    }

    /**
     * Method checks if the current user is allowed to administrate the photo's module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function editPhotoRight(): bool
    {
        return $this->checkRolesRight('rol_photo');
    }

    /**
     * Method checks if the current user is allowed to administrate the web links module.
     * @return bool Return true if the user is admin of the module otherwise false
     * @throws Exception
     */
    public function editWeblinksRight(): bool
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

    /**
     * Return the human-readable name of this record.
     * 
     * @return string The readable representation of the record ("Lastname, Firstname")
     */
    public function readableName(): string
    {
        return $this->mProfileFieldsData->getValue('LAST_NAME') . ', ' . $this->mProfileFieldsData->getValue('FIRST_NAME');
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * For the users table, we also ignore usr_valid, usr_*_login, etc.
     * 
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        $ignored = parent::getIgnoredLogColumns();
        $ignored[] = 'usr_uuid';
        $ignored[] = 'usr_pw_reset_id';
        $ignored[] = 'usr_pw_reset_timestamp';
        $ignored[] = 'usr_last_login';
        $ignored[] = 'usr_actual_login';
        $ignored[] = 'usr_number_login';
        $ignored[] = 'usr_date_invalid';
        $ignored[] = 'usr_number_invalid';
        $ignored[] = 'usr_valid';
        return $ignored;
    }

    /**
     * Adjust the changelog entry for this db record: Don't store the actual password, just '********'. Also, the photo cannot be stores, so indicate this by '[...]', too.
     * 
     * @param LogChanges $logEntry The log entry to adjust
     * 
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry)
    {
        if ($logEntry->getValue('log_field') == 'usr_password') {
            $logEntry->setValue('log_value_old', '********');
            $logEntry->setValue('log_value_new', '********');
        } elseif ($logEntry->getValue('log_field') == 'usr_photo') {
            $logEntry->setValue('log_value_old', '[...]');
            $logEntry->setValue('log_value_new', '[...]');
        }
    }
}
