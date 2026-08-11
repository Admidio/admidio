<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Categories\Entity\Category;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Components\Entity\Component;
use Admidio\Documents\Entity\File as DocumentFile;
use Admidio\Documents\Entity\Folder;
use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\ValueObject\Participants;
use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\DatabaseDump;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Htaccess;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Infrastructure\Service\RegistrationService;
use Admidio\Infrastructure\Utils\Maintenance;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\ItemBorrowData;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions as InventorySelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Inventory\Service\ItemFieldService;
use Admidio\Inventory\Service\ItemService;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Messages\Entity\Message;
use Admidio\Organizations\Entity\Organization;
use Admidio\Photos\Entity\Album;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\Entity\SelectOptions as ProfileSelectOptions;
use Admidio\Requirements\Entity\Provider;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Session\Entity\AutoLogin;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\KeyService;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Weblinks\Entity\Weblink;
use InvalidArgumentException;
use PDO;
use RuntimeException;

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
        self::registerEventTasks();
        self::registerRoomTasks();
        self::registerForumTasks();
        self::registerLinkTasks();
        self::registerMessageTasks();
        self::registerDocumentTasks();
        self::registerPhotoTasks();
        self::registerInventoryTasks();
        self::registerProfileFieldTasks();
        self::registerCategoryReportTasks();
        self::registerChangelogTasks();
        self::registerPluginTasks();
        self::registerRequirementsTasks();
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
        ?string $unavailableReason = null
    ): void {
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
            $unavailableReason
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
        if ($name === 'format'
            && !in_array('record', $values, true)
            && (
                in_array('table', $values, true)
                || (
                    count($values) === 2
                    && in_array('text', $values, true)
                    && in_array('json', $values, true)
                )
            )) {
            $position = array_search(
                in_array('table', $values, true) ? 'table' : 'text',
                $values,
                true
            );
            array_splice($values, $position + 1, 0, array('record'));
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
            'status',
            'status',
            'Show installation, organization and filesystem/database update status.',
            'status [--format=text|json]',
            null,
            false,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
    }

    private static function registerSystemTasks(): void
    {
        $installReason = 'current master implements installation as the procedural web flow under install/install_steps/; '
            . 'src/InstallationUpdate/Service/Installation.php has no complete headless installation operation.';
        self::task('install:check', 'unavailable', 'Validate prerequisites for a new installation.',
            'install:check', null, false, array(), array(), array(), $installReason);
        self::task('install:run', 'unavailable', 'Install a new Admidio database and organization.',
            'install:run', null, false, array(), array(), array(), $installReason);

        self::task(
            'update:check',
            'updateCheck',
            'Check the public Admidio release information used by the preferences update check.',
            'update:check [--format=text|json]',
            'PREFERENCES',
            true,
            array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json')))
        );
        self::task(
            'upgrade',
            'unavailable',
            'Execute the native core database updater for the checked-out Admidio version.',
            'upgrade [--yes]',
            'CORE',
            true,
            array(),
            array(self::opt('yes', 'Confirm the database upgrade.', '', false, false, true)),
            array(),
            'src/InstallationUpdate/Service/Update.php is still coupled to the web login/session update flow; '
                . 'the command is reserved for the separate headless-updater PR.'
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
            'Show whether adm_my_files is protected by an .htaccess file.',
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
            'unavailable',
            'Enable, disable or query maintenance mode.',
            'maintenance:mode enable|disable|status',
            'CORE',
            true,
            array(self::arg('mode', 'enable, disable or status.')),
            array(),
            array(),
            'current src/Infrastructure/Utils/Maintenance.php contains repair utilities but no maintenance-mode API; this command is reserved for the separate maintenance-mode PR.'
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
            self::opt('share-members', 'Reuse parent members in suborganizations.', 'BOOL')
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
        self::task('user:list', 'userList', 'List users in the current organization.',
            'user:list [--search=TEXT] [--group=GROUP] [--former] [--limit=N] [--offset=N] [--format=FORMAT]',
            'CONTACTS', true, array(), array(
                self::opt('search', 'Search login name and configured first/last name fields.', 'TEXT'),
                self::opt('group', 'Restrict to members of a group.', 'GROUP'),
                self::opt('former', 'Include former members.', '', false, false, true),
                self::opt('limit', 'Maximum number of records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('user:show', 'userShow', 'Show user profile data.',
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
            'user:delete USER ... [--yes]', null, true,
            array(self::arg('user', 'One or more users.', true, true)),
            array(self::opt('yes', 'Confirm permanent deletion.', '', false, false, true)));
        self::task('user:export', 'userExport', 'Export a user as the native vCard representation.',
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
                self::opt('password', 'New password.', 'PASSWORD'),
                self::opt('password-stdin', 'Read password from STDIN.', '', false, false, true)
            ));
        self::task('user:send-password', 'userSendPassword', 'Send the native password-reset/new-password email.',
            'user:send-password USER', 'CONTACTS', true, array(self::arg('user', 'User.')));
        self::task('user:send-login', 'userSendPassword', 'Send the native login/new-password email.',
            'user:send-login USER', 'CONTACTS', true, array(self::arg('user', 'User.')));
        self::task('user:tfa-status', 'userTfaStatus', 'Show whether two-factor authentication is configured.',
            'user:tfa-status USER [--format=text|json]', 'CONTACTS', true,
            array(self::arg('user', 'User.')), array(
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        self::task(
            'user:tfa-setup',
            'unavailable',
            'Set up two-factor authentication.',
            'user:tfa-setup USER',
            'CONTACTS',
            true,
            array(self::arg('user', 'User.')),
            array(),
            array(),
            'current modules/profile/two_factor_authentication.php stores the generated secret in the web session between generation and OTP confirmation; no headless setup API exists.'
        );
        self::task('user:tfa-reset', 'userTfaReset', 'Remove the configured two-factor secret.',
            'user:tfa-reset USER [--yes]', 'CONTACTS', true,
            array(self::arg('user', 'User.')), array(self::opt('yes', 'Confirm reset.', '', false, false, true)));
        self::task(
            'user:photo-set',
            'unavailable',
            'Set the user profile photo.',
            'user:photo-set USER FILE',
            'CONTACTS',
            true,
            array(self::arg('user', 'User.'), self::arg('file', 'JPEG/PNG image file.')),
            array(),
            array(),
            'current modules/profile/profile_photo_edit.php contains the image-validation/scaling/storage workflow directly in the web module; no reusable profile-photo service exists.'
        );
        self::task(
            'user:photo-delete',
            'unavailable',
            'Delete the user profile photo.',
            'user:photo-delete USER [--yes]',
            'CONTACTS',
            true,
            array(self::arg('user', 'User.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)),
            array(),
            'current modules/profile/profile_photo_edit.php contains profile-photo storage/deletion directly in the web module; no reusable profile-photo service exists.'
        );
    }

    private static function registerRelationTasks(): void
    {
        self::task('relation-type:list', 'relationTypeList', 'List user relation types.',
            'relation-type:list [--format=FORMAT]', 'CONTACTS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::task('relation-type:show', 'relationTypeShow', 'Show a user relation type.',
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
            ));
        self::task('relation-type:update', 'relationTypeUpdate', 'Update a user relation type. Its existing relation shape is retained, matching current master.',
            'relation-type:update TYPE [options]', 'CONTACTS', true,
            array(self::arg('type', 'Relation type UUID/id.')), $baseTypeOptions);
        self::task('relation-type:delete', 'relationTypeDelete', 'Delete a user relation type.',
            'relation-type:delete TYPE [--yes]', 'CONTACTS', true,
            array(self::arg('type', 'Relation type UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('relation:list', 'relationList', 'List user relations.',
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
        self::task('registration:create-user', 'registrationApprove', 'Alias for registration:approve.',
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
        self::task('group:list', 'groupList', 'List groups/roles.',
            'group:list [--category=CATEGORY] [--active=BOOL] [--format=FORMAT]', 'GROUPS-ROLES', true,
            array(), array(
                self::opt('category', 'Role category.', 'CATEGORY'),
                self::opt('active', 'Filter rol_valid.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('group:show', 'groupShow', 'Show a group/role.',
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
        self::task('group:export', 'groupExport', 'Export group members as vCards.',
            'group:export GROUP [--output=FILE]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')));
        self::task('group:permissions', 'groupPermissions', 'Show or change native role permission columns.',
            'group:permissions GROUP [--set=RIGHT=BOOL ...] [--format=text|json]', 'GROUPS-ROLES', true,
            array(self::arg('group', 'Group.')), array(
                self::opt('set', 'Set a rol_* permission column.', 'RIGHT=BOOL', false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))
            ));
        self::task('group:members', 'groupMembers', 'List role memberships.',
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
            array(self::opt('date', 'Membership end date; defaults to today.', 'DATE')));
        self::task('group:updateuser', 'groupUpdateUser', 'Update an existing membership period/leader status.',
            'group:updateuser GROUP USER [--start=DATE] [--end=DATE] [--leader=BOOL]', null, true,
            array(self::arg('group', 'Group.'), self::arg('user', 'User.')), array(
                self::opt('start', 'Membership start date.', 'DATE'),
                self::opt('end', 'Membership end date.', 'DATE'),
                self::opt('leader', 'Leader status.', 'BOOL')
            ));
        self::task('group:deletemembership', 'groupDeleteMembership', 'Permanently delete one membership history row.',
            'group:deletemembership MEMBERSHIP [--yes]', 'GROUPS-ROLES', true,
            array(self::arg('membership', 'Membership UUID/id.')),
            array(self::opt('yes', 'Confirm permanent history deletion.', '', false, false, true)));
        self::task('group:dependencies', 'groupDependencies', 'List parent/child role dependencies.',
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
        self::task('list:list', 'listList', 'List saved member-list configurations.',
            'list:list [--global=BOOL] [--format=FORMAT]', 'GROUPS-ROLES', true, array(), array(
                self::opt('global', 'Filter global/private lists.', 'BOOL'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('list:show', 'listShow', 'Show a saved member-list configuration.',
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
        self::task(
            'list:export',
            'unavailable',
            'Render a saved member list to CSV/XLSX/ODS/PDF.',
            'list:export LIST [options] --format=csv|xlsx|ods|pdf|pdfl',
            'GROUPS-ROLES',
            true,
            array(self::arg('list', 'List UUID/id.')),
            array(self::opt('format', 'Export format.', 'FORMAT', true, false, false, array('csv', 'xlsx', 'ods', 'pdf', 'pdfl'))),
            array(),
            'current modules/groups-roles/lists_show.php builds exports through web-presenter/output code; there is no reusable headless list-export service in current master.'
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
        self::task('category:list', 'categoryList', 'List categories.',
            'category:list [--type=ANN|AWA|EVT|FOT|IVT|LNK|ROL|USF] [--format=FORMAT]', 'CATEGORIES', true,
            array(), array(
                self::opt('type', 'Category type.', 'TYPE', false, false, false, array('ANN', 'AWA', 'EVT', 'FOT', 'IVT', 'LNK', 'ROL', 'USF')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('category:show', 'categoryShow', 'Show a category.',
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
        self::task('announcement:list', 'announcementList', 'List announcements visible to the acting user.',
            'announcement:list [--category=CATEGORY] [--search=TEXT] [--limit=N] [--offset=N] [--format=FORMAT]',
            'ANNOUNCEMENTS', true, array(), array(
                self::opt('category', 'Announcement category.', 'CATEGORY'),
                self::opt('search', 'Headline/description search.', 'TEXT'),
                self::opt('limit', 'Maximum records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('announcement:show', 'announcementShow', 'Show an announcement.',
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
        self::task(
            'announcement:export-rss',
            'unavailable',
            'Generate the announcements RSS feed.',
            'announcement:export-rss [--category=CATEGORY] [--output=FILE]',
            'ANNOUNCEMENTS',
            true,
            array(),
            array(self::opt('category', 'Announcement category.', 'CATEGORY')),
            array(),
            'AnnouncementsService::rssFeed() emits the HTTP response directly through RssFeed::getRssFeed(); current master has no method returning the RSS document for headless output.'
        );
    }

    private static function registerEventTasks(): void
    {
        self::task('event:list', 'eventList', 'List events.',
            'event:list [--calendar=CATEGORY] [--date-from=DATE] [--date-to=DATE] [--format=FORMAT]',
            'EVENTS', true, array(), array(
                self::opt('calendar', 'Event category/calendar.', 'CATEGORY'),
                self::opt('date-from', 'Start date.', 'DATE'),
                self::opt('date-to', 'End date.', 'DATE'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('event:show', 'eventShow', 'Show an event.',
            'event:show EVENT [--format=text|json]', 'EVENTS', true,
            array(self::arg('event', 'Event UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $eventReason = 'current modules/events/events_function.php contains the complete save/copy/delete orchestration '
            . '(participation role, room conflicts, participant rights and notifications) around Event; no data-oriented EventsService exists yet.';
        foreach (array('event:add', 'event:update', 'event:copy') as $command) {
            self::task($command, 'unavailable', str_replace(':', ' ', ucfirst($command)) . '.',
                $command . ' [options]', 'EVENTS', true, array(), array(), array(), $eventReason);
        }
        self::task('event:delete', 'eventDelete', 'Delete an event through Event::delete().',
            'event:delete EVENT [--yes]', 'EVENTS', true,
            array(self::arg('event', 'Event.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('event:participate', 'eventParticipation', 'Set participation to yes.',
            'event:participate EVENT [USER] [--guests=N] [--comment=TEXT]', null, true,
            array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)), array(
                self::opt('guests', 'Number of additional guests.', 'N'),
                self::opt('comment', 'Participation comment.', 'TEXT')
            ));
        self::task('event:cancel', 'eventParticipation', 'Set participation to no.',
            'event:cancel EVENT [USER] [--comment=TEXT]', null, true,
            array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)),
            array(self::opt('comment', 'Participation comment.', 'TEXT')));
        self::task('event:maybe', 'eventParticipation', 'Set participation to maybe.',
            'event:maybe EVENT [USER] [--comment=TEXT]', null, true,
            array(self::arg('event', 'Event.'), self::arg('user', 'User; defaults to actor.', false)),
            array(self::opt('comment', 'Participation comment.', 'TEXT')));
        self::task('event:participants', 'eventParticipants', 'List event participants.',
            'event:participants EVENT [--format=FORMAT]', 'EVENTS', true,
            array(self::arg('event', 'Event.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        $exportReason = 'current event iCalendar output is implemented in the web module/legacy ModuleEvents output path and does not expose a headless document-return API.';
        self::task('event:export', 'unavailable', 'Export an event as iCalendar.',
            'event:export EVENT --format=ics [--output=FILE]', 'EVENTS', true,
            array(self::arg('event', 'Event.')), array(), array(), $exportReason);
        self::task('event:export-calendar', 'unavailable', 'Export event range/calendar as iCalendar.',
            'event:export-calendar [options] --format=ics [--output=FILE]', 'EVENTS', true,
            array(), array(), array(), $exportReason);
    }

    private static function registerRoomTasks(): void
    {
        self::task('room:list', 'roomList', 'List rooms.',
            'room:list [--format=FORMAT]', 'ROOMS', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))));
        self::task('room:show', 'roomShow', 'Show a room.',
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

    private static function registerForumTasks(): void
    {
        self::task('forum:list', 'forumList', 'List forum topics.',
            'forum:list [--category=CATEGORY] [--format=FORMAT]', 'FORUM', true, array(), array(
                self::opt('category', 'Forum category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('forum:topic', 'forumTopic', 'Show a forum topic and its posts.',
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
        self::task(
            'forum:export-rss',
            'unavailable',
            'Generate the forum RSS feed.',
            'forum:export-rss [--category=CATEGORY] [--output=FILE]',
            'FORUM',
            true,
            array(),
            array(self::opt('category', 'Forum category.', 'CATEGORY')),
            array(),
            'ForumService::rssFeed() emits the HTTP response through RssFeed directly; current master has no headless RSS document-return API.'
        );
    }

    private static function registerLinkTasks(): void
    {
        self::task('link:list', 'linkList', 'List web links.',
            'link:list [--category=CATEGORY] [--format=FORMAT]', 'LINKS', true, array(), array(
                self::opt('category', 'Link category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('link:show', 'linkShow', 'Show a web link.',
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
        self::task('message:list', 'messageList', 'List messages involving the acting user.',
            'message:list [--type=email|pm] [--limit=N] [--offset=N] [--format=FORMAT]',
            'MESSAGES', true, array(), array(
                self::opt('type', 'Message type.', 'TYPE', false, false, false, array('email', 'pm')),
                self::opt('limit', 'Maximum records.', 'N'),
                self::opt('offset', 'Result offset.', 'N'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('message:show', 'messageShow', 'Show a message/conversation entry.',
            'message:show MESSAGE [--format=text|json]', 'MESSAGES', true,
            array(self::arg('message', 'Message UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $sendOptions = array(
            self::opt('type', 'email or private message.', 'TYPE', true, false, false, array('email', 'pm')),
            self::opt('user', 'Recipient user.', 'USER', false, true),
            self::opt('group', 'Recipient group.', 'GROUP', false, true),
            self::opt('subject', 'Message subject.', 'TEXT', true),
            self::opt('body', 'Message body.', 'TEXT'),
            self::opt('body-file', 'Read body from file.', 'FILE'),
            self::opt('attachment', 'Attachment path.', 'FILE', false, true)
        );
        self::task(
            'message:send',
            'unavailable',
            'Send email/private message using the native Message/Email recipient rules.',
            'message:send --type=email|pm [recipients] --subject=TEXT (--body=TEXT|--body-file=FILE)',
            'MESSAGES',
            true,
            array(),
            $sendOptions,
            array(),
            'current modules/messages/messages_write.php contains the recipient authorization, mail/private-message split and form processing; Message alone does not cover the complete email send workflow as a headless service.'
        );
        foreach (array('message:reply', 'message:forward') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(':', ' ', $command)) . '.',
                $command . ' MESSAGE [options]', 'MESSAGES', true,
                array(self::arg('message', 'Source message.')), array(), array(),
                'current master implements reply/forward preparation and send orchestration in modules/messages/messages_write.php; no headless message composition service exists.');
        }
        self::task('message:delete', 'messageDelete', 'Delete message records using Message::delete().',
            'message:delete MESSAGE ... [--yes]', 'MESSAGES', true,
            array(self::arg('message', 'One or more messages.', true, true)),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('message:list-attachments', 'messageAttachments', 'List attachments of a message.',
            'message:list-attachments MESSAGE [--format=FORMAT]', 'MESSAGES', true,
            array(self::arg('message', 'Message.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task(
            'message:get-attachment',
            'unavailable',
            'Retrieve a message attachment.',
            'message:get-attachment ATTACHMENT [--output=FILE]',
            'MESSAGES',
            true,
            array(self::arg('attachment', 'Attachment identifier.')),
            array(),
            array(),
            'current modules/messages/messages_attachment.php performs the attachment ownership/access check and HTTP download; Message exposes attachment metadata but no permission-checked headless attachment fetch operation.'
        );
    }

    private static function registerDocumentTasks(): void
    {
        self::task('document:list', 'documentList', 'List folders/files visible below a folder.',
            'document:list [FOLDER] [--recursive] [--format=FORMAT]', 'DOCUMENTS-FILES', true,
            array(self::arg('folder', 'Folder UUID; empty means root.', false)), array(
                self::opt('recursive', 'Traverse recursively.', '', false, false, true),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task(
            'document:download',
            'unavailable',
            'Download a managed document.',
            'document:download FILE [--output=FILEPATH]',
            'DOCUMENTS-FILES',
            true,
            array(self::arg('file', 'File UUID.')),
            array(),
            array(),
            'DocumentsService::downloadFile() delegates to File::getFileForDownload(), which emits an HTTP response; current master has no headless permission-checked byte-return method.'
        );
        self::task(
            'document:upload',
            'unavailable',
            'Upload a managed document.',
            'document:upload FILEPATH --folder=FOLDER [--name=NAME]',
            'DOCUMENTS-FILES',
            true,
            array(self::arg('filepath', 'Local file path.')),
            array(self::opt('folder', 'Destination folder.', 'FOLDER', true), self::opt('name', 'Stored filename.', 'NAME')),
            array(),
            'current upload handling is implemented by the jQuery file-upload endpoint and web request upload semantics; no native headless upload service is exposed.'
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
        self::task('document:permissions', 'documentPermissions', 'Show folder view/upload role assignments.',
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
        self::task('photo:list', 'photoList', 'List photo albums.',
            'photo:list [--parent=ALBUM] [--format=FORMAT]', 'PHOTOS', true, array(), array(
                self::opt('parent', 'Parent album.', 'ALBUM'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('photo:album-show', 'photoAlbumShow', 'Show a photo album.',
            'photo:album-show ALBUM [--format=text|json]', 'PHOTOS', true,
            array(self::arg('album', 'Album UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $albumOptions = array(
            self::opt('name', 'Album name.', 'NAME'),
            self::opt('parent', 'Parent album.', 'ALBUM'),
            self::opt('begin', 'Begin date.', 'DATE'),
            self::opt('end', 'End date.', 'DATE'),
            self::opt('photographers', 'Photographers.', 'TEXT'),
            self::opt('description', 'Description.', 'TEXT'),
            self::opt('locked', 'Album locked flag.', 'BOOL')
        );
        self::task(
            'photo:album-add',
            'unavailable',
            'Create a photo album.',
            'photo:album-add NAME [options]',
            'PHOTOS',
            true,
            array(self::arg('name', 'Album name.')),
            $albumOptions,
            array(),
            'current modules/photos/photos_new.php combines Album database changes with organization filesystem directory naming and rename/create behavior; no reusable album-save service exposes that complete operation.'
        );
        self::task('photo:album-update', 'unavailable', 'Update a photo album.',
            'photo:album-update ALBUM [options]', 'PHOTOS', true,
            array(self::arg('album', 'Album.')), $albumOptions, array(),
            'current modules/photos/photos_new.php combines Album database changes with organization filesystem directory naming and rename/create behavior; no reusable album-save service exposes that complete operation.');
        self::task('photo:album-delete', 'photoAlbumDelete', 'Delete a photo album through Album::delete().',
            'photo:album-delete ALBUM [--yes]', 'PHOTOS', true,
            array(self::arg('album', 'Album.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('photo:album-lock', 'photoAlbumLock', 'Lock a photo album.',
            'photo:album-lock ALBUM', 'PHOTOS', true, array(self::arg('album', 'Album.')));
        self::task('photo:album-unlock', 'photoAlbumUnlock', 'Unlock a photo album.',
            'photo:album-unlock ALBUM', 'PHOTOS', true, array(self::arg('album', 'Album.')));
        $photoFsReason = 'current photo upload/download/rotate/ecard functionality is implemented directly in modules/photos with filesystem/HTTP request handling; no reusable headless photo-file service exists.';
        foreach (array('photo:album-download','photo:upload','photo:download','photo:delete','photo:rotate','photo:ecard-templates','photo:ecard-send') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' [arguments] [options]', 'PHOTOS', true, array(), array(), array(), $photoFsReason);
        }
    }

    private static function registerInventoryTasks(): void
    {
        self::task('inventory:list', 'inventoryList', 'List inventory items.',
            'inventory:list [--search=TEXT] [--category=CATEGORY] [--status=active|retired|all] [--format=FORMAT]',
            'INVENTORY', true, array(), array(
                self::opt('search', 'Search item data.', 'TEXT'),
                self::opt('category', 'Inventory category.', 'CATEGORY'),
                self::opt('status', 'Item status.', 'STATUS', false, false, false, array('active', 'retired', 'all')),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('inventory:show', 'inventoryShow', 'Show an inventory item and its configured field values.',
            'inventory:show ITEM [--format=text|json]', 'INVENTORY', true,
            array(self::arg('item', 'Item UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));

        $itemReason = 'ItemService::save() and picture handling currently validate FormPresenter/session POST/upload data; '
            . 'a reusable headless item-save API must be extracted before CLI create/update/copy/picture operations.';
        self::task('inventory:add', 'unavailable', 'Create an inventory item.',
            'inventory:add --field=FIELD=VALUE ... [--picture=FILE]', 'INVENTORY', true,
            array(), array(self::opt('field', 'Inventory field assignment.', 'FIELD=VALUE', true, true)), array(), $itemReason);
        self::task('inventory:update', 'unavailable', 'Update an inventory item.',
            'inventory:update ITEM [--field=FIELD=VALUE ...]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(self::opt('field', 'Inventory field assignment.', 'FIELD=VALUE', false, true)), array(), $itemReason);
        self::task('inventory:copy', 'unavailable', 'Copy an inventory item.',
            'inventory:copy ITEM [--copies=N] [--field=FIELD=VALUE ...]', 'INVENTORY', true,
            array(self::arg('item', 'Item.')), array(), array(), $itemReason);
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
        foreach (array('inventory:picture-set', 'inventory:picture-get', 'inventory:picture-delete') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' ITEM [FILE]', 'INVENTORY', true, array(), array(), array(), $itemReason);
        }
        $importReason = 'Inventory ImportService reads the current uploaded web file/form state; it does not expose a file-path + mapping API.';
        self::task('inventory:import', 'unavailable', 'Import inventory items.',
            'inventory:import FILE [options]', 'INVENTORY', true,
            array(self::arg('file', 'Import file.')), array(), array(), $importReason);
        $exportReason = 'Inventory ExportService::createExport() sends browser headers/files directly and has no headless output-target API.';
        self::task('inventory:export', 'unavailable', 'Export inventory items.',
            'inventory:export --format=FORMAT [--output=FILE]', 'INVENTORY', true,
            array(), array(), array(), $exportReason);

        self::task('inventory:fields', 'inventoryFields', 'List inventory field definitions.',
            'inventory:fields [--format=FORMAT]', 'INVENTORY', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('inventory:field-show', 'inventoryFieldShow', 'Show an inventory field definition.',
            'inventory:field-show FIELD [--format=text|json]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field UUID/id/internal name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $inventoryFieldReason = 'ItemFieldService::save() validates the web FormPresenter/POST object; current master has no data-oriented field-definition save method.';
        foreach (array('inventory:field-add','inventory:field-update') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' [FIELD] [options]', 'INVENTORY', true, array(), array(), array(), $inventoryFieldReason);
        }
        self::task('inventory:field-delete', 'inventoryFieldDelete', 'Delete an inventory field using ItemFieldService::delete().',
            'inventory:field-delete FIELD [--yes]', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('inventory:field-move', 'inventoryFieldMove', 'Move an inventory field.',
            'inventory:field-move FIELD up|down', 'INVENTORY', true,
            array(self::arg('field', 'Inventory field.'), self::arg('direction', 'up or down.')));
        self::task('inventory:options', 'inventoryOptions', 'List select options for an inventory field.',
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
        self::task('profile:fields', 'profileFields', 'List profile field definitions.',
            'profile:fields [--category=CATEGORY] [--format=FORMAT]', 'CONTACTS', true, array(), array(
                self::opt('category', 'Profile-field category.', 'CATEGORY'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))
            ));
        self::task('profile:field-show', 'profileFieldShow', 'Show a profile field definition.',
            'profile:field-show FIELD [--format=text|json]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field UUID/id/internal name.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $fieldReason = 'ProfileFieldService::save() validates FormPresenter/session POST; current master has no data-oriented profile-field definition save API.';
        self::task('profile:field-add', 'unavailable', 'Create a profile field.',
            'profile:field-add [options]', 'CONTACTS', true, array(), array(), array(), $fieldReason);
        self::task('profile:field-update', 'unavailable', 'Update a profile field.',
            'profile:field-update FIELD [options]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.')), array(), array(), $fieldReason);
        self::task('profile:field-delete', 'profileFieldDelete', 'Delete a profile field through ProfileField::delete().',
            'profile:field-delete FIELD [--yes]', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task('profile:field-move', 'profileFieldMove', 'Move a profile field.',
            'profile:field-move FIELD up|down', 'CONTACTS', true,
            array(self::arg('field', 'Profile field.'), self::arg('direction', 'up or down.')));
        self::task('profile:options', 'profileOptions', 'List select options of a profile field.',
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
        self::task('category-report:list', 'categoryReportList', 'List category-report configurations.',
            'category-report:list [--format=FORMAT]', 'CATEGORY-REPORT', true, array(),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'md', 'dokuwiki'))));
        self::task('category-report:show', 'categoryReportShow', 'Show a category-report configuration.',
            'category-report:show CONFIG [--format=text|json]', 'CATEGORY-REPORT', true,
            array(self::arg('config', 'Report config UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $reportReason = 'category report configuration and export are implemented by system/classes/CategoryReport.php plus module web forms; '
            . 'current master has no Entity/Service for safely writing configurations or returning generated exports headlessly.';
        foreach (array('category-report:add','category-report:update','category-report:copy','category-report:delete','category-report:run') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' [arguments] [options]', 'CATEGORY-REPORT', true, array(), array(), array(), $reportReason);
        }
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
            'plugin:enable',
            'unavailable',
            'Enable an installed plugin.',
            'plugin:enable PLUGIN',
            'PLUGINS',
            true,
            array(self::arg('plugin', 'Plugin name.')),
            array(),
            array(),
            'current PluginAbstract::isActivated() equates activation with installation and PluginManager::getActivePlugins() explicitly has no separate persisted activation state.'
        );
        self::task(
            'plugin:disable',
            'unavailable',
            'Disable an installed plugin.',
            'plugin:disable PLUGIN',
            'PLUGINS',
            true,
            array(self::arg('plugin', 'Plugin name.')),
            array(),
            array(),
            'current PluginAbstract::isActivated() equates activation with installation and PluginManager::getActivePlugins() explicitly has no separate persisted activation state.'
        );
        self::task(
            'plugin:move',
            'unavailable',
            'Move plugin ordering.',
            'plugin:move PLUGIN up|down',
            'PLUGINS',
            true,
            array(self::arg('plugin', 'Plugin name.'), self::arg('direction', 'up or down.')),
            array(),
            array(),
            'current PluginManager/PluginAbstract do not expose a generic persisted plugin sequence operation.'
        );
    }

    private static function registerRequirementsTasks(): void
    {
        self::task('requirements:list', 'requirementsList', 'List requirements providers visible to the actor.',
            'requirements:list [--query=TEXT] [--format=FORMAT]', null, true, array(), array(
                self::opt('query', 'Search provider name/address/url/description.', 'TEXT'),
                self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('table', 'json', 'csv', 'md', 'dokuwiki'))
            ));
        self::task('requirements:show', 'requirementsShow', 'Show a requirements provider if visible.',
            'requirements:show PROVIDER [--format=text|json]', null, true,
            array(self::arg('provider', 'Provider UUID/id.')),
            array(self::opt('format', 'Output format.', 'FORMAT', false, false, false, array('text', 'json'))));
        $providerOptions = array(
            self::opt('name', 'Provider name.', 'NAME'),
            self::opt('address', 'Postal address.', 'TEXT'),
            self::opt('url', 'Website.', 'URL'),
            self::opt('description', 'Description.', 'TEXT'),
            self::opt('qualified', 'Qualified flag; administrator only.', 'BOOL'),
            self::opt('public', 'Public visibility flag.', 'BOOL'),
            self::opt('editable', 'Editable-by-users flag.', 'BOOL')
        );
        self::task('requirements:provider-add', 'requirementsAdd', 'Create a requirements provider.',
            'requirements:provider-add --name=NAME [options]', null, true, array(),
            array_replace($providerOptions, array(0 => self::opt('name', 'Provider name.', 'NAME', true))));
        self::task('requirements:provider-update', 'requirementsUpdate', 'Update a requirements provider according to Provider access rules.',
            'requirements:provider-update PROVIDER [options]', null, true,
            array(self::arg('provider', 'Provider.')), $providerOptions);
        self::task('requirements:provider-delete', 'requirementsDelete', 'Delete a requirements provider if Provider::isDeletable().',
            'requirements:provider-delete PROVIDER [--yes]', null, true,
            array(self::arg('provider', 'Provider.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
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
        $ssoReason = 'SSOService::save() validates the web FormPresenter/POST payload before applying client mappings and access roles; '
            . 'current master does not expose a data-oriented SSO client save method.';
        foreach (array('sso:saml-add','sso:saml-update','sso:oidc-add','sso:oidc-update') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' [CLIENT] [options]', 'CORE', true, array(), array(), array(), $ssoReason);
        }
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
            'unavailable',
            'Render OIDC discovery metadata.',
            'sso:oidc-discovery [--output=FILE]',
            'CORE',
            true,
            array(),
            array(),
            array(),
            'OIDCService::handleDiscoveryRequest() returns an HTTP PSR-7 response from the web protocol service; current master has no public data method returning the discovery array.'
        );
        self::task(
            'sso:saml-metadata',
            'unavailable',
            'Render SAML IdP metadata.',
            'sso:saml-metadata [--output=FILE]',
            'CORE',
            true,
            array(),
            array(),
            array(),
            'SAMLService::handleMetadataRequest() is an HTTP protocol response method; current master has no public headless metadata-document method.'
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
        $keySaveReason = 'KeyService::save() validates the web FormPresenter/POST payload; a data-oriented save operation must be extracted before CLI key create/update/regenerate is safe.';
        foreach (array('sso:key-add','sso:key-update','sso:key-generate','sso:key-regenerate') as $command) {
            self::task($command, 'unavailable', ucfirst(str_replace(array(':','-'), ' ', $command)) . '.',
                $command . ' [KEY] [options]', 'CORE', true, array(), array(), array(), $keySaveReason);
        }
        self::task(
            'sso:key-import',
            'unavailable',
            'Import an SSO key/certificate.',
            'sso:key-import --name=NAME --file=FILE [--password-stdin]',
            'CORE',
            true,
            array(),
            array(),
            array(),
            'the import branch in current modules/sso/keys.php is explicitly TODO; no current-master import API exists.'
        );
        self::task('sso:key-delete', 'ssoKeyDelete', 'Delete an SSO key Entity.',
            'sso:key-delete KEY [--yes]', 'CORE', true,
            array(self::arg('key', 'Key UUID/id.')),
            array(self::opt('yes', 'Confirm deletion.', '', false, false, true)));
        self::task(
            'sso:key-export',
            'unavailable',
            'Export an SSO key as PKCS#12.',
            'sso:key-export KEY [--password-stdin] [--output=FILE]',
            'CORE',
            true,
            array(self::arg('key', 'Key UUID/id.')),
            array(),
            array(),
            'KeyService::exportToPkcs12() sends HTTP headers, echoes data and exits; current master needs a byte-return method before headless export.'
        );
        self::task(
            'sso:certificate-export',
            'unavailable',
            'Export an SSO certificate.',
            'sso:certificate-export KEY [--output=FILE]',
            'CORE',
            true,
            array(self::arg('key', 'Key UUID/id.')),
            array(),
            array(),
            'KeyService::exportCertificate() sends HTTP headers, echoes data and exits; current master needs a byte-return method before headless export.'
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

    public static function version(array $arguments, array $options): int
    {
        global $gSystemComponent;

        $data = array(
            'filesystem' => ADMIDIO_VERSION_TEXT,
            'database' => self::componentVersion($gSystemComponent)
        );

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            CliApplication::writeOutput(
                'Admidio ' . $data['filesystem'] . PHP_EOL
                . 'Database core ' . $data['database'] . PHP_EOL,
                $options
            );
        } else {
            CliApplication::writeValue($data, $options, $format);
        }

        return 0;
    }

    public static function status(array $arguments, array $options): int
    {
        global $gSystemComponent, $gCurrentOrganization;

        if ((int)$gSystemComponent->getValue('com_id') === 0) {
            throw new RuntimeException('The CORE component record was not found in the database.');
        }

        $databaseVersion = self::componentVersion($gSystemComponent);
        $filesystemVersion = ADMIDIO_VERSION
            . (ADMIDIO_VERSION_BETA > 0 ? '-Beta.' . ADMIDIO_VERSION_BETA : '');
        $updateCompleted = $gSystemComponent->getValue('com_update_completed');
        $updateStep = (int)$gSystemComponent->getValue('com_update_step');

        if ($updateCompleted === '' || $updateCompleted === null) {
            $state = 'update-required';
        } elseif ($updateCompleted !== true && $updateCompleted !== 1 && $updateCompleted !== '1') {
            $state = 'update-incomplete';
        } else {
            $comparison = version_compare($databaseVersion, $filesystemVersion);
            $state = $comparison < 0 ? 'update-required'
                : ($comparison > 0 ? 'filesystem-older-than-database' : 'ok');
        }

        $data = array(
            'organization' => (string)$gCurrentOrganization->getValue('org_shortname'),
            'filesystem_version' => ADMIDIO_VERSION_TEXT,
            'database_version' => $databaseVersion,
            'database_update_step' => $updateStep,
            'status' => $state
        );

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            CliApplication::writeOutput(
                'Organization: ' . $data['organization'] . PHP_EOL
                . 'Filesystem:   ' . $data['filesystem_version'] . PHP_EOL
                . 'Database:     ' . $data['database_version'] . PHP_EOL
                . 'Update step:  ' . $data['database_update_step'] . PHP_EOL
                . 'Status:       ' . strtoupper(str_replace('-', ' ', $data['status'])) . PHP_EOL,
                $options
            );
        } else {
             CliApplication::writeValue($data, $options, $format);
        }

        return $state === 'ok' ? 0 : 3;
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

        $format = CliApplication::optionString($options, 'format', 'text');
        if ($format === 'text') {
            CliApplication::writeOutput(
                'Stable version: ' . $data['stableVersion'] . PHP_EOL
                . 'Beta version:   ' . $data['betaVersion']
                . ($data['betaRelease'] !== '' ? '-Beta.' . $data['betaRelease'] : '') . PHP_EOL
                . 'Update state:   ' . $data['versionUpdate'] . PHP_EOL,
                $options
            );
        } else {
            CliApplication::writeValue($data, $options, $format);
        }

        return (int)$data['versionUpdate'] === 99 ? 4 : 0;
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
        ob_start();
        phpinfo();
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

        $source = ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $filename;
        $target = CliApplication::optionString($options, 'output');

        if ($target === '') {
            $target = getcwd() . DIRECTORY_SEPARATOR . $filename;
        }

        if (!@rename($source, $target)) {
            if (!@copy($source, $target)) {
                $dump->deleteDumpFile();
                throw new RuntimeException('Could not move database dump to "' . $target . '".');
            }
            $dump->deleteDumpFile();
        }

        CliApplication::writeSuccess('Database backup written to ' . $target . '.', $options);
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

        return $protected ? 0 : 3;
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
        global $gDb, $gCurrentOrgId, $gProfileFields;

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
        global $gDb, $gProfileFields;

        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        $data = array(
            'id' => (int)$user->getValue('usr_id'),
            'uuid' => (string)$user->getValue('usr_uuid'),
            'login' => (string)$user->getValue('usr_login_name'),
            'valid' => (bool)$user->getValue('usr_valid'),
            'profile' => array()
        );

        foreach ($gProfileFields->getProfileFields() as $field) {
            $nameIntern = (string)$field->getValue('usf_name_intern');
            $data['profile'][$nameIntern] = $user->getValue($nameIntern, 'database');
        }

        if (CliApplication::optionBool($options, 'memberships', false)) {
            $data['memberships'] = $gDb->queryPrepared(
                'SELECT mem_uuid AS uuid, rol_uuid AS role_uuid, rol_name AS role,
                        mem_begin AS begin, mem_end AS end, mem_leader AS leader
                   FROM ' . TBL_MEMBERS . '
             INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                  WHERE mem_usr_id = ?
               ORDER BY mem_begin DESC, rol_name',
                array((int)$user->getValue('usr_id'))
            )->fetchAll();
        }

        if (CliApplication::optionBool($options, 'relations', false)) {
            $data['relations'] = $gDb->queryPrepared(
                'SELECT ure_uuid AS uuid, urt_uuid AS type_uuid, urt_name AS type,
                        usr2.usr_uuid AS related_user_uuid, usr2.usr_login_name AS related_login
                   FROM ' . TBL_USER_RELATIONS . '
             INNER JOIN ' . TBL_USER_RELATION_TYPES . ' ON urt_id = ure_urt_id
             INNER JOIN ' . TBL_USERS . ' AS usr2 ON usr2.usr_id = ure_usr_id2
                  WHERE ure_usr_id1 = ?
               ORDER BY urt_name, usr2.usr_login_name',
                array((int)$user->getValue('usr_id'))
            )->fetchAll();
        }

        CliApplication::writeValue($data, $options);
        return 0;
    }

    public static function userAdd(array $arguments, array $options): int
    {
        global $gDb, $gProfileFields, $gCurrentOrgId;

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

        $user->save();

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

        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $role->startMembership((int)$user->getValue('usr_id'));
        }

        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Created user ' . $user->getValue('usr_uuid') . '.', $options);
        return 0;
    }

    public static function userUpdate(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));

        if (!$GLOBALS['gCurrentUser']->hasRightEditProfile($user)) {
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
        global $gDb, $gProfileFields;

        $source = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
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
        $copy->save();

        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $role->startMembership((int)$copy->getValue('usr_id'));
        }

        CliApplication::writeSuccess('Created user copy ' . $copy->getValue('usr_uuid') . '.', $options);
        return 0;
    }

    public static function userRemove(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUserId;

        CliApplication::confirm('End current organization memberships for the selected user(s)?', $options);

        foreach ($arguments as $reference) {
            $user = CliApplication::resolveUser($reference);
            if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $statement = $gDb->queryPrepared(
                'SELECT DISTINCT rol_id
                   FROM ' . TBL_MEMBERS . '
             INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                  WHERE mem_usr_id = ?
                    AND mem_begin <= ?
                    AND mem_end > ?
                    AND rol_valid = true
                    AND cat_org_id = ?',
                array((int)$user->getValue('usr_id'), DATE_NOW, DATE_NOW, $gCurrentOrgId)
            );

            while ($roleId = $statement->fetchColumn()) {
                $role = new Role($gDb, (int)$roleId);
                if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
                    throw new Exception('SYS_NO_RIGHTS');
                }
                $role->stopMembership((int)$user->getValue('usr_id'));
            }

            self::reloadUserSessions((int)$user->getValue('usr_id'));
        }

        CliApplication::writeSuccess('Selected user membership(s) ended.', $options);
        return 0;
    }

    public static function userDelete(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser, $gCurrentUserId;

        if (!$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        CliApplication::confirm('Permanently delete the selected user(s)?', $options);

        foreach ($arguments as $reference) {
            $user = CliApplication::resolveUser($reference);
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

            $user->delete();
        }

        CliApplication::writeSuccess('Selected user(s) deleted.', $options);
        return 0;
    }

    public static function userExport(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        CliApplication::writeOutput($user->getVCard(), $options);
        return 0;
    }

    public static function userSetPassword(array $arguments, array $options): int
    {
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user'));
        if (!$GLOBALS['gCurrentUser']->hasRightEditProfile($user)) {
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
        global $gDb, $gCurrentUser;

        if (!$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

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
        global $gDb, $gCurrentUser;

        if (!$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

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
        global $gCurrentUser;
        if (!$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete this relation type?', $options);
        self::resolveRelationType(CliApplication::requireArgument($arguments, 0, 'type'))->delete();
        CliApplication::writeSuccess('Relation type deleted.', $options);
        return 0;
    }

    public static function relationList(array $arguments, array $options): int
    {
        global $gDb;
        $where = array('1 = 1');
        $params = array();
        if (isset($arguments[0])) {
            $user = CliApplication::resolveUser($arguments[0]);
            $where[] = '(ure_usr_id1 = ? OR ure_usr_id2 = ?)';
            $params[] = (int)$user->getValue('usr_id');
            $params[] = (int)$user->getValue('usr_id');
        }
        if (array_key_exists('type', $options)) {
            $type = self::resolveRelationType(CliApplication::optionString($options, 'type'));
            $where[] = 'ure_urt_id = ?';
            $params[] = (int)$type->getValue('urt_id');
        }

        $rows = $gDb->queryPrepared(
            'SELECT ure.ure_id AS id, ure.ure_uuid AS uuid, urt.urt_name AS type,
                    ure.ure_usr_id1 AS user1_id, ure.ure_usr_id2 AS user2_id
               FROM ' . TBL_USER_RELATIONS . ' ure
         INNER JOIN ' . TBL_USER_RELATION_TYPES . ' urt ON urt.urt_id = ure.ure_urt_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY ure.ure_id',
            $params
        )->fetchAll();
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function relationAdd(array $arguments, array $options): int
    {
        global $gDb;
        $user1 = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 0, 'user1'));
        $user2 = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user2'));
        $type = self::resolveRelationType(CliApplication::requireArgument($arguments, 2, 'type'));
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

        CliApplication::writeValue(array('id' => (int)$relation->getValue('ure_id'), 'uuid' => (string)$relation->getValue('ure_uuid')), $options);
        return 0;
    }

    public static function relationDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete this user relation?', $options);
        self::resolveRelation(CliApplication::requireArgument($arguments, 0, 'relation'))->delete();
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
        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $rows = array();
        foreach ($registration->searchSimilarUsers() as $userId) {
            $user = CliApplication::resolveUser((string)$userId);
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
        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $validationId = CliApplication::requireArgument($arguments, 1, 'validation-id');

        $service = new RegistrationService($GLOBALS['gDb'], (string)$registration->getValue('usr_uuid'));
        $result = $service->confirmRegistration($validationId);

        CliApplication::writeValue($result, $options);
        return 0;
    }

    public static function registrationApprove(array $arguments, array $options): int
    {
        $registration = self::resolveRegistration(CliApplication::requireArgument($arguments, 0, 'user'));
        $registration->acceptRegistration();
        foreach (CliApplication::optionValues($options, 'group') as $groupReference) {
            $role = self::resolveGroup($groupReference);
            if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $role->startMembership((int)$registration->getValue('usr_id'));
        }
        self::reloadUserSessions((int)$registration->getValue('usr_id'));
        CliApplication::writeSuccess('Registration approved.', $options);
        return 0;
    }

    public static function registrationAssign(array $arguments, array $options): int
    {
        $registration = self::resolveRegistration(
            CliApplication::requireArgument($arguments, 0, 'registration-user')
        );
        $existing = CliApplication::resolveUser(
            CliApplication::requireArgument($arguments, 1, 'existing-user')
        );

        $service = new RegistrationService(
            $GLOBALS['gDb'],
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
        global $gDb, $gCurrentOrgId;
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
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function groupShow(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $data = self::roleData($role);

        if (CliApplication::optionBool($options, 'permissions', false)) {
            $data['permissions'] = self::rolePermissionData($role);
        }
        if (CliApplication::optionBool($options, 'members', false)) {
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
            ? CliApplication::optionString($options, 'name')
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
        global $gDb;
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $userIds = $gDb->queryPrepared(
            'SELECT DISTINCT mem_usr_id
               FROM ' . TBL_MEMBERS . '
              WHERE mem_rol_id = ?
                AND mem_begin <= ?
                AND mem_end >= ?
           ORDER BY mem_usr_id',
            array((int)$role->getValue('rol_id'), DATE_NOW, DATE_NOW)
        )->fetchAll(PDO::FETCH_COLUMN);

        $vcards = '';
        foreach ($userIds as $userId) {
            $vcards .= CliApplication::resolveUser((string)$userId)->getVCard() . PHP_EOL;
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
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
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
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
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
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if (!array_key_exists('date', $options) || CliApplication::optionString($options, 'date') === DATE_NOW) {
            $role->stopMembership((int)$user->getValue('usr_id'));
        } else {
            $endDate = CliApplication::optionString($options, 'date');
            self::validateDate($endDate);
            $membership = self::resolveMembershipForDate(
                (int)$role->getValue('rol_id'),
                (int)$user->getValue('usr_id'),
                $endDate
            );
            $membership->setValue('mem_end', $endDate);
            $membership->save();
        }

        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Group membership ended.', $options);
        return 0;
    }

    public static function groupUpdateUser(array $arguments, array $options): int
    {
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $user = CliApplication::resolveUser(CliApplication::requireArgument($arguments, 1, 'user'));
        if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $membership = self::resolveMembershipForDate(
            (int)$role->getValue('rol_id'),
            (int)$user->getValue('usr_id'),
            DATE_NOW,
            true
        );
        $start = CliApplication::optionString($options, 'start', (string)$membership->getValue('mem_begin', 'Y-m-d'));
        $end = CliApplication::optionString($options, 'end', (string)$membership->getValue('mem_end', 'Y-m-d'));
        self::validateDateRange($start, $end);
        $leader = CliApplication::optionBool($options, 'leader', (bool)$membership->getValue('mem_leader'));

        $role->setMembership((int)$user->getValue('usr_id'), $start, $end, $leader, true);
        self::reloadUserSessions((int)$user->getValue('usr_id'));
        CliApplication::writeSuccess('Group membership updated.', $options);
        return 0;
    }

    public static function groupDeleteMembership(array $arguments, array $options): int
    {
        $membership = self::resolveMembership(CliApplication::requireArgument($arguments, 0, 'membership'));
        $role = new Role($GLOBALS['gDb'], (int)$membership->getValue('mem_rol_id'));
        if (!$role->allowedToAssignMembers($GLOBALS['gCurrentUser'])) {
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
        $role = self::resolveGroup(CliApplication::requireArgument($arguments, 0, 'group'));
        $rows = array();
        foreach (RoleDependency::getParentRoles($GLOBALS['gDb'], (int)$role->getValue('rol_id')) as $id) {
            $parent = new Role($GLOBALS['gDb'], (int)$id);
            $rows[] = array('direction' => 'parent', 'id' => (int)$id, 'uuid' => $parent->getValue('rol_uuid'), 'name' => $parent->getValue('rol_name'));
        }
        foreach (RoleDependency::getChildRoles($GLOBALS['gDb'], (int)$role->getValue('rol_id')) as $id) {
            $child = new Role($GLOBALS['gDb'], (int)$id);
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
        $dependency->insert($gCurrentUserId);
        $dependency->updateMembership();
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

    public static function permissionsList(array $arguments, array $options): int
    {
        global $gDb;
        $where = '';
        $params = array();
        if (array_key_exists('type', $options)) {
            $where = ' WHERE ror.ror_name_intern = ?';
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
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'object-id'), 'object-id');
        $rights = new RolesRights($GLOBALS['gDb'], $right, $objectId);
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
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'object-id'), 'object-id');
        CliApplication::confirm('Clear all role assignments for this object right?', $options);
        (new RolesRights($GLOBALS['gDb'], $right, $objectId))->delete();
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
            'view_roles' => (new RolesRights($GLOBALS['gDb'], 'category_view', $id))->getRolesNames(),
            'edit_roles' => (new RolesRights($GLOBALS['gDb'], 'category_edit', $id))->getRolesNames()
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
            ? CliApplication::optionString($options, 'name')
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
            'view_roles' => (new RolesRights($GLOBALS['gDb'], 'menu_view', $id))->getRolesNames()
        ), $options);
        return 0;
    }

    public static function menuAdd(array $arguments, array $options): int
    {
        global $gDb;
        $menu = new MenuEntry($gDb);
        $menu->setValue('men_name', CliApplication::requireArgument($arguments, 0, 'name'));
        self::applyMenuOptions($menu, $options, true);
        $menu->save();
        self::saveMenuRights($menu, $options);
        CliApplication::writeValue(array('id' => (int)$menu->getValue('men_id'), 'uuid' => (string)$menu->getValue('men_uuid')), $options);
        return 0;
    }

    public static function menuUpdate(array $arguments, array $options): int
    {
        $menu = self::resolveMenu(CliApplication::requireArgument($arguments, 0, 'menu'));
        self::applyMenuOptions($menu, $options, false);
        $menu->save();
        self::saveMenuRights($menu, $options);
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
        $limit = max(0, CliApplication::optionInt($options, 'limit', 0));
        $offset = max(0, CliApplication::optionInt($options, 'offset', 0));
        $sql = 'SELECT ann.ann_id AS id, ann.ann_uuid AS uuid, ann.ann_headline AS headline,
                       cat.cat_name AS category, ann.ann_timestamp_create AS created_at
                  FROM ' . TBL_ANNOUNCEMENTS . ' ann
            INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = ann.ann_cat_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY ann.ann_timestamp_create DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        $all = $gDb->queryPrepared($sql, $params)->fetchAll();
        $rows = array();
        foreach ($all as $row) {
            $announcement = new Announcement($gDb, (int)$row['id']);
            if ($announcement->isVisible()) {
                $rows[] = $row;
            }
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
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
        $copy->setValue('ann_headline', array_key_exists('headline', $options) ? CliApplication::optionString($options, 'headline') : (string)$source->getValue('ann_headline', 'database'));
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

    public static function eventList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;
        $where = array('(cat.cat_org_id = ? OR cat.cat_org_id IS NULL)');
        $params = array($gCurrentOrgId);
        if (array_key_exists('calendar', $options)) {
            $category = self::resolveCategory(CliApplication::optionString($options, 'calendar'), 'EVT');
            $where[] = 'dat.dat_cat_id = ?';
            $params[] = (int)$category->getValue('cat_id');
        }
        if (array_key_exists('date-from', $options)) {
            $date = CliApplication::optionString($options, 'date-from');
            self::validateDate($date);
            $where[] = 'dat.dat_end >= ?';
            $params[] = $date . ' 00:00:00';
        }
        if (array_key_exists('date-to', $options)) {
            $date = CliApplication::optionString($options, 'date-to');
            self::validateDate($date);
            $where[] = 'dat.dat_begin <= ?';
            $params[] = $date . ' 23:59:59';
        }
        if (array_key_exists('state', $options)) {
            $state = CliApplication::optionString($options, 'state');
            if ($state === 'actual') {
                $where[] = 'dat.dat_end >= ?';
                $params[] = DATETIME_NOW;
            } elseif ($state === 'old') {
                $where[] = 'dat.dat_end < ?';
                $params[] = DATETIME_NOW;
            }
        }

        $all = $gDb->queryPrepared(
            'SELECT dat.dat_id AS id, dat.dat_uuid AS uuid, dat.dat_headline AS headline,
                    dat.dat_begin AS begin, dat.dat_end AS end, dat.dat_all_day AS all_day,
                    cat.cat_name AS calendar, dat.dat_location AS location
               FROM ' . TBL_EVENTS . ' dat
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = dat.dat_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY dat.dat_begin',
            $params
        )->fetchAll();

        $rows = array();
        foreach ($all as $row) {
            $event = new Event($gDb, (int)$row['id']);
            if ($event->isVisible()) {
                $rows[] = $row;
            }
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function eventShow(array $arguments, array $options): int
    {
        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(array(
            'id' => (int)$event->getValue('dat_id'),
            'uuid' => $event->getValue('dat_uuid'),
            'calendar_id' => (int)$event->getValue('dat_cat_id'),
            'participation_role_id' => $event->getValue('dat_rol_id'),
            'room_id' => $event->getValue('dat_room_id'),
            'headline' => $event->getValue('dat_headline', 'database'),
            'begin' => $event->getValue('dat_begin', 'Y-m-d H:i:s'),
            'end' => $event->getValue('dat_end', 'Y-m-d H:i:s'),
            'all_day' => (bool)$event->getValue('dat_all_day'),
            'description' => $event->getValue('dat_description', 'database'),
            'location' => $event->getValue('dat_location'),
            'country' => $event->getValue('dat_country'),
            'deadline' => $event->getValue('dat_deadline', 'Y-m-d H:i:s'),
            'max_members' => (int)$event->getValue('dat_max_members'),
            'allow_comments' => (bool)$event->getValue('dat_allow_comments'),
            'additional_guests' => (bool)$event->getValue('dat_additional_guests')
        ), $options);
        return 0;
    }

    public static function eventDelete(array $arguments, array $options): int
    {
        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete event "' . $event->getValue('dat_headline') . '"?', $options);
        $event->delete();
        CliApplication::writeSuccess('Event deleted.', $options);
        return 0;
    }

    public static function eventParticipation(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser, $gCurrentUserId, $gSettingsManager;
        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if ((int)$event->getValue('dat_rol_id') === 0) {
            throw new InvalidArgumentException('Participation is not enabled for this event.');
        }

        $user = isset($arguments[1]) ? CliApplication::resolveUser($arguments[1]) : $gCurrentUser;
        $participants = new Participants($gDb, (int)$event->getValue('dat_rol_id'));
        if ((int)$user->getValue('usr_id') !== $gCurrentUserId
            && !$gCurrentUser->isAdministrator()
            && !$participants->isLeader($gCurrentUserId)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ((int)$user->getValue('usr_id') === $gCurrentUserId && !$event->allowedToParticipate() && !$event->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $membership = new Membership($gDb);
        $membership->readDataByColumns(array(
            'mem_rol_id' => (int)$event->getValue('dat_rol_id'),
            'mem_usr_id' => (int)$user->getValue('usr_id')
        ));
        if (array_key_exists('comment', $options)) {
            if (!(bool)$event->getValue('dat_allow_comments') && !$event->isEditable()) {
                throw new InvalidArgumentException('Comments are disabled for this event.');
            }
            $membership->setValue('mem_comment', CliApplication::optionString($options, 'comment'));
        }
        if (array_key_exists('guests', $options)) {
            if (!(bool)$event->getValue('dat_additional_guests') && !$event->isEditable()) {
                throw new InvalidArgumentException('Additional guests are disabled for this event.');
            }
            $membership->setValue('mem_count_guests', max(0, CliApplication::optionInt($options, 'guests', 0)));
        }
        if ($membership->isNewRecord()) {
            $membership->setValue('mem_begin', DATE_NOW);
        }
        $membership->save();

        $command = CliApplication::currentCommand();
        if ($command === 'event:participate') {
            if (!$event->possibleToParticipate() && !$participants->isLeader($gCurrentUserId)) {
                throw new Exception('SYS_PARTICIPATE_NO_RIGHTS');
            }
            $membership->startMembership(
                (int)$event->getValue('dat_rol_id'),
                (int)$user->getValue('usr_id'),
                null,
                Participants::PARTICIPATION_YES
            );
        } elseif ($command === 'event:maybe') {
            if (!$event->possibleToParticipate() && !$participants->isLeader($gCurrentUserId)) {
                throw new Exception('SYS_PARTICIPATE_NO_RIGHTS');
            }
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
        $event = self::resolveEvent(CliApplication::requireArgument($arguments, 0, 'event'));
        if (!$event->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ((int)$event->getValue('dat_rol_id') === 0) {
            CliApplication::writeRows(array(), CliApplication::optionString($options, 'format', 'table'), $options);
            return 0;
        }
        $participants = new Participants($GLOBALS['gDb'], (int)$event->getValue('dat_rol_id'));
        $rows = array();
        foreach ($participants->getParticipantsArray() as $participant) {
            $rows[] = $participant;
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function roomList(array $arguments, array $options): int
    {
        $rows = $GLOBALS['gDb']->queryPrepared(
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
        $room = new Room($GLOBALS['gDb']);
        $room->setValue('room_name', CliApplication::requireArgument($arguments, 0, 'name'));
        self::applyRoomOptions($room, $options);
        $room->save();
        CliApplication::writeValue(array('id' => (int)$room->getValue('room_id'), 'uuid' => (string)$room->getValue('room_uuid')), $options);
        return 0;
    }

    public static function roomUpdate(array $arguments, array $options): int
    {
        $room = self::resolveRoom(CliApplication::requireArgument($arguments, 0, 'room'));
        if (array_key_exists('name', $options)) {
            $room->setValue('room_name', CliApplication::optionString($options, 'name'));
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
            throw new InvalidArgumentException('The room is used by one or more events.');
        }
        CliApplication::confirm('Delete room "' . $room->getValue('room_name') . '"?', $options);
        $room->delete();
        CliApplication::writeSuccess('Room deleted.', $options);
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
        $all = $gDb->queryPrepared(
            'SELECT fot.fot_id AS id, fot.fot_uuid AS uuid, fot.fot_title AS title,
                    cat.cat_name AS category, fot.fot_views AS views, fot.fot_timestamp_create AS created_at
               FROM ' . TBL_FORUM_TOPICS . ' fot
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = fot.fot_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY fot.fot_timestamp_create DESC',
            $params
        )->fetchAll();
        $rows = array();
        foreach ($all as $row) {
            $topic = new Topic($gDb, (int)$row['id']);
            if ($topic->isVisible()) {
                $rows[] = $row;
            }
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
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
        $topic = new Topic($GLOBALS['gDb']);
        $category = self::resolveCategory(CliApplication::optionString($options, 'category'), 'FOT');
        $topic->setValue('fot_cat_id', (int)$category->getValue('cat_id'));
        $topic->setValue('fot_title', CliApplication::optionString($options, 'title'));
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
            $topic->setValue('fot_title', CliApplication::optionString($options, 'title'));
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
        $topic = self::resolveTopic(CliApplication::requireArgument($arguments, 0, 'topic'));
        if (!$topic->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $post = new Post($GLOBALS['gDb']);
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
        global $gCurrentUser;
        $post = self::resolvePost(CliApplication::requireArgument($arguments, 0, 'post'));
        $topic = new Topic($GLOBALS['gDb'], (int)$post->getValue('fop_fot_id'));
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
        global $gCurrentUser;
        $post = self::resolvePost(CliApplication::requireArgument($arguments, 0, 'post'));
        $topic = new Topic($GLOBALS['gDb'], (int)$post->getValue('fop_fot_id'));
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
        $all = $gDb->queryPrepared(
            'SELECT lnk.lnk_id AS id, lnk.lnk_uuid AS uuid, lnk.lnk_name AS name,
                    lnk.lnk_url AS url, lnk.lnk_sequence AS sequence, cat.cat_name AS category
               FROM ' . TBL_LINKS . ' lnk
         INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = lnk.lnk_cat_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY cat.cat_sequence, lnk.lnk_sequence',
            $params
        )->fetchAll();
        $rows = array();
        foreach ($all as $row) {
            $link = new Weblink($gDb, (int)$row['id']);
            if ($link->isVisible()) {
                $rows[] = $row;
            }
        }
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
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
        $link = new Weblink($GLOBALS['gDb']);
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

        CliApplication::writeValue(array(
            'id' => (int)$message->getValue('msg_id'),
            'uuid' => (string)$message->getValue('msg_uuid'),
            'type' => (string)$message->getValue('msg_type'),
            'subject' => (string)$message->getValue('msg_subject'),
            'sender_id' => (int)$message->getValue('msg_usr_id_sender'),
            'timestamp' => (string)$message->getValue('msg_timestamp', 'Y-m-d H:i:s'),
            'read_state' => (int)$message->getValue('msg_read'),
            'recipients' => $message->getRecipientsNamesString(),
            'content' => $message->getContent('database'),
            'attachments' => $message->getAttachmentsInformation()
        ), $options);
        return 0;
    }

    public static function messageDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete the selected message record(s)?', $options);
        foreach ($arguments as $reference) {
            $message = self::resolveMessage($reference);
            self::assertMessageAccess($message);
            $message->delete();
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
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $folder = new Folder($GLOBALS['gDb'], (int)$file->getValue('fil_fol_id'));
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
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $source = new Folder($GLOBALS['gDb'], (int)$file->getValue('fil_fol_id'));
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
        $file = self::resolveDocumentFile(CliApplication::requireArgument($arguments, 0, 'file'));
        $folder = new Folder($GLOBALS['gDb'], (int)$file->getValue('fil_fol_id'));
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
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$GLOBALS['gCurrentUser']->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $recursive = CliApplication::optionBool($options, 'recursive', false) ?? false;

        if (CliApplication::optionExists($options, 'view-role')) {
            $roleIds = self::resolveRoleIds(CliApplication::optionValues($options, 'view-role'));
            $current = $folder->getViewRolesIds();
            $folder->removeRolesOnFolder('folder_view', array_values(array_diff($current, $roleIds)), $recursive);
            $folder->addRolesOnFolder('folder_view', array_values(array_diff($roleIds, $current)), $recursive);
        }
        if (CliApplication::optionExists($options, 'upload-role')) {
            $roleIds = self::resolveRoleIds(CliApplication::optionValues($options, 'upload-role'));
            $current = $folder->getUploadRolesIds();
            $folder->removeRolesOnFolder('folder_upload', array_values(array_diff($current, $roleIds)), $recursive);
            $folder->addRolesOnFolder('folder_upload', array_values(array_diff($roleIds, $current)), $recursive);
        }
        if (CliApplication::optionExists($options, 'public')) {
            if ($recursive) {
                $folder->editPublicFlagOnFolder(CliApplication::optionBool($options, 'public', false) ?? false);
            } else {
                $folder->setValue('fol_public', (int)(CliApplication::optionBool($options, 'public', false) ?? false));
            }
            $folder->save();
        }
        CliApplication::writeSuccess('Folder permissions updated.', $options);
        return 0;
    }

    public static function documentUnregistered(array $arguments, array $options): int
    {
        $folder = self::resolveFolder($arguments[0] ?? '');
        if (!$GLOBALS['gCurrentUser']->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $rows = self::getUnregisteredEntries($folder, false);
        CliApplication::writeRows(
            $rows,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function documentRegister(array $arguments, array $options): int
    {
        $folder = self::resolveFolder(CliApplication::requireArgument($arguments, 0, 'folder'));
        if (!$GLOBALS['gCurrentUser']->isAdministratorDocumentsFiles()) {
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
        global $gDb, $gCurrentOrgId;

        $params = array($gCurrentOrgId);
        $parentSql = '';
        if (CliApplication::optionExists($options, 'parent')) {
            $parent = self::resolveAlbum(CliApplication::optionString($options, 'parent'));
            $parentSql = ' AND pho_pho_id_parent = ?';
            $params[] = (int)$parent->getValue('pho_id');
        }

        $rows = array();
        $statement = $gDb->queryPrepared(
            'SELECT pho_id, pho_uuid, pho_name, pho_begin, pho_end, pho_quantity,
                    pho_locked, pho_pho_id_parent
               FROM ' . TBL_PHOTOS . '
              WHERE pho_org_id = ?' . $parentSql . '
           ORDER BY pho_begin DESC, pho_name',
            $params
        );
        while ($row = $statement->fetch()) {
            $album = new Album($gDb, (int)$row['pho_id']);
            if ($album->isVisible()) {
                $rows[] = array(
                    'id' => $row['pho_id'],
                    'uuid' => $row['pho_uuid'],
                    'name' => $row['pho_name'],
                    'begin' => $row['pho_begin'],
                    'end' => $row['pho_end'],
                    'quantity' => $row['pho_quantity'],
                    'locked' => $row['pho_locked'],
                    'parent_id' => $row['pho_pho_id_parent']
                );
            }
        }
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
        $search = mb_strtolower(CliApplication::optionString($options, 'search'));
        $rows = array();

        foreach ($items->getItems() as $itemRow) {
            if ($categoryId !== null && (int)$itemRow['ini_cat_id'] !== $categoryId) {
                continue;
            }
            $items->readItemData((string)$itemRow['ini_uuid']);
            if ($statusFilter === 'retired' && !$items->isRetired()) {
                continue;
            }
            if ($statusFilter === 'active' && $items->isRetired()) {
                continue;
            }

            $row = array(
                'id' => (int)$itemRow['ini_id'],
                'uuid' => (string)$itemRow['ini_uuid'],
                'category_id' => (int)$itemRow['ini_cat_id'],
                'status_id' => (int)$itemRow['ini_status']
            );
            foreach ($items->getItemFields() as $name => $field) {
                $row[(string)$name] = $items->getValue((string)$name, 'database');
            }
            if ($search !== '' && !str_contains(mb_strtolower(implode(' ', array_map('strval', $row))), $search)) {
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

    public static function inventoryDelete(array $arguments, array $options): int
    {
        CliApplication::confirm('Delete the selected inventory item(s)?', $options);
        foreach ($arguments as $reference) {
            $uuid = self::resolveItemUuid($reference);
            (new ItemService($GLOBALS['gDb'], $uuid))->delete();
        }
        CliApplication::writeSuccess('Inventory item(s) deleted.', $options);
        return 0;
    }

    public static function inventoryRetire(array $arguments, array $options): int
    {
        foreach ($arguments as $reference) {
            (new ItemService($GLOBALS['gDb'], self::resolveItemUuid($reference)))->retireItem();
        }
        CliApplication::writeSuccess('Inventory item(s) retired.', $options);
        return 0;
    }

    public static function inventoryReinstate(array $arguments, array $options): int
    {
        foreach ($arguments as $reference) {
            (new ItemService($GLOBALS['gDb'], self::resolveItemUuid($reference)))->reinstateItem();
        }
        CliApplication::writeSuccess('Inventory item(s) reinstated.', $options);
        return 0;
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

    public static function inventoryFieldDelete(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        CliApplication::confirm('Delete inventory field "' . $field->getValue('inf_name') . '"?', $options);
        (new ItemFieldService($GLOBALS['gDb'], (string)$field->getValue('inf_uuid')))->delete();
        CliApplication::writeSuccess('Inventory field deleted.', $options);
        return 0;
    }

    public static function inventoryFieldMove(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $direction = self::direction(CliApplication::requireArgument($arguments, 1, 'direction'));
        (new ItemFieldService($GLOBALS['gDb'], (string)$field->getValue('inf_uuid')))->moveSequence(
            $direction === 'up' ? ItemFieldService::MOVE_UP : ItemFieldService::MOVE_DOWN
        );
        CliApplication::writeSuccess('Inventory field moved.', $options);
        return 0;
    }

    public static function inventoryOptions(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $select = new InventorySelectOptions($GLOBALS['gDb'], (int)$field->getValue('inf_id'));
        $rows = array_values($select->getAllOptions(
            CliApplication::optionBool($options, 'include-obsolete', false) ?? false
        ));
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function inventoryOptionAdd(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $value = CliApplication::requireArgument($arguments, 1, 'value');
        $select = new InventorySelectOptions($GLOBALS['gDb'], (int)$field->getValue('inf_id'));
        $select->setOptionValues(array(0 => array('value' => $value, 'obsolete' => false)));
        CliApplication::writeSuccess('Inventory select option added.', $options);
        return 0;
    }

    public static function inventoryOptionUpdate(array $arguments, array $options): int
    {
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new InventorySelectOptions($GLOBALS['gDb'], (int)$field->getValue('inf_id'));
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
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new InventorySelectOptions($GLOBALS['gDb'], (int)$field->getValue('inf_id'));
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
        $field = self::resolveInventoryField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $direction = self::direction(CliApplication::requireArgument($arguments, 2, 'direction'));
        self::moveSelectOption(
            new InventorySelectOptions($GLOBALS['gDb'], (int)$field->getValue('inf_id')),
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
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $select = new ProfileSelectOptions($GLOBALS['gDb'], (int)$field->getValue('usf_id'));
        $rows = array_values($select->getAllOptions(
            CliApplication::optionBool($options, 'include-obsolete', false) ?? false
        ));
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function profileOptionAdd(array $arguments, array $options): int
    {
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $value = CliApplication::requireArgument($arguments, 1, 'value');
        $select = new ProfileSelectOptions($GLOBALS['gDb'], (int)$field->getValue('usf_id'));
        $select->setOptionValues(array(0 => array('value' => $value, 'obsolete' => false)));
        CliApplication::writeSuccess('Profile select option added.', $options);
        return 0;
    }

    public static function profileOptionUpdate(array $arguments, array $options): int
    {
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new ProfileSelectOptions($GLOBALS['gDb'], (int)$field->getValue('usf_id'));
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
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $select = new ProfileSelectOptions($GLOBALS['gDb'], (int)$field->getValue('usf_id'));
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
        $field = self::resolveProfileField(CliApplication::requireArgument($arguments, 0, 'field'));
        $optionId = self::positiveInt(CliApplication::requireArgument($arguments, 1, 'option'), 'option');
        $direction = self::direction(CliApplication::requireArgument($arguments, 2, 'direction'));
        self::moveSelectOption(
            new ProfileSelectOptions($GLOBALS['gDb'], (int)$field->getValue('usf_id')),
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

    public static function changelogList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentUser;

        $permittedTables = array_values(array_unique(ChangelogService::getPermittedTables($gCurrentUser)));
        if ($gCurrentUser->isAdministrator()) {
            // A full administrator may inspect every table that is currently configured for logging.
            $allTables = $gDb->queryPrepared(
                'SELECT DISTINCT log_table
                   FROM ' . TBL_LOG_CHANGES . '
               ORDER BY log_table'
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allTables as $table) {
                if (ChangelogService::hasLogViewPermission((string)$table, $gCurrentUser)) {
                    $permittedTables[] = (string)$table;
                }
            }
            $permittedTables = array_values(array_unique($permittedTables));
        } else {
            $permittedTables = array_values(array_filter(
                $permittedTables,
                static fn (string $table): bool => ChangelogService::hasLogViewPermission($table, $gCurrentUser)
            ));
        }

        if ($permittedTables === array()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $requestedTables = CliApplication::optionValues($options, 'table');
        if ($requestedTables !== array()) {
            foreach ($requestedTables as $table) {
                if (!in_array($table, $permittedTables, true)) {
                    throw new Exception('SYS_NO_RIGHTS');
                }
            }
            $tables = $requestedTables;
        } else {
            $tables = $permittedTables;
        }

        $conditions = array();
        $params = array();
        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $conditions[] = 'log_table IN (' . $placeholders . ')';
        array_push($params, ...$tables);

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
                  FROM ' . TBL_LOG_CHANGES . '
                 WHERE ' . implode(' AND ', $conditions) . '
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

    public static function requirementsList(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $params = array($gCurrentOrgId);
        $searchSql = '';
        $query = CliApplication::optionString($options, 'query');
        if ($query !== '') {
            $searchSql = ' AND (LOWER(rqp_name) LIKE LOWER(?)
                              OR LOWER(COALESCE(rqp_address, \'\')) LIKE LOWER(?)
                              OR LOWER(COALESCE(rqp_url, \'\')) LIKE LOWER(?)
                              OR LOWER(COALESCE(rqp_description, \'\')) LIKE LOWER(?))';
            $term = '%' . $query . '%';
            array_push($params, $term, $term, $term, $term);
        }

        $rows = $gDb->queryPrepared(
            'SELECT rqp_id AS id, rqp_uuid AS uuid, rqp_name AS name,
                    rqp_address AS address, rqp_url AS url,
                    rqp_qualified AS qualified, rqp_public AS public,
                    rqp_editable AS editable, rqp_usr_id_create AS created_by
               FROM ' . TBL_REQ_PROVIDERS . '
              WHERE rqp_org_id = ?' . $searchSql . '
           ORDER BY rqp_name, rqp_id',
            $params
        )->fetchAll();

        $visible = array();
        foreach ($rows as $row) {
            $provider = new Provider($gDb, (int)$row['id']);
            if ($provider->isVisible()) {
                $visible[] = $row;
            }
        }

        CliApplication::writeRows(
            $visible,
            CliApplication::optionString($options, 'format', 'table'),
            $options
        );
        return 0;
    }

    public static function requirementsShow(array $arguments, array $options): int
    {
        $provider = self::resolveProvider(CliApplication::requireArgument($arguments, 0, 'provider'));
        if (!$provider->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::writeValue(self::providerData($provider), $options);
        return 0;
    }

    public static function requirementsAdd(array $arguments, array $options): int
    {
        global $gDb, $gCurrentOrgId;

        $provider = new Provider($gDb);
        $provider->setValue('rqp_org_id', $gCurrentOrgId);
        self::applyProviderOptions($provider, $options, true);
        $provider->save();

        CliApplication::writeValue(self::providerData($provider), $options);
        return 0;
    }

    public static function requirementsUpdate(array $arguments, array $options): int
    {
        $provider = self::resolveProvider(CliApplication::requireArgument($arguments, 0, 'provider'));
        if (!$provider->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        self::applyProviderOptions($provider, $options, false);
        $provider->save();

        CliApplication::writeSuccess('Requirements provider updated.', $options);
        return 0;
    }

    public static function requirementsDelete(array $arguments, array $options): int
    {
        $provider = self::resolveProvider(CliApplication::requireArgument($arguments, 0, 'provider'));
        if (!$provider->isDeletable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        CliApplication::confirm('Delete provider "' . $provider->getValue('rqp_name') . '"?', $options);
        $provider->delete();
        CliApplication::writeSuccess('Requirements provider deleted.', $options);
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

    public static function ssoKeyDelete(array $arguments, array $options): int
    {
        $key = self::resolveSsoKey(CliApplication::requireArgument($arguments, 0, 'key'));
        CliApplication::confirm('Delete SSO key "' . $key->getValue('key_name') . '"?', $options);
        $key->delete();
        CliApplication::writeSuccess('SSO key deleted.', $options);
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

    public static function unavailable(array $arguments, array $options): int
    {
        throw new RuntimeException(
            'This command is registered as unavailable in the current Admidio master.'
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

    private static function validateDate(string $date, string $label): void
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
    private static function splitAssignment(string $assignment, string $label): array
    {
        $position = strpos($assignment, '=');
        if ($position === false || $position === 0) {
            throw new InvalidArgumentException($label . ' expects NAME=VALUE.');
        }
        return array(substr($assignment, 0, $position), substr($assignment, $position + 1));
    }

    private static function reloadUserSessions(int $userId): void
    {
        $GLOBALS['gDb']->queryPrepared(
            'UPDATE ' . TBL_SESSIONS . ' SET ses_reload = true WHERE ses_usr_id = ?',
            array($userId)
        );
    }

    private static function reloadAllSessions(): void
    {
        $GLOBALS['gDb']->queryPrepared('UPDATE ' . TBL_SESSIONS . ' SET ses_reload = true');
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
                  WHERE org_uuid = ? OR org_shortname = ?',
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
                  WHERE urt_uuid = ? OR urt_name = ?',
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
            ? CliApplication::optionString($options, 'name')
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
            ? CliApplication::optionString($options, 'inverse-name')
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
                  WHERE (rol_uuid = ? OR rol_name = ?)
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

    private static function resolveMembershipForDate(Role $role, User $user, string $date): Membership
    {
        global $gDb;

        self::validateDate($date, 'membership date');
        $ids = $gDb->queryPrepared(
            'SELECT mem_id
               FROM ' . TBL_MEMBERS . '
              WHERE mem_rol_id = ?
                AND mem_usr_id = ?
                AND ? BETWEEN mem_begin AND mem_end
           ORDER BY mem_begin DESC, mem_id DESC',
            array((int)$role->getValue('rol_id'), (int)$user->getValue('usr_id'), $date)
        )->fetchAll(PDO::FETCH_COLUMN);

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
        return $GLOBALS['gDb']->queryPrepared(
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
            $where = '(cat_uuid = ? OR cat_name = ?)';
            array_push($params, $reference, $reference);
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

        $sequence = 0;
        $category = new Category($gDb);
        while ($row = $statement->fetch()) {
            ++$sequence;
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
        $id = CliApplication::resolveId(TBL_MENU, 'men_id', 'men_uuid', $reference, 'menu entry');
        return new MenuEntry($GLOBALS['gDb'], $id);
    }

    private static function applyMenuOptions(MenuEntry $menu, array $options, bool $new): void
    {
        foreach (array(
            'name' => 'men_name',
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
                $id = (int)$GLOBALS['gDb']->queryPrepared(
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
        if (!CliApplication::optionExists($options, 'view-role')) {
            return;
        }
        (new RolesRights($GLOBALS['gDb'], 'menu_view', (int)$menu->getValue('men_id')))
            ->saveRoles(self::resolveRoleIds(CliApplication::optionValues($options, 'view-role')));
        self::reloadAllSessions();
    }

    private static function resolveAnnouncement(string $reference): Announcement
    {
        $id = CliApplication::resolveId(
            TBL_ANNOUNCEMENTS,
            'ann_id',
            'ann_uuid',
            $reference,
            'announcement'
        );
        return new Announcement($GLOBALS['gDb'], $id);
    }

    private static function applyAnnouncementOptions(Announcement $announcement, array $options, bool $new): void
    {
        if (CliApplication::optionExists($options, 'headline')) {
            $announcement->setValue('ann_headline', CliApplication::optionString($options, 'headline'));
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

    private static function resolveEvent(string $reference): Event
    {
        $id = CliApplication::resolveId(TBL_EVENTS, 'dat_id', 'dat_uuid', $reference, 'event');
        return new Event($GLOBALS['gDb'], $id);
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
        if (CliApplication::optionExists($options, 'name')) {
            $room->setValue('room_name', CliApplication::optionString($options, 'name'));
        }
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

    private static function resolveTopic(string $reference): Topic
    {
        $id = CliApplication::resolveId(TBL_FORUM_TOPICS, 'fot_id', 'fot_uuid', $reference, 'forum topic');
        return new Topic($GLOBALS['gDb'], $id);
    }

    private static function resolvePost(string $reference): Post
    {
        $id = CliApplication::resolveId(TBL_FORUM_POSTS, 'fop_id', 'fop_uuid', $reference, 'forum post');
        return new Post($GLOBALS['gDb'], $id);
    }

    private static function resolveLink(string $reference): Weblink
    {
        $id = CliApplication::resolveId(TBL_LINKS, 'lnk_id', 'lnk_uuid', $reference, 'web link');
        return new Weblink($GLOBALS['gDb'], $id);
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
        $id = CliApplication::resolveId(TBL_MESSAGES, 'msg_id', 'msg_uuid', $reference, 'message');
        return new Message($GLOBALS['gDb'], $id);
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
        $folder = new Folder($GLOBALS['gDb']);
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
                $subfolder = new Folder($GLOBALS['gDb'], (int)$subfolderData['fol_id']);
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
        $contents = array(
            'folders' => $folder->getSubfoldersWithProperties(),
            'files' => $folder->getFilesWithProperties()
        );
        $contents = $folder->addAdditionalToFolderContents($contents);

        $rows = array();
        foreach ($contents['folders'] ?? array() as $entry) {
            if (($entry['fol_id'] ?? 0) === 0 || ($entry['fol_uuid'] ?? '') === '') {
                $rows[] = array(
                    'type' => 'folder',
                    'name' => $entry['fol_name'] ?? $entry['name'] ?? '',
                    'path' => $entry['fol_path'] ?? $folder->getFolderPath()
                );
            }
        }
        foreach ($contents['files'] ?? array() as $entry) {
            if (($entry['fil_id'] ?? 0) === 0 || ($entry['fil_uuid'] ?? '') === '') {
                $rows[] = array(
                    'type' => 'file',
                    'name' => $entry['fil_name'] ?? $entry['name'] ?? '',
                    'path' => $folder->getFolderPath()
                );
            }
        }
        return $rows;
    }

    private static function registerUnregisteredRecursive(Folder $folder, bool $recursive): void
    {
        foreach (self::getUnregisteredEntries($folder) as $entry) {
            $name = (string)$entry['name'];
            if ($name === '') {
                continue;
            }
            $folder->addFolderOrFileToDatabase($name);
        }

        if ($recursive) {
            foreach ($folder->getSubfoldersWithProperties() as $subfolderData) {
                $subfolder = new Folder($GLOBALS['gDb'], (int)$subfolderData['fol_id']);
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
        $data = array(
            'id' => $itemData->getItemId(),
            'uuid' => (string)$GLOBALS['gDb']->queryPrepared(
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
                'SELECT inf_id FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_id = ? AND inf_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT inf_id
                   FROM ' . TBL_INVENTORY_FIELDS . '
                  WHERE inf_org_id = ? AND (inf_uuid = ? OR inf_name_intern = ?)',
                array($gCurrentOrgId, $reference, strtoupper($reference))
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

    private static function resolveProvider(string $reference): Provider
    {
        global $gDb, $gCurrentOrgId;

        if (ctype_digit($reference)) {
            $rows = $gDb->queryPrepared(
                'SELECT rqp_id FROM ' . TBL_REQ_PROVIDERS . ' WHERE rqp_id = ? AND rqp_org_id = ?',
                array((int)$reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $gDb->queryPrepared(
                'SELECT rqp_id FROM ' . TBL_REQ_PROVIDERS . ' WHERE rqp_uuid = ? AND rqp_org_id = ?',
                array($reference, $gCurrentOrgId)
            )->fetchAll(PDO::FETCH_COLUMN);
        }
        if (count($rows) !== 1) {
            throw new InvalidArgumentException('Requirements provider was not found.');
        }
        return new Provider($gDb, (int)$rows[0]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function providerData(Provider $provider): array
    {
        return array(
            'id' => (int)$provider->getValue('rqp_id'),
            'uuid' => (string)$provider->getValue('rqp_uuid'),
            'organization_id' => (int)$provider->getValue('rqp_org_id'),
            'name' => $provider->getValue('rqp_name', 'database'),
            'address' => $provider->getValue('rqp_address', 'database'),
            'url' => $provider->getValue('rqp_url', 'database'),
            'description' => $provider->getValue('rqp_description', 'database'),
            'qualified' => (bool)$provider->getValue('rqp_qualified'),
            'public' => (bool)$provider->getValue('rqp_public'),
            'editable' => (bool)$provider->getValue('rqp_editable'),
            'created_by' => (int)$provider->getValue('rqp_usr_id_create')
        );
    }

    private static function applyProviderOptions(Provider $provider, array $options, bool $new): void
    {
        if (CliApplication::optionExists($options, 'name')) {
            $provider->setValue('rqp_name', CliApplication::optionString($options, 'name'));
        } elseif ($new) {
            throw new InvalidArgumentException('--name is required.');
        }

        foreach (array(
            'address' => 'rqp_address',
            'url' => 'rqp_url',
            'description' => 'rqp_description'
        ) as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                $provider->setValue($column, CliApplication::optionString($options, $option));
            }
        }

        $mayChangeVisibility = $new || $provider->canChangeVisibilityFlags();
        foreach (array('public' => 'rqp_public', 'editable' => 'rqp_editable') as $option => $column) {
            if (CliApplication::optionExists($options, $option)) {
                if (!$mayChangeVisibility) {
                    throw new Exception('SYS_NO_RIGHTS');
                }
                $provider->setValue($column, CliApplication::optionBool($options, $option, false) ?? false);
            }
        }

        if (CliApplication::optionExists($options, 'qualified')) {
            if (!Component::isAdministrable('REQUIREMENTS')) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $provider->setValue('rqp_qualified', CliApplication::optionBool($options, 'qualified', false) ?? false);
        } elseif ($new) {
            $provider->setValue('rqp_qualified', false);
        }
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


    private static function changePermissions(string $mode, array $arguments, array $options): int
    {
        $right = CliApplication::requireArgument($arguments, 0, 'right-type');
        $objectId = self::positiveInt(
            CliApplication::requireArgument($arguments, 1, 'object-id'),
            'object-id'
        );
        $rights = new RolesRights($GLOBALS['gDb'], $right, $objectId);
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
