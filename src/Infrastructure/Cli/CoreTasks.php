<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Categories\Entity\Category;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Components\Entity\Component;
use Admidio\Documents\Entity\File as DocumentFile;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Events\Service\EventService;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use Admidio\Events\ValueObject\Participants;
use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\DatabaseDump;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Htaccess;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Infrastructure\Service\RegistrationService;
use Admidio\Infrastructure\Utils\Maintenance;
use Admidio\Infrastructure\Utils\MaintenanceMode;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\ItemBorrowData;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions as InventorySelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Inventory\Service\ExportService;
use Admidio\Inventory\Service\ImportService;
use Admidio\Inventory\Service\ItemFieldService;
use Admidio\Inventory\Service\ItemService;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Messages\Entity\Message;
use Admidio\Messages\Service\MessageService;
use Admidio\Organizations\Entity\Organization;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\AlbumService;
use Admidio\Photos\Service\ECardService;
use Admidio\Photos\Service\PhotoService;
use Admidio\Photos\ValueObject\ECard;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\Entity\SelectOptions as ProfileSelectOptions;
use Admidio\ProfileFields\Service\ProfileFieldService;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Roles\ValueObject\ListData;
use Admidio\Session\Entity\AutoLogin;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\KeyService;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\SAMLService;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Users\Service\UserPhotoService;
use Admidio\Weblinks\Entity\Weblink;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use RobThree\Auth\TwoFactorAuth;

/**
 ***********************************************************************************************
 * Registration and callbacks for the commands supplied by Admidio core.
 *
 * There is intentionally no command switch here. Every command, including built-in commands,
 * is registered in CliTaskRegistry and dispatched in exactly the same way as a module task.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CoreTasks
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        self::registerGeneralTasks();
        self::registerSystemTasks();
        self::registerConfigTasks();
        self::registerOrganizationTasks();
        self::registerUserTasks();
        self::registerRelationTasks();
        self::registerRegistrationTasks();
        self::registerGroupTasks();
        self::registerListTasks();
        self::registerPermissionTasks();
        self::registerCategoryTasks();
        self::registerMenuTasks();
        self::registerAnnouncementTasks();
        self::registerForumTasks();
        self::registerLinkTasks();
        self::registerMessageTasks();
        self::registerDocumentTasks();
        self::registerPhotoTasks();
        self::registerEventTasks();
        self::registerRoomTasks();
        self::registerInventoryTasks();
        self::registerProfileFieldTasks();
        self::registerCategoryReportTasks();
        self::registerChangelogTasks();
        self::registerPluginTasks();
        self::registerSsoTasks();
        self::registerSessionTasks();
        self::registerModuleTasks();
    }

    /**
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    private static function task(
        string $name,
        string $method,
        string $description,
        string $usage = '',
        ?string $component = null,
        bool $actorRequired = false,
        array $arguments = array(),
        array $options = array(),
        array $examples = array(),
        ?string $unavailableReason = null,
        string $componentAccess = CliTaskRegistry::ACCESS_ADMINISTRABLE,
        ?string $aliasOf = null,
        ?string $requiredRight = null
    ): void {
        /*
         * The callback resolves the method only when the command is executed, so a typo would
         * survive registration, listing and help and surface as a fatal on first use. Verify it
         * while the registry is built.
         */
        if (!method_exists(self::class, $method)) {
            throw new InvalidArgumentException(
                'CLI command "' . $name . '" refers to the unknown callback CoreTasks::' . $method . '().'
            );
        }

        CliTaskRegistry::registerCore(
            $name,
            static fn (array $arguments, array $options): int => self::$method($arguments, $options),
            $description,
            $usage,
            $component,
            $actorRequired,
            $arguments,
            $options,
            $examples,
            $unavailableReason,
            $componentAccess,
            $aliasOf,
            $requiredRight
        );
    }

    /**
     * Register a second name for an already registered command.
     *
     * The alias repeats the arguments and options of the aliased command, because they are part of
     * its own validation and help output, but it is marked so "admidio list" and the help can show
     * where it points instead of presenting two independent commands.
     *
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     */
    private static function alias(
        string $name,
        string $aliasOf,
        string $method,
        string $usage = '',
        ?string $component = null,
        bool $actorRequired = false,
        array $arguments = array(),
        array $options = array(),
        string $componentAccess = CliTaskRegistry::ACCESS_ADMINISTRABLE
    ): void {
        self::task(
            $name,
            $method,
            'Alias for ' . $aliasOf . '.',
            $usage,
            $component,
            $actorRequired,
            $arguments,
            $options,
            array(),
            null,
            $componentAccess,
            $aliasOf
        );
    }

    /**
     * Register a command that only reads data.
     *
     * Such a command requires the component to be visible instead of administrable, because it
     * performs the same record-level check as the corresponding web module - Entity::isVisible(),
     * User::hasRightViewProfile(), Folder::hasViewRight() and so on. Never use this for a command
     * that writes, unless the called service implements the complete rights model itself.
     *
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    private static function readTask(
        string $name,
        string $method,
        string $description,
        string $usage = '',
        ?string $component = null,
        bool $actorRequired = false,
        array $arguments = array(),
        array $options = array(),
        array $examples = array(),
        ?string $unavailableReason = null
    ): void {
        self::task(
            $name,
            $method,
            $description,
            $usage,
            $component,
            $actorRequired,
            $arguments,
            $options,
            $examples,
            $unavailableReason,
            CliTaskRegistry::ACCESS_VISIBLE
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function arg(
        string $name,
        string $description,
        bool $required = true,
        bool $multiple = false
    ): array {
        return array(
            'name' => $name,
            'description' => $description,
            'required' => $required,
            'multiple' => $multiple
        );
    }

    /**
     * @param array<int,string> $values
     * @return array<string,mixed>
     */
    private static function opt(
        string $name,
        string $description,
        string $value = 'VALUE',
        bool $required = false,
        bool $multiple = false,
        bool $flag = false,
        array $values = array()
    ): array {
        if ($name === 'format') {
            $values = self::withRecordFormat($values);
        }

        $option = array(
            'name' => $name,
            'description' => $description,
            'required' => $required,
            'multiple' => $multiple,
            'flag' => $flag
        );
        if (!$flag) {
            $option['value'] = $value;
        }
        if (count($values) > 0) {
            $option['values'] = $values;
        }

        return $option;
    }

    /**
     * Add the "record" format to a --format option that can render it.
     *
     * CliApplication::writeRows() renders "record" - the field/value layout - for every result set
     * it can render as a table, and writeValue() renders it for a single data record. Those are
     * exactly the commands whose values contain "table", or the pair "text" and "json". Listing it
     * in each of the roughly one hundred registrations would be pure repetition, so it is derived
     * here; the rule lives in one place instead of being an unexplained side effect of opt().
     *
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private static function withRecordFormat(array $values): array
    {
        if (in_array('record', $values, true)) {
            return $values;
        }

        $rendersTable = in_array('table', $values, true);
        $rendersSingleRecord = count($values) === 2
            && in_array('text', $values, true)
            && in_array('json', $values, true);

        if (!$rendersTable && !$rendersSingleRecord) {
            return $values;
        }

        $position = array_search($rendersTable ? 'table' : 'text', $values, true);
        array_splice($values, $position + 1, 0, array('record'));

        return $values;
    }

    private static function registerGeneralTasks(): void
    {
        self::task(
            'help',
            'help',
            'Show command help. Markdown and native DokuWiki output can be generated from the registry.',
            'help [COMMAND] [--all] [--format=text|md|dokuwiki|json] [--output=FILE]',
            null,
            false,
            array(self::arg('command', 'Command whose help should be displayed.', false)),
            array(
                self::opt('all', 'Render documentation for all registered commands.', '', false, false, true),
                self::opt('format', 'Help format.', 'FORMAT', false, false, false, array('text', 'md', 'dokuwiki', 'json'))
            ),
            array(
                'admidio help group:adduser',
                'admidio help --all --format=md',
                'admidio help --all --format=dokuwiki'
            )
        );
        self::task(
            'list',
            'listCommands',
            'List registered CLI commands.',
            'list [NAMESPACE] [--format=table|json|csv|md|dokuwiki]',
            null,
            false,
            array(self::arg('namespace', 'Optional command namespace.', false)),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki')))
        );
        self::task(
            'version',
            'version',
            'Show filesystem and installed database core version.',
            'version [--format=text|json]',
            null,
            false,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
        self::task(
            'cli:selfcheck',
            'selfCheck',
            'Validate the command line itself: registration, help and the internal consistency of its source. '
                . 'Needs no acting user. Exits with 3 when a problem is found.',
            'cli:selfcheck [--format=table|json|csv|md|dokuwiki]',
            null,
            false,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))),
            array('admidio cli:selfcheck --format=json')
        );
        self::task(
            'completion',
            'completion',
            'Print a shell completion script for the Admidio command line.',
            'completion [bash|zsh]',
            null,
            false,
            array(self::arg('shell', 'Shell to generate the script for. Defaults to bash.', false)),
            array(),
            array(
                'admidio completion bash > /etc/bash_completion.d/admidio',
                'admidio completion zsh > "${fpath[1]}/_admidio"'
            )
        );
        self::task(
            'status',
            'status',
            'Show installation, organization and filesystem/database update status. Exits with 3 if the status is not ok.',
            'status [--format=text|json]',
            null,
            false,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
    }

    private static function registerSystemTasks(): void
    {
        /*
         * The database of a new installation is described by adm_my_files/config.php. If that file
         * already exists, its values are used and the --db-* options may only repeat them. If it
         * doesn't exist, the options describe the database and install:run writes the file.
         *
         * Every value that is not given as an option is asked for, in the order of the web
         * installation wizard, unless --no-interaction forbids questions.
         */
        $installOptions = array(
            self::opt('db-type', 'Database system. Required without an existing configuration file.', 'TYPE', false, false, false, array('mariadb', 'mysql', 'pgsql')),
            self::opt('db-host', 'Host name or IP address of the database server.', 'HOST'),
            self::opt('db-port', 'Port of the database server. Empty for the default port of the database system.', 'PORT'),
            self::opt('db-name', 'Name of the database.', 'NAME'),
            self::opt('db-user', 'User of the database.', 'USER'),
            self::opt('db-password', 'Password of the database user. Prefer --db-password-stdin.', 'PASSWORD'),
            self::opt('db-password-stdin', 'Read the password of the database user from STDIN.', '', false, false, true),
            self::opt('table-prefix', 'Prefix of the Admidio tables, "adm" by default.', 'PREFIX'),
            self::opt('root-url', 'URL of this Admidio installation. Required without an existing configuration file.', 'URL'),
            self::opt('language', 'Language of the new organization, "en" by default.', 'LANGUAGE'),
            self::opt('timezone', 'Time zone of the new organization.', 'TIMEZONE'),
            self::opt('organization-shortname', 'Short name of the organization.', 'NAME', true),
            self::opt('organization-name', 'Name of the organization.', 'NAME', true),
            self::opt('organization-email', 'Email address of the organization administrator.', 'EMAIL', true),
            self::opt('admin-login', 'Login name of the administrator.', 'LOGIN', true),
            self::opt('admin-first-name', 'First name of the administrator.', 'NAME', true),
            self::opt('admin-last-name', 'Last name of the administrator.', 'NAME', true),
            self::opt('admin-email', 'Email address of the administrator.', 'EMAIL', true),
            self::opt('admin-password', 'Password of the administrator. Prefer --admin-password-stdin.', 'PASSWORD'),
            self::opt('admin-password-stdin', 'Read the password of the administrator from STDIN. It is the second line if the database password is read from STDIN too.', '', false, false, true),
            self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
        );
        $installUsage = '--db-type=TYPE --db-host=HOST --db-name=NAME --db-user=USER --root-url=URL'
            . ' --organization-shortname=NAME --organization-name=NAME --organization-email=EMAIL'
            . ' --admin-login=LOGIN --admin-first-name=NAME --admin-last-name=NAME --admin-email=EMAIL [options]';
        $installExample = 'admidio %COMMAND% --db-type=mariadb --db-host=localhost --db-name=admidio --db-user=admidio'
            . ' --db-password-stdin --root-url=https://www.example.org/admidio --timezone=Europe/Berlin'
            . ' --organization-shortname=EXAMPLE --organization-name="Example Organization" --organization-email=info@example.org'
            . ' --admin-login=admin --admin-first-name=Anna --admin-last-name=Admin --admin-email=anna@example.org --admin-password-stdin';

        self::task(
            'install:check',
            'installCheck',
            'Check all values and prerequisites of a new installation without changing anything. Missing values are asked for.',
            'install:check ' . $installUsage,
            null,
            false,
            array(),
            $installOptions,
            array(str_replace('%COMMAND%', 'install:check', $installExample))
        );
        self::task(
            'install:run',
            'installRun',
            'Install a new Admidio database with its first organization and administrator. Missing values are asked for, --no-interaction requires all of them as options.',
            'install:run ' . $installUsage . ' [--yes]',
            null,
            false,
            array(),
            array_merge($installOptions, array(self::opt('yes', 'Confirm the installation.', '', false, false, true))),
            array(str_replace('%COMMAND%', 'install:run', $installExample) . ' --yes')
        );

        self::task(
            'update:check',
            'updateCheck',
            'Check the public Admidio release information used by the preferences update check. Exits with 4 if an update is available.',
            'update:check [--format=text|json]',
            'PREFERENCES',
            true,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
        self::task(
            'system:info',
            'systemInfo',
            'Show Admidio, PHP, database and operating-system information.',
            'system:info [--format=text|json]',
            'PREFERENCES',
            true,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
        self::task(
            'system:phpinfo',
            'phpInfo',
            'Print PHP configuration information in text form.',
            'system:phpinfo',
            'PREFERENCES',
            true
        );
        self::task(
            'database:backup',
            'databaseBackup',
            'Create a native Admidio database dump.',
            'database:backup [--output=FILE]',
            'PREFERENCES',
            true
        );
        self::task(
            'email:test',
            'emailTest',
            'Send the configured Admidio test email to the acting administrator.',
            'email:test',
            'PREFERENCES',
            true
        );
        self::task(
            'htaccess:status',
            'htaccessStatus',
            'Show whether adm_my_files is protected by an .htaccess file. Exits with 3 if it is unprotected.',
            'htaccess:status [--format=text|json]',
            'PREFERENCES',
            true,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
        self::task('htaccess:enable', 'htaccessEnable', 'Protect adm_my_files through the native Htaccess helper.',
            'htaccess:enable', 'PREFERENCES', true);
        self::task('htaccess:disable', 'htaccessDisable', 'Remove the Admidio .htaccess protection file.',
            'htaccess:disable [--yes]', 'PREFERENCES', true, array(),
            array(self::opt('yes', 'Confirm removal of protection.', '', false, false, true)));
        self::task('maintenance:repair-categories', 'repairCategories', 'Run the native category repair operation.',
            'maintenance:repair-categories [--yes]', 'CORE', true, array(),
            array(self::opt('yes', 'Confirm the repair.', '', false, false, true)));
        self::task('maintenance:repair-documents', 'repairDocuments', 'Run the native documents/files path repair operation.',
            'maintenance:repair-documents [--yes]', 'CORE', true, array(),
            array(self::opt('yes', 'Confirm the repair.', '', false, false, true)));
        self::task(
            'maintenance:mode',
            'maintenanceMode',
            'Enable, disable or query the application-wide maintenance mode.',
            'maintenance:mode [MODE] [--title=TITLE] [--message=MESSAGE] [--retry-after=SECONDS] '
                . '[--allow-script=SCRIPT]... [--owner=OWNER] [--force] [--format=record|json]',
            null,
            false,
            array(self::arg('mode', 'Operation to perform: enable, disable or status. Defaults to status.', false)),
            array(
                self::opt('title', 'Title shown on the maintenance page.', 'TITLE'),
                self::opt('message', 'Message shown on the maintenance page.', 'MESSAGE'),
                self::opt('retry-after', 'Retry-After interval in seconds.', 'SECONDS'),
                self::opt('allow-script', 'Relative script path that may bypass maintenance mode.', 'SCRIPT', false, true),
                self::opt('owner', 'Maintenance owner identifier. Defaults to "cli".', 'OWNER'),
                self::opt('force', 'Disable maintenance mode regardless of its owner.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('record', 'json'))
            ),
            array(
                'admidio maintenance:mode',
                'admidio maintenance:mode status',
                'admidio maintenance:mode enable --message="Maintenance in progress" --retry-after=300 --yes',
                'admidio maintenance:mode disable',
                'admidio maintenance:mode disable --force'
            )
        );
    }

    private static function registerConfigTasks(): void
    {
        self::task('config:list', 'configList', 'List organization preferences.',
            'config:list [--filter=PATTERN] [--format=table|json|csv|md|dokuwiki]',
            'PREFERENCES', true, array(), array(
                self::opt('filter', 'Only include preference names containing this text.', 'PATTERN'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('config:get', 'configGet', 'Read a preference.',
            'config:get NAME [--type=raw|string|int|float|bool] [--format=text|json]',
            'PREFERENCES', true,
            array(self::arg('name', 'Preference name.')),
            array(
                self::opt('type', 'Typed SettingsManager getter.', 'TYPE', false, false, false, array('raw', 'string', 'int', 'float', 'bool')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        self::task('config:set', 'configSet', 'Store a preference through SettingsManager.',
            'config:set NAME VALUE', 'PREFERENCES', true,
            array(self::arg('name', 'Preference name.'), self::arg('value', 'Preference value.')));
        self::task('config:delete', 'configDelete', 'Delete an organization preference.',
            'config:delete NAME [--yes]', 'PREFERENCES', true,
            array(self::arg('name', 'Preference name.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
    }

    private static function registerOrganizationTasks(): void
    {
        self::task('organization:list', 'organizationList', 'List organizations.',
            'organization:list [--format=table|json|csv|md|dokuwiki]', 'ORGANIZATIONS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::task('organization:show', 'organizationShow', 'Show an organization.',
            'organization:show ORG [--format=text|json]', 'ORGANIZATIONS', true,
            array(self::arg('org', 'Organization UUID, id or unique short name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $orgOptions = array(
            self::opt('short-name', 'Organization short name.', 'NAME'),
            self::opt('name', 'Organization long name.', 'NAME'),
            self::opt('email', 'Administrator email address.', 'EMAIL'),
            self::opt('homepage', 'Organization homepage.', 'URL'),
            self::opt('parent', 'Parent organization.', 'ORG'),
            self::opt('show-organization-select', 'Show organization selection at login.', 'BOOL'),
            self::opt('share-members', 'Set contacts_suborganization_use_same_members on the PARENT organization, so its suborganizations share its members.', 'BOOL')
        );
        self::task('organization:add', 'organizationAdd', 'Create a suborganization with native basic data and preferences.',
            'organization:add --short-name=NAME --name=NAME --email=EMAIL [options]', 'ORGANIZATIONS', true, array(),
            array_replace($orgOptions, array(
                0 => self::opt('short-name', 'Organization short name.', 'NAME', true),
                1 => self::opt('name', 'Organization long name.', 'NAME', true),
                2 => self::opt('email', 'Administrator email address.', 'EMAIL', true)
            )));
        self::task('organization:update', 'organizationUpdate', 'Update organization properties.',
            'organization:update ORG [options]', 'ORGANIZATIONS', true,
            array(self::arg('org', 'Organization UUID, id or unique short name.')), $orgOptions);
        self::task('organization:delete', 'organizationDelete', 'Delete an organization using Organization::delete().',
            'organization:delete ORG [--yes]', 'ORGANIZATIONS', true,
            array(self::arg('org', 'Organization UUID, id or unique short name.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
    }

    private static function registerUserTasks(): void
    {
        self::readTask('user:list', 'userList', 'List users in the current organization.',
            'user:list [--search=TEXT] [--group=GROUP] [--former] [--limit=N] [--offset=N] [--format=FORMAT]',
            'CONTACTS', true, array(), array(
                self::opt('search', 'Search login name and configured first/last name fields.', 'TEXT'),
                self::opt('group', 'Restrict to members of a group.', 'GROUP'),
                self::opt('former', 'Include former members.', '', false, false, true),
                self::opt('limit', 'Maximum number of records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('user:show', 'userShow', 'Show user profile data.',
            'user:show USER [--memberships] [--relations] [--format=text|json]', 'CONTACTS', true,
            array(self::arg('user', 'User UUID, id or unique login name.')), array(
                self::opt('memberships', 'Include role memberships.', '', false, false, true),
                self::opt('relations', 'Include user relations.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        $userWriteOptions = array(
            self::opt('login', 'Login name.', 'LOGIN'),
            self::opt('password', 'Password. Prefer --password-stdin.', 'PASSWORD'),
            self::opt('password-stdin', 'Read password from STDIN.', '', false, false, true),
            self::opt('field', 'Profile field assignment INTERNAL_NAME=value.', 'FIELD=VALUE', false, true),
            self::opt('group', 'Additional role/group assignment.', 'GROUP', false, true)
        );
        self::task('user:add', 'userAdd', 'Create a user using native profile fields and default-role assignment.',
            'user:add --field=FIELD=VALUE ... [--login=LOGIN] [--password-stdin] [--group=GROUP ...]',
            'CONTACTS', true, array(), $userWriteOptions);
        self::task('user:update', 'userUpdate', 'Update login/profile data of a user.',
            'user:update USER [--login=LOGIN] [--field=FIELD=VALUE ...]', 'CONTACTS', true,
            array(self::arg('user', 'User UUID, id or login name.')), array(
                self::opt('login', 'Login name.', 'LOGIN'),
                self::opt('field', 'Profile field assignment INTERNAL_NAME=value.', 'FIELD=VALUE', false, true)
            ));
        self::task('user:copy', 'userCopy', 'Create a new user initialized from another profile.',
            'user:copy USER [--login=LOGIN] [--field=FIELD=VALUE ...] [--group=GROUP ...]',
            'CONTACTS', true, array(self::arg('user', 'Source user.')), array(
                self::opt('login', 'Login name of the new user.', 'LOGIN'),
                self::opt('field', 'Override profile field INTERNAL_NAME=value.', 'FIELD=VALUE', false, true),
                self::opt('group', 'Group assignment for the copy.', 'GROUP', false, true)
            ));
        self::task('user:remove', 'userRemove', 'End current-organization memberships so the user becomes a former member.',
            'user:remove USER ... [--yes]', 'CONTACTS', true,
            array(self::arg('user', 'One or more users.', true, true)),
            array(self::opt('yes', 'Confirm removal.', '', false, false, true)));
        self::task('user:delete', 'userDelete', 'Permanently delete users where the native organization checks allow it.',
            'user:delete USER ... [--any-organization] [--yes]', null, true,
            array(self::arg('user', 'One or more users.', true, true)),
            array(
                self::opt('any-organization', 'Also resolve users that are not members of the current organization.', '', false, false, true),
                self::opt('yes', 'Confirm permanent deletion.', '', false, false, true)
            ), requiredRight: 'administrator');
        self::readTask('user:export', 'userExport', 'Export a user as the native vCard representation.',
            'user:export USER [--output=FILE]', 'CONTACTS', true,
            array(self::arg('user', 'User to export.')));
        $importReason = 'current src/Users/Entity/UserImport.php and modules/contacts/import.php implement a stateful web import workflow; '
            . 'there is no complete data-oriented import service to call headlessly without duplicating that workflow.';
        self::task('user:import', 'unavailable', 'Import contacts from a supported spreadsheet/text file.',
            'user:import FILE [options]', 'CONTACTS', true,
            array(self::arg('file', 'Import file.')), array(), array(), $importReason);
        self::task('user:import-check', 'unavailable', 'Validate and preview a contacts import.',
            'user:import-check FILE [options]', 'CONTACTS', true,
            array(self::arg('file', 'Import file.')), array(), array(), $importReason);
        self::task('user:set-password', 'userSetPassword', 'Set a user password.',
            'user:set-password USER [--password=PASSWORD|--password-stdin]', 'CONTACTS', true,
            array(self::arg('user', 'User.')), array(
                self::opt('password', 'New password. Prefer --password-stdin.', 'PASSWORD'),
                self::opt('password-stdin', 'Read password from STDIN.', '', false, false, true)
            ));
        self::task('user:send-password', 'userSendPassword', 'Send the native password-reset/new-password email.',
            'user:send-password USER', 'CONTACTS', true, array(self::arg('user', 'User.')));
        self::alias('user:send-login', 'user:send-password', 'userSendPassword',
            'user:send-login USER', 'CONTACTS', true, array(self::arg('user', 'User.')));
        self::task('user:tfa-status', 'userTfaStatus', 'Show whether two-factor authentication is configured.',
            'user:tfa-status USER [--format=text|json]', 'CONTACTS', true,
            array(self::arg('user', 'User.')), array(
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        self::task(
            'user:tfa-setup',
            'userTfaSetup',
            'Set up two-factor authentication for the acting user.',
            'user:tfa-setup USER [--secret=SECRET|--secret-stdin] [--code=CODE]',
            null,
            true,
            array(self::arg('user', 'User.')),
            array(
                self::opt('secret', 'TOTP secret. Prefer --secret-stdin. If omitted, generate and print a new secret.', 'SECRET'),
                self::opt('secret-stdin', 'Read the TOTP secret from STDIN.', '', false, false, true),
                self::opt('code', 'One-time security code. If omitted, prompt interactively.', 'CODE')
            )
        );
        self::task('user:tfa-reset', 'userTfaReset', 'Remove the configured two-factor secret.',
            'user:tfa-reset USER [--yes]', 'CONTACTS', true,
            array(self::arg('user', 'User.')), array(self::opt('yes', 'Confirm reset.', '', false, false, true)));
        self::task(
            'user:photo-set',
            'userPhotoSet',
            'Set the user profile photo.',
            'user:photo-set USER FILE',
            'CONTACTS',
            true,
            array(self::arg('user', 'User.'), self::arg('file', 'JPEG/PNG image file.'))
        );
        self::task(
            'user:photo-delete',
            'userPhotoDelete',
            'Delete the user profile photo.',
            'user:photo-delete USER [--yes]',
            'CONTACTS',
            true,
            array(self::arg('user', 'User.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true))
        );
    }

    private static function registerRelationTasks(): void
    {
        self::readTask('relation-type:list', 'relationTypeList', 'List user relation types.',
            'relation-type:list [--format=FORMAT]', 'CONTACTS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::readTask('relation-type:show', 'relationTypeShow', 'Show a user relation type.',
            'relation-type:show TYPE [--format=text|json]', 'CONTACTS', true,
            array(self::arg('type', 'Relation type UUID or id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $baseTypeOptions = array(
            self::opt('name', 'Relation name.', 'NAME'),
            self::opt('name-male', 'Male-specific name.', 'NAME'),
            self::opt('name-female', 'Female-specific name.', 'NAME'),
            self::opt('editable-by-user', 'Whether users may edit this relation.', 'BOOL'),
            self::opt('inverse-name', 'Inverse relation name (asymmetrical types only).', 'NAME'),
            self::opt('inverse-name-male', 'Male-specific inverse relation name.', 'NAME'),
            self::opt('inverse-name-female', 'Female-specific inverse relation name.', 'NAME'),
            self::opt('inverse-editable-by-user', 'Whether users may edit the inverse relation.', 'BOOL')
        );
        self::task('relation-type:add', 'relationTypeAdd', 'Create a user relation type.',
            'relation-type:add --name=NAME --type=TYPE [options]', 'CONTACTS', true, array(),
            array_merge(
                array(
                    self::opt('name', 'Relation name.', 'NAME', true),
                    self::opt('type', 'Relation shape.', 'TYPE', true, false, false, array('symmetrical', 'asymmetrical', 'unidirectional'))
                ),
                array_slice($baseTypeOptions, 1)
            ), requiredRight: 'administrator');
        self::task('relation-type:update', 'relationTypeUpdate', 'Update a user relation type. Its existing relation shape is retained, matching current master.',
            'relation-type:update TYPE [options]', 'CONTACTS', true,
            array(self::arg('type', 'Relation type UUID/id.')), $baseTypeOptions, requiredRight: 'administrator');
        self::task('relation-type:delete', 'relationTypeDelete', 'Delete a user relation type.',
            'relation-type:delete TYPE [--yes]', 'CONTACTS', true,
            array(self::arg('type', 'Relation type UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)), requiredRight: 'administrator');
        self::readTask('relation:list', 'relationList', 'List user relations.',
            'relation:list [USER] [--type=TYPE] [--format=FORMAT]', 'CONTACTS', true,
            array(self::arg('user', 'Optional user.', false)), array(
                self::opt('type', 'Relation type.', 'TYPE'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('relation:add', 'relationAdd', 'Create a user relation.',
            'relation:add USER1 USER2 TYPE', 'CONTACTS', true,
            array(self::arg('user1', 'First user.'), self::arg('user2', 'Second user.'), self::arg('type', 'Relation type UUID/id.')));
        self::task('relation:delete', 'relationDelete', 'Delete a user relation including its native inverse record.',
            'relation:delete RELATION [--yes]', 'CONTACTS', true,
            array(self::arg('relation', 'Relation UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
    }

    private static function registerRegistrationTasks(): void
    {
        self::task('registration:list', 'registrationList', 'List pending registrations.',
            'registration:list [--format=FORMAT]', 'REGISTRATION', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::task('registration:show', 'registrationShow', 'Show a pending registration.',
            'registration:show USER [--format=text|json]', 'REGISTRATION', true,
            array(self::arg('user', 'Registration user UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        self::task('registration:similar', 'registrationSimilar', 'Search native similar-user matches for a registration.',
            'registration:similar USER [--format=FORMAT]', 'REGISTRATION', true,
            array(self::arg('user', 'Registration user.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md'))));
        self::task('registration:confirm', 'registrationConfirm', 'Confirm a registration validation id.',
            'registration:confirm USER VALIDATION_ID', null, false,
            array(self::arg('user', 'Registration user UUID.'), self::arg('validation-id', 'Registration validation id.')));
        self::task('registration:approve', 'registrationApprove', 'Approve a registration using UserRegistration::acceptRegistration().',
            'registration:approve USER [--group=GROUP ...]', 'REGISTRATION', true,
            array(self::arg('user', 'Registration user.')),
            array(self::opt('group', 'Additional group after approval.', 'GROUP', false, true)));
        self::alias('registration:create-user', 'registration:approve', 'registrationApprove',
            'registration:create-user USER [--group=GROUP ...]', 'REGISTRATION', true,
            array(self::arg('user', 'Registration user.')),
            array(self::opt('group', 'Additional group after approval.', 'GROUP', false, true)));
        self::task(
            'registration:assign',
            'registrationAssign',
            'Assign a pending registration to an existing user using RegistrationService.',
            'registration:assign REGISTRATION_USER EXISTING_USER [--existing-member=BOOL]',
            'REGISTRATION',
            true,
            array(self::arg('registration-user', 'Pending registration user.'), self::arg('existing-user', 'Existing user.')),
            array(self::opt('existing-member', 'Existing user is already a member of the current organization.', 'BOOL'))
        );
        self::task('registration:delete', 'registrationDelete', 'Delete a pending registration.',
            'registration:delete USER [--yes]', 'REGISTRATION', true,
            array(self::arg('user', 'Registration user.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('registration:send-login', 'registrationSendLogin', 'Send login data to the matched existing user.',
            'registration:send-login REGISTRATION_USER EXISTING_USER', 'REGISTRATION', true,
            array(self::arg('registration-user', 'Pending registration user.'), self::arg('existing-user', 'Existing user.')));
    }

    private static function registerGroupTasks(): void
    {
        self::readTask('group:list', 'groupList', 'List groups/roles.',
            'group:list [--category=CATEGORY] [--active=BOOL] [--format=FORMAT]', 'GROUPS-ROLES', true,
            array(), array(
                self::opt('category', 'Role category.', 'CATEGORY'),
                self::opt('active', 'Filter rol_valid.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('group:show', 'groupShow', 'Show a group/role.',
            'group:show GROUP [--members] [--permissions] [--format=text|json]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group UUID/id/name.')), array(
                self::opt('members', 'Include current members.', '', false, false, true),
                self::opt('permissions', 'Include role permission columns.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        $roleOptions = array(
            self::opt('category', 'Role category.', 'CATEGORY'),
            self::opt('name', 'Role name.', 'NAME'),
            self::opt('description', 'Role description.', 'TEXT'),
            self::opt('mail-to-role', 'Who may mail this role (0-3).', 'MODE'),
            self::opt('view-memberships', 'Who may view memberships (0-3).', 'MODE'),
            self::opt('view-profiles', 'Who may view member profiles (0-3).', 'MODE'),
            self::opt('leader-rights', 'Leader rights (0-3).', 'MODE'),
            self::opt('default-list', 'Default list configuration.', 'LIST'),
            self::opt('default-registration', 'Assign role by default to registrations.', 'BOOL'),
            self::opt('max-members', 'Maximum member count.', 'N'),
            self::opt('cost', 'Role cost.', 'NUMBER'),
            self::opt('cost-period', 'Cost period.', 'PERIOD'),
            self::opt('start', 'Role start date YYYY-MM-DD.', 'DATE'),
            self::opt('end', 'Role end date YYYY-MM-DD.', 'DATE'),
            self::opt('start-time', 'Role start time HH:MM.', 'TIME'),
            self::opt('end-time', 'Role end time HH:MM.', 'TIME'),
            self::opt('weekday', 'Weekday number.', 'DAY'),
            self::opt('location', 'Meeting location.', 'TEXT')
        );
        self::task('group:add', 'groupAdd', 'Create a role/group.',
            'group:add NAME --category=CATEGORY [options]', 'GROUPS-ROLES', true,
            array(self::arg('name', 'Role name.')),
            array_replace($roleOptions, array(0 => self::opt('category', 'Role category.', 'CATEGORY', true))));
        self::task('group:update', 'groupUpdate', 'Update a role/group.',
            'group:update GROUP [options]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')), $roleOptions);
        self::task('group:delete', 'groupDelete', 'Delete a role/group.',
            'group:delete GROUP [--yes]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')), array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('group:activate', 'groupActivate', 'Activate a role/group.',
            'group:activate GROUP', 'GROUPS-ROLES', true, array(self::arg('group', 'Group.')));
        self::task('group:deactivate', 'groupDeactivate', 'Deactivate a role/group.',
            'group:deactivate GROUP', 'GROUPS-ROLES', true, array(self::arg('group', 'Group.')));
        self::readTask('group:export', 'groupExport', 'Export group members as vCards.',
            'group:export GROUP [--output=FILE]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')));
        self::task('group:permissions', 'groupPermissions', 'Show or change native role permission columns.',
            'group:permissions GROUP [--set=RIGHT=BOOL ...] [--format=text|json]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')), array(
                self::opt('set', 'Set a rol_* permission column.', 'RIGHT=BOOL', false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        self::readTask('group:members', 'groupMembers', 'List role memberships.',
            'group:members GROUP [--date=DATE] [--active] [--former] [--future] [--leaders] [--format=FORMAT]',
            'GROUPS-ROLES', true, array(self::arg('group', 'Group.')), array(
                self::opt('date', 'Reference date.', 'DATE'),
                self::opt('active', 'Show active memberships.', '', false, false, true),
                self::opt('former', 'Show former memberships.', '', false, false, true),
                self::opt('future', 'Show future memberships.', '', false, false, true),
                self::opt('leaders', 'Only leaders.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('group:adduser', 'groupAddUser', 'Assign a user to a group.',
            'group:adduser GROUP USER [--start=DATE] [--end=DATE] [--leader=BOOL] [--force-period]',
            null, true, array(self::arg('group', 'Group.'), self::arg('user', 'User.')), array(
                self::opt('start', 'Membership start date.', 'DATE'),
                self::opt('end', 'Membership end date.', 'DATE'),
                self::opt('leader', 'Leader status.', 'BOOL'),
                self::opt('force-period', 'Pass forcePeriod=true to Role::setMembership().', '', false, false, true)
            ));
        self::task('group:deluser', 'groupDelUser', 'End a current group membership using Role::stopMembership().',
            'group:deluser GROUP USER [--date=DATE]', null, true,
            array(self::arg('group', 'Group.'), self::arg('user', 'User.')),
            array(self::opt('date', 'Last day of the membership. Without this option the membership is ended as of today.', 'DATE')));
        self::task('group:updateuser', 'groupUpdateUser', 'Update an existing membership period/leader status.',
            'group:updateuser GROUP USER [--start=DATE] [--end=DATE] [--leader=BOOL] [--force-period=BOOL]', null, true,
            array(self::arg('group', 'Group.'), self::arg('user', 'User.')), array(
                self::opt('start', 'Membership start date.', 'DATE'),
                self::opt('end', 'Membership end date.', 'DATE'),
                self::opt('leader', 'Leader status.', 'BOOL'),
                self::opt('force-period', 'Write exactly the given period instead of merging it with an adjacent one. Defaults to true.', 'BOOL')
            ));
        self::task('group:deletemembership', 'groupDeleteMembership', 'Permanently delete one membership history row.',
            'group:deletemembership MEMBERSHIP [--yes]', 'GROUPS-ROLES', true,
            array(self::arg('membership', 'Membership UUID/id.')),
            array(self::opt('yes', 'Confirm permanent history deletion.', '', false, false, true)));
        self::readTask('group:dependencies', 'groupDependencies', 'List parent/child role dependencies.',
            'group:dependencies GROUP [--format=FORMAT]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('group:adddependency', 'groupAddDependency', 'Add a dependent child role.',
            'group:adddependency GROUP DEPENDENT_GROUP', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Parent group.'), self::arg('dependent-group', 'Dependent child group.')));
        self::task('group:deldependency', 'groupDelDependency', 'Remove a role dependency.',
            'group:deldependency GROUP DEPENDENT_GROUP [--yes]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Parent group.'), self::arg('dependent-group', 'Dependent child group.')),
            array(self::opt('yes', 'Confirm removal.', '', false, false, true)));
    }

    private static function registerListTasks(): void
    {
        self::readTask('list:list', 'listList', 'List saved member-list configurations.',
            'list:list [--global=BOOL] [--format=FORMAT]', 'GROUPS-ROLES', true, array(), array(
                self::opt('global', 'Filter global/private lists.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('list:show', 'listShow', 'Show a saved member-list configuration.',
            'list:show LIST [--format=text|json]', 'GROUPS-ROLES', true,
            array(self::arg('list', 'List UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $listOptions = array(
            self::opt('name', 'List name.', 'NAME'),
            self::opt('global', 'Make list available organization-wide.', 'BOOL'),
            self::opt('column', 'Column field id/internal name/special field.', 'COLUMN', false, true),
            self::opt('condition', 'Column filter COLUMN=EXPRESSION.', 'COLUMN=EXPRESSION', false, true),
            self::opt('sort', 'Sort COLUMN:asc|desc.', 'COLUMN:DIRECTION', false, true)
        );
        self::task('list:add', 'listAdd', 'Create a saved member-list configuration.',
            'list:add NAME [--global=BOOL] --column=COLUMN ... [--condition=...] [--sort=...]',
            'GROUPS-ROLES', true, array(self::arg('name', 'List name.')), $listOptions);
        self::task('list:update', 'listUpdate', 'Update a saved member-list configuration.',
            'list:update LIST [options]', 'GROUPS-ROLES', true,
            array(self::arg('list', 'List UUID/id.')), $listOptions);
        self::task('list:copy', 'listCopy', 'Copy a saved member-list configuration.',
            'list:copy LIST --name=NAME [--global=BOOL]', 'GROUPS-ROLES', true,
            array(self::arg('list', 'Source list.')), array(
                self::opt('name', 'Name of the copied list.', 'NAME', true),
                self::opt('global', 'Global/private flag.', 'BOOL')
            ));
        self::task('list:delete', 'listDelete', 'Delete a saved member-list configuration.',
            'list:delete LIST [--yes]', 'GROUPS-ROLES', true,
            array(self::arg('list', 'List UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::readTask(
            'list:export',
            'listExport',
            'Render a saved member list to CSV/XLSX/ODS/PDF.',
            'list:export LIST --role=GROUP ... [--relation-type=TYPE ...] [--date-from=DATE] [--date-to=DATE] [--members=active|former|all] --format=csv|xlsx|ods|pdf [--output=FILE]',
            'GROUPS-ROLES',
            true,
            array(self::arg('list', 'List UUID/id.')),
            array(
                self::opt('role', 'Role/group whose members should be exported.', 'GROUP', true, true),
                self::opt('relation-type', 'User relation type UUID/id.', 'TYPE', false, true),
                self::opt('date-from', 'Membership range start.', 'DATE'),
                self::opt('date-to', 'Membership range end.', 'DATE'),
                self::opt('members', 'Membership state.', 'STATE', false, false, false, array('active', 'former', 'all')),
                self::opt('format', 'Export format.', 'FORMAT', true, false, false, array('csv', 'xlsx', 'ods', 'pdf'))
            )
        );
    }

    private static function registerPermissionTasks(): void
    {
        self::task('permissions:list', 'permissionsList', 'List RolesRights assignments.',
            'permissions:list [--type=RIGHT_TYPE] [--format=FORMAT]', 'CORE', true, array(), array(
                self::opt('type', 'Roles-right name.', 'RIGHT_TYPE'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('permissions:show', 'permissionsShow', 'Show roles assigned to an object right.',
            'permissions:show RIGHT_TYPE OBJECT_ID [--format=text|json]', 'CORE', true,
            array(self::arg('right-type', 'Roles-right name.'), self::arg('object-id', 'Numeric object id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        foreach (array('set', 'add', 'remove') as $mode) {
            self::task('permissions:' . $mode, 'permissions' . ucfirst($mode),
                ucfirst($mode) . ' role assignments for an object right.',
                'permissions:' . $mode . ' RIGHT_TYPE OBJECT_ID --role=GROUP ...',
                'CORE', true,
                array(self::arg('right-type', 'Roles-right name.'), self::arg('object-id', 'Numeric object id.')),
                array(self::opt('role', 'Role/group.', 'GROUP', true, true)));
        }
        self::task('permissions:clear', 'permissionsClear', 'Remove all role assignments from an object right.',
            'permissions:clear RIGHT_TYPE OBJECT_ID [--yes]', 'CORE', true,
            array(self::arg('right-type', 'Roles-right name.'), self::arg('object-id', 'Numeric object id.')),
            array(self::opt('yes', 'Confirm removal.', '', false, false, true)));
    }

    private static function registerCategoryTasks(): void
    {
        self::readTask('category:list', 'categoryList', 'List categories.',
            'category:list [--type=ANN|AWA|EVT|FOT|IVT|LNK|ROL|USF] [--format=FORMAT]', 'CATEGORIES', true,
            array(), array(
                self::opt('type', 'Category type.', 'TYPE', false, false, false, array('ANN', 'AWA', 'EVT', 'FOT', 'IVT', 'LNK', 'ROL', 'USF')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('category:show', 'categoryShow', 'Show a category.',
            'category:show CATEGORY [--format=text|json]', 'CATEGORIES', true,
            array(self::arg('category', 'Category UUID/id/name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $categoryOptions = array(
            self::opt('view-role', 'Role allowed to view this category.', 'GROUP', false, true),
            self::opt('edit-role', 'Role allowed to edit this category.', 'GROUP', false, true),
            self::opt('multi-organization', 'Use a global category (cat_org_id NULL).', 'BOOL'),
            self::opt('default', 'Make this the default category of its type.', 'BOOL')
        );
        self::task('category:add', 'categoryAdd', 'Create a category with native view/edit RolesRights.',
            'category:add TYPE NAME [options]', 'CATEGORIES', true,
            array(self::arg('type', 'Category type.'), self::arg('name', 'Category name.')), $categoryOptions);
        self::task('category:update', 'categoryUpdate', 'Update a category and its view/edit rights.',
            'category:update CATEGORY [--name=NAME] [options]', 'CATEGORIES', true,
            array(self::arg('category', 'Category.')), array_merge(
                array(self::opt('name', 'Category name.', 'NAME')), $categoryOptions
            ));
        self::task('category:delete', 'categoryDelete', 'Delete an empty non-system category.',
            'category:delete CATEGORY [--yes]', 'CATEGORIES', true,
            array(self::arg('category', 'Category.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('category:move', 'categoryMove', 'Move a category one position.',
            'category:move CATEGORY up|down', 'CATEGORIES', true,
            array(self::arg('category', 'Category.'), self::arg('direction', 'up or down.')));
    }

    private static function registerMenuTasks(): void
    {
        self::task('menu:list', 'menuList', 'List menu entries.',
            'menu:list [--format=FORMAT]', 'MENU', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('menu:show', 'menuShow', 'Show a menu entry.',
            'menu:show MENU [--format=text|json]', 'MENU', true,
            array(self::arg('menu', 'Menu UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $menuOptions = array(
            self::opt('name', 'Menu name.', 'NAME'),
            self::opt('description', 'Description.', 'TEXT'),
            self::opt('parent', 'Parent menu UUID/id.', 'MENU'),
            self::opt('component', 'Component internal name.', 'COMPONENT'),
            self::opt('view-role', 'Role allowed to see this menu item.', 'GROUP', false, true),
            self::opt('url', 'Absolute or relative URL.', 'URL'),
            self::opt('icon', 'Bootstrap icon name.', 'ICON'),
            self::opt('node', 'Whether this entry is a menu node.', 'BOOL')
        );
        self::task('menu:add', 'menuAdd', 'Create a menu entry.',
            'menu:add NAME [options]', 'MENU', true,
            array(self::arg('name', 'Menu name.')), $menuOptions);
        self::task('menu:update', 'menuUpdate', 'Update a menu entry.',
            'menu:update MENU [options]', 'MENU', true,
            array(self::arg('menu', 'Menu entry.')), $menuOptions);
        self::task('menu:delete', 'menuDelete', 'Delete a menu entry.',
            'menu:delete MENU [--yes]', 'MENU', true,
            array(self::arg('menu', 'Menu entry.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('menu:move', 'menuMove', 'Move a menu entry one position.',
            'menu:move MENU up|down', 'MENU', true,
            array(self::arg('menu', 'Menu entry.'), self::arg('direction', 'up or down.')));
    }

    private static function registerAnnouncementTasks(): void
    {
        self::readTask('announcement:list', 'announcementList', 'List announcements visible to the acting user.',
            'announcement:list [--category=CATEGORY] [--search=TEXT] [--limit=N] [--offset=N] [--format=FORMAT]',
            'ANNOUNCEMENTS', true, array(), array(
                self::opt('category', 'Announcement category.', 'CATEGORY'),
                self::opt('search', 'Headline/description search.', 'TEXT'),
                self::opt('limit', 'Maximum records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('announcement:show', 'announcementShow', 'Show an announcement.',
            'announcement:show ANNOUNCEMENT [--format=text|json]', 'ANNOUNCEMENTS', true,
            array(self::arg('announcement', 'Announcement UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $annOptions = array(
            self::opt('headline', 'Headline.', 'TEXT'),
            self::opt('category', 'Announcement category.', 'CATEGORY'),
            self::opt('description', 'HTML/text description.', 'TEXT'),
            self::opt('description-file', 'Read description from file.', 'FILE')
        );
        self::task('announcement:add', 'announcementAdd', 'Create an announcement.',
            'announcement:add --headline=TEXT --category=CATEGORY (--description=TEXT|--description-file=FILE)',
            'ANNOUNCEMENTS', true, array(), array_replace($annOptions, array(
                0 => self::opt('headline', 'Headline.', 'TEXT', true),
                1 => self::opt('category', 'Announcement category.', 'CATEGORY', true)
            )));
        self::task('announcement:update', 'announcementUpdate', 'Update an announcement.',
            'announcement:update ANNOUNCEMENT [options]', 'ANNOUNCEMENTS', true,
            array(self::arg('announcement', 'Announcement.')), $annOptions);
        self::task('announcement:copy', 'announcementCopy', 'Copy an announcement.',
            'announcement:copy ANNOUNCEMENT [--headline=TEXT] [--category=CATEGORY]', 'ANNOUNCEMENTS', true,
            array(self::arg('announcement', 'Source announcement.')), $annOptions);
        self::task('announcement:delete', 'announcementDelete', 'Delete an announcement.',
            'announcement:delete ANNOUNCEMENT [--yes]', 'ANNOUNCEMENTS', true,
            array(self::arg('announcement', 'Announcement.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::readTask(
            'announcement:export-rss',
            'announcementExportRss',
            'Generate the announcements RSS feed.',
            'announcement:export-rss [--category=CATEGORY] [--output=FILE]',
            'ANNOUNCEMENTS',
            true,
            array(),
            array(self::opt('category', 'Announcement category.', 'CATEGORY'))
        );
    }

    private static function registerForumTasks(): void
    {
        self::readTask('forum:list', 'forumList', 'List forum topics.',
            'forum:list [--category=CATEGORY] [--format=FORMAT]', 'FORUM', true, array(), array(
                self::opt('category', 'Forum category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::readTask('forum:topic', 'forumTopic', 'Show a forum topic and its posts.',
            'forum:topic TOPIC [--format=text|json]', 'FORUM', true,
            array(self::arg('topic', 'Topic UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        self::task('forum:topic-add', 'forumTopicAdd', 'Create a forum topic with its first post.',
            'forum:topic-add --category=CATEGORY --title=TEXT (--text=TEXT|--text-file=FILE)',
            'FORUM', true, array(), array(
                self::opt('category', 'Forum category.', 'CATEGORY', true),
                self::opt('title', 'Topic title.', 'TEXT', true),
                self::opt('text', 'First post text.', 'TEXT'),
                self::opt('text-file', 'Read first post from file.', 'FILE')
            ));
        self::task('forum:topic-update', 'forumTopicUpdate', 'Update a forum topic.',
            'forum:topic-update TOPIC [--category=CATEGORY] [--title=TEXT]', 'FORUM', true,
            array(self::arg('topic', 'Topic.')), array(
                self::opt('category', 'Forum category.', 'CATEGORY'),
                self::opt('title', 'Topic title.', 'TEXT')
            ));
        self::task('forum:topic-delete', 'forumTopicDelete', 'Delete a forum topic and its posts.',
            'forum:topic-delete TOPIC [--yes]', 'FORUM', true,
            array(self::arg('topic', 'Topic.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('forum:post-add', 'forumPostAdd', 'Add a post to a topic.',
            'forum:post-add TOPIC (--text=TEXT|--text-file=FILE)', 'FORUM', true,
            array(self::arg('topic', 'Topic.')), array(
                self::opt('text', 'Post text.', 'TEXT'),
                self::opt('text-file', 'Read post from file.', 'FILE')
            ));
        self::task('forum:post-update', 'forumPostUpdate', 'Update a forum post.',
            'forum:post-update POST (--text=TEXT|--text-file=FILE)', 'FORUM', true,
            array(self::arg('post', 'Post UUID/id.')), array(
                self::opt('text', 'Post text.', 'TEXT'),
                self::opt('text-file', 'Read post from file.', 'FILE')
            ));
        self::task('forum:post-delete', 'forumPostDelete', 'Delete a forum post.',
            'forum:post-delete POST [--yes]', 'FORUM', true,
            array(self::arg('post', 'Post.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::readTask(
            'forum:export-rss',
            'forumExportRss',
            'Generate the forum RSS feed.',
            'forum:export-rss [--category=CATEGORY] [--output=FILE]',
            'FORUM',
            true,
            array(),
            array(self::opt('category', 'Forum category.', 'CATEGORY'))
        );
    }

    private static function registerLinkTasks(): void
    {
        self::readTask('link:list', 'linkList', 'List web links.',
            'link:list [--category=CATEGORY] [--format=FORMAT]', 'LINKS', true, array(), array(
                self::opt('category', 'Link category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('link:show', 'linkShow', 'Show a web link.',
            'link:show LINK [--format=text|json]', 'LINKS', true,
            array(self::arg('link', 'Link UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $options = array(
            self::opt('name', 'Link name.', 'NAME'),
            self::opt('url', 'URL.', 'URL'),
            self::opt('category', 'Link category.', 'CATEGORY'),
            self::opt('description', 'Description.', 'TEXT')
        );
        self::task('link:add', 'linkAdd', 'Create a web link.',
            'link:add --name=NAME --url=URL --category=CATEGORY [--description=TEXT]', 'LINKS', true, array(),
            array_replace($options, array(
                0 => self::opt('name', 'Link name.', 'NAME', true),
                1 => self::opt('url', 'URL.', 'URL', true),
                2 => self::opt('category', 'Link category.', 'CATEGORY', true)
            )));
        self::task('link:update', 'linkUpdate', 'Update a web link.',
            'link:update LINK [options]', 'LINKS', true,
            array(self::arg('link', 'Link.')), $options);
        self::task('link:delete', 'linkDelete', 'Delete a web link.',
            'link:delete LINK [--yes]', 'LINKS', true,
            array(self::arg('link', 'Link.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('link:move', 'linkMove', 'Move a web link one position in its category.',
            'link:move LINK up|down', 'LINKS', true,
            array(self::arg('link', 'Link.'), self::arg('direction', 'up or down.')));
    }

    private static function registerMessageTasks(): void
    {
        self::readTask('message:list', 'messageList', 'List messages involving the acting user.',
            'message:list [--type=email|pm] [--limit=N] [--offset=N] [--format=FORMAT]',
            'MESSAGES', true, array(), array(
                self::opt('type', 'Message type.', 'TYPE', false, false, false, array('email', 'pm')),
                self::opt('limit', 'Maximum records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::readTask('message:show', 'messageShow', 'Show a message/conversation entry.',
            'message:show MESSAGE [--format=text|json]', 'MESSAGES', true,
            array(self::arg('message', 'Message UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $messageTextOptions = array(
            self::opt('body', 'Message body.', 'TEXT'),
            self::opt('body-file', 'Read body from file.', 'FILE')
        );
        $messageBodyOptions = array_merge(
            $messageTextOptions,
            array(self::opt('attachment', 'Attachment path.', 'FILE', false, true))
        );
        $messageRecipientOptions = array(
            self::opt('user', 'Recipient user.', 'USER', false, true),
            self::opt('group', 'Recipient group; active members are addressed.', 'GROUP', false, true)
        );
        self::readTask(
            'message:send',
            'messageSend',
            'Send email/private message using the native Message/Email recipient rules.',
            'message:send --type=email|pm [recipients] --subject=TEXT (--body=TEXT|--body-file=FILE)',
            'MESSAGES',
            true,
            array(),
            array_merge(
                array(
                    self::opt('type', 'Email or private message.', 'TYPE', true, false, false, array('email', 'pm'))
                ),
                $messageRecipientOptions,
                array(
                    self::opt('subject', 'Message subject.', 'TEXT', true)
                ),
                $messageBodyOptions,
                array(
                    self::opt('carbon-copy', 'Send a copy of an email to the acting user.', 'BOOL'),
                    self::opt('delivery-confirmation', 'Request an email read confirmation where allowed.', 'BOOL')
                )
            )
        );
        self::readTask(
            'message:reply',
            'messageReply',
            'Reply to a private-message conversation.',
            'message:reply MESSAGE (--body=TEXT|--body-file=FILE)',
            'MESSAGES',
            true,
            array(self::arg('message', 'Private-message conversation.')),
            $messageTextOptions
        );
        self::readTask(
            'message:forward',
            'messageForward',
            'Forward an email as a new email.',
            'message:forward MESSAGE (--user=USER|--group=GROUP) [options]',
            'MESSAGES',
            true,
            array(self::arg('message', 'Source email.')),
            array_merge(
                $messageRecipientOptions,
                array(self::opt('subject', 'Override the original subject.', 'TEXT')),
                $messageBodyOptions,
                array(
                    self::opt('carbon-copy', 'Send a copy to the acting user.', 'BOOL'),
                    self::opt('delivery-confirmation', 'Request an email read confirmation where allowed.', 'BOOL')
                )
            )
        );
        self::task('message:delete', 'messageDelete', 'Delete message records using Message::delete().',
            'message:delete MESSAGE ... [--yes]', 'MESSAGES', true,
            array(self::arg('message', 'One or more messages.', true, true)),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)),
            array(), null, CliTaskRegistry::ACCESS_VISIBLE);
        self::readTask('message:list-attachments', 'messageAttachments', 'List attachments of a message.',
            'message:list-attachments MESSAGE [--format=FORMAT]', 'MESSAGES', true,
            array(self::arg('message', 'Message.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::readTask(
            'message:get-attachment',
            'messageGetAttachment',
            'Retrieve a message attachment.',
            'message:get-attachment ATTACHMENT [--output=FILE]',
            'MESSAGES',
            true,
            array(self::arg('attachment', 'Attachment UUID.'))
        );
    }

    private static function registerDocumentTasks(): void
    {
        self::readTask('document:list', 'documentList', 'List folders/files visible below a folder.',
            'document:list [FOLDER] [--recursive] [--format=FORMAT]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID; empty means root.', false)), array(
                self::opt('recursive', 'Traverse recursively.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask(
            'document:download',
            'documentDownload',
            'Download a managed document.',
            'document:download FILE [--output=FILEPATH]',
            'DOCUMENTS-FILES',
            true,
            array(self::arg('file', 'File UUID.'))
        );
        self::task(
            'document:upload',
            'documentUpload',
            'Upload a managed document.',
            'document:upload FILEPATH --folder=FOLDER [--name=NAME]',
            'DOCUMENTS-FILES',
            true,
            array(self::arg('filepath', 'Local file path.')),
            array(
                self::opt('folder', 'Destination folder.', 'FOLDER', true),
                self::opt('name', 'Stored filename.', 'NAME')
            )
        );
        self::task('document:file-rename', 'documentFileRename', 'Rename a managed file.',
            'document:file-rename FILE NAME', 'DOCUMENTS-FILES', true,
            array(self::arg('file', 'File UUID.'), self::arg('name', 'New filename.')));
        self::task('document:file-move', 'documentFileMove', 'Move a managed file.',
            'document:file-move FILE DESTINATION_FOLDER', 'DOCUMENTS-FILES', true,
            array(self::arg('file', 'File UUID.'), self::arg('destination-folder', 'Destination folder UUID.')));
        self::task('document:file-delete', 'documentFileDelete', 'Delete a managed file.',
            'document:file-delete FILE [--yes]', 'DOCUMENTS-FILES', true,
            array(self::arg('file', 'File UUID.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('document:folder-add', 'documentFolderAdd', 'Create a managed folder.',
            'document:folder-add PARENT_FOLDER NAME', 'DOCUMENTS-FILES', true,
            array(self::arg('parent-folder', 'Parent folder UUID; use root UUID for root.'), self::arg('name', 'Folder name.')));
        self::task('document:folder-rename', 'documentFolderRename', 'Rename a managed folder.',
            'document:folder-rename FOLDER NAME', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID.'), self::arg('name', 'New folder name.')));
        self::task('document:folder-move', 'documentFolderMove', 'Move a managed folder.',
            'document:folder-move FOLDER DESTINATION_FOLDER', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID.'), self::arg('destination-folder', 'Destination folder UUID.')));
        self::task('document:folder-delete', 'documentFolderDelete', 'Delete a managed folder recursively where native rights permit.',
            'document:folder-delete FOLDER [--yes]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID.')),
            array(self::opt('yes', 'Confirm recursive deletion.', '', false, false, true)));
        self::readTask('document:permissions', 'documentPermissions', 'Show folder view/upload role assignments.',
            'document:permissions FOLDER [--format=text|json]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        self::task('document:permissions-set', 'documentPermissionsSet', 'Set folder view/upload role assignments.',
            'document:permissions-set FOLDER [--view-role=GROUP ...] [--upload-role=GROUP ...] [--public=BOOL] [--recursive=BOOL]',
            'DOCUMENTS-FILES', true, array(self::arg('folder', 'Folder UUID.')), array(
                self::opt('view-role', 'View role.', 'GROUP', false, true),
                self::opt('upload-role', 'Upload role.', 'GROUP', false, true),
                self::opt('public', 'Folder public flag.', 'BOOL'),
                self::opt('recursive', 'Apply rights recursively.', 'BOOL')
            ));
        self::task('document:unregistered', 'documentUnregistered', 'Find physical folders/files not registered in the database.',
            'document:unregistered [FOLDER] [--format=FORMAT]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID; root if omitted.', false)),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('document:register', 'documentRegister', 'Register physical child entries missing from the database.',
            'document:register FOLDER [--recursive]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID.')),
            array(self::opt('recursive', 'Register recursively.', '', false, false, true)));
    }

    private static function registerPhotoTasks(): void
    {
        self::readTask('photo:list', 'photoList', 'List photo albums.',
            'photo:list [--parent=ALBUM] [--format=FORMAT]', 'PHOTOS', true, array(), array(
                self::opt('parent', 'Parent album or ALL for a root album.', 'ALBUM'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::readTask('photo:album-show', 'photoAlbumShow', 'Show a photo album.',
            'photo:album-show ALBUM [--format=text|json]', 'PHOTOS', true,
            array(self::arg('album', 'Album UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $albumOptions = array(
            self::opt('name', 'Album name.', 'NAME'),
            self::opt('parent', 'Parent album or ALL for a root album.', 'ALBUM'),
            self::opt('begin', 'Begin date.', 'DATE'),
            self::opt('end', 'End date.', 'DATE'),
            self::opt('photographers', 'Photographers.', 'TEXT'),
            self::opt('description', 'Description.', 'TEXT'),
            self::opt('locked', 'Album locked flag.', 'BOOL')
        );
        self::task(
            'photo:album-add',
            'photoAlbumAdd',
            'Create a photo album.',
            'photo:album-add NAME --begin=DATE [options]',
            'PHOTOS',
            true,
            array(self::arg('name', 'Album name.')),
            array_replace($albumOptions, array(
                2 => self::opt('begin', 'Begin date.', 'DATE', true)
            ))
        );
        self::task(
            'photo:album-update',
            'photoAlbumUpdate',
            'Update a photo album.',
            'photo:album-update ALBUM [options]',
            'PHOTOS',
            true,
            array(self::arg('album', 'Album.')),
            $albumOptions
        );
        self::task('photo:album-delete', 'photoAlbumDelete', 'Delete a photo album through Album::delete().',
            'photo:album-delete ALBUM [--yes]', 'PHOTOS', true,
            array(self::arg('album', 'Album.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('photo:album-lock', 'photoAlbumLock', 'Lock a photo album.',
            'photo:album-lock ALBUM', 'PHOTOS', true, array(self::arg('album', 'Album.')));
        self::task('photo:album-unlock', 'photoAlbumUnlock', 'Unlock a photo album.',
            'photo:album-unlock ALBUM', 'PHOTOS', true, array(self::arg('album', 'Album.')));
        self::readTask('photo:ecard-templates', 'photoEcardTemplates', 'List available e-card templates.',
            'photo:ecard-templates [--format=FORMAT]', 'PHOTOS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::readTask(
            'photo:album-download',
            'photoAlbumDownload',
            'Download all photos of an album as ZIP.',
            'photo:album-download ALBUM [--output=FILE]',
            'PHOTOS',
            true,
            array(self::arg('album', 'Album.'))
        );
        self::task(
            'photo:upload',
            'photoUpload',
            'Upload one or more local image files to an album.',
            'photo:upload ALBUM FILE ...',
            'PHOTOS',
            true,
            array(
                self::arg('album', 'Album.'),
                self::arg('file', 'JPEG/PNG image file.', true, true)
            )
        );
        self::readTask(
            'photo:download',
            'photoDownload',
            'Download a single photo.',
            'photo:download ALBUM PHOTO_NUMBER [--output=FILE]',
            'PHOTOS',
            true,
            array(
                self::arg('album', 'Album.'),
                self::arg('photo-number', 'Photo number.')
            )
        );
        self::task(
            'photo:delete',
            'photoDelete',
            'Delete one or more photos from an album.',
            'photo:delete ALBUM PHOTO_NUMBER ... [--yes]',
            'PHOTOS',
            true,
            array(
                self::arg('album', 'Album.'),
                self::arg('photo-number', 'One or more photo numbers.', true, true)
            ),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true))
        );
        self::task(
            'photo:rotate',
            'photoRotate',
            'Rotate a photo by 90 degrees.',
            'photo:rotate ALBUM PHOTO_NUMBER --direction=left|right',
            'PHOTOS',
            true,
            array(
                self::arg('album', 'Album.'),
                self::arg('photo-number', 'Photo number.')
            ),
            array(self::opt('direction', 'Rotation direction.', 'DIRECTION', true, false, false, array('left', 'right')))
        );
        self::readTask(
            'photo:ecard-send',
            'photoEcardSend',
            'Send an e-card.',
            'photo:ecard-send ALBUM PHOTO_NUMBER --template=TEMPLATE (--user=USER|--group=GROUP) '
                . '(--message=TEXT|--message-file=FILE)',
            'PHOTOS',
            true,
            array(
                self::arg('album', 'Album.'),
                self::arg('photo-number', 'Photo number.')
            ),
            array(
                self::opt('template', 'E-card template filename.', 'TEMPLATE', true),
                self::opt('user', 'Recipient user.', 'USER', false, true),
                self::opt('group', 'Recipient group; active members are addressed.', 'GROUP', false, true),
                self::opt('message', 'E-card message.', 'TEXT'),
                self::opt('message-file', 'Read the e-card message from a file.', 'FILE')
            ),
            array(
                'photo:ecard-send ALBUM 1 --template=default.tpl --user=john '
                    . '--message="Best wishes!" --as=admin'
            )
        );
    }

    private static function registerEventTasks(): void
    {
        self::readTask('event:list', 'eventList', 'List events.',
            'event:list [--calendar=CATEGORY] [--date-from=DATE] [--date-to=DATE] [--state=actual|old|all] [--format=FORMAT]',
            'EVENTS', true, array(), array(
                self::opt('calendar', 'Event category/calendar.', 'CATEGORY'),
                self::opt('date-from', 'Start date.', 'DATE'),
                self::opt('date-to', 'End date.', 'DATE'),
                self::opt('state', 'Restrict to upcoming or past events.', 'STATE', false, false, false, array('actual', 'old', 'all')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('event:show', 'eventShow', 'Show an event.',
            'event:show EVENT [--format=text|json]', 'EVENTS', true,
            array(self::arg('event', 'Event UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));

        $eventOptions = self::eventOptions();
        self::task('event:add', 'eventAdd', 'Create an event.',
            'event:add --headline=TEXT --from=DATETIME --calendar=CATEGORY [--repeat=FREQUENCY] [options]',
            'EVENTS', true, array(), array_replace($eventOptions, array(
                0 => self::opt('headline', 'Event title.', 'TEXT', true),
                1 => self::opt('from', 'Event start date/time (YYYY-MM-DDTHH:MM).', 'DATETIME', true),
                3 => self::opt('calendar', 'Event category/calendar.', 'CATEGORY', true)
            )));
        self::task('event:update', 'eventUpdate', 'Update an event.',
            'event:update EVENT [--recurrence-scope=this|series] [options]', 'EVENTS', true,
            array(self::arg('event', 'Event.')), $eventOptions);
        self::task('event:copy', 'eventCopy', 'Copy an event.',
            'event:copy EVENT [--repeat=FREQUENCY] [options]', 'EVENTS', true,
            array(self::arg('event', 'Source event.')), $eventOptions);
        self::task('event:delete', 'eventDelete', 'Delete an event or cancel a recurring event occurrence.',
            'event:delete EVENT [--recurrence-scope=this|series] [--yes]', 'EVENTS', true,
            array(self::arg('event', 'Event.')), array(
                self::opt('recurrence-scope', 'Scope for recurring events.', 'SCOPE', false, false, false, array('this', 'series')),
                self::opt('yes', 'Confirm deletion.', '', false, false, true)
            ));
        self::task('event:participate', 'eventParticipation', 'Set participation to yes.',
            'event:participate EVENT [USER] [--guests=N] [--comment=TEXT]', 'EVENTS',
            true, array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)), array(
                self::opt('guests', 'Number of additional guests.', 'N'),
                self::opt('comment', 'Participation comment.', 'TEXT')
            ), array(), null, CliTaskRegistry::ACCESS_VISIBLE);
        self::task('event:cancel', 'eventParticipation', 'Set participation to no.',
            'event:cancel EVENT [USER] [--comment=TEXT]', 'EVENTS',
            true, array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)),
            array(self::opt('comment', 'Participation comment.', 'TEXT')),
            array(), null, CliTaskRegistry::ACCESS_VISIBLE);
        self::task('event:maybe', 'eventParticipation', 'Set participation to maybe.',
            'event:maybe EVENT [USER] [--comment=TEXT]', 'EVENTS',
            true, array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)),
            array(self::opt('comment', 'Participation comment.', 'TEXT')),
            array(), null, CliTaskRegistry::ACCESS_VISIBLE);
        self::readTask('event:participants', 'eventParticipants', 'List event participants.',
            'event:participants EVENT [--format=FORMAT]', 'EVENTS', true,
            array(self::arg('event', 'Event.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::readTask('event:export', 'eventExport', 'Export an event as iCalendar.',
            'event:export EVENT [--output=FILE]', 'EVENTS', true,
            array(self::arg('event', 'Event.')));
        self::readTask('event:export-calendar', 'eventExportCalendar', 'Export an event range/calendar as iCalendar.',
            'event:export-calendar [--calendar=CATEGORY] [--date-from=DATE] [--date-to=DATE] [--output=FILE]',
            'EVENTS', true, array(), array(
                self::opt('calendar', 'Event calendar/category.', 'CATEGORY'),
                self::opt('date-from', 'Start date.', 'DATE'),
                self::opt('date-to', 'End date.', 'DATE')
            ));
    }

    private static function registerRoomTasks(): void
    {
        self::readTask('room:list', 'roomList', 'List rooms.',
            'room:list [--format=FORMAT]', 'ROOMS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::readTask('room:show', 'roomShow', 'Show a room.',
            'room:show ROOM [--format=text|json]', 'ROOMS', true,
            array(self::arg('room', 'Room UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));

        $roomOptions = array(
            self::opt('name', 'Room name.', 'NAME'),
            self::opt('capacity', 'Normal capacity.', 'N'),
            self::opt('overhang', 'Additional overhang capacity.', 'N'),
            self::opt('description', 'Room description.', 'TEXT')
        );
        self::task('room:add', 'roomAdd', 'Create a room.',
            'room:add NAME [--capacity=N] [--overhang=N] [--description=TEXT]', 'ROOMS', true,
            array(self::arg('name', 'Room name.')), $roomOptions);
        self::task('room:update', 'roomUpdate', 'Update a room.',
            'room:update ROOM [options]', 'ROOMS', true,
            array(self::arg('room', 'Room.')), $roomOptions);
        self::task('room:delete', 'roomDelete', 'Delete an unused room.',
            'room:delete ROOM [--yes]', 'ROOMS', true,
            array(self::arg('room', 'Room.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
    }

    private static function registerInventoryTasks(): void
    {
        self::readTask('inventory:list', 'inventoryList', 'List inventory items.',
            'inventory:list [--search=TEXT] [--category=CATEGORY] [--status=active|retired|all] [--format=FORMAT]',
            'INVENTORY', true, array(), array(
                self::opt('search', 'Search item data.', 'TEXT'),
                self::opt('category', 'Inventory category.', 'CATEGORY'),
                self::opt('status', 'Item status.', 'STATUS', false, false, false, array('active', 'retired', 'all')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::readTask('inventory:show', 'inventoryShow', 'Show an inventory item and its configured field values.',
            'inventory:show ITEM [--format=text|json]', 'INVENTORY', true,
            array(self::arg('item', 'Item UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));

        self::task('inventory:add', 'inventoryAdd', 'Create an inventory item.',
            'inventory:add --field=FIELD=VALUE ...', 'INVENTORY', true,
            array(), array(self::opt('field', 'Inventory field assignment.', 'FIELD=VALUE', true, true)));
        self::task('inventory:update', 'inventoryUpdate', 'Update an inventory item.',
            'inventory:update ITEM [--field=FIELD=VALUE ...]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(self::opt('field', 'Inventory field assignment.', 'FIELD=VALUE', false, true)));
        self::task('inventory:copy', 'inventoryCopy', 'Copy an inventory item.',
            'inventory:copy ITEM [--copies=N] [--number-field=FIELD] [--field=FIELD=VALUE ...]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(
                self::opt('copies', 'Number of copies.', 'N'),
                self::opt('number-field', 'Numeric inventory field to increment for copied items.', 'FIELD'),
                self::opt('field', 'Inventory field assignment.', 'FIELD=VALUE', false, true)
            ));
        self::task('inventory:delete', 'inventoryDelete', 'Delete inventory items using ItemService::delete().',
            'inventory:delete ITEM ... [--yes]', 'INVENTORY', true,
            array(self::arg('item', 'One or more items.', true, true)),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('inventory:retire', 'inventoryRetire', 'Retire inventory items using ItemService::retireItem().',
            'inventory:retire ITEM ...', 'INVENTORY', true,
            array(self::arg('item', 'One or more items.', true, true)));
        self::task('inventory:reinstate', 'inventoryReinstate', 'Reinstate retired inventory items.',
            'inventory:reinstate ITEM ...', 'INVENTORY', true,
            array(self::arg('item', 'One or more items.', true, true)));
        self::task('inventory:checkout', 'inventoryCheckout', 'Assign an inventory item to a receiver.',
            'inventory:checkout ITEM --user=USER [--date=DATE]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(
                self::opt('user', 'Receiver user.', 'USER', true),
                self::opt('date', 'Borrow date.', 'DATE')
            ));
        self::task('inventory:return', 'inventoryReturn', 'Return a checked-out inventory item.',
            'inventory:return ITEM [--date=DATE]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(self::opt('date', 'Return date.', 'DATE')));
        self::task('inventory:picture-set', 'inventoryPictureSet', 'Set the picture of an inventory item.',
            'inventory:picture-set ITEM FILE', 'INVENTORY', true,
            array(self::arg('item', 'Item.'), self::arg('file', 'JPEG/PNG image file.')));
        self::task('inventory:picture-get', 'inventoryPictureGet', 'Export the picture of an inventory item.',
            'inventory:picture-get ITEM [--output=FILE]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')));
        self::task('inventory:picture-delete', 'inventoryPictureDelete', 'Delete the picture of an inventory item.',
            'inventory:picture-delete ITEM [--yes]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        $importOptions = array(
                self::opt('input-format', 'Input file format.', 'FORMAT', false, false, false,
                    array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML')),
                self::opt('encoding', 'CSV input encoding.', 'ENCODING', false, false, false,
                    array('GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1')),
                self::opt('separator', 'CSV separator.', 'SEPARATOR', false, false, false,
                    array('auto', 'comma', 'semicolon', 'tab', 'pipe')),
                self::opt('enclosure', 'CSV field enclosure.', 'ENCLOSURE', false, false, false,
                    array('auto', 'none', 'double', 'single')),
                self::opt('sheet', 'Worksheet name or zero-based index.', 'SHEET'),
                self::opt('first-row', 'Whether the first row contains column names. Defaults to true.', 'BOOL'),
                self::opt('map', 'Map an inventory field to a one-based column number or header: FIELD=COLUMN.',
                    'FIELD=COLUMN', false, true)
        );
        self::task('inventory:import', 'inventoryImport', 'Import inventory items from a spreadsheet or delimited file.',
            'inventory:import FILE [options]', 'INVENTORY', true,
            array(self::arg('file', 'Import file.')), $importOptions);
        self::readTask('inventory:import-check', 'inventoryImportCheck',
            'Preview an inventory import: report the resolved field mapping and the number of items without writing anything.',
            'inventory:import-check FILE [options] [--format=text|json]', 'INVENTORY', true,
            array(self::arg('file', 'Import file.')), array_merge($importOptions, array(
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            )));
        self::readTask('inventory:export', 'inventoryExport', 'Export inventory items.',
            'inventory:export --format=FORMAT [--output=FILE]', 'INVENTORY', true,
            array(), array(
                self::opt('format', 'Export format.', 'FORMAT', true, false, false,
                    array('xlsx', 'ods', 'csv-ms', 'csv-oo', 'pdf', 'pdfl'))
            ));

        self::readTask('inventory:fields', 'inventoryFields', 'List inventory field definitions.',
            'inventory:fields [--format=FORMAT]', 'INVENTORY', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::readTask('inventory:field-show', 'inventoryFieldShow', 'Show an inventory field definition.',
            'inventory:field-show FIELD [--format=text|json]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field UUID/id/internal name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $inventoryFieldOptions = array(
            self::opt('name', 'Field name.', 'NAME'),
            self::opt('type', 'Field type.', 'TYPE', false, false, false, array(
                'CATEGORY', 'CHECKBOX', 'DATE', 'DECIMAL', 'DROPDOWN', 'DROPDOWN_MULTISELECT',
                'DROPDOWN_DATE_INTERVAL', 'EMAIL', 'NUMBER', 'PHONE', 'RADIO_BUTTON', 'TEXT', 'TEXT_BIG', 'URL'
            )),
            self::opt('connected-field', 'Connected DATE field.', 'FIELD'),
            self::opt('required', 'Mandatory-field mode.', 'MODE', false, false, false, array('0', '1')),
            self::opt('description', 'Field description.', 'TEXT')
        );
        self::task('inventory:field-add', 'inventoryFieldAdd', 'Create an inventory field.',
            'inventory:field-add --name=NAME --type=TYPE [options]', 'INVENTORY', true, array(),
            array_replace($inventoryFieldOptions, array(
                0 => self::opt('name', 'Field name.', 'NAME', true),
                1 => self::opt('type', 'Field type.', 'TYPE', true, false, false, array(
                    'CATEGORY', 'CHECKBOX', 'DATE', 'DECIMAL', 'DROPDOWN', 'DROPDOWN_MULTISELECT',
                    'DROPDOWN_DATE_INTERVAL', 'EMAIL', 'NUMBER', 'PHONE', 'RADIO_BUTTON', 'TEXT', 'TEXT_BIG', 'URL'
                ))
            )));
        self::task('inventory:field-update', 'inventoryFieldUpdate', 'Update an inventory field.',
            'inventory:field-update FIELD [options]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.')), $inventoryFieldOptions);
        self::task('inventory:field-delete', 'inventoryFieldDelete', 'Delete an inventory field using ItemFieldService::delete().',
            'inventory:field-delete FIELD [--yes]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('inventory:field-move', 'inventoryFieldMove', 'Move an inventory field.',
            'inventory:field-move FIELD up|down', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.'), self::arg('direction', 'up or down.')));
        self::readTask('inventory:options', 'inventoryOptions', 'List select options for an inventory field.',
            'inventory:options FIELD [--include-obsolete] [--format=FORMAT]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.')), array(
                self::opt('include-obsolete', 'Include obsolete options.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('inventory:option-add', 'inventoryOptionAdd', 'Add a select option.',
            'inventory:option-add FIELD VALUE', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.'), self::arg('value', 'Option value.')));
        self::task('inventory:option-update', 'inventoryOptionUpdate', 'Update a select option.',
            'inventory:option-update FIELD OPTION [--value=VALUE] [--obsolete=BOOL]',
            'INVENTORY', true, array(self::arg('field', 'Inventory field.'), self::arg('option', 'Option id.')), array(
                self::opt('value', 'Option value.', 'VALUE'),
                self::opt('obsolete', 'Obsolete flag.', 'BOOL')
            ));
        self::task('inventory:option-delete', 'inventoryOptionDelete', 'Delete a select option if unused.',
            'inventory:option-delete FIELD OPTION [--yes]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.'), self::arg('option', 'Option id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('inventory:option-move', 'inventoryOptionMove', 'Move a select option.',
            'inventory:option-move FIELD OPTION up|down', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.'), self::arg('option', 'Option id.'), self::arg('direction', 'up or down.')));
    }

    private static function registerProfileFieldTasks(): void
    {
        self::readTask('profile:fields', 'profileFields', 'List profile field definitions.',
            'profile:fields [--category=CATEGORY] [--format=FORMAT]', 'CONTACTS', true, array(), array(
                self::opt('category', 'Profile-field category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::readTask('profile:field-show', 'profileFieldShow', 'Show a profile field definition.',
            'profile:field-show FIELD [--format=text|json]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field UUID/id/internal name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $profileFieldOptions = array(
            self::opt('name', 'Field name.', 'NAME'),
            self::opt('category', 'Profile-field category.', 'CATEGORY'),
            self::opt('type', 'Field type.', 'TYPE', false, false, false, array(
                'CHECKBOX', 'DATE', 'DECIMAL', 'DROPDOWN', 'DROPDOWN_MULTISELECT', 'EMAIL',
                'NUMBER', 'PHONE', 'RADIO_BUTTON', 'TEXT', 'TEXT_BIG', 'URL'
            )),
            self::opt('required', 'Mandatory-field mode.', 'MODE', false, false, false, array('0', '1', '2', '3')),
            self::opt('hidden', 'Hidden field flag.', 'BOOL'),
            self::opt('disabled', 'Disabled field flag.', 'BOOL'),
            self::opt('registration', 'Show field during registration.', 'BOOL'),
            self::opt('default', 'Default value.', 'VALUE'),
            self::opt('regex', 'Regular expression.', 'REGEX'),
            self::opt('icon', 'Bootstrap icon.', 'ICON'),
            self::opt('url', 'URL template.', 'URL'),
            self::opt('description', 'Field description.', 'TEXT')
        );
        self::task('profile:field-add', 'profileFieldAdd', 'Create a profile field.',
            'profile:field-add --name=NAME --category=CATEGORY --type=TYPE [options]', 'CONTACTS', true, array(),
            array_replace($profileFieldOptions, array(
                0 => self::opt('name', 'Field name.', 'NAME', true),
                1 => self::opt('category', 'Profile-field category.', 'CATEGORY', true),
                2 => self::opt('type', 'Field type.', 'TYPE', true, false, false, array(
                    'CHECKBOX', 'DATE', 'DECIMAL', 'DROPDOWN', 'DROPDOWN_MULTISELECT', 'EMAIL',
                    'NUMBER', 'PHONE', 'RADIO_BUTTON', 'TEXT', 'TEXT_BIG', 'URL'
                ))
            )), requiredRight: 'administrator');
        self::task('profile:field-update', 'profileFieldUpdate', 'Update a profile field.',
            'profile:field-update FIELD [options]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.')), $profileFieldOptions, requiredRight: 'administrator');
        self::task('profile:field-delete', 'profileFieldDelete', 'Delete a profile field through ProfileField::delete().',
            'profile:field-delete FIELD [--yes]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('profile:field-move', 'profileFieldMove', 'Move a profile field.',
            'profile:field-move FIELD up|down', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('direction', 'up or down.')));
        self::readTask('profile:options', 'profileOptions', 'List select options of a profile field.',
            'profile:options FIELD [--include-obsolete] [--format=FORMAT]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.')), array(
                self::opt('include-obsolete', 'Include obsolete options.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('profile:option-add', 'profileOptionAdd', 'Add a profile select option.',
            'profile:option-add FIELD VALUE', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('value', 'Option value.')));
        self::task('profile:option-update', 'profileOptionUpdate', 'Update a profile select option.',
            'profile:option-update FIELD OPTION [--value=VALUE] [--obsolete=BOOL]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('option', 'Option id.')), array(
                self::opt('value', 'Option value.', 'VALUE'),
                self::opt('obsolete', 'Obsolete flag.', 'BOOL')
            ));
        self::task('profile:option-delete', 'profileOptionDelete', 'Delete an unused profile select option.',
            'profile:option-delete FIELD OPTION [--yes]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('option', 'Option id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('profile:option-move', 'profileOptionMove', 'Move a profile select option.',
            'profile:option-move FIELD OPTION up|down', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('option', 'Option id.'), self::arg('direction', 'up or down.')));
    }

    private static function registerCategoryReportTasks(): void
    {
        self::readTask('category-report:list', 'categoryReportList', 'List category-report configurations.',
            'category-report:list [--format=FORMAT]', 'CATEGORY-REPORT', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::readTask('category-report:show', 'categoryReportShow', 'Show a category-report configuration.',
            'category-report:show CONFIG [--format=text|json]', 'CATEGORY-REPORT', true,
            array(self::arg('config', 'Report config UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $reportOptions = array(
            self::opt('name', 'Configuration name.', 'NAME'),
            self::opt('role', 'Restrict report to role/group.', 'GROUP', false, true),
            self::opt('category', 'Restrict report to role category.', 'CATEGORY', false, true),
            self::opt('column', 'Report column code (for example p12, r4, l4, udummy).', 'COLUMN', false, true),
            self::opt('condition', 'Condition aligned with --column; repeat in column order.', 'CONDITION', false, true),
            self::opt('number-column', 'Show running row number.', 'BOOL'),
            self::opt('default', 'Make this the default report configuration.', 'BOOL')
        );
        self::task('category-report:add', 'categoryReportAdd', 'Create a category-report configuration.',
            'category-report:add --name=NAME --column=COLUMN ... [options]', 'CATEGORY-REPORT', true, array(),
            array_replace($reportOptions, array(
                0 => self::opt('name', 'Configuration name.', 'NAME', true),
                3 => self::opt('column', 'Report column code.', 'COLUMN', true, true)
            )), requiredRight: 'administrator');
        self::task('category-report:update', 'categoryReportUpdate', 'Update a category-report configuration.',
            'category-report:update CONFIG [options]', 'CATEGORY-REPORT', true,
            array(self::arg('config', 'Report config id/name.')), $reportOptions, requiredRight: 'administrator');
        self::task('category-report:copy', 'categoryReportCopy', 'Copy a category-report configuration.',
            'category-report:copy CONFIG [--name=NAME]', 'CATEGORY-REPORT', true,
            array(self::arg('config', 'Report config id/name.')),
            array(self::opt('name', 'Name of the copied configuration.', 'NAME')), requiredRight: 'administrator');
        self::task('category-report:delete', 'categoryReportDelete', 'Delete a category-report configuration.',
            'category-report:delete CONFIG [--yes]', 'CATEGORY-REPORT', true,
            array(self::arg('config', 'Report config id/name.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)), requiredRight: 'administrator');
        self::readTask('category-report:run', 'categoryReportRun', 'Run a category-report configuration.',
            'category-report:run CONFIG [--date=DATE] [--filter=TEXT] [--format=FORMAT]',
            'CATEGORY-REPORT', true, array(self::arg('config', 'Report config id/name.')), array(
                self::opt('date', 'Reference date.', 'DATE'),
                self::opt('filter', 'Only include rows containing text.', 'TEXT'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
    }

    private static function registerChangelogTasks(): void
    {
        self::task('changelog:list', 'changelogList', 'List permitted changelog records.',
            'changelog:list [--table=TABLE ...] [--object=UUID] [--user=USER] [--date-from=DATE] [--date-to=DATE] [--action=ACTION] [--format=FORMAT]',
            null, true, array(), array(
                self::opt('table', 'Database table.', 'TABLE', false, true),
                self::opt('object', 'Object UUID.', 'UUID'),
                self::opt('user', 'Creating user.', 'USER'),
                self::opt('date-from', 'Start date.', 'DATE'),
                self::opt('date-to', 'End date.', 'DATE'),
                self::opt('action', 'create, change or delete.', 'ACTION', false, false, false, array('create', 'change', 'delete')),
                self::opt('limit', 'Maximum records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('changelog:show', 'changelogShow', 'Show one permitted changelog record.',
            'changelog:show CHANGE [--format=text|json]', null, true,
            array(self::arg('change', 'Log change id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
    }

    private static function registerPluginTasks(): void
    {
        self::task('plugin:list', 'pluginList', 'List discovered plugins and installation/update state.',
            'plugin:list [--installed] [--updates] [--format=FORMAT]', 'PLUGINS', true, array(), array(
                self::opt('installed', 'Only installed plugins.', '', false, false, true),
                self::opt('updates', 'Only plugins with updates.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('plugin:show', 'pluginShow', 'Show plugin metadata/state.',
            'plugin:show PLUGIN [--format=text|json]', 'PLUGINS', true,
            array(self::arg('plugin', 'Plugin name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        self::task('plugin:install', 'pluginInstall', 'Install a current-interface plugin.',
            'plugin:install PLUGIN [--add-menu=BOOL]', 'PLUGINS', true,
            array(self::arg('plugin', 'Plugin name.')),
            array(self::opt('add-menu', 'Add plugin menu entry.', 'BOOL')));
        self::task('plugin:update', 'pluginUpdate', 'Run a plugin update.',
            'plugin:update PLUGIN', 'PLUGINS', true, array(self::arg('plugin', 'Plugin name.')));
        self::task('plugin:remove', 'pluginRemove', 'Uninstall a current-interface plugin.',
            'plugin:remove PLUGIN [--remove-menu=BOOL] [--yes]', 'PLUGINS', true,
            array(self::arg('plugin', 'Plugin name.')), array(
                self::opt('remove-menu', 'Remove plugin menu entry.', 'BOOL'),
                self::opt('yes', 'Confirm uninstall.', '', false, false, true)
            ));
        self::task(
            'plugin:move',
            'pluginMove',
            'Move plugin ordering.',
            'plugin:move PLUGIN up|down',
            'PLUGINS',
            true,
            array(self::arg('plugin', 'Plugin name.'), self::arg('direction', 'up or down.'))
        );

    }

    private static function registerSsoTasks(): void
    {
        self::task('sso:list', 'ssoList', 'List SAML/OIDC clients.',
            'sso:list [--type=saml|oidc] [--enabled=BOOL] [--format=FORMAT]', 'CORE', true, array(), array(
                self::opt('type', 'Client type.', 'TYPE', false, false, false, array('saml', 'oidc')),
                self::opt('enabled', 'Enabled filter.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('sso:show', 'ssoShow', 'Show an SSO client.',
            'sso:show CLIENT [--type=saml|oidc] [--format=text|json]', 'CORE', true,
            array(self::arg('client', 'Client UUID/client id.')), array(
                self::opt('type', 'Disambiguate client type.', 'TYPE', false, false, false, array('saml', 'oidc')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        $samlOptions = array(
            self::opt('name', 'Client name.', 'NAME'),
            self::opt('client-id', 'SAML service-provider entity/client id.', 'ID'),
            self::opt('enabled', 'Enabled flag.', 'BOOL'),
            self::opt('metadata-url', 'Service-provider metadata URL.', 'URL'),
            self::opt('acs-url', 'Assertion Consumer Service URL.', 'URL'),
            self::opt('slo-url', 'Single Logout Service URL.', 'URL'),
            self::opt('certificate', 'X.509 certificate PEM file.', 'FILE'),
            self::opt('require-auth-signed', 'Require signed AuthnRequests.', 'BOOL'),
            self::opt('sign-assertions', 'Sign assertions.', 'BOOL'),
            self::opt('encrypt-assertions', 'Encrypt assertions.', 'BOOL'),
            self::opt('validate-signatures', 'Validate request signatures.', 'BOOL'),
            self::opt('assertion-lifetime', 'Assertion lifetime in seconds.', 'SECONDS'),
            self::opt('clock-skew', 'Allowed clock skew in seconds.', 'SECONDS'),
            self::opt('userid-field', 'Admidio user field used as SSO subject.', 'FIELD'),
            self::opt('field-map', 'Map ADM_FIELD=SSO_FIELD.', 'ADM_FIELD=SSO_FIELD', false, true),
            self::opt('field-map-other', 'Include all other profile fields.', 'BOOL'),
            self::opt('role-map', 'Map GROUP=SSO_ROLE.', 'GROUP=SSO_ROLE', false, true),
            self::opt('role-map-other', 'Include all other role memberships.', 'BOOL'),
            self::opt('access-role', 'Role/group allowed to use this client.', 'GROUP', false, true)
        );
        self::task('sso:saml-add', 'ssoSamlAdd', 'Create a SAML client.',
            'sso:saml-add --name=NAME --client-id=ID --acs-url=URL [options]', 'CORE', true, array(),
            array_replace($samlOptions, array(
                0 => self::opt('name', 'Client name.', 'NAME', true),
                1 => self::opt('client-id', 'SAML service-provider entity/client id.', 'ID', true),
                4 => self::opt('acs-url', 'Assertion Consumer Service URL.', 'URL', true)
            )));
        self::task('sso:saml-update', 'ssoSamlUpdate', 'Update a SAML client.',
            'sso:saml-update CLIENT [options]', 'CORE', true,
            array(self::arg('client', 'SAML client UUID/client id.')), $samlOptions);

        $oidcOptions = array(
            self::opt('name', 'Client name.', 'NAME'),
            self::opt('client-id', 'OIDC client id.', 'ID'),
            self::opt('client-secret', 'New client secret. Prefer --client-secret-stdin.', 'SECRET'),
            self::opt('client-secret-stdin', 'Read the new client secret from STDIN.', '', false, false, true),
            self::opt('enabled', 'Enabled flag.', 'BOOL'),
            self::opt('redirect-uri', 'Redirect URI.', 'URI'),
            self::opt('userid-field', 'Admidio user field used as subject.', 'FIELD'),
            self::opt('scope', 'Allowed OIDC scope.', 'SCOPE', false, true),
            self::opt('field-map', 'Map ADM_FIELD=CLAIM.', 'ADM_FIELD=CLAIM', false, true),
            self::opt('field-map-other', 'Reject/unmapped other fields according to OIDC mapping mode.', 'BOOL'),
            self::opt('role-map', 'Map GROUP=CLAIM.', 'GROUP=CLAIM', false, true),
            self::opt('role-map-other', 'Include all other role memberships.', 'BOOL'),
            self::opt('access-role', 'Role/group allowed to use this client.', 'GROUP', false, true)
        );
        self::task('sso:oidc-add', 'ssoOidcAdd', 'Create an OIDC client.',
            'sso:oidc-add --name=NAME --client-id=ID [options]', 'CORE', true, array(),
            array_replace($oidcOptions, array(
                0 => self::opt('name', 'Client name.', 'NAME', true),
                1 => self::opt('client-id', 'OIDC client id.', 'ID', true)
            )));
        self::task('sso:oidc-update', 'ssoOidcUpdate', 'Update an OIDC client.',
            'sso:oidc-update CLIENT [options]', 'CORE', true,
            array(self::arg('client', 'OIDC client UUID/client id.')), $oidcOptions);
        self::task('sso:saml-delete', 'ssoDelete', 'Delete a SAML client using the native SAMLClient entity.',
            'sso:saml-delete CLIENT [--yes]', 'CORE', true,
            array(self::arg('client', 'SAML client UUID/client id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('sso:oidc-delete', 'ssoDelete', 'Delete an OIDC client using the native OIDCClient entity.',
            'sso:oidc-delete CLIENT [--yes]', 'CORE', true,
            array(self::arg('client', 'OIDC client UUID/client id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('sso:enable', 'ssoEnable', 'Enable an SSO client using SSOClient::enable().',
            'sso:enable CLIENT [--type=saml|oidc]', 'CORE', true,
            array(self::arg('client', 'Client UUID/client id.')),
            array(self::opt('type', 'Disambiguate client type.', 'TYPE', false, false, false, array('saml', 'oidc'))));
        self::task('sso:disable', 'ssoDisable', 'Disable an SSO client using SSOClient::enable(false).',
            'sso:disable CLIENT [--type=saml|oidc]', 'CORE', true,
            array(self::arg('client', 'Client UUID/client id.')),
            array(self::opt('type', 'Disambiguate client type.', 'TYPE', false, false, false, array('saml', 'oidc'))));
        self::task(
            'sso:oidc-discovery',
            'ssoOidcDiscovery',
            'Render OIDC discovery metadata.',
            'sso:oidc-discovery [--format=json] [--output=FILE]',
            'CORE',
            true,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('json')))
        );
        self::task(
            'sso:saml-metadata',
            'ssoSamlMetadata',
            'Render SAML IdP metadata.',
            'sso:saml-metadata [--output=FILE]',
            'CORE',
            true
        );

        self::task('sso:keys', 'ssoKeys', 'List SSO signing/encryption keys.',
            'sso:keys [--active=BOOL] [--format=FORMAT]', 'CORE', true, array(), array(
                self::opt('active', 'Only active keys.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('sso:key-show', 'ssoKeyShow', 'Show SSO key metadata without private key material.',
            'sso:key-show KEY [--format=text|json]', 'CORE', true,
            array(self::arg('key', 'Key UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $keyOptions = array(
            self::opt('name', 'Key name.', 'NAME'),
            self::opt(
                'algorithm',
                'Key algorithm.',
                'ALGORITHM',
                false,
                false,
                false,
                array('RSA', 'RSA-2048', 'RSA-3072', 'RSA-4096')
            ),
            self::opt('active', 'Active flag.', 'BOOL'),
            self::opt('country', 'Certificate country code.', 'CODE'),
            self::opt('state', 'Certificate state/province.', 'TEXT'),
            self::opt('locality', 'Certificate city/locality.', 'TEXT'),
            self::opt('organization-name', 'Certificate organization.', 'TEXT'),
            self::opt('organization-unit', 'Certificate organization unit.', 'TEXT'),
            self::opt('common-name', 'Certificate common name.', 'TEXT'),
            self::opt('admin-email', 'Certificate administrator email.', 'EMAIL'),
            self::opt('expires', 'Certificate expiration date.', 'DATE')
        );
        $newKeyOptions = array_replace($keyOptions, array(
            0 => self::opt('name', 'Key name.', 'NAME', true),
            3 => self::opt('country', 'Certificate country code.', 'CODE', true),
            4 => self::opt('state', 'Certificate state/province.', 'TEXT', true),
            5 => self::opt('locality', 'Certificate city/locality.', 'TEXT', true),
            7 => self::opt('organization-unit', 'Certificate organization unit.', 'TEXT', true)
        ));
        self::task('sso:key-add', 'ssoKeyAdd', 'Generate and store a new SSO key/certificate.',
            'sso:key-add --name=NAME --country=CODE --state=TEXT --locality=TEXT --organization-unit=TEXT [options]',
            'CORE', true, array(), $newKeyOptions);
        self::task('sso:key-update', 'ssoKeyUpdate', 'Update SSO key metadata without regenerating the key.',
            'sso:key-update KEY [--name=NAME] [--active=BOOL]', 'CORE', true,
            array(self::arg('key', 'Key UUID/id.')), array(
                self::opt('name', 'Key name.', 'NAME'),
                self::opt('active', 'Active flag.', 'BOOL')
            ));
        self::task('sso:key-generate', 'ssoKeyGenerate', 'Generate a new key and certificate.',
            'sso:key-generate --name=NAME --country=CODE --state=TEXT --locality=TEXT --organization-unit=TEXT [options]',
            'CORE', true, array(), $newKeyOptions);
        self::task('sso:key-regenerate', 'ssoKeyRegenerate', 'Regenerate an existing key and certificate.',
            'sso:key-regenerate KEY [options] [--yes]', 'CORE', true,
            array(self::arg('key', 'Key UUID/id.')),
            array_merge($keyOptions, array(self::opt('yes', 'Confirm key regeneration.', '', false, false, true))));
        self::task('sso:key-delete', 'ssoKeyDelete', 'Delete an SSO key Entity.',
            'sso:key-delete KEY [--yes]', 'CORE', true,
            array(self::arg('key', 'Key UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task(
            'sso:key-export',
            'ssoKeyExport',
            'Export an SSO key as PKCS#12.',
            'sso:key-export KEY [--password=PASSWORD|--password-stdin] [--output=FILE]',
            'CORE',
            true,
            array(self::arg('key', 'Key UUID/id.')),
            array(
                self::opt('password', 'PKCS#12 export password. Prefer --password-stdin.', 'PASSWORD'),
                self::opt('password-stdin', 'Read export password from STDIN.', '', false, false, true)
            )
        );
        self::task(
            'sso:certificate-export',
            'ssoCertificateExport',
            'Export an SSO certificate.',
            'sso:certificate-export KEY [--output=FILE]',
            'CORE',
            true,
            array(self::arg('key', 'Key UUID/id.'))
        );
        self::task('sso:token-cleanup', 'ssoTokenCleanup', 'Delete expired/revoked OIDC token/code rows.',
            'sso:token-cleanup [--yes]', 'CORE', true, array(),
            array(self::opt('yes', 'Confirm cleanup.', '', false, false, true)));
    }

    private static function registerSessionTasks(): void
    {
        self::task('session:invalidate', 'sessionInvalidate', 'Mark all active sessions of a user for reload.',
            'session:invalidate USER', 'CORE', true,
            array(self::arg('user', 'User.')));
        self::task('session:invalidate-all', 'sessionInvalidateAll', 'Mark all active sessions for reload.',
            'session:invalidate-all [--yes]', 'CORE', true, array(),
            array(self::opt('yes', 'Confirm invalidation.', '', false, false, true)));
        self::task('session:cleanup', 'sessionCleanup', 'Delete stale PHP-session database records.',
            'session:cleanup [--max-inactive-minutes=N]', 'CORE', true, array(),
            array(self::opt('max-inactive-minutes', 'Inactive age threshold.', 'N')));
        self::task('autologin:cleanup', 'autoLoginCleanup', 'Run native auto-login table cleanup.',
            'autologin:cleanup', 'CORE', true);
    }

    private static function registerModuleTasks(): void
    {
        self::task('module:list', 'moduleList', 'List module namespaces represented by registered commands.',
            'module:list [--format=FORMAT]', null, false, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('module:tasks', 'moduleTasks', 'List registered tasks, optionally restricted to one namespace.',
            'module:tasks [MODULE] [--format=FORMAT]', null, false,
            array(self::arg('module', 'Optional module namespace.', false)),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
    }

    public static function help(array $arguments, array $options): int
    {
        return (new CliApplication())->showHelp($arguments, $options);
    }

    public static function listCommands(array $arguments, array $options): int
    {
        return (new CliApplication())->showList($arguments, $options);
    }

    public static function selfCheck(array $arguments, array $options): int
    {
        $problems = CliSelfCheck::run();
        $statistics = CliSelfCheck::statistics();
        $format = CliApplication::optionString($options, 'format', 'table');

        if ($format === 'json') {
            CliApplication::writeValue(
                array(
                    'ok' => $problems === array(),
                    'checked' => $statistics,
                    'problems' => $problems
                ),
                $options,
                'json'
            );

            return $problems === array()
                ? CliApplication::EXIT_SUCCESS
                : CliApplication::EXIT_STATE_NOT_OK;
        }

        if ($problems !== array()) {
            CliApplication::writeRows($problems, $format, $options);
        }

        CliApplication::writeSuccess(
            ($problems === array() ? 'OK: ' : 'FAILED: ' . count($problems) . ' problem(s) in ')
            . $statistics['commands'] . ' commands, '
            . $statistics['internal_calls'] . ' internal calls and '
            . $statistics['imports'] . ' imports across '
            . $statistics['source_files'] . ' source files.',
            $options
        );

        return $problems === array()
            ? CliApplication::EXIT_SUCCESS
            : CliApplication::EXIT_STATE_NOT_OK;
    }

    public static function completion(array $arguments, array $options): int
    {
        $shell = strtolower($arguments[0] ?? 'bash');
        if (!in_array($shell, array('bash', 'zsh'), true)) {
            throw new InvalidArgumentException('completion supports bash and zsh.');
        }

        /*
         * The registry already knows every command and its options, so the completion data is
         * generated from the same source as the help. A command whose prerequisites are missing is
         * left out, because completing it would only lead to an error message.
         */
        $commands = array();
        $optionsPerCommand = array();

        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if ($task['unavailableReason'] !== null) {
                continue;
            }

            $commands[] = $name;

            $names = array();
            foreach ($task['options'] as $option) {
                $names[] = '--' . $option['name'];
            }
            $optionsPerCommand[$name] = $names;
        }

        $globalOptions = array();
        foreach (CliApplication::globalOptionNames() as $name) {
            $globalOptions[] = '--' . $name;
        }

        CliApplication::writeOutput(
            $shell === 'bash'
                ? self::bashCompletion($commands, $optionsPerCommand, $globalOptions)
                : self::zshCompletion($commands, $optionsPerCommand, $globalOptions),
            $options
        );

        return CliApplication::EXIT_SUCCESS;
    }

    /**
     * @param array<int,string> $commands
     * @param array<string,array<int,string>> $optionsPerCommand
     * @param array<int,string> $globalOptions
     */
    private static function bashCompletion(
        array $commands,
        array $optionsPerCommand,
        array $globalOptions
    ): string {
        $cases = '';
        foreach ($optionsPerCommand as $command => $names) {
            if ($names === array()) {
                continue;
            }
            $cases .= '        ' . $command . ') opts="' . implode(' ', $names) . '" ;;' . PHP_EOL;
        }

        return '# Bash completion for the Admidio command line.'
            . PHP_EOL . '# Generated by "admidio completion bash"; regenerate after adding commands.'
            . PHP_EOL . '_admidio() {' . PHP_EOL
            . '    local cur prev commands global opts command i' . PHP_EOL
            . '    cur="${COMP_WORDS[COMP_CWORD]}"' . PHP_EOL
            . '    commands="' . implode(' ', $commands) . '"' . PHP_EOL
            . '    global="' . implode(' ', $globalOptions) . '"' . PHP_EOL
            . PHP_EOL
            . '    command=""' . PHP_EOL
            . '    for ((i = 1; i < COMP_CWORD; i++)); do' . PHP_EOL
            . '        case "${COMP_WORDS[i]}" in' . PHP_EOL
            . '            -*) ;;' . PHP_EOL
            . '            *) command="${COMP_WORDS[i]}"; break ;;' . PHP_EOL
            . '        esac' . PHP_EOL
            . '    done' . PHP_EOL
            . PHP_EOL
            . '    if [ -z "$command" ]; then' . PHP_EOL
            . '        COMPREPLY=($(compgen -W "$commands $global" -- "$cur"))' . PHP_EOL
            . '        return 0' . PHP_EOL
            . '    fi' . PHP_EOL
            . PHP_EOL
            . '    opts=""' . PHP_EOL
            . '    case "$command" in' . PHP_EOL
            . $cases
            . '    esac' . PHP_EOL
            . PHP_EOL
            . '    COMPREPLY=($(compgen -W "$opts $global" -- "$cur"))' . PHP_EOL
            . '}' . PHP_EOL
            . 'complete -F _admidio admidio' . PHP_EOL
            . 'complete -F _admidio ./admidio' . PHP_EOL;
    }

    /**
     * @param array<int,string> $commands
     * @param array<string,array<int,string>> $optionsPerCommand
     * @param array<int,string> $globalOptions
     */
    private static function zshCompletion(
        array $commands,
        array $optionsPerCommand,
        array $globalOptions
    ): string {
        $descriptions = '';
        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if ($task['unavailableReason'] !== null) {
                continue;
            }
            $description = str_replace(array("'", "\r", "\n", ':'), array('', '', ' ', ' '), $task['description']);

            /*
             * _describe splits a completion on the first unescaped colon, and every Admidio command
             * name contains one, so the colon of the name has to be escaped.
             */
            $descriptions .= "        '" . str_replace(':', '\\:', $name) . ':' . $description . "'" . PHP_EOL;
        }

        $cases = '';
        foreach ($optionsPerCommand as $command => $names) {
            if ($names === array()) {
                continue;
            }
            $cases .= '            ' . $command . ') opts=(' . implode(' ', $names) . ') ;;' . PHP_EOL;
        }

        return '#compdef admidio'
            . PHP_EOL . '# Zsh completion for the Admidio command line.'
            . PHP_EOL . '# Generated by "admidio completion zsh"; regenerate after adding commands.'
            . PHP_EOL . '_admidio() {' . PHP_EOL
            . '    local -a commands opts global' . PHP_EOL
            . '    commands=(' . PHP_EOL . $descriptions . '    )' . PHP_EOL
            . '    global=(' . implode(' ', $globalOptions) . ')' . PHP_EOL
            . PHP_EOL
            . '    if (( CURRENT == 2 )); then' . PHP_EOL
            . '        _describe -t commands "admidio command" commands' . PHP_EOL
            . '        return' . PHP_EOL
            . '    fi' . PHP_EOL
            . PHP_EOL
            . '    opts=()' . PHP_EOL
            . '    case "${words[2]}" in' . PHP_EOL
            . $cases
            . '    esac' . PHP_EOL
            . PHP_EOL
            . '    _describe -t options "option" opts' . PHP_EOL
            . '    _describe -t options "global option" global' . PHP_EOL
            . '}' . PHP_EOL
            . '_admidio "$@"' . PHP_EOL;
    }

    public static function version(array $arguments, array $options): int
    {
        [$systemComponent, $databaseError] = self::tryReadCoreComponent();

        $data = array(
            'filesystem' => ADMIDIO_VERSION_TEXT,
            'database' => $systemComponent instanceof Component
                ? self::componentVersion($systemComponent)
                : null
        );
        if ($databaseError !== null) {
            $data['database_error'] = $databaseError;
        }

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            $output = 'Admidio ' . $data['filesystem'] . PHP_EOL;
            if ($data['database'] !== null) {
                $output .= 'Database core ' . $data['database'] . PHP_EOL;
            } elseif ($databaseError !== null) {
                $output .= 'Database core unavailable: ' . $databaseError . PHP_EOL;
            }
            CliApplication::writeOutput($output, $options);
        } else {
            CliApplication::writeValue($data, $options, $format);
        }

        return CliApplication::EXIT_SUCCESS;
    }

    public static function status(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrganization, $g_organization;

        [$systemComponent, $databaseError] = self::tryReadCoreComponent();

        $data = array(
            'organization' => null,
            'filesystem_version' => ADMIDIO_VERSION_TEXT,
            'database_version' => null,
            'database_update_step' => null,
            'status' => 'not-configured'
        );

        if ($systemComponent instanceof Component) {
            try {
                if (!isset($gCurrentOrganization) || !$gCurrentOrganization instanceof Organization) {
                    $gCurrentOrganization = Organization::createDefaultOrganizationObject(
                        $gDb,
                        isset($g_organization) ? (string)$g_organization : ''
                    );
                }

                if ((int)$gCurrentOrganization->getValue('org_id') === 0) {
                    throw new RuntimeException('The configured Admidio organization could not be found.');
                }

                $databaseVersion = self::componentVersion($systemComponent);
                $filesystemVersion = ADMIDIO_VERSION
                    . (ADMIDIO_VERSION_BETA > 0 ? '-Beta.' . ADMIDIO_VERSION_BETA : '');
                $updateCompleted = $systemComponent->getValue('com_update_completed');
                $updateStep = (int)$systemComponent->getValue('com_update_step');

                if ($updateCompleted === '' || $updateCompleted === null) {
                    $state = 'update-required';
                } elseif ($updateCompleted !== true && $updateCompleted !== 1 && $updateCompleted !== '1') {
                    $state = 'update-incomplete';
                } else {
                    $comparison = version_compare($databaseVersion, $filesystemVersion);
                    $state = $comparison < 0 ? 'update-required'
                        : ($comparison > 0 ? 'filesystem-older-than-database' : 'ok');
                }

                $data['organization'] = (string)$gCurrentOrganization->getValue('org_shortname');
                $data['database_version'] = $databaseVersion;
                $data['database_update_step'] = $updateStep;
                $data['status'] = $state;
            } catch (\Throwable $exception) {
                $databaseError = $exception->getMessage();
                $data['status'] = 'organization-unavailable';
            }
        } elseif (is_file(self::installationConfigPath())) {
            $data['status'] = 'database-unavailable';
        }

        if ($databaseError !== null) {
            $data['detail'] = $databaseError;
        }

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            $output = 'Organization: ' . ($data['organization'] ?? '-') . PHP_EOL
                . 'Filesystem:   ' . $data['filesystem_version'] . PHP_EOL
                . 'Database:     ' . ($data['database_version'] ?? '-') . PHP_EOL
                . 'Update step:  ' . ($data['database_update_step'] ?? '-') . PHP_EOL
                . 'Status:       ' . strtoupper(str_replace('-', ' ', $data['status'])) . PHP_EOL;
            if ($databaseError !== null) {
                $output .= 'Detail:       ' . $databaseError . PHP_EOL;
            }
            CliApplication::writeOutput($output, $options);
        } else {
            CliApplication::writeValue($data, $options, $format);
        }

        return $data['status'] === 'ok'
            ? CliApplication::EXIT_SUCCESS
            : CliApplication::EXIT_STATE_NOT_OK;
    }

    /**
     * Read the installed CORE component without making version/status depend on the full CLI bootstrap.
     *
     * @return array{0:?Component,1:?string}
     */
    private static function tryReadCoreComponent(): array
    {
        global $gDb, $gSystemComponent;

        if (isset($gSystemComponent)
            && $gSystemComponent instanceof Component
            && (int)$gSystemComponent->getValue('com_id') > 0) {
            return array($gSystemComponent, null);
        }

        if (!is_file(self::installationConfigPath())) {
            return array(null, 'Admidio configuration file adm_my_files/config.php was not found.');
        }

        try {
            if (!isset($gDb) || !$gDb instanceof Database) {
                $gDb = Database::createDatabaseInstance();
            }

            $gSystemComponent = new Component($gDb);
            $gSystemComponent->readDataByColumns(array(
                'com_type' => 'SYSTEM',
                'com_name_intern' => 'CORE'
            ));

            if ((int)$gSystemComponent->getValue('com_id') === 0) {
                return array(null, 'The Admidio database is not installed.');
            }

            return array($gSystemComponent, null);
        } catch (\Throwable $exception) {
            return array(null, $exception->getMessage());
        }
    }

    /**
     * Path of the configuration file that describes the database of this Admidio installation.
     */
    private static function installationConfigPath(): string
    {
        // the CLI bootstrap resolves --config, so install:run writes where the next call reads
        if (defined('ADMIDIO_CONFIG_FILE')) {
            return ADMIDIO_CONFIG_FILE;
        }

        return ADMIDIO_PATH . FOLDER_DATA . '/config.php';
    }

    /**
     * Build the input of a new installation out of the options of install:check and install:run.
     *
     * Admidio derives its table names, its time zone and its URL from the configuration file while
     * it is bootstrapping, therefore long before this command runs. The corresponding options were
     * already read by the CLI bootstrap, and the values of the bootstrap are the defaults here. A
     * difference between both means that the option cannot take effect, so it is reported instead
     * of silently installing something else.
     *
     * @param array<string,mixed> $options
     * @throws Exception
     */
    private static function installationConfig(array $options): InstallationConfig
    {
        global $gL10n;

        $configFileExists = is_file(self::installationConfigPath());

        $config = InstallationConfig::fromArray(array(
            'dbType' => CliApplication::optionString($options, 'db-type', $configFileExists ? DB_TYPE : ''),
            'dbHost' => CliApplication::optionString($options, 'db-host', $configFileExists ? (string) DB_HOST : ''),
            'dbPort' => CliApplication::optionString($options, 'db-port', $configFileExists && DB_PORT !== null ? (string) DB_PORT : ''),
            'dbName' => CliApplication::optionString($options, 'db-name', $configFileExists ? (string) DB_NAME : ''),
            'dbUsername' => CliApplication::optionString($options, 'db-user', $configFileExists ? (string) DB_USERNAME : ''),
            'dbPassword' => self::installationSecret($options, 'db-password', $configFileExists ? (string) DB_PASSWORD : ''),
            'tablePrefix' => CliApplication::optionString($options, 'table-prefix', TABLE_PREFIX),
            'rootUrl' => CliApplication::optionString($options, 'root-url', $configFileExists ? ADMIDIO_URL : ''),
            'language' => CliApplication::optionString($options, 'language', $gL10n->getLanguage()),
            'timezone' => CliApplication::optionString($options, 'timezone', date_default_timezone_get()),
            'organizationShortName' => CliApplication::optionString($options, 'organization-shortname'),
            'organizationName' => CliApplication::optionString($options, 'organization-name'),
            'organizationEmail' => CliApplication::optionString($options, 'organization-email'),
            'adminLogin' => CliApplication::optionString($options, 'admin-login'),
            'adminFirstName' => CliApplication::optionString($options, 'admin-first-name'),
            'adminLastName' => CliApplication::optionString($options, 'admin-last-name'),
            'adminEmail' => CliApplication::optionString($options, 'admin-email'),
            'adminPassword' => self::installationSecret($options, 'admin-password', '')
        ));

        if ($configFileExists) {
            /*
             * The site reads its database out of the configuration file, so an installation into a
             * different database would leave a site that cannot reach its own data.
             */
            self::assertConfigFileValue('db-type', $config->dbType, DB_TYPE);
            self::assertConfigFileValue('db-host', $config->dbHost, (string) DB_HOST);
            self::assertConfigFileValue('db-port', (string) $config->dbPort, (string) InstallationConfig::normalizePort(DB_PORT));
            self::assertConfigFileValue('db-name', $config->dbName, (string) DB_NAME);
            self::assertConfigFileValue('db-user', $config->dbUsername, (string) DB_USERNAME);
            self::assertConfigFileValue('db-password', $config->dbPassword, (string) DB_PASSWORD, false);
            self::assertConfigFileValue('table-prefix', $config->tablePrefix, TABLE_PREFIX);
            self::assertConfigFileValue('timezone', $config->timezone, date_default_timezone_get());
            self::assertConfigFileValue('root-url', $config->rootUrl, rtrim(ADMIDIO_URL, '/'));

            return $config;
        }

        if (CliApplication::optionString($options, 'root-url') === '') {
            throw new InvalidArgumentException(
                'Missing required option --root-url. Without ' . FOLDER_DATA
                . '/config.php the URL of the new installation is not known.'
            );
        }

        self::assertBootstrapValue('db-type', $config->dbType, DB_TYPE);
        self::assertBootstrapValue('table-prefix', $config->tablePrefix, TABLE_PREFIX);
        self::assertBootstrapValue('timezone', $config->timezone, date_default_timezone_get());
        self::assertBootstrapValue('root-url', $config->rootUrl, rtrim(ADMIDIO_URL, '/'));

        return $config;
    }

    /**
     * Read a password of the installation, either from its option or from a line of STDIN.
     *
     * @param array<string,mixed> $options
     */
    private static function installationSecret(array $options, string $name, string $default): string
    {
        $secret = CliApplication::readSecret($options, $name, $name . '-stdin');

        return $secret === '' ? $default : $secret;
    }

    /**
     * Check an option of the installation against the value that the configuration file defines.
     *
     * @param bool $showValues Set to false for a secret, whose values may not be printed.
     * @throws InvalidArgumentException
     */
    private static function assertConfigFileValue(
        string $option,
        string $value,
        string $configFileValue,
        bool $showValues = true
    ): void {
        if ($value === $configFileValue) {
            return;
        }

        throw new InvalidArgumentException(
            '--' . $option . ($showValues ? ' is "' . $value . '" but ' : ' does not match ')
            . FOLDER_DATA . '/config.php' . ($showValues ? ' defines "' . $configFileValue . '"' : '')
            . '. An existing configuration file defines the database of this installation, so either '
            . 'remove the option or the configuration file.'
        );
    }

    /**
     * Check an option of the installation against the value that Admidio was started with.
     *
     * The constants that are derived from these options exist before a command is dispatched, so the
     * CLI bootstrap reads them itself. It only understands the forms "--option value" and
     * "--option=value" outside of the "--" terminator.
     *
     * @throws RuntimeException
     */
    private static function assertBootstrapValue(string $option, string $value, string $bootstrapValue): void
    {
        if ($value === $bootstrapValue) {
            return;
        }

        throw new RuntimeException(
            '--' . $option . ' was not readable before Admidio started, which is why "' . $bootstrapValue
            . '" was used instead of "' . $value . '". Write the option as --' . $option . '=VALUE.'
        );
    }

    /**
     * Report the values of an installation that a command has established.
     *
     * @param array<string,mixed> $options
     * @param array<string,mixed> $result
     */
    private static function writeInstallationValues(InstallationConfig $config, array $result, array $options): void
    {
        CliApplication::writeValue(
            array_merge(
                array(
                    'config_file' => self::installationConfigPath(),
                    'database_type' => $config->dbType,
                    'database_host' => $config->dbHost,
                    'database_name' => $config->dbName,
                    'table_prefix' => $config->tablePrefix,
                    'root_url' => $config->rootUrl,
                    'organization' => $config->organizationShortName,
                    'administrator' => $config->adminLogin
                ),
                $result
            ),
            $options,
            CliApplication::optionString($options, 'format', 'record')
        );
    }

    public static function installCheck(array $arguments, array $options): int
    {
        $config = self::installationConfig($options);
        $configFileExists = is_file(self::installationConfigPath());

        Installation::validateConfiguration($config);

        /*
         * The command may not change anything, so the folders that the installation needs are only
         * checked for the permission that would let install:run create them.
         */
        if (!is_dir(ADMIDIO_PATH . FOLDER_DATA) || !is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
            throw new RuntimeException(FOLDER_DATA . ' has to exist and it has to be writable.');
        }

        $db = Installation::connectDatabase($config);

        self::writeInstallationValues(
            $config,
            array(
                'config_file_state' => $configFileExists ? 'exists' : 'will be created',
                'database_version' => $db->getName() . ' ' . $db->getVersion(),
                'installable' => true
            ),
            $options
        );

        return CliApplication::EXIT_SUCCESS;
    }

    public static function installRun(array $arguments, array $options): int
    {
        $config = self::installationConfig($options);
        $configFileExists = is_file(self::installationConfigPath());

        Installation::validateConfiguration($config);
        Installation::checkFolderPermissions();

        // everything is checked before the first change, so a rejected installation leaves no traces
        $db = Installation::connectDatabase($config);

        CliApplication::confirm(
            'Install Admidio for the organization "' . $config->organizationShortName . '" into the database "'
            . $config->dbName . '" of ' . $config->dbHost . '?',
            $options
        );

        if (!$configFileExists) {
            Installation::writeConfigFile($config, self::installationConfigPath());
        }

        $result = Installation::install($db, $config);

        self::writeInstallationValues(
            $config,
            array(
                'organization_id' => $result['organizationId'],
                'administrator_id' => $result['administratorId'],
                'installed' => true
            ),
            $options
        );

        return CliApplication::EXIT_SUCCESS;
    }

    public static function updateCheck(array $arguments, array $options): int
    {
        $service = new PreferencesService();
        if (!method_exists($service, 'getUpdateInformation')) {
            throw new RuntimeException(
                'PreferencesService::getUpdateInformation() is required by the CLI update check.'
            );
        }

        /** @var array<string,mixed> $data */
        $data = $service->getUpdateInformation();
        $versionUpdate = (int) $data['versionUpdate'];

        /*
         * 99 does not describe a version but a failed request. The command could not determine
         * anything, so it must not report a result.
         */
        if ($versionUpdate === 99) {
            throw new RuntimeException(
                'The Admidio update information could not be read from ' . ADMIDIO_HOMEPAGE . 'update.txt.'
            );
        }

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            // a version that the update server doesn't provide is displayed as "n/a"
            CliApplication::writeOutput(
                'Stable version: ' . ($data['stableVersion'] !== '' ? $data['stableVersion'] : 'n/a') . PHP_EOL
                . 'Beta version:   ' . ($data['betaVersion'] !== ''
                    ? $data['betaVersion'] . ($data['betaRelease'] !== '' ? '-Beta.' . $data['betaRelease'] : '')
                    : 'n/a') . PHP_EOL
                . 'Update state:   ' . $data['versionUpdate'] . PHP_EOL,
                $options
            );
        } else {
            CliApplication::writeValue($data, $options, $format);
        }

        // 0 = up to date, 1 = new stable version, 2 = new beta version, 3 = both
        return $versionUpdate === 0
            ? CliApplication::EXIT_SUCCESS
            : CliApplication::EXIT_UPDATE_AVAILABLE;
    }

    public static function systemInfo(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrganization;

        $data = array(
            'admidio' => ADMIDIO_VERSION_TEXT,
            'php' => PHP_VERSION,
            'database_engine' => $gDb->getName(),
            'database_version' => $gDb->getVersion(),
            'operating_system' => SystemInfoUtils::getOS(),
            'uname' => SystemInfoUtils::getUname(),
            'architecture' => SystemInfoUtils::is64Bit() ? '64-bit' : '32-bit',
            'organization' => $gCurrentOrganization->getValue('org_shortname')
        );

        CliApplication::writeValue(
            $data,
            $options,
            CliApplication::optionString($options, 'format', 'text')
        );

        return 0;
    }

    public static function phpInfo(array $arguments, array $options): int
    {
        /*
         * INFO_ENVIRONMENT and INFO_VARIABLES are deliberately omitted. In a CLI process they list
         * $_ENV and $_SERVER, which in a containerized deployment routinely carry the database
         * credentials of the installation.
         */
        ob_start();
        phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
        $content = ob_get_clean();
        if ($content === false) {
            throw new RuntimeException('Could not collect PHP information.');
        }

        CliApplication::writeOutput($content, $options);
        return 0;
    }

    public static function databaseBackup(array $arguments, array $options): int
    {
        global $gDb;

        $filename = 'admidio-' . date('Ymd-His') . '.sql.gz';
        $dump = new DatabaseDump($gDb);
        $dump->create($filename);

        // A dump contains every table of the installation, so restrict it to the invoking user.
        self::moveGeneratedFile(
            ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $filename,
            $filename,
            $options,
            true
        );
        return 0;
    }

    public static function emailTest(array $arguments, array $options): int
    {
        $service = new PreferencesService();

        if (!$service->sendTestEmail()) {
            throw new RuntimeException('The test email could not be sent.');
        }

        CliApplication::writeSuccess('Test email sent.', $options);
        return 0;
    }

    public static function htaccessStatus(array $arguments, array $options): int
    {
        $protected = is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess');
        CliApplication::writeValue(
            array('protected' => $protected, 'file' => ADMIDIO_PATH . FOLDER_DATA . '/.htaccess'),
            $options,
            CliApplication::optionString($options, 'format', 'text')
        );

        return $protected ? CliApplication::EXIT_SUCCESS : CliApplication::EXIT_STATE_NOT_OK;
    }

    public static function htaccessEnable(array $arguments, array $options): int
    {
        $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
        if (!$htaccess->protectFolder()) {
            throw new RuntimeException('Could not protect adm_my_files with .htaccess.');
        }

        CliApplication::writeSuccess('adm_my_files protection enabled.', $options);
        return 0;
    }

    public static function htaccessDisable(array $arguments, array $options): int
    {
        CliApplication::confirm('Remove .htaccess protection from adm_my_files?', $options);

        $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
        if (!$htaccess->unprotectFolder()) {
            throw new RuntimeException('Could not remove adm_my_files .htaccess protection.');
        }

        CliApplication::writeSuccess('adm_my_files protection disabled.', $options);
        return 0;
    }

    public static function repairCategories(array $arguments, array $options): int
    {
        global $gDb;

        CliApplication::confirm('Reorganize category sequences?', $options);
        (new Maintenance($gDb))->reorganizeCategories();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Category repair completed.', $options);
        return 0;
    }

    public static function repairDocuments(array $arguments, array $options): int
    {
        global $gDb;

        CliApplication::confirm('Repair document folder paths?', $options);
        (new Maintenance($gDb))->repairDocumentsFilesPath();
        CliApplication::writeSuccess('Document path repair completed.', $options);
        return 0;
    }

    public static function maintenanceMode(array $arguments, array $options): int
    {
        $mode = isset($arguments[0]) ? strtolower((string)$arguments[0]) : 'status';

        if (!in_array($mode, array('enable', 'disable', 'status'), true)) {
            throw new InvalidArgumentException('MODE expects one of: enable, disable, status.');
        }

        if ($mode === 'enable') {
            if (CliApplication::optionBool($options, 'force', false)) {
                throw new InvalidArgumentException('--force is only valid with maintenance:mode disable.');
            }

            CliApplication::confirm('Enable maintenance mode?', $options);

            MaintenanceMode::enable(
                CliApplication::optionString($options, 'title'),
                CliApplication::optionString($options, 'message'),
                CliApplication::optionValues($options, 'allow-script'),
                CliApplication::optionInt($options, 'retry-after', 120) ?? 120,
                CliApplication::optionString($options, 'owner', 'cli')
            );
        } elseif ($mode === 'disable') {
            foreach (array('title', 'message', 'retry-after', 'allow-script') as $optionName) {
                if (CliApplication::optionExists($options, $optionName)) {
                    throw new InvalidArgumentException(
                        '--' . $optionName . ' is only valid with maintenance:mode enable.'
                    );
                }
            }

            if (CliApplication::optionBool($options, 'force', false)) {
                MaintenanceMode::disable();
            } else {
                MaintenanceMode::disable(CliApplication::optionString($options, 'owner', 'cli'));
            }
        } else {
            foreach (array('title', 'message', 'retry-after', 'allow-script', 'owner', 'force') as $optionName) {
                if (CliApplication::optionExists($options, $optionName)) {
                    throw new InvalidArgumentException(
                        '--' . $optionName . ' is not valid with maintenance:mode status.'
                    );
                }
            }
        }

        $state = MaintenanceMode::getState();
        $output = array('enabled' => $state !== null);

        if ($state !== null) {
            $output += $state;
        }

        CliApplication::writeValue($output, $options, 'record');
        return 0;
    }

    public static function configList(array $arguments, array $options): int
    {
        global $gSettingsManager;

        $settings = $gSettingsManager->getAll(true);
        ksort($settings);
        $filter = CliApplication::optionString($options, 'filter');
        $rows = array();

        foreach ($settings as $name => $value) {
            if ($filter !== '' && stripos((string)$name, $filter) === false) {
                continue;
            }
            $rows[] = array('name' => $name, 'value' => $value);
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function configGet(array $arguments, array $options): int
    {
        global $gSettingsManager;

        $name = CliApplication::requireArgument($arguments, 0, 'name');
        $type = CliApplication::optionString($options, 'type', 'raw');

        $value = match ($type) {
            'string' => $gSettingsManager->getString($name),
            'int' => $gSettingsManager->getInt($name),
            'float' => $gSettingsManager->getFloat($name),
            'bool' => $gSettingsManager->getBool($name),
            default => $gSettingsManager->get($name, true)
        };

        CliApplication::writeValue($value, $options);
        return 0;
    }

    public static function configSet(array $arguments, array $options): int
    {
        global $gSettingsManager;

        $name = CliApplication::requireArgument($arguments, 0, 'name');
        $value = CliApplication::requireArgument($arguments, 1, 'value');

        $gSettingsManager->set($name, $value);
        self::reloadAllSessions();

        CliApplication::writeSuccess('Updated preference ' . $name . '.', $options);
        return 0;
    }

    public static function configDelete(array $arguments, array $options): int
    {
        global $gSettingsManager;

        $name = CliApplication::requireArgument($arguments, 0, 'name');
        CliApplication::confirm('Delete preference "' . $name . '"?', $options);
        $gSettingsManager->del($name);
        self::reloadAllSessions();

        CliApplication::writeSuccess('Deleted preference ' . $name . '.', $options);
        return 0;
    }

    public static function organizationList(array $arguments, array $options): int
    {
        global $gDb;

        $rows = $gDb->queryPrepared(
            'SELECT org_id AS id, org_uuid AS uuid, org_shortname AS short_name,
                    org_longname AS name, org_org_id_parent AS parent_id,
                    org_homepage AS homepage, org_email_administrator AS email
               FROM ' . TBL_ORGANIZATIONS . '
           ORDER BY org_longname'
        )->fetchAll();

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function organizationShow(array $arguments, array $options): int
    {
        $organization = self::resolveOrganization(CliApplication::requireArgument($arguments, 0, 'org'));

        $data = array(
            'id' => (int)$organization->getValue('org_id'),
            'uuid' => (string)$organization->getValue('org_uuid'),
            'short_name' => (string)$organization->getValue('org_shortname'),
            'name' => (string)$organization->getValue('org_longname'),
            'parent_id' => (int)$organization->getValue('org_org_id_parent'),
            'homepage' => (string)$organization->getValue('org_homepage'),
            'email' => (string)$organization->getValue('org_email_administrator'),
            'show_organization_select' => (bool)$organization->getValue('org_show_org_select')
        );

        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function organizationAdd(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUserId, $gSettingsManager, $gCurrentOrganization;

        $shortName = CliApplication::optionString($options, 'short-name');
        $longName = CliApplication::optionString($options, 'name');
        $email = CliApplication::optionString($options, 'email');

        if (!StringUtils::strValidCharacters($shortName, 'noSpecialChar')) {
            throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
        }

        $existing = new Organization($gDb, $shortName);
        if ((int)$existing->getValue('org_id') > 0) {
            throw new Exception('INS_ORGA_SHORTNAME_EXISTS', array($shortName));
        }

        $parentId = $gCurrentOrgId;
        if (CliApplication::optionExists($options, 'parent')) {
            $parentId = (int)self::resolveOrganization(
                CliApplication::optionString($options, 'parent')
            )->getValue('org_id');
        }

        $gDb->startTransaction();

        try {
            $organization = new Organization($gDb);
            $organization->setValue('org_shortname', $shortName);
            $organization->setValue('org_longname', $longName);
            $organization->setValue(
                'org_homepage',
                CliApplication::optionString($options, 'homepage', ADMIDIO_URL)
            );
            $organization->setValue('org_email_administrator', $email);
            $organization->setValue(
                'org_show_org_select',
                (int)(CliApplication::optionBool($options, 'show-organization-select', true) ?? true)
            );
            $organization->setValue('org_org_id_parent', $parentId);
            $organization->save();

            Entity::setLoggingEnabled(false);
            require ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php';
            $defaultOrgPreferences['system_language'] = $gSettingsManager->getString('system_language');

            $newSettingsManager =& $organization->getSettingsManager();
            $newSettingsManager->setMulti($defaultOrgPreferences, false);
            $organization->createBasicData($gCurrentUserId);
            Entity::setLoggingEnabled(true);

            /*
             * The preference belongs to the parent: it decides whether its suborganizations
             * reuse its members. It is therefore written on the parent, not on the new
             * organization.
             */
            if (CliApplication::optionExists($options, 'share-members')) {
                $parent = new Organization($gDb, $parentId);
                $parentSettingsManager =& $parent->getSettingsManager();
                $parentSettingsManager->set(
                    'contacts_suborganization_use_same_members',
                    (int)(CliApplication::optionBool($options, 'share-members', false) ?? false)
                );
            }

            if ($gCurrentOrganization->countAllRecords() === 2) {
                $gCurrentOrganization->setValue('org_show_org_select', true);
                $gCurrentOrganization->save();
            }

            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            Entity::setLoggingEnabled(true);
            $gDb->rollback();
            throw $exception;
        }

        self::reloadAllSessions();
        CliApplication::writeSuccess(
            'Created organization ' . $organization->getValue('org_uuid') . '.',
            $options
        );
        return 0;
    }

    public static function organizationUpdate(array $arguments, array $options): int
    {
        global $gCurrentOrgId, $gCurrentOrganization, $gSettingsManager;

        $organization = self::resolveOrganization(CliApplication::requireArgument($arguments, 0, 'org'));
        if ((int)$organization->getValue('org_id') !== $gCurrentOrgId) {
            throw new RuntimeException(
                'Current master only edits the current organization. Select it with --organization before updating.'
            );
        }

        $mapping = array(
            'name' => 'org_longname',
            'email' => 'org_email_administrator',
            'homepage' => 'org_homepage'
        );
        foreach ($mapping as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $organization->setValue($column, CliApplication::optionString($options, $option));
            }
        }

        if (CliApplication::optionExists($options, 'short-name')
            && CliApplication::optionString($options, 'short-name') !== (string)$organization->getValue('org_shortname')) {
            throw new RuntimeException('Current OrganizationService does not permit changing org_shortname.');
        }

        if (CliApplication::optionExists($options, 'parent')) {
            $parent = self::resolveOrganization(CliApplication::optionString($options, 'parent'));
            $organization->setValue('org_org_id_parent', (int)$parent->getValue('org_id'));
        }

        if (CliApplication::optionExists($options, 'show-organization-select')) {
            $organization->setValue(
                'org_show_org_select',
                (int)(CliApplication::optionBool($options, 'show-organization-select', false) ?? false)
            );
        }

        $organization->save();

        if (CliApplication::optionExists($options, 'share-members')
            && !$gCurrentOrganization->isChildOrganization()
            && $gCurrentOrganization->isParentOrganization()) {
            $gSettingsManager->set(
                'contacts_suborganization_use_same_members',
                (int)(CliApplication::optionBool($options, 'share-members', false) ?? false)
            );
        }

        self::reloadAllSessions();
        CliApplication::writeSuccess('Updated organization.', $options);
        return 0;
    }

    public static function organizationDelete(array $arguments, array $options): int
    {
        global $gCurrentOrgId;

        $organization = self::resolveOrganization(CliApplication::requireArgument($arguments, 0, 'org'));
        if ((int)$organization->getValue('org_org_id_parent') !== $gCurrentOrgId) {
            throw new RuntimeException(
                'Only a direct suborganization of the current organization can be deleted, matching modules/organizations.php.'
            );
        }

        CliApplication::confirm(
            'Delete organization "' . $organization->getValue('org_longname') . '" and its organization data?',
            $options
        );

        $organization->delete();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Deleted organization.', $options);
        return 0;
    }

    public static function userList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gProfileFields, $gCurrentUser;

        // Same rule as modules/contacts/contacts.php.
        if (!$gCurrentUser->isAdministratorUsers() && !$gCurrentUser->isAllowedToViewUsers()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $queryParams = array($gCurrentOrgId);
        $membershipCondition = CliApplication::optionBool($options, 'former', false)
            ? ''
            : ' AND mem_begin <= ? AND mem_end > ?';
        if ($membershipCondition !== '') {
            $queryParams[] = DATE_NOW;
            $queryParams[] = DATE_NOW;
        }

        $groupCondition = '';
        if (CliApplication::optionExists($options, 'group')) {
            $role = self::resolveGroup(CliApplication::optionString($options, 'group'));
            $groupCondition = ' AND rol_id = ?';
            $queryParams[] = (int)$role->getValue('rol_id');
        }

        $searchCondition = '';
        $search = CliApplication::optionString($options, 'search');
        if ($search !== '') {
            $searchCondition = ' AND (
                    UPPER(usr_login_name) LIKE UPPER(?)
                 OR UPPER(last_name.usd_value) LIKE UPPER(?)
                 OR UPPER(first_name.usd_value) LIKE UPPER(?)
               )';
            $needle = '%' . $search . '%';
            $queryParams[] = $needle;
            $queryParams[] = $needle;
            $queryParams[] = $needle;
        }

        $limit = max(0, CliApplication::optionInt($options, 'limit', 0) ?? 0);
        $offset = max(0, CliApplication::optionInt($options, 'offset', 0) ?? 0);

        $sql = 'SELECT DISTINCT usr_id AS id, usr_uuid AS uuid, usr_login_name AS login,
                       first_name.usd_value AS first_name, last_name.usd_value AS last_name
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_MEMBERS . ' ON mem_usr_id = usr_id
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
             LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ?
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ?
                 WHERE rol_valid = true
                   AND (cat_org_id = ? OR cat_org_id IS NULL)'
            . $membershipCondition . $groupCondition . $searchCondition
            . ' ORDER BY last_name.usd_value, first_name.usd_value, usr_login_name';

        $queryParams = array_merge(
            array(
                (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id')
            ),
            $queryParams
        );

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        $rows = $gDb->queryPrepared($sql, $queryParams)->fetchAll();
        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );

        return 0;
    }

    public static function userShow(array $arguments, array $options): int
    {
        global $gDb, $gProfileFields, $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gSettingsManager;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$gCurrentUser->hasRightViewProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $data = array(
            'id' => (int)$user->getValue('usr_id'),
            'uuid' => (string)$user->getValue('usr_uuid'),
            'login' => (string)$user->getValue('usr_login_name'),
            'valid' => (bool)$user->getValue('usr_valid'),
            'profile' => array()
        );

        /*
         * The profile page applies this predicate to every single profile field. The broad
         * hasRightViewProfile() check above only decides whether the profile itself may be opened.
         */
        foreach ($gProfileFields->getProfileFields() as $field) {
            $nameIntern = (string)$field->getValue('usf_name_intern');
            if ($gCurrentUser->allowedViewProfileField($user, $nameIntern)) {
                $data['profile'][$nameIntern] = $user->getValue($nameIntern, 'database');
            }
        }

        if (CliApplication::optionBool($options, 'memberships', false)) {
            $memberships = $gDb->queryPrepared(
                'SELECT mem_uuid AS uuid, mem_rol_id AS role_id, rol_uuid AS role_uuid, rol_name AS role,
                        mem_begin AS begin, mem_end AS end, mem_leader AS leader
                   FROM ' . TBL_MEMBERS . '
             INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE mem_usr_id = ?
                    AND rol_valid = true
                    AND cat_name_intern <> \'EVENTS\'
                    AND (cat_org_id = ? OR cat_org_id IS NULL)
               ORDER BY mem_begin DESC, rol_name',
                array((int)$user->getValue('usr_id'), $gCurrentOrgId)
            )->fetchAll();

            $data['memberships'] = array();
            foreach ($memberships as $membership) {
                // mem_end is exclusive, so a membership that ends today is already a former one.
                $isFormer = strcmp((string)$membership['end'], DATE_NOW) <= 0;
                if (($isFormer && !$gSettingsManager->getBool('profile_show_former_roles'))
                    || (!$isFormer && !$gSettingsManager->getBool('profile_show_roles'))) {
                    continue;
                }

                if ($gCurrentUserId !== (int)$user->getValue('usr_id')
                    && !$gCurrentUser->hasRightViewRole((int)$membership['role_id'])) {
                    continue;
                }

                unset($membership['role_id']);
                $data['memberships'][] = $membership;
            }
        }

        if (CliApplication::optionBool($options, 'relations', false)) {
            $data['relations'] = array();

            if ($gSettingsManager->getBool('contacts_user_relations_enabled')) {
                $relations = $gDb->queryPrepared(
                    'SELECT ure_uuid AS uuid, urt_uuid AS type_uuid, urt_name AS type,
                            ure_usr_id2 AS related_user_id
                       FROM ' . TBL_USER_RELATIONS . '
                 INNER JOIN ' . TBL_USER_RELATION_TYPES . ' ON urt_id = ure_urt_id
                      WHERE ure_usr_id1 = ?
                        AND urt_name <> \'\'
                        AND urt_name_male <> \'\'
                        AND urt_name_female <> \'\'
                   ORDER BY urt_name',
                    array((int)$user->getValue('usr_id'))
                )->fetchAll();

                $visibleUsers = array();
                foreach ($relations as $relation) {
                    $otherUser = self::visibleRelationEndpoint((int)$relation['related_user_id'], $visibleUsers);
                    if ($otherUser === null) {
                        continue;
                    }

                    $data['relations'][] = array(
                        'uuid' => (string)$relation['uuid'],
                        'type_uuid' => (string)$relation['type_uuid'],
                        'type' => (string)$relation['type'],
                        'related_user_uuid' => (string)$otherUser->getValue('usr_uuid')
                    );
                }
            }
        }

        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function userAdd(array $arguments, array $options): int
    {
        global $gDb, $gProfileFields, $gCurrentOrgId, $gCurrentUser;

        $user = new User($gDb, $gProfileFields);
        self::applyUserFields($user, $options);

        $login = CliApplication::optionString($options, 'login');
        if ($login !== '') {
            self::assertUniqueLogin($login);
            if (!$user->setValue('usr_login_name', $login)) {
                throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_USERNAME'));
            }
        }

        $password = CliApplication::readSecret($options, 'password', 'password-stdin');
        if ($password !== '') {
            $user->setPassword($password);
        }

        // Check the requested groups before the user record is written.
        $roles = array();
        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($gCurrentUser)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $roles[] = $role;
        }

        /*
         * The user, the default roles and the requested groups belong together. Without a
         * transaction a failing group assignment leaves a contact without any membership behind.
         */
        $gDb->startTransaction();
        try {
            $user->save();
            $userId = (int)$user->getValue('usr_id');

            $defaultRoleCount = (int)$gDb->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE rol_default_registration = true
                    AND cat_org_id = ?',
                array($gCurrentOrgId)
            )->fetchColumn();

            if ($defaultRoleCount > 0) {
                $user->assignDefaultRoles();
            }

            foreach ($roles as $role) {
                $role->startMembership($userId);
            }

            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Created user ' . $user->getValue('usr_uuid') . '.', $options);
        return 0;
    }

    public static function userUpdate(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));

        if (!$gCurrentUser->hasRightEditProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if (CliApplication::optionExists($options, 'login')) {
            $login = CliApplication::optionString($options, 'login');
            if ($login !== (string)$user->getValue('usr_login_name')) {
                self::assertUniqueLogin($login, (int)$user->getValue('usr_id'));
                if (!$user->setValue('usr_login_name', $login)) {
                    throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_USERNAME'));
                }
            }
        }

        self::applyUserFields($user, $options);
        $user->save();
        self::reloadUserSessions((int)$user->getValue('usr_id'));

        CliApplication::writeSuccess('Updated user ' . $user->getValue('usr_uuid') . '.', $options);
        return 0;
    }

    public static function userCopy(array $arguments, array $options): int
    {
        global $gDb, $gProfileFields, $gCurrentOrgId, $gCurrentUser;

        $source = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));

        // Copying a profile discloses and duplicates all of its data.
        if (!$gCurrentUser->hasRightEditProfile($source)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $copy = new User($gDb, $gProfileFields);

        foreach ($gProfileFields->getProfileFields() as $field) {
            $nameIntern = (string)$field->getValue('usf_name_intern');
            $value = $source->getValue($nameIntern, 'database');
            if ($value !== '' && $value !== null) {
                $copy->setValue($nameIntern, $value);
            }
        }

        if (CliApplication::optionExists($options, 'login')) {
            $login = CliApplication::optionString($options, 'login');
            self::assertUniqueLogin($login);
            $copy->setValue('usr_login_name', $login);
        }

        self::applyUserFields($copy, $options);

        // Check the requested groups before the copy is written.
        $roles = array();
        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($gCurrentUser)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $roles[] = $role;
        }

        $gDb->startTransaction();
        try {
            $copy->save();
            $copyId = (int)$copy->getValue('usr_id');

            // Same default-role handling as user:add, otherwise the copy has no membership at all.
            $defaultRoleCount = (int)$gDb->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE rol_default_registration = true
                    AND cat_org_id = ?',
                array($gCurrentOrgId)
            )->fetchColumn();

            if ($defaultRoleCount > 0) {
                $copy->assignDefaultRoles();
            }

            foreach ($roles as $role) {
                $role->startMembership($copyId);
            }

            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Created user copy ' . $copy->getValue('usr_uuid') . '.', $options);
        return 0;
    }

    public static function userRemove(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUserId, $gCurrentUser;

        CliApplication::confirm('End current organization memberships for the selected user(s)?', $options);

        // Resolve and check every reference before the first membership is ended.
        $users = array();
        foreach ($arguments as $reference) {
            $user = CliApplication::resolveUser($reference);
            if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $users[] = $user;
        }

        $gDb->startTransaction();
        try {
            foreach ($users as $user) {
                $userId = (int)$user->getValue('usr_id');

                $roleIds = $gDb->queryPrepared(
                    'SELECT DISTINCT rol_id
                       FROM ' . TBL_MEMBERS . '
                 INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                 INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                      WHERE mem_usr_id = ?
                        AND mem_begin <= ?
                        AND mem_end > ?
                        AND rol_valid = true
                        AND cat_org_id = ?',
                    array($userId, DATE_NOW, DATE_NOW, $gCurrentOrgId)
                )->fetchAll(PDO::FETCH_COLUMN);

                foreach ($roleIds as $roleId) {
                    $role = new Role($gDb, (int)$roleId);
                    if (!$role->allowedToAssignMembers($gCurrentUser)) {
                        throw new Exception('SYS_NO_RIGHTS');
                    }
                    $role->stopMembership($userId);
                }

                self::reloadUserSessions($userId);
            }

            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Selected user membership(s) ended.', $options);
        return 0;
    }

    public static function userDelete(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser, $gCurrentUserId;

        CliApplication::confirm('Permanently delete the selected user(s)?', $options);

        $anyOrganization = CliApplication::optionBool($options, 'any-organization', false) ?? false;

        // Resolve and check every reference before the first user is deleted.
        $users = array();
        foreach ($arguments as $reference) {
            $user = CliApplication::resolveUser($reference, $anyOrganization);
            $userId = (int)$user->getValue('usr_id');

            if ($userId === $gCurrentUserId) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $otherOrganizationMemberships = (int)$gDb->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_MEMBERS . '
             INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE rol_valid = true
                    AND cat_org_id <> ?
                    AND mem_begin <= ?
                    AND mem_end > ?
                    AND mem_usr_id = ?',
                array($gCurrentOrgId, DATE_NOW, DATE_NOW, $userId)
            )->fetchColumn();

            if ($otherOrganizationMemberships > 0) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $users[] = $user;
        }

        $gDb->startTransaction();
        try {
            foreach ($users as $user) {
                $user->delete();
            }
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Selected user(s) deleted.', $options);
        return 0;
    }

    public static function userExport(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$gCurrentUser->hasRightViewProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::writeOutput($user->getVCard(), $options);
        return 0;
    }

    public static function userSetPassword(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$gCurrentUser->hasRightEditProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $password = CliApplication::readSecret($options, 'password', 'password-stdin');
        if ($password === '') {
            throw new InvalidArgumentException('Provide --password or --password-stdin.');
        }

        if (!$user->setPassword($password)) {
            throw new RuntimeException('Could not set user password.');
        }
        $user->save();

        CliApplication::writeSuccess('Password updated.', $options);
        return 0;
    }

    public static function userSendPassword(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        $user->sendNewPassword();

        CliApplication::writeSuccess('Login information sent.', $options);
        return 0;
    }

    public static function userTfaSetup(array $arguments, array $options): int
    {
        global $gCurrentOrganization, $gCurrentUser, $gCurrentUserId;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if ((int)$user->getValue('usr_id') !== (int)$gCurrentUserId) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ($user->hasSetupTfa()) {
            throw new RuntimeException('Two-factor authentication is already configured for this user.');
        }

        $tfa = new TwoFactorAuth(issuer: (string)$gCurrentOrganization->getValue('org_longname'));
        $secret = CliApplication::readSecret($options, 'secret', 'secret-stdin');
        if ($secret === '') {
            $secret = $tfa->createSecret();
            CliApplication::writeOutput('Secret: ' . $secret . PHP_EOL, $options, false);
        }

        $code = CliApplication::optionString($options, 'code');
        if ($code === '') {
            if (CliApplication::optionBool($options, 'no-interaction', false)) {
                throw new RuntimeException('A verification code is required. Pass --code=CODE.');
            }

            fwrite(STDERR, 'Verification code: ');
            $input = fgets(STDIN);
            if ($input === false) {
                throw new RuntimeException('Could not read verification code from STDIN.');
            }
            $code = trim($input);
        }

        if (!$tfa->verifyCode($secret, $code)) {
            throw new Exception('SYS_SECURITY_CODE_INVALID');
        }

        $gCurrentUser->setSecondFactorSecret($secret);
        $gCurrentUser->save();

        CliApplication::writeSuccess('Two-factor authentication configured.', $options);
        return 0;
    }

    public static function userTfaStatus(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        CliApplication::writeValue(
            array(
                'user_uuid' => (string)$user->getValue('usr_uuid'),
                'configured' => $user->hasSetupTfa()
            ),
            $options
        );

        return 0;
    }

    public static function userTfaReset(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if ((int)$user->getValue('usr_id') !== (int)$gCurrentUser->getValue('usr_id')
            && !$gCurrentUser->isAdministratorUsers()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::confirm('Reset two-factor authentication for this user?', $options);
        $user->setSecondFactorSecret(null);
        $user->save();
        self::reloadUserSessions((int)$user->getValue('usr_id'));

        CliApplication::writeSuccess('Two-factor authentication reset.', $options);
        return 0;
    }


    public static function userPhotoSet(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$gCurrentUser->hasRightEditProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $sourcePath = CliApplication::requireArgument($arguments, 1, 'file');
        (new UserPhotoService())->saveFromFile($user, $sourcePath);
        self::reloadUserSessions((int)$user->getValue('usr_id'));

        CliApplication::writeSuccess('Profile photo updated.', $options);
        return 0;
    }

    public static function userPhotoDelete(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$gCurrentUser->hasRightEditProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::confirm('Delete this profile photo?', $options);
        (new UserPhotoService())->delete($user);
        self::reloadUserSessions((int)$user->getValue('usr_id'));

        CliApplication::writeSuccess('Profile photo deleted.', $options);
        return 0;
    }

    public static function relationTypeList(array $arguments, array $options): int
    {
        global $gDb;
        $rows = $gDb->queryPrepared(
            'SELECT urt_id AS id, urt_uuid AS uuid, urt_name AS name, urt_name_male AS name_male,
                    urt_name_female AS name_female, urt_edit_user AS editable_by_user,
                    urt_id_inverse AS inverse_id
               FROM ' . TBL_USER_RELATION_TYPES . '
           ORDER BY urt_name'
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function relationTypeShow(array $arguments, array $options): int
    {
        $type = self::resolveRelationType(CliApplication::requireArgument($arguments, 0, 'type'));
        CliApplication::writeValue(array(
            'id' => (int)$type->getValue('urt_id'),
            'uuid' => (string)$type->getValue('urt_uuid'),
            'name' => (string)$type->getValue('urt_name', 'database'),
            'name_male' => (string)$type->getValue('urt_name_male', 'database'),
            'name_female' => (string)$type->getValue('urt_name_female', 'database'),
            'editable_by_user' => (bool)$type->getValue('urt_edit_user'),
            'relation_type' => $type->getRelationTypeString(),
            'inverse_id' => $type->getValue('urt_id_inverse')
        ), $options);
        return 0;
    }

    public static function relationTypeAdd(array $arguments, array $options): int
    {
        global $gDb;

        $kind = CliApplication::optionString($options, 'type');
        if (!in_array($kind, array(
            UserRelationType::USER_RELATION_TYPE_SYMMETRICAL,
            UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL,
            UserRelationType::USER_RELATION_TYPE_UNIDIRECTIONAL
        ), true)) {
            throw new InvalidArgumentException('--type must be symmetrical, asymmetrical or unidirectional.');
        }

        $relationType = new UserRelationType($gDb);
        self::applyRelationTypeOptions($relationType, $options);

        $inverse = null;
        if ($kind === UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL) {
            $inverseName = CliApplication::optionString($options, 'inverse-name');
            if ($inverseName === '') {
                throw new InvalidArgumentException('--inverse-name is required for an asymmetrical relation type.');
            }
            $inverse = new UserRelationType($gDb);
            self::applyInverseRelationTypeOptions($inverse, $options, true);
        }

        $gDb->startTransaction();
        try {
            $relationType->save();

            if ($kind === UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL) {
                $inverse->setValue('urt_id_inverse', (int)$relationType->getValue('urt_id'));
                $inverse->save();

                $relationType->setValue('urt_id_inverse', (int)$inverse->getValue('urt_id'));
                $relationType->save();
            } elseif ($kind === UserRelationType::USER_RELATION_TYPE_SYMMETRICAL) {
                $relationType->setValue('urt_id_inverse', (int)$relationType->getValue('urt_id'));
                $relationType->save();
            }

            $gDb->endTransaction();
        } catch (\Throwable $e) {
            $gDb->rollback();
            throw $e;
        }

        CliApplication::writeValue(array(
            'id' => (int)$relationType->getValue('urt_id'),
            'uuid' => (string)$relationType->getValue('urt_uuid')
        ), $options);
        return 0;
    }

    public static function relationTypeUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $relationType = self::resolveRelationType(CliApplication::requireArgument($arguments, 0, 'type'));
        $kind = $relationType->getRelationTypeString();
        self::applyRelationTypeOptions($relationType, $options);

        $inverse = null;
        if ($kind === UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL) {
            $inverse = new UserRelationType($gDb, (int)$relationType->getValue('urt_id_inverse'));
            if ($inverse->isNewRecord()) {
                throw new RuntimeException('The inverse user relation type could not be loaded.');
            }
            self::applyInverseRelationTypeOptions($inverse, $options, false);
        }

        $gDb->startTransaction();
        try {
            $relationType->save();
            if ($inverse !== null) {
                $inverse->save();
            }
            $gDb->endTransaction();
        } catch (\Throwable $e) {
            $gDb->rollback();
            throw $e;
        }

        CliApplication::writeSuccess('Relation type updated.', $options);
        return 0;
    }

    public static function relationTypeDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete this relation type?', $options);
        self::resolveRelationType(CliApplication::requireArgument($arguments, 0, 'type'))->delete();
        CliApplication::writeSuccess('Relation type deleted.', $options);
        return 0;
    }

    /**
     * Resolve one endpoint of a user relation, or null if the acting user may not see it.
     *
     * An endpoint is only shown if the user belongs to the selected organization and the acting
     * user may open their profile - the same two decisions the web relation list makes. A relation
     * list repeats the same users many times, so every user is read only once.
     *
     * @param array<int,?User> $cache
     */
    private static function visibleRelationEndpoint(int $userId, array &$cache): ?User
    {
        global $gCurrentUser;

        if (!array_key_exists($userId, $cache)) {
            try {
                $user = CliApplication::resolveUser((string)$userId);
            } catch (InvalidArgumentException) {
                $user = null;
            }

            $cache[$userId] = ($user !== null && $gCurrentUser->hasRightViewProfile($user)) ? $user : null;
        }

        return $cache[$userId];
    }

    public static function relationList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $where = array('1 = 1');
        $params = array();
        if (isset($arguments[0])) {
            $user = CliApplication::resolveUser($arguments[0]);
            if (!$gCurrentUser->hasRightViewProfile($user)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $where[] = '(ure_usr_id1 = ? OR ure_usr_id2 = ?)';
            $params[] = (int)$user->getValue('usr_id');
            $params[] = (int)$user->getValue('usr_id');
        }
        if (array_key_exists('type', $options)) {
            $type = self::resolveRelationType(CliApplication::optionString($options, 'type'));
            $where[] = 'ure_urt_id = ?';
            $params[] = (int)$type->getValue('urt_id');
        }

        $relationRows = $gDb->queryPrepared(
            'SELECT ure.ure_id AS id, ure.ure_uuid AS uuid, urt.urt_name AS type,
                    ure.ure_usr_id1 AS user1_id, ure.ure_usr_id2 AS user2_id
               FROM ' . TBL_USER_RELATIONS . ' ure
         INNER JOIN ' . TBL_USER_RELATION_TYPES . ' urt ON urt.urt_id = ure.ure_urt_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY ure.ure_id',
            $params
        )->fetchAll();

        $rows = array();
        $visibleUsers = array();
        foreach ($relationRows as $row) {
            $user1 = self::visibleRelationEndpoint((int)$row['user1_id'], $visibleUsers);
            $user2 = self::visibleRelationEndpoint((int)$row['user2_id'], $visibleUsers);

            if ($user1 === null || $user2 === null) {
                continue;
            }

            $rows[] = array(
                'id' => (int)$row['id'],
                'uuid' => (string)$row['uuid'],
                'type' => (string)$row['type'],
                'user1_uuid' => (string)$user1->getValue('usr_uuid'),
                'user2_uuid' => (string)$user2->getValue('usr_uuid')
            );
        }

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function relationAdd(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $user1 = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user1'));
        $user2 = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user2'));
        $type = self::resolveRelationType(CliApplication::requireArgument($arguments, 2, 'type'));

        if (!$gCurrentUser->hasRightEditProfile($user1)
            || !$gCurrentUser->hasRightEditProfile($user2)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ((int)$user1->getValue('usr_id') === (int)$user2->getValue('usr_id')) {
            throw new InvalidArgumentException('A user relation requires two different users.');
        }

        $relation = new UserRelation($gDb);
        $relation->readDataByColumns(array(
            'ure_urt_id' => (int)$type->getValue('urt_id'),
            'ure_usr_id1' => (int)$user1->getValue('usr_id'),
            'ure_usr_id2' => (int)$user2->getValue('usr_id')
        ));
        if (!$relation->isNewRecord()) {
            throw new InvalidArgumentException('The relation already exists.');
        }

        $gDb->startTransaction();
        try {
            $relation->setValue('ure_urt_id', (int)$type->getValue('urt_id'));
            $relation->setValue('ure_usr_id1', (int)$user1->getValue('usr_id'));
            $relation->setValue('ure_usr_id2', (int)$user2->getValue('usr_id'));
            $relation->save();

            $inverseType = $type->getValue('urt_id_inverse');
            if ($inverseType !== null && (int)$inverseType > 0
                && ((int)$inverseType !== (int)$type->getValue('urt_id')
                    || (int)$user1->getValue('usr_id') !== (int)$user2->getValue('usr_id'))) {
                $inverse = new UserRelation($gDb);
                $inverse->readDataByColumns(array(
                    'ure_urt_id' => (int)$inverseType,
                    'ure_usr_id1' => (int)$user2->getValue('usr_id'),
                    'ure_usr_id2' => (int)$user1->getValue('usr_id')
                ));
                if ($inverse->isNewRecord()) {
                    $inverse->setValue('ure_urt_id', (int)$inverseType);
                    $inverse->setValue('ure_usr_id1', (int)$user2->getValue('usr_id'));
                    $inverse->setValue('ure_usr_id2', (int)$user1->getValue('usr_id'));
                    $inverse->save();
                }
            }
            $gDb->endTransaction();
        } catch (\Throwable $e) {
            $gDb->rollback();
            throw $e;
        }

        CliApplication::writeValue(
            array(
                'id' => (int)$relation->getValue('ure_id'),
                'uuid' => (string)$relation->getValue('ure_uuid')
            ),
            $options
        );
        return 0;
    }

    public static function relationDelete(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $relation = self::resolveRelation(CliApplication::requireArgument($arguments, 0, 'relation'));

        /*
         * resolveRelation() is deliberately global because relation UUIDs/ids are globally unique.
         * Resolve both endpoints again through the organization-scoped user resolver before the
         * record can be mutated.
         */
        try {
            $user1 = CliApplication::resolveUser((string)$relation->getValue('ure_usr_id1'));
            $user2 = CliApplication::resolveUser((string)$relation->getValue('ure_usr_id2'));
        } catch (InvalidArgumentException) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if (!$gCurrentUser->hasRightEditProfile($user1)
            || !$gCurrentUser->hasRightEditProfile($user2)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::confirm('Delete this user relation?', $options);
        $relation->delete();

        CliApplication::writeSuccess('User relation deleted.', $options);
        return 0;
    }

    public static function registrationList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $rows = $gDb->queryPrepared(
            'SELECT reg.reg_id AS registration_id, usr.usr_id AS user_id, usr.usr_uuid AS user_uuid,
                    usr.usr_login_name AS login, reg.reg_timestamp AS registered_at,
                    reg.reg_validation_id AS validation_id
               FROM ' . TBL_REGISTRATIONS . ' reg
         INNER JOIN ' . TBL_USERS . ' usr ON usr.usr_id = reg.reg_usr_id
              WHERE reg.reg_org_id = ?
           ORDER BY reg.reg_timestamp',
            array($gCurrentOrgId)
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function registrationShow(array $arguments, array $options): int
    {
        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $data = self::userData($registration);
        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function registrationSimilar(array $arguments, array $options): int
    {
        global $gDb, $gProfileFields;

        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $rows = array();
        // The ids come from searchSimilarUsers(), so they need no further lookup.
        foreach ($registration->searchSimilarUsers() as $userId) {
            $user = new User($gDb, $gProfileFields, (int)$userId);
            $rows[] = array(
                'id' => (int)$user->getValue('usr_id'),
                'uuid' => (string)$user->getValue('usr_uuid'),
                'login' => (string)$user->getValue('usr_login_name'),
                'name' => $user->readableName()
            );
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function registrationConfirm(array $arguments, array $options): int
    {
        global $gDb;
        /*
         * This command is the counterpart of the confirmation link a registrant receives by email,
         * where nobody is logged in either, so it deliberately runs without --as. It still has to
         * establish the globals that a web request always provides.
         */
        CliApplication::ensureAnonymousActor();

        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $validationId = CliApplication::requireArgument($arguments, 1, 'validation-id');

        $service = new RegistrationService($gDb, (string)$registration->getValue('usr_uuid'));
        $result = $service->confirmRegistration($validationId);

        CliApplication::writeValue($result, $options);
        return 0;
    }

    public static function registrationApprove(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));

        // Check the requested groups before the registration is accepted.
        $roles = array();
        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($gCurrentUser)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $roles[] = $role;
        }

        $gDb->startTransaction();
        try {
            $registration->acceptRegistration();
            foreach ($roles as $role) {
                $role->startMembership((int)$registration->getValue('usr_id'));
            }
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        self::reloadUserSessions((int)$registration->getValue('usr_id'));
        CliApplication::writeSuccess('Registration approved.', $options);
        return 0;
    }

    public static function registrationAssign(array $arguments, array $options): int
    {
        global $gDb;
        $registration = self::resolveRegistration(
            CliApplication::requireArgument($arguments, 0, 'registration-user')
        );
        $existing = CliApplication::resolveUser(
            CliApplication::requireArgument($arguments, 1, 'existing-user')
        );

        $service = new RegistrationService(
            $gDb,
            (string)$registration->getValue('usr_uuid')
        );
        $result = $service->assignRegistration(
            (string)$existing->getValue('usr_uuid'),
            CliApplication::optionBool($options, 'existing-member', false) ?? false,
            false
        );

        self::reloadUserSessions((int)$existing->getValue('usr_id'));
        CliApplication::writeValue($result, $options);
        return 0;
    }

    public static function registrationDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete this pending registration?', $options);
        self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'))->delete();
        CliApplication::writeSuccess('Registration deleted.', $options);
        return 0;
    }

    public static function registrationSendLogin(array $arguments, array $options): int
    {
        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'registration-user'));
        $existing = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'existing-user'));
        if ((string)$existing->getValue('usr_login_name') === '') {
            throw new InvalidArgumentException('The existing user has no login name.');
        }
        $existing->sendNewPassword();
        CliApplication::writeSuccess('Login information sent.', $options);
        return 0;
    }

    public static function groupList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser;
        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);

        if (array_key_exists('category', $options)) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'ROL');
            $where[] = 'rol.rol_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        if (array_key_exists('active', $options)) {
            $where[] = 'rol.rol_valid = ?';
            $params[] = CliApplication::optionBool($options, 'active') ? 1 : 0;
        }

        $rows = $gDb->queryPrepared(
            'SELECT rol.rol_id AS id, rol.rol_uuid AS uuid, rol.rol_name AS name,
                    cat.cat_name AS category, rol.rol_valid AS active, rol.rol_system AS system,
                    rol.rol_administrator AS administrator
               FROM ' . TBL_ROLES . ' rol
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = rol.rol_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY cat.cat_sequence, rol.rol_name',
            $params
        )->fetchAll();

        // Only roles the acting user may see, as in the groups-roles web module.
        $visible = array();
        foreach ($rows as $row) {
            if ($gCurrentUser->hasRightViewRole((int)$row['id'])) {
                $visible[] = $row;
            }
        }
        $rows = $visible;

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function groupShow(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $roleId = (int)$role->getValue('rol_id');
        if (!$gCurrentUser->hasRightViewRole($roleId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $data = self::roleData($role);

        if (CliApplication::optionBool($options, 'permissions', false)) {
            $data['permissions'] = self::rolePermissionData($role);
        }
        if (CliApplication::optionBool($options, 'members', false)) {
            if (!$gCurrentUser->hasRightViewProfiles($roleId)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $data['members'] = self::membershipRows($role, DATE_NOW, 'active', false);
        }

        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function groupAdd(array $arguments, array $options): int
    {
        global $gDb;
        $name = CliApplication::requireArgument($arguments, 0, 'name');
        $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'ROL');

        self::assertRoleNameUnique($name, (int)$category->getValue('cat_id'), 0);
        $role = new Role($gDb);
        $role->setValue('rol_name', $name);
        $role->setValue('rol_cat_id', (int)$category->getValue('cat_id'));
        self::applyRoleOptions($role, $options);
        $role->save();

        CliApplication::writeValue(array(
            'id' => (int)$role->getValue('rol_id'),
            'uuid' => (string)$role->getValue('rol_uuid'),
            'name' => (string)$role->getValue('rol_name')
        ), $options);
        return 0;
    }

    public static function groupUpdate(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $name = array_key_exists('name', $options)
            ? self::requireTextOption($options, 'name')
            : (string)$role->getValue('rol_name');
        $categoryId = (int)$role->getValue('rol_cat_id');
        if (array_key_exists('category', $options)) {
            $categoryId = (int)self::resolveCategory(CliApplication::optionString($options, 'category'), 'ROL')->getValue('cat_id');
        }
        self::assertRoleNameUnique($name, $categoryId, (int)$role->getValue('rol_id'));

        if (array_key_exists('name', $options)) {
            $role->setValue('rol_name', $name);
        }
        if (array_key_exists('category', $options)) {
            $role->setValue('rol_cat_id', $categoryId);
        }
        self::applyRoleOptions($role, $options);

        if (array_key_exists('max-members', $options) && $role->countVacancies() < 0) {
            throw new Exception('SYS_ROLE_MAX_MEMBERS', array($role->getValue('rol_name')));
        }

        $role->save();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Group updated.', $options);
        return 0;
    }

    public static function groupDelete(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        CliApplication::confirm('Delete group "' . $role->getValue('rol_name') . '"?', $options);
        $role->delete();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Group deleted.', $options);
        return 0;
    }

    public static function groupActivate(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $role->activate();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Group activated.', $options);
        return 0;
    }

    public static function groupDeactivate(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $role->deactivate();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Group deactivated.', $options);
        return 0;
    }

    public static function groupExport(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser, $gProfileFields;
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $roleId = (int)$role->getValue('rol_id');

        // Same rule as list:export: the role must be viewable and its member profiles readable.
        if (!$gCurrentUser->hasRightViewRole($roleId)
            || !$gCurrentUser->hasRightViewProfiles($roleId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $userIds = $gDb->queryPrepared(
            'SELECT DISTINCT mem_usr_id
               FROM ' . TBL_MEMBERS . '
              WHERE mem_rol_id = ?
                AND mem_begin <= ?
                AND mem_end >= ?
           ORDER BY mem_usr_id',
            array($roleId, DATE_NOW, DATE_NOW)
        )->fetchAll(PDO::FETCH_COLUMN);

        $vcards = '';
        // The ids come from the membership query above, so they need no further lookup.
        foreach ($userIds as $userId) {
            $vcards .= (new User($gDb, $gProfileFields, (int)$userId))->getVCard() . PHP_EOL;
        }
        CliApplication::writeOutput($vcards, $options);
        return 0;
    }

    public static function groupPermissions(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));

        foreach (CliApplication::optionValues($options, 'set') as $assignment) {
            [$right, $value] = self::splitAssignment($assignment, '--set');
            if (!in_array($right, self::rolePermissionColumns(), true)) {
                throw new InvalidArgumentException('Unknown role permission "' . $right . '".');
            }
            $role->setValue($right, self::parseBool($value));
        }
        if (count(CliApplication::optionValues($options, 'set')) > 0) {
            $role->save();
            self::reloadAllSessions();
        }

        CliApplication::writeValue(self::rolePermissionData($role), $options);
        return 0;
    }

    public static function groupMembers(array $arguments, array $options): int
    {
        global $gCurrentUser;

        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $roleId = (int)$role->getValue('rol_id');
        if (!$gCurrentUser->hasRightViewRole($roleId)
            || !$gCurrentUser->hasRightViewProfiles($roleId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $date = CliApplication::optionString($options, 'date', DATE_NOW);
        self::validateDate($date);

        $mode = 'all';
        if (CliApplication::optionBool($options, 'active', false)) {
            $mode = 'active';
        } elseif (CliApplication::optionBool($options, 'former', false)) {
            $mode = 'former';
        } elseif (CliApplication::optionBool($options, 'future', false)) {
            $mode = 'future';
        }

        $rows = self::membershipRows($role, $date, $mode, CliApplication::optionBool($options, 'leaders', false));
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function groupAddUser(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $start = CliApplication::optionString($options, 'start', DATE_NOW);
        $end = CliApplication::optionString($options, 'end', DATE_MAX);
        self::validateDateRange($start, $end);
        $leader = CliApplication::optionBool($options, 'leader', null);
        $role->setMembership(
            (int)$user->getValue('usr_id'),
            $start,
            $end,
            $leader,
            CliApplication::optionBool($options, 'force-period', false)
        );
        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('User assigned to group.', $options);
        return 0;
    }

    public static function groupDelUser(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if (!CliApplication::optionExists($options, 'date')) {
            // Ends the membership as of today; also guards the administrator role.
            $role->stopMembership((int)$user->getValue('usr_id'));
        } else {
            $endDate = CliApplication::optionString($options, 'date');
            self::validateDate($endDate, '--date');

            /*
             * Role::stopMembership() refuses to empty the administrator role. An explicit end date
             * must not become a way around that check, and the membership has to be written
             * through Role::setMembership() so role dependencies are handled the same way.
             */
            if ((bool)$role->getValue('rol_administrator')
                && ($role->countMembers() + $role->countLeaders()) <= 1) {
                throw new Exception('SYS_MUST_HAVE_ADMINISTRATOR');
            }

            $membership = self::resolveMembershipForDate($role, $user, $endDate);
            $role->setMembership(
                (int)$user->getValue('usr_id'),
                (string)$membership->getValue('mem_begin', 'Y-m-d'),
                $endDate,
                (bool)$membership->getValue('mem_leader')
            );
        }

        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Group membership ended.', $options);
        return 0;
    }

    public static function groupUpdateUser(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $membership = self::resolveMembershipForDate($role, $user, DATE_NOW, true);
        $start = CliApplication::optionString($options, 'start', (string)$membership->getValue('mem_begin', 'Y-m-d'));
        $end = CliApplication::optionString($options, 'end', (string)$membership->getValue('mem_end', 'Y-m-d'));
        self::validateDateRange($start, $end);
        $leader = CliApplication::optionBool($options, 'leader', (bool)$membership->getValue('mem_leader'));

        /*
         * This command states the period explicitly, so the default is to write exactly that period
         * instead of merging it with an adjacent one. --force-period=0 restores the merging
         * behaviour of Role::setMembership().
         */
        $role->setMembership(
            (int)$user->getValue('usr_id'),
            $start,
            $end,
            $leader,
            CliApplication::optionBool($options, 'force-period', true) ?? true
        );
        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Group membership updated.', $options);
        return 0;
    }

    public static function groupDeleteMembership(array $arguments, array $options): int
    {
        global $gCurrentUser, $gDb;
        $membership = self::resolveMembership(CliApplication::requireArgument($arguments, 0, 'membership'));
        $role = new Role($gDb, (int)$membership->getValue('mem_rol_id'));
        if (!$role->allowedToAssignMembers($gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Permanently delete this membership history row?', $options);
        $userId = (int)$membership->getValue('mem_usr_id');
        $membership->delete();
        self::reloadUserSessions($userId);
        CliApplication::writeSuccess('Membership history row deleted.', $options);
        return 0;
    }

    public static function groupDependencies(array $arguments, array $options): int
    {
        global $gCurrentUser, $gDb;

        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        if (!$gCurrentUser->hasRightViewRole((int)$role->getValue('rol_id'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $rows = array();
        foreach (RoleDependency::getParentRoles($gDb, (int)$role->getValue('rol_id')) as $id) {
            $parent = new Role($gDb, (int)$id);
            $rows[] = array('direction' => 'parent', 'id' => (int)$id, 'uuid' => $parent->getValue('rol_uuid'), 'name' => $parent->getValue('rol_name'));
        }
        foreach (RoleDependency::getChildRoles($gDb, (int)$role->getValue('rol_id')) as $id) {
            $child = new Role($gDb, (int)$id);
            $rows[] = array('direction' => 'child', 'id' => (int)$id, 'uuid' => $child->getValue('rol_uuid'), 'name' => $child->getValue('rol_name'));
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function groupAddDependency(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUserId;
        $parent = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $child = self::resolveGroup(CliApplication::requireArgument($arguments, 1, 'dependent-group'));
        if ((int)$parent->getValue('rol_id') === (int)$child->getValue('rol_id')) {
            throw new InvalidArgumentException('A group cannot depend on itself.');
        }

        $dependency = new RoleDependency($gDb);
        if ($dependency->get((int)$child->getValue('rol_id'), (int)$parent->getValue('rol_id'))) {
            throw new InvalidArgumentException('The role dependency already exists.');
        }
        $dependency->clear();
        $dependency->setParent((int)$parent->getValue('rol_id'));
        $dependency->setChild((int)$child->getValue('rol_id'));

        // updateMembership() propagates the parent memberships to the child role.
        $gDb->startTransaction();
        try {
            $dependency->insert($gCurrentUserId);
            $dependency->updateMembership();
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        self::reloadAllSessions();

        CliApplication::writeSuccess('Role dependency added.', $options);
        return 0;
    }

    public static function groupDelDependency(array $arguments, array $options): int
    {
        global $gDb;
        $parent = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $child = self::resolveGroup(CliApplication::requireArgument($arguments, 1, 'dependent-group'));
        $dependency = new RoleDependency($gDb);
        if (!$dependency->get((int)$child->getValue('rol_id'), (int)$parent->getValue('rol_id'))) {
            throw new InvalidArgumentException('Role dependency was not found.');
        }
        CliApplication::confirm('Delete this role dependency?', $options);
        $dependency->delete();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Role dependency deleted.', $options);
        return 0;
    }

    public static function listList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUserId, $gCurrentUser;
        $where = array('lst_org_id = ?');
        $params = array($gCurrentOrgId);
        if (!$gCurrentUser->isAdministratorRoles()) {
            $where[] = '(lst_global = true OR lst_usr_id = ?)';
            $params[] = $gCurrentUserId;
        }
        if (array_key_exists('global', $options)) {
            $where[] = 'lst_global = ?';
            $params[] = CliApplication::optionBool($options, 'global') ? 1 : 0;
        }
        $rows = $gDb->queryPrepared(
            'SELECT lst_id AS id, lst_uuid AS uuid, lst_name AS name, lst_global AS global, lst_usr_id AS owner_user_id
               FROM ' . TBL_LISTS . '
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY lst_name',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function listShow(array $arguments, array $options): int
    {
        $list = self::resolveList(CliApplication::requireArgument($arguments, 0, 'list'));
        self::assertListEditableOrVisible($list, false);
        CliApplication::writeValue(self::listData($list), $options);
        return 0;
    }

    public static function listAdd(array $arguments, array $options): int
    {
        global $gDb;
        $list = new ListConfiguration($gDb);
        $list->setValue('lst_name', CliApplication::requireArgument($arguments, 0, 'name'));
        $list->setValue('lst_global', CliApplication::optionBool($options, 'global', false) ? 1 : 0);
        self::applyListColumns($list, $options, true);
        if ($list->countColumns() === 0) {
            throw new InvalidArgumentException('At least one --column is required.');
        }
        $list->save();
        CliApplication::writeValue(array('id' => (int)$list->getValue('lst_id'), 'uuid' => (string)$list->getValue('lst_uuid')), $options);
        return 0;
    }

    public static function listUpdate(array $arguments, array $options): int
    {
        $list = self::resolveList(CliApplication::requireArgument($arguments, 0, 'list'));
        self::assertListEditableOrVisible($list, true);
        if (array_key_exists('name', $options)) {
            $list->setValue('lst_name', CliApplication::optionString($options, 'name'));
        }
        if (array_key_exists('global', $options)) {
            $list->setValue('lst_global', CliApplication::optionBool($options, 'global') ? 1 : 0);
        }
        if (array_key_exists('column', $options)) {
            $list->deleteColumn(0, true);
            self::applyListColumns($list, $options, true);
        } else {
            self::applyListColumns($list, $options, false);
        }
        $list->save();
        CliApplication::writeSuccess('List configuration updated.', $options);
        return 0;
    }

    public static function listCopy(array $arguments, array $options): int
    {
        global $gDb;
        $source = self::resolveList(CliApplication::requireArgument($arguments, 0, 'list'));
        self::assertListEditableOrVisible($source, false);

        $copy = new ListConfiguration($gDb);
        $copy->setValue('lst_name', CliApplication::optionString($options, 'name'));
        $copy->setValue('lst_global', CliApplication::optionBool($options, 'global', false) ? 1 : 0);

        foreach (self::getListColumnRows((int)$source->getValue('lst_id')) as $column) {
            $field = (int)$column['lsc_usf_id'] > 0 ? (int)$column['lsc_usf_id'] : (string)$column['lsc_special_field'];
            $copy->addColumn($field, 0, (string)$column['lsc_sort'], (string)$column['lsc_filter']);
        }
        $copy->save();
        CliApplication::writeValue(array('id' => (int)$copy->getValue('lst_id'), 'uuid' => (string)$copy->getValue('lst_uuid')), $options);
        return 0;
    }

    public static function listDelete(array $arguments, array $options): int
    {
        $list = self::resolveList(CliApplication::requireArgument($arguments, 0, 'list'));
        self::assertListEditableOrVisible($list, true);
        CliApplication::confirm('Delete this list configuration?', $options);
        $list->delete();
        CliApplication::writeSuccess('List configuration deleted.', $options);
        return 0;
    }

    public static function listExport(array $arguments, array $options): int
    {
        global $gCurrentOrganization, $gCurrentUser, $gL10n, $gSettingsManager;

        if ($gSettingsManager->getInt('groups_roles_export') === 0
            || ($gSettingsManager->getInt('groups_roles_export') === 2
                && !$gCurrentUser->checkRolesRight('rol_edit_user'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $list = self::resolveList(CliApplication::requireArgument($arguments, 0, 'list'));
        self::assertListEditableOrVisible($list, false);

        $roleReferences = CliApplication::optionValues($options, 'role');
        if (count($roleReferences) === 0) {
            throw new InvalidArgumentException('At least one --role is required.');
        }

        $roleUuids = array();
        $roleNames = array();
        $hasRightViewProfiles = true;
        $hasRightViewFormerMembers = true;

        foreach ($roleReferences as $reference) {
            $role = self::resolveGroup($reference);
            $roleId = (int)$role->getValue('rol_id');

            if (!$gCurrentUser->hasRightViewRole($roleId)
                || (!(bool)$role->getValue('rol_valid')
                    && !$gCurrentUser->checkRolesRight('rol_assign_roles'))) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            if (!$gCurrentUser->hasRightViewProfiles($roleId)) {
                $hasRightViewProfiles = false;
            }
            if (!$gCurrentUser->hasRightViewFormerRolesMembers($roleId)) {
                $hasRightViewFormerMembers = false;
            }

            $roleUuids[] = (string)$role->getValue('rol_uuid');
            $roleNames[] = (string)$role->getValue('rol_name');
        }

        $members = CliApplication::optionString($options, 'members', 'active');
        $dateFrom = CliApplication::optionString($options, 'date-from', DATE_NOW);
        $dateTo = CliApplication::optionString($options, 'date-to', DATE_NOW);
        self::validateDate($dateFrom, '--date-from');
        self::validateDate($dateTo, '--date-to');

        if ($dateFrom > $dateTo) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        if (!$hasRightViewFormerMembers) {
            $members = 'active';
            $dateFrom = DATE_NOW;
            $dateTo = DATE_NOW;
        }

        $relationUuids = array();
        foreach (CliApplication::optionValues($options, 'relation-type') as $reference) {
            $relationUuids[] = (string)self::resolveRelationType($reference)->getValue('urt_uuid');
        }

        if (!$hasRightViewProfiles) {
            $list->setModeShowOnlyNames();
        }

        $listData = new ListData();
        $listData->setDataByConfiguration(
            $list,
            array(
                'showRolesMembers' => $roleUuids,
                'showAllMembersThisOrga' => $members === 'all',
                'showFormerMembers' => $members !== 'active',
                'showRelationTypes' => $relationUuids,
                'startDate' => $dateFrom,
                'endDate' => $dateTo
            )
        );

        $headlines = $list->getColumnNames();
        if ($members === 'all') {
            $headlines[] = $gL10n->get('SYS_GROUP_ROLE_MEMBERSHIP');
        }
        $listData->setColumnHeadlines($headlines);

        $filename = (string)$gCurrentOrganization->getValue('org_shortname')
            . '-' . implode('-', $roleNames);
        if ((string)$list->getValue('lst_name') !== '') {
            $filename .= '-' . (string)$list->getValue('lst_name');
        }
        $filename = FileSystemUtils::getSanitizedPathEntry(str_replace('.', '', html_entity_decode(
            $filename,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )));

        $export = $listData->createExportFile(
            $filename,
            CliApplication::optionString($options, 'format', 'csv')
        );

        self::moveGeneratedFile($export['path'], $export['filename'], $options);
        return 0;
    }

    public static function permissionsList(array $arguments, array $options): int
    {
        global $gDb;
        $where = '';
        $params = array();
        if (array_key_exists('type', $options)) {
            $where = ' WHERE UPPER(ror.ror_name_intern) = UPPER(?)';
            $params[] = CliApplication::optionString($options, 'type');
        }
        $rows = $gDb->queryPrepared(
            'SELECT ror.ror_name_intern AS right_type, rrd.rrd_object_id AS object_id,
                    rol.rol_id AS role_id, rol.rol_uuid AS role_uuid, rol.rol_name AS role_name
               FROM ' . TBL_ROLES_RIGHTS_DATA . ' rrd
         INNER JOIN ' . TBL_ROLES_RIGHTS . ' ror ON ror.ror_id = rrd.rrd_ror_id
         INNER JOIN ' . TBL_ROLES . ' rol ON rol.rol_id = rrd.rrd_rol_id' .
            $where . '
           ORDER BY ror.ror_name_intern, rrd.rrd_object_id, rol.rol_name',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function permissionsShow(array $arguments, array $options): int
    {
        global $gDb;
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'object-id'), 'object-id');
        $rights = new RolesRights($gDb, $right, $objectId);
        CliApplication::writeValue(array(
            'right_type' => $right,
            'object_id' => $objectId,
            'roles' => $rights->getRolesNames()
        ), $options);
        return 0;
    }

    public static function permissionsSet(array $arguments, array $options): int
    {
        return self::changePermissions('set', $arguments, $options);
    }

    public static function permissionsAdd(array $arguments, array $options): int
    {
        return self::changePermissions('add', $arguments, $options);
    }

    public static function permissionsRemove(array $arguments, array $options): int
    {
        return self::changePermissions('remove', $arguments, $options);
    }

    public static function permissionsClear(array $arguments, array $options): int
    {
        global $gDb;
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'object-id'), 'object-id');
        CliApplication::confirm('Clear all role assignments for this object right?', $options);
        (new RolesRights($gDb, $right, $objectId))->delete();
        self::reloadAllSessions();
        CliApplication::writeSuccess('Object rights cleared.', $options);
        return 0;
    }

    public static function categoryList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $where = array('(cat_org_id = ? OR cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);
        if (array_key_exists('type', $options)) {
            $where[] = 'cat_type = ?';
            $params[] = CliApplication::optionString($options, 'type');
        }
        $rows = $gDb->queryPrepared(
            'SELECT cat_id AS id, cat_uuid AS uuid, cat_type AS type, cat_name AS name,
                    cat_name_intern AS internal_name, cat_default AS default_category,
                    cat_system AS system, cat_sequence AS sequence, cat_org_id AS organization_id
               FROM ' . TBL_CATEGORIES . '
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY cat_type, cat_sequence, cat_name',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function categoryShow(array $arguments, array $options): int
    {
        global $gDb;
        $category = self::resolveCategory(CliApplication::requireArgument($arguments, 0, 'category'));
        $id = (int)$category->getValue('cat_id');
        CliApplication::writeValue(array(
            'id' => $id,
            'uuid' => $category->getValue('cat_uuid'),
            'type' => $category->getValue('cat_type'),
            'name' => $category->getValue('cat_name', 'database'),
            'internal_name' => $category->getValue('cat_name_intern'),
            'default' => (bool)$category->getValue('cat_default'),
            'system' => (bool)$category->getValue('cat_system'),
            'sequence' => (int)$category->getValue('cat_sequence'),
            'organization_id' => $category->getValue('cat_org_id'),
            'view_roles' => (new RolesRights($gDb, 'category_view', $id))->getRolesNames(),
            'edit_roles' => (new RolesRights($gDb, 'category_edit', $id))->getRolesNames()
        ), $options);
        return 0;
    }

    public static function categoryAdd(array $arguments, array $options): int
    {
        global $gDb;

        $type = strtoupper(CliApplication::requireArgument($arguments, 0, 'type'));
        self::assertCategoryType($type);
        $name = CliApplication::requireArgument($arguments, 1, 'name');

        $category = new Category($gDb);
        $category->setValue('cat_type', $type);
        $category->setValue('cat_name', $name);
        self::setCategoryOrganizationScope($category, $options, true);
        self::assertCategoryNameUnique($category, $name);
        self::assertCategoryRightsInput($category, $options, true);

        $gDb->startTransaction();
        try {
            $category->save();
            self::saveCategoryRights($category, $options, true);
            if (CliApplication::optionBool($options, 'default', false)) {
                self::makeDefaultCategory($category);
            }
            self::resequenceCategories($type);
            $gDb->endTransaction();
        } catch (\Throwable $e) {
            $gDb->rollback();
            throw $e;
        }

        CliApplication::writeValue(array(
            'id' => (int)$category->getValue('cat_id'),
            'uuid' => (string)$category->getValue('cat_uuid')
        ), $options);
        return 0;
    }

    public static function categoryUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $category = self::resolveCategory(CliApplication::requireArgument($arguments, 0, 'category'));
        if (!$category->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $name = CliApplication::optionExists($options, 'name')
            ? self::requireTextOption($options, 'name')
            : (string)$category->getValue('cat_name', 'database');

        self::setCategoryOrganizationScope($category, $options, false);
        self::assertCategoryNameUnique($category, $name);
        self::assertCategoryRightsInput($category, $options, false);

        if (CliApplication::optionExists($options, 'name')) {
            $category->setValue('cat_name', $name);
        }

        $gDb->startTransaction();
        try {
            $category->save();
            self::saveCategoryRights($category, $options, false);
            if (CliApplication::optionBool($options, 'default', false)) {
                self::makeDefaultCategory($category);
            }
            self::resequenceCategories((string)$category->getValue('cat_type'));
            $gDb->endTransaction();
        } catch (\Throwable $e) {
            $gDb->rollback();
            throw $e;
        }

        CliApplication::writeSuccess('Category updated.', $options);
        return 0;
    }

    public static function categoryDelete(array $arguments, array $options): int
    {
        $category = self::resolveCategory(CliApplication::requireArgument($arguments, 0, 'category'));
        if (!$category->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete category "' . $category->getValue('cat_name') . '"?', $options);
        $category->delete();
        CliApplication::writeSuccess('Category deleted.', $options);
        return 0;
    }

    public static function categoryMove(array $arguments, array $options): int
    {
        $category = self::resolveCategory(CliApplication::requireArgument($arguments, 0, 'category'));
        $direction = strtolower(CliApplication::requireArgument($arguments, 1, 'direction'));
        if (!in_array($direction, array('up', 'down'), true)) {
            throw new InvalidArgumentException('Direction must be "up" or "down".');
        }
        $category->moveSequence($direction === 'up' ? Category::MOVE_UP : Category::MOVE_DOWN);
        CliApplication::writeSuccess('Category moved.', $options);
        return 0;
    }

    public static function menuList(array $arguments, array $options): int
    {
        global $gDb;
        $rows = $gDb->queryPrepared(
            'SELECT men.men_id AS id, men.men_uuid AS uuid, men.men_name AS name,
                    men.men_name_intern AS internal_name, men.men_men_id_parent AS parent_id,
                    men.men_url AS url, men.men_icon AS icon, men.men_order AS sequence,
                    men.men_standard AS standard, com.com_name_intern AS component
               FROM ' . TBL_MENU . ' men
          LEFT JOIN ' . TBL_COMPONENTS . ' com ON com.com_id = men.men_com_id
           ORDER BY men.men_order, men.men_name'
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function menuShow(array $arguments, array $options): int
    {
        global $gDb;
        $menu = self::resolveMenu(CliApplication::requireArgument($arguments, 0, 'menu'));
        $id = (int)$menu->getValue('men_id');
        CliApplication::writeValue(array(
            'id' => $id,
            'uuid' => $menu->getValue('men_uuid'),
            'name' => $menu->getValue('men_name', 'database'),
            'description' => $menu->getValue('men_description', 'database'),
            'parent_id' => $menu->getValue('men_men_id_parent'),
            'component_id' => $menu->getValue('men_com_id'),
            'url' => $menu->getValue('men_url'),
            'icon' => $menu->getValue('men_icon'),
            'node' => (bool)$menu->getValue('men_node'),
            'standard' => (bool)$menu->getValue('men_standard'),
            'view_roles' => (new RolesRights($gDb, 'menu_view', $id))->getRolesNames()
        ), $options);
        return 0;
    }

    public static function menuAdd(array $arguments, array $options): int
    {
        global $gDb;
        $menu = new MenuEntry($gDb);
        $menu->setValue('men_name', CliApplication::requireArgument($arguments, 0, 'name'));
        self::applyMenuOptions($menu, $options, true);

        // The entry and its view roles have to appear together.
        $gDb->startTransaction();
        try {
            $menu->save();
            self::saveMenuRights($menu, $options);
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeValue(array('id' => (int)$menu->getValue('men_id'), 'uuid' => (string)$menu->getValue('men_uuid')), $options);
        return 0;
    }

    public static function menuUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $menu = self::resolveMenu(CliApplication::requireArgument($arguments, 0, 'menu'));
        self::applyMenuOptions($menu, $options, false);

        $gDb->startTransaction();
        try {
            $menu->save();
            self::saveMenuRights($menu, $options);
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Menu entry updated.', $options);
        return 0;
    }

    public static function menuDelete(array $arguments, array $options): int
    {
        $menu = self::resolveMenu(CliApplication::requireArgument($arguments, 0, 'menu'));
        CliApplication::confirm('Delete menu entry "' . $menu->getValue('men_name') . '"?', $options);
        $menu->delete();
        CliApplication::writeSuccess('Menu entry deleted.', $options);
        return 0;
    }

    public static function menuMove(array $arguments, array $options): int
    {
        $menu = self::resolveMenu(CliApplication::requireArgument($arguments, 0, 'menu'));
        $direction = strtolower(CliApplication::requireArgument($arguments, 1, 'direction'));
        if (!in_array($direction, array('up', 'down'), true)) {
            throw new InvalidArgumentException('Direction must be "up" or "down".');
        }
        $menu->moveSequence($direction === 'up' ? MenuEntry::MOVE_UP : MenuEntry::MOVE_DOWN);
        CliApplication::writeSuccess('Menu entry moved.', $options);
        return 0;
    }

    public static function announcementList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);
        if (array_key_exists('category', $options)) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'ANN');
            $where[] = 'ann.ann_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        if (array_key_exists('search', $options)) {
            $where[] = '(UPPER(ann.ann_headline) LIKE UPPER(?) OR UPPER(ann.ann_description) LIKE UPPER(?))';
            $search = '%' . CliApplication::optionString($options, 'search') . '%';
            $params[] = $search;
            $params[] = $search;
        }
        $format = CliApplication::optionString($options, 'format', 'table');
        if (!self::restrictToVisibleCategories('ANN', 'ann.ann_cat_id', $where, $params)) {
            CliApplication::writeRows(array(), $format, $options);
            return 0;
        }

        $limit = max(0, CliApplication::optionInt($options, 'limit', 0));
        $offset = max(0, CliApplication::optionInt($options, 'offset', 0));
        $sql = 'SELECT ann.ann_id AS id, ann.ann_uuid AS uuid, ann.ann_headline AS headline,
                       cat.cat_name AS category, ann.ann_timestamp_create AS created_at
                  FROM ' . TBL_ANNOUNCEMENTS . ' ann
            INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = ann.ann_cat_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY ann.ann_timestamp_create DESC';

        // Visibility is part of the query now, so the database can do the paging.
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } elseif ($offset > 0) {
            $sql .= ' LIMIT ' . PHP_INT_MAX . ' OFFSET ' . $offset;
        }

        CliApplication::writeRows($gDb->queryPrepared($sql, $params)->fetchAll(), $format, $options);
        return 0;
    }

    public static function announcementShow(array $arguments, array $options): int
    {
        $announcement = self::resolveAnnouncement(CliApplication::requireArgument($arguments, 0, 'announcement'));
        if (!$announcement->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(array(
            'id' => (int)$announcement->getValue('ann_id'),
            'uuid' => $announcement->getValue('ann_uuid'),
            'category_id' => (int)$announcement->getValue('ann_cat_id'),
            'headline' => $announcement->getValue('ann_headline', 'database'),
            'description' => $announcement->getValue('ann_description', 'database'),
            'created_at' => $announcement->getValue('ann_timestamp_create')
        ), $options);
        return 0;
    }

    public static function announcementAdd(array $arguments, array $options): int
    {
        global $gDb;
        $announcement = new Announcement($gDb);
        self::applyAnnouncementOptions($announcement, $options, true);
        $changed = $announcement->save();
        if ($changed) {
            $announcement->sendNotification();
        }
        CliApplication::writeValue(array('id' => (int)$announcement->getValue('ann_id'), 'uuid' => (string)$announcement->getValue('ann_uuid')), $options);
        return 0;
    }

    public static function announcementUpdate(array $arguments, array $options): int
    {
        $announcement = self::resolveAnnouncement(CliApplication::requireArgument($arguments, 0, 'announcement'));
        if (!$announcement->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        self::applyAnnouncementOptions($announcement, $options, false);
        if ($announcement->save()) {
            $announcement->sendNotification();
        }
        CliApplication::writeSuccess('Announcement updated.', $options);
        return 0;
    }

    public static function announcementCopy(array $arguments, array $options): int
    {
        global $gDb;
        $source = self::resolveAnnouncement(CliApplication::requireArgument($arguments, 0, 'announcement'));
        if (!$source->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $copy = new Announcement($gDb);
        $copy->setValue('ann_headline', array_key_exists('headline', $options) ? self::requireTextOption($options, 'headline') : (string)$source->getValue('ann_headline', 'database'));
        $copy->setValue('ann_description', (string)$source->getValue('ann_description', 'database'));
        if (array_key_exists('category', $options)) {
            $copy->setValue('ann_cat_id', (int)self::resolveCategory(CliApplication::optionString($options, 'category'), 'ANN')->getValue('cat_id'));
        } else {
            $copy->setValue('ann_cat_id', (int)$source->getValue('ann_cat_id'));
        }
        $copy->save();
        $copy->sendNotification();
        CliApplication::writeValue(array('id' => (int)$copy->getValue('ann_id'), 'uuid' => (string)$copy->getValue('ann_uuid')), $options);
        return 0;
    }

    public static function announcementDelete(array $arguments, array $options): int
    {
        $announcement = self::resolveAnnouncement(CliApplication::requireArgument($arguments, 0, 'announcement'));
        if (!$announcement->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete this announcement?', $options);
        $announcement->delete();
        CliApplication::writeSuccess('Announcement deleted.', $options);
        return 0;
    }

    public static function announcementExportRss(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrganization;

        $service = new AnnouncementsService(
            $gDb,
            CliApplication::optionExists($options, 'category')
                ? (string)self::resolveCategory(
                    CliApplication::optionString($options, 'category'),
                    'ANN'
                )->getValue('cat_uuid')
                : ''
        );

        CliApplication::writeOutput(
            $service->getRssFeedContent((string)$gCurrentOrganization->getValue('org_shortname')),
            $options
        );
        return 0;
    }

    public static function forumList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);
        if (array_key_exists('category', $options)) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'FOT');
            $where[] = 'fot.fot_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        $format = CliApplication::optionString($options, 'format', 'table');
        if (!self::restrictToVisibleCategories('FOT', 'fot.fot_cat_id', $where, $params)) {
            CliApplication::writeRows(array(), $format, $options);
            return 0;
        }

        $rows = $gDb->queryPrepared(
            'SELECT fot.fot_id AS id, fot.fot_uuid AS uuid, fot.fot_title AS title,
                    cat.cat_name AS category, fot.fot_views AS views, fot.fot_timestamp_create AS created_at
               FROM ' . TBL_FORUM_TOPICS . ' fot
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = fot.fot_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY fot.fot_timestamp_create DESC',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, $format, $options);
        return 0;
    }

    public static function forumTopic(array $arguments, array $options): int
    {
        global $gDb;
        $topic = self::resolveTopic(CliApplication::requireArgument($arguments, 0, 'topic'));
        if (!$topic->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $posts = $gDb->queryPrepared(
            'SELECT fop_id AS id, fop_uuid AS uuid, fop_text AS text,
                    fop_usr_id_create AS user_id, fop_timestamp_create AS created_at,
                    fop_timestamp_change AS changed_at
               FROM ' . TBL_FORUM_POSTS . '
              WHERE fop_fot_id = ?
           ORDER BY fop_timestamp_create, fop_id',
            array((int)$topic->getValue('fot_id'))
        )->fetchAll();
        CliApplication::writeValue(array(
            'id' => (int)$topic->getValue('fot_id'),
            'uuid' => $topic->getValue('fot_uuid'),
            'category_id' => (int)$topic->getValue('fot_cat_id'),
            'title' => $topic->getValue('fot_title', 'database'),
            'posts' => $posts
        ), $options);
        return 0;
    }

    public static function forumTopicAdd(array $arguments, array $options): int
    {
        global $gDb;
        $topic = new Topic($gDb);
        $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'FOT');
        $topic->setValue('fot_cat_id', (int)$category->getValue('cat_id'));
        $topic->setValue('fot_title', self::requireTextOption($options, 'title'));
        $topic->setValue('fop_text', CliApplication::optionString($options, 'text'));
        if ($topic->save()) {
            $topic->sendNotification();
        }
        CliApplication::writeValue(array('id' => (int)$topic->getValue('fot_id'), 'uuid' => (string)$topic->getValue('fot_uuid')), $options);
        return 0;
    }

    public static function forumTopicUpdate(array $arguments, array $options): int
    {
        $topic = self::resolveTopic(CliApplication::requireArgument($arguments, 0, 'topic'));
        if (!$topic->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (array_key_exists('category', $options)) {
            $topic->setValue('fot_cat_id', (int)self::resolveCategory(CliApplication::optionString($options, 'category'), 'FOT')->getValue('cat_id'));
        }
        if (array_key_exists('title', $options)) {
            $topic->setValue('fot_title', self::requireTextOption($options, 'title'));
        }
        if ($topic->save()) {
            $topic->sendNotification();
        }
        CliApplication::writeSuccess('Forum topic updated.', $options);
        return 0;
    }

    public static function forumTopicDelete(array $arguments, array $options): int
    {
        $topic = self::resolveTopic(CliApplication::requireArgument($arguments, 0, 'topic'));
        if (!$topic->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete this forum topic?', $options);
        $topic->delete();
        CliApplication::writeSuccess('Forum topic deleted.', $options);
        return 0;
    }

    public static function forumPostAdd(array $arguments, array $options): int
    {
        global $gDb;
        $topic = self::resolveTopic(CliApplication::requireArgument($arguments, 0, 'topic'));
        if (!$topic->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $post = new Post($gDb);
        $post->setValue('fop_fot_id', (int)$topic->getValue('fot_id'));
        $post->setValue('fop_text', CliApplication::optionString($options, 'text'));
        if ($post->save()) {
            $post->sendNotification();
        }
        CliApplication::writeValue(array('id' => (int)$post->getValue('fop_id'), 'uuid' => (string)$post->getValue('fop_uuid')), $options);
        return 0;
    }

    public static function forumPostUpdate(array $arguments, array $options): int
    {
        global $gCurrentUser, $gDb;
        $post = self::resolvePost(CliApplication::requireArgument($arguments, 0, 'post'));
        $topic = new Topic($gDb, (int)$post->getValue('fop_fot_id'));
        if (!$topic->isEditable()
            && !$gCurrentUser->isAdministratorForum()
            && (int)$post->getValue('fop_usr_id_create') !== (int)$gCurrentUser->getValue('usr_id')) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $post->setValue('fop_text', CliApplication::optionString($options, 'text'));
        if ($post->save()) {
            $post->sendNotification();
        }
        CliApplication::writeSuccess('Forum post updated.', $options);
        return 0;
    }

    public static function forumPostDelete(array $arguments, array $options): int
    {
        global $gCurrentUser, $gDb;
        $post = self::resolvePost(CliApplication::requireArgument($arguments, 0, 'post'));
        $topic = new Topic($gDb, (int)$post->getValue('fop_fot_id'));
        if ((int)$topic->getValue('fot_fop_id_first_post') === (int)$post->getValue('fop_id')) {
            throw new InvalidArgumentException('Delete the topic to delete its first post.');
        }
        if (!$topic->isEditable()
            && !$gCurrentUser->isAdministratorForum()
            && (int)$post->getValue('fop_usr_id_create') !== (int)$gCurrentUser->getValue('usr_id')) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete this forum post?', $options);
        $post->delete();
        CliApplication::writeSuccess('Forum post deleted.', $options);
        return 0;
    }

    public static function forumExportRss(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrganization;

        $service = new ForumService(
            $gDb,
            CliApplication::optionExists($options, 'category')
                ? (string)self::resolveCategory(
                    CliApplication::optionString($options, 'category'),
                    'FOT'
                )->getValue('cat_uuid')
                : ''
        );

        CliApplication::writeOutput(
            $service->getRssFeedContent((string)$gCurrentOrganization->getValue('org_shortname')),
            $options
        );
        return 0;
    }

    public static function linkList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);
        if (array_key_exists('category', $options)) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'LNK');
            $where[] = 'lnk.lnk_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        $format = CliApplication::optionString($options, 'format', 'table');
        if (!self::restrictToVisibleCategories('LNK', 'lnk.lnk_cat_id', $where, $params)) {
            CliApplication::writeRows(array(), $format, $options);
            return 0;
        }

        $rows = $gDb->queryPrepared(
            'SELECT lnk.lnk_id AS id, lnk.lnk_uuid AS uuid, lnk.lnk_name AS name,
                    lnk.lnk_url AS url, lnk.lnk_sequence AS sequence, cat.cat_name AS category
               FROM ' . TBL_LINKS . ' lnk
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = lnk.lnk_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY cat.cat_sequence, lnk.lnk_sequence',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, $format, $options);
        return 0;
    }

    public static function linkShow(array $arguments, array $options): int
    {
        $link = self::resolveLink(CliApplication::requireArgument($arguments, 0, 'link'));
        if (!$link->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(array(
            'id' => (int)$link->getValue('lnk_id'),
            'uuid' => $link->getValue('lnk_uuid'),
            'category_id' => (int)$link->getValue('lnk_cat_id'),
            'name' => $link->getValue('lnk_name', 'database'),
            'url' => $link->getValue('lnk_url', 'database'),
            'description' => $link->getValue('lnk_description', 'database'),
            'counter' => (int)$link->getValue('lnk_counter'),
            'sequence' => (int)$link->getValue('lnk_sequence')
        ), $options);
        return 0;
    }

    public static function linkAdd(array $arguments, array $options): int
    {
        global $gDb;
        $link = new Weblink($gDb);
        self::applyLinkOptions($link, $options, true);
        if ($link->save()) {
            $link->sendNotification();
        }
        CliApplication::writeValue(array('id' => (int)$link->getValue('lnk_id'), 'uuid' => (string)$link->getValue('lnk_uuid')), $options);
        return 0;
    }

    public static function linkUpdate(array $arguments, array $options): int
    {
        $link = self::resolveLink(CliApplication::requireArgument($arguments, 0, 'link'));
        if (!$link->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        self::applyLinkOptions($link, $options, false);
        if ($link->save()) {
            $link->sendNotification();
        }
        CliApplication::writeSuccess('Web link updated.', $options);
        return 0;
    }

    public static function linkDelete(array $arguments, array $options): int
    {
        $link = self::resolveLink(CliApplication::requireArgument($arguments, 0, 'link'));
        if (!$link->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete this web link?', $options);
        $link->delete();
        CliApplication::writeSuccess('Web link deleted.', $options);
        return 0;
    }

    public static function linkMove(array $arguments, array $options): int
    {
        $link = self::resolveLink(CliApplication::requireArgument($arguments, 0, 'link'));
        if (!$link->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $direction = strtolower(CliApplication::requireArgument($arguments, 1, 'direction'));
        if (!in_array($direction, array('up', 'down'), true)) {
            throw new InvalidArgumentException('Direction must be "up" or "down".');
        }
        $link->moveSequence($direction === 'up' ? Weblink::MOVE_UP : Weblink::MOVE_DOWN);
        CliApplication::writeSuccess('Web link moved.', $options);
        return 0;
    }

    public static function messageList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUserId;

        $params = array($gCurrentUserId, $gCurrentUserId);
        $condition = '';
        $type = strtoupper(CliApplication::optionString($options, 'type'));
        if ($type !== '') {
            $condition .= ' AND msg_type = ?';
            $params[] = $type === 'PM' ? 'PM' : 'EMAIL';
        }

        $limit = max(0, CliApplication::optionInt($options, 'limit', 0) ?? 0);
        $offset = max(0, CliApplication::optionInt($options, 'offset', 0) ?? 0);
        $sql = 'SELECT msg_id AS id, msg_uuid AS uuid, msg_type AS type, msg_subject AS subject,
                       msg_usr_id_sender AS sender_id, msg_timestamp AS timestamp, msg_read AS read_state,
                       (SELECT COUNT(*) FROM ' . TBL_MESSAGES_ATTACHMENTS . ' WHERE msa_msg_id = msg_id) AS attachments
                  FROM ' . TBL_MESSAGES . '
                 WHERE (msg_usr_id_sender = ?
                    OR EXISTS (
                        SELECT 1
                          FROM ' . TBL_MESSAGES_RECIPIENTS . '
                         WHERE msr_msg_id = msg_id
                           AND msg_type = \'PM\'
                           AND msr_usr_id = ?
                    ))' . $condition . '
              ORDER BY msg_timestamp DESC, msg_id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        CliApplication::writeRows(
            $gDb->queryPrepared($sql, $params)->fetchAll(),
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function messageShow(array $arguments, array $options): int
    {
        $message = self::resolveMessage(CliApplication::requireArgument($arguments, 0, 'message'));
        self::assertMessageAccess($message);

        CliApplication::writeValue(self::messageData($message), $options);
        return 0;
    }

    public static function messageSend(array $arguments, array $options): int
    {
        global $gDb;
        $type = strtolower(CliApplication::optionString($options, 'type'));
        if (!in_array($type, array('email', 'pm'), true)) {
            throw new InvalidArgumentException('--type must be email or pm.');
        }

        $subject = CliApplication::optionString($options, 'subject');
        if ($subject === '') {
            throw new InvalidArgumentException('--subject is required.');
        }

        $body = self::readTextOption($options, 'body', 'body-file', true);
        $recipients = self::buildMessageRecipients($options, $type);
        $attachments = $type === 'email' ? self::buildMessageAttachments($options) : array();

        $userUuid = '';
        if ($type === 'pm') {
            if (count($recipients) !== 1 || str_contains($recipients[0], ':')) {
                throw new InvalidArgumentException('A private message requires exactly one --user recipient.');
            }
            $userUuid = $recipients[0];
        }

        $message = (new MessageService($gDb))->sendData(
            $type,
            $subject,
            $body,
            $recipients,
            '',
            $userUuid,
            '',
            $attachments,
            '',
            '',
            CliApplication::optionBool($options, 'delivery-confirmation', false) ?? false,
            CliApplication::optionBool($options, 'carbon-copy', false) ?? false
        );

        CliApplication::writeValue(self::messageData($message), $options);
        return 0;
    }

    public static function messageReply(array $arguments, array $options): int
    {
        global $gDb;
        $message = self::resolveMessage(CliApplication::requireArgument($arguments, 0, 'message'));
        self::assertMessageAccess($message);

        if ((string)$message->getValue('msg_type') !== Message::MESSAGE_TYPE_PM) {
            throw new InvalidArgumentException('message:reply can only be used with private-message conversations.');
        }

        if (CliApplication::optionExists($options, 'attachment')) {
            throw new InvalidArgumentException('Private messages do not support attachments.');
        }

        $body = self::readTextOption($options, 'body', 'body-file', true);
        $savedMessage = (new MessageService($gDb))->sendData(
            Message::MESSAGE_TYPE_PM,
            (string)$message->getValue('msg_subject', 'database'),
            $body,
            array(),
            (string)$message->getValue('msg_uuid')
        );

        CliApplication::writeValue(self::messageData($savedMessage), $options);
        return 0;
    }

    public static function messageForward(array $arguments, array $options): int
    {
        global $gDb;
        $message = self::resolveMessage(CliApplication::requireArgument($arguments, 0, 'message'));
        self::assertMessageAccess($message);

        if ((string)$message->getValue('msg_type') !== Message::MESSAGE_TYPE_EMAIL) {
            throw new InvalidArgumentException('message:forward can only be used with email messages.');
        }

        $recipients = self::buildMessageRecipients($options, 'email');
        $subject = CliApplication::optionExists($options, 'subject')
            ? CliApplication::optionString($options, 'subject')
            : (string)$message->getValue('msg_subject', 'database');

        if ($subject === '') {
            throw new InvalidArgumentException('--subject must not be empty.');
        }

        if (CliApplication::optionExists($options, 'body')
            || CliApplication::optionExists($options, 'body-file')) {
            $body = self::readTextOption($options, 'body', 'body-file');
        } else {
            $body = $message->getContent('database');
        }

        $savedMessage = (new MessageService($gDb))->sendData(
            Message::MESSAGE_TYPE_EMAIL,
            $subject,
            $body,
            $recipients,
            '',
            '',
            '',
            self::buildMessageAttachments($options),
            '',
            '',
            CliApplication::optionBool($options, 'delivery-confirmation', false) ?? false,
            CliApplication::optionBool($options, 'carbon-copy', false) ?? false
        );

        CliApplication::writeValue(self::messageData($savedMessage), $options);
        return 0;
    }

    public static function messageDelete(array $arguments, array $options): int
    {
        global $gDb;

        CliApplication::confirm('Delete the selected message record(s)?', $options);

        // Resolve and check every reference before the first message is deleted.
        $messages = array();
        foreach ($arguments as $reference) {
            $message = self::resolveMessage($reference);
            self::assertMessageAccess($message);

            // Message::delete() implements the two-participant lifecycle for private messages.
            // Email records remain deletable only by their sender.
            if ((int)$message->getValue('msg_usr_id_sender') !== $GLOBALS['gCurrentUserId']
                && (string)$message->getValue('msg_type') !== Message::MESSAGE_TYPE_PM) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $messages[] = $message;
        }

        $gDb->startTransaction();
        try {
            foreach ($messages as $message) {
                $message->delete();
            }
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Message record(s) deleted.', $options);
        return 0;
    }

    public static function messageAttachments(array $arguments, array $options): int
    {
        $message = self::resolveMessage(CliApplication::requireArgument($arguments, 0, 'message'));
        self::assertMessageAccess($message);
        CliApplication::writeRows(
            $message->getAttachmentsInformation(),
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }


    public static function messageGetAttachment(array $arguments, array $options): int
    {
        global $gDb;

        $attachmentUuid = CliApplication::requireArgument($arguments, 0, 'attachment');
        $attachment = new Entity($gDb, TBL_MESSAGES_ATTACHMENTS, 'msa');

        if (!$attachment->readDataByUuid($attachmentUuid)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $message = new Message($gDb, (int)$attachment->getValue('msa_msg_id'));
        self::assertMessageAccess($message);

        $source = ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/'
            . $attachment->getValue('msa_file_name');
        if (!is_file($source) || !is_readable($source)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $filename = (string)$attachment->getValue('msa_original_file_name');
        if ($filename === '') {
            $filename = (string)$attachment->getValue('msa_file_name');
        }
        $target = CliApplication::resolveOutputPath(
            $options,
            FileSystemUtils::getSanitizedPathEntry($filename)
        );

        if (!@copy($source, $target)) {
            throw new RuntimeException('Could not copy attachment to "' . $target . '".');
        }

        CliApplication::writeSuccess('Attachment written to ' . $target . '.', $options);
        return 0;
    }

    public static function documentDownload(array $arguments, array $options): int
    {
        global $gDb;

        $fileUUID = CliApplication::requireArgument($arguments, 0, 'file');
        $file = (new DocumentsService($gDb))->prepareFileDownload($fileUUID);

        $source = $file->getFullFilePath();
        $target = CliApplication::resolveOutputPath(
            $options,
            (string)$file->getValue('fil_name', 'database')
        );

        if (!@copy($source, $target)) {
            throw new RuntimeException('Could not copy document to "' . $target . '".');
        }

        CliApplication::writeSuccess('Document written to ' . $target . '.', $options);
        return 0;
    }


    public static function documentUpload(array $arguments, array $options): int
    {
        global $gDb;

        $sourcePath = CliApplication::requireArgument($arguments, 0, 'filepath');
        $folder = self::resolveFolder(CliApplication::optionString($options, 'folder'));
        $service = new DocumentsService($gDb, (string)$folder->getValue('fol_uuid'));
        $file = $service->uploadFile($sourcePath, CliApplication::optionString($options, 'name'));

        CliApplication::writeSuccess(
            'Uploaded document ' . $file->getValue('fil_uuid') . '.',
            $options
        );
        return 0;
    }

    public static function documentList(array $arguments, array $options): int
    {
        $folder = self::resolveFolder($arguments[0] ?? '');
        $rows = array();
        self::collectFolderContents(
            $folder,
            $rows,
            CliApplication::optionBool($options, 'recursive', false) ?? false
        );
        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function documentFileRename(array $arguments, array $options): int
    {
        global $gDb;
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $folder = new Folder($gDb, (int)$file->getValue('fil_fol_id'));
        if (!$folder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $newName = CliApplication::requireArgument($arguments, 1, 'name');
        if (!StringUtils::strIsValidFileName($newName)) {
            throw new Exception('SYS_FILENAME_INVALID');
        }
        if (!FileSystemUtils::allowedFileExtension($newName)) {
            throw new Exception('SYS_FILE_EXTENSION_INVALID');
        }

        $oldPath = $file->getFullFilePath();
        $newPath = $file->getFullFolderPath() . '/' . $newName;
        if ($oldPath !== $newPath) {
            FileSystemUtils::moveFile($oldPath, $newPath);
            $file->setValue('fil_name', $newName);
            $file->save();
        }
        CliApplication::writeSuccess('Document renamed.', $options);
        return 0;
    }

    public static function documentFileMove(array $arguments, array $options): int
    {
        global $gDb;
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $source = new Folder($gDb, (int)$file->getValue('fil_fol_id'));
        if (!$source->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $destination = self::resolveFolder(CliApplication::requireArgument($arguments, 1, 'destination-folder'));
        if (!$destination->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $file->moveToFolder((string)$destination->getValue('fol_uuid'));
        CliApplication::writeSuccess('Document moved.', $options);
        return 0;
    }

    public static function documentFileDelete(array $arguments, array $options): int
    {
        global $gDb;
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $folder = new Folder($gDb, (int)$file->getValue('fil_fol_id'));
        if (!$folder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete document "' . $file->getValue('fil_name') . '"?', $options);
        $file->delete();
        CliApplication::writeSuccess('Document deleted.', $options);
        return 0;
    }

    public static function documentFolderAdd(array $arguments, array $options): int
    {
        $parent = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'parent-folder'));
        if (!$parent->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $name = CliApplication::requireArgument($arguments, 1, 'name');
        if (!StringUtils::strIsValidFolderName($name)) {
            throw new Exception('SYS_FOLDER_NAME_INVALID');
        }
        $error = $parent->createFolder($name);
        if ($error !== null) {
            throw new RuntimeException($error['text'] . ': ' . $error['path']);
        }
        $parent->addFolderOrFileToDatabase($name);
        CliApplication::writeSuccess('Folder created.', $options);
        return 0;
    }

    public static function documentFolderRename(array $arguments, array $options): int
    {
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$folder->hasUploadRight() || $folder->getValue('fol_fol_id_parent') === null) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $name = CliApplication::requireArgument($arguments, 1, 'name');
        if (!StringUtils::strIsValidFolderName($name)) {
            throw new Exception('SYS_FOLDER_NAME_INVALID');
        }
        $oldPath = $folder->getFullFolderPath();
        $parentPath = (string)$folder->getValue('fol_path', 'database');
        $newPath = ADMIDIO_PATH . $parentPath . '/' . $name;
        if ($oldPath !== $newPath) {
            FileSystemUtils::moveDirectory($oldPath, $newPath);
            $folder->rename($name, $parentPath);
        }
        CliApplication::writeSuccess('Folder renamed.', $options);
        return 0;
    }

    public static function documentFolderMove(array $arguments, array $options): int
    {
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$folder->hasUploadRight() || $folder->getValue('fol_fol_id_parent') === null) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $destination = self::resolveFolder(CliApplication::requireArgument($arguments, 1, 'destination-folder'));
        if ((int)$destination->getValue('fol_id') === (int)$folder->getValue('fol_id')) {
            throw new InvalidArgumentException('A folder cannot be moved into itself.');
        }
        $folder->moveToFolder((string)$destination->getValue('fol_uuid'));
        CliApplication::writeSuccess('Folder moved.', $options);
        return 0;
    }

    public static function documentFolderDelete(array $arguments, array $options): int
    {
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$folder->hasUploadRight() || $folder->getValue('fol_fol_id_parent') === null) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete folder "' . $folder->getValue('fol_name') . '" recursively?', $options);
        $folder->delete();
        CliApplication::writeSuccess('Folder deleted.', $options);
        return 0;
    }

    public static function documentPermissions(array $arguments, array $options): int
    {
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$folder->hasViewRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(array(
            'folder_uuid' => (string)$folder->getValue('fol_uuid'),
            'public' => (bool)$folder->getValue('fol_public'),
            'view_role_ids' => $folder->getViewRolesIds(),
            'view_roles' => $folder->getViewRolesNames(),
            'upload_role_ids' => $folder->getUploadRolesIds()
        ), $options);
        return 0;
    }

    public static function documentPermissionsSet(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$gCurrentUser->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $recursive = CliApplication::optionBool($options, 'recursive', false) ?? false;

        // Resolve the roles before anything is written, so an unknown role changes nothing.
        $viewRoleIds = CliApplication::optionExists($options, 'view-role')
            ? self::resolveRoleIds(CliApplication::optionValues($options, 'view-role'))
            : null;
        $uploadRoleIds = CliApplication::optionExists($options, 'upload-role')
            ? self::resolveRoleIds(CliApplication::optionValues($options, 'upload-role'))
            : null;

        /*
         * View roles, upload roles and the public flag are three independent writes that together
         * form one permission state; a partial result would leave the folder inconsistent.
         */
        $gDb->startTransaction();
        try {
            if ($viewRoleIds !== null) {
                $current = $folder->getViewRolesIds();
                $folder->removeRolesOnFolder('folder_view', array_values(array_diff($current, $viewRoleIds)), $recursive);
                $folder->addRolesOnFolder('folder_view', array_values(array_diff($viewRoleIds, $current)), $recursive);
            }
            if ($uploadRoleIds !== null) {
                $current = $folder->getUploadRolesIds();
                $folder->removeRolesOnFolder('folder_upload', array_values(array_diff($current, $uploadRoleIds)), $recursive);
                $folder->addRolesOnFolder('folder_upload', array_values(array_diff($uploadRoleIds, $current)), $recursive);
            }
            if (CliApplication::optionExists($options, 'public')) {
                if ($recursive) {
                    $folder->editPublicFlagOnFolder(CliApplication::optionBool($options, 'public', false) ?? false);
                } else {
                    $folder->setValue('fol_public', (int)(CliApplication::optionBool($options, 'public', false) ?? false));
                }
                $folder->save();
            }

            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }

        CliApplication::writeSuccess('Folder permissions updated.', $options);
        return 0;
    }

    public static function documentUnregistered(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $folder = self::resolveFolder($arguments[0] ?? '');
        if (!$gCurrentUser->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $rows = self::getUnregisteredEntries($folder);
        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function documentRegister(array $arguments, array $options): int
    {
        global $gCurrentUser;
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$gCurrentUser->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        self::registerUnregisteredRecursive(
            $folder,
            CliApplication::optionBool($options, 'recursive', false) ?? false
        );
        CliApplication::writeSuccess('Unregistered document entries registered.', $options);
        return 0;
    }

    public static function photoList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser;

        $params = array($gCurrentOrgId);
        $parentSql = '';
        if (CliApplication::optionExists($options, 'parent')) {
            $parent = self::resolveAlbum(CliApplication::optionString($options, 'parent'));
            $parentSql = ' AND pho_pho_id_parent = ?';
            $params[] = (int)$parent->getValue('pho_id');
        }

        /*
         * Album::isVisible() requires the album to belong to the current organization and, unless
         * the user administers photos, not to be locked. Both are expressed in the query instead of
         * loading every album only to ask it.
         */
        $lockedSql = $gCurrentUser->isAdministratorPhotos() ? '' : ' AND pho_locked = false';

        $rows = $gDb->queryPrepared(
            'SELECT pho_id AS id, pho_uuid AS uuid, pho_name AS name, pho_begin AS begin,
                    pho_end AS end, pho_quantity AS quantity, pho_locked AS locked,
                    pho_pho_id_parent AS parent_id
               FROM ' . TBL_PHOTOS . '
              WHERE pho_org_id = ?' . $parentSql . $lockedSql . '
           ORDER BY pho_begin DESC, pho_name',
            $params
        )->fetchAll();

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function photoAlbumShow(array $arguments, array $options): int
    {
        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        if (!$album->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(self::albumData($album), $options);
        return 0;
    }

    public static function photoAlbumAdd(array $arguments, array $options): int
    {
        global $gDb;

        // --begin is declared as required in the registration.
        $values = self::photoAlbumFormValues($options);
        $values['pho_name'] = CliApplication::requireArgument($arguments, 0, 'name');

        $parentUuid = 'ALL';
        if (CliApplication::optionExists($options, 'parent')) {
            $parentReference = CliApplication::optionString($options, 'parent');
            $parentUuid = strtoupper($parentReference) === 'ALL'
                ? 'ALL'
                : (string)self::resolveAlbum($parentReference)->getValue('pho_uuid');
        }

        $album = new Album($gDb);
        (new AlbumService($gDb))->saveData($album, $values, $parentUuid);

        CliApplication::writeValue(self::albumData($album), $options);
        return 0;
    }

    public static function photoAlbumUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $values = self::photoAlbumFormValues($options);

        $parentUuid = 'ALL';
        if (CliApplication::optionExists($options, 'parent')) {
            $parentReference = CliApplication::optionString($options, 'parent');
            $parentUuid = strtoupper($parentReference) === 'ALL'
                ? 'ALL'
                : (string)self::resolveAlbum($parentReference)->getValue('pho_uuid');
        } elseif ((int)$album->getValue('pho_pho_id_parent') > 0) {
            $parent = new Album($gDb, (int)$album->getValue('pho_pho_id_parent'));
            $parentUuid = (string)$parent->getValue('pho_uuid');
        }

        if (count($values) === 0 && !CliApplication::optionExists($options, 'parent')) {
            throw new InvalidArgumentException('No photo album values were supplied.');
        }

        (new AlbumService($gDb))->saveData($album, $values, $parentUuid);

        CliApplication::writeValue(self::albumData($album), $options);
        return 0;
    }

    public static function photoAlbumDownload(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $archive = (new PhotoService($gDb, $album))->createAlbumArchive();

        self::moveGeneratedFile($archive['path'], $archive['filename'], $options);
        return 0;
    }

    public static function photoUpload(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $files = array_slice($arguments, 1);
        if (count($files) === 0) {
            throw new InvalidArgumentException('At least one photo file is required.');
        }

        $service = new PhotoService($gDb, $album);
        $numbers = array();

        foreach ($files as $file) {
            $numbers[] = $service->uploadFromFile((string)$file);
        }

        CliApplication::writeValue(array('photo_numbers' => $numbers), $options);
        return 0;
    }

    public static function photoDownload(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $photoNumber = self::positiveInt(
            CliApplication::requireArgument($arguments, 1, 'photo-number'),
            'photo-number'
        );
        $download = (new PhotoService($gDb, $album))->getDownloadFile($photoNumber);
        $target = CliApplication::resolveOutputPath($options, $download['filename']);

        if (!@copy($download['path'], $target)) {
            throw new RuntimeException('Could not copy photo to "' . $target . '".');
        }

        CliApplication::writeSuccess('Photo written to ' . $target . '.', $options);
        return 0;
    }

    public static function photoDelete(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $photoNumbers = array();

        foreach (array_slice($arguments, 1) as $photoNumber) {
            $photoNumbers[] = self::positiveInt((string)$photoNumber, 'photo-number');
        }

        if (count($photoNumbers) === 0) {
            throw new InvalidArgumentException('At least one photo number is required.');
        }

        $photoNumbers = array_values(array_unique($photoNumbers));
        rsort($photoNumbers, SORT_NUMERIC);

        CliApplication::confirm(
            'Delete ' . count($photoNumbers) . ' photo(s) from album "' . $album->getValue('pho_name') . '"?',
            $options
        );

        $service = new PhotoService($gDb, $album);
        foreach ($photoNumbers as $photoNumber) {
            $service->deletePhoto($photoNumber);
        }

        CliApplication::writeSuccess('Photo(s) deleted.', $options);
        return 0;
    }

    public static function photoRotate(array $arguments, array $options): int
    {
        global $gDb;

        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $photoNumber = self::positiveInt(
            CliApplication::requireArgument($arguments, 1, 'photo-number'),
            'photo-number'
        );
        $direction = CliApplication::optionString($options, 'direction');
        if (!in_array($direction, array('left', 'right'), true)) {
            throw new InvalidArgumentException('--direction must be left or right.');
        }

        (new PhotoService($gDb, $album))->rotatePhoto($photoNumber, $direction);

        CliApplication::writeSuccess('Photo rotated.', $options);
        return 0;
    }

    public static function photoAlbumDelete(array $arguments, array $options): int
    {
        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        if (!$album->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete photo album "' . $album->getValue('pho_name') . '"?', $options);
        $album->delete();
        CliApplication::writeSuccess('Photo album deleted.', $options);
        return 0;
    }

    public static function photoAlbumLock(array $arguments, array $options): int
    {
        return self::setAlbumLocked($arguments, $options, true);
    }

    public static function photoAlbumUnlock(array $arguments, array $options): int
    {
        return self::setAlbumLocked($arguments, $options, false);
    }


    public static function photoEcardTemplates(array $arguments, array $options): int
    {
        global $gL10n;

        $ecard = new ECard($gL10n);
        $rows = array();
        foreach ($ecard->getFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates') as $filename) {
            $rows[] = array('template' => $filename);
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function photoEcardSend(array $arguments, array $options): int
    {
        global $gDb;
        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        $photoNumber = self::positiveInt(
            CliApplication::requireArgument($arguments, 1, 'photo-number'),
            'photo-number'
        );
        $template = CliApplication::optionString($options, 'template');
        if ($template === '') {
            throw new InvalidArgumentException('--template is required.');
        }

        $userUuids = array();
        foreach (CliApplication::optionValues($options, 'user') as $reference) {
            $user = CliApplication::resolveUser($reference);
            $userUuids[] = (string)$user->getValue('usr_uuid');
        }

        $roleUuids = array();
        foreach (CliApplication::optionValues($options, 'group') as $reference) {
            $role = self::resolveGroup($reference);
            $roleUuids[] = (string)$role->getValue('rol_uuid');
        }

        if (count($userUuids) === 0 && count($roleUuids) === 0) {
            throw new InvalidArgumentException('At least one --user or --group recipient is required.');
        }

        $message = self::readTextOption($options, 'message', 'message-file', true);

        (new ECardService($gDb))->send(
            (string)$album->getValue('pho_uuid'),
            $photoNumber,
            $template,
            $message,
            $roleUuids,
            $userUuids
        );

        CliApplication::writeSuccess('E-card sent.', $options);
        return 0;
    }

    public static function eventList(array $arguments, array $options): int
    {
        global $gCurrentOrgId, $gDb;

        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);

        if (CliApplication::optionExists($options, 'calendar')) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'calendar'), 'EVT');
            $where[] = 'dat.dat_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        if (CliApplication::optionExists($options, 'date-from')) {
            $date = CliApplication::optionString($options, 'date-from');
            self::validateDate($date, '--date-from');
            $where[] = 'dat.dat_end >= ?';
            $params[] = $date . ' 00:00:00';
        }
        if (CliApplication::optionExists($options, 'date-to')) {
            $date = CliApplication::optionString($options, 'date-to');
            self::validateDate($date, '--date-to');
            $where[] = 'dat.dat_begin <= ?';
            $params[] = $date . ' 23:59:59';
        }

        $state = CliApplication::optionString($options, 'state', 'actual');
        if ($state === 'actual') {
            $where[] = 'dat.dat_end >= ?';
            $params[] = DATETIME_NOW;
        } elseif ($state === 'old') {
            $where[] = 'dat.dat_end < ?';
            $params[] = DATETIME_NOW;
        }

        $format = CliApplication::optionString($options, 'format', 'table');
        if (!self::restrictToVisibleCategories('EVT', 'dat.dat_cat_id', $where, $params)) {
            CliApplication::writeRows(array(), $format, $options);
            return 0;
        }

        $where[] = '(dat.dat_recurrence_status IS NULL OR dat.dat_recurrence_status <> ?)';
        $params[] = 'cancelled';

        $rows = $gDb->queryPrepared(
            'SELECT dat.dat_id AS id, dat.dat_uuid AS uuid, dat.dat_headline AS headline,
                    dat.dat_begin AS begin, dat.dat_end AS end, dat.dat_all_day AS all_day,
                    cat.cat_name AS calendar, dat.dat_location AS location,
                    dat.dat_evr_id AS recurrence_id,
                    dat.dat_recurrence_status AS recurrence_status
               FROM ' . TBL_EVENTS . ' dat
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = dat.dat_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY dat.dat_begin',
            $params
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['calendar'] = Language::translateIfTranslationStrId((string)$row['calendar']);
        }
        unset($row);

        CliApplication::writeRows($rows, $format, $options);
        return 0;
    }

    public static function eventShow(array $arguments, array $options): int
    {
        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::writeValue(self::eventData($event), $options);
        return 0;
    }

    public static function eventAdd(array $arguments, array $options): int
    {
        global $gDb;

        $event = new Event($gDb);
        $formValues = self::buildEventFormValues($event, $options, true);
        $savedEvent = (new EventService($gDb))->saveData('', $formValues);

        CliApplication::writeValue(self::eventData($savedEvent), $options);
        return 0;
    }

    public static function eventUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $formValues = self::buildEventFormValues($event, $options, false);
        $savedEvent = (new EventService($gDb))->saveData(
            (string)$event->getValue('dat_uuid'),
            $formValues,
            '',
            false,
            CliApplication::optionString($options, 'recurrence-scope', 'this')
        );

        CliApplication::writeValue(self::eventData($savedEvent), $options);
        return 0;
    }

    public static function eventCopy(array $arguments, array $options): int
    {
        global $gDb;

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $formValues = self::buildEventFormValues($event, $options, false);
        $savedEvent = (new EventService($gDb))->saveData(
            (string)$event->getValue('dat_uuid'),
            $formValues,
            '',
            true
        );

        CliApplication::writeValue(self::eventData($savedEvent), $options);
        return 0;
    }

    public static function eventDelete(array $arguments, array $options): int
    {
        global $gDb;

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::confirm('Delete event "' . $event->getValue('dat_headline') . '"?', $options);
        (new EventService($gDb))->deleteData(
            (string)$event->getValue('dat_uuid'),
            CliApplication::optionString($options, 'recurrence-scope', 'this')
        );

        CliApplication::writeSuccess('Event deleted.', $options);
        return 0;
    }

    public static function eventParticipation(array $arguments, array $options): int
    {
        global $gCurrentUser, $gCurrentUserId, $gDb, $gSettingsManager;

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if ((int)$event->getValue('dat_rol_id') === 0) {
            throw new InvalidArgumentException('Participation is not enabled for this event.');
        }

        $user = isset($arguments[1]) ? CliApplication::resolveUser($arguments[1]) : $gCurrentUser;
        $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
        $isLeader = $participants->isLeader($gCurrentUserId);
        if ((int)$user->getValue('usr_id') !== $gCurrentUserId
            && !$gCurrentUser->isAdministrator()
            && !$isLeader) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ((int)$user->getValue('usr_id') === $gCurrentUserId && !$event->allowedToParticipate() && !$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $command = CliApplication::currentCommand();

        /*
         * The deadline and the participant limit are answered from the state before anything is
         * written, the way EventService::changeParticipation() does it. Saving the membership
         * first makes the user a participant, and possibleToParticipate() then answers for
         * somebody who is already signed up: the limit would never refuse anybody.
         */
        if ($command !== 'event:cancel' && !$event->possibleToParticipate() && !$isLeader) {
            throw new Exception('SYS_PARTICIPATE_NO_RIGHTS');
        }

        $membership = new Membership($gDb);
        $membership->readDataByColumns(array(
            'mem_rol_id' => (int)$event->getValue('dat_rol_id'),
            'mem_usr_id' => (int)$user->getValue('usr_id')
        ));
        if (CliApplication::optionExists($options, 'comment')) {
            if (!(bool)$event->getValue('dat_allow_comments') && !$event->isEditable()) {
                throw new InvalidArgumentException('Comments are disabled for this event.');
            }
            $membership->setValue('mem_comment', CliApplication::optionString($options, 'comment'));
        }
        if (CliApplication::optionExists($options, 'guests')) {
            if (!(bool)$event->getValue('dat_additional_guests') && !$event->isEditable()) {
                throw new InvalidArgumentException('Additional guests are disabled for this event.');
            }

            $guests = max(0, CliApplication::optionInt($options, 'guests', 0) ?? 0);
            $maxMembers = (int)$event->getValue('dat_max_members');

            // the guests count towards the limit, so the same condition as in the event module applies
            if ($maxMembers > 0
                && $participants->getCount() + ($guests - (int)$membership->getValue('mem_count_guests')) >= $maxMembers) {
                throw new Exception('SYS_ROLE_MAX_MEMBERS', array($event->getValue('dat_headline')));
            }

            $membership->setValue('mem_count_guests', $guests);
        }
        if ($membership->isNewRecord()) {
            $membership->setValue('mem_begin', DATE_NOW);
        }
        $membership->save();

        if ($command === 'event:participate') {
            $membership->startMembership(
                (int)$event->getValue('dat_rol_id'),
                (int)$user->getValue('usr_id'),
                null,
                Participants::PARTICIPATION_YES
            );
        } elseif ($command === 'event:maybe') {
            $membership->startMembership(
                (int)$event->getValue('dat_rol_id'),
                (int)$user->getValue('usr_id'),
                null,
                Participants::PARTICIPATION_MAYBE
            );
        } else {
            if ($gSettingsManager->getBool('events_save_cancellations')) {
                $membership->startMembership(
                    (int)$event->getValue('dat_rol_id'),
                    (int)$user->getValue('usr_id'),
                    null,
                    Participants::PARTICIPATION_NO
                );
            } else {
                $membership->deleteMembership((int)$event->getValue('dat_rol_id'), (int)$user->getValue('usr_id'));
            }
        }

        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Event participation updated.', $options);
        return 0;
    }

    public static function eventParticipants(array $arguments, array $options): int
    {
        global $gDb;

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ((int)$event->getValue('dat_rol_id') === 0) {
            CliApplication::writeRows(array(), CliApplication::optionString($options, 'format', 'table'), $options);
            return 0;
        }

        /*
         * Participants::getParticipantsArray() answers the columns the participant list of the
         * module shows. The comment and the number of guests that event:participate writes are
         * not among them, so they are read here and the command can report what it stored.
         */
        $additional = $gDb->queryPrepared(
            'SELECT mem_usr_id, mem_comment, mem_count_guests
               FROM ' . TBL_MEMBERS . '
              WHERE mem_rol_id = ?
                AND mem_begin <= ?
                AND mem_end    > ?',
            array((int)$event->getValue('dat_rol_id'), DATE_NOW, DATE_NOW)
        )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

        $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
        $rows = array();
        foreach ($participants->getParticipantsArray() as $usrId => $participant) {
            $participant['comment'] = (string)($additional[$usrId]['mem_comment'] ?? '');
            $participant['count_guests'] = (int)($additional[$usrId]['mem_count_guests'] ?? 0);
            $rows[] = $participant;
        }

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function eventExport(array $arguments, array $options): int
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new Exception('SYS_ICAL_DISABLED');
        }

        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $events = new \ModuleEvents();
        $events->setParameter('dat_uuid', (string)$event->getValue('dat_uuid'));

        CliApplication::writeOutput((string)$events->getICalContent(), $options);
        return 0;
    }

    public static function eventExportCalendar(array $arguments, array $options): int
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('events_ical_export_enabled')) {
            throw new Exception('SYS_ICAL_DISABLED');
        }

        $dateFrom = CliApplication::optionString($options, 'date-from', date('Y-m-d', strtotime('-6 months')));
        $dateTo = CliApplication::optionString($options, 'date-to', DATE_MAX);
        self::validateDate($dateFrom, '--date-from');
        self::validateDate($dateTo, '--date-to');

        if ($dateFrom > $dateTo) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        $events = new \ModuleEvents();
        $events->setDateRange($dateFrom, $dateTo);

        if (CliApplication::optionExists($options, 'calendar')) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'calendar'), 'EVT');
            $events->setParameter('cat_uuid', (string)$category->getValue('cat_uuid'));
        }

        CliApplication::writeOutput((string)$events->getICalContent(), $options);
        return 0;
    }

    public static function roomList(array $arguments, array $options): int
    {
        global $gDb;

        $rows = $gDb->queryPrepared(
            'SELECT room_id AS id, room_uuid AS uuid, room_name AS name,
                    room_capacity AS capacity, room_overhang AS overhang, room_description AS description
               FROM ' . TBL_ROOMS . '
           ORDER BY room_name'
        )->fetchAll();

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function roomShow(array $arguments, array $options): int
    {
        $room = self::resolveRoom(CliApplication::requireArgument($arguments, 0, 'room'));
        CliApplication::writeValue(self::roomData($room), $options);
        return 0;
    }

    public static function roomAdd(array $arguments, array $options): int
    {
        global $gDb;

        $room = new Room($gDb);
        $room->setValue('room_name', CliApplication::requireArgument($arguments, 0, 'name'));
        self::applyRoomOptions($room, $options);
        $room->save();

        CliApplication::writeValue(self::roomData($room), $options);
        return 0;
    }

    public static function roomUpdate(array $arguments, array $options): int
    {
        $room = self::resolveRoom(CliApplication::requireArgument($arguments, 0, 'room'));
        if (CliApplication::optionExists($options, 'name')) {
            $room->setValue('room_name', self::requireTextOption($options, 'name'));
        }
        self::applyRoomOptions($room, $options);
        $room->save();

        CliApplication::writeSuccess('Room updated.', $options);
        return 0;
    }

    public static function roomDelete(array $arguments, array $options): int
    {
        global $gDb;

        $room = self::resolveRoom(CliApplication::requireArgument($arguments, 0, 'room'));
        $used = (int)$gDb->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_EVENTS . ' WHERE dat_room_id = ?',
            array((int)$room->getValue('room_id'))
        )->fetchColumn();
        if ($used > 0) {
            throw new Exception('SYS_ROOM_COULD_NOT_BE_DELETED');
        }

        CliApplication::confirm('Delete room "' . $room->getValue('room_name') . '"?', $options);
        $room->delete();

        CliApplication::writeSuccess('Room deleted.', $options);
        return 0;
    }

    public static function inventoryList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $statusFilter = CliApplication::optionString($options, 'status', 'active');
        $items->showRetiredItems($statusFilter === 'all' || $statusFilter === 'retired');
        $items->readItems();

        $categoryId = null;
        if (CliApplication::optionExists($options, 'category')) {
            $categoryId = (int)self::resolveCategory(CliApplication::optionString($options, 'category'), 'IVT')->getValue('cat_id');
        }
        $search = StringUtils::strToLower(CliApplication::optionString($options, 'search'));
        $rows = array();

        /*
         * ItemsData::isRetired() reads the STATUS select options again for every item it is called
         * on. The status id is already part of the rows returned by readItems(), so the id of the
         * retired option is resolved once and compared directly. That also lets the category and
         * status filters run before readItemData(), which is the expensive call per item.
         */
        $retiredStatusId = self::inventoryRetiredStatusId($items);

        foreach ($items->getItems() as $itemRow) {
            if ($categoryId !== null && (int)$itemRow['ini_cat_id'] !== $categoryId) {
                continue;
            }

            $isRetired = $retiredStatusId > 0 && (int)$itemRow['ini_status'] === $retiredStatusId;
            if (($statusFilter === 'retired' && !$isRetired)
                || ($statusFilter === 'active' && $isRetired)) {
                continue;
            }

            $items->readItemData((string)$itemRow['ini_uuid']);

            $row = array(
                'id' => (int)$itemRow['ini_id'],
                'uuid' => (string)$itemRow['ini_uuid'],
                'category_id' => (int)$itemRow['ini_cat_id'],
                'status_id' => (int)$itemRow['ini_status']
            );
            foreach ($items->getItemFields() as $name => $field) {
                $row[(string)$name] = $items->getValue((string)$name, 'database');
            }
            if ($search !== '' && !str_contains(StringUtils::strToLower(implode(' ', array_map('strval', $row))), $search)) {
                continue;
            }
            $rows[] = $row;
        }

        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function inventoryShow(array $arguments, array $options): int
    {
        $itemData = self::resolveItemData(CliApplication::requireArgument($arguments, 0, 'item'));
        CliApplication::writeValue(self::itemData($itemData), $options);
        return 0;
    }

    public static function inventoryAdd(array $arguments, array $options): int
    {
        global $gDb;

        $formValues = self::inventoryFormValues(CliApplication::optionValues($options, 'field'));
        if (!isset($formValues['INF-CATEGORY']) || $formValues['INF-CATEGORY'] === '') {
            throw new InvalidArgumentException('A CATEGORY inventory field assignment is required.');
        }
        self::validateRequiredInventoryFields($formValues);

        (new ItemService($gDb))->saveData($formValues);
        CliApplication::writeSuccess('Inventory item created.', $options);
        return 0;
    }

    public static function inventoryUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $uuid = self::resolveItemUuid(CliApplication::requireArgument($arguments, 0, 'item'));
        $formValues = self::inventoryFormValues(CliApplication::optionValues($options, 'field'));

        if (count($formValues) === 0) {
            throw new InvalidArgumentException('At least one --field assignment is required.');
        }

        (new ItemService($gDb, $uuid))->saveData($formValues, true);
        CliApplication::writeSuccess('Inventory item updated.', $options);
        return 0;
    }

    public static function inventoryCopy(array $arguments, array $options): int
    {
        global $gDb;

        $source = self::resolveItemData(CliApplication::requireArgument($arguments, 0, 'item'));
        $formValues = array();

        foreach ($source->getItemFields() as $field) {
            $name = (string)$field->getValue('inf_name_intern');
            $formValues['INF-' . $name] = $source->getValue($name, 'database');
        }

        foreach (self::inventoryFormValues(CliApplication::optionValues($options, 'field')) as $key => $value) {
            $formValues[$key] = $value;
        }

        $copies = CliApplication::optionExists($options, 'copies')
            ? self::positiveInt(CliApplication::optionString($options, 'copies'), '--copies')
            : 1;

        $numberFieldId = 0;
        if (CliApplication::optionExists($options, 'number-field')) {
            $numberField = self::resolveInventoryField(CliApplication::optionString($options, 'number-field'));
            if ((string)$numberField->getValue('inf_type') !== 'NUMBER') {
                throw new InvalidArgumentException('--number-field must reference an inventory NUMBER field.');
            }
            $numberFieldId = (int)$numberField->getValue('inf_id');
        }

        (new ItemService($gDb, '', $numberFieldId, $copies))->saveData($formValues);
        CliApplication::writeSuccess('Inventory item copied.', $options);
        return 0;
    }

    public static function inventoryDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete the selected inventory item(s)?', $options);
        self::applyToInventoryItems(
            $arguments,
            static function (ItemService $service): void {
                $service->delete();
            }
        );
        CliApplication::writeSuccess('Inventory item(s) deleted.', $options);
        return 0;
    }

    public static function inventoryRetire(array $arguments, array $options): int
    {
        self::applyToInventoryItems(
            $arguments,
            static function (ItemService $service): void {
                $service->retireItem();
            }
        );
        CliApplication::writeSuccess('Inventory item(s) retired.', $options);
        return 0;
    }

    public static function inventoryReinstate(array $arguments, array $options): int
    {
        self::applyToInventoryItems(
            $arguments,
            static function (ItemService $service): void {
                $service->reinstateItem();
            }
        );
        CliApplication::writeSuccess('Inventory item(s) reinstated.', $options);
        return 0;
    }

    /**
     * Resolve every item reference first and then run $operation for all of them in one
     * transaction, so an unknown reference in the middle of the argument list cannot leave the
     * preceding items already changed.
     *
     * @param array<int,string> $references
     */
    private static function applyToInventoryItems(array $references, callable $operation): void
    {
        global $gDb;

        $uuids = array();
        foreach ($references as $reference) {
            $uuids[] = self::resolveItemUuid((string)$reference);
        }

        $gDb->startTransaction();
        try {
            foreach ($uuids as $uuid) {
                $operation(new ItemService($gDb, $uuid));
            }
            $gDb->endTransaction();
        } catch (\Throwable $exception) {
            $gDb->rollback();
            throw $exception;
        }
    }

    public static function inventoryCheckout(array $arguments, array $options): int
    {
        $item = self::resolveItemData(CliApplication::requireArgument($arguments, 0, 'item'));
        if (!$item->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ($item->isRetired()) {
            throw new InvalidArgumentException('A retired inventory item cannot be checked out.');
        }
        if ($item->isBorrowed()) {
            throw new InvalidArgumentException('The inventory item is already checked out.');
        }

        $user = CliApplication::resolveUser(CliApplication::optionString($options, 'user'));
        $date = CliApplication::optionString($options, 'date', DATE_NOW);
        self::validateDate($date, '--date');
        $item->setValue('LAST_RECEIVER', (int)$user->getValue('usr_id'));
        $item->setValue('BORROW_DATE', $date);
        $item->setValue('RETURN_DATE', '');
        $item->saveItemData();
        $item->sendNotification();

        CliApplication::writeSuccess('Inventory item checked out.', $options);
        return 0;
    }

    public static function inventoryReturn(array $arguments, array $options): int
    {
        $item = self::resolveItemData(CliApplication::requireArgument($arguments, 0, 'item'));
        if (!$item->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if (!$item->isBorrowed()) {
            throw new InvalidArgumentException('The inventory item is not currently checked out.');
        }
        $date = CliApplication::optionString($options, 'date', DATE_NOW);
        self::validateDate($date, '--date');
        $item->setValue('RETURN_DATE', $date);
        $item->saveItemData();
        $item->sendNotification();
        CliApplication::writeSuccess('Inventory item returned.', $options);
        return 0;
    }


    public static function inventoryPictureSet(array $arguments, array $options): int
    {
        global $gDb;
        $itemUuid = self::resolveItemUuid(CliApplication::requireArgument($arguments, 0, 'item'));
        $sourcePath = CliApplication::requireArgument($arguments, 1, 'file');

        (new ItemService($gDb, $itemUuid))->saveItemPictureFromFile($sourcePath);

        CliApplication::writeSuccess('Inventory item picture updated.', $options);
        return 0;
    }

    public static function inventoryPictureGet(array $arguments, array $options): int
    {
        global $gDb;
        $itemUuid = self::resolveItemUuid(CliApplication::requireArgument($arguments, 0, 'item'));
        $picture = (new ItemService($gDb, $itemUuid))->getItemPictureData();

        self::writeExportContent($picture, $options);
        return 0;
    }

    public static function inventoryPictureDelete(array $arguments, array $options): int
    {
        global $gDb;
        $itemUuid = self::resolveItemUuid(CliApplication::requireArgument($arguments, 0, 'item'));

        CliApplication::confirm('Delete this inventory item picture?', $options);
        (new ItemService($gDb, $itemUuid))->deleteItemPicture();

        CliApplication::writeSuccess('Inventory item picture deleted.', $options);
        return 0;
    }

    /**
     * Read the import file and resolve the field mapping, without writing anything.
     *
     * Shared by inventory:import and inventory:import-check so the preview is guaranteed to
     * describe the same mapping the import would use.
     *
     * @param array<int,string> $arguments
     * @param array<string,mixed> $options
     * @return array{service:ImportService,rows:array<int,array<int,mixed>>,mapping:array<int,int>,firstRow:bool}
     */
    private static function prepareInventoryImport(array $arguments, array $options): array
    {
        global $gDb, $gCurrentOrgId, $gSettingsManager;

        $file = CliApplication::requireArgument($arguments, 0, 'file');
        $inputFormat = strtoupper(CliApplication::optionString($options, 'input-format', 'AUTO'));
        $encoding = CliApplication::optionString($options, 'encoding');

        $separator = match (CliApplication::optionString($options, 'separator', 'auto')) {
            'comma' => ',',
            'semicolon' => ';',
            'tab' => "\t",
            'pipe' => '|',
            default => ''
        };
        $enclosure = match (CliApplication::optionString($options, 'enclosure', 'auto')) {
            'none' => '',
            'double' => '"',
            'single' => '\'',
            default => 'AUTO'
        };
        $sheet = CliApplication::optionString($options, 'sheet');
        $firstRow = CliApplication::optionExists($options, 'first-row')
            ? self::parseBool(CliApplication::optionString($options, 'first-row'))
            : true;

        $importService = new ImportService();
        $importData = $importService->readImportFileData(
            $file,
            $inputFormat,
            $encoding,
            $separator,
            $enclosure,
            $sheet
        );

        if (count($importData) === 0) {
            throw new InvalidArgumentException('The import file contains no rows.');
        }

        $headers = $firstRow ? $importData[0] : array();
        $mapping = array();
        $usedColumns = array();

        foreach (CliApplication::optionValues($options, 'map') as $assignment) {
            [$fieldReference, $columnReference] = self::splitAssignment($assignment, '--map');
            $field = self::resolveInventoryField($fieldReference);
            $columnIndex = self::resolveInventoryImportColumn($columnReference, $headers, $firstRow);

            if (in_array($columnIndex, $usedColumns, true)) {
                throw new InvalidArgumentException(
                    'Import column "' . $columnReference . '" is assigned to more than one inventory field.'
                );
            }

            $mapping[(int)$field->getValue('inf_id')] = $columnIndex;
            $usedColumns[] = $columnIndex;
        }

        if (count($mapping) === 0) {
            if (!$firstRow) {
                throw new InvalidArgumentException(
                    'At least one --map=FIELD=COLUMN option is required when --first-row=false.'
                );
            }

            $itemsData = new ItemsData($gDb, $gCurrentOrgId);
            foreach ($itemsData->getItemFields() as $field) {
                $internalName = (string)$field->getValue('inf_name_intern');
                if ($gSettingsManager->getBool('inventory_items_disable_borrowing')
                    && in_array($internalName, $itemsData->borrowFieldNames, true)) {
                    continue;
                }

                $labels = array(
                    $internalName,
                    (string)$field->getValue('inf_name'),
                    Language::translateIfTranslationStrId((string)$field->getValue('inf_name'))
                );

                foreach ($headers as $columnIndex => $header) {
                    if (in_array(trim((string)$header), $labels, true)) {
                        $mapping[(int)$field->getValue('inf_id')] = (int)$columnIndex;
                        break;
                    }
                }
            }
        }

        if (count($mapping) === 0) {
            throw new InvalidArgumentException(
                'No inventory fields could be mapped. Use --map=FIELD=COLUMN.'
            );
        }

        return array(
            'service' => $importService,
            'rows' => $importData,
            'mapping' => $mapping,
            'firstRow' => $firstRow
        );
    }

    public static function inventoryImport(array $arguments, array $options): int
    {
        $prepared = self::prepareInventoryImport($arguments, $options);

        $formValues = $prepared['mapping'];
        if ($prepared['firstRow']) {
            $formValues['first_row'] = '1';
        }

        $result = $prepared['service']->importData($prepared['rows'], $formValues);
        CliApplication::writeSuccess((string)$result['message'], $options);
        return 0;
    }

    public static function inventoryImportCheck(array $arguments, array $options): int
    {
        global $gDb;

        $prepared = self::prepareInventoryImport($arguments, $options);

        $rows = array();
        foreach ($prepared['mapping'] as $fieldId => $columnIndex) {
            $field = new ItemField($gDb, (int)$fieldId);
            $rows[] = array(
                'field' => (string)$field->getValue('inf_name_intern'),
                'name' => (string)$field->getValue('inf_name'),
                'type' => (string)$field->getValue('inf_type'),
                'required' => (int)$field->getValue('inf_required_input') === 1,
                'column' => $columnIndex + 1
            );
        }

        $dataRows = count($prepared['rows']) - ($prepared['firstRow'] ? 1 : 0);

        CliApplication::writeValue(array(
            'items_to_import' => max(0, $dataRows),
            'first_row_contains_column_names' => $prepared['firstRow'],
            'mapped_fields' => count($prepared['mapping']),
            'mapping' => $rows
        ), $options);

        return 0;
    }

    public static function inventoryExport(array $arguments, array $options): int
    {
        $format = CliApplication::optionString($options, 'format');
        $export = (new ExportService())->createExportFile($format);

        self::moveGeneratedFile($export['path'], $export['filename'], $options);
        return 0;
    }

    public static function inventoryFields(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $rows = $gDb->queryPrepared(
            'SELECT inf_id AS id, inf_uuid AS uuid, inf_name_intern AS internal_name,
                    inf_name AS name, inf_type AS type, inf_description AS description,
                    inf_system AS system, inf_required_input AS required_input,
                    inf_sequence AS sequence, inf_inf_uuid_connected AS connected_uuid
               FROM ' . TBL_INVENTORY_FIELDS . '
              WHERE inf_org_id = ?
           ORDER BY inf_sequence, inf_id',
            array($gCurrentOrgId)
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function inventoryFieldShow(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        CliApplication::writeValue(self::inventoryFieldData($field), $options);
        return 0;
    }

    public static function inventoryFieldAdd(array $arguments, array $options): int
    {
        global $gDb;

        $values = self::inventoryFieldFormValues($options);
        if (!isset($values['inf_name'], $values['inf_type'])) {
            throw new InvalidArgumentException('--name and --type are required.');
        }

        (new ItemFieldService($gDb))->saveData($values);
        CliApplication::writeSuccess('Inventory field created.', $options);
        return 0;
    }

    public static function inventoryFieldUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        if ((bool)$field->getValue('inf_system')
            && (CliApplication::optionExists($options, 'type')
                || CliApplication::optionExists($options, 'connected-field'))) {
            throw new RuntimeException('The type/connection of a system inventory field cannot be changed.');
        }

        $values = self::inventoryFieldFormValues($options);
        if (count($values) === 0) {
            throw new InvalidArgumentException('No inventory field values were supplied.');
        }

        (new ItemFieldService($gDb, (string)$field->getValue('inf_uuid')))->saveData($values);
        CliApplication::writeSuccess('Inventory field updated.', $options);
        return 0;
    }

    public static function inventoryFieldDelete(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        CliApplication::confirm('Delete inventory field "' . $field->getValue('inf_name') . '"?', $options);
        (new ItemFieldService($gDb, (string)$field->getValue('inf_uuid')))->delete();
        CliApplication::writeSuccess('Inventory field deleted.', $options);
        return 0;
    }

    public static function inventoryFieldMove(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $direction = self::direction(CliApplication::requireArgument($arguments, 1, 'direction'));
        (new ItemFieldService($gDb, (string)$field->getValue('inf_uuid')))->moveSequence(
            $direction === 'up' ? ItemFieldService::MOVE_UP : ItemFieldService::MOVE_DOWN
        );
        CliApplication::writeSuccess('Inventory field moved.', $options);
        return 0;
    }

    public static function inventoryOptions(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $select = new InventorySelectOptions($gDb, (int)$field->getValue('inf_id'));
        $rows = array_values($select->getAllOptions(
            CliApplication::optionBool($options, 'include-obsolete', false) ?? false
        ));
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function inventoryOptionAdd(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $value = CliApplication::requireArgument($arguments, 1, 'value');
        $select = new InventorySelectOptions($gDb, (int)$field->getValue('inf_id'));
        $select->setOptionValues(array(0 => array('value' => $value, 'obsolete' => false)));
        CliApplication::writeSuccess('Inventory select option added.', $options);
        return 0;
    }

    public static function inventoryOptionUpdate(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new InventorySelectOptions($gDb, (int)$field->getValue('inf_id'));
        if (!$select->readDataById($optionId)) {
            throw new InvalidArgumentException('Unknown inventory select option.');
        }
        if ((bool)$select->getValue('ifo_system')) {
            throw new RuntimeException('System inventory select options cannot be changed.');
        }
        $values = array();
        if (CliApplication::optionExists($options, 'value')) {
            $values['value'] = CliApplication::optionString($options, 'value');
        }
        if (CliApplication::optionExists($options, 'obsolete')) {
            $values['obsolete'] = CliApplication::optionBool($options, 'obsolete', false) ?? false;
        }
        if ($values !== array()) {
            $select->setOptionValues(array($optionId => $values));
        }
        CliApplication::writeSuccess('Inventory select option updated.', $options);
        return 0;
    }

    public static function inventoryOptionDelete(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new InventorySelectOptions($gDb, (int)$field->getValue('inf_id'));
        if (!$select->readDataById($optionId)) {
            throw new InvalidArgumentException('Unknown inventory select option.');
        }
        if ((bool)$select->getValue('ifo_system')) {
            throw new RuntimeException('System inventory select options cannot be deleted.');
        }
        if ($select->isOptionUsed($optionId)) {
            throw new RuntimeException('Inventory select option is in use and cannot be deleted.');
        }
        CliApplication::confirm('Delete this inventory select option?', $options);
        $select->deleteOption($optionId);
        CliApplication::writeSuccess('Inventory select option deleted.', $options);
        return 0;
    }

    public static function inventoryOptionMove(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $direction = self::direction(CliApplication::requireArgument($arguments, 2, 'direction'));
        self::moveSelectOption(
            new InventorySelectOptions($gDb, (int)$field->getValue('inf_id')),
            $optionId,
            $direction
        );
        CliApplication::writeSuccess('Inventory select option moved.', $options);
        return 0;
    }

    public static function profileFields(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $params = array($gCurrentOrgId);
        $categorySql = '';
        if (CliApplication::optionExists($options, 'category')) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'USF');
            $categorySql = ' AND usf_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }

        $rows = $gDb->queryPrepared(
            'SELECT usf_id AS id, usf_uuid AS uuid, usf_name_intern AS internal_name,
                    usf_name AS name, usf_type AS type, usf_cat_id AS category_id,
                    usf_system AS system, usf_disabled AS disabled, usf_hidden AS hidden,
                    usf_registration AS registration, usf_required_input AS required_input,
                    usf_sequence AS sequence, usf_description AS description
               FROM ' . TBL_USER_FIELDS . '
         INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = usf_cat_id
              WHERE (cat_org_id = ? OR cat_org_id IS NULL)' . $categorySql . '
           ORDER BY cat_sequence, usf_sequence, usf_id',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function profileFieldShow(array $arguments, array $options): int
    {
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        CliApplication::writeValue(self::profileFieldData($field), $options);
        return 0;
    }

    public static function profileFieldAdd(array $arguments, array $options): int
    {
        global $gDb;

        $values = self::profileFieldFormValues($options);
        if (!isset($values['usf_name'], $values['usf_cat_id'], $values['usf_type'])) {
            throw new InvalidArgumentException('--name, --category and --type are required.');
        }

        (new ProfileFieldService($gDb))->saveData($values);
        CliApplication::writeSuccess('Profile field created.', $options);
        return 0;
    }

    public static function profileFieldUpdate(array $arguments, array $options): int
    {
        global $gDb;

        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        if ((bool)$field->getValue('usf_system')
            && (CliApplication::optionExists($options, 'category')
                || CliApplication::optionExists($options, 'type'))) {
            throw new RuntimeException('The category/type of a system profile field cannot be changed.');
        }

        $values = self::profileFieldFormValues($options);
        if (count($values) === 0) {
            throw new InvalidArgumentException('No profile field values were supplied.');
        }
        if (isset($values['usf_name']) && !isset($values['usf_cat_id'])) {
            $values['usf_cat_id'] = (int)$field->getValue('usf_cat_id');
        }

        (new ProfileFieldService($gDb, (string)$field->getValue('usf_uuid')))->saveData($values);
        CliApplication::writeSuccess('Profile field updated.', $options);
        return 0;
    }

    public static function profileFieldDelete(array $arguments, array $options): int
    {
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        CliApplication::confirm('Delete profile field "' . $field->getValue('usf_name') . '"?', $options);
        $field->delete();
        CliApplication::writeSuccess('Profile field deleted.', $options);
        return 0;
    }

    public static function profileFieldMove(array $arguments, array $options): int
    {
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $direction = self::direction(CliApplication::requireArgument($arguments, 1, 'direction'));
        $field->moveSequence($direction === 'up' ? ProfileField::MOVE_UP : ProfileField::MOVE_DOWN);
        CliApplication::writeSuccess('Profile field moved.', $options);
        return 0;
    }

    public static function profileOptions(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $select = new ProfileSelectOptions($gDb, (int)$field->getValue('usf_id'));
        $rows = array_values($select->getAllOptions(
            CliApplication::optionBool($options, 'include-obsolete', false) ?? false
        ));
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function profileOptionAdd(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $value = CliApplication::requireArgument($arguments, 1, 'value');
        $select = new ProfileSelectOptions($gDb, (int)$field->getValue('usf_id'));
        $select->setOptionValues(array(0 => array('value' => $value, 'obsolete' => false)));
        CliApplication::writeSuccess('Profile select option added.', $options);
        return 0;
    }

    public static function profileOptionUpdate(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new ProfileSelectOptions($gDb, (int)$field->getValue('usf_id'));
        if (!$select->readDataById($optionId)) {
            throw new InvalidArgumentException('Unknown profile select option.');
        }
        if ((bool)$select->getValue('ufo_system')) {
            throw new RuntimeException('System profile select options cannot be changed.');
        }
        $values = array();
        if (CliApplication::optionExists($options, 'value')) {
            $values['value'] = CliApplication::optionString($options, 'value');
        }
        if (CliApplication::optionExists($options, 'obsolete')) {
            $values['obsolete'] = CliApplication::optionBool($options, 'obsolete', false) ?? false;
        }
        if ($values !== array()) {
            $select->setOptionValues(array($optionId => $values));
        }
        CliApplication::writeSuccess('Profile select option updated.', $options);
        return 0;
    }

    public static function profileOptionDelete(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new ProfileSelectOptions($gDb, (int)$field->getValue('usf_id'));
        if (!$select->readDataById($optionId)) {
            throw new InvalidArgumentException('Unknown profile select option.');
        }
        if ((bool)$select->getValue('ufo_system')) {
            throw new RuntimeException('System profile select options cannot be deleted.');
        }
        if ($select->isOptionUsed($optionId)) {
            throw new RuntimeException('Profile select option is in use and cannot be deleted.');
        }
        CliApplication::confirm('Delete this profile select option?', $options);
        $select->deleteOption($optionId);
        CliApplication::writeSuccess('Profile select option deleted.', $options);
        return 0;
    }

    public static function profileOptionMove(array $arguments, array $options): int
    {
        global $gDb;
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $direction = self::direction(CliApplication::requireArgument($arguments, 2, 'direction'));
        self::moveSelectOption(
            new ProfileSelectOptions($gDb, (int)$field->getValue('usf_id')),
            $optionId,
            $direction
        );
        CliApplication::writeSuccess('Profile select option moved.', $options);
        return 0;
    }


    public static function categoryReportList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gSettingsManager;

        $defaultId = $gSettingsManager->getInt('category_report_default_configuration');
        $rows = $gDb->queryPrepared(
            'SELECT crt_id AS id, crt_org_id AS organization_id, crt_name AS name,
                    crt_col_fields AS columns, crt_col_conditions AS conditions,
                    crt_selection_role AS role_selection, crt_selection_cat AS category_selection,
                    crt_number_col AS number_column
               FROM ' . TBL_CATEGORY_REPORT . '
              WHERE crt_org_id = ? OR crt_org_id IS NULL
           ORDER BY crt_name, crt_id',
            array($gCurrentOrgId)
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['default'] = (int)$row['id'] === $defaultId;
        }
        unset($row);

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function categoryReportShow(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gSettingsManager;

        $selector = CliApplication::requireArgument($arguments, 0, 'config');
        if (ctype_digit($selector)) {
            $statement = $gDb->queryPrepared(
                'SELECT *
                   FROM ' . TBL_CATEGORY_REPORT . '
                  WHERE crt_id = ?
                    AND (crt_org_id = ? OR crt_org_id IS NULL)',
                array((int)$selector, $gCurrentOrgId)
            );
        } else {
            $statement = $gDb->queryPrepared(
                'SELECT *
                   FROM ' . TBL_CATEGORY_REPORT . '
                  WHERE crt_name = ?
                    AND (crt_org_id = ? OR crt_org_id IS NULL)',
                array($selector, $gCurrentOrgId)
            );
        }

        $rows = $statement->fetchAll();
        if (count($rows) !== 1) {
            throw new InvalidArgumentException(
                count($rows) === 0
                    ? 'Unknown category-report configuration.'
                    : 'Category-report configuration name is ambiguous; use the numeric id.'
            );
        }

        $row = $rows[0];
        $data = array(
            'id' => (int)$row['crt_id'],
            'organization_id' => $row['crt_org_id'] === null ? null : (int)$row['crt_org_id'],
            'name' => $row['crt_name'],
            'columns' => $row['crt_col_fields'],
            'conditions' => $row['crt_col_conditions'],
            'role_selection' => $row['crt_selection_role'],
            'category_selection' => $row['crt_selection_cat'],
            'number_column' => (bool)$row['crt_number_col'],
            'default' => (int)$row['crt_id'] === $gSettingsManager->getInt('category_report_default_configuration')
        );

        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function categoryReportAdd(array $arguments, array $options): int
    {
        $report = new \CategoryReport();
        $config = self::categoryReportConfigForSave($report->getConfigArray());
        $values = self::categoryReportFormValues($options, array(
            'id' => '',
            'name' => '',
            'col_fields' => '',
            'col_conditions' => '',
            'selection_role' => '',
            'selection_cat' => '',
            'number_col' => 0,
            'default_conf' => false
        ));

        if ($values['name'] === '' || $values['col_fields'] === '') {
            throw new InvalidArgumentException('--name and at least one --column are required.');
        }

        $config[] = $values;
        $report->saveConfigArray($config);

        CliApplication::writeSuccess('Category-report configuration created.', $options);
        return 0;
    }

    public static function categoryReportUpdate(array $arguments, array $options): int
    {
        $report = new \CategoryReport();
        $config = self::categoryReportConfigForSave($report->getConfigArray());
        $index = self::categoryReportConfigIndex(
            $config,
            CliApplication::requireArgument($arguments, 0, 'config')
        );
        $config[$index] = self::categoryReportFormValues($options, $config[$index]);
        $report->saveConfigArray($config);

        CliApplication::writeSuccess('Category-report configuration updated.', $options);
        return 0;
    }

    public static function categoryReportCopy(array $arguments, array $options): int
    {
        $report = new \CategoryReport();
        $config = self::categoryReportConfigForSave($report->getConfigArray());
        $index = self::categoryReportConfigIndex(
            $config,
            CliApplication::requireArgument($arguments, 0, 'config')
        );

        $copy = $config[$index];
        $copy['id'] = '';
        $copy['default_conf'] = false;
        $copy['name'] = CliApplication::optionExists($options, 'name')
            ? CliApplication::optionString($options, 'name')
            : $report->createName(html_entity_decode((string)$copy['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $config[] = $copy;
        $report->saveConfigArray($config);

        CliApplication::writeSuccess('Category-report configuration copied.', $options);
        return 0;
    }

    public static function categoryReportDelete(array $arguments, array $options): int
    {
        $report = new \CategoryReport();
        $config = self::categoryReportConfigForSave($report->getConfigArray());
        $index = self::categoryReportConfigIndex(
            $config,
            CliApplication::requireArgument($arguments, 0, 'config')
        );

        CliApplication::confirm(
            'Delete category-report configuration "' . html_entity_decode(
                (string)$config[$index]['name'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ) . '"?',
            $options
        );

        // A negative id marks the entry as deleted for saveConfigArray(). Negating an already
        // negative id would revive it, so make the sign unconditional.
        $config[$index]['id'] = -1 * abs((int)$config[$index]['id']);
        $report->saveConfigArray($config);

        CliApplication::writeSuccess('Category-report configuration deleted.', $options);
        return 0;
    }

    public static function categoryReportRun(array $arguments, array $options): int
    {
        global $gCurrentUser, $gProfileFields;

        if (!$gCurrentUser->checkRolesRight('rol_all_lists_view')) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $report = new \CategoryReport();
        $config = $report->getConfigArray();
        $index = self::categoryReportConfigIndex(
            $config,
            CliApplication::requireArgument($arguments, 0, 'config')
        );
        $report->setConfiguration((int)$config[$index]['id']);

        $date = CliApplication::optionString($options, 'date', DATE_NOW);
        self::validateDate($date, '--date');
        $report->generate_listData($date);

        $headers = array();
        foreach ($report->headerData as $columnHeader) {
            $headers[] = html_entity_decode(
                (string)$columnHeader['data'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        $rows = array();
        $filter = StringUtils::strToLower(CliApplication::optionString($options, 'filter'));
        foreach ($report->listData as $memberData) {
            $row = array();
            foreach (array_values($memberData) as $indexColumn => $value) {
                $header = $headers[$indexColumn] ?? 'column_' . ($indexColumn + 1);
                $headerData = array_values($report->headerData)[$indexColumn] ?? array('id' => 0);
                $profileFieldId = (int)($headerData['id'] ?? 0);

                if ($profileFieldId > 0) {
                    $type = (string)$gProfileFields->getPropertyById($profileFieldId, 'usf_type');
                    if (in_array($type, array('DROPDOWN', 'DROPDOWN_MULTISELECT', 'RADIO_BUTTON'), true)
                        && $value !== '' && $value !== null) {
                        $selectOptions = $gProfileFields->getPropertyById(
                            $profileFieldId,
                            'ufo_usf_options',
                            'text'
                        );
                        if (is_array($value)) {
                            $value = implode(', ', array_map(
                                static fn (mixed $entry): string => (string)($selectOptions[$entry] ?? ''),
                                $value
                            ));
                        } elseif (isset($selectOptions[$value])) {
                            $value = $selectOptions[$value];
                        }
                    } elseif ($type === 'CHECKBOX') {
                        $value = $value ? 'X' : '';
                    }
                } elseif ($value === true) {
                    $value = 'X';
                }

                $row[$header] = $value;
            }

            if ($filter !== ''
                && !str_contains(
                    StringUtils::strToLower(implode(' ', array_map(
                        static fn (mixed $value): string => is_scalar($value) ? (string)$value : '',
                        $row
                    ))),
                    $filter
                )) {
                continue;
            }

            $rows[] = $row;
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function changelogList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $permittedTables = array_values(array_filter(
            array_unique(ChangelogService::getPermittedTables($gCurrentUser)),
            static fn (string $table): bool => ChangelogService::hasLogViewPermission($table, $gCurrentUser)
        ));

        /*
         * ChangelogService::isTableLogged() falls back to the changelog_table_others preference for
         * tables it does not know, so asking for a table name that is certainly not configured
         * answers whether *any* table may be inspected. For an administrator that makes the
         * log_table restriction pointless, and enumerating the tables would be incomplete anyway.
         *
         * The previous implementation derived the list with SELECT DISTINCT log_table over
         * adm_log_changes instead. That is the largest table of a mature installation and the query
         * has no predicate to use an index for.
         */
        $anyTablePermitted = $gCurrentUser->isAdministrator()
            && ChangelogService::hasLogViewPermission('adm_cli_unknown_table', $gCurrentUser);

        if (!$anyTablePermitted && $permittedTables === array()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $requestedTables = CliApplication::optionValues($options, 'table');
        if ($requestedTables !== array()) {
            foreach ($requestedTables as $table) {
                if (!ChangelogService::hasLogViewPermission($table, $gCurrentUser)
                    || (!$anyTablePermitted && !in_array($table, $permittedTables, true))) {
                    throw new Exception('SYS_NO_RIGHTS');
                }
            }
            $tables = $requestedTables;
        } else {
            // No restriction is needed when every table may be inspected anyway.
            $tables = $anyTablePermitted ? array() : $permittedTables;
        }

        $conditions = array();
        $params = array();
        if ($tables !== array()) {
            $placeholders = implode(', ', array_fill(0, count($tables), '?'));
            $conditions[] = 'log_table IN (' . $placeholders . ')';
            array_push($params, ...$tables);
        }

        $objectUuid = CliApplication::optionString($options, 'object');
        if ($objectUuid !== '') {
            $conditions[] = 'log_record_uuid = ?';
            $params[] = $objectUuid;
        }

        if (CliApplication::optionExists($options, 'user')) {
            $user = CliApplication::resolveUser(CliApplication::optionString($options, 'user'));
            $conditions[] = 'log_usr_id_create = ?';
            $params[] = (int)$user->getValue('usr_id');
        }

        $dateFrom = CliApplication::optionString($options, 'date-from');
        $dateTo = CliApplication::optionString($options, 'date-to');
        if ($dateFrom !== '') {
            self::validateDate($dateFrom, '--date-from');
            $conditions[] = 'log_timestamp_create >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            self::validateDate($dateTo, '--date-to');
            $conditions[] = 'log_timestamp_create <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($dateFrom !== '' && $dateTo !== '') {
            self::validateDateRange($dateFrom, $dateTo);
        }

        $action = CliApplication::optionString($options, 'action');
        if ($action !== '') {
            $conditions[] = 'log_action = ?';
            $params[] = match ($action) {
                'create' => 'CREATED',
                'change' => 'MODIFY',
                'delete' => 'DELETED',
                default => throw new InvalidArgumentException('Invalid changelog action.')
            };
        }

        $limit = CliApplication::optionExists($options, 'limit')
            ? self::positiveInt(CliApplication::optionString($options, 'limit'), '--limit')
            : 100;
        $offset = CliApplication::optionExists($options, 'offset')
            ? max(0, (int)CliApplication::optionString($options, 'offset'))
            : 0;

        $sql = 'SELECT log_id AS id, log_table AS table_name, log_record_id AS record_id,
                       log_record_uuid AS record_uuid, log_record_name AS record_name,
                       log_related_id AS related_id, log_related_name AS related_name,
                       log_field AS field, log_field_name AS field_name, log_action AS action,
                       log_value_old AS value_old, log_value_new AS value_new,
                       log_usr_id_create AS user_id, log_timestamp_create AS timestamp,
                       log_comment AS comment
                  FROM ' . TBL_LOG_CHANGES
            . ($conditions === array() ? '' : ' WHERE ' . implode(' AND ', $conditions)) . '
              ORDER BY log_timestamp_create DESC, log_id DESC
                 LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $gDb->queryPrepared($sql, $params)->fetchAll();
        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function changelogShow(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $id = self::positiveInt(CliApplication::requireArgument($arguments, 0, 'change'), 'change');
        $row = $gDb->queryPrepared(
            'SELECT log_id AS id, log_table AS table_name, log_record_id AS record_id,
                    log_record_uuid AS record_uuid, log_record_name AS record_name,
                    log_record_linkid AS record_link_id,
                    log_related_id AS related_id, log_related_name AS related_name,
                    log_field AS field, log_field_name AS field_name, log_action AS action,
                    log_value_old AS value_old, log_value_new AS value_new,
                    log_usr_id_create AS user_id, log_timestamp_create AS timestamp,
                    log_comment AS comment
               FROM ' . TBL_LOG_CHANGES . '
              WHERE log_id = ?',
            array($id)
        )->fetch();

        if ($row === false) {
            throw new InvalidArgumentException('Unknown changelog record.');
        }

        $table = (string)$row['table_name'];
        $permittedTables = ChangelogService::getPermittedTables($gCurrentUser);
        if ((!$gCurrentUser->isAdministrator() && !in_array($table, $permittedTables, true))
            || !ChangelogService::hasLogViewPermission($table, $gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::writeValue($row, $options);
        return 0;
    }

    public static function pluginList(array $arguments, array $options): int
    {
        $rows = array();
        foreach ((new PluginManager())->getAvailablePlugins() as $folder => $pluginData) {
            $interface = $pluginData['interface'] ?? null;
            if ($interface === null) {
                $row = array(
                    'plugin' => $folder,
                    'name' => $folder,
                    'installed' => false,
                    'installed_version' => '',
                    'available_version' => '',
                    'update_available' => false,
                    'interface' => false
                );
            } else {
                /** @var PluginAbstract $plugin */
                $plugin = $interface::getInstance();
                $metadata = $plugin::getMetadata();
                $row = array(
                    'plugin' => $folder,
                    'name' => $plugin::getName(),
                    'installed' => $plugin::isInstalled(),
                    'installed_version' => $plugin::getVersion(),
                    'available_version' => (string)($metadata['version'] ?? ''),
                    'update_available' => $plugin::isUpdateAvailable(),
                    'interface' => true
                );
            }

            if (CliApplication::optionBool($options, 'installed', false) === true && !$row['installed']) {
                continue;
            }
            if (CliApplication::optionBool($options, 'updates', false) === true && !$row['update_available']) {
                continue;
            }
            $rows[] = $row;
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function pluginShow(array $arguments, array $options): int
    {
        $plugin = self::resolvePlugin(CliApplication::requireArgument($arguments, 0, 'plugin'));
        $data = array(
            'name' => $plugin::getName(),
            'component_name' => $plugin::getComponentName(),
            'component_id' => $plugin::getComponentId(),
            'installed' => $plugin::isInstalled(),
            'activated' => $plugin::isActivated(),
            'installed_version' => $plugin::getVersion(),
            'update_available' => $plugin::isUpdateAvailable(),
            'metadata' => $plugin::getMetadata()
        );
        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function pluginInstall(array $arguments, array $options): int
    {
        $plugin = self::resolvePlugin(CliApplication::requireArgument($arguments, 0, 'plugin'));
        $addMenu = CliApplication::optionBool($options, 'add-menu', true) ?? true;
        if (!$plugin::doInstall($addMenu)) {
            throw new RuntimeException('Plugin is already installed or could not be installed.');
        }
        CliApplication::writeSuccess('Plugin installed.', $options);
        return 0;
    }

    public static function pluginUpdate(array $arguments, array $options): int
    {
        $plugin = self::resolvePlugin(CliApplication::requireArgument($arguments, 0, 'plugin'));
        if (!$plugin::isInstalled()) {
            throw new RuntimeException('Plugin is not installed.');
        }
        if (!$plugin::doUpdate()) {
            throw new RuntimeException('Plugin update was not performed.');
        }
        CliApplication::writeSuccess('Plugin updated.', $options);
        return 0;
    }

    public static function pluginRemove(array $arguments, array $options): int
    {
        $plugin = self::resolvePlugin(CliApplication::requireArgument($arguments, 0, 'plugin'));
        if (!$plugin::isInstalled()) {
            throw new RuntimeException('Plugin is not installed.');
        }

        CliApplication::confirm('Uninstall plugin "' . $plugin::getName() . '"?', $options);
        $removeMenu = CliApplication::optionBool($options, 'remove-menu', true) ?? true;
        if (!$plugin::doUninstall($removeMenu)) {
            throw new RuntimeException('Plugin uninstall was not performed.');
        }
        CliApplication::writeSuccess('Plugin removed.', $options);
        return 0;
    }

    public static function pluginMove(array $arguments, array $options): int
    {
        $plugin = self::resolvePlugin(CliApplication::requireArgument($arguments, 0, 'plugin'));
        $direction = self::direction(CliApplication::requireArgument($arguments, 1, 'direction'));

        if (!$plugin::isInstalled()) {
            throw new RuntimeException('Plugin is not installed.');
        }

        $sequence = $plugin::getPluginSequence();
        $newSequence = $direction === 'up' ? max(1, $sequence - 1) : $sequence + 1;

        if (!$plugin::setPluginSequence($newSequence)) {
            throw new RuntimeException('Plugin sequence could not be updated.');
        }

        CliApplication::writeSuccess('Plugin moved.', $options);
        return 0;
    }

    public static function ssoOidcDiscovery(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $service = new OIDCService($gDb, $gCurrentUser);
        CliApplication::writeValue($service->getDiscoveryConfiguration(), $options, 'json');
        return 0;
    }

    public static function ssoSamlMetadata(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $service = new SAMLService($gDb, $gCurrentUser);
        CliApplication::writeOutput($service->getMetadataXml(), $options);
        return 0;
    }

    public static function ssoList(array $arguments, array $options): int
    {
        global $gDb;

        $type = CliApplication::optionString($options, 'type');
        $enabledFilter = CliApplication::optionBool($options, 'enabled');
        $rows = array();

        if ($type === '' || $type === 'saml') {
            $samlRows = $gDb->queryPrepared(
                'SELECT smc_id AS id, smc_uuid AS uuid, smc_client_id AS client_id,
                        smc_client_name AS name, smc_enabled AS enabled
                   FROM ' . TBL_SAML_CLIENTS . '
               ORDER BY smc_client_name, smc_id'
            )->fetchAll();
            foreach ($samlRows as $row) {
                $row['type'] = 'saml';
                if ($enabledFilter === null || (bool)$row['enabled'] === $enabledFilter) {
                    $rows[] = $row;
                }
            }
        }

        if ($type === '' || $type === 'oidc') {
            $oidcRows = $gDb->queryPrepared(
                'SELECT ocl_id AS id, ocl_uuid AS uuid, ocl_client_id AS client_id,
                        ocl_client_name AS name, ocl_enabled AS enabled
                   FROM ' . TBL_OIDC_CLIENTS . '
               ORDER BY ocl_client_name, ocl_id'
            )->fetchAll();
            foreach ($oidcRows as $row) {
                $row['type'] = 'oidc';
                if ($enabledFilter === null || (bool)$row['enabled'] === $enabledFilter) {
                    $rows[] = $row;
                }
            }
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function ssoShow(array $arguments, array $options): int
    {
        [$client, $type] = self::resolveSsoClient(
            CliApplication::requireArgument($arguments, 0, 'client'),
            CliApplication::optionString($options, 'type')
        );

        CliApplication::writeValue(self::ssoClientData($client, $type), $options);
        return 0;
    }

    public static function ssoEnable(array $arguments, array $options): int
    {
        return self::setSsoEnabled($arguments, $options, true);
    }

    public static function ssoDisable(array $arguments, array $options): int
    {
        return self::setSsoEnabled($arguments, $options, false);
    }

    public static function ssoSamlAdd(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        [$formValues, $accessRoles] = self::ssoFormValues('saml', null, $options);

        foreach (array('smc_client_name', 'smc_client_id', 'smc_acs_url') as $requiredField) {
            if (($formValues[$requiredField] ?? '') === '') {
                throw new InvalidArgumentException('--name, --client-id and --acs-url are required.');
            }
        }

        $client = (new SAMLService($gDb, $gCurrentUser))->saveData(null, $formValues, $accessRoles);
        CliApplication::writeValue(self::ssoClientData($client, 'saml'), $options);
        return 0;
    }

    public static function ssoSamlUpdate(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        [$client] = self::resolveSsoClient(
            CliApplication::requireArgument($arguments, 0, 'client'),
            'saml'
        );
        [$formValues, $accessRoles] = self::ssoFormValues('saml', $client, $options);

        $saved = (new SAMLService($gDb, $gCurrentUser))->saveData(
            (string)$client->getValue('smc_uuid'),
            $formValues,
            $accessRoles
        );

        CliApplication::writeValue(self::ssoClientData($saved, 'saml'), $options);
        return 0;
    }

    public static function ssoOidcAdd(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        [$formValues, $accessRoles] = self::ssoFormValues('oidc', null, $options);

        foreach (array('ocl_client_name', 'ocl_client_id') as $requiredField) {
            if (($formValues[$requiredField] ?? '') === '') {
                throw new InvalidArgumentException('--name and --client-id are required.');
            }
        }

        $client = (new OIDCService($gDb, $gCurrentUser))->saveData(null, $formValues, $accessRoles);
        CliApplication::writeValue(self::ssoClientData($client, 'oidc'), $options);
        return 0;
    }

    public static function ssoOidcUpdate(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        [$client] = self::resolveSsoClient(
            CliApplication::requireArgument($arguments, 0, 'client'),
            'oidc'
        );
        [$formValues, $accessRoles] = self::ssoFormValues('oidc', $client, $options);

        $saved = (new OIDCService($gDb, $gCurrentUser))->saveData(
            (string)$client->getValue('ocl_uuid'),
            $formValues,
            $accessRoles
        );

        CliApplication::writeValue(self::ssoClientData($saved, 'oidc'), $options);
        return 0;
    }

    public static function ssoDelete(array $arguments, array $options): int
    {
        $type = str_contains(CliApplication::currentCommand(), ':saml-') ? 'saml' : 'oidc';
        [$client] = self::resolveSsoClient(
            CliApplication::requireArgument($arguments, 0, 'client'),
            $type
        );
        CliApplication::confirm('Delete ' . strtoupper($type) . ' client "' . $client->getName() . '"?', $options);
        $client->delete();
        CliApplication::writeSuccess(strtoupper($type) . ' client deleted.', $options);
        return 0;
    }

    public static function ssoKeys(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $params = array($gCurrentOrgId);
        $where = 'key_org_id = ?';
        $active = CliApplication::optionBool($options, 'active');
        if ($active !== null) {
            $where .= ' AND key_is_active = ?';
            $params[] = $active;
        }

        $rows = $gDb->queryPrepared(
            'SELECT key_id AS id, key_uuid AS uuid, key_name AS name,
                    key_algorithm AS algorithm, key_expires_at AS expires_at,
                    key_is_active AS active, key_timestamp_create AS created_at,
                    key_timestamp_change AS changed_at
               FROM ' . TBL_SSO_KEYS . '
              WHERE ' . $where . '
           ORDER BY key_name, key_id',
            $params
        )->fetchAll();

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function ssoKeyShow(array $arguments, array $options): int
    {
        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        CliApplication::writeValue(self::ssoKeyData($key), $options);
        return 0;
    }

    public static function ssoKeyAdd(array $arguments, array $options): int
    {
        return self::saveSsoKey('', $options, 'key');
    }

    public static function ssoKeyUpdate(array $arguments, array $options): int
    {
        global $gDb;
        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        $values = array();

        if (CliApplication::optionExists($options, 'name')) {
            $values['key_name'] = CliApplication::optionString($options, 'name');
        }
        if (CliApplication::optionExists($options, 'active')) {
            $values['key_is_active'] = CliApplication::optionBool($options, 'active', false) ?? false;
        }
        if (count($values) === 0) {
            throw new InvalidArgumentException('No SSO key values were supplied.');
        }

        $saved = (new KeyService($gDb))->saveData(
            (string)$key->getValue('key_uuid'),
            $values,
            'save'
        );

        CliApplication::writeValue(self::ssoKeyData($saved), $options);
        return 0;
    }

    public static function ssoKeyGenerate(array $arguments, array $options): int
    {
        return self::saveSsoKey('', $options, 'key');
    }

    public static function ssoKeyRegenerate(array $arguments, array $options): int
    {
        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        CliApplication::confirm('Regenerate SSO key "' . $key->getValue('key_name') . '"?', $options);

        return self::saveSsoKey((string)$key->getValue('key_uuid'), $options, 'key', $key);
    }

    public static function ssoKeyDelete(array $arguments, array $options): int
    {
        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        CliApplication::confirm('Delete SSO key "' . $key->getValue('key_name') . '"?', $options);
        $key->delete();
        CliApplication::writeSuccess('SSO key deleted.', $options);
        return 0;
    }

    public static function ssoKeyExport(array $arguments, array $options): int
    {
        global $gDb;

        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        $password = CliApplication::readSecret($options, 'password', 'password-stdin');
        $export = (new KeyService($gDb))->getPkcs12ExportData(
            (string)$key->getValue('key_uuid'),
            $password
        );

        // The PKCS#12 container holds the private key.
        self::writeExportContent($export, $options, true);
        return 0;
    }

    public static function ssoCertificateExport(array $arguments, array $options): int
    {
        global $gDb;

        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        $export = (new KeyService($gDb))->getCertificateExportData((string)$key->getValue('key_uuid'));

        self::writeExportContent($export, $options);
        return 0;
    }

    public static function ssoTokenCleanup(array $arguments, array $options): int
    {
        global $gDb;

        CliApplication::confirm('Delete expired/revoked OIDC token and authorization-code rows?', $options);

        $gDb->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_ACCESS_TOKENS . '
                  WHERE oat_expires_at < ? OR oat_revoked = true',
            array(DATETIME_NOW)
        );
        $gDb->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_REFRESH_TOKENS . '
                  WHERE ort_expires_at < ? OR ort_revoked = true',
            array(DATETIME_NOW)
        );
        $gDb->queryPrepared(
            'DELETE FROM ' . TBL_OIDC_AUTH_CODES . '
                  WHERE oac_expires_at < ? OR oac_revoked = true OR oac_used = true',
            array(DATETIME_NOW)
        );

        CliApplication::writeSuccess('OIDC token cleanup completed.', $options);
        return 0;
    }

    public static function sessionInvalidate(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('User sessions marked for reload.', $options);
        return 0;
    }

    public static function sessionInvalidateAll(array $arguments, array $options): int
    {
        CliApplication::confirm('Mark all active sessions for reload?', $options);
        self::reloadAllSessions();
        CliApplication::writeSuccess('All sessions marked for reload.', $options);
        return 0;
    }

    public static function sessionCleanup(array $arguments, array $options): int
    {
        global $gDb;

        $minutes = CliApplication::optionExists($options, 'max-inactive-minutes')
            ? self::positiveInt(CliApplication::optionString($options, 'max-inactive-minutes'), '--max-inactive-minutes')
            : 30;
        $cutoff = date('Y-m-d H:i:s', time() - $minutes * 60);

        $gDb->queryPrepared(
            'DELETE FROM ' . TBL_SESSIONS . ' WHERE ses_timestamp < ?',
            array($cutoff)
        );
        CliApplication::writeSuccess('Stale sessions deleted.', $options);
        return 0;
    }

    public static function autoLoginCleanup(array $arguments, array $options): int
    {
        global $gDb;

        $autoLogin = new AutoLogin($gDb);
        $autoLogin->tableCleanup();

        CliApplication::writeSuccess('Expired auto-login records deleted.', $options);
        return 0;
    }

    public static function moduleList(array $arguments, array $options): int
    {
        $modules = array();
        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if (!str_contains($name, ':')) {
                continue;
            }
            [$namespace] = explode(':', $name, 2);
            if (!isset($modules[$namespace])) {
                $modules[$namespace] = array(
                    'module' => $namespace,
                    'commands' => 0,
                    'core' => true
                );
            }
            ++$modules[$namespace]['commands'];
            if (!$task['core']) {
                $modules[$namespace]['core'] = false;
            }
        }
        ksort($modules);

        CliApplication::writeRows(
            array_values($modules),
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function moduleTasks(array $arguments, array $options): int
    {
        $namespace = $arguments[0] ?? '';
        $rows = array();

        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if ($namespace !== '' && !str_starts_with($name, $namespace . ':')) {
                continue;
            }
            if ($namespace === '' && !str_contains($name, ':')) {
                continue;
            }
            $rows[] = array(
                'command' => $name,
                'description' => $task['description'],
                'available' => $task['unavailableReason'] === null,
                'component' => $task['component'] ?? '',
                'core' => $task['core']
            );
        }

        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    /**
     * Callback of the commands that cannot be executed in the current Admidio version.
     *
     * CliApplication::run() already refuses a task that carries an unavailableReason and reports
     * that reason, so this method is normally never reached. It is kept deliberately as the
     * fallback for the case that a command is registered with this callback but without a reason:
     * the command then still fails with a meaningful message instead of silently doing nothing.
     */
    public static function unavailable(array $arguments, array $options): int
    {
        throw new RuntimeException(
            'Command "' . CliApplication::currentCommand()
            . '" is not available in this Admidio version.'
        );
    }


    private static function componentVersion(Component $component): string
    {
        $version = (string)$component->getValue('com_version');
        $beta = (int)$component->getValue('com_beta');
        return $version . ($beta > 0 ? '-Beta.' . $beta : '');
    }

    private static function positiveInt(string $value, string $label): int
    {
        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            throw new InvalidArgumentException($label . ' must be a positive integer.');
        }
        return (int)$value;
    }

    private static function parseBool(string|bool|int $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on', 'enable', 'enabled' => true,
            '0', 'false', 'no', 'off', 'disable', 'disabled' => false,
            default => throw new InvalidArgumentException('Expected a boolean value, got "' . $value . '".')
        };
    }

    private static function direction(string $direction): string
    {
        $direction = strtolower($direction);
        if (!in_array($direction, array('up', 'down'), true)) {
            throw new InvalidArgumentException('Direction must be "up" or "down".');
        }
        return $direction;
    }

    private static function validateDate(string $date, string $label = 'date'): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException($label . ' must use YYYY-MM-DD.');
        }
    }

    private static function validateDateRange(string $start, string $end): void
    {
        self::validateDate($start, 'start date');
        self::validateDate($end, 'end date');
        if ($start > $end) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitAssignment(string $assignment, string $label = 'assignment'): array
    {
        $position = strpos($assignment, '=');
        if ($position === false || $position === 0) {
            throw new InvalidArgumentException($label . ' expects NAME=VALUE.');
        }
        return array(substr($assignment, 0, $position), substr($assignment, $position + 1));
    }

    /**
     * Both helpers only record the request; CliApplication performs at most one statement at the
     * end of the process. A command that changes several objects used to issue one UPDATE over
     * adm_sessions per change.
     */
    private static function reloadUserSessions(int $userId): void
    {
        CliApplication::queueSessionReload($userId);
    }

    private static function reloadAllSessions(): void
    {
        CliApplication::queueSessionReload();
    }

    private static function assertUniqueLogin(string $login, int $excludeUserId = 0): void
    {
        global $gDb;

        if ($login === '') {
            return;
        }

        $sql = 'SELECT COUNT(*) FROM ' . TBL_USERS . ' WHERE UPPER(usr_login_name) = UPPER(?)';
        $params = array($login);
        if ($excludeUserId > 0) {
            $sql .= ' AND usr_id <> ?';
            $params[] = $excludeUserId;
        }

        if ((int)$gDb->queryPrepared($sql, $params)->fetchColumn() > 0) {
            throw new Exception('SYS_LOGIN_NAME_EXIST');
        }
    }

    private static function applyUserFields(User $user, array $options): void
    {
        foreach (CliApplication::optionValues($options, 'field') as $assignment) {
            [$fieldReference, $value] = self::splitAssignment($assignment, '--field');
            $field = self::resolveProfileField($fieldReference);
            $internalName = (string)$field->getValue('usf_name_intern');
            if (!$user->setValue($internalName, $value)) {
                throw new InvalidArgumentException('Invalid value for profile field "' . $internalName . '".');
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function userData(User $user): array
    {
        global $gProfileFields;

        $data = array(
            'id' => (int)$user->getValue('usr_id'),
            'uuid' => (string)$user->getValue('usr_uuid'),
            'login' => (string)$user->getValue('usr_login_name'),
            'valid' => (bool)$user->getValue('usr_valid')
        );

        $fields = array();
        foreach ($gProfileFields->getProfileFields() as $field) {
            $internalName = (string)$field->getValue('usf_name_intern');
            $fields[$internalName] = $user->getValue($internalName, 'database');
        }
        $data['fields'] = $fields;

        return $data;
    }

    private static function resolveOrganization(string $reference): Organization
    {
        global $gDb;

        if ($reference === '') {
            throw new InvalidArgumentException('Organization reference must not be empty.');
        }

        if (ctype_digit($reference)) {
            $organization = new Organization($gDb, (int)$reference);
        } else {
            $id = (int)$gDb->queryPrepared(
                'SELECT org_id
                   FROM ' . TBL_ORGANIZATIONS . '
                  WHERE org_uuid = ? OR UPPER(org_shortname) = UPPER(?)',
                array($reference, $reference)
            )->fetchColumn();
            if ($id === 0) {
                throw new InvalidArgumentException('Organization "' . $reference . '" was not found.');
            }
            $organization = new Organization($gDb, $id);
        }

        if ((int)$organization->getValue('org_id') === 0) {
            throw new InvalidArgumentException('Organization "' . $reference . '" was not found.');
        }
        return $organization;
    }

    private static function resolveRegistration(string $reference): UserRegistration
    {
        global $gDb, $gProfileFields, $gCurrentOrgId;

        $user = CliApplication::resolveUser($reference);
        $userId = (int)$user->getValue('usr_id');
        $registered = (int)$gDb->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_REGISTRATIONS . ' WHERE reg_org_id = ? AND reg_usr_id = ?',
            array($gCurrentOrgId, $userId)
        )->fetchColumn();

        if ($registered === 0) {
            throw new InvalidArgumentException('User "' . $reference . '" is not a pending registration.');
        }

        return new UserRegistration($gDb, $gProfileFields, $userId, $gCurrentOrgId);
    }

    private static function resolveRelationType(string $reference): UserRelationType
    {
        global $gDb;

        if (ctype_digit($reference)) {
            $id = (int)$reference;
        } else {
            $params = array($reference, $reference);
            $rows = $gDb->queryPrepared(
                'SELECT urt_id
                   FROM ' . TBL_USER_RELATION_TYPES . '
                  WHERE urt_uuid = ? OR UPPER(urt_name) = UPPER(?)',
                $params
            )->fetchAll(PDO::FETCH_COLUMN);
            $ids = array_values(array_unique(array_map('intval', $rows)));
            if (count($ids) !== 1) {
                throw new InvalidArgumentException(
                    count($ids) === 0
                        ? 'Unknown user relation type.'
                        : 'User relation type name is ambiguous; use UUID or id.'
                );
            }
            $id = $ids[0];
        }

        $type = new UserRelationType($gDb, $id);
        if ((int)$type->getValue('urt_id') === 0) {
            throw new InvalidArgumentException('Unknown user relation type.');
        }
        return $type;
    }

    private static function resolveRelation(string $reference): UserRelation
    {
        global $gDb;

        $id = CliApplication::resolveId(
            TBL_USER_RELATIONS,
            'ure_id',
            'ure_uuid',
            $reference,
            'user relation'
        );
        return new UserRelation($gDb, $id);
    }

    private static function applyRelationTypeOptions(UserRelationType $type, array $options): void
    {
        $fallbackName = CliApplication::optionExists($options, 'name')
            ? self::requireTextOption($options, 'name')
            : (string)$type->getValue('urt_name', 'database');

        if (CliApplication::optionExists($options, 'name')) {
            $type->setValue('urt_name', $fallbackName);
            if (!CliApplication::optionExists($options, 'name-male') && $type->isNewRecord()) {
                $type->setValue('urt_name_male', $fallbackName);
            }
            if (!CliApplication::optionExists($options, 'name-female') && $type->isNewRecord()) {
                $type->setValue('urt_name_female', $fallbackName);
            }
        }
        if (CliApplication::optionExists($options, 'name-male')) {
            $value = CliApplication::optionString($options, 'name-male');
            $type->setValue('urt_name_male', $value !== '' ? $value : $fallbackName);
        }
        if (CliApplication::optionExists($options, 'name-female')) {
            $value = CliApplication::optionString($options, 'name-female');
            $type->setValue('urt_name_female', $value !== '' ? $value : $fallbackName);
        }
        if (CliApplication::optionExists($options, 'editable-by-user')) {
            $type->setValue(
                'urt_edit_user',
                CliApplication::optionBool($options, 'editable-by-user', false) ?? false
            );
        }
    }

    private static function applyInverseRelationTypeOptions(
        UserRelationType $type,
        array $options,
        bool $new
    ): void {
        $fallbackName = CliApplication::optionExists($options, 'inverse-name')
            ? self::requireTextOption($options, 'inverse-name')
            : (string)$type->getValue('urt_name', 'database');

        if (CliApplication::optionExists($options, 'inverse-name')) {
            $type->setValue('urt_name', $fallbackName);
            if ($new && !CliApplication::optionExists($options, 'inverse-name-male')) {
                $type->setValue('urt_name_male', $fallbackName);
            }
            if ($new && !CliApplication::optionExists($options, 'inverse-name-female')) {
                $type->setValue('urt_name_female', $fallbackName);
            }
        }
        if (CliApplication::optionExists($options, 'inverse-name-male')) {
            $value = CliApplication::optionString($options, 'inverse-name-male');
            $type->setValue('urt_name_male', $value !== '' ? $value : $fallbackName);
        }
        if (CliApplication::optionExists($options, 'inverse-name-female')) {
            $value = CliApplication::optionString($options, 'inverse-name-female');
            $type->setValue('urt_name_female', $value !== '' ? $value : $fallbackName);
        }
        if (CliApplication::optionExists($options, 'inverse-editable-by-user')) {
            $type->setValue(
                'urt_edit_user',
                CliApplication::optionBool($options, 'inverse-editable-by-user', false) ?? false
            );
        }
    }

    private static function resolveGroup(string $reference): Role
    {
        global $gDb, $gCurrentOrgId;

        if ($reference === '') {
            throw new InvalidArgumentException('Group reference must not be empty.');
        }

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT rol_id
                   FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE rol_id = ?
                    AND (cat_org_id = ? OR cat_org_id IS NULL)',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT rol_id
                   FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE (rol_uuid = ? OR UPPER(rol_name) = UPPER(?))
                    AND (cat_org_id = ? OR cat_org_id IS NULL)',
                array($reference, $reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        $ids = array_values(array_unique(array_map('intval', $rows)));
        if (count($ids) !== 1) {
            throw new InvalidArgumentException(
                count($ids) === 0
                    ? 'Group "' . $reference . '" was not found.'
                    : 'Group name "' . $reference . '" is ambiguous; use UUID or id.'
            );
        }

        return new Role($gDb, $ids[0]);
    }

    private static function assertRoleNameUnique(string $name, int $categoryId, int $excludeRoleId): void
    {
        global $gDb, $gCurrentOrgId;

        $count = (int)$gDb->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_ROLES . '
         INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
              WHERE rol_name = ?
                AND rol_cat_id = ?
                AND rol_id <> ?
                AND (cat_org_id = ? OR cat_org_id IS NULL)',
            array($name, $categoryId, $excludeRoleId, $gCurrentOrgId)
        )->fetchColumn();

        if ($count > 0) {
            throw new Exception('SYS_ROLE_NAME_EXISTS');
        }
    }

    private static function applyRoleOptions(Role $role, array $options): void
    {
        $simple = array(
            'description' => 'rol_description',
            'location' => 'rol_location'
        );
        foreach ($simple as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $role->setValue($column, CliApplication::optionString($options, $option));
            }
        }

        $modeColumns = array(
            'mail-to-role' => 'rol_mail_this_role',
            'view-memberships' => 'rol_view_memberships',
            'view-profiles' => 'rol_view_members_profiles',
            'leader-rights' => 'rol_leader_rights'
        );
        foreach ($modeColumns as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $mode = (int)CliApplication::optionString($options, $option);
                if ($mode < 0 || $mode > 3) {
                    throw new InvalidArgumentException('--' . $option . ' must be between 0 and 3.');
                }
                $role->setValue($column, $mode);
            }
        }

        if (CliApplication::optionExists($options, 'default-list')) {
            $reference = CliApplication::optionString($options, 'default-list');
            $role->setValue('rol_lst_id', $reference === '' ? 0 : (int)self::resolveList($reference)->getValue('lst_id'));
        }
        if (CliApplication::optionExists($options, 'default-registration')) {
            $role->setValue('rol_default_registration', CliApplication::optionBool($options, 'default-registration', false) ?? false);
        }
        if (CliApplication::optionExists($options, 'max-members')) {
            $value = CliApplication::optionString($options, 'max-members');
            if ($value !== '' && (!ctype_digit($value) || (int)$value < 0)) {
                throw new InvalidArgumentException('--max-members must be a non-negative integer.');
            }
            $role->setValue('rol_max_members', $value === '' ? 0 : (int)$value);
        }
        if (CliApplication::optionExists($options, 'cost')) {
            $value = CliApplication::optionString($options, 'cost');
            if ($value !== '' && !is_numeric($value)) {
                throw new InvalidArgumentException('--cost must be numeric.');
            }
            $role->setValue('rol_cost', $value === '' ? 0 : (float)$value);
        }
        if (CliApplication::optionExists($options, 'cost-period')) {
            $value = CliApplication::optionString($options, 'cost-period');
            if ($value !== '' && !ctype_digit($value)) {
                throw new InvalidArgumentException('--cost-period must be numeric.');
            }
            $role->setValue('rol_cost_period', $value === '' ? 0 : (int)$value);
        }

        foreach (array('start' => 'rol_start_date', 'end' => 'rol_end_date') as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $value = CliApplication::optionString($options, $option);
                if ($value !== '') {
                    self::validateDate($value, '--' . $option);
                }
                $role->setValue($column, $value);
            }
        }

        $start = (string)$role->getValue('rol_start_date', 'database');
        $end = (string)$role->getValue('rol_end_date', 'database');
        if ($start !== '' && $end !== '') {
            self::validateDateRange($start, $end);
        }

        foreach (array('start-time' => 'rol_start_time', 'end-time' => 'rol_end_time') as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $value = CliApplication::optionString($options, $option);
                if ($value !== '') {
                    $parsed = \DateTimeImmutable::createFromFormat('!H:i', $value);
                    $errors = \DateTimeImmutable::getLastErrors();
                    if ($parsed === false
                        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                        || $parsed->format('H:i') !== $value) {
                        throw new InvalidArgumentException('--' . $option . ' must use HH:MM.');
                    }
                    $value = $parsed->format('H:i:s');
                }
                $role->setValue($column, $value);
            }
        }

        if (CliApplication::optionExists($options, 'weekday')) {
            $weekday = (int)CliApplication::optionString($options, 'weekday');
            if ($weekday < 0 || $weekday > 7) {
                throw new InvalidArgumentException('--weekday must be between 0 and 7.');
            }
            $role->setValue('rol_weekday', $weekday);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function roleData(Role $role): array
    {
        return array(
            'id' => (int)$role->getValue('rol_id'),
            'uuid' => (string)$role->getValue('rol_uuid'),
            'category_id' => (int)$role->getValue('rol_cat_id'),
            'name' => $role->getValue('rol_name', 'database'),
            'description' => $role->getValue('rol_description', 'database'),
            'valid' => (bool)$role->getValue('rol_valid'),
            'system' => (bool)$role->getValue('rol_system'),
            'administrator' => (bool)$role->getValue('rol_administrator'),
            'mail_to_role' => (int)$role->getValue('rol_mail_this_role'),
            'view_memberships' => (int)$role->getValue('rol_view_memberships'),
            'view_profiles' => (int)$role->getValue('rol_view_members_profiles'),
            'leader_rights' => (int)$role->getValue('rol_leader_rights'),
            'default_list_id' => (int)$role->getValue('rol_lst_id'),
            'default_registration' => (bool)$role->getValue('rol_default_registration'),
            'max_members' => (int)$role->getValue('rol_max_members'),
            'cost' => $role->getValue('rol_cost'),
            'cost_period' => (int)$role->getValue('rol_cost_period'),
            'start_date' => $role->getValue('rol_start_date', 'database'),
            'end_date' => $role->getValue('rol_end_date', 'database'),
            'start_time' => $role->getValue('rol_start_time', 'database'),
            'end_time' => $role->getValue('rol_end_time', 'database'),
            'weekday' => $role->getValue('rol_weekday'),
            'location' => $role->getValue('rol_location', 'database')
        );
    }

    /**
     * @return array<int,string>
     */
    private static function rolePermissionColumns(): array
    {
        return array(
            'rol_assign_roles',
            'rol_approve_users',
            'rol_announcements',
            'rol_events',
            'rol_documents_files',
            'rol_inventory_admin',
            'rol_edit_user',
            'rol_forum_admin',
            'rol_mail_to_all',
            'rol_photo',
            'rol_profile',
            'rol_weblinks',
            'rol_all_lists_view'
        );
    }

    /**
     * @return array<string,bool>
     */
    private static function rolePermissionData(Role $role): array
    {
        $data = array();
        foreach (self::rolePermissionColumns() as $column) {
            $data[$column] = (bool)$role->getValue($column);
        }
        return $data;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function membershipRows(Role $role, string $date, string $state, bool $leadersOnly): array
    {
        global $gDb;

        self::validateDate($date, '--date');

        $conditions = array('mem_rol_id = ?');
        $params = array((int)$role->getValue('rol_id'));

        if ($state === 'active') {
            $conditions[] = 'mem_begin <= ? AND mem_end >= ?';
            array_push($params, $date, $date);
        } elseif ($state === 'former') {
            $conditions[] = 'mem_end < ?';
            $params[] = $date;
        } elseif ($state === 'future') {
            $conditions[] = 'mem_begin > ?';
            $params[] = $date;
        }

        if ($leadersOnly) {
            $conditions[] = 'mem_leader = true';
        }

        return $gDb->queryPrepared(
            'SELECT mem_id AS id, mem_uuid AS uuid, mem_usr_id AS user_id,
                    usr_uuid AS user_uuid, usr_login_name AS login,
                    mem_begin AS start_date, mem_end AS end_date,
                    mem_leader AS leader
               FROM ' . TBL_MEMBERS . '
         INNER JOIN ' . TBL_USERS . ' ON usr_id = mem_usr_id
              WHERE ' . implode(' AND ', $conditions) . '
           ORDER BY mem_begin, usr_login_name, mem_id',
            $params
        )->fetchAll();
    }

    private static function resolveMembership(string $reference): Membership
    {
        global $gDb;

        $id = CliApplication::resolveId(
            TBL_MEMBERS,
            'mem_id',
            'mem_uuid',
            $reference,
            'membership'
        );
        return new Membership($gDb, $id);
    }

    /**
     * @param bool $allowLatest Fall back to the most recent membership if none covers $date.
     */
    private static function resolveMembershipForDate(
        Role $role,
        User $user,
        string $date,
        bool $allowLatest = false
    ): Membership {
        global $gDb;

        self::validateDate($date, 'membership date');
        $roleId = (int)$role->getValue('rol_id');
        $userId = (int)$user->getValue('usr_id');

        $ids = $gDb->queryPrepared(
            'SELECT mem_id
               FROM ' . TBL_MEMBERS . '
              WHERE mem_rol_id = ?
                AND mem_usr_id = ?
                AND ? BETWEEN mem_begin AND mem_end
           ORDER BY mem_begin DESC, mem_id DESC',
            array($roleId, $userId, $date)
        )->fetchAll(PDO::FETCH_COLUMN);

        if (count($ids) === 0 && $allowLatest) {
            $ids = array_slice(
                $gDb->queryPrepared(
                    'SELECT mem_id
                       FROM ' . TBL_MEMBERS . '
                      WHERE mem_rol_id = ?
                        AND mem_usr_id = ?
                   ORDER BY mem_begin DESC, mem_id DESC',
                    array($roleId, $userId)
                )->fetchAll(PDO::FETCH_COLUMN),
                0,
                1
            );
        }

        if (count($ids) !== 1) {
            throw new InvalidArgumentException(
                count($ids) === 0
                    ? 'No membership exists for the selected date.'
                    : 'Several membership periods overlap on the selected date.'
            );
        }
        return new Membership($gDb, (int)$ids[0]);
    }

    private static function resolveList(string $reference): ListConfiguration
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT lst_id FROM ' . TBL_LISTS . ' WHERE lst_id = ? AND lst_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT lst_id FROM ' . TBL_LISTS . ' WHERE lst_uuid = ? AND lst_org_id = ?',
                array($reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if (count($rows) !== 1) {
            throw new InvalidArgumentException('List configuration was not found.');
        }
        return new ListConfiguration($gDb, (int)$rows[0]);
    }

    private static function assertListEditableOrVisible(ListConfiguration $list, bool $edit): void
    {
        global $gCurrentUser, $gCurrentUserId;

        $owner = (int)$list->getValue('lst_usr_id') === $gCurrentUserId;
        $global = (bool)$list->getValue('lst_global');

        if ($edit) {
            if (!$owner && !$gCurrentUser->isAdministratorRoles()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            return;
        }

        if (!$owner && !$global && !$gCurrentUser->isAdministratorRoles() && !$gCurrentUser->checkRolesRight('rol_all_lists_view')) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function getListColumnRows(int $listId): array
    {
        global $gDb;
        return $gDb->queryPrepared(
            'SELECT lsc_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter
               FROM ' . TBL_LIST_COLUMNS . '
              WHERE lsc_lst_id = ?
           ORDER BY lsc_number, lsc_id',
            array($listId)
        )->fetchAll();
    }

    /**
     * @return array<string,mixed>
     */
    private static function listData(ListConfiguration $list): array
    {
        return array(
            'id' => (int)$list->getValue('lst_id'),
            'uuid' => (string)$list->getValue('lst_uuid'),
            'organization_id' => (int)$list->getValue('lst_org_id'),
            'user_id' => (int)$list->getValue('lst_usr_id'),
            'name' => $list->getValue('lst_name', 'database'),
            'global' => (bool)$list->getValue('lst_global'),
            'columns' => self::getListColumnRows((int)$list->getValue('lst_id'))
        );
    }

    private static function applyListColumns(ListConfiguration $list, array $options, bool $allowColumns): void
    {
        $columns = CliApplication::optionValues($options, 'column');
        $conditions = array();
        foreach (CliApplication::optionValues($options, 'condition') as $assignment) {
            [$column, $filter] = self::splitAssignment($assignment, '--condition');
            $conditions[$column] = $filter;
        }

        $sorts = array();
        foreach (CliApplication::optionValues($options, 'sort') as $sort) {
            $position = strrpos($sort, ':');
            if ($position === false || $position === 0) {
                throw new InvalidArgumentException('--sort expects COLUMN:asc|desc.');
            }
            $column = substr($sort, 0, $position);
            $direction = strtoupper(substr($sort, $position + 1));
            if (!in_array($direction, array('ASC', 'DESC'), true)) {
                throw new InvalidArgumentException('--sort direction must be asc or desc.');
            }
            $sorts[$column] = $direction;
        }

        if ($columns === array()) {
            if ($conditions === array() && $sorts === array()) {
                return;
            }

            $existing = self::getListColumnRows((int)$list->getValue('lst_id'));
            if ($existing === array()) {
                return;
            }
            $list->deleteColumn(1, true);

            foreach ($existing as $column) {
                $selector = (int)$column['lsc_usf_id'] > 0
                    ? (string)$column['lsc_usf_id']
                    : (string)$column['lsc_special_field'];
                $field = (int)$column['lsc_usf_id'] > 0
                    ? (int)$column['lsc_usf_id']
                    : (string)$column['lsc_special_field'];
                $filter = $conditions[$selector] ?? (string)$column['lsc_filter'];
                $sort = $sorts[$selector] ?? (string)$column['lsc_sort'];
                if (!$list->addColumn($field, 0, $sort, $filter)) {
                    throw new InvalidArgumentException('Invalid list column "' . $selector . '".');
                }
            }
            return;
        }

        if (!$allowColumns) {
            throw new InvalidArgumentException('Columns cannot be replaced in this operation.');
        }

        foreach ($columns as $selector) {
            $field = self::resolveListColumn($selector);
            $filter = $conditions[$selector] ?? '';
            $sort = $sorts[$selector] ?? '';
            if (!$list->addColumn($field, 0, $sort, $filter)) {
                throw new InvalidArgumentException('Invalid list column "' . $selector . '".');
            }
        }

        $known = array_fill_keys($columns, true);
        foreach (array_keys($conditions + $sorts) as $selector) {
            if (!isset($known[$selector])) {
                throw new InvalidArgumentException(
                    'Filter/sort column "' . $selector . '" is not present in --column.'
                );
            }
        }
    }

    private static function resolveListColumn(string $reference): int|string
    {
        if (ctype_digit($reference)) {
            $field = self::resolveProfileField($reference);
            return (int)$field->getValue('usf_id');
        }

        try {
            $field = self::resolveProfileField($reference);
            return (int)$field->getValue('usf_id');
        } catch (InvalidArgumentException) {
            // ListConfiguration::addColumn() performs the canonical validation of special fields.
            return $reference;
        }
    }

    private static function assertCategoryType(string $type): void
    {
        if (!in_array($type, array('ANN', 'AWA', 'EVT', 'FOT', 'IVT', 'LNK', 'ROL', 'USF'), true)) {
            throw new InvalidArgumentException('Unknown category type "' . $type . '".');
        }
    }

    private static function resolveCategory(string $reference, string $requiredType = ''): Category
    {
        global $gDb, $gCurrentOrgId;

        if ($requiredType !== '') {
            self::assertCategoryType($requiredType);
        }
        if ($reference === '') {
            throw new InvalidArgumentException('Category reference must not be empty.');
        }

        $params = array();
        if (ctype_digit($reference)) {
            $where = 'cat_id = ?';
            $params[] = (int)$reference;
        } else {
            $where = '(cat_uuid = ? OR cat_name = ? OR cat_name_intern = ?)';
            array_push($params, $reference, $reference, $reference);
        }
        $where .= ' AND (cat_org_id = ? OR cat_org_id IS NULL)';
        $params[] = $gCurrentOrgId;
        if ($requiredType !== '') {
            $where .= ' AND cat_type = ?';
            $params[] = $requiredType;
        }

        $ids = array_values(array_unique(array_map(
            'intval',
            $gDb->queryPrepared('SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE ' . $where, $params)
                ->fetchAll(PDO::FETCH_COLUMN)
        )));

        if (count($ids) === 0 && !ctype_digit($reference)) {
            $translatedRows = $gDb->queryPrepared(
                'SELECT cat_id, cat_name
                   FROM ' . TBL_CATEGORIES . '
                  WHERE (cat_org_id = ? OR cat_org_id IS NULL)'
                    . ($requiredType !== '' ? ' AND cat_type = ?' : ''),
                $requiredType !== '' ? array($gCurrentOrgId, $requiredType) : array($gCurrentOrgId)
            )->fetchAll();

            foreach ($translatedRows as $row) {
                if (strcasecmp(Language::translateIfTranslationStrId((string)$row['cat_name']), $reference) === 0) {
                    $ids[] = (int)$row['cat_id'];
                }
            }
            $ids = array_values(array_unique($ids));
        }

        if (count($ids) !== 1) {
            throw new InvalidArgumentException(
                count($ids) === 0
                    ? 'Category "' . $reference . '" was not found.'
                    : 'Category name "' . $reference . '" is ambiguous; use UUID or id.'
            );
        }

        return new Category($gDb, $ids[0]);
    }

    private static function setCategoryOrganizationScope(
        Category $category,
        array $options,
        bool $newRecord
    ): void {
        global $gCurrentOrganization, $gCurrentOrgId;

        $type = (string)$category->getValue('cat_type');
        $systemCategory = (bool)$category->getValue('cat_system');
        $nameIntern = (string)$category->getValue('cat_name_intern');

        if ($type === 'ROL') {
            // The EVENTS role category is the one global role-category exception in current master.
            $category->setValue('cat_org_id', $nameIntern === 'EVENTS' ? 0 : $gCurrentOrgId);
            return;
        }

        if ($type === 'USF' && ($gCurrentOrganization->countAllRecords() === 1 || $systemCategory)) {
            $category->setValue('cat_org_id', 0);
            return;
        }

        if (CliApplication::optionExists($options, 'multi-organization')) {
            $category->setValue(
                'cat_org_id',
                CliApplication::optionBool($options, 'multi-organization', false) ? 0 : $gCurrentOrgId
            );
        } elseif ($newRecord) {
            $category->setValue('cat_org_id', $gCurrentOrgId);
        }
    }

    private static function assertCategoryRightsInput(
        Category $category,
        array $options,
        bool $newRecord
    ): void {
        global $gCurrentOrganization;

        $type = (string)$category->getValue('cat_type');
        $nameIntern = (string)$category->getValue('cat_name_intern');

        if (($type === 'ROL' || $nameIntern === 'BASIC_DATA')
            && (CliApplication::optionExists($options, 'view-role')
                || CliApplication::optionExists($options, 'edit-role'))) {
            throw new InvalidArgumentException(
                'View/edit RolesRights are not configurable for this category type.'
            );
        }

        if ($newRecord
            && $type !== 'ROL'
            && ((bool)$category->getValue('cat_system') === false
                || $gCurrentOrganization->countAllRecords() === 1)
            && !CliApplication::optionExists($options, 'view-role')) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_VISIBLE_FOR'));
        }
    }

    private static function resequenceCategories(string $type): void
    {
        global $gDb, $gCurrentOrgId;

        $statement = $gDb->queryPrepared(
            'SELECT *
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND (cat_org_id = ? OR cat_org_id IS NULL)
           ORDER BY cat_org_id, cat_sequence',
            array($type, $gCurrentOrgId)
        );

        /*
         * Only categories whose position actually changes are written. Saving every category of
         * the type on every category:add and category:update produced a write and a changelog
         * record per category even when the order was already correct.
         */
        $sequence = 0;
        $category = new Category($gDb);
        while ($row = $statement->fetch()) {
            ++$sequence;
            if ((int)$row['cat_sequence'] === $sequence) {
                continue;
            }

            $category->clear();
            $category->setArray($row);
            $category->setValue('cat_sequence', $sequence);
            $category->save();
        }
    }

    private static function assertCategoryNameUnique(Category $category, string $name): void
    {
        global $gDb, $gCurrentOrgId;

        $count = (int)$gDb->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND cat_name = ?
                AND cat_id <> ?
                AND (cat_org_id = ? OR cat_org_id IS NULL)',
            array(
                (string)$category->getValue('cat_type'),
                $name,
                (int)$category->getValue('cat_id'),
                $gCurrentOrgId
            )
        )->fetchColumn();

        if ($count > 0) {
            throw new Exception('SYS_CATEGORY_EXISTS_IN_ORGA');
        }
    }

    private static function saveCategoryRights(Category $category, array $options, bool $newRecord): void
    {
        global $gDb, $gCurrentOrganization, $gCurrentOrgId, $gProfileFields;

        $type = (string)$category->getValue('cat_type');
        $nameIntern = (string)$category->getValue('cat_name_intern');

        if ($type === 'ROL' || $nameIntern === 'BASIC_DATA') {
            return;
        }

        $id = (int)$category->getValue('cat_id');
        $organizationCount = $gCurrentOrganization->countAllRecords();
        $categoryOrganizationId = (int)$category->getValue('cat_org_id');
        $viewRight = new RolesRights($gDb, 'category_view', $id);

        if ($categoryOrganizationId > 0 || ($categoryOrganizationId === 0 && $organizationCount === 1)) {
            if (CliApplication::optionExists($options, 'view-role')) {
                $viewRight->saveRoles(
                    self::resolveRoleIds(CliApplication::optionValues($options, 'view-role'))
                );
            }
        } else {
            // Current web behavior does not support role-specific visibility for categories
            // that are shared by several organizations.
            $viewRight->delete();
        }

        if ($type === 'USF') {
            // ProfileFields caches category visibility information.
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        } else {
            $editRight = new RolesRights($gDb, 'category_edit', $id);
            if (CliApplication::optionExists($options, 'edit-role')) {
                $editRight->saveRoles(
                    self::resolveRoleIds(CliApplication::optionValues($options, 'edit-role'))
                );
            } elseif ($newRecord) {
                $editRight->saveRoles(array());
            }
        }

        self::reloadAllSessions();
    }

    private static function makeDefaultCategory(Category $category): void
    {
        $category->setValue('cat_default', true);
        $category->save();
    }

    private static function resolveMenu(string $reference): MenuEntry
    {
        global $gDb;
        $id = CliApplication::resolveId(TBL_MENU, 'men_id', 'men_uuid', $reference, 'menu entry');
        return new MenuEntry($gDb, $id);
    }

    private static function applyMenuOptions(MenuEntry $menu, array $options, bool $new): void
    {
        global $gDb;
        if (CliApplication::optionExists($options, 'name')) {
            $menu->setValue('men_name', self::requireTextOption($options, 'name'));
        }
        foreach (array(
            'description' => 'men_description',
            'url' => 'men_url',
            'icon' => 'men_icon'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $menu->setValue($column, CliApplication::optionString($options, $option));
            }
        }

        if (CliApplication::optionExists($options, 'node')) {
            $menu->setValue('men_node', CliApplication::optionBool($options, 'node', false) ?? false);
        }
        if (CliApplication::optionExists($options, 'parent')) {
            $parentRef = CliApplication::optionString($options, 'parent');
            $menu->setValue(
                'men_men_id_parent',
                $parentRef === '' ? 0 : (int)self::resolveMenu($parentRef)->getValue('men_id')
            );
        }
        if (CliApplication::optionExists($options, 'component')) {
            $component = CliApplication::optionString($options, 'component');
            if ($component === '') {
                $menu->setValue('men_com_id', 0);
            } else {
                $id = (int)$gDb->queryPrepared(
                    'SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE UPPER(com_name_intern) = UPPER(?)',
                    array($component)
                )->fetchColumn();
                if ($id === 0) {
                    throw new InvalidArgumentException('Unknown component "' . $component . '".');
                }
                $menu->setValue('men_com_id', $id);
            }
        }
    }

    private static function saveMenuRights(MenuEntry $menu, array $options): void
    {
        global $gDb;
        if (!CliApplication::optionExists($options, 'view-role')) {
            return;
        }
        (new RolesRights($gDb, 'menu_view', (int)$menu->getValue('men_id')))
            ->saveRoles(self::resolveRoleIds(CliApplication::optionValues($options, 'view-role')));
        self::reloadAllSessions();
    }

    /**
     * Restrict a listing to the categories the acting user may view.
     *
     * The list commands used to build one Entity per result row only to call isVisible(), which for
     * announcements, events, forum topics and weblinks is nothing but a membership test against
     * User::getAllVisibleCategories(). Reading that list once and filtering in SQL removes a query
     * per row, and it makes LIMIT/OFFSET exact because the filter is applied by the database.
     *
     * @param array<int,string> $where
     * @param array<int,mixed> $params
     * @return bool False if the user may not see any category of that type, so the result is empty.
     */
    private static function restrictToVisibleCategories(
        string $categoryType,
        string $categoryColumn,
        array &$where,
        array &$params
    ): bool {
        global $gCurrentUser;

        $visibleCategories = array_map('intval', $gCurrentUser->getAllVisibleCategories($categoryType));
        if ($visibleCategories === array()) {
            return false;
        }

        $where[] = $categoryColumn . ' IN (' . implode(', ', array_fill(0, count($visibleCategories), '?')) . ')';
        array_push($params, ...$visibleCategories);

        return true;
    }

    private static function resolveEvent(string $reference): Event
    {
        global $gDb;

        $id = CliApplication::resolveId(TBL_EVENTS, 'dat_id', 'dat_uuid', $reference, 'event');
        return new Event($gDb, $id);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function eventOptions(): array
    {
        return array(
            self::opt('headline', 'Event title.', 'TEXT'),
            self::opt('from', 'Event start date/time (YYYY-MM-DDTHH:MM).', 'DATETIME'),
            self::opt('to', 'Event end date/time (YYYY-MM-DDTHH:MM).', 'DATETIME'),
            self::opt('calendar', 'Event category/calendar.', 'CATEGORY'),
            self::opt('location', 'Event location.', 'TEXT'),
            self::opt('country', 'Two-letter country code.', 'COUNTRY'),
            self::opt('room', 'Room UUID/id/name; empty value clears the room.', 'ROOM'),
            self::opt('all-day', 'All-day event flag.', 'BOOL'),
            self::opt('highlight', 'Highlight event flag.', 'BOOL'),
            self::opt('participation', 'Enable participation.', 'BOOL'),
            self::opt('participation-role', 'Group allowed to participate.', 'GROUP', false, true),
            self::opt('participate-self', 'Assign the acting user as event leader.', 'BOOL'),
            self::opt('allow-comments', 'Allow participation comments.', 'BOOL'),
            self::opt('allow-guests', 'Allow additional guests.', 'BOOL'),
            self::opt('max-members', 'Maximum number of participants.', 'N'),
            self::opt('deadline', 'Participation deadline (YYYY-MM-DDTHH:MM), empty value clears it.', 'DATETIME'),
            self::opt('participants-visible', 'Participants may view the participants list.', 'BOOL'),
            self::opt('participants-mail', 'Participants may send mail to the event role.', 'BOOL'),
            self::opt('description', 'Event description.', 'TEXT'),
            self::opt('description-file', 'Read event description from a file.', 'FILE'),
            self::opt('repeat', 'Recurrence frequency.', 'FREQUENCY', false, false, false, array('none', 'daily', 'weekly', 'monthly', 'yearly')),
            self::opt('interval', 'Recurrence interval, e.g. 2 for every second week.', 'N'),
            self::opt('weekday', 'Weekly recurrence weekday.', 'DAY', false, true, false, EventRecurrenceRule::WEEKDAYS),
            self::opt('ends', 'Recurrence end mode.', 'END', false, false, false, array('never', 'count', 'until')),
            self::opt('count', 'Recurrence occurrence count.', 'N'),
            self::opt('until', 'Recurrence end date (YYYY-MM-DD).', 'DATE'),
            self::opt('recurrence-scope', 'Scope for editing recurring events.', 'SCOPE', false, false, false, array('this', 'series'))
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function eventData(Event $event): array
    {
        return array(
            'id' => (int)$event->getValue('dat_id'),
            'uuid' => (string)$event->getValue('dat_uuid'),
            'calendar_id' => (int)$event->getValue('dat_cat_id'),
            'participation_role_id' => (int)$event->getValue('dat_rol_id'),
            'room_id' => (int)$event->getValue('dat_room_id'),
            'recurrence_id' => (int)$event->getValue('dat_evr_id'),
            'recurrence_status' => $event->getValue('dat_recurrence_status', 'database'),
            'recurrence_original_begin' => $event->getValue('dat_recurrence_original_begin', 'Y-m-d H:i:s'),
            'headline' => $event->getValue('dat_headline', 'database'),
            'begin' => $event->getValue('dat_begin', 'Y-m-d H:i:s'),
            'end' => $event->getValue('dat_end', 'Y-m-d H:i:s'),
            'all_day' => (bool)$event->getValue('dat_all_day'),
            'description' => $event->getValue('dat_description', 'database'),
            'location' => $event->getValue('dat_location', 'database'),
            'country' => $event->getValue('dat_country', 'database'),
            'deadline' => $event->getValue('dat_deadline', 'Y-m-d H:i:s'),
            'max_members' => (int)$event->getValue('dat_max_members'),
            'allow_comments' => (bool)$event->getValue('dat_allow_comments'),
            'additional_guests' => (bool)$event->getValue('dat_additional_guests')
        );
    }

    /**
     * Build the same data array that the current event FormPresenter passes to EventService.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function buildEventFormValues(Event $event, array $options, bool $new): array
    {
        global $gCurrentUser, $gDb, $gSettingsManager;

        $participationRoleId = $new ? 0 : (int)$event->getValue('dat_rol_id');
        $participationPossible = $participationRoleId > 0;

        if ($new) {
            $formValues = array(
                'dat_headline' => '',
                'dat_location' => '',
                'dat_country' => null,
                'dat_room_id' => 0,
                'dat_all_day' => 0,
                'event_from' => '',
                'event_from_time' => '',
                'event_to' => '',
                'event_to_time' => '',
                'cat_uuid' => '',
                'dat_highlight' => 0,
                'event_participation_possible' => 0,
                'adm_event_participation_right' => array(),
                'event_current_user_assigned' => 1,
                'dat_allow_comments' => 0,
                'dat_additional_guests' => 0,
                'dat_max_members' => 0,
                'event_deadline' => '',
                'event_deadline_time' => '',
                'event_right_list_view' => 0,
                'event_right_send_mail' => 0,
                'dat_description' => '',
                'event_recurrence_frequency' => 'none',
                'event_recurrence_interval' => 1,
                'event_recurrence_weekdays' => array(),
                'event_recurrence_end_type' => EventRecurrenceRule::END_TYPE_NEVER,
                'event_recurrence_count' => '',
                'event_recurrence_until' => ''
            );
        } else {
            $begin = (string)$event->getValue('dat_begin', 'Y-m-d H:i');
            $end = (string)$event->getValue('dat_end', 'Y-m-d H:i');
            $deadline = (string)$event->getValue('dat_deadline', 'Y-m-d H:i');

            $calendarUuid = (string)$gDb->queryPrepared(
                'SELECT cat_uuid FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?',
                array((int)$event->getValue('dat_cat_id'))
            )->fetchColumn();
            if ($calendarUuid === '') {
                throw new InvalidArgumentException('The event calendar could not be resolved.');
            }

            $rightEventParticipation = new RolesRights($gDb, 'event_participation', (int)$event->getValue('dat_id'));

            $rightListView = false;
            $rightSendMail = false;
            if ($participationRoleId > 0) {
                $participationRole = new Role($gDb, $participationRoleId);
                $rightListView = $participationRole->getValue('rol_view_memberships') === Role::VIEW_ROLE_MEMBERS;
                $rightSendMail = $participationRole->getValue('rol_mail_this_role') === Role::VIEW_ROLE_MEMBERS;
            }

            $formValues = array(
                'dat_headline' => (string)$event->getValue('dat_headline', 'database'),
                'dat_location' => (string)$event->getValue('dat_location', 'database'),
                'dat_country' => $event->getValue('dat_country', 'database'),
                'dat_room_id' => (int)$event->getValue('dat_room_id'),
                'dat_all_day' => (int)(bool)$event->getValue('dat_all_day'),
                'event_from' => substr($begin, 0, 10),
                'event_from_time' => substr($begin, 11, 5),
                'event_to' => substr($end, 0, 10),
                'event_to_time' => substr($end, 11, 5),
                'cat_uuid' => $calendarUuid,
                'dat_highlight' => (int)(bool)$event->getValue('dat_highlight'),
                'event_participation_possible' => (int)$participationPossible,
                'adm_event_participation_right' => $rightEventParticipation->getRolesIds(),
                'event_current_user_assigned' => (int)(
                    $participationRoleId > 0 && $gCurrentUser->isLeaderOfRole($participationRoleId)
                ),
                'dat_allow_comments' => (int)(bool)$event->getValue('dat_allow_comments'),
                'dat_additional_guests' => (int)(bool)$event->getValue('dat_additional_guests'),
                'dat_max_members' => (int)$event->getValue('dat_max_members'),
                'event_deadline' => $deadline === '' ? '' : substr($deadline, 0, 10),
                'event_deadline_time' => $deadline === '' ? '' : substr($deadline, 11, 5),
                'event_right_list_view' => (int)$rightListView,
                'event_right_send_mail' => (int)$rightSendMail,
                'dat_description' => (string)$event->getValue('dat_description', 'database'),
                'event_recurrence_frequency' => 'none',
                'event_recurrence_interval' => 1,
                'event_recurrence_weekdays' => array(),
                'event_recurrence_end_type' => EventRecurrenceRule::END_TYPE_NEVER,
                'event_recurrence_count' => '',
                'event_recurrence_until' => ''
            );

            self::applyExistingRecurrenceValues($event, $formValues);
        }

        if (CliApplication::optionExists($options, 'headline')) {
            $formValues['dat_headline'] = CliApplication::optionString($options, 'headline');
        }
        if (trim((string)$formValues['dat_headline']) === '') {
            throw new InvalidArgumentException('--headline must not be empty.');
        }

        if (CliApplication::optionExists($options, 'from')) {
            [$formValues['event_from'], $formValues['event_from_time']] =
                self::splitEventDateTime(CliApplication::optionString($options, 'from'), '--from');
        }
        if ((string)$formValues['event_from'] === '') {
            throw new InvalidArgumentException('--from is required.');
        }

        if (CliApplication::optionExists($options, 'to')) {
            [$formValues['event_to'], $formValues['event_to_time']] =
                self::splitEventDateTime(CliApplication::optionString($options, 'to'), '--to');
        } elseif ($new) {
            $formValues['event_to'] = $formValues['event_from'];
            $formValues['event_to_time'] = $formValues['event_from_time'];
        }

        if (CliApplication::optionExists($options, 'calendar')) {
            $calendar = self::resolveCategory(CliApplication::optionString($options, 'calendar'), 'EVT');
            $formValues['cat_uuid'] = (string)$calendar->getValue('cat_uuid');
        }
        if ((string)$formValues['cat_uuid'] === '') {
            throw new InvalidArgumentException('--calendar is required.');
        }

        foreach (array('location' => 'dat_location', 'country' => 'dat_country') as $option => $field) {
            if (CliApplication::optionExists($options, $option)) {
                $formValues[$field] = CliApplication::optionString($options, $option);
            }
        }

        if (CliApplication::optionExists($options, 'room')) {
            if (!$gSettingsManager->getBool('events_rooms_enabled')) {
                throw new InvalidArgumentException('Rooms are disabled for events.');
            }

            $room = CliApplication::optionString($options, 'room');
            $formValues['dat_room_id'] = $room === '' || $room === '0'
                ? 0
                : (int)self::resolveRoom($room)->getValue('room_id');
        }

        foreach (array(
            'all-day' => 'dat_all_day',
            'highlight' => 'dat_highlight',
            'participation' => 'event_participation_possible',
            'participate-self' => 'event_current_user_assigned',
            'allow-comments' => 'dat_allow_comments',
            'allow-guests' => 'dat_additional_guests',
            'participants-visible' => 'event_right_list_view',
            'participants-mail' => 'event_right_send_mail'
        ) as $option => $field) {
            if (CliApplication::optionExists($options, $option)) {
                $formValues[$field] = (int)(CliApplication::optionBool($options, $option, false) ?? false);
            }
        }

        if (CliApplication::optionExists($options, 'max-members')) {
            $maxMembers = CliApplication::optionInt($options, 'max-members');
            if ($maxMembers === null || $maxMembers < 0) {
                throw new InvalidArgumentException('--max-members must be a non-negative integer.');
            }
            $formValues['dat_max_members'] = $maxMembers;
        }

        if (CliApplication::optionExists($options, 'participation-role')) {
            $formValues['adm_event_participation_right'] = self::resolveRoleIds(
                CliApplication::optionValues($options, 'participation-role')
            );
        }

        if (empty($formValues['event_participation_possible'])) {
            $formValues['adm_event_participation_right'] = array();
        }

        if (CliApplication::optionExists($options, 'deadline')) {
            $deadline = CliApplication::optionString($options, 'deadline');
            if ($deadline === '') {
                $formValues['event_deadline'] = '';
                $formValues['event_deadline_time'] = '';
            } else {
                [$formValues['event_deadline'], $formValues['event_deadline_time']] =
                    self::splitEventDateTime($deadline, '--deadline');
            }
        }

        if (CliApplication::optionExists($options, 'description')
            || CliApplication::optionExists($options, 'description-file')) {
            $formValues['dat_description'] = self::readTextOption($options, 'description', 'description-file');
        }

        self::applyRecurrenceOptions($formValues, $options);

        return $formValues;
    }

    /**
     * @param array<string,mixed> $formValues
     * @param array<string,mixed> $options
     */
    private static function applyRecurrenceOptions(array &$formValues, array $options): void
    {
        if (CliApplication::optionExists($options, 'repeat')) {
            $formValues['event_recurrence_frequency'] = CliApplication::optionString($options, 'repeat');
        }
        if (CliApplication::optionExists($options, 'interval')) {
            $interval = CliApplication::optionInt($options, 'interval');
            if ($interval === null || $interval < 1) {
                throw new InvalidArgumentException('--interval must be greater than or equal to 1.');
            }
            $formValues['event_recurrence_interval'] = $interval;
        }
        if (CliApplication::optionExists($options, 'weekday')) {
            $formValues['event_recurrence_weekdays'] = CliApplication::optionValues($options, 'weekday');
        }
        if (CliApplication::optionExists($options, 'ends')) {
            $formValues['event_recurrence_end_type'] = CliApplication::optionString($options, 'ends');
        }
        if (CliApplication::optionExists($options, 'count')) {
            $count = CliApplication::optionInt($options, 'count');
            if ($count === null || $count < 1) {
                throw new InvalidArgumentException('--count must be greater than or equal to 1.');
            }
            $formValues['event_recurrence_count'] = $count;
        }
        if (CliApplication::optionExists($options, 'until')) {
            $until = CliApplication::optionString($options, 'until');
            self::validateDate($until, '--until');
            $formValues['event_recurrence_until'] = $until;
        }
    }

    /**
     * @param array<string,mixed> $formValues
     */
    private static function applyExistingRecurrenceValues(Event $event, array &$formValues): void
    {
        global $gDb;

        $recurrenceId = (int)$event->getValue('dat_evr_id');
        $repository = new EventRecurrenceRepository($gDb);
        $recurrence = $recurrenceId > 0
            ? $repository->readById($recurrenceId)
            : $repository->readByMasterEventId((int)$event->getValue('dat_id'));

        if ($recurrence === null) {
            return;
        }

        $rule = $repository->toRule($recurrence);
        $formValues['event_recurrence_frequency'] = $rule->getFrequency();
        $formValues['event_recurrence_interval'] = $rule->getInterval();
        $formValues['event_recurrence_weekdays'] = $rule->getByDay();
        $formValues['event_recurrence_end_type'] = $rule->getEndType();
        $formValues['event_recurrence_count'] = $rule->getCount() ?? '';
        $formValues['event_recurrence_until'] = $rule->getUntil()?->format('Y-m-d') ?? '';
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitEventDateTime(string $value, string $label): array
    {
        $normalized = CliApplication::validateDateTime($value, $label);
        return array(substr($normalized, 0, 10), substr($normalized, 11, 5));
    }

    private static function resolveRoom(string $reference): Room
    {
        global $gDb;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT room_id FROM ' . TBL_ROOMS . ' WHERE room_id = ?',
                array((int)$reference)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT room_id FROM ' . TBL_ROOMS . ' WHERE room_uuid = ? OR room_name = ?',
                array($reference, $reference)
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        $ids = array_values(array_unique(array_map('intval', $rows)));
        if (count($ids) !== 1) {
            throw new InvalidArgumentException(
                count($ids) === 0 ? 'Room was not found.' : 'Room name is ambiguous; use UUID or id.'
            );
        }

        return new Room($gDb, $ids[0]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function roomData(Room $room): array
    {
        return array(
            'id' => (int)$room->getValue('room_id'),
            'uuid' => (string)$room->getValue('room_uuid'),
            'name' => $room->getValue('room_name', 'database'),
            'description' => $room->getValue('room_description', 'database'),
            'capacity' => (int)$room->getValue('room_capacity'),
            'overhang' => (int)$room->getValue('room_overhang')
        );
    }

    private static function applyRoomOptions(Room $room, array $options): void
    {
        if (CliApplication::optionExists($options, 'description')) {
            $room->setValue('room_description', CliApplication::optionString($options, 'description'));
        }
        foreach (array('capacity' => 'room_capacity', 'overhang' => 'room_overhang') as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $value = CliApplication::optionString($options, $option);
                if ($value === '' || !ctype_digit($value)) {
                    throw new InvalidArgumentException('--' . $option . ' must be a non-negative integer.');
                }
                $room->setValue($column, (int)$value);
            }
        }
    }

    private static function resolveAnnouncement(string $reference): Announcement
    {
        global $gDb;
        $id = CliApplication::resolveId(
            TBL_ANNOUNCEMENTS,
            'ann_id',
            'ann_uuid',
            $reference,
            'announcement'
        );
        return new Announcement($gDb, $id);
    }

    private static function applyAnnouncementOptions(Announcement $announcement, array $options, bool $new): void
    {
        if (CliApplication::optionExists($options, 'headline')) {
            $announcement->setValue('ann_headline', self::requireTextOption($options, 'headline'));
        } elseif ($new) {
            throw new InvalidArgumentException('--headline is required.');
        }

        if (CliApplication::optionExists($options, 'category')) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'ANN');
            if (!$category->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $announcement->setValue('ann_cat_id', (int)$category->getValue('cat_id'));
        } elseif ($new) {
            throw new InvalidArgumentException('--category is required.');
        }

        $hasDescription = CliApplication::optionExists($options, 'description');
        $hasFile = CliApplication::optionExists($options, 'description-file');
        if ($hasDescription && $hasFile) {
            throw new InvalidArgumentException('Use either --description or --description-file, not both.');
        }
        if ($hasDescription) {
            $announcement->setValue('ann_description', CliApplication::optionString($options, 'description'));
        } elseif ($hasFile) {
            $file = CliApplication::optionString($options, 'description-file');
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new InvalidArgumentException('Could not read description file "' . $file . '".');
            }
            $announcement->setValue('ann_description', $content);
        }
    }

    private static function resolveTopic(string $reference): Topic
    {
        global $gDb;
        $id = CliApplication::resolveId(TBL_FORUM_TOPICS, 'fot_id', 'fot_uuid', $reference, 'forum topic');
        return new Topic($gDb, $id);
    }

    private static function resolvePost(string $reference): Post
    {
        global $gDb;
        $id = CliApplication::resolveId(TBL_FORUM_POSTS, 'fop_id', 'fop_uuid', $reference, 'forum post');
        return new Post($gDb, $id);
    }

    private static function resolveLink(string $reference): Weblink
    {
        global $gDb;
        $id = CliApplication::resolveId(TBL_LINKS, 'lnk_id', 'lnk_uuid', $reference, 'web link');
        return new Weblink($gDb, $id);
    }

    private static function applyLinkOptions(Weblink $link, array $options, bool $new): void
    {
        foreach (array(
            'name' => 'lnk_name',
            'url' => 'lnk_url',
            'description' => 'lnk_description'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $link->setValue($column, CliApplication::optionString($options, $option));
            } elseif ($new && in_array($option, array('name', 'url'), true)) {
                throw new InvalidArgumentException('--' . $option . ' is required.');
            }
        }

        if (CliApplication::optionExists($options, 'category')) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'LNK');
            if (!$category->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $link->setValue('lnk_cat_id', (int)$category->getValue('cat_id'));
        } elseif ($new) {
            throw new InvalidArgumentException('--category is required.');
        }
    }

    private static function resolveMessage(string $reference): Message
    {
        global $gDb;
        $id = CliApplication::resolveId(TBL_MESSAGES, 'msg_id', 'msg_uuid', $reference, 'message');
        return new Message($gDb, $id);
    }

    /**
     * @return array<string,mixed>
     */
    private static function messageData(Message $message): array
    {
        return array(
            'id' => (int)$message->getValue('msg_id'),
            'uuid' => (string)$message->getValue('msg_uuid'),
            'type' => (string)$message->getValue('msg_type'),
            'subject' => (string)$message->getValue('msg_subject', 'database'),
            'sender_id' => (int)$message->getValue('msg_usr_id_sender'),
            'timestamp' => (string)$message->getValue('msg_timestamp', 'Y-m-d H:i:s'),
            'read_state' => (int)$message->getValue('msg_read'),
            'recipients' => $message->getRecipientsNamesString(),
            'content' => $message->getContent('database'),
            'attachments' => $message->getAttachmentsInformation()
        );
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,string>
     */
    private static function buildMessageRecipients(array $options, string $type): array
    {
        $recipients = array();

        foreach (CliApplication::optionValues($options, 'user') as $reference) {
            $user = CliApplication::resolveUser($reference);
            $recipients[] = (string)$user->getValue('usr_uuid');
        }

        $groupReferences = CliApplication::optionValues($options, 'group');
        if ($type === 'pm' && count($groupReferences) > 0) {
            throw new InvalidArgumentException('Private messages cannot be sent to groups.');
        }

        foreach ($groupReferences as $reference) {
            $role = self::resolveGroup($reference);
            $recipients[] = 'groupID: ' . $role->getValue('rol_uuid');
        }

        if (count($recipients) === 0) {
            throw new InvalidArgumentException('At least one --user or --group recipient is required.');
        }

        return array_values(array_unique($recipients));
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,array{path:string,name:string,type:string}>
     */
    private static function buildMessageAttachments(array $options): array
    {
        $attachments = array();

        foreach (CliApplication::optionValues($options, 'attachment') as $path) {
            if (!is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('Attachment "' . $path . '" does not exist or is not readable.');
            }

            $type = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detectedType = mime_content_type($path);
                if (is_string($detectedType) && $detectedType !== '') {
                    $type = $detectedType;
                }
            }

            $attachments[] = array(
                'path' => $path,
                'name' => basename($path),
                'type' => $type
            );
        }

        return $attachments;
    }

    private static function assertMessageAccess(Message $message): void
    {
        global $gCurrentUserId, $gDb;

        if ((int)$message->getValue('msg_usr_id_sender') === $gCurrentUserId) {
            return;
        }

        $recipient = (int)$gDb->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_MESSAGES_RECIPIENTS . '
              WHERE msr_msg_id = ?
                AND msr_usr_id = ?',
            array((int)$message->getValue('msg_id'), $gCurrentUserId)
        )->fetchColumn();
        if ($recipient === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    private static function resolveFolder(string $reference): Folder
    {
        global $gDb;
        $folder = new Folder($gDb);
        $folder->getFolderForDownload($reference);
        return $folder;
    }

    private static function resolveDocumentFile(string $reference): DocumentFile
    {
        global $gDb;

        $file = new DocumentFile($gDb);
        if (ctype_digit($reference)) {
            $uuid = (string)$gDb->queryPrepared(
                'SELECT fil_uuid FROM ' . TBL_FILES . ' WHERE fil_id = ?',
                array((int)$reference)
            )->fetchColumn();
            if ($uuid === '') {
                throw new InvalidArgumentException('Document file was not found.');
            }
        } else {
            $uuid = $reference;
        }
        $file->getFileForDownload($uuid);
        return $file;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function collectFolderContents(Folder $folder, array &$rows, bool $recursive): void
    {
        global $gDb;
        foreach ($folder->getSubfoldersWithProperties() as $subfolderData) {
            $rows[] = array(
                'type' => 'folder',
                'id' => (int)$subfolderData['fol_id'],
                'uuid' => $subfolderData['fol_uuid'],
                'name' => $subfolderData['fol_name'],
                'path' => $subfolderData['fol_path'],
                'public' => (bool)$subfolderData['fol_public'],
                'locked' => (bool)$subfolderData['fol_locked'],
                'exists' => (bool)$subfolderData['fol_exists']
            );
            if ($recursive) {
                $subfolder = new Folder($gDb, (int)$subfolderData['fol_id']);
                if ($subfolder->hasViewRight()) {
                    self::collectFolderContents($subfolder, $rows, true);
                }
            }
        }

        foreach ($folder->getFilesWithProperties() as $fileData) {
            $rows[] = array(
                'type' => 'file',
                'id' => (int)$fileData['fil_id'],
                'uuid' => $fileData['fil_uuid'],
                'name' => $fileData['fil_name'],
                'size' => $fileData['fil_size'],
                'locked' => (bool)$fileData['fil_locked'],
                'exists' => (bool)$fileData['fil_exists']
            );
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function getUnregisteredEntries(Folder $folder): array
    {
        /*
         * Folder::addAdditionalToFolderContents() reports the physical entries that have no
         * database record under the separate keys additionalFolders/additionalFiles; the registered
         * entries stay in folders/files. DocumentsService::findUnregisteredFoldersFiles() applies
         * the same rule, but formats the file size for the web UI, so the raw size is collected
         * here to keep the CLI output machine-readable.
         */
        $contents = $folder->addAdditionalToFolderContents(array(
            'folders' => $folder->getSubfoldersWithProperties(),
            'files' => $folder->getFilesWithProperties()
        ));

        $path = $folder->getFolderPath();
        $rows = array();

        foreach ($contents['additionalFolders'] ?? array() as $entry) {
            $rows[] = array(
                'type' => 'folder',
                'name' => (string)($entry['fol_name'] ?? ''),
                'size' => '',
                'path' => $path
            );
        }

        foreach ($contents['additionalFiles'] ?? array() as $entry) {
            $rows[] = array(
                'type' => 'file',
                'name' => (string)($entry['fil_name'] ?? ''),
                'size' => (int)($entry['fil_size'] ?? 0),
                'path' => $path
            );
        }

        return $rows;
    }

    private static function registerUnregisteredRecursive(Folder $folder, bool $recursive): void
    {
        global $gDb;
        foreach (self::getUnregisteredEntries($folder) as $entry) {
            $name = (string)$entry['name'];
            if ($name === '') {
                continue;
            }
            $folder->addFolderOrFileToDatabase($name);
        }

        if ($recursive) {
            foreach ($folder->getSubfoldersWithProperties() as $subfolderData) {
                $subfolder = new Folder($gDb, (int)$subfolderData['fol_id']);
                if ($subfolder->hasUploadRight()) {
                    self::registerUnregisteredRecursive($subfolder, true);
                }
            }
        }
    }

    /**
     * @return array<int,int>
     */
    private static function resolveRoleIds(array $references): array
    {
        $ids = array();
        foreach ($references as $reference) {
            $ids[] = (int)self::resolveGroup((string)$reference)->getValue('rol_id');
        }
        return array_values(array_unique($ids));
    }

    private static function resolveAlbum(string $reference): Album
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT pho_id FROM ' . TBL_PHOTOS . ' WHERE pho_id = ? AND pho_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT pho_id FROM ' . TBL_PHOTOS . ' WHERE pho_uuid = ? AND pho_org_id = ?',
                array($reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if (count($rows) !== 1) {
            throw new InvalidArgumentException('Photo album was not found.');
        }
        return new Album($gDb, (int)$rows[0]);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function photoAlbumFormValues(array $options): array
    {
        $values = array();

        $mapping = array(
            'name' => 'pho_name',
            'begin' => 'pho_begin',
            'end' => 'pho_end',
            'photographers' => 'pho_photographers',
            'description' => 'pho_description'
        );

        foreach ($mapping as $option => $field) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$field] = CliApplication::optionString($options, $option);
            }
        }

        if (CliApplication::optionExists($options, 'locked')) {
            $values['pho_locked'] = CliApplication::optionBool($options, 'locked') ? 1 : 0;
        }

        return $values;
    }

    /**
     * @return array<string,mixed>
     */
    private static function albumData(Album $album): array
    {
        return array(
            'id' => (int)$album->getValue('pho_id'),
            'uuid' => (string)$album->getValue('pho_uuid'),
            'parent_id' => (int)$album->getValue('pho_pho_id_parent'),
            'name' => $album->getValue('pho_name', 'database'),
            'begin' => $album->getValue('pho_begin', 'database'),
            'end' => $album->getValue('pho_end', 'database'),
            'photographers' => $album->getValue('pho_photographers', 'database'),
            'description' => $album->getValue('pho_description', 'database'),
            'locked' => (bool)$album->getValue('pho_locked')
        );
    }

    private static function setAlbumLocked(array $arguments, array $options, bool $locked): int
    {
        $album = self::resolveAlbum(CliApplication::requireArgument($arguments, 0, 'album'));
        if (!$album->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $album->setValue('pho_locked', $locked);
        $album->save();
        CliApplication::writeSuccess('Photo album ' . ($locked ? 'locked.' : 'unlocked.'), $options);
        return 0;
    }

    /**
     * Id of the STATUS select option that marks an inventory item as retired, or 0 if the
     * installation has no such option.
     */
    private static function inventoryRetiredStatusId(ItemsData $items): int
    {
        global $gDb;

        $statusFieldId = (int)$items->getProperty('STATUS', 'inf_id');
        if ($statusFieldId === 0) {
            return 0;
        }

        foreach ((new InventorySelectOptions($gDb, $statusFieldId))->getAllOptions() as $option) {
            if (($option['value'] ?? '') === 'SYS_INVENTORY_FILTER_RETIRED_ITEMS') {
                return (int)$option['id'];
            }
        }

        return 0;
    }

    private static function resolveItemUuid(string $reference): string
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $uuid = $gDb->queryPrepared(
                'SELECT ini_uuid
                   FROM ' . TBL_INVENTORY_ITEMS . '
                  WHERE ini_id = ? AND ini_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchColumn();
        } else {
            $uuid = $gDb->queryPrepared(
                'SELECT ini_uuid
                   FROM ' . TBL_INVENTORY_ITEMS . '
                  WHERE ini_uuid = ? AND ini_org_id = ?',
                array($reference, $gCurrentOrgId)
            )->fetchColumn();
        }
        if ($uuid === false || $uuid === '') {
            throw new InvalidArgumentException('Inventory item was not found.');
        }
        return (string)$uuid;
    }

    private static function resolveItemData(string $reference): ItemsData
    {
        global $gDb, $gCurrentOrgId;

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $items->readItemData(self::resolveItemUuid($reference));
        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private static function itemData(ItemsData $itemData): array
    {
        global $gDb;
        $data = array(
            'id' => $itemData->getItemId(),
            'uuid' => (string)$gDb->queryPrepared(
                'SELECT ini_uuid FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?',
                array($itemData->getItemId())
            )->fetchColumn(),
            'retired' => $itemData->isRetired(),
            'borrowed' => $itemData->isBorrowed(),
            'fields' => array()
        );
        foreach ($itemData->getItemFields() as $fieldName => $field) {
            $data['fields'][$fieldName] = $itemData->getValue((string)$fieldName, 'database');
        }
        return $data;
    }

    private static function resolveInventoryField(string $reference): ItemField
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT inf_id
                   FROM ' . TBL_INVENTORY_FIELDS . '
                  WHERE inf_id = ?
                    AND (inf_org_id = ? OR inf_org_id IS NULL)',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT inf_id
                   FROM ' . TBL_INVENTORY_FIELDS . '
                  WHERE (inf_org_id = ? OR inf_org_id IS NULL)
                    AND (inf_uuid = ? OR UPPER(inf_name_intern) = UPPER(?))',
                array($gCurrentOrgId, $reference, $reference)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        $ids = array_values(array_unique(array_map('intval', $rows)));
        if (count($ids) !== 1) {
            throw new InvalidArgumentException('Inventory field was not found.');
        }
        return new ItemField($gDb, $ids[0]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function inventoryFieldData(ItemField $field): array
    {
        $data = array();
        foreach (array(
            'inf_id', 'inf_uuid', 'inf_org_id', 'inf_name_intern', 'inf_name', 'inf_type',
            'inf_required_input', 'inf_system', 'inf_sequence', 'inf_description', 'inf_inf_uuid_connected'
        ) as $column) {
            $data[$column] = $field->getValue($column, 'database');
        }
        return $data;
    }

    private static function resolveProfileField(string $reference): ProfileField
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT usf_id
                   FROM ' . TBL_USER_FIELDS . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = usf_cat_id
                  WHERE usf_id = ?
                    AND (cat_org_id = ? OR cat_org_id IS NULL)',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT usf_id
                   FROM ' . TBL_USER_FIELDS . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = usf_cat_id
                  WHERE (usf_uuid = ? OR UPPER(usf_name_intern) = UPPER(?))
                    AND (cat_org_id = ? OR cat_org_id IS NULL)',
                array($reference, $reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        $ids = array_values(array_unique(array_map('intval', $rows)));
        if (count($ids) !== 1) {
            throw new InvalidArgumentException('Profile field was not found.');
        }
        return new ProfileField($gDb, $ids[0]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function profileFieldData(ProfileField $field): array
    {
        $data = array();
        foreach (array(
            'usf_id', 'usf_uuid', 'usf_cat_id', 'usf_name_intern', 'usf_name', 'usf_type',
            'usf_required_input', 'usf_hidden', 'usf_disabled', 'usf_registration',
            'usf_default_value', 'usf_regex', 'usf_icon', 'usf_url', 'usf_description',
            'usf_system', 'usf_sequence'
        ) as $column) {
            $data[$column] = $field->getValue($column, 'database');
        }
        return $data;
    }

    private static function moveSelectOption(object $selectOptions, int $optionId, string $direction): void
    {
        if (!$selectOptions->readDataById($optionId)) {
            throw new InvalidArgumentException('Select option was not found.');
        }

        $allOptions = array_values($selectOptions->getAllOptions(true));
        usort(
            $allOptions,
            static fn (array $left, array $right): int =>
                ((int)$left['sequence'] <=> (int)$right['sequence']) ?: ((int)$left['id'] <=> (int)$right['id'])
        );

        $currentIndex = null;
        foreach ($allOptions as $index => $option) {
            if ((int)$option['id'] === $optionId) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            throw new InvalidArgumentException('Select option was not found in its field option sequence.');
        }

        $otherIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if (!isset($allOptions[$otherIndex])) {
            return;
        }

        [$allOptions[$currentIndex], $allOptions[$otherIndex]] =
            [$allOptions[$otherIndex], $allOptions[$currentIndex]];

        $sequence = array();
        foreach ($allOptions as $position => $option) {
            $sequence[(int)$option['id']] = $position;
        }

        $selectOptions->setSequence($sequence);
    }

    private static function resolvePlugin(string $reference): PluginAbstract
    {
        $manager = new PluginManager();
        $plugin = $manager->getPluginByName($reference);
        if ($plugin === null) {
            $plugin = $manager->getPluginByComponentName(strtoupper($reference));
        }
        if ($plugin === null && ctype_digit($reference)) {
            $plugin = $manager->getPluginById((int)$reference);
        }
        if ($plugin === null) {
            throw new InvalidArgumentException('Plugin "' . $reference . '" was not found or has no current PluginAbstract interface.');
        }
        return $plugin;
    }

    /**
     * @return array{0:SAMLClient|OIDCClient,1:string}
     */
    private static function resolveSsoClient(string $reference, string $type = ''): array
    {
        global $gDb;

        if ($type !== '' && !in_array($type, array('saml', 'oidc'), true)) {
            throw new InvalidArgumentException('--type must be saml or oidc.');
        }

        $matches = array();

        if ($type === '' || $type === 'saml') {
            $params = array();
            if (ctype_digit($reference)) {
                $where = 'smc_id = ?';
                $params[] = (int)$reference;
            } else {
                $where = '(smc_uuid = ? OR smc_client_id = ?)';
                array_push($params, $reference, $reference);
            }
            $ids = $gDb->queryPrepared(
                'SELECT smc_id FROM ' . TBL_SAML_CLIENTS . ' WHERE ' . $where,
                $params
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $matches[] = array(new SAMLClient($gDb, (int)$id), 'saml');
            }
        }

        if ($type === '' || $type === 'oidc') {
            if (ctype_digit($reference)) {
                $ids = $gDb->queryPrepared(
                    'SELECT ocl_id FROM ' . TBL_OIDC_CLIENTS . ' WHERE ocl_id = ?',
                    array((int)$reference)
                )->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $ids = $gDb->queryPrepared(
                    'SELECT ocl_id FROM ' . TBL_OIDC_CLIENTS . ' WHERE ocl_uuid = ? OR ocl_client_id = ?',
                    array($reference, $reference)
                )->fetchAll(PDO::FETCH_COLUMN);
            }
            foreach ($ids as $id) {
                $matches[] = array(new OIDCClient($gDb, (int)$id), 'oidc');
            }
        }

        if (count($matches) !== 1) {
            throw new InvalidArgumentException(
                count($matches) === 0
                    ? 'SSO client was not found.'
                    : 'SSO client reference is ambiguous; specify --type=saml|oidc.'
            );
        }
        return $matches[0];
    }

    /**
     * @return array<string,mixed>
     */
    private static function ssoClientData(SAMLClient|OIDCClient $client, string $type): array
    {
        $prefix = $type === 'saml' ? 'smc' : 'ocl';
        $data = array(
            'type' => $type,
            'id' => (int)$client->getValue($prefix . '_id'),
            'uuid' => (string)$client->getValue($prefix . '_uuid'),
            'client_id' => $client->getIdentifier(),
            'name' => $client->getName(),
            'enabled' => $client->isEnabled(),
            'userid_field' => $client->getUserIdField(),
            'field_mapping' => $client->getFieldMapping(),
            'field_mapping_catchall' => $client->getFieldMappingCatchall(),
            'role_mapping' => $client->getRoleMapping(),
            'role_mapping_catchall' => $client->getRoleMappingCatchall(),
            'access_role_ids' => $client->getAccessRolesIds(),
            'access_roles' => $client->getAccessRolesNames()
        );

        if ($type === 'saml') {
            $data += array(
                'metadata_url' => $client->getValue('smc_metadata_url', 'database'),
                'acs_url' => $client->getValue('smc_acs_url', 'database'),
                'slo_url' => $client->getValue('smc_slo_url', 'database'),
                'allowed_clock_skew' => $client->getValue('smc_allowed_clock_skew'),
                'assertion_lifetime' => $client->getValue('smc_assertion_lifetime'),
                'sign_assertions' => (bool)$client->getValue('smc_sign_assertions'),
                'encrypt_assertions' => (bool)$client->getValue('smc_encrypt_assertions'),
                'require_auth_signed' => (bool)$client->getValue('smc_require_auth_signed'),
                'validate_signatures' => (bool)$client->getValue('smc_validate_signatures')
            );
        } else {
            $data += array(
                'redirect_uri' => $client->getValue('ocl_redirect_uri', 'database'),
                'grant_types' => $client->getValue('ocl_grant_types', 'database'),
                'scope' => $client->getValue('ocl_scope', 'database')
            );
        }

        return $data;
    }

    private static function setSsoEnabled(array $arguments, array $options, bool $enabled): int
    {
        [$client, $type] = self::resolveSsoClient(
            CliApplication::requireArgument($arguments, 0, 'client'),
            CliApplication::optionString($options, 'type')
        );
        $client->enable($enabled);
        $client->save();
        CliApplication::writeSuccess(
            strtoupper($type) . ' client ' . ($enabled ? 'enabled.' : 'disabled.'),
            $options
        );
        return 0;
    }

    private static function resolveSsoKey(string $reference): Key
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $ids = $gDb->queryPrepared(
                'SELECT key_id FROM ' . TBL_SSO_KEYS . ' WHERE key_id = ? AND key_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $ids = $gDb->queryPrepared(
                'SELECT key_id FROM ' . TBL_SSO_KEYS . ' WHERE key_uuid = ? AND key_org_id = ?',
                array($reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if (count($ids) !== 1) {
            throw new InvalidArgumentException('SSO key was not found.');
        }
        return new Key($gDb, (int)$ids[0]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function ssoKeyData(Key $key): array
    {
        return array(
            'id' => (int)$key->getValue('key_id'),
            'uuid' => (string)$key->getValue('key_uuid'),
            'organization_id' => (int)$key->getValue('key_org_id'),
            'name' => $key->getValue('key_name', 'database'),
            'algorithm' => $key->getValue('key_algorithm', 'database'),
            'expires_at' => $key->getValue('key_expires_at', 'database'),
            'active' => (bool)$key->getValue('key_is_active'),
            'has_public_key' => (string)$key->getValue('key_public', 'database') !== '',
            'has_certificate' => (string)$key->getValue('key_certificate', 'database') !== ''
        );
    }


    /**
     * Resolve a human-friendly import column reference to the zero-based PhpSpreadsheet column index.
     *
     * Numeric references are one-based for CLI users; string references match a first-row heading.
     *
     * @param array<int,mixed> $headers
     */
    private static function resolveInventoryImportColumn(
        string $reference,
        array $headers,
        bool $firstRow
    ): int {
        $reference = trim($reference);

        if ($reference !== '' && ctype_digit($reference)) {
            $column = (int)$reference;
            if ($column <= 0) {
                throw new InvalidArgumentException('Import column numbers start at 1.');
            }
            return $column - 1;
        }

        if (!$firstRow) {
            throw new InvalidArgumentException(
                'Import columns must be specified by number when --first-row=false.'
            );
        }

        $matches = array();
        foreach ($headers as $columnIndex => $header) {
            if (trim((string)$header) === $reference) {
                $matches[] = (int)$columnIndex;
            }
        }

        if (count($matches) !== 1) {
            throw new InvalidArgumentException(
                count($matches) === 0
                    ? 'Import column "' . $reference . '" was not found in the first row.'
                    : 'Import column heading "' . $reference . '" is ambiguous; use a column number.'
            );
        }

        return $matches[0];
    }

    private static function inventoryFormValues(array $assignments): array
    {
        $values = array();

        foreach ($assignments as $assignment) {
            [$fieldReference, $value] = self::splitAssignment($assignment, '--field');
            $field = self::resolveInventoryField($fieldReference);
            $fieldName = (string)$field->getValue('inf_name_intern');

            if ($fieldName === 'CATEGORY' && $value !== '') {
                $value = (string)self::resolveCategory($value, 'IVT')->getValue('cat_uuid');
            }

            if ((string)$field->getValue('inf_type') === 'DROPDOWN_MULTISELECT') {
                $value = $value === '' ? array() : array_map('trim', explode(',', $value));
            }

            $values['INF-' . $fieldName] = $value;
        }

        return $values;
    }

    /**
     * Validate the required inventory fields that would normally be enforced by FormPresenter.
     *
     * @param array<string,mixed> $formValues
     */
    private static function validateRequiredInventoryFields(array $formValues): void
    {
        global $gDb, $gCurrentOrgId, $gL10n;

        $itemsData = new ItemsData($gDb, $gCurrentOrgId);
        foreach ($itemsData->getItemFields() as $itemField) {
            if ((int)$itemField->getValue('inf_required_input') !== 1) {
                continue;
            }

            $postKey = 'INF-' . $itemField->getValue('inf_name_intern');
            if (!array_key_exists($postKey, $formValues)
                || (is_array($formValues[$postKey]) && count($formValues[$postKey]) === 0)
                || (is_string($formValues[$postKey]) && strlen($formValues[$postKey]) === 0)) {
                throw new Exception(
                    $gL10n->get('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name')))
                );
            }
        }
    }

    private static function inventoryFieldFormValues(array $options): array
    {
        $values = array();

        foreach (array(
            'name' => 'inf_name',
            'type' => 'inf_type',
            'description' => 'inf_description'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$column] = CliApplication::optionString($options, $option);
            }
        }

        if (CliApplication::optionExists($options, 'required')) {
            $required = (int)CliApplication::optionString($options, 'required');
            if ($required < 0 || $required > 1) {
                throw new InvalidArgumentException('--required expects 0 or 1.');
            }
            $values['inf_required_input'] = $required;
        }

        if (CliApplication::optionExists($options, 'connected-field')) {
            $connectedReference = CliApplication::optionString($options, 'connected-field');
            $values['inf_inf_uuid_connected'] = $connectedReference === ''
                ? ''
                : (string)self::resolveInventoryField($connectedReference)->getValue('inf_uuid');
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function profileFieldFormValues(array $options): array
    {
        $values = array();

        foreach (array(
            'name' => 'usf_name',
            'type' => 'usf_type',
            'default' => 'usf_default_value',
            'regex' => 'usf_regex',
            'icon' => 'usf_icon',
            'url' => 'usf_url',
            'description' => 'usf_description'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$column] = CliApplication::optionString($options, $option);
            }
        }

        if (CliApplication::optionExists($options, 'category')) {
            $values['usf_cat_id'] = (int)self::resolveCategory(
                CliApplication::optionString($options, 'category'),
                'USF'
            )->getValue('cat_id');
        }

        if (CliApplication::optionExists($options, 'required')) {
            $required = (int)CliApplication::optionString($options, 'required');
            if ($required < 0 || $required > 3) {
                throw new InvalidArgumentException('--required expects a value from 0 to 3.');
            }
            $values['usf_required_input'] = $required;
        }

        foreach (array(
            'hidden' => 'usf_hidden',
            'disabled' => 'usf_disabled',
            'registration' => 'usf_registration'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$column] = CliApplication::optionBool($options, $option, false) ?? false;
            }
        }

        return $values;
    }

    /**
     * CategoryReport::getConfigArray() HTML-encodes configuration names for the web UI.
     * Decode them before passing the array back to saveConfigArray(), just as a browser form
     * submission would do.
     *
     * @param array<int,array<string,mixed>> $config
     * @return array<int,array<string,mixed>>
     */
    private static function categoryReportConfigForSave(array $config): array
    {
        foreach ($config as &$values) {
            $values['name'] = html_entity_decode(
                (string)($values['name'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }
        unset($values);

        return $config;
    }

    private static function categoryReportConfigIndex(array $config, string $reference): int
    {
        $matches = array();

        foreach ($config as $index => $values) {
            $name = html_entity_decode((string)($values['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ((ctype_digit($reference) && (int)$values['id'] === (int)$reference)
                || (!ctype_digit($reference) && $name === $reference)) {
                $matches[] = $index;
            }
        }

        if (count($matches) !== 1) {
            throw new InvalidArgumentException(
                count($matches) === 0
                    ? 'Unknown category-report configuration.'
                    : 'Category-report configuration name is ambiguous; use the numeric id.'
            );
        }

        return (int)$matches[0];
    }

    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function categoryReportFormValues(array $options, array $values): array
    {
        if (CliApplication::optionExists($options, 'name')) {
            $values['name'] = self::requireTextOption($options, 'name');
        }

        if (CliApplication::optionExists($options, 'role')) {
            $ids = array();
            foreach (CliApplication::optionValues($options, 'role') as $reference) {
                $ids[] = (int)self::resolveGroup($reference)->getValue('rol_id');
            }
            $values['selection_role'] = implode(',', $ids);
        }

        if (CliApplication::optionExists($options, 'category')) {
            $ids = array();
            foreach (CliApplication::optionValues($options, 'category') as $reference) {
                $ids[] = (int)self::resolveCategory($reference, 'ROL')->getValue('cat_id');
            }
            $values['selection_cat'] = implode(',', $ids);
        }

        if (CliApplication::optionExists($options, 'column')) {
            $columns = CliApplication::optionValues($options, 'column');
            $values['col_fields'] = implode(',', $columns);

            $conditions = CliApplication::optionValues($options, 'condition');
            $conditions = array_slice($conditions, 0, count($columns));
            while (count($conditions) < count($columns)) {
                $conditions[] = '';
            }
            $conditions = array_map(
                static function (string $condition): string {
                    $condition = str_replace(array('<', '>'), array('{', '}'), $condition);
                    return trim(str_replace(array("\r", "\n"), ' ', $condition));
                },
                $conditions
            );
            $values['col_conditions'] = implode(',', $conditions);
        } elseif (CliApplication::optionExists($options, 'condition')) {
            throw new InvalidArgumentException('--condition can only be used together with --column.');
        }

        if (CliApplication::optionExists($options, 'number-column')) {
            $values['number_col'] = (int)(CliApplication::optionBool($options, 'number-column', false) ?? false);
        }
        if (CliApplication::optionExists($options, 'default')) {
            $values['default_conf'] = CliApplication::optionBool($options, 'default', false) ?? false;
        }

        return $values;
    }

    /**
     * @param SAMLClient|OIDCClient|null $client
     * @param array<string,mixed> $options
     * @return array{0:array<string,mixed>,1:array<int,int>}
     */
    private static function ssoFormValues(
        string $type,
        SAMLClient|OIDCClient|null $client,
        array $options
    ): array {
        $prefix = $type === 'saml' ? 'smc' : 'ocl';
        $values = array();

        if ($client !== null) {
            $fieldMapping = $client->getFieldMapping();
            $roleMapping = $client->getRoleMapping();

            $values['fieldsmap_sso'] = array_keys($fieldMapping);
            $values['fieldsmap_Admidio'] = array_values($fieldMapping);
            $values['rolesmap_sso'] = array_keys($roleMapping);
            $values['rolesmap_Admidio'] = array_values($roleMapping);
            $values['sso_fields_no_other'] = $client->getFieldMappingCatchall();
            $values['sso_roles_all_other'] = $client->getRoleMappingCatchall();
            $accessRoles = array_map('intval', $client->getAccessRolesIds());
        } else {
            $values['fieldsmap_sso'] = array();
            $values['fieldsmap_Admidio'] = array();
            $values['rolesmap_sso'] = array();
            $values['rolesmap_Admidio'] = array();
            $values['sso_fields_no_other'] = false;
            $values['sso_roles_all_other'] = false;
            $accessRoles = array();
        }

        $fieldOptions = $type === 'saml'
            ? array(
                'name' => 'smc_client_name',
                'client-id' => 'smc_client_id',
                'metadata-url' => 'smc_metadata_url',
                'acs-url' => 'smc_acs_url',
                'slo-url' => 'smc_slo_url',
                'assertion-lifetime' => 'smc_assertion_lifetime',
                'clock-skew' => 'smc_allowed_clock_skew',
                'userid-field' => 'smc_userid_field'
            )
            : array(
                'name' => 'ocl_client_name',
                'client-id' => 'ocl_client_id',
                'redirect-uri' => 'ocl_redirect_uri',
                'userid-field' => 'ocl_userid_field'
            );

        foreach ($fieldOptions as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$column] = CliApplication::optionString($options, $option);
            } elseif ($client !== null) {
                $values[$column] = $client->getValue($column, 'database');
            }
        }

        $boolOptions = $type === 'saml'
            ? array(
                'enabled' => 'smc_enabled',
                'require-auth-signed' => 'smc_require_auth_signed',
                'sign-assertions' => 'smc_sign_assertions',
                'encrypt-assertions' => 'smc_encrypt_assertions',
                'validate-signatures' => 'smc_validate_signatures'
            )
            : array('enabled' => 'ocl_enabled');

        foreach ($boolOptions as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$column] = CliApplication::optionBool($options, $option, false) ?? false;
            } elseif ($client !== null) {
                $values[$column] = (bool)$client->getValue($column);
            } elseif ($option === 'enabled') {
                $values[$column] = true;
            }
        }

        if ($type === 'saml') {
            if ($client === null) {
                $values += array(
                    'smc_metadata_url' => '',
                    'smc_slo_url' => '',
                    'smc_x509_certificate' => '',
                    'smc_require_auth_signed' => false,
                    'smc_sign_assertions' => false,
                    'smc_encrypt_assertions' => false,
                    'smc_validate_signatures' => false,
                    'smc_assertion_lifetime' => 600,
                    'smc_allowed_clock_skew' => 0,
                    'smc_userid_field' => 'usr_id'
                );
            }

            if (CliApplication::optionExists($options, 'certificate')) {
                $certificateFile = CliApplication::optionString($options, 'certificate');
                $certificate = @file_get_contents($certificateFile);
                if ($certificate === false) {
                    throw new RuntimeException('Could not read certificate file "' . $certificateFile . '".');
                }
                $values['smc_x509_certificate'] = $certificate;
            } elseif ($client !== null) {
                $values['smc_x509_certificate'] = $client->getValue('smc_x509_certificate', 'database');
            }
        } else {
            if ($client === null) {
                $values += array(
                    'ocl_redirect_uri' => '',
                    'ocl_userid_field' => 'usr_id',
                    'ocl_scope' => array()
                );
            }

            if (CliApplication::optionExists($options, 'scope')) {
                $values['ocl_scope'] = array_values(array_filter(
                    CliApplication::optionValues($options, 'scope'),
                    static fn (string $scope): bool => $scope !== 'openid'
                ));
            } elseif ($client !== null) {
                $scopes = preg_split(
                    '/[,;\s]+/',
                    trim((string)$client->getValue('ocl_scope', 'database'))
                ) ?: array();
                $values['ocl_scope'] = array_values(array_filter(
                    $scopes,
                    static fn (string $scope): bool => $scope !== '' && $scope !== 'openid'
                ));
            }

            if (CliApplication::optionExists($options, 'client-secret')
                || CliApplication::optionBool($options, 'client-secret-stdin', false)) {
                $secret = CliApplication::readSecret($options, 'client-secret', 'client-secret-stdin');
                if ($secret === '') {
                    throw new InvalidArgumentException('The OIDC client secret must not be empty.');
                }
                $values['new_ocl_client_secret'] = $secret;
            }
        }

        if (CliApplication::optionExists($options, 'field-map')) {
            $values['fieldsmap_sso'] = array();
            $values['fieldsmap_Admidio'] = array();
            foreach (CliApplication::optionValues($options, 'field-map') as $assignment) {
                [$admidioField, $ssoField] = self::splitAssignment($assignment);
                $values['fieldsmap_Admidio'][] = $admidioField;
                $values['fieldsmap_sso'][] = $ssoField;
            }
        }

        if (CliApplication::optionExists($options, 'field-map-other')) {
            $values['sso_fields_no_other'] = CliApplication::optionBool(
                $options,
                'field-map-other',
                false
            ) ?? false;
        }

        if (CliApplication::optionExists($options, 'role-map')) {
            $values['rolesmap_sso'] = array();
            $values['rolesmap_Admidio'] = array();
            foreach (CliApplication::optionValues($options, 'role-map') as $assignment) {
                [$roleReference, $ssoRole] = self::splitAssignment($assignment);
                $values['rolesmap_Admidio'][] = (int)self::resolveGroup($roleReference)->getValue('rol_id');
                $values['rolesmap_sso'][] = $ssoRole;
            }
        }

        if (CliApplication::optionExists($options, 'role-map-other')) {
            $values['sso_roles_all_other'] = CliApplication::optionBool(
                $options,
                'role-map-other',
                false
            ) ?? false;
        }

        if (CliApplication::optionExists($options, 'access-role')) {
            $accessRoles = self::resolveRoleIds(CliApplication::optionValues($options, 'access-role'));
        }

        return array($values, $accessRoles);
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function saveSsoKey(
        string $keyUUID,
        array $options,
        string $mode,
        ?Key $existingKey = null
    ): int {
        global $gCurrentOrganization, $gDb;

        $values = self::ssoKeyCertificateValues($existingKey);

        $values['key_name'] = CliApplication::optionExists($options, 'name')
            ? CliApplication::optionString($options, 'name')
            : ($existingKey === null ? '' : (string)$existingKey->getValue('key_name', 'database'));

        if ($values['key_name'] === '') {
            throw new InvalidArgumentException('--name is required.');
        }

        $values['key_algorithm'] = CliApplication::optionExists($options, 'algorithm')
            ? CliApplication::optionString($options, 'algorithm')
            : ($existingKey === null ? 'RSA' : (string)$existingKey->getValue('key_algorithm', 'database'));

        $values['key_is_active'] = CliApplication::optionExists($options, 'active')
            ? (CliApplication::optionBool($options, 'active', true) ?? true)
            : ($existingKey === null ? true : (bool)$existingKey->getValue('key_is_active'));

        foreach (array(
            'country' => 'cert_country',
            'state' => 'cert_state',
            'locality' => 'cert_locality',
            'organization-name' => 'cert_org',
            'organization-unit' => 'cert_orgunit',
            'common-name' => 'cert_common_name',
            'admin-email' => 'cert_admin_email',
            'expires' => 'key_expires_at'
        ) as $option => $field) {
            if (CliApplication::optionExists($options, $option)) {
                $values[$field] = CliApplication::optionString($options, $option);
            }
        }

        if (CliApplication::optionExists($options, 'expires')) {
            self::validateDate($values['key_expires_at'], '--expires');
        }

        if ($mode === 'key') {
            foreach (array(
                'cert_country' => '--country',
                'cert_state' => '--state',
                'cert_locality' => '--locality',
                'cert_orgunit' => '--organization-unit'
            ) as $field => $optionName) {
                if (trim((string)$values[$field]) === '') {
                    throw new InvalidArgumentException(
                        $optionName . ' is required when generating an SSO key certificate.'
                    );
                }
            }
        }

        $saved = (new KeyService($gDb))->saveData($keyUUID, $values, $mode);
        CliApplication::writeValue(self::ssoKeyData($saved), $options);
        return 0;
    }

    /**
     * @return array<string,mixed>
     */
    private static function ssoKeyCertificateValues(?Key $key): array
    {
        global $gCurrentOrganization;

        $expires = new \DateTime();
        $expires->modify('+2 years');

        $values = array(
            'cert_country' => '',
            'cert_state' => '',
            'cert_locality' => '',
            'cert_org' => (string)$gCurrentOrganization->getValue('org_longname'),
            'cert_orgunit' => '',
            'cert_common_name' => ADMIDIO_URL,
            'cert_admin_email' => (string)$gCurrentOrganization->getValue('org_email_administrator'),
            'key_expires_at' => $expires->format('Y-m-d')
        );

        if ($key === null) {
            return $values;
        }

        $certificate = (string)$key->getValue('key_certificate', 'database');
        if ($certificate !== '') {
            $parsed = openssl_x509_parse($certificate);
            if (is_array($parsed)) {
                $subject = $parsed['subject'] ?? array();
                $values['cert_country'] = (string)($subject['C'] ?? '');
                $values['cert_state'] = (string)($subject['ST'] ?? '');
                $values['cert_locality'] = (string)($subject['L'] ?? '');
                $values['cert_org'] = (string)($subject['O'] ?? $values['cert_org']);
                $values['cert_orgunit'] = (string)($subject['OU'] ?? '');
                $values['cert_common_name'] = (string)($subject['CN'] ?? $values['cert_common_name']);
                $values['cert_admin_email'] = (string)($subject['emailAddress'] ?? $values['cert_admin_email']);
            }
        }

        $currentExpires = (string)$key->getValue('key_expires_at', 'database');
        if ($currentExpires !== '') {
            $values['key_expires_at'] = substr($currentExpires, 0, 10);
        }

        return $values;
    }

    /**
     * Read an option that replaces a required text and refuse an empty value.
     *
     * An option that is not given leaves the stored value alone, but one that is given has to
     * carry a value: the column behind it is NOT NULL or its edit form marks it required, so an
     * empty value would leave a record that no list and no reference can name any more.
     *
     * @param array<string,mixed> $options
     */
    private static function requireTextOption(array $options, string $name): string
    {
        $value = CliApplication::optionString($options, $name);

        if (trim($value) === '') {
            throw new InvalidArgumentException('--' . $name . ' must not be empty.');
        }

        return $value;
    }

    /**
     * Read a text value from either an inline option or a file option.
     *
     * @param array<string,mixed> $options
     */
    private static function readTextOption(
        array $options,
        string $valueOption,
        string $fileOption,
        bool $required = false
    ): string {
        $hasValue = CliApplication::optionExists($options, $valueOption);
        $hasFile = CliApplication::optionExists($options, $fileOption);

        if ($hasValue && $hasFile) {
            throw new InvalidArgumentException(
                'Use either --' . $valueOption . ' or --' . $fileOption . ', not both.'
            );
        }

        if ($hasValue) {
            return CliApplication::optionString($options, $valueOption);
        }

        if ($hasFile) {
            $file = CliApplication::optionString($options, $fileOption);
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new InvalidArgumentException('Could not read file "' . $file . '".');
            }

            return $content;
        }

        if ($required) {
            throw new InvalidArgumentException(
                'Either --' . $valueOption . ' or --' . $fileOption . ' is required.'
            );
        }

        return '';
    }

    /**
     * @param array{filename:string,contentType:string,content:string} $export
     * @param array<string,mixed> $options
     */
    private static function writeExportContent(array $export, array $options, bool $secret = false): void
    {
        $target = CliApplication::resolveOutputPath($options, $export['filename']);

        if (file_put_contents($target, $export['content']) === false) {
            throw new RuntimeException('Could not write export file "' . $target . '".');
        }

        if ($secret) {
            CliApplication::protectExportedFile($target);
        }

        CliApplication::writeSuccess('Export written to ' . $target . '.', $options);
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function moveGeneratedFile(
        string $source,
        string $filename,
        array $options,
        bool $secret = false
    ): void {
        $target = CliApplication::resolveOutputPath($options, $filename);

        try {
            if (!@rename($source, $target) && !@copy($source, $target)) {
                throw new RuntimeException('Could not write export file "' . $target . '".');
            }
        } finally {
            // The generated file lives in adm_my_files/tmp and must not survive a failed export.
            if (is_file($source)) {
                @unlink($source);
            }
        }

        if ($secret) {
            CliApplication::protectExportedFile($target);
        }

        CliApplication::writeSuccess('Export written to ' . $target . '.', $options);
    }

    private static function changePermissions(string $mode, array $arguments, array $options): int
    {
        global $gDb;
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(
            CliApplication::requireArgument($arguments, 1, 'object-id'),
            'object-id'
        );
        $rights = new RolesRights($gDb, $right, $objectId);
        if ((int)$rights->getValue('ror_id') === 0) {
            throw new InvalidArgumentException('Unknown object-right type "' . $right . '".');
        }

        $roleIds = self::resolveRoleIds(CliApplication::optionValues($options, 'role'));
        if ($mode === 'set') {
            $rights->saveRoles($roleIds);
        } elseif ($mode === 'add') {
            $rights->addRoles($roleIds);
        } elseif ($mode === 'remove') {
            $rights->removeRoles($roleIds);
        } else {
            throw new InvalidArgumentException('Unknown permission update mode.');
        }

        self::reloadAllSessions();
        CliApplication::writeValue(array(
            'right_type' => $right,
            'object_id' => $objectId,
            'role_ids' => $rights->getRolesIds(),
            'roles' => $rights->getRolesNames()
        ), $options);
        return 0;
    }
}
