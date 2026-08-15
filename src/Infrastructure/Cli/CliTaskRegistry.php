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
     * @var array<string,array{
     *     name:string,
     *     component:?string,
     *     componentAccess:string,
     *     aliasOf:?string,
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
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
     * Core namespaces cannot be extended or shadowed by module registration.
     *
     * @var array<string,bool>
     */
    private static array $coreNamespaces = array();

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
        ?string $aliasOf = null
    ): void {
        self::registerTask(
            $taskName,
            $componentName,
            $componentAccess,
            $aliasOf,
            $actorRequired,
            $callback,
            $description,
            $usage,
            $arguments,
            $options,
            $examples,
            $unavailableReason,
            true
        );
    }

    /**
     * Register a module-specific command.
     *
     * A module may provide modules/<module>/cli.php and register a command such as
     * "inventory:checkout". Before the callback is executed the component is checked through
     * Component::isAdministrable(), or through Component::isVisible() when the command declares
     * ACCESS_VISIBLE.
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
        string $componentAccess = self::ACCESS_ADMINISTRABLE
    ): void {
        if (!str_contains($taskName, ':')) {
            throw new InvalidArgumentException('Module CLI task names must use the form module:task.');
        }

        if ($componentName === '') {
            throw new InvalidArgumentException('A module CLI task must specify an Admidio component.');
        }

        self::registerTask(
            $taskName,
            strtoupper($componentName),
            $componentAccess,
            null,
            true,
            $callback,
            $description,
            $usage,
            $arguments,
            $options,
            $examples,
            null,
            false
        );
    }

    /**
     * @return array{
     *     name:string,
     *     component:?string,
     *     componentAccess:string,
     *     aliasOf:?string,
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
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
     *     actorRequired:bool,
     *     callback:callable,
     *     description:string,
     *     usage:string,
     *     arguments:array<int,array<string,mixed>>,
     *     options:array<int,array<string,mixed>>,
     *     examples:array<int,string>,
     *     unavailableReason:?string,
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
        bool $actorRequired,
        callable $callback,
        string $description,
        string $usage,
        array $arguments,
        array $options,
        array $examples,
        ?string $unavailableReason,
        bool $core
    ): void {
        if (!preg_match('/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)?$/', $taskName)) {
            throw new InvalidArgumentException(
                'CLI command names must use lowercase letters, digits and hyphens with at most one namespace separator.'
            );
        }

        if (!$core && !str_contains($taskName, ':')) {
            throw new InvalidArgumentException('Module CLI task names must use the form module:task.');
        }

        if (!in_array($componentAccess, array(self::ACCESS_ADMINISTRABLE, self::ACCESS_VISIBLE), true)) {
            throw new InvalidArgumentException(
                'CLI command "' . $taskName . '" declares an unknown component access level.'
            );
        }

        if (str_contains($taskName, ':')) {
            $namespace = strstr($taskName, ':', true);

            if (!$core && isset(self::$coreNamespaces[$namespace])) {
                throw new InvalidArgumentException(
                    'CLI namespace "' . $namespace . '" is reserved by Admidio core.'
                );
            }

            if ($core) {
                self::$coreNamespaces[$namespace] = true;
            }
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

        // A module registering through modules/<module>/cli.php must show up in getAll().
        self::$sortedTasks = null;

        self::$tasks[$taskName] = array(
            'name' => $taskName,
            'component' => $componentName === null ? null : strtoupper($componentName),
            'componentAccess' => $componentAccess,
            'aliasOf' => $aliasOf,
            'actorRequired' => $actorRequired || $componentName !== null,
            'callback' => $callback,
            'description' => trim($description),
            'usage' => trim($usage),
            'arguments' => $arguments,
            'options' => $options,
            'examples' => $examples,
            'unavailableReason' => $unavailableReason,
            'core' => $core
        );
    }
}
