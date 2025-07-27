<?php

namespace Admidio\Organizations\Entity;

use Admidio\Categories\Entity\Category;
use Admidio\Documents\Entity\Folder;
use Admidio\Events\Entity\Event;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Database;
use Admidio\Photos\Entity\Album;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Roles\Entity\RolesRights;
use Ramsey\Uuid\Uuid;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Handle organization data of Admidio and is connected to database table adm_organizations
 *
 * This class creates the organization object and manages the access to the
 * organization specific preferences of the table adm_preferences. There
 * are also some method to read the relationship of organizations if the
 * database contains more as one organization.
 *
 * **Code example**
 * ```
 * // create object and read the value of the language preference
 * $organization    = new Organization($gDb, $organizationId);
 * $settingsManager =& $organization->getSettingsManager();
 * $language        = $settingsManager->get('system_language');
 * // language = 'de'
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Organization extends Entity
{
    /**
     * @var bool Flag will be set if the class had already searched for child organizations
     */
    protected bool $bCheckChildOrganizations = false;
    /**
     * @var array<int,string> Array with all child organizations of this organization
     */
    protected array $childOrganizations = array();
    /**
     * @var SettingsManager Manager for organization preferences
     */
    protected SettingsManager $settingsManager;
    /**
     * @var int Number of all organizations in database
     */
    protected int $countOrganizations = 0;

    /**
     * Constructor that will create an object of a recordset of the table adm_organizations.
     * If the id is set than the specific organization will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int|string $organization The recordset of the organization with this id will be loaded.
     *                                 The organization can be the table id or the organization shortname.
     *                                 If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, $organization = '')
    {
        parent::__construct($database, TBL_ORGANIZATIONS, 'org');

        if (is_numeric($organization)) {
            $this->readDataById($organization);
        } else {
            $this->readDataByColumns(array('org_shortname' => $organization));
        }

        if ((int)$this->getValue('org_id') > 0) {
            $this->settingsManager = new SettingsManager($database, (int)$this->getValue('org_id'));
            $this->settingsManager->resetAll();
        }
    }

    /**
     * Initialize all necessary data of this object.
     * @return void
     * @throws Exception
     */
    public function clear()
    {
        parent::clear();

        $this->bCheckChildOrganizations = false;
        $this->childOrganizations = array();
        $this->countOrganizations = 0;

        if (isset($this->settingsManager)) {
            $this->settingsManager->clearAll();
        }
    }

    /**
     * Reads the number of all records of this table. In addition to the parent method
     * this method will cache the value and will return the cached value on multiple calls.
     * @return int Number of all organizations in database.
     * @throws Exception
     */
    public function countAllRecords(): int
    {
        if ($this->countOrganizations === 0) {
            $this->countOrganizations = parent::countAllRecords();
        }
        return $this->countOrganizations;
    }

    /**
     * Creates all necessary data for a new organization. This method can only be called once for an organization.
     * It will create the basic categories, lists, roles, systemmails etc.
     * @param int $userId The id of the administrator who creates the new organization.
     *                    This will be the first valid user of the new organization.
     * @throws Exception
     */
    public function createBasicData(int $userId)
    {
        global $gL10n, $gProfileFields;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = $this->db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        // create all systemmail texts and write them into table adm_texts
        $systemmailsTexts = array(
            'SYSMAIL_REGISTRATION_CONFIRMATION' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_CONFIRMATION'),
            'SYSMAIL_REGISTRATION_NEW' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_ADMINISTRATOR'),
            'SYSMAIL_REGISTRATION_APPROVED' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_USER'),
            'SYSMAIL_REGISTRATION_REFUSED' => $gL10n->get('SYS_SYSMAIL_REFUSE_REGISTRATION'),
            'SYSMAIL_NEW_PASSWORD' => $gL10n->get('SYS_SYSMAIL_NEW_PASSWORD'),
            'SYSMAIL_PASSWORD_RESET' => $gL10n->get('SYS_SYSMAIL_PASSWORD_RESET')
        );
        $text = new Text($this->db);

        $orgId = (int)$this->getValue('org_id');

        foreach ($systemmailsTexts as $key => $value) {
            $text->clear();
            $text->setValue('txt_org_id', $orgId);
            $text->setValue('txt_name', $key);
            $text->setValue('txt_text', $value);
            $text->save();
        }

        // create default category for roles, events and weblinks
        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, ?, \'ROL\', \'COMMON\', \'SYS_COMMON\', true, 1, ?, ?)';
        $queryParams = array($orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);
        $categoryCommon = $this->db->lastInsertId();

        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, ?, \'ROL\', \'GROUPS\',    \'INS_GROUPS\',    false, false, 2, ?, ?)
                     , (?, ?, \'ROL\', \'COURSES\',   \'INS_COURSES\',   false, false, 3, ?, ?)
                     , (?, ?, \'ROL\', \'TEAMS\',     \'INS_TEAMS\',     false, false, 4, ?, ?)
                     , (?, ?, \'ROL\', \'EVENTS\',    \'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\', false, true, 5, ?, ?)
                     , (?, ?, \'LNK\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'LNK\', \'INTERN\',    \'INS_INTERN\',    false, false, 2, ?, ?)
                     , (?, ?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', false, false, 2, ?, ?)
                     , (?, ?, \'FOT\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'EVT\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'EVT\', \'TRAINING\',  \'INS_TRAINING\',  false, false, 2, ?, ?)
                     , (?, ?, \'EVT\', \'COURSES\',   \'INS_COURSES\',   false, false, 3, ?, ?)
                     , (?, ?, \'IVT\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)';
        $queryParams = array(
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
        );
        $this->db->queryPrepared($sql, $queryParams);

        // if the second organization is added than also create global categories
        if ($this->countAllRecords() === 2) {
            $categoryAnnouncement = new Category($this->db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new Category($this->db);
            $categoryEvents->setValue('cat_type', 'EVT');
            $categoryEvents->setValue('cat_name_intern', 'EVT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new Category($this->db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }

        // insert root folder name for documents & files module
        $sql = 'INSERT INTO ' . TBL_FOLDERS . '
                       (fol_org_id, fol_uuid, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_usr_id, fol_timestamp)
                VALUES (?, ?, \'DOCUMENTS\', ?, ?, false, true, ?, ?)';
        $queryParams = array($orgId, Uuid::uuid4(), Folder::getRootFolderName('documents', $this->getValue('org_shortname')), FOLDER_DATA, $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);

        // insert inventory fields
        $sql = 'INSERT INTO ' . TBL_INVENTORY_FIELDS . '
                       (inf_uuid, inf_org_id, inf_type, inf_name_intern, inf_name, inf_description, inf_system, inf_required_input, inf_sequence, inf_usr_id_create, inf_timestamp_create, inf_usr_id_change, inf_timestamp_change)
                VALUES (?, ?, \'TEXT\', \'ITEMNAME\', \'SYS_INVENTORY_ITEMNAME\', \'SYS_INVENTORY_ITEMNAME_DESC\', 1, 1, 0, ?, ?, NULL, NULL),
                       (?, ?, \'CATEGORY\', \'CATEGORY\', \'SYS_CATEGORY\', \'SYS_INVENTORY_CATEGORY_DESC\', 1, 1, 1, ?, ?, NULL, NULL),
                       (?, ?, \'TEXT\', \'KEEPER\', \'SYS_INVENTORY_KEEPER\', \'SYS_INVENTORY_KEEPER_DESC\', 1, 0, 2, ?, ?, NULL, NULL),
                       (?, ?, \'CHECKBOX\', \'IN_INVENTORY\', \'SYS_INVENTORY_IN_INVENTORY\', \'SYS_INVENTORY_IN_INVENTORY_DESC\', 1, 0, 3, ?, ?, NULL, NULL),
                       (?, ?, \'TEXT\', \'LAST_RECEIVER\', \'SYS_INVENTORY_LAST_RECEIVER\', \'SYS_INVENTORY_LAST_RECEIVER_DESC\', 1, 0, 4, ?, ?, NULL, NULL),
                       (?, ?, \'DATE\', \'RECEIVED_ON\', \'SYS_INVENTORY_RECEIVED_ON\', \'SYS_INVENTORY_RECEIVED_ON_DESC\', 1, 0, 5, ?, ?, NULL, NULL),
                       (?, ?, \'DATE\', \'RECEIVED_BACK_ON\', \'SYS_INVENTORY_RECEIVED_BACK_ON\', \'SYS_INVENTORY_RECEIVED_BACK_ON_DESC\', 1, 0, 6, ?, ?, NULL, NULL);
                ';
        $queryParams = array(
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW,
            Uuid::uuid4(), $orgId, $systemUserId, DATETIME_NOW
        );
        $this->db->queryPrepared($sql, $queryParams);

        // now create default roles

        // Create role administrator
        $roleAdministrator = new Role($this->db);
        $roleAdministrator->saveChangesWithoutRights();
        $roleAdministrator->setValue('rol_cat_id', $categoryCommon, false);
        $roleAdministrator->setValue('rol_name', $gL10n->get('SYS_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_assign_roles', 1);
        $roleAdministrator->setValue('rol_approve_users', 1);
        $roleAdministrator->setValue('rol_announcements', 1);
        $roleAdministrator->setValue('rol_events', 1);
        $roleAdministrator->setValue('rol_documents_files', 1);
        $roleAdministrator->setValue('rol_forum_admin', 1);
        $roleAdministrator->setValue('rol_photo', 1);
        $roleAdministrator->setValue('rol_weblinks', 1);
        $roleAdministrator->setValue('rol_inventory_admin', 1);
        $roleAdministrator->setValue('rol_edit_user', 1);
        $roleAdministrator->setValue('rol_mail_to_all', 1);
        $roleAdministrator->setValue('rol_mail_this_role', 3);
        $roleAdministrator->setValue('rol_profile', 1);
        $roleAdministrator->setValue('rol_all_lists_view', 1);
        $roleAdministrator->setValue('rol_administrator', 1);
        $roleAdministrator->setValue('rol_view_memberships', Role::VIEW_LOGIN_USERS);
        $roleAdministrator->save();

        // Create role member
        $roleMember = new Role($this->db);
        $roleMember->saveChangesWithoutRights();
        $roleMember->setValue('rol_cat_id', $categoryCommon, false);
        $roleMember->setValue('rol_name', $gL10n->get('SYS_MEMBER'));
        $roleMember->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_MEMBER'));
        $roleMember->setValue('rol_mail_this_role', 2);
        $roleMember->setValue('rol_profile', 1);
        $roleMember->setValue('rol_default_registration', 1);
        $roleMember->setValue('rol_view_memberships', Role::VIEW_NOBODY);
        $roleMember->save();

        // Create role board
        $roleManagement = new Role($this->db);
        $roleManagement->saveChangesWithoutRights();
        $roleManagement->setValue('rol_cat_id', $categoryCommon, false);
        $roleManagement->setValue('rol_name', $gL10n->get('INS_BOARD'));
        $roleManagement->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_BOARD'));
        $roleManagement->setValue('rol_announcements', 1);
        $roleManagement->setValue('rol_events', 1);
        $roleManagement->setValue('rol_weblinks', 1);
        $roleManagement->setValue('rol_edit_user', 1);
        $roleManagement->setValue('rol_mail_to_all', 1);
        $roleManagement->setValue('rol_mail_this_role', 2);
        $roleManagement->setValue('rol_profile', 1);
        $roleManagement->setValue('rol_all_lists_view', 1);
        $roleManagement->setValue('rol_view_memberships', Role::VIEW_LOGIN_USERS);
        $roleManagement->save();

        // set edit role rights to forum categories for role member
        $sql = 'SELECT cat_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = \'FOT\'
                   AND cat_org_id = ? -- $orgId';
        $pdoStatement = $this->db->queryPrepared($sql, array($orgId));
        $row = $pdoStatement->fetch();

        $rightCategoryView = new RolesRights($this->db, 'category_edit', (int)$row['cat_id']);
        $rightCategoryView->saveRoles(array($roleMember->getValue('rol_id')));

        // Create membership for user in role 'Administrator' and 'Members'
        $membershipAdministrator = new Membership($this->db);
        $membershipAdministrator->startMembership($roleAdministrator->getValue('rol_id'), $userId, false);
        $membershipMember = new Membership($this->db);
        $membershipMember->startMembership($roleMember->getValue('rol_id'), $userId, false);

        // create object with current user field structure
        $gProfileFields = new ProfileFields($this->db, $orgId);

        // create default list configurations
        $addressList = new ListConfiguration($this->db);
        $addressList->setValue('lst_name', $gL10n->get('INS_ADDRESS_LIST'));
        $addressList->setValue('lst_org_id', $orgId);
        $addressList->setValue('lst_global', 1);
        $addressList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $addressList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $addressList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('STREET', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $addressList->save();

        $phoneList = new ListConfiguration($this->db);
        $phoneList->setValue('lst_name', $gL10n->get('INS_PHONE_LIST'));
        $phoneList->setValue('lst_org_id', $orgId);
        $phoneList->setValue('lst_global', 1);
        $phoneList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $phoneList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $phoneList->addColumn((int)$gProfileFields->getProperty('PHONE', 'usf_id'));
        $phoneList->addColumn((int)$gProfileFields->getProperty('MOBILE', 'usf_id'));
        $phoneList->addColumn((int)$gProfileFields->getProperty('EMAIL', 'usf_id'));
        $phoneList->save();

        $contactDetailsList = new ListConfiguration($this->db);
        $contactDetailsList->setValue('lst_name', $gL10n->get('SYS_CONTACT_DETAILS'));
        $contactDetailsList->setValue('lst_org_id', $orgId);
        $contactDetailsList->setValue('lst_global', 1);
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('STREET', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('PHONE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('MOBILE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('EMAIL', 'usf_id'));
        $contactDetailsList->save();

        $formerList = new ListConfiguration($this->db);
        $formerList->setValue('lst_name', $gL10n->get('INS_MEMBERSHIP'));
        $formerList->setValue('lst_org_id', $orgId);
        $formerList->setValue('lst_global', 1);
        $formerList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $formerList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $formerList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $formerList->addColumn('mem_begin');
        $formerList->addColumn('mem_end');
        $formerList->save();

        $participantList = new ListConfiguration($this->db);
        $participantList->setValue('lst_name', $gL10n->get('SYS_PARTICIPANTS'));
        $participantList->setValue('lst_org_id', $orgId);
        $participantList->setValue('lst_global', 1);
        $participantList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $participantList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $participantList->addColumn('mem_approved');
        $participantList->addColumn('mem_comment');
        $participantList->addColumn('mem_count_guests');
        $participantList->save();

        $contactsList = new ListConfiguration($this->db);
        $contactsList->setValue('lst_name', $gL10n->get('SYS_CONTACTS'));
        $contactsList->setValue('lst_org_id', $orgId);
        $contactsList->setValue('lst_global', 1);
        $contactsList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $contactsList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $contactsList->addColumn('usr_login_name');
        $contactsList->addColumn((int)$gProfileFields->getProperty('GENDER', 'usf_id'));
        $contactsList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactsList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $contactsList->addColumn('usr_timestamp_change');
        $contactsList->save();

        // create default category report configuration
        $categoryReportColumns = 'p' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('STREET', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('CITY', 'usf_id') . ',' .
            'r' . $roleAdministrator->getValue('rol_id') . ',' .
            'r' . $roleManagement->getValue('rol_id') . ',' .
            'r' . $roleMember->getValue('rol_id');
        $categoryReport = new Entity($this->db, TBL_CATEGORY_REPORT, 'crt');
        $categoryReport->setValue('crt_org_id', $orgId);
        $categoryReport->setValue('crt_name', $gL10n->get('SYS_GENERAL_ROLE_ASSIGNMENT'));
        $categoryReport->setValue('crt_col_fields', $categoryReportColumns);
        $categoryReport->setValue('crt_number_col', 0);
        $categoryReport->save();

        // set new default configuration to the module settings
        $organizationSettings = new SettingsManager($this->db, $orgId);
        $organizationSettings->getAll();
        $organizationSettings->set('system_notifications_role', $roleAdministrator->getValue('rol_uuid'));
        $organizationSettings->set('groups_roles_default_configuration', $addressList->getValue('lst_id'));
        $organizationSettings->set('events_list_configuration', $participantList->getValue('lst_id'));
        $organizationSettings->set('contacts_list_configuration', $contactsList->getValue('lst_id'));
        $organizationSettings->set('category_report_default_configuration', $categoryReport->getValue('crt_id'));
    }

    /**
     * Create an organization object depending on an optional organization shortname string. If an
     * organization shortname is set than this organization will be read otherwise the organization
     * with the minimum ID will be read.
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     * @param string $organization The organization shortname. If this is set than this organization
     *                                 will be read otherwise the organization with the minimum ID.
     * @return Organization Returns an organization object.
     * @throws Exception
     */
    public static function createDefaultOrganizationObject(Database $db, string $organization = ''): Organization
    {
        if ($organization !== '') {
            $organizationObject = new Organization($db, $organization);
        } else {
            $sql = 'SELECT MIN(org_id) as organization_id FROM ' . TBL_ORGANIZATIONS;
            $pdoStatement = $db->queryPrepared($sql, array(), false);
            $row = $pdoStatement->fetch();
            $organizationObject = new Organization($db, (int)$row['organization_id']);
        }

        return $organizationObject;
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        $this->db->startTransaction();

        // delete all category reports
        $sql = 'DELETE FROM ' . TBL_CATEGORY_REPORT . '
                 WHERE crt_org_id = ? -- $this->getValue(\'org_id\')
                     ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all announcements
        $sql = 'DELETE FROM ' . TBL_ANNOUNCEMENTS . '
                 WHERE ann_cat_id IN (
                       SELECT cat.cat_id
                         FROM (SELECT cat_id
                                 FROM ' . TBL_CATEGORIES . '
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) cat
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all events
        $sql = 'SELECT evt.*
                  FROM ' . TBL_EVENTS . ' evt
                 INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = dat_cat_id
                 WHERE cat_org_id = ? -- $this->getValue(\'org_id\') ';
        $eventsStatement = $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        while ($eventRow = $eventsStatement->fetch()) {
            $event = new Event($this->db);
            $event->setArray($eventRow);
            $event->delete();
        }

        // delete all forum posts
        $sql = 'DELETE FROM ' . TBL_FORUM_POSTS . '
                 WHERE fop_fot_id IN (
                       SELECT fot.fot_id
                         FROM (SELECT fot_id
                                 FROM ' . TBL_FORUM_TOPICS . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = fot_cat_id
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) fot
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all forum topics
        $sql = 'DELETE FROM ' . TBL_FORUM_TOPICS . '
                 WHERE fot_cat_id IN (
                       SELECT cat.cat_id
                         FROM (SELECT cat_id
                                 FROM ' . TBL_CATEGORIES . '
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) cat
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all photos
        $sql = 'SELECT pho.*
                  FROM ' . TBL_PHOTOS . ' pho
                 WHERE pho_pho_id_parent IS NULL
                   AND pho_org_id = ? -- $this->getValue(\'org_id\') ';
        $albumStatement = $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        while ($photoAlbumRow = $albumStatement->fetch()) {
            $photoAlbum = new Album($this->db);
            $photoAlbum->setArray($photoAlbumRow);
            $photoAlbum->delete();
        }

        // delete all weblinks
        $sql = 'DELETE FROM ' . TBL_LINKS . '
                 WHERE lnk_cat_id IN (
                       SELECT cat.cat_id
                         FROM (SELECT cat_id
                                 FROM ' . TBL_CATEGORIES . '
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) cat
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all roles rights of categories
        $sql = 'DELETE FROM ' . TBL_ROLES_RIGHTS_DATA . '
                 WHERE rrd_ror_id IN (
                       SELECT ror.ror_id
                         FROM (SELECT ror_id
                                 FROM ' . TBL_ROLES_RIGHTS . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = ror_ror_id_parent
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                  AND ror_name_intern = \'category_view\'
                                 ) ror
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all lists
        $sql = 'DELETE FROM ' . TBL_LIST_COLUMNS . '
                 WHERE lsc_lst_id IN (
                       SELECT lst.lst_id
                         FROM (SELECT lst_id
                                 FROM ' . TBL_LISTS . '
                                WHERE lst_org_id = ? -- $this->getValue(\'org_id\')
                                 ) lst
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        $sql = 'DELETE FROM ' . TBL_LISTS . '
                 WHERE lst_org_id = ? -- $this->getValue(\'org_id\')
                     ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all auto logins
        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN . '
                 WHERE atl_org_id = ? -- $this->getValue(\'org_id\') ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all preferences
        $sql = 'DELETE FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? -- $this->getValue(\'org_id\') ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all registrations
        $sql = 'DELETE FROM ' . TBL_REGISTRATIONS . '
                 WHERE reg_org_id = ? -- $this->getValue(\'org_id\') ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all sessions
        $sql = 'DELETE FROM ' . TBL_SESSIONS . '
                 WHERE ses_org_id = ? -- $this->getValue(\'org_id\') ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all texts
        $sql = 'DELETE FROM ' . TBL_TEXTS . '
                 WHERE txt_org_id = ? -- $this->getValue(\'org_id\') ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all folders
        $sqlAdminRoles = 'SELECT fol_uuid
                        FROM ' . TBL_FOLDERS . '
                       WHERE fol_type = \'DOCUMENTS\'
                         AND fol_name = \'documents_' . $this->getValue('org_shortname') . '\' ';
        $statementFolder = $this->db->queryPrepared($sqlAdminRoles);
        $folder_uuid = $statementFolder->fetchColumn();

        $documentsFilesRootFolder = new Folder($this->db);
        $documentsFilesRootFolder->getFolderForDownload($folder_uuid);
        $documentsFilesRootFolder->delete();

        // delete all memberships
        $sql = 'DELETE FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id IN (
                       SELECT rol.rol_id
                         FROM (SELECT rol_id
                                 FROM ' . TBL_ROLES . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) rol
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all roles rights of roles
        $sql = 'DELETE FROM ' . TBL_ROLES_RIGHTS_DATA . '
                 WHERE rrd_rol_id IN (
                       SELECT rol.rol_id
                         FROM (SELECT rol_id
                                 FROM ' . TBL_ROLES . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) rol
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all role dependencies
        $sql = 'DELETE FROM ' . TBL_ROLE_DEPENDENCIES . '
                 WHERE rld_rol_id_parent IN (
                       SELECT rol.rol_id
                         FROM (SELECT rol_id
                                 FROM ' . TBL_ROLES . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) rol
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        $sql = 'DELETE FROM ' . TBL_ROLE_DEPENDENCIES . '
                 WHERE rld_rol_id_child IN (
                       SELECT rol.rol_id
                         FROM (SELECT rol_id
                                 FROM ' . TBL_ROLES . '
                                INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) rol
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all roles
        $sql = 'DELETE FROM ' . TBL_ROLES . '
                 WHERE rol_cat_id IN (
                       SELECT cat.cat_id
                         FROM (SELECT cat_id
                                 FROM ' . TBL_CATEGORIES . '
                                WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                                 ) cat
                       )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all categories
        $sql = 'DELETE FROM ' . TBL_CATEGORIES . '
                 WHERE cat_org_id = ? -- $this->getValue(\'org_id\')
                     ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all inventory item data
        $sql = 'DELETE FROM ' . TBL_INVENTORY_ITEM_DATA . '
                    WHERE ind_ini_id IN (
                        SELECT ivt.ini_id
                            FROM (SELECT ini_id
                                    FROM ' . TBL_INVENTORY_ITEMS . '
                                    WHERE ini_org_id = ? -- $this->getValue(\'org_id\')
                                    ) ivt
                        )';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all inventory item lend data
        $sql = 'DELETE FROM ' . TBL_INVENTORY_ITEM_LEND_DATA . '
                 WHERE inl_ini_id IN (
                       SELECT ini.ini_id
                         FROM (SELECT ini_id
                                 FROM ' . TBL_INVENTORY_ITEMS . '
                                WHERE ini_org_id = ? -- $this->getValue(\'org_id\')
                                 ) ini
                       )';

        // delete all inventory items
        $sql = 'DELETE FROM ' . TBL_INVENTORY_ITEMS . '
                 WHERE ini_org_id = ? -- $this->getValue(\'org_id\')
                     ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // delete all inventory field options
        $sql = 'DELETE FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
                 WHERE ifo_inf_id IN (
                       SELECT inf.inf_id
                         FROM (SELECT inf_id
                                 FROM ' . TBL_INVENTORY_FIELDS . '
                                WHERE inf_org_id = ? -- $this->getValue(\'org_id\')
                                 ) inf
                       )';

        // delete all inventory fields
        $sql = 'DELETE FROM ' . TBL_INVENTORY_FIELDS . '
                 WHERE inf_org_id = ? -- $this->getValue(\'org_id\')
                     ';
        $this->db->queryPrepared($sql, array($this->getValue('org_id')));

        // now delete the organization
        parent::delete();

        return $this->db->endTransaction();
    }

    /**
     * @return array<int,string> Returns an array with all child organizations
     * @throws Exception
     */
    protected function getChildOrganizations(): array
    {
        if (!$this->bCheckChildOrganizations) {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getOrganizationsInRelationship(true, false);
            $this->bCheckChildOrganizations = true;
        }

        return $this->childOrganizations;
    }

    /**
     * Create a comma separated list with all organization ids of children,
     * parent and this organization that is prepared for use in SQL
     * @param bool $shortname If set to true then a list of all shortnames will be returned
     * @return string Returns a string with a comma separated list of all organization
     *                ids that are parents or children and the own id
     * @throws Exception
     */
    public function getFamilySQL(bool $shortname = false): string
    {
        $organizations = $this->getOrganizationsInRelationship();

        if ($shortname) {
            /**
             * @param string $value
             * @return string
             */
            function addQuotationMarks(string $value): string
            {
                return '\'' . $value . '\'';
            }

            $organizationShortnames = array_values($organizations);
            $organizationShortnames[] = $this->getValue('org_shortname');
            $organizationShortnames = array_map('addQuotationMarks', $organizationShortnames);
            return implode(',', $organizationShortnames);
        }

        $organizationIds = array_keys($organizations);
        $organizationIds[] = (int)$this->getValue('org_id');
        return implode(',', $organizationIds);
    }

    /**
     * Read all child and parent organizations of this organization and returns an array with them.
     * @param bool $child If set to **true** (default) then all child organizations will be in the array
     * @param bool $parent If set to **true** (default) then the parent organization will be in the array
     * @param bool $longname If set to **true** then the value of the array will be the **org_longname**
     *                       otherwise it will be **org_shortname**
     * @return array<int,string> Returns an array with all child and parent organizations e.g. array('org_id' => 'org_shortname')
     * @throws Exception
     */
    public function getOrganizationsInRelationship(bool $child = true, bool $parent = true, bool $longname = false): array
    {
        $sqlWhere = array();
        $queryParams = array();

        if ($child) {
            $sqlWhere[] = 'org_org_id_parent = ?';
            $queryParams[] = (int)$this->getValue('org_id');
        }
        $orgParentId = (int)$this->getValue('org_org_id_parent');
        if ($parent && $orgParentId > 0) {
            $sqlWhere[] = 'org_id = ?';
            $queryParams[] = $orgParentId;
        }

        $sql = 'SELECT org_id, org_longname, org_shortname
                  FROM ' . TBL_ORGANIZATIONS . '
                 WHERE ' . implode(' OR ', $sqlWhere);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $childOrganizations = array();
        while ($row = $pdoStatement->fetch()) {
            $orgId = (int)$row['org_id'];
            if ($longname) {
                $childOrganizations[$orgId] = $row['org_longname'];
            } else {
                $childOrganizations[$orgId] = $row['org_shortname'];
            }
        }
        return $childOrganizations;
    }

    /**
     * @return SettingsManager
     * @throws Exception
     */
    public function &getSettingsManager(): SettingsManager
    {
        if (!isset($this->settingsManager)) {
            $this->settingsManager = new SettingsManager($this->db, (int)$this->getValue('org_id'));
            $this->settingsManager->resetAll();
        }

        return $this->settingsManager;
    }

    /**
     * Method checks if the organization is configured as a child organization in the recordset.
     * @return bool Return **true** if the organization is a child of another organization
     * @throws Exception
     */
    public function isChildOrganization(): bool
    {
        return $this->getValue('org_org_id_parent') > 0;
    }

    /**
     * Method checks if the organization is configured as a parent organization in the recordset.
     * @return bool Return **true** if the organization is the parent of at least one other organization
     * @throws Exception
     */
    public function isParentOrganization(): bool
    {
        return count($this->getChildOrganizations()) > 0;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($checkValue) {
            // org_shortname shouldn't be edited
            if ($columnName === 'org_shortname' && !$this->newRecord) {
                return false;
            } elseif ($columnName === 'org_homepage' && $newValue !== '') {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false) {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * @return array<string,mixed>
     */
    public function getDbColumns(): array
    {
        return $this->dbColumns;
    }

    /**
     * Adjust the changelog entry for this db record: Add the parent fold as a related object
     * 
     * @param LogChanges $logEntry The log entry to adjust
     * 
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry) {
        $orgParentId = (int) $this->getValue('org_org_id_parent');
        if ($orgParentId > 0) {
            $sql = 'SELECT org_id, org_longname, org_shortname
                      FROM '.TBL_ORGANIZATIONS.'
                     WHERE org_id = ?';
            $pdoStatement = $this->db->queryPrepared($sql, [$orgParentId]);
    
            while ($row = $pdoStatement->fetch()) {
                $logEntry->setLogRelated($row['org_id'], $row['org_longname']);
            }
        }
    }
    /**
     * Return a human-readable representation of this record.
     * For organizations, simply use the longname
     * 
     * @return string The readable representation of the record (can also be a translatable identifier)
     */
    public function readableName(): string
    {
        if (array_key_exists($this->columnPrefix.'_longname', $this->dbColumns)) {
            return $this->dbColumns[$this->columnPrefix.'_longname'];
        } else {
            return $this->dbColumns[$this->keyColumnName];
        }
    }
}
