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
     * @var array<string,array{
     *     name:string,
     *     component:?string,
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
        ?string $unavailableReason = null
    ): void {
        self::registerTask(
            $taskName,
            $componentName,
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
     * "inventory:checkout". The component is checked through Component::isAdministrable()
     * before the callback is executed.
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
        array $examples = array()
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
        $tasks = self::$tasks;
        ksort($tasks);

        return $tasks;
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
     * @return array<int,string>
     */
    public static function getOptionFlags(string $taskName): array
    {
        $flags = array('help', 'quiet', 'no-interaction', 'yes');

        $task = self::get($taskName);
        if ($task !== null) {
            foreach ($task['options'] as $option) {
                if (($option['flag'] ?? false) === true && isset($option['name'])) {
                    $flags[] = (string)$option['name'];
                }
            }
        }

        return array_values(array_unique($flags));
    }

    /**
     * @param array<int,array<string,mixed>> $arguments
     * @param array<int,array<string,mixed>> $options
     * @param array<int,string> $examples
     */
    private static function registerTask(
        string $taskName,
        ?string $componentName,
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

        self::$tasks[$taskName] = array(
            'name' => $taskName,
            'component' => $componentName === null ? null : strtoupper($componentName),
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
