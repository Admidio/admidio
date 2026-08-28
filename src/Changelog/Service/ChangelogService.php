<?php
namespace Admidio\Changelog\Service;

use InvalidArgumentException;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Photos\Entity\Album;
use Admidio\Announcements\Entity\Announcement;
use Admidio\Categories\Entity\Category;
use Admidio\Components\Entity\Component;
use Admidio\Events\Entity\Event;
use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Forum\Entity\Topic;
use Admidio\Forum\Entity\Post;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\Item;

use Admidio\Roles\Entity\ListColumns;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Membership;
use Admidio\Preferences\Entity\Preferences;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesDependencies;
use Admidio\Roles\Entity\RolesRightsData;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Events\Entity\Room;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Roles\Service\RolesService;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Weblinks\Entity\Weblink;
use Admidio\UI\Presenter\PagePresenter;
use DateTime;
use ModuleEvents;

/**
 * @brief Class with methods to help with the changelog.
 *
 * This class adds some static functions that are used in the changelog module to keep the
 * code easy to read and short
 *
 * **What the changelog records**
 *
 * The changelog is an audit trail of the application, not of the database. Its only source are
 * Entity::save() and Entity::delete(), so a change is recorded if, and only if, it was made
 * through an Entity object. Statements that are sent to the database directly, bulk updates,
 * changes made by other programs and changes made in the database itself never appear in it.
 * Whenever a service deletes or updates dependent records with its own SQL, it has to read those
 * records through their Entity and delete them there, or the audit trail loses them silently.
 * A database trigger would see all of them, but none of the application context that makes an
 * entry readable: the acting user, the readable name of the record, the translated field names,
 * the masking of secrets and the organization the change belongs to.
 *
 * A database table without an Entity class of its own is therefore not covered, except that its
 * table name still falls under the preference changelog_table_others.
 *
 * **Keeping a table complete**
 *
 * The name of the database table is the type discriminator of a log entry, and it is used by
 * several methods of this class that have to be kept in sync when a table is added:
 * getTableLabel() for its title, getFieldTranslations() for the title and type of each of its
 * columns, createLink() for the link to the record, getObjectForTable() for the object the page
 * headline is built from, getRelatedTable() if its entries point at a record of another table,
 * and getPermittedTables() for the users that may read its entries. A third-party extension
 * registers all of these through registerCallback() instead, see the example at the end of this
 * file.
 *
 * **Code example**
 * ```
 * $allLogTables = ChangelogService::getTableLabel();
 * $readableTableName = ChangelogService::getTableLabel('users');
 *
 * $permittedTables = ChangelogService::getPermittedTables($gCurrentUser);
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ChangelogService {

/*********************************************************
 * Translate table and field names to human-readable texts
 *    -> Most of these translations are duplicated from other code / files!
 *********************************************************
 */

    /**
     * Static(global) list of tables, which should not be included in the changelog
     * @var array
     */
    public static array $noLogTables = [
        'auto_login', 'components', 'id', 'log_changes',
        'messages', 'messages_attachments', 'messages_content', 'messages_recipients',
        'oidc_access_tokens', 'oidc_refresh_tokens', 'oidc_auth_codes', 'registrations',
        'sessions'];

    /**
     * Minimum number of seconds between two runs of the automatic purge of the change history.
     */
    protected const PURGE_INTERVAL_SECONDS = 86400;


    /**
     * array holding all customizations by third-party extensions.
     * @var array
     */
    protected static array $customCallbacks = array(
        'getTableLabel' => ['' => []],
        'getTableLabelArray' => [],
        'getAreaArray' => [],
        'getObjectForTable' => ['' => []],
        'getFieldTranslations' => [],
        'createLink' => ['' => []],
        'formatValue' => ['' => []],
        'getRelatedTable' => ['' => []],
        'getPermittedTables' => ['' => []],
    );

    /**
     * Register a callback function or value for the changelog functionality.
     * If the callback is a value (string, array, etc.), it will be returned. If
     * the callback is a function, it will be executed and if the return value is
     * not empty, it will be returned. If the function returns a null or empty
     * value, the next callback or the default processing of the ChangelogService
     * method will proceed.
     * @param string $function The method of the ChangelogService class that should be customized. One of
     *     'getTableLabel', 'getTableLabelArray', 'getObjectForTable', 'getFieldTranslations', 'createLink',
     *     'formatValue', 'getRelatedTable', 'getPermittedTables'. Any other value throws an InvalidArgumentException.
     * @param string $moduleOrKey The module or type that should be customized. If
     *     empty, the callback will be executed for all values, and it will be used
     *     if it evaluates to a non-empty value.
     * @param mixed $callback The callback function or value. A value will be returned
     *      unchanged, a function will be executed (arguments should be identical to
     *      the methods of the ChangelogService class)
     * @return void
     */
    public static function registerCallback(string $function, string $moduleOrKey, mixed $callback) : void {
        // Fail here and not somewhere in an unrelated changelog request later on: an unknown
        // method would silently add a key that no ChangelogService method ever consults.
        if (!array_key_exists($function, self::$customCallbacks)) {
            throw new InvalidArgumentException('Unsupported changelog callback "' . $function . '".');
        }

        if (empty($moduleOrKey)) {
            if ($function == 'getTableLabelArray' || $function == 'getFieldTranslations' || $function == 'getAreaArray') {
                // These are merged into the lookup tables instead of being evaluated, so the
                // registered value has to be an array.
                if (!is_array($callback)) {
                    throw new InvalidArgumentException('The changelog callback "' . $function . '" must be an array.');
                }
                self::$customCallbacks[$function] = array_merge(self::$customCallbacks[$function], $callback);
            } else {
                // append callback to list of callbacks for all values
                self::$customCallbacks[$function][''][] = $callback;
            }
        } else {
            self::$customCallbacks[$function][$moduleOrKey] = $callback;
        }

        // the callbacks feed into the cached lookup tables
        self::resetCache();
    }

    static protected function evaluateCallback(mixed $callback, ...$args) : mixed {
        if (is_callable($callback)) {
            return $callback(...$args);
        } else {
            return $callback;
        }
    }

    /**
     * Cached lookup tables. getTableLabel() is consulted for every single log write and
     * getFieldTranslations() builds a large array of translated texts, so neither of them may be
     * rebuilt on each call.
     * @var array|null
     */
    private static ?array $tableLabelCache = null;
    private static ?array $fieldTranslationsCache = null;

    /**
     * Language the cached field translations were built for. The cache is only valid as long as
     * the language of the current request does not change.
     * @var string
     */
    private static string $fieldTranslationsLanguage = '';

    /**
     * Drop all cached lookup tables. Must be called whenever a callback is registered that changes
     * their content.
     * @return void
     */
    public static function resetCache(): void
    {
        self::$tableLabelCache = null;
        self::$fieldTranslationsCache = null;
        self::$fieldTranslationsLanguage = '';
    }

    /**
     * Return a human-readable title for the given database table. If table is
     * null, a full named array of all titles is returned.
     * @param mixed|null $table The database table name (sans the table prefix)
     * @return array|string The human-readable title of the database table
     * @throws Exception
     */
    public static function getTableLabel(mixed $table = null): array|string  {
        // First process callbacks defined for the given table:
        if (!empty($table) && array_key_exists($table, self::$customCallbacks['getTableLabel'])) {
            $callback = self::$customCallbacks['getTableLabel'][$table];
            $val = self::evaluateCallback($callback, $table);
            if (!empty($val)) return $val;
        }
        // second (if first step does not yield a match) process callbacks defined for ALL values:
        if (!empty($table) && array_key_exists('', self::$customCallbacks['getTableLabel'])) {
            foreach (self::$customCallbacks['getTableLabel'][''] as $callback) {
                $val = self::evaluateCallback($callback, $table);
                if (!empty($val)) return $val;
            }
        }
        // If none of the callbacks matches, proceed with the default processing...
        $tableLabels = self::getTableLabels();

        if ($table == null) {
            return $tableLabels;
        } else {
            if (array_key_exists($table, $tableLabels)) {
                return Language::translateIfTranslationStrId($tableLabels[$table]);
            } else {
                return '';
            }
        }
    }

    /**
     * Named list of all database tables and their translation ids, including the entries that were
     * added by third-party extensions. The list is needed for every single log write, so it is
     * built once per request and cached afterwards.
     *
     * @return array Named array of database table name => translation id
     */
    private static function getTableLabels(): array
    {
        if (self::$tableLabelCache !== null) {
            return self::$tableLabelCache;
        }

        /**
         * Named list of all available table columns and their translation IDs.
         * @var array $tableLabels
         */
        $tableLabels = array(
            'user_data' => 'SYS_PROFILE_FIELD',
            'users' =>  'SYS_PROFILE_FIELD',
            'members' => 'SYS_ROLE_MEMBERSHIPS',
            'user_fields' => 'ORG_PROFILE_FIELDS',
            'user_field_select_options' => 'SYS_PROFILE_FIELD_SELECT_OPTIONS',
            'announcements' => 'SYS_ANNOUNCEMENTS',
            'events' => 'SYS_EVENTS',
            'rooms' => 'SYS_ROOM',
            'roles' => 'SYS_ROLES',
            'role_dependencies' => 'SYS_DEPENDENCIES',
            'roles_rights' => 'SYS_ROLE_RIGHTS',
            'roles_rights_data' => 'SYS_ROLE_RIGHTS',

            'categories' => 'SYS_CATEGORIES',
            'category_report' => 'SYS_CATEGORY_REPORT',

            'forum_topics' => 'SYS_FORUM_TOPIC',
            'forum_posts' => 'SYS_FORUM_POST',

            'links' => 'SYS_WEBLINKS',

            'folders' => 'SYS_FOLDER',
            'files' => 'SYS_FILE',

            'inventory_fields' => 'SYS_INVENTORY_ITEMFIELDS',
            'inventory_field_select_options' => 'SYS_INVENTORY_ITEMFIELD_SELECT_OPTIONS',
            'inventory_items' => 'SYS_INVENTORY_ITEMS',
            'inventory_item_data' => 'SYS_INVENTORY_ITEM_DATA',
            'inventory_item_borrow_data' => 'SYS_INVENTORY_ITEM_BORROW_DATA',

            'organizations' => 'SYS_ORGANIZATION',
            'menu' => 'SYS_MENU_ITEM',

            'user_relation_types' => 'SYS_USER_RELATION_TYPE',
            'user_relations' => 'SYS_USER_RELATIONS',

            'photos' => 'SYS_PHOTO_ALBUMS',

            'lists' => 'SYS_LIST',
            'list_columns' => 'SYS_LIST_COLUMNS', // Changes to the list column are handled as changes to the list -> list_columns is never displayed as affected table

            'preferences' => 'SYS_SETTINGS',
            'texts' => 'SYS_SETTINGS',
            'saml_clients' => 'SYS_SSO_CLIENTS_SAML',
            'oidc_clients' => 'SYS_SSO_CLIENTS_OIDC',
            'sso_keys' => 'SYS_SSO_KEYS',
            'others' => 'SYS_ALL_OTHERS',
        );

        self::$tableLabelCache = array_merge($tableLabels, self::$customCallbacks['getTableLabelArray']);
        return self::$tableLabelCache;
    }


    /**
     * The sections the areas of the change history are grouped into. The order of the sections is
     * the order they are displayed in. A section with an empty title is displayed without a heading.
     *
     * @return array Named array of section id => translation id of the heading
     */
    public static function getAreaSections(): array
    {
        return array(
            'user_role_data' => 'SYS_HEADER_USER_ROLE_DATA',
            'content_modules' => 'SYS_HEADER_CONTENT_MODULES',
            'settings' => 'SYS_HEADER_PREFERENCES',
            'other' => ''
        );
    }

    /**
     * The areas the change history can be switched on and off for. An area bundles all database
     * tables of one module or of one group of settings, so the user configures the change history
     * in the modules he knows and never in database tables.
     *
     * Every table of getTableLabel() that no area claims is added to the area 'other', so a table
     * that a third-party extension registered is configurable even if the extension does not
     * declare an area for it.
     *
     * An extension adds its own area, or adds its tables to an existing one, by registering an
     * array of the same shape:
     * ```
     * ChangelogService::registerCallback('getAreaArray', '', array(
     *     'my_module' => array('label' => 'PLG_MY_MODULE', 'section' => 'content_modules',
     *         'enabledBy' => array('my_module_plugin_enabled'), 'tables' => array('my_table'))
     * ));
     * ```
     *
     * @return array Ordered named array of area id => array with the keys
     *      **label** (translation id), **section** (id of getAreaSections()),
     *      **enabledBy** (names of the preferences that enable the module, the area is displayed
     *      as soon as one of them is enabled; an empty array means the area is always displayed)
     *      and **tables** (database table names without prefix)
     * @throws Exception
     */
    public static function getAreas(): array
    {
        $areas = array(
            'user_profile' => array(
                'label' => 'SYS_CHANGELOG_AREA_USER_PROFILE',
                'section' => 'user_role_data',
                'enabledBy' => array(),
                'tables' => array('users', 'user_data', 'members', 'user_relations')
            ),
            'permissions' => array(
                'label' => 'SYS_ROLE_RIGHTS',
                'section' => 'user_role_data',
                'enabledBy' => array(),
                'tables' => array('roles_rights', 'roles_rights_data')
            ),
            'documents_files' => array(
                'label' => 'SYS_DOCUMENTS_FILES',
                'section' => 'content_modules',
                'enabledBy' => array('documents_files_module_enabled'),
                'tables' => array('files', 'folders')
            ),
            'photos' => array(
                'label' => 'SYS_PHOTO_ALBUMS',
                'section' => 'content_modules',
                'enabledBy' => array('photo_module_enabled'),
                'tables' => array('photos')
            ),
            'announcements' => array(
                'label' => 'SYS_ANNOUNCEMENTS',
                'section' => 'content_modules',
                'enabledBy' => array('announcements_module_enabled'),
                'tables' => array('announcements')
            ),
            'events' => array(
                'label' => 'SYS_EVENTS',
                'section' => 'content_modules',
                'enabledBy' => array('events_module_enabled'),
                'tables' => array('events', 'rooms')
            ),
            'forum' => array(
                'label' => 'SYS_FORUM',
                'section' => 'content_modules',
                'enabledBy' => array('forum_module_enabled'),
                'tables' => array('forum_topics', 'forum_posts')
            ),
            'inventory' => array(
                'label' => 'SYS_INVENTORY',
                'section' => 'content_modules',
                'enabledBy' => array('inventory_module_enabled'),
                'tables' => array('inventory_fields', 'inventory_field_select_options', 'inventory_items', 'inventory_item_data', 'inventory_item_borrow_data')
            ),
            'weblinks' => array(
                'label' => 'SYS_WEBLINKS',
                'section' => 'content_modules',
                'enabledBy' => array('weblinks_module_enabled'),
                'tables' => array('links')
            ),
            'profile_role_settings' => array(
                'label' => 'SYS_CHANGELOG_AREA_PROFILE_ROLE_SETTINGS',
                'section' => 'settings',
                'enabledBy' => array(),
                'tables' => array('roles', 'role_dependencies', 'user_fields', 'user_field_select_options', 'user_relation_types')
            ),
            'reports' => array(
                'label' => 'SYS_CHANGELOG_AREA_REPORTS',
                'section' => 'settings',
                'enabledBy' => array(),
                'tables' => array('lists', 'list_columns', 'category_report')
            ),
            'sso' => array(
                'label' => 'SYS_SSO',
                'section' => 'settings',
                'enabledBy' => array('sso_saml_enabled', 'sso_oidc_enabled'),
                'tables' => array('saml_clients', 'oidc_clients', 'sso_keys')
            ),
            'preferences' => array(
                'label' => 'SYS_SETTINGS',
                'section' => 'settings',
                'enabledBy' => array(),
                'tables' => array('organizations', 'menu', 'preferences', 'texts', 'categories')
            ),
            'other' => array(
                'label' => 'SYS_ALL_OTHERS',
                'section' => 'other',
                'enabledBy' => array(),
                'tables' => array('others')
            )
        );

        // An extension may either define a new area or add its tables to an existing one, so the
        // registered definition is merged into the existing area instead of replacing it.
        foreach (self::$customCallbacks['getAreaArray'] as $areaId => $area) {
            if (isset($areas[$areaId])) {
                $areas[$areaId]['tables'] = array_merge($areas[$areaId]['tables'], $area['tables'] ?? array());
                unset($area['tables']);
                $areas[$areaId] = array_merge($areas[$areaId], $area);
            } else {
                $areas[$areaId] = array_merge(array('label' => '', 'section' => 'other', 'enabledBy' => array(), 'tables' => array()), $area);
            }
        }

        // Every table that no area claims is configured together with the unnamed tables. Without
        // this a table of a third-party extension would have no checkbox at all.
        $assignedTables = array();
        foreach ($areas as $area) {
            $assignedTables = array_merge($assignedTables, $area['tables']);
        }
        $areas['other']['tables'] = array_values(array_unique(array_merge(
            $areas['other']['tables'],
            array_diff(array_keys(self::getTableLabel()), $assignedTables)
        )));

        return $areas;
    }

    /**
     * The areas of getAreas() that belong to a module that is enabled. An area of a disabled module
     * must not only be hidden but be left out of the form completely: the preferences of its tables
     * are kept unchanged, so switching the module on again restores the previous configuration.
     *
     * @return array The displayed areas in the same shape as getAreas()
     * @throws Exception
     */
    public static function getVisibleAreas(): array
    {
        global $gSettingsManager;

        $areas = self::getAreas();

        foreach ($areas as $areaId => $area) {
            if (count($area['enabledBy']) === 0) {
                continue;
            }

            $moduleEnabled = false;
            foreach ($area['enabledBy'] as $preferenceName) {
                if ($gSettingsManager->has($preferenceName) && $gSettingsManager->getInt($preferenceName) > 0) {
                    $moduleEnabled = true;
                    break;
                }
            }

            if (!$moduleEnabled) {
                unset($areas[$areaId]);
            }
        }

        return $areas;
    }


    /**
     * Return an Entity-derived object for the given module. No ID or UUID is set by default,
     * but
     * @param string $module The module name (database table without prefix)
     * @return Entity|null An empty object for the given table.
     *
     * **Code example**
     * ```
     * $usr = ChangelogService::getObjectForTable('users');
     * $usr->readDataById(500);
     * ```
     * @throws Exception
     */
    public static function getObjectForTable(string $module): Entity | null {
        global $gDb, $gProfileFields;

        if (str_starts_with($module, TABLE_PREFIX . '_')) {
            $module = substr($module, strlen(TABLE_PREFIX) + 1);
        }
        // HANDLE REGISTERED CALLBACKS, THEN DEFAULT PROCESSING
        // First process callbacks defined for the given module:
        if (!empty($module) && array_key_exists($module, self::$customCallbacks['getObjectForTable'])) {
            $callback = self::$customCallbacks['getObjectForTable'][$module];
            $val = self::evaluateCallback($callback, $module);
            if (!empty($val)) return $val;
        }
        // second (if first step does not yield a match) process callbacks defined for ALL values:
        if (!empty($module) && array_key_exists('', self::$customCallbacks['getObjectForTable'])) {
            foreach (self::$customCallbacks['getObjectForTable'][''] as $callback) {
                $val = self::evaluateCallback($callback, $module);
                if (!empty($val)) return $val;
            }
        }
        // If none of the callbacks matches, proceed with the default processing...
        switch ($module) {
            case 'users':
            case 'user_data':
                return new User($gDb, $gProfileFields);
            case 'announcements':
                return new Announcement($gDb);
            case 'categories':
                return new Category($gDb);
            case 'category_report' :
                return  new Entity($gDb, TBL_CATEGORY_REPORT, 'crt');
            case 'events' :
                return new Event($gDb);
            case 'files':
                return new File($gDb);
            case 'folders' :
                return new Folder($gDb);
            case 'links' :
                return new Weblink($gDb);
            case 'lists' :
                return new ListConfiguration($gDb);
            case 'list_columns':
                return new ListColumns($gDb);
            case 'members':
                return new Membership($gDb);
            case 'menu':
                return new MenuEntry($gDb);
            case 'organizations':
                return new Organization($gDb);
            case 'photos':
                return new Album($gDb);
            case 'preferences':
                return new Preferences($gDb);
            case 'registrations':
                return new UserRegistration($gDb, $gProfileFields);
            case 'roles':
                return new Role($gDb);
            //case 'roles_rights':
                //return new RolesRights($gDb, '', 0);
            case 'roles_rights_data':
                return new RolesRightsData($gDb);
            case 'role_dependencies':
                return new RolesDependencies($gDb);
            case 'rooms':
                return new Room($gDb);
            case 'texts':
                return new Text($gDb);
            case 'user_field_select_options':
            case 'user_fields':
                return new ProfileField($gDb);
            case 'user_relations':
                return new UserRelation($gDb);
            case 'user_relation_types':
                return new UserRelationType($gDb);
            case 'forum_topics':
                return new Topic($gDb);
            case 'forum_posts':
                return new Post($gDb);
            case 'saml_clients':
                return new SAMLClient($gDb);
            case 'oidc_clients':
                return new OIDCClient($gDb);
            case 'sso_keys':
                return new Key($gDb);
            case 'inventory_field_select_options':
            case 'inventory_fields':
                return new ItemField($gDb);
            case 'inventory_item_data':
            case 'inventory_item_borrow_data':
            case 'inventory_items':
                return new Item($gDb);
            default:
                return null;
        }
    }

    /**
     * Return a named array of all available field / database column names and translations.
     * The array values can either be a simple (translatable) string or an array of the form
     *    array('name' => 'SYS_LEADER', 'type' => 'BOOL')
     * Possible types are BOOL, CATEGORY, ICON, URL, ROLE, ROOM, EMAIL, ORG, FOLDER, ICON, etc.
     * If type is CUSTOM_LIST, an additional key 'entries' can be used to provide explicit value
     * transformations / translations.
     *
     * The type can later be fed to the ChangelogService::formatValue function for proper HTML formatting
     * of a field value.
     *
     * @return array
     *
     * **Code example**
     * ```
     * $fieldNames = ChangelogService::getFieldTranslations();
     * $membershipStartTitle = Language::translateIfTranslationStrId($fieldNames['mem_begin']);  // returns 'SYS_MEMBERSHIP_START'
     * $leaderInfo = $fieldNames['mem_leader'];   // returns ['name' => 'SYS_LEADER, 'type' => 'BOOL']
     * ```
     * @throws Exception
     */
    public static function getFieldTranslations(): array
    {
        global $gL10n;

        // The result contains translated texts, so it can only be reused for the same language.
        if (self::$fieldTranslationsCache !== null
            && self::$fieldTranslationsLanguage === $gL10n->getLanguage()) {
            return self::$fieldTranslationsCache;
        }

        $userFieldText = array(
            'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
            'DATE' => $gL10n->get('SYS_DATE'),
            'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER'),
            'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
            'EMAIL' => $gL10n->get('SYS_EMAIL'),
            'NUMBER' => $gL10n->get('SYS_NUMBER'),
            'PHONE' => $gL10n->get('SYS_PHONE'),
            'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
            'TEXT' => $gL10n->get('SYS_TEXT') . ' (100 ' . $gL10n->get('SYS_CHARACTERS') . ')',
            'TEXT_BIG' => $gL10n->get('SYS_TEXT') . ' (4000 ' . $gL10n->get('SYS_CHARACTERS') . ')',
            'URL' => $gL10n->get('SYS_URL')
        );

        $memApprovedValues = array(
            ModuleEvents::MEMBER_APPROVAL_STATE_INVITED => array(
                'text' => 'SYS_EVENT_PARTICIPATION_INVITED',
                'icon' => 'calendar2-check-fill'
            ),
            ModuleEvents::MEMBER_APPROVAL_STATE_ATTEND => array(
                'text' => 'SYS_EVENT_PARTICIPATION_ATTEND',
                'icon' => 'check-circle-fill'
            ),
            ModuleEvents::MEMBER_APPROVAL_STATE_TENTATIVE => array(
                'text' => 'SYS_EVENT_PARTICIPATION_TENTATIVE',
                'icon' => 'question-circle-fill'
            ),
            ModuleEvents::MEMBER_APPROVAL_STATE_REFUSED => array(
                'text' => 'SYS_EVENT_PARTICIPATION_CANCELED',
                'icon' => 'x-circle-fill'
            )
        );

        $translations = array(
            'mem_begin' =>                 'SYS_MEMBERSHIP_START',
            'mem_end' =>                   'SYS_MEMBERSHIP_END',
            'mem_leader' =>                array('name' => 'SYS_LEADER', 'type' => 'BOOL'),
            'mem_approved' =>              array('name' => 'SYS_MEMBERSHIP_APPROVED', 'type' => 'CUSTOM_LIST', 'entries' => $memApprovedValues),
            'mem_count_guests' =>          'SYS_SEAT_AMOUNT',
            'mem_timestamp_change' =>      'SYS_CHANGED_AT',
            'mem_usr_id_change' =>         'SYS_CHANGED_BY',
            'mem_usr_id_create' =>         'SYS_CREATED_AT',
            'mem_timestamp_create' =>      'SYS_CREATED_BY',
            'mem_comment' =>               'SYS_COMMENT',


            'usr_password' =>              'SYS_PASSWORD',
            'usr_photo' =>                 'SYS_PROFILE_PHOTO',
            'usr_login_name' =>            'SYS_USERNAME',
            'usr_uuid' =>                  'SYS_UNIQUE_ID',
            'usr_timestamp_change' =>      'SYS_CHANGED_AT',
            'usr_usr_id_change' =>         'SYS_CHANGED_BY',
            'usr_usr_id_create' =>         'SYS_CREATED_AT',
            'usr_timestamp_create' =>      'SYS_CREATED_BY',


            'usf_name' =>                  'SYS_NAME',
            'usf_name_intern' =>           'SYS_INTERNAL_NAME',
            'usf_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'usf_type' =>                  array('name' => 'SYS_TYPE', 'type' => 'CUSTOM_LIST', 'entries' => $userFieldText),
            'usf_description' =>           'SYS_DESCRIPTION',
            'usf_default_value' =>         'SYS_DEFAULT_VALUE',
            'usf_regex' =>                 'SYS_REGULAR_EXPRESSION',
            'usf_disabled' =>              array('name' => 'SYS_DISABLED', 'type' => 'BOOL'),
            'usf_hidden' =>                array('name' => 'SYS_HIDDEN', 'type' => 'BOOL'),
            'usf_registration' =>          'ORG_FIELD_REGISTRATION',
            'usf_sequence' =>              'SYS_ORDER',
            'usf_icon' =>                  array('name' => 'SYS_ICON', 'type' => 'ICON'),
            'usf_url' =>                   array('name' => 'SYS_URL', 'type' => 'URL'),
            'usf_required_input' =>        array('name' => 'SYS_REQUIRED_INPUT', 'type' => 'BOOL'),
            'ufo_value' =>                 'SYS_VALUE',
            'ufo_usf_id' =>                'SYS_PROFILE_FIELD',
            'ufo_sequence' =>              'SYS_ORDER',
            'ufo_obsolete' =>             array('name' => 'SYS_DELETED', 'type' => 'BOOL'),

            'prf_value' =>                 'SYS_VALUE',
            'prf_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),

            'ann_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'ann_headline' =>              'SYS_HEADLINE',
            'ann_description' =>           'SYS_DESCRIPTION',

            'room_name' =>                 'SYS_NAME',
            'room_description' =>          'SYS_DESCRIPTION',
            'room_capacity' =>             'SYS_CAPACITY',
            'room_overhang' =>             'SYS_OVERHANG',

            'dat_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'dat_rol_id'=>                 array('name' => 'SYS_ROLE', 'type' => 'ROLE'),
            'dat_room_id' =>               array('name' => 'SYS_ROOM', 'type' => 'ROOM'),
            'dat_begin' =>                 'SYS_START',
            'dat_end' =>                   'SYS_END',
            'dat_all_day' =>               array('name' => 'SYS_ALL_DAY', 'type' => 'BOOL'),
            'dat_headline' =>              'SYS_HEADLINE',
            'dat_description' =>           'SYS_DESCRIPTION',
            'dat_highlight' =>             array('name' => 'SYS_HIGHLIGHT_EVENT', 'type' => 'BOOL'),
            'dat_location' =>              'SYS_VENUE',
            'dat_country' =>               array('name' => 'SYS_COUNTRY', 'type' => 'COUNTRY'),
            'dat_deadline' =>              'SYS_DEADLINE',
            'dat_max_members' =>           'SYS_MAX_PARTICIPANTS',
            'dat_allow_comments' =>        array('name' => 'SYS_ALLOW_USER_COMMENTS', 'type' => 'BOOL'),
            'dat_additional_guests' =>     array('name' => 'SYS_ALLOW_ADDITIONAL_GUESTS', 'type' => 'BOOL'),
            'dat_evr_id' =>                array('name' => 'SYS_RECURRENCE_RULE', 'type' => 'RECURRENCE_RULE'),
            'dat_recurrence_original_begin' => array('name' => 'SYS_RECURRENCE_ORIGINAL_BEGIN', 'type' => 'DATETIME'),
            'dat_recurrence_status' =>     array('name' => 'SYS_RECURRENCE_STATUS', 'type' => 'CUSTOM_LIST', 'entries' => array('master' => $gL10n->get('SYS_RECURRENCE_STATUS_MASTER'), 'generated' => $gL10n->get('SYS_RECURRENCE_STATUS_GENERATED'), 'modified' => $gL10n->get('SYS_RECURRENCE_STATUS_MODIFIED'), 'cancelled' => $gL10n->get('SYS_RECURRENCE_STATUS_CANCELLED'))),
            'dat_recurrence_scope' =>      array('name' => 'SYS_RECURRENCE_SCOPE', 'type' => 'CUSTOM_LIST', 'entries' => array('this' => $gL10n->get('SYS_RECURRENCE_SCOPE_THIS'), 'series' => $gL10n->get('SYS_RECURRENCE_SCOPE_SERIES'))),

            'rol_name' =>                  'SYS_NAME',
            'rol_description' =>           'SYS_DESCRIPTION',
            'rol_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'rol_mail_this_role' =>        array('name' => 'SYS_SEND_MAILS', 'type' => 'CUSTOM_LIST', 'entries' => array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'), 3 => $gL10n->get('SYS_ALSO_VISITORS'))),

            'rol_view_memberships' =>      array('name' => 'SYS_VIEW_ROLE_MEMBERSHIPS', 'type' => 'CUSTOM_LIST', 'entries' => array(0 => $gL10n->get('SYS_NOBODY'), 3 => $gL10n->get('SYS_LEADERS'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'))),
            'rol_view_members_profiles' => array('name' => 'SYS_VIEW_PROFILES_OF_ROLE_MEMBERS', 'type' => 'CUSTOM_LIST', 'entries' => array(0 => $gL10n->get('SYS_NOBODY'), 3 => $gL10n->get('SYS_LEADERS'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'))),
            'rol_leader_rights' =>         array('name' => 'SYS_LEADER', 'type' => 'CUSTOM_LIST', 'entries' => array(0 => $gL10n->get('SYS_NO_ADDITIONAL_RIGHTS'), 1 => $gL10n->get('SYS_ASSIGN_MEMBERS'), 2 => $gL10n->get('SYS_EDIT_MEMBERS'), 3 => $gL10n->get('SYS_ASSIGN_EDIT_MEMBERS'))),
            'rol_lst_id' =>                array('name' => 'SYS_DEFAULT_LIST', 'type' => 'LIST'),
            'rol_default_registration' =>  array('name' => 'SYS_DEFAULT_ASSIGNMENT_REGISTRATION', 'type' => 'BOOL'),
            'rol_max_members' =>           'SYS_MAX_PARTICIPANTS',
            'rol_cost' =>                  'SYS_CONTRIBUTION',
            'rol_cost_period' =>           array('name' => 'SYS_CONTRIBUTION_PERIOD', 'type' => 'CUSTOM_LIST', 'entries' => Role::getCostPeriods()),
            'rol_assign_roles' =>          array('name' => 'SYS_RIGHT_ASSIGN_ROLES', 'type' => 'BOOL'),
            'rol_all_lists_view' =>        array('name' => 'SYS_RIGHT_ALL_LISTS_VIEW', 'type' => 'BOOL'),
            'rol_approve_users' =>         array('name' => 'SYS_RIGHT_APPROVE_USERS', 'type' => 'BOOL'),
            'rol_mail_to_all' =>           array('name' => 'SYS_RIGHT_MAIL_TO_ALL', 'type' => 'BOOL'),
            'rol_edit_user' =>             array('name' => 'SYS_RIGHT_EDIT_USER', 'type' => 'BOOL'),
            'rol_profile' =>               array('name' => 'SYS_RIGHT_PROFILE', 'type' => 'BOOL'),
            'rol_announcements' =>         array('name' => 'SYS_RIGHT_ANNOUNCEMENTS', 'type' => 'BOOL'),
            'rol_events' =>                array('name' => 'SYS_RIGHT_DATES', 'type' => 'BOOL'),
            'rol_photo' =>                 array('name' => 'SYS_RIGHT_PHOTOS', 'type' => 'BOOL'),
            'rol_documents_files' =>       array('name' => 'SYS_RIGHT_DOCUMENTS_FILES', 'type' => 'BOOL'),
            'rol_forum_admin' =>           array('name' => 'SYS_RIGHT_FORUM', 'type' => 'BOOL'),
            'rol_weblinks' =>              array('name' => 'SYS_RIGHT_WEBLINKS', 'type' => 'BOOL'),
            'rol_valid' =>                 array('name' => 'SYS_ACTIVATE_ROLE', 'type' => 'BOOL'),

            'rol_start_date' =>            'SYS_VALID_FROM',
            'rol_end_date' =>              'SYS_VALID_TO',
            'rol_start_time' =>            'SYS_TIME_FROM',
            'rol_end_time' =>              'SYS_TIME_TO',
            'rol_weekday' =>               array('name' => 'SYS_WEEKDAY', 'type' => 'WEEKDAY'),
            'rol_location' =>              'SYS_MEETING_POINT',

            'ror_name_intern' =>           'SYS_INTERNAL_NAME',
            'ror_table' =>                 'SYS_TABLE',
            // 'ror_ror_id_parent' =>         '',

            'gbo_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'gbo_name' =>                  'SYS_NAME',
            'gbo_text' =>                  'SYS_MESSAGE',
            'gbo_email' =>                 array('name' => 'SYS_EMAIL', 'type' => 'EMAIL'),
            'gbo_homepage' =>              array('name' => 'SYS_WEBSITE', 'type' => 'URL'),
            'gbo_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),

            'gbc_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'gbc_name' =>                  'SYS_NAME',
            'gbc_text' =>                  'SYS_MESSAGE',
            'gbc_email' =>                 array('name' => 'SYS_EMAIL', 'type' => 'EMAIL'),
            'gbc_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),

            'fot_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'fot_fop_id_first_post' =>     array('name' => 'SYS_FORUM_POST', 'type' => 'POST'),
            'fot_title' =>                 'SYS_TITLE',
            'fop_text' =>                  'SYS_TEXT',
            'fop_fot_id' =>                array('name' => 'SYS_FORUM_TOPIC', 'type' => 'TOPIC'),

            'inf_type' =>                  array('name' => 'SYS_TYPE', 'type' => 'CUSTOM_LIST', 'entries' => $userFieldText),
            'inf_name' =>                  'SYS_NAME',
            'inf_description' =>           'SYS_DESCRIPTION',
            'inf_required_input' =>        array('name' => 'SYS_REQUIRED_INPUT', 'type' => 'BOOL'),
            'inf_sequence' =>              'SYS_ORDER',
            'ini_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'ini_status' =>                array('name' => 'SYS_INVENTORY_STATUS', 'type' => 'INVENTORY_STATUS'),
            'ini_picture' =>               array('name' => 'SYS_INVENTORY_ITEM_PICTURE', 'type' => 'PICTURE'),
            // Changes of the item data are logged with the item field as field name, so the
            // following column names only occur in entries of previous Admidio versions.
            'ind_value' =>                 'SYS_VALUE',
            'ind_value_bool' =>            'SYS_VALUE',
            'ind_value_date' =>            'SYS_VALUE',
            'ind_value_mail' =>            'SYS_VALUE',
            'ind_value_url' =>             'SYS_VALUE',
            'ind_value_icon' =>            'SYS_VALUE',
            'ind_value_usr' =>             'SYS_VALUE',
            'inb_last_receiver' =>         array('name' => 'SYS_INVENTORY_LAST_RECEIVER', 'type' => 'USER'),
            'inb_borrow_date' =>           array('name' => 'SYS_INVENTORY_BORROW_DATE', 'type' => 'DATETIME'),
            'inb_return_date' =>           array('name' => 'SYS_INVENTORY_RETURN_DATE', 'type' => 'DATETIME'),
            'ifo_value' =>                 'SYS_VALUE',
            'ifo_inf_id' =>                'SYS_INVENTORY_ITEMFIELD',
            'ifo_sequence' =>              'SYS_ORDER',
            'ifo_obsolete' =>              array('name' => 'SYS_DELETED', 'type' => 'BOOL'),

            'lnk_name' =>                  'SYS_LINK_NAME',
            'lnk_description' =>           'SYS_DESCRIPTION',
            'lnk_url' =>                   array('name' => 'SYS_LINK_ADDRESS', 'type' => 'URL'),
            'lnk_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
            'lnk_counter' =>               'SYS_COUNTER',
            'lnk_sequence' =>             'SYS_ORDER',

            'txt_text' =>                  array('name' => 'SYS_TEXT', 'type' => 'TEXT_BIG'),
            'txt_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),

            'fol_name' =>                  'SYS_NAME',
            'fol_description' =>           'SYS_DESCRIPTION',
            'fol_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'fol_fol_id_parent' =>         array('name' => 'SYS_FOLDER', 'type' => 'FOLDER'),
            'fol_path' =>                  'SYS_PATH',
            'fol_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),
            'fol_public' =>                array('name' => 'SYS_VISIBLE', 'type' => 'BOOL'),

            'fil_name' =>                  'SYS_NAME',
            'fil_description' =>           'SYS_DESCRIPTION',
            'fil_fol_id' =>                array('name' => 'SYS_FOLDER', 'type' => 'FOLDER'),
            'fil_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'fil_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),
            // 'fil_counter' =>               '', // not logged!

            'pho_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'pho_name'  =>                 'SYS_ALBUM',
            'pho_description' =>           'SYS_DESCRIPTION',
            'pho_pho_id_parent' =>         array('name' => 'SYS_PARENT_ALBUM', 'type' => 'ALBUM'),
            'pho_quantity'  =>             'SYS_QUANTITY',
            'pho_begin' =>                 'SYS_START',
            'pho_end' =>                   'SYS_END',
            'pho_photographers' =>         'SYS_PHOTOS_BY',
            'pho_locked' =>                array('name' => 'SYS_LOCK_ALBUM', 'type' => 'BOOL'),

            'org_shortname' =>             'SYS_NAME_ABBREVIATION',
            'org_longname' =>              'SYS_NAME',
            'org_org_id_parent' =>         array('name' => 'SYS_PARENT_ORGANIZATION', 'type'=> 'ORG'),
            'org_homepage' =>              array('name' => 'SYS_HOMEPAGE', 'type'=> 'URL'),
            'org_email_administrator' =>   array('name' => 'SYS_EMAIL_ADMINISTRATOR', 'type' => 'EMAIL'),
            'org_show_org_select' =>       array('name' => 'SYS_SHOW_ORGANIZATION_SELECT', 'type' => 'BOOL'),

            'men_name' =>                  'SYS_NAME',
            'men_name_intern' =>           'SYS_INTERNAL_NAME',
            'men_description' =>           'SYS_DESCRIPTION',
            'men_men_id_parent' =>         array('name' => 'SYS_MENU_LEVEL', 'type' => 'MENU'), // Parents are hard-coded and have no modification page! -> No link possible!
            'men_com_id' =>                array('name' => 'SYS_MODULE_RIGHTS', 'type' => 'COMPONENT'),
            //'men_node' =>                  '', // men_node cannot be set by the user (section headings in the frontend)!
            'men_order' =>                 'SYS_ORDER',
            'men_standard' =>              $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_MENU_ITEM'))),
            'men_url' =>                   array('name' => 'SYS_URL', 'type' => 'URL'),
            'men_icon' =>                  array('name' => 'SYS_ICON', 'type' => 'ICON'),

            'urt_name' =>                  'SYS_NAME',
            'urt_name_male' =>             'SYS_MALE',
            'urt_name_female' =>           'SYS_FEMALE',
            'urt_edit_user' =>             array('name' => 'SYS_EDIT_USER_IN_RELATION', 'type' => 'BOOL'),
            'urt_id_inverse' =>            array('name' => 'SYS_OPPOSITE_RELATIONSHIP', 'type' => 'RELATION_TYPE'),

            'crt_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'crt_name' =>                  'SYS_NAME',
            'crt_col_fields' =>            'SYS_COLUMN_SELECTION',
            'crt_selection_role' =>        array('name' => 'SYS_ROLE_SELECTION', 'type' => 'ROLE'),
            'crt_selection_cat' =>         array('name' => 'SYS_CAT_SELECTION', 'type' => 'CATEGORY'),
            'crt_number_col' =>            array('name' => $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')', 'type' => 'BOOL'),

            'lst_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'lst_usr_id' =>                array('name' => 'SYS_MEMBER', 'type' => 'USER'),
            'lst_name' =>                  'SYS_NAME',
            'lst_global' =>                array('name' => 'SYS_CONFIGURATION_ALL_USERS', 'type' => 'BOOL'),
            'lsc_number' =>                'SYS_NUMBER',
            'lsc_filter' =>                'SYS_CONDITION',
            'lsc_sort' =>                  'SYS_ORDER',

            'cat_name' =>                  'SYS_NAME',
            'cat_name_intern' =>           'SYS_INTERNAL_NAME',
            'cat_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            //'cat_type' =>                  '', // Holds indicators like USF, ROL, LNK, EVT, ANN,
            'cat_system' =>                array('name' => 'SYS_SYSTEM', 'type' => 'BOOL'),
            'cat_default' =>               array('name' => $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_CATEGORY'))), 'type' => 'BOOL'),
            'cat_sequence' =>              'SYS_ORDER',

            'smc_client_id' =>              'SYS_SSO_CLIENT_ID',
            'smc_client_name' =>            'SYS_SSO_CLIENT_NAME',
            'smc_metadata_url' =>           'SYS_SSO_METADATA_URL',
            'smc_acs_url' =>                'SYS_SSO_ACS_URL',
            'smc_slo_url' =>                'SYS_SSO_SLO_URL',
            'smc_x509_certificate' =>       'SYS_SSO_X509_CERTIFICATE',
            'smc_userid_field' =>           'SYS_SSO_USERID_FIELD',
            'smc_field_mapping' =>          array('name' => 'SYS_SSO_ATTRIBUTES', 'type' => 'SAML_field_mapping'),
            'smc_role_mapping' =>           array('name' => 'SYS_SSO_ROLESMAP', 'type' => 'SSO_roles_mapping'),
            'smc_validate_signatures' =>    array('name' => 'SYS_SSO_VALIDATE_SIGNATURES', 'type' => 'BOOL'),
            'smc_require_auth_signed' =>    array('name' => 'SYS_SSO_REQUIRE_AUTHN_SIGNED', 'type' => 'BOOL'),
            'smc_sign_assertions' =>        array('name' => 'SYS_SSO_SIGN_ASSERTIONS', 'type' => 'BOOL'),
            'smc_encrypt_assertions' =>     array('name' => 'SYS_SSO_ENCRYPT_ASSERTIONS', 'type' => 'BOOL'),
            'smc_assertion_lifetime' =>     'SYS_SSO_SAML_ASSERTION_LIFETIME',
            'smc_allowed_clock_skew' =>     'SYS_SSO_SAML_ALLOWED_CLOCK_SKEW',
            
            'ocl_client_id' =>              'SYS_SSO_CLIENT_ID',
            'ocl_client_name' =>            'SYS_SSO_CLIENT_NAME',
            'ocl_client_secret' =>          'SYS_SSO_CLIENT_SECRET',
            'ocl_redirect_uri' =>           'SYS_SSO_REDIRECT_URI',
            'ocl_userid_field' =>           'SYS_SSO_USERID_FIELD',
            'ocl_field_mapping' =>          array('name' => 'SYS_SSO_ATTRIBUTES', 'type' => 'OIDC_field_mapping'),
            'ocl_role_mapping' =>           array('name' => 'SYS_SSO_ROLESMAP', 'type' => 'SSO_roles_mapping'),
            // 'ocl_grant_types' =>            'TODO',
            // 'ocl_scope' =>                  'TODO',        

            'key_org_id' =>                 array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
            'key_name' =>                   'SYS_NAME',
            'key_algorithm' =>              'SYS_SSO_KEY_ALGORITHM',
            'key_private' =>                'SYS_SSO_KEY_PRIVATE',
            'key_public' =>                 'SYS_SSO_KEY_PUBLIC',
            'key_certificate' =>            'SYS_SSO_KEY_CERTIFICATE',
            'key_expires_at' =>             array('name' => 'SYS_SSO_KEY_EXPIRES', 'type' => 'DATETIME'),
            'key_is_active' =>              array('name' => 'SYS_SSO_KEY_ACTIVE', 'type' => 'BOOL'),

        );
        self::$fieldTranslationsLanguage = $gL10n->getLanguage();
        self::$fieldTranslationsCache = array_merge($translations, self::$customCallbacks['getFieldTranslations']);
        return self::$fieldTranslationsCache;
    }



    /**
     * Create an HTML link to the admidio page corresponding to the given module (DB table without prefix). Optional object ID
     * and/or UUID can be passed and will be used in the HREF, if supported.
     * If the module / table does not provide a page, the text without link will be returned.
     *
     * Most modules have switched to the UUID-approach, so in most cases the id will be ignored and only the $uuid will be used.
     *
     * @param string $text The display text of the link
     * @param string $module The admidio module / database table without prefix
     * @param int|string $id The object ID
     * @param string $uuid The object UUID
     * @return string HTML Link to the module's view or edit page for the given object, if such a page is provided at all. If not, the text is returned without adding a link.
     *
     * **Code example**
     * ```
     * $user = new User($gDb, 1);
     * $link = self::createLink($user->readableName(), 'users', 0, $user->getValue('usr_uuid'));
     * ```
     */
    public static function createLink(string $text, string $module, int|string $id, string $uuid = ''): string
    {
        $url = '';
        // HANDLE REGISTERED CALLBACKS, THEN DEFAULT PROCESSING
        // First process callbacks defined for the given module:
        if (!empty($module) && empty($url) && array_key_exists($module, self::$customCallbacks['createLink'])) {
            $callback = self::$customCallbacks['createLink'][$module];
            $url = self::evaluateCallback($callback, $text, $module, $id, $uuid);
        }
        // second (if first step does not yield a match) process callbacks defined for ALL values:
        if (!empty($module) && empty($url) && array_key_exists('', self::$customCallbacks['createLink'])) {
            foreach (self::$customCallbacks['createLink'][''] as $callback) {
                if (empty($url)) {
                    $url = self::evaluateCallback($callback, $text, $module, $id, $uuid);
                }
            }
        }
        // If none of the callbacks matches, proceed with the default processing...
        if (empty($url)) {
            switch ($module) {
                case 'users': // Fall through
                case 'user_data':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $uuid)); break;
                case 'announcements':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements.php', array('mode' => 'edit', 'announcement_uuid' => $uuid)); break;
                case 'categories' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/categories.php', array('mode' => 'edit', 'uuid' => $uuid)); break; // Note: the type is no longer needed (only recommended, but we don't have it in the changelog DB)
                case 'category_report' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences.php'); break;
                case 'events' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/events.php', array('mode' => 'edit', 'dat_uuid' => $uuid)); break;
                case 'files' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files.php', array('mode' => 'download', 'file_uuid' => $uuid)); break;
                case 'folders' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files.php', array('folder_uuid' => $uuid)); break;
                case 'forum_topics' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/forum.php', array('mode' => 'topic', 'topic_uuid' => $uuid)); break;
                case 'forum_posts' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/forum.php', array('mode' => 'post_edit', 'post_uuid' => $uuid)); break;
                case 'inventory_field_select_options': // Fall through
                case 'inventory_fields' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/inventory.php', array('mode' => 'field_edit', 'uuid' => $uuid)); break;
                case 'inventory_item_data' : // Fall through
                case 'inventory_items' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/inventory.php',array('mode' => 'item_edit', 'item_uuid' => $uuid)); break;
                case 'inventory_item_borrow_data' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/inventory.php', array('mode' => 'item_edit_borrow', 'item_uuid' => $uuid)); break;
                case 'links' :
                    $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php', array('link_uuid' => $uuid)); break;
                case 'lists' :
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
                case 'list_columns':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
                case 'members':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('role_list' => $uuid)); break;
                case 'menu':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu.php', array('mode' => 'edit', 'uuid' => $uuid)); break;
                // case 'organizations': // There is currently no edit page for other organizations! One needs to log in to the other org!
                //     $url = SecurityUtils::encodeUrl(); break;
                case 'photos':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $uuid)); break;
                // case 'preferences': // There is just one preferences page, but no way to link to individual sections or preference items!
                //     $url = SecurityUtils::encodeUrl(); break;
                // case 'registrations':
                //     $url = SecurityUtils::encodeUrl(); break;
                case 'roles':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('role_list' => $uuid)); break;
                case 'roles_rights':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('mode' => 'edit', 'role_uuid' => $uuid)); break;
                case 'roles_rights_data':
                    // The log_record_linkid contains the table and the uuid encoded as 'table':'UUID' => split and call Create linke with the new table!
                    if (strpos($id, ':') !== false) {
                        // Split into table and UUID
                        [$table, $id] = explode(':', $id, 2);
                    } else {
                        $table = ''; // Table is empty
                    }
                    return self::createLink($text, $table, $id, $id);
                case 'role_dependencies':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles.php', array('mode' => 'edit', 'role_uuid' => $uuid)); break;
                case 'rooms':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php', array('room_uuid' => $uuid)); break;
                // case 'texts': // Texts can be modified in the preferences, but there is no direct link to the notifications sections, where the texts are located at the end!
                //     $url = SecurityUtils::encodeUrl(); break;
                case 'user_field_select_options':
                case 'user_fields':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields.php', array('mode' => 'edit', 'uuid' => $uuid)); break;
                case 'user_relations': // For user relations, we don't link to the modification of the individual relation, but to the user1
                    // $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/userrelations_new.php', array('user_uuid' => $uuid)); break;
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $uuid)); break;
                case 'user_relation_types':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php', array('urt_uuid' => $uuid)); break;
                case 'saml_clients':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/sso/clients.php', array('mode' => 'edit_saml', 'uuid' => $uuid)); break;
                case 'oidc_clients':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/sso/clients.php', array('mode' => 'edit_oidc', 'uuid' => $uuid)); break;
                case 'sso_keys':
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/sso/keys.php', array('mode' => 'edit', 'uuid' => $uuid)); break;
            }
        }
        // The returned string is rendered as HTML by the changelog DataTable, while every caller
        // passes plain text that originates from the database (record names, related object names,
        // user names). Encode it here, at the single place where the return value is assembled.
        // Note: the 'roles_rights_data' case above returns early via a recursive call, which runs
        // through this same encoding exactly once.
        $text = SecurityUtils::encodeHTML(StringUtils::strStripTags($text));

        if ($url != '') {
            return '<a href="'.$url.'">'.$text.'</a>';
        } else {
            return $text;
        }
    }

    /**
     * Format a recurrence rule id as a readable recurrence summary.
     * @throws Exception
     */
    private static function formatRecurrenceRuleValue(string $value): string
    {
        global $gDb, $gL10n, $gSettingsManager;

        if (!ctype_digit($value)) {
            return $value;
        }

        $sql = 'SELECT evr_frequency, evr_interval, evr_byday, evr_end_type, evr_until, evr_count
                  FROM ' . TBL_EVENT_RECURRENCES . '
                 WHERE evr_id = ?';
        $recurrenceStatement = $gDb->queryPrepared($sql, array((int)$value));
        $recurrence = $recurrenceStatement->fetch();

        if (!$recurrence) {
            return $gL10n->get('SYS_RECURRENCE_RULE_VAR', array($value));
        }

        $frequencies = array(
            'daily' => $gL10n->get('SYS_DAILY'),
            'weekly' => $gL10n->get('SYS_WEEKLY'),
            'monthly' => $gL10n->get('SYS_MONTHLY'),
            'yearly' => $gL10n->get('SYS_ANNUALLY')
        );
        $weekdays = array(
            'MO' => $gL10n->get('SYS_MONDAY'),
            'TU' => $gL10n->get('SYS_TUESDAY'),
            'WE' => $gL10n->get('SYS_WEDNESDAY'),
            'TH' => $gL10n->get('SYS_THURSDAY'),
            'FR' => $gL10n->get('SYS_FRIDAY'),
            'SA' => $gL10n->get('SYS_SATURDAY'),
            'SU' => $gL10n->get('SYS_SUNDAY')
        );

        $summary = array();
        $summary[] = $frequencies[(string)$recurrence['evr_frequency']] ?? (string)$recurrence['evr_frequency'];
        $summary[] = $gL10n->get('SYS_RECURRENCE_INTERVAL') . ': ' . (int)$recurrence['evr_interval'];

        if ((string)$recurrence['evr_byday'] !== '') {
            $byDay = array();
            foreach (explode(',', (string)$recurrence['evr_byday']) as $day) {
                $byDay[] = $weekdays[$day] ?? $day;
            }
            $summary[] = $gL10n->get('SYS_RECURRENCE_WEEKDAYS') . ': ' . implode(', ', $byDay);
        }

        if ((string)$recurrence['evr_end_type'] === 'count') {
            $summary[] = $gL10n->get('SYS_RECURRENCE_END') . ': ' . $gL10n->get('SYS_RECURRENCE_END_AFTER_COUNT') . ' (' . (int)$recurrence['evr_count'] . ')';
        } elseif ((string)$recurrence['evr_end_type'] === 'until') {
            $until = DateTime::createFromFormat('Y-m-d H:i:s', (string)$recurrence['evr_until']);
            $untilValue = $until instanceof DateTime ? $until->format($gSettingsManager->getString('system_date')) : (string)$recurrence['evr_until'];
            $summary[] = $gL10n->get('SYS_RECURRENCE_END') . ': ' . $gL10n->get('SYS_RECURRENCE_END_ON_DATE') . ' ' . $untilValue;
        } else {
            $summary[] = $gL10n->get('SYS_RECURRENCE_END') . ': ' . $gL10n->get('SYS_RECURRENCE_END_NEVER');
        }

        return implode('; ', $summary);
    }

    /**
     * Format the given value for the given type. E.g. BOOL variables are displayed as a checked/unchecked checkbox,
     * ICON displays the icon graphics next to the icon name, an ORG will show the organisation name rather than the ID,
     * etc. For basic types, this function is more or less the same as the profile fields formatting. However, this method
     * provides many more data types, like ROOM, EVENT, CATEGORY, FOLDER, LIST, ...
     * Wherever possible (e.g. for USER, ROOM, FOLDER, ALBUM, ...), the value is also linked with the corresponding Admidio page.
     * A type CUSTOM_LIST is also implemented, which uses the named array $entries to transform the value before displaying.
     *
     * @param mixed $value The value to be formatted
     * @param mixed $type The type of the variable (e.g. 'BOOL', 'DATE', 'EMAIL', 'URL', 'USER', 'ROOM', 'EVENT', ...., 'CUSTOM_LIST')
     * @param mixed $entries if $type is 'CUSTOM_LIST', a named array of value transformations.
     * @return mixed The formatted value, if possible including a link to the corresponding Admidio page.
     *
     * **Code example**
     * ```
     * $output = ChangelogService::formatValue("http://www.admidio.org/", "URL"); // Returns a link to the URL
     * $output = ChangelogService::formatValue(1, 'USER');  // Returns a link to the administrator, text is the administrator's name
     * ```
     * @throws Exception
     */
    public static function formatValue($value, $type, $entries = []) {
        global $gSettingsManager, $gCurrentUserUUID, $gDb, $gProfileFields, $gL10n, $gCurrentOrganization;
        if ($value != '' && !in_array($type, ['SAML_field_mapping', 'SAML_roles_mapping', 'SSO_field_mapping', 'SSO_roles_mapping', 'OIDC_field_mapping', 'OIDC_roles_mapping']) ) {
            $value = SecurityUtils::encodeHTML(StringUtils::strStripTags($value));
        }

        // HANDLE REGISTERED CALLBACKS, THEN DEFAULT PROCESSING
        // First process callbacks defined for the given module:
        if (!empty($type) && array_key_exists($type, self::$customCallbacks['formatValue'])) {
            $callback = self::$customCallbacks['formatValue'][$type];
            $val = self::evaluateCallback($callback, $value, $type, $entries);
            if (!empty($val)) return $val;
        }
        // second (if first step does not yield a match) process callbacks defined for ALL values:
        if (!empty($type) && array_key_exists('', self::$customCallbacks['formatValue'])) {
            foreach (self::$customCallbacks['formatValue'][''] as $callback) {
                $val = self::evaluateCallback($callback, $value, $type, $entries);
                if (!empty($val)) return $val;
            }
        }
        // If none of the callbacks matches, proceed with the default processing...

        // if value is empty or null, then do nothing
        if ($value != '') {
            // create html for each field type
            $htmlValue = $value;

            switch ($type) {
                case 'BOOL':
                    if ($value == 1 || $value == "true") {
                        $htmlValue = '<i class="bi bi-check-square"></i>';
                    } else {
                        $htmlValue = '<i class="bi bi-square"></i>';
                    }
                    break;
                case 'DATE':
                    if ($value !== '') {
                        // date must be formatted
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'DATETIME':
                    // The value can be stored with or without a time part
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    if (!$date instanceof DateTime) {
                        $date = DateTime::createFromFormat('Y-m-d H:i', $value);
                    }
                    if ($date instanceof DateTime) {
                        $htmlValue = $date->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                    } else {
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'EMAIL':
                    // the value in db is only the position, now search for the text
                    if ($value !== '') {
                        if (!$gSettingsManager->getInt('mail_module_enabled') > 0) {
                            $emailLink = 'mailto:' . $value;
                        } else {
                            $emailLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $gCurrentUserUUID));
                        }
                        $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '" style="overflow: visible; display: inline;">' . $value . '</a>';
                    }
                    break;

                case 'URL':
                    if ($value !== '') {
                        // The value is a historic entry from the log table and may predate any
                        // input validation, so only schemes that are safe to open may be turned
                        // into a clickable link. Values without a scheme (e.g. the relative menu
                        // urls like /modules/preferences.php) stay linkable, everything else is
                        // shown as plain text. $value is already HTML encoded at this point, which
                        // leaves the scheme untouched. Browsers ignore whitespace and control
                        // characters within the scheme, so remove them before the check.
                        $scheme = preg_replace('/[\s\x00-\x1F]+/', '', $value);
                        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $scheme, $schemeMatch)
                            && !in_array(strtolower($schemeMatch[1]), array('http', 'https', 'mailto'), true)) {
                            break;
                        }

                        $displayValue = $value;

                        // trim "http://", "https://", "//"
                        if (str_contains($displayValue, '//')) {
                            $displayValue = substr($displayValue, strpos($displayValue, '//') + 2);
                        }
                        // trim after the 35th char
                        if (strlen($value) > 35) {
                            $displayValue = substr($displayValue, 0, 35) . '...';
                        }
                        $htmlValue = '<a href="' . $value . '" target="_blank" title="' . $value . '">' . $displayValue . '</a>';
                    }
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
                case 'ICON':
                    $htmlValue = '<div class="bi bi-'.$value.'"> '. $value.'</div>';
                    break;
                case 'ORG':
                    $obj = new Organization($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'organizations', $obj->getValue('org_id'), $obj->getValue('org_uuid'));
                    break;
                case 'RELATION_TYPE':
                    $obj = new UserRelationType($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'user_relation_types', $obj->getValue('urt_id'), $obj->getValue('urt_uuid'));
                    break;
                case 'ALBUM':
                    $obj = new Album($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'photos', $obj->getValue('pho_id'), $obj->getValue('pho_uuid'));
                    break;
                case 'FOLDER':
                    $obj = new Folder($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'folders', $obj->getValue('fol_id'), $obj->getValue('fol_uuid'));
                    break;
                case 'ROLE':
                    $obj = new Role($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'roles', $obj->getValue('rol_id'), $obj->getValue('rol_uuid'));
                    break;
                case 'CATEGORY':
                    $obj = new Category($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId(Language::translateIfTranslationStrId($obj->readableName())), 'categories', $obj->getValue('cat_id'), $obj->getValue('cat_uuid'));
                    break;
                case 'USER':
                    if ($value > 0) {
                        $obj = new User($gDb, $gProfileFields, $value);
                        $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'users', $obj->getValue('usr_id'), $obj->getValue('usr_uuid'));
                    } else {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname'). '"';
                        $htmlValue = '<i>' . SecurityUtils::encodeHTML(StringUtils::strStripTags($gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION',array($orgName)))) . '</i>';
                    }
                    break;
                case 'ROOM':
                    $obj = new Room($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'rooms', $obj->getValue('room_id'), $obj->getValue('room_uuid'));
                    break;
                case 'RECURRENCE_RULE':
                    $htmlValue = self::formatRecurrenceRuleValue((string)$value);
                    break;
                case 'COUNTRY':
                    $htmlValue = $gL10n->getCountryName($value);
                    break;
                case 'WEEKDAY':
                    if ($value > 0) {
                        $htmlValue = RolesService::getWeekdays($value);
                    } else {
                        $htmlValue = $value;
                    }
                    break;
                case 'LIST':
                    $obj = new ListConfiguration($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'lists', $obj->getValue('lst_id'), $obj->getValue('lst_uuid'));
                    break;
                case 'MENU':
                    $obj = new MenuEntry($gDb, $value);
                    $htmlValue = Language::translateIfTranslationStrId($obj->readableName()); //createLink(Language::translateIfTranslationStrId($obj->readableName()), 'lists', $obj->getValue('men_id'), $obj->getValue('men_uuid'));
                    break;
                case 'COMPONENT':
                    $obj = new Component($gDb, $value);
                    $htmlValue = Language::translateIfTranslationStrId($obj->readableName());
                    break;
                case 'TOPIC':
                    $obj = new Topic($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'forum_topics', $obj->getValue('fot_id'), $obj->getValue('fot_uuid'));
                    break;
                case 'POST':
                    $obj = new POST($gDb, $value);
                    $htmlValue = self::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'forum_posts', $obj->getValue('fop_id'), $obj->getValue('fop_uuid'));
                    break;
                case 'CUSTOM_LIST':
                    $value = $entries[$value]??$value;
                    $htmlValue = '';
                    if (is_array($value)) {
                        if (isset($value['icon'])) {
                            $htmlValue .= '<div class="bi bi-'.$value['icon'].'"> ';
                        }
                        if (isset($value['text'])) {
                            $htmlValue .=  $gL10n-> get($value['text']);
                        }
                        if (isset($value['icon'])) {
                            $htmlValue .= '</div>';
                        }
                    } else {
                        $htmlValue = $value;
                    }
                    break;
                case 'SAML_field_mapping':
                    $htmlValue = self::createMappingTable($value, $gL10n->get('SYS_PROFILE_FIELD'), $gL10n->get('SYS_SSO_ATTRIBUTE'), new ProfileField($gDb), ["*" => $gL10n->get('SYS_SSO_ATTRIBUTES_ALLOTHER')]);
                    break;
                case 'OIDC_field_mapping':
                    $htmlValue = self::createMappingTable($value, $gL10n->get('SYS_PROFILE_FIELD'), $gL10n->get('SYS_SSO_ATTRIBUTE'), new ProfileField($gDb), ["*" => $gL10n->get('SYS_SSO_ATTRIBUTES_NOOTHER')]);
                    break;
                case 'SSO_roles_mapping':
                    $htmlValue = self::createMappingTable($value, $gL10n->get('SYS_ROLE'), $gL10n->get('SYS_SSO_ROLE'), new Role($gDb), ["*" => $gL10n->get('SYS_SSO_ROLES_ALLOTHER')]);
                    break;

                case 'PICTURE':
                    // The value is the base64-encoded image data so decode it and display the image
                    $image = new Image();
                    $data = base64_decode($value);
                    try {
                        $image->setImageFromData($data);
                        $mime = $image->getMimeType(); // z\.B\. 'image/png'
                        $htmlValue = '<img id="picture" class="rounded" style="max-height: 24px; max-width: 24px;" src="data:' . $mime . ';base64,' . $value . '" alt="Picture">';
                    } catch (\Throwable $e) {
                        $htmlValue = '&nbsp;'; // If the image cannot be created, return a non-breaking space
                    }
                    break;
            }
            $value = $htmlValue;
        }
        // special case for type BOOL and no value is there, then show unchecked checkbox
        else {
            if ($type === 'BOOL') {
                $value = '<i class="bi bi-square"></i>';
            }
        }
        return $value;
    }


    public static function createMappingTable(string $value, string $admidioField, string $targetField, Entity $object, array $messages = []) {
        $mapping = json_decode($value, true);
        // If value is not a json array, don't transform it
        if (empty($mapping)) {
            return $value;
        }

        // Header
        $table = '<table border="1"><tr style="background: darkgray"><th>' . $admidioField . '</th><th>' . $targetField . "</th></tr>\n";
        // Loop through all mappings:
        foreach ($mapping as $ssoVal => $admVal) {
            if (array_key_exists($ssoVal, $messages)) {
                if (!empty($admVal)) {
                    $msg = $messages[$ssoVal];
                    $table .= '<tr><td colspan="2" style="border: solid 1pt gray; background: lightgray;">' . $msg . "</td></tr>\n";
                }
            } else {
                if (is_numeric($admVal)) {
                    $object->readDataById($admVal);
                    $admVal = $object->readableName();
                }
                // formatValue() deliberately skips its encoding for the mapping types, so the
                // values decoded from the JSON are still raw and have to be encoded here.
                $table .= '<tr><td>' . SecurityUtils::encodeHTML((string)$admVal) . '</td><td>'
                    . SecurityUtils::encodeHTML((string)$ssoVal) . "</td></tr>\n";
            }
        }
        $table .= '</table>';
        return $table;
    }

    /**
     * For a given database table and potentially a related object ID, return the type of table for the related object.
     * In many cases, the related object will have the same type (e.g. menu or folder hierarchy), but for other objects,
     * the related object has a different type (e.g. a file has the folder as related object, a membership record has the
     * corresponding role as related, ...)
     * @param string $table The table of the object
     * @param string $relatedName The id of the related object. Passed by reference, so this method can adjust the displayed name of the related object!
     * @return string The table for the related object
     * @throws Exception
     */
    public static function getRelatedTable(string $table, string &$relatedName = '') : string {
        // HANDLE REGISTERED CALLBACKS, THEN DEFAULT PROCESSING
        // First process callbacks defined for the given module:
        if (!empty($table) && array_key_exists($table, self::$customCallbacks['getRelatedTable'])) {
            $callback = self::$customCallbacks['getRelatedTable'][$table];
            $val = self::evaluateCallback($callback, $table, $relatedName);
            if (!empty($val)) return $val;
        }
        // second (if first step does not yield a match) process callbacks defined for ALL values:
        if (!empty($table) && array_key_exists('', self::$customCallbacks['getRelatedTable'])) {
            foreach (self::$customCallbacks['getRelatedTable'][''] as $callback) {
                $val = self::evaluateCallback($callback, $table, $relatedName);
                if (!empty($val)) return $val;
            }
        }
        // If none of the callbacks matches, proceed with the default processing...

        switch ($table) {
            case 'members':
                return 'roles';
            case 'roles_rights_data':
                return 'roles';
            case 'roles_dependencies':
                return 'roles';
            case 'files':
                return 'folders';
            case 'forum_posts':
                return 'forum_topics';
            case 'forum_topics':
                return 'forum_posts';
            case 'list_columns':
                // The related item is either a user field or a column name mem_ or usr_ -> in the latter case, convert it to a translatable string and translate
                if (!empty($relatedName) && (str_starts_with($relatedName, 'mem_') || str_starts_with($relatedName, 'usr_'))) {
                    $fieldStrings = ChangelogService::getFieldTranslations();
                    $relatedName = $fieldStrings[$relatedName]??$relatedName;
                    if (is_array($relatedName)) {
                        $relatedName = $relatedName['name']??'-';
                    }
                    if (!empty($relatedName)) {
                        $relatedName = Language::translateIfTranslationStrId($relatedName);
                    }
                }
                return 'user_fields';
            default:
        }
        return $table;
    }


    /**
     * Return a list of all db tables where the current user has admin / edit rights
     * @param User $user The user
     * @return string[] List of all accessible tables
     * @throws Exception
     */
    public static function getPermittedTables(User $user) : array {
        $tablesPermitted = [];
        if ($user->isAdministratorAnnouncements())
            $tablesPermitted[] = 'announcements';
        if ($user->isAdministratorRoles())
            $tablesPermitted = array_merge($tablesPermitted, ['roles', 'roles_rights', 'roles_rights_data', 'members']);
        if ($user->isAdministratorEvents())
            $tablesPermitted[] = 'events';
        if ($user->isAdministratorDocumentsFiles())
            // The role rights of a folder are stored in roles_rights_data, and the history buttons
            // of the documents module request that table in addition to files and folders.
            $tablesPermitted = array_merge($tablesPermitted, ['files', 'folders', 'roles_rights_data']);
        if ($user->isAdministratorUsers())
            $tablesPermitted = array_merge($tablesPermitted, ['users', 'user_data', 'user_relations', 'members']);
        if ($user->isAdministratorPhotos())
            $tablesPermitted[] = 'photos';
        if ($user->isAdministratorWeblinks())
            $tablesPermitted[] = 'links';
        if ($user->isAdministratorInventory())
            $tablesPermitted = array_merge($tablesPermitted, ['inventory_fields', 'inventory_field_select_options',
                'inventory_items', 'inventory_item_data', 'inventory_item_borrow_data']);
        if ($user->isAdministratorForum())
            $tablesPermitted = array_merge($tablesPermitted, ['forum_topics', 'forum_posts']);

        // HANDLE REGISTERED CALLBACKS to add additional tables
        // First process callbacks defined for the given module:
        $callbacks = self::$customCallbacks['getPermittedTables'];
        if (array_key_exists('', $callbacks)) {
            $callbacks = array_merge($callbacks, $callbacks['']);
            unset($callbacks['']);
        }
        foreach ($callbacks as $callback) {
            $val = self::evaluateCallback($callback, $user);
            if (is_array($val)) {
                $tablesPermitted = array_merge($tablesPermitted, $val);
            } elseif (!empty($val)) {
                $tablesPermitted[] = $val;
            }
        }

        return array_values(array_unique($tablesPermitted));
    }

    /**
     * Database tables that hold data shared between all organizations. They are not protected by
     * per-table permissions, but by the right to edit the profile of the affected user.
     * @var array
     */
    public static array $userTables = array('users', 'user_data', 'user_relations', 'members');

    /**
     * Check whether nothing but tables of $userTables were requested. Those tables are not
     * protected by per-table permissions, but by the right to edit the profile of the affected
     * user, so the caller has to read that user and pass it on to getReadableTables() and
     * getUserTableRestriction().
     * @param array $tables The requested database tables of the changelog
     * @return bool Returns true if at least one table was requested and all of them are user tables
     */
    public static function isUserHistory(array $tables) : bool {
        return count($tables) > 0 && count(array_diff($tables, self::$userTables)) === 0;
    }

    /**
     * Check whether the user may read the changelog of all tables without any restriction.
     * @param User $user The user whose permissions should be checked
     * @return bool Returns true if no table restriction has to be applied for that user
     * @throws Exception
     */
    public static function hasUnrestrictedAccess(User $user) : bool {
        global $gSettingsManager;

        return $gSettingsManager->getInt('changelog_module_enabled') > 0 && $user->isAdministrator();
    }

    /**
     * Determine which of the requested database tables the given user is allowed to read in the
     * changelog. This is the single place where changelog view permissions are evaluated.
     *
     * The result is deliberately not filtered by isTableLogged(): entries that were recorded while
     * logging was still enabled must stay readable after logging has been switched off again.
     *
     * @param User $user The user whose permissions should be checked
     * @param array $requestedTables The tables requested by the caller. An empty array means "all known tables".
     * @param User|null $subject If the changelog of one particular user is requested, that user.
     * @return string[] The subset of tables the user may read. An empty array means "no access at all".
     * @throws Exception
     */
    public static function getReadableTables(User $user, array $requestedTables = array(), ?User $subject = null) : array {
        global $gSettingsManager;

        // Changelog disabled globally, or only enabled for administrators
        if ($gSettingsManager->getInt('changelog_module_enabled') === 0) {
            return array();
        }
        if ($gSettingsManager->getInt('changelog_module_enabled') === 2 && !$user->isAdministrator()) {
            return array();
        }

        $candidates = $requestedTables;
        if (count($candidates) === 0) {
            $candidates = array_keys(self::getTableLabel());
        }

        if ($user->isAdministrator()) {
            return array_values($candidates);
        }

        $tablesPermitted = self::getPermittedTables($user);

        // The user tables are shared between all organizations and use per-user permissions:
        // either the user may administer all contacts (already covered by getPermittedTables), or
        // a concrete subject was requested whose profile the user is allowed to edit, or - if no
        // subject was requested at all - the user may at least see the changes to his/her own
        // data. In the latter two cases the caller MUST restrict the log entries of those tables
        // to the corresponding user, see getUserTableRestriction().
        if ($subject !== null && !$subject->isNewRecord()) {
            if ($user->hasRightEditProfile($subject)) {
                $tablesPermitted = array_merge($tablesPermitted, self::$userTables);
            }
        } elseif ($user->hasRightEditProfile($user)) {
            $tablesPermitted = array_merge($tablesPermitted, self::$userTables);
        }

        return array_values(array_intersect($candidates, $tablesPermitted));
    }

    /**
     * Return the user the log entries of the shared user tables have to be restricted to.
     *
     * getReadableTables() grants access to the user tables on a per-user basis, so whenever the
     * current user may not administer all contacts, the entries of those tables must additionally
     * be restricted to the one user he/she is allowed to see. Doing this as an SQL condition (and
     * not by dropping rows afterwards) keeps the record counts and the paging of the changelog
     * table correct.
     *
     * @param User $user The user whose permissions should be checked
     * @param User|null $subject If the changelog of one particular user is requested, that user.
     * @return string The uuid the entries of the user tables must be restricted to, or an empty
     *                string if the user may read those tables without any restriction.
     * @throws Exception
     */
    public static function getUserTableRestriction(User $user, ?User $subject = null) : string {
        // administrators and user administrators may read the log of all users
        if ($user->isAdministrator() || $user->isAdministratorUsers()) {
            return '';
        }

        if ($subject !== null && !$subject->isNewRecord() && $user->hasRightEditProfile($subject)) {
            return (string)$subject->getValue('usr_uuid');
        }

        // everyone else may only see the changes to his/her own data
        return (string)$user->getValue('usr_uuid');
    }

    /**
     * Return the date from which the change history should be shown by default.
     *
     * The period is configured with the preference changelog_default_days. A value of 0 means that
     * the change history is not limited by date at all. The user can always change the period with
     * the filter of the changelog page.
     *
     * @return DateTime The start date of the default filter period
     * @throws Exception
     */
    public static function getDefaultFilterDateFrom() : DateTime {
        global $gSettingsManager;

        $defaultDays = $gSettingsManager->getInt('changelog_default_days');
        if ($defaultDays <= 0) {
            // no limit -> start well before the first possible log entry
            return new DateTime('1970-01-01');
        }

        $dateFrom = new DateTime(DATE_NOW);
        $dateFrom->modify('-' . $defaultDays . ' day');
        return $dateFrom;
    }

    /**
     * Delete the entries of the change history that are older than the retention period of the
     * preference changelog_retention_days. The entries are only removed from the old end of the
     * history, so that the history of a single record or action can never be removed selectively.
     *
     * @param int|null $retentionDays Number of days the entries should be kept. A value of 0 (the
     *                                default of the preference) keeps them forever and deletes
     *                                nothing. If null, the preference of the organization is used.
     * @param int|null $organizationId The organization whose entries should be deleted. If null,
     *                                 the current organization is used.
     * @return int Returns the number of deleted entries
     * @throws Exception
     */
    public static function purgeOldEntries(?int $retentionDays = null, ?int $organizationId = null): int
    {
        global $gDb, $gSettingsManager, $gCurrentOrgId;

        if ($retentionDays === null) {
            $retentionDays = $gSettingsManager->getInt('changelog_retention_days');
        }
        // 0 means that the change history is kept without any limit
        if ($retentionDays <= 0) {
            return 0;
        }

        if ($organizationId === null) {
            $organizationId = (int)$gCurrentOrgId;
        }

        $deleteBefore = new DateTime();
        $deleteBefore->modify('-' . $retentionDays . ' day');

        // Entries of the tables that are shared between all organizations and entries that were
        // written before log_org_id existed have no organization. They may only be deleted if this
        // installation has one organization, because otherwise the purge of one organization would
        // delete entries that another organization still has to keep.
        $countStatement = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_ORGANIZATIONS);
        $entriesWithoutOrganization = ($countStatement !== false && (int)$countStatement->fetchColumn() === 1);

        $sql = 'DELETE FROM ' . TBL_LOG_CHANGES . '
                 WHERE log_timestamp_create < ? -- $deleteBefore
                   AND (log_org_id = ? ' . ($entriesWithoutOrganization ? 'OR log_org_id IS NULL' : '') . ')';
        $statement = $gDb->queryPrepared($sql, array($deleteBefore->format('Y-m-d H:i:s'), $organizationId));

        // Remember when the entries of this organization were purged, so that the opportunistic
        // purge of purgeOldEntriesIfDue() and a cron job do not repeat the work of each other.
        if ($organizationId === (int) $gCurrentOrgId) {
            $gSettingsManager->set('changelog_last_purge', (string) time());
        }

        return ($statement === false) ? 0 : $statement->rowCount();
    }

    /**
     * Purge the outdated entries of the change history, but at most once every PURGE_INTERVAL_SECONDS.
     * This is the opportunistic purge that keeps the change history from growing without limit in
     * installations that have no cron job. It is deliberately cheap: if no retention period is
     * configured or the last purge is not old enough, it does nothing but read two preferences.
     *
     * The timestamp of the last purge is stored in the preference changelog_last_purge and is
     * written before the entries are deleted, so that requests that arrive while a purge is still
     * running do not start a second one. A purge that is triggered by a cron job or by the button
     * in the preferences updates the same timestamp, so this method then stays idle.
     *
     * @return int Returns the number of deleted entries
     * @throws Exception
     */
    public static function purgeOldEntriesIfDue(): int
    {
        global $gSettingsManager;

        // This is called on every request, so it also has to work in an installation whose
        // database is still on an older version and does not have these preferences yet.
        if (!$gSettingsManager->has('changelog_retention_days')
            || $gSettingsManager->getInt('changelog_retention_days') <= 0) {
            return 0;
        }

        $now = time();
        if ($gSettingsManager->has('changelog_last_purge')
            && $now - $gSettingsManager->getInt('changelog_last_purge') < self::PURGE_INTERVAL_SECONDS) {
            return 0;
        }

        $gSettingsManager->set('changelog_last_purge', (string) $now);

        return self::purgeOldEntries();
    }

    /**
     * Check whether changes to a given table or a list of given database tables are logged at all.
     * This is independent of particular viewing permissions of the current user.
     * If multiple tables are given (as a comma-separated string), at least one of them needs to be logged.
     * @param string|array $table The database table(s) of the changelog (comma-separated list for multiple())
     * @return bool Returns true if the database table (or at least one, of multiple are given) is logged
     * @throws Exception
     */
    public static function isTableLogged(string|array $table) : bool {
        global $gSettingsManager;

        // Whether a table is logged is a preference of the organization, so nothing can be logged
        // while the system is still starting up and no organization is known yet. The update reads
        // the session, and with it the auto login, before it determines the organization, so this
        // is reached with no settings at all.
        if (!isset($gSettingsManager)) {
            return false;
        }

        if ($gSettingsManager->getInt('changelog_module_enabled') === 0) { // Changelog not enabled
            return false;
        }

        $tables = is_array($table) ? $table : explode(',', $table);

        foreach ($tables as $tableName) {
            $tableName = trim($tableName);
            if ($tableName === '' || in_array($tableName, self::$noLogTables, true)) {
                continue;
            }

            $settingName = 'changelog_table_' . $tableName;
            $tableIsKnown = ChangelogService::getTableLabel($tableName) !== '';

            // Once we find ANY requested table that is logged, we can return true!
            if ($tableIsKnown && $gSettingsManager->has($settingName)) {
                if ($gSettingsManager->getBool($settingName)) {
                    return true;
                }
            } elseif ($gSettingsManager->getBool('changelog_table_others')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display a "Change History" button in the current module's PagePresenter if changelog functionality
     * is enabled at all, the table has logging enabled and the current user is allowed to view
     * those objects. If these conditions are not satisfied, no button is displayed.
     *
     * @param PagePresenter $page The PagePresenter of the module, where the change history button should be added
     * @param string $area Identifier for the module, used for the menu item ID
     * @param string|array $table The database table(s) of the changelog (comma-separated list for multiple())
     * @param bool $condition Additional condition to display/hide
     * @param array $params
     * @return void
     * @throws Exception
     */
    public static function displayHistoryButton(PagePresenter $page, string $area, string|array $table, bool $condition = true, array $params = array()) : void {
        global $gL10n;

        $url = self::getHistoryUrl($table, $condition, $params);
        if ($url === '') {
            return;
        }

        $page->addPageFunctionsMenuItem(
            "menu_item_{$area}_change_history",
            $gL10n->get('SYS_CHANGE_HISTORY'),
            $url,
            'bi-clock-history'
        );
    }

    /**
     * Build the URL of the changelog page for the given table(s), if the changelog is enabled for
     * them and the current user is allowed to view those objects. This is the shared implementation
     * behind displayHistoryButton() and displayHistoryButtonTable().
     *
     * @param string|array $table The database table(s) of the changelog (comma-separated list for multiple)
     * @param bool $condition Additional condition to display/hide
     * @param array $params Additional URL parameters (uuid, id, related_id, ...)
     * @return string The URL of the changelog page, or an empty string if it must not be offered
     * @throws Exception
     */
    private static function getHistoryUrl(string|array $table, bool $condition = true, array $params = array()) : string {
        global $gCurrentUser, $gProfileFields, $gDb;

        // Required table(s) are not logged at all, or the condition for the history button is not met
        if (!$condition || !self::isTableLogged($table)) {
            return '';
        }

        if (!is_array($table)) {
            $table = array_map('trim', explode(',', $table));
        }

        // If the log of one particular user is requested, the permission can also be granted
        // through the right to edit that user's profile.
        $subject = null;
        if (!empty($params['uuid']) && !empty($table) && empty(array_diff($table, self::$userTables))) {
            $subject = new User($gDb, $gProfileFields);
            $subject->readDataByUuid($params['uuid']);
        }

        // the user needs read access to every requested table
        if (count(array_diff($table, self::getReadableTables($gCurrentUser, $table, $subject))) > 0) {
            return '';
        }

        return SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/changelog/changelog.php',
            array_merge(array('table' => implode(',', $table)), $params)
        );
    }

    /**
     * Returns a string containing an icon-link "Change History" button to implement in a DataTable if changelog functionality
     * is enabled at all, the table has logging enabled and the current user is allowed to view
     * those objects. If these conditions are not satisfied, this function returns an empty string.
     *
     * @param string|array $table The database table(s) of the changelog (comma-separated list for multiple())
     * @param bool $condition Additional condition to display/hide
     * @param array $params
     * @return array
     * @throws Exception
     */
    public static function displayHistoryButtonTable(string|array $table, bool $condition = true, array $params = array()) : array {
        global $gL10n;

        $url = self::getHistoryUrl($table, $condition, $params);
        if ($url === '') {
            return array();
        }

        // If the user has access to the changelog, create the link to the changelog page
        return array(
            'url' => $url,
            'icon' => 'bi bi-clock-history',
            'tooltip' => $gL10n->get('SYS_CHANGE_HISTORY'),
        );
    }
}


/*******************************************************
 * EXAMPLE CODE for the callback mechanism:
 *    The forum changelog can also be implemented by the following code, which can be called by a third party extension somewhere in its
 *    initialization code (must be executed at least before a changelog page is displayed and before the third-party extension writes
 *    data to the database!)
 *******************************************************

## Translation of database tables
ChangelogService::registerCallback('getTableLabelArray', 'forum_topics', 'SYS_FORUM_TOPIC');
ChangelogService::registerCallback('getTableLabelArray', 'forum_posts', 'SYS_FORUM_POST');

## Translations and type definitions of database columns
ChangelogService::registerCallback('getFieldTranslations', '', [
    'fot_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
    'fot_fop_id_first_post' =>     array('name' => 'SYS_FORUM_POST', 'type' => 'POST'),
    'fot_title' =>                 'SYS_TITLE',
    'fop_text' =>                  'SYS_TEXT',
    'fop_fot_id' =>                array('name' => 'SYS_FORUM_TOPIC', 'type' => 'TOPIC')
]);

## Formatting of new database column types (in many cases not needed)
ChangelogService::registerCallback('formatValue', 'TOPIC', function($value, $type, $entries = []) {
    global $gDb;
    if (empty($value)) return '';
    $obj = new Topic($gDb, $value??0);
    return ChangelogService::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'forum_topics',
            $obj->getValue('fot_id'), $obj->getValue('fot_uuid'));
});
ChangelogService::registerCallback('formatValue', 'POST', function($value, $type, $entries = []) {
    global $gDb;
    if (empty($value)) return '';
    $obj = new POST($gDb, $value??0);
    return ChangelogService::createLink(Language::translateIfTranslationStrId($obj->readableName()), 'forum_posts',
            $obj->getValue('fop_id'), $obj->getValue('fop_uuid'));
});

## Create HTML links to the object's list view and edit pages
ChangelogService::registerCallback('createLink', 'forum_topics', function(string $text, string $module, int|string $id, string $uuid = '') {
    return SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/forum.php',
                array('mode' => 'topic', 'topic_uuid' => $uuid));
});
ChangelogService::registerCallback('createLink', 'forum_posts', function(string $text, string $module, int|string $id, string $uuid = '') {
    return SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/forum.php',
                array('mode' => 'post_edit', 'post_uuid' => $uuid));
});

## Object types of related objects (if object relations are used at all!)
ChangelogService::registerCallback('getRelatedTable', 'forum_topics', 'forum_posts');
ChangelogService::registerCallback('getRelatedTable', 'forum_posts', 'forum_topics');


## Create Entity-derived objects to create headlines with proper object names
ChangelogService::registerCallback('getObjectForTable', 'forum_topics', function() {global $gDb; return new Topic($gDb);});
ChangelogService::registerCallback('getObjectForTable', 'forum_posts', function() {global $gDb; return new Post($gDb);});

## Enable per-user detection of access permissions to the tables (based on user's role permission); Admin is always allowed
ChangelogService::registerCallback('getPermittedTables', '', function(User $user) { if ($user->isAdministratorForum()) return ['forum_topics', 'forum_posts']; });

*/
