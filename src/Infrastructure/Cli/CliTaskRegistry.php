<?php
namespace Admidio\Infrastructure\Cli;

use InvalidArgumentException;

/**
 ***********************************************************************************************
 * Registry for Admidio command-line tasks.
 *
 * Core commands and module-specific tasks use the same registry. Core commands are registered
 * first and therefore reserve their names. Modules may add new module:task commands but cannot
 * replace commands that have already been registered.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliTaskRegistry
{
    /**
     * The acting user must administer the component. This is the default and applies to every
     * command that changes configuration or data of a module.
     */
    public const ACCESS_ADMINISTRABLE = 'administrable';

    /**
     * The component only has to be visible to the acting user. Used by read-only commands and by
     * commands whose service implements the real rights model itself. Such a command must perform
     * the same record-level check as its web counterpart, for example Entity::isVisible() or
     * User::hasRightViewProfile().
     */
    public const ACCESS_VISIBLE = 'visible';

    /**
     * Rights a command may require in addition to its component, mapped to the User method that
     * answers them. The component alone cannot express these: relation-type:add for example needs
     * the contacts module *and* a full administrator, while isAdministrable('CONTACTS') is
     * satisfied by a contacts administrator.
     *
     * @var array<string,string>
     */
    public const ADDITIONAL_RIGHTS = array(
        'administrator' => 'isAdministrator',
        'usersAdministrator' => 'isAdministratorUsers'
    );

    /**
     * @var array<string,array{
     *     name:string,
     *     component:?string,
     *     componentAccess:string,
     *     aliasOf:?string,
     *     requiredRight:?string,
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
     *     supportsDryRun:bool,
     *     core:bool
     * }>
     */
    private static array $tasks = array();

    /**
     * Name-sorted copy of $tasks, built on first use and dropped whenever a task is registered.
     *
     * @var array<string,array<string,mixed>>|null
     */
    private static ?array $sortedTasks = null;

    /**
     * The jobs of maintenance:run, in registration order: the core jobs first, then those of the
     * modules and plugins.
     *
     * @var array<string,array{name:string,callback:callable,description:string,core:bool}>
     */
    private static array $maintenanceJobs = array();

    /**
     * Core namespaces cannot be extended or shadowed by module registration.
     *
     * @var array<string,bool>
     */
    private static array $coreNamespaces = array();

    /**
     * Directory name of the module or plugin whose registrations are currently being loaded, or
     * null outside of that.
     *
     * @var string|null
     */
    private static ?string $moduleContext = null;

    /**
     * Whether the current context is a module or a plugin. Both own the command namespace named
     * after their directory; the kind only appears in the error message.
     *
     * @var string
     */
    private static string $moduleContextKind = 'Module';

    /**
     * Announce which module's cli.php is being loaded, so its registrations can be restricted to
     * its own command namespace.
     */
    public static function setModuleContext(?string $module): void
    {
        self::$moduleContext = $module;
        self::$moduleContextKind = 'Module';
    }

    /**
     * Announce which plugin's entry file is being loaded. A plugin owns the command namespace named
     * after its directory, exactly like a module.
     */
    public static function setPluginContext(?string $plugin): void
    {
        self::$moduleContext = $plugin;
        self::$moduleContextKind = 'Plugin';
    }

    /**
     * Register an Admidio core command.
     *
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    public static function registerCore(
        string $taskName,
        callable $callback,
        string $description,
        string $usage = '',
        ?string $componentName = null,
        bool $actorRequired = false,
        array $arguments = array(),
        array $options = array(),
        array $examples = array(),
        ?string $unavailableReason = null,
        string $componentAccess = self::ACCESS_ADMINISTRABLE,
        ?string $aliasOf = null,
        ?string $requiredRight = null,
        bool $supportsDryRun = false
    ): void {
        self::registerTask(
            $taskName,
            $componentName,
            $componentAccess,
            $aliasOf,
            $requiredRight,
            $actorRequired,
            $callback,
            $description,
            $usage,
            $arguments,
            $options,
            $examples,
            $unavailableReason,
            $supportsDryRun,
            true
        );
    }

    /**
     * Register a module- or plugin-specific command.
     *
     * A module provides modules/<module>/cli.php and a plugin provides plugins/<plugin>/cli.php.
     * Each may only register commands of its own namespace, derived from its directory name, and not
     * one that Admidio core already uses. An acting user is always
     * required, and before the callback runs the component is checked through
     * Component::isAdministrable(), or through Component::isVisible() when the command declares
     * ACCESS_VISIBLE.
     *
     * A failure while loading the file is reported but does not stop the other commands.
     *
     * **Example** modules/example/cli.php
     * ```
     * use Admidio\Infrastructure\Cli\CliApplication;
     * use Admidio\Infrastructure\Cli\CliTaskRegistry;
     *
     * CliTaskRegistry::register(
     *     'example:greet',
     *     'CORE',
     *     function (array $arguments, array $options): int {
     *         CliApplication::writeSuccess(
     *             'Hello ' . CliApplication::requireArgument($arguments, 0, 'name') . '.',
     *             $options
     *         );
     *         return CliApplication::EXIT_SUCCESS;
     *     },
     *     'Greet somebody.',
     *     'example:greet NAME',
     *     array(array('name' => 'name', 'description' => 'Who to greet.', 'required' => true))
     * );
     * ```
     *
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    public static function register(
        string $taskName,
        string $componentName,
        callable $callback,
        string $description = '',
        string $usage = '',
        array $arguments = array(),
        array $options = array(),
        array $examples = array(),
        string $componentAccess = self::ACCESS_ADMINISTRABLE,
        bool $supportsDryRun = false
    ): void {
        if (!str_contains($taskName, ':')) {
            throw new InvalidArgumentException('Extension CLI task names must use the form extension:task.');
        }

        if ($componentName === '') {
            throw new InvalidArgumentException('An extension CLI task must specify an Admidio component.');
        }

        self::registerTask(
            $taskName,
            $componentName,
            $componentAccess,
            null,
            null,
            true,
            $callback,
            $description,
            $usage,
            $arguments,
            $options,
            $examples,
            null,
            $supportsDryRun,
            false
        );
    }

    /**
     * Register a maintenance job of Admidio core.
     */
    public static function registerCoreMaintenanceJob(string $jobName, callable $callback, string $description): void
    {
        self::registerJob($jobName, $callback, $description, true);
    }

    /**
     * Register a maintenance job of a module or plugin. `admidio maintenance:run` runs every
     * registered job, so one crontab entry covers core, modules and plugins.
     *
     * A module provides its jobs in modules/<module>/cli.php and a plugin in plugins/<plugin>/cli.php,
     * next to its commands. The name uses the namespace of the module or plugin, like a command. The
     * job runs in the organization and as the acting user of maintenance:run. It is run every time
     * maintenance:run runs - typically hourly - and has to decide itself whether there is anything
     * to do, cheaply when there is not. It may return a short message for the result table and
     * throws when it fails; the other jobs run anyway.
     *
     * **Example** plugins/example/cli.php
     * ```
     * CliTaskRegistry::registerMaintenanceJob(
     *     'example:cleanup',
     *     static function (): ?string {
     *         return ExampleCleanup::runIfDue() . ' entries removed';
     *     },
     *     'Remove expired example entries.'
     * );
     * ```
     *
     * @param callable(): (string|null|void) $callback
     */
    public static function registerMaintenanceJob(string $jobName, callable $callback, string $description): void
    {
        if (!str_contains($jobName, ':')) {
            throw new InvalidArgumentException('Extension maintenance jobs must use the form extension:job.');
        }

        self::registerJob($jobName, $callback, $description, false);
    }

    /**
     * The registered maintenance jobs, in the order in which maintenance:run executes them.
     *
     * @return array<string,array{name:string,callback:callable,description:string,core:bool}>
     */
    public static function getMaintenanceJobs(): array
    {
        return self::$maintenanceJobs;
    }

    private static function registerJob(string $jobName, callable $callback, string $description, bool $core): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)?$/', $jobName)) {
            throw new InvalidArgumentException(
                'Maintenance job names must use lowercase letters, digits and hyphens with at most one namespace separator.'
            );
        }

        if (!$core) {
            self::assertOwnNamespace($jobName, 'maintenance jobs');
        }

        if (isset(self::$maintenanceJobs[$jobName])) {
            throw new InvalidArgumentException('Maintenance job "' . $jobName . '" is already registered.');
        }

        self::$maintenanceJobs[$jobName] = array(
            'name' => $jobName,
            'callback' => $callback,
            'description' => trim($description),
            'core' => $core
        );
    }

    /**
     * @return array{
     *     name:string,
     *     component:?string,
     *     componentAccess:string,
     *     aliasOf:?string,
     *     requiredRight:?string,
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
     *     supportsDryRun:bool,
     *     core:bool
     * }|null
     */
    public static function get(string $taskName): ?array
    {
        return self::$tasks[$taskName] ?? null;
    }

    /**
     * @return array<string,array{
     *     name:string,
     *     component:?string,
     *     componentAccess:string,
     *     aliasOf:?string,
     *     requiredRight:?string,
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
     *     supportsDryRun:bool,
     *     core:bool
     * }>
     */
    public static function getAll(): array
    {
        if (self::$sortedTasks === null) {
            self::$sortedTasks = self::$tasks;
            ksort(self::$sortedTasks);
        }

        return self::$sortedTasks;
    }

    /**
     * Return command metadata without callbacks. This is suitable for JSON help output.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function getDocumentation(): array
    {
        $documentation = array();

        foreach (self::getAll() as $name => $task) {
            unset($task['callback']);
            $documentation[$name] = $task;
        }

        return $documentation;
    }

    /**
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    private static function registerTask(
        string $taskName,
        ?string $componentName,
        string $componentAccess,
        ?string $aliasOf,
        ?string $requiredRight,
        bool $actorRequired,
        callable $callback,
        string $description,
        string $usage,
        array $arguments,
        array $options,
        array $examples,
        ?string $unavailableReason,
        bool $supportsDryRun,
        bool $core
    ): void {
        if (!preg_match('/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)?$/', $taskName)) {
            throw new InvalidArgumentException(
                'CLI command names must use lowercase letters, digits and hyphens with at most one namespace separator.'
            );
        }

        if (!$core && !str_contains($taskName, ':')) {
            throw new InvalidArgumentException('Extension CLI task names must use the form extension:task.');
        }

        if (!in_array($componentAccess, array(self::ACCESS_ADMINISTRABLE, self::ACCESS_VISIBLE), true)) {
            throw new InvalidArgumentException(
                'CLI command "' . $taskName . '" declares an unknown component access level.'
            );
        }

        if (str_contains($taskName, ':')) {
            $namespace = strstr($taskName, ':', true);

            if (!$core) {
                self::assertOwnNamespace($taskName, 'commands');
            }

            if ($core) {
                self::$coreNamespaces[$namespace] = true;
            }
        }

        if ($requiredRight !== null && !isset(self::ADDITIONAL_RIGHTS[$requiredRight])) {
            throw new InvalidArgumentException(
                'CLI command "' . $taskName . '" requires the unknown right "' . $requiredRight . '".'
            );
        }

        if ($aliasOf !== null && !isset(self::$tasks[$aliasOf])) {
            throw new InvalidArgumentException(
                'CLI command "' . $taskName . '" is declared as an alias of the unknown command "'
                . $aliasOf . '". Register the aliased command first.'
            );
        }

        if (isset(self::$tasks[$taskName])) {
            throw new InvalidArgumentException('CLI command "' . $taskName . '" is already registered.');
        }

        foreach ($arguments as $argument) {
            if (!isset($argument['name']) || trim((string)$argument['name']) === '') {
                throw new InvalidArgumentException('Every CLI argument definition must have a name.');
            }
        }

        foreach ($options as $option) {
            if (!isset($option['name']) || trim((string)$option['name']) === '') {
                throw new InvalidArgumentException('Every CLI option definition must have a name.');
            }
        }

        // An extension registering through its cli.php must show up in getAll().
        self::$sortedTasks = null;

        self::$tasks[$taskName] = array(
            'name' => $taskName,
            'component' => $componentName === null ? null : (
                self::$moduleContext !== null && self::$moduleContextKind === 'Plugin'
                    ? $componentName : strtoupper($componentName)
            ),
            'componentAccess' => $componentAccess,
            'aliasOf' => $aliasOf,
            'requiredRight' => $requiredRight,
            'actorRequired' => $actorRequired || $componentName !== null,
            'callback' => $callback,
            'description' => trim($description),
            'usage' => trim($usage),
            'arguments' => $arguments,
            'options' => $options,
            'examples' => $examples,
            'unavailableReason' => $unavailableReason,
            'supportsDryRun' => $supportsDryRun,
            'core' => $core
        );
    }

    /**
     * An extension may not use a namespace of Admidio core, and a module or plugin only its own: the
     * namespace named after its directory. Module directories are plural where the namespace is
     * singular - modules/events supplies "event:list" - so the singular form belongs to the module
     * as well, and a plugin directory may use hyphens, which a namespace may also leave out.
     *
     * @param string $kind "commands" or "maintenance jobs", for the message.
     */
    private static function assertOwnNamespace(string $name, string $kind): void
    {
        $namespace = strstr($name, ':', true);

        if (isset(self::$coreNamespaces[$namespace])) {
            throw new InvalidArgumentException('CLI namespace "' . $namespace . '" is reserved by Admidio core.');
        }

        if (self::$moduleContext === null) {
            return;
        }

        $ownNamespaces = array(self::$moduleContext);
        $singular = preg_replace('/s$/', '', self::$moduleContext);
        if ($singular !== self::$moduleContext) {
            $ownNamespaces[] = $singular;
        }
        $ownNamespaces[] = str_replace('-', '', self::$moduleContext);

        if (!in_array($namespace, $ownNamespaces, true)) {
            throw new InvalidArgumentException(
                self::$moduleContextKind . ' "' . self::$moduleContext . '" may only register ' . $kind . ' of the "'
                . implode(':" or "', array_unique($ownNamespaces)) . ':" namespace, but tried to register "'
                . $name . '".'
            );
        }
    }
}
