<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Infrastructure\Utils\FileSystemUtils;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Throwable;

/**
 ***********************************************************************************************
 * Configuration of the Admidio command line, read from adm_my_files/cli-config.php.
 *
 * The file decides whether the command line may be used at all, which system accounts may use it,
 * which commands they may run and which values the options default to. It is read before
 * config.php, because the defaults of --config, --host and --organization are needed before the
 * configuration of the installation is loaded, and it is looked up beside the code that is being
 * executed - it can be selected neither with an option nor with an environment variable.
 *
 * The file is a policy and not a security boundary. Whoever can read adm_my_files/config.php holds
 * the credentials of the database and reaches the same data without the command line, so the
 * restrictions here describe an intent that the operating system has to enforce through the access
 * rights of adm_my_files. CoreTasks::selfCheck() reports where those rights are too generous.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliPolicy
{
    /**
     * Name of the configuration file, always read from the adm_my_files of the executed code.
     */
    public const FILE_NAME = 'cli-config.php';

    /**
     * Commands that are available without the command line being enabled.
     *
     * They only describe the command line itself and change nothing: without them an administrator
     * could not find out how the command line is enabled, and cli:defaultconfig could not print the
     * file that enables it.
     *
     * @var array<int,string>
     */
    public const UNRESTRICTED_COMMANDS = array('', 'help', 'list', 'version', 'completion', 'cli:defaultconfig');

    /**
     * Commands of the installation, which have to run while there is no installation to configure.
     *
     * @var array<int,string>
     */
    private const INSTALLATION_COMMANDS = array('install:check', 'install:run');

    /**
     * Options that must never be given a default value: a confirmation of a destructive operation
     * belongs to the invocation that performs it, and --help would replace every command with its
     * documentation.
     *
     * @var array<int,string>
     */
    private const NON_DEFAULTABLE_OPTIONS = array('yes', 'overwrite', 'help');

    /**
     * Options whose default has to be resolved before the others, because the applicability of the
     * remaining ones is decided by their value.
     *
     * @var array<int,string>
     */
    private const LEADING_OPTIONS = array('format');

    /**
     * Environment variable that makes one invocation ignore the defaults of the configuration file.
     *
     * A configured default changes what a command does without appearing on the command line, which
     * is the point of it but also makes a surprising result hard to explain. This runs a command the
     * way a machine without the file would run it, and the regression tests use it so that they
     * observe the documented behaviour and not the configuration of the checkout they run in. The
     * access rules are unaffected: they are not defaults, and the environment is set by the caller.
     */
    private const IGNORE_DEFAULTS_VARIABLE = 'ADMIDIO_NO_DEFAULTS';

    /**
     * Global options that are flags and therefore take no value.
     *
     * @var array<int,string>
     */
    private const GLOBAL_FLAGS = array('quiet', 'no-interaction', 'yes', 'help', 'overwrite', 'no-truncate', 'dry-run');

    private static ?CliPolicy $current = null;

    private string $path = '';
    private bool $exists = false;
    private bool $enabled = false;
    private string $allowedUsers = '';
    private string $allowedCommands = '';
    private string $deniedCommands = '';
    private string $allowedActors = '';
    private string $logFile = '';
    /** @var array<string,mixed> */
    private array $defaults = array();
    private string $dataPath = '';

    /**
     * Names of the options whose value was taken from the configuration file in this process.
     *
     * @var array<int,string>
     */
    private array $defaultedOptions = array();

    /**
     * Read the configuration file of the installation below the given path.
     *
     * The result becomes the policy of this process, so that the entry point, the application and
     * the commands all describe the same configuration.
     *
     * @param string $rootPath Path of the Admidio installation whose code is executed.
     */
    public static function load(string $rootPath): CliPolicy
    {
        self::$current = self::read($rootPath);

        return self::$current;
    }

    /**
     * Read the configuration file without making it the policy of this process.
     *
     * The test suite uses this to find out how the checkout it runs in is configured, without that
     * configuration deciding what the tests of the option defaults observe.
     *
     * @param string $rootPath Path of the Admidio installation whose code is executed.
     */
    public static function read(string $rootPath): CliPolicy
    {
        $policy = new self();
        $policy->dataPath = $rootPath . '/adm_my_files';
        $policy->path = $policy->dataPath . '/' . self::FILE_NAME;
        $policy->exists = is_file($policy->path);

        if ($policy->exists) {
            $policy->readFile();
        }

        return $policy;
    }

    /**
     * The policy of this process. An unconfigured policy denies everything that is restricted.
     */
    public static function current(): CliPolicy
    {
        if (self::$current === null) {
            self::$current = new self();
        }

        return self::$current;
    }

    /**
     * Use the given policy instead of the one of this process, or null to forget it.
     *
     * The in-process tests need this: they run in the installation of the developer, whose
     * configuration file must not decide what the tests of the option defaults observe.
     */
    public static function useForTests(?CliPolicy $policy): void
    {
        self::$current = $policy;
    }

    /**
     * Build a policy out of the values of a configuration file, without reading one.
     *
     * @param array<string,mixed> $values Values as the configuration file defines them, without
     *                                    the "gCli" prefix, e.g. array('enabled' => true).
     */
    public static function fromValues(array $values, string $path = '', string $dataPath = ''): CliPolicy
    {
        $policy = new self();
        $policy->path = $path;
        $policy->dataPath = $dataPath;
        $policy->exists = true;
        $policy->enabled = (bool)($values['enabled'] ?? false);
        $policy->allowedUsers = (string)($values['allowedUsers'] ?? '');
        $policy->allowedCommands = (string)($values['allowedCommands'] ?? '');
        $policy->deniedCommands = (string)($values['deniedCommands'] ?? '');
        $policy->allowedActors = (string)($values['allowedActors'] ?? '');
        $policy->logFile = (string)($values['logFile'] ?? '');
        $policy->defaults = is_array($values['defaults'] ?? null) ? $values['defaults'] : array();

        return $policy;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function isEnabled(): bool
    {
        return $this->exists && $this->enabled;
    }

    public function allowedUsers(): string
    {
        return $this->allowedUsers;
    }

    public function allowedActors(): string
    {
        return $this->allowedActors;
    }

    public function allowedCommands(): string
    {
        return $this->allowedCommands;
    }

    public function deniedCommands(): string
    {
        return $this->deniedCommands;
    }

    /**
     * Why the command may not be executed, or null when it may.
     *
     * @param string $command Name of the command, empty when none was given.
     * @param string $configFile Configuration file the command would work with.
     */
    public function denialReason(string $command, string $configFile = ''): ?string
    {
        if (in_array($command, self::UNRESTRICTED_COMMANDS, true)) {
            return null;
        }

        /*
         * An installation has no configuration file yet and therefore cannot have a command-line
         * configuration either. Once the installation exists, its commands are restricted like
         * every other command - a CLI installation deliberately does not enable the command line.
         */
        if (in_array($command, self::INSTALLATION_COMMANDS, true) && !is_file($configFile)) {
            return null;
        }

        if (!$this->exists) {
            return 'The Admidio command line is disabled. Create ' . $this->path
                . ' with $gCliEnabled = true to enable it; "' . $this->program()
                . ' cli:defaultconfig" prints a documented template.';
        }

        if (!$this->enabled) {
            return 'The Admidio command line is disabled in ' . $this->path
                . '. Set $gCliEnabled = true to enable it.';
        }

        $reason = $this->systemAccountDenial();
        if ($reason !== null) {
            return $reason;
        }

        return $this->commandDenial($command);
    }

    /**
     * Problems of the installation that the administrator should know about but that do not stop
     * the command.
     *
     * @return array<int,string>
     */
    public function warnings(): array
    {
        $warnings = array();

        if (!$this->exists) {
            return $warnings;
        }

        /*
         * A configuration file that anybody may rewrite states nothing: the restriction it contains
         * can be lifted by everyone it is meant to restrict. Only warn, because a broad umask is
         * common on a developer machine and refusing would be of no help there.
         *
         * Windows has no mode bits that fileperms() could report - it always answers with a
         * writable file - so the check would be a permanent false alarm there.
         */
        if (DIRECTORY_SEPARATOR === '\\') {
            return $warnings;
        }

        $mode = @fileperms($this->path);
        if ($mode !== false && ($mode & 0022) !== 0) {
            $warnings[] = $this->path . ' is writable by its group or by everyone, so the '
                . 'restrictions it contains can be changed by the accounts they restrict.';
        }

        return $warnings;
    }

    /**
     * Whether the acting user selected with --as may be used at all.
     *
     * @param array<int,string> $references Login name, id and UUID of the account.
     */
    public function allowsActor(array $references): bool
    {
        $allowed = self::splitList($this->allowedActors);
        if ($allowed === array()) {
            return true;
        }

        foreach ($allowed as $entry) {
            foreach ($references as $reference) {
                if ($reference !== '' && strcasecmp($entry, $reference) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The value an option defaults to, or null when it has no default.
     *
     * The environment is asked before the configuration file, so that a single invocation can
     * override the configuration without editing it.
     *
     * @param string $command Name of the command whose defaults are used.
     * @param string $name Name of the option without the leading dashes.
     */
    public function optionDefault(string $command, string $name): ?string
    {
        if (in_array($name, self::NON_DEFAULTABLE_OPTIONS, true)) {
            return null;
        }

        $environment = getenv('ADMIDIO_' . strtoupper(str_replace('-', '_', $name)));
        if (is_string($environment) && $environment !== '') {
            return $environment;
        }

        if (self::defaultsIgnored()) {
            return null;
        }

        if ($command !== '' && isset($this->defaults[$command]) && is_array($this->defaults[$command])) {
            $value = $this->defaults[$command][$name] ?? null;
            if ($value !== null && !is_array($value)) {
                return self::scalarToString($value);
            }
        }

        $value = $this->defaults[$name] ?? null;
        if ($value === null || is_array($value)) {
            return null;
        }

        return self::scalarToString($value);
    }

    /**
     * Add the configured defaults to the options that were given on the command line.
     *
     * Only options that the command actually accepts are filled in, so a global default can never
     * make a command fail on an option it does not know. The value is validated afterwards by
     * CliApplication::validateInput(), exactly like a value that was typed.
     *
     * @param string $command Name of the command.
     * @param array<string,mixed>|null $task Registration of the command, null when it is unknown.
     * @param array<string,mixed> $options Options read from the command line.
     * @return array<string,mixed> Returns the options including the defaults.
     */
    public function applyDefaults(string $command, ?array $task, array $options): array
    {
        $names = CliApplication::globalOptionNames();
        $flags = array();

        if ($task !== null) {
            foreach ($task['options'] as $definition) {
                $names[] = (string)$definition['name'];
                if (($definition['flag'] ?? false) === true) {
                    $flags[] = (string)$definition['name'];
                }
            }
        }

        foreach (self::GLOBAL_FLAGS as $flag) {
            $flags[] = $flag;
        }

        /*
         * The value of --format decides whether --width and --no-truncate apply at all, so it has
         * to be resolved before them.
         */
        $names = array_values(array_unique(array_merge(self::LEADING_OPTIONS, $names)));

        foreach ($names as $name) {
            if (in_array($name, self::NON_DEFAULTABLE_OPTIONS, true) || isset($options[$name])) {
                continue;
            }

            $value = $this->optionDefault($command, $name);
            if ($value === null || $value === '') {
                continue;
            }

            if (!$this->defaultApplies($name, $task, $options)) {
                continue;
            }

            if (in_array($name, $flags, true)) {
                $flagValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($flagValue !== true) {
                    continue;
                }
                $options[$name] = true;
            } else {
                $options[$name] = $value;
            }

            $this->defaultedOptions[] = $name;
        }

        return $options;
    }

    /**
     * Names of the options whose value was taken from the configuration file.
     *
     * @return array<int,string>
     */
    public function defaultedOptions(): array
    {
        return array_values(array_unique($this->defaultedOptions));
    }

    /**
     * Sentence that names the configuration file as the origin of the values it supplied, to be
     * appended to the message of a rejected input.
     *
     * Only the options the message is actually about are named, so an error about an argument does
     * not point at a configuration file that has nothing to do with it.
     *
     * @param string $message Message of the rejected input.
     */
    public function defaultsHint(string $message): string
    {
        if ($this->path === '') {
            return '';
        }

        $named = array();
        foreach ($this->defaultedOptions() as $name) {
            if (str_contains($message, '--' . $name)) {
                $named[] = $name;
            }
        }

        if ($named === array()) {
            return '';
        }

        return ' The value of --' . implode(', --', $named) . ' comes from ' . $this->path . '.';
    }

    /**
     * Append one invocation to the log file, if one is configured.
     *
     * Logging must never influence the result of a command, so every failure here is swallowed.
     *
     * @param array<int,string> $argv Arguments of the process.
     * @param int $exitCode Exit code the process ends with.
     * @param string $actor Acting Admidio user, empty when the command ran without one.
     */
    public function logInvocation(array $argv, int $exitCode, string $actor = ''): void
    {
        $file = $this->logFilePath();
        if ($file === '') {
            return;
        }

        try {
            $directory = dirname($file);
            if (!is_dir($directory)) {
                FileSystemUtils::createDirectoryIfNotExists($directory);
            }

            $logger = new Logger('AdmidioCli');
            $handler = new RotatingFileHandler(
                $file,
                0,
                Logger::INFO,
                true,
                FileSystemUtils::DEFAULT_MODE_FILE
            );
            $handler->setFormatter(new LineFormatter("[%datetime%] %message%\n", 'Y-m-d H:i:s', true, true));
            $logger->pushHandler($handler);

            $logger->info(
                'account=' . CliSystemAccount::current()->description()
                . ' exit=' . $exitCode
                . ' actor=' . ($actor !== '' ? $actor : '-')
                . ' command=' . self::redactArguments($argv)
            );
        } catch (Throwable) {
            // A command must not fail because its log file cannot be written.
        }
    }

    /**
     * Path of the log file, empty when no log is configured.
     *
     * A relative path belongs to the data directory of the installation, because that is the
     * directory an Admidio installation is guaranteed to be able to write to.
     */
    public function logFilePath(): string
    {
        if ($this->logFile === '') {
            return '';
        }

        if (self::isAbsolutePath($this->logFile)) {
            return $this->logFile;
        }

        $dataPath = $this->dataPath !== '' ? $this->dataPath : (defined('ADMIDIO_PATH') ? ADMIDIO_PATH . '/adm_my_files' : '');
        if ($dataPath === '') {
            return '';
        }

        return $dataPath . '/' . ltrim($this->logFile, '/\\');
    }

    /**
     * The configuration as the self-check and the preferences of the browser report it.
     *
     * @param bool $withSystemAccount Whether the account of the current process belongs in the
     *                                description. A web request runs as the web server and not as
     *                                the account that would use the command line, so the browser
     *                                asks without it - which also spares it the lookup.
     * @return array<string,mixed>
     */
    public function describe(bool $withSystemAccount = true): array
    {
        $logFile = $this->logFilePath();

        return array(
            'file' => $this->path,
            'exists' => $this->exists,
            'enabled' => $this->isEnabled(),
            'allowed_users' => $this->allowedUsers,
            'allowed_commands' => $this->allowedCommands,
            'denied_commands' => $this->deniedCommands,
            'allowed_actors' => $this->allowedActors,
            'log_file' => $logFile,
            'log_writable' => $logFile === '' ? null : self::isWritablePath($logFile),
            'system_account' => $withSystemAccount ? CliSystemAccount::current()->description() : '',
            'defaults' => $this->defaults
        );
    }

    /**
     * Read the values out of the configuration file.
     *
     * The file is included in a scope of its own, so that its variables do not become globals of
     * the process and cannot collide with the configuration of the installation.
     */
    private function readFile(): void
    {
        $values = (static function (string $admCliConfigFile): array {
            require $admCliConfigFile;

            $variables = get_defined_vars();
            unset($variables['admCliConfigFile']);

            return $variables;
        })($this->path);

        $this->enabled = (bool)($values['gCliEnabled'] ?? false);
        $this->allowedUsers = (string)($values['gCliAllowedUsers'] ?? '');
        $this->allowedCommands = (string)($values['gCliAllowedCommands'] ?? '');
        $this->deniedCommands = (string)($values['gCliDeniedCommands'] ?? '');
        $this->allowedActors = (string)($values['gCliAllowedActors'] ?? '');
        $this->logFile = (string)($values['gCliLogFile'] ?? '');
        $this->defaults = is_array($values['gCliDefaults'] ?? null) ? $values['gCliDefaults'] : array();
    }

    /**
     * Why the system account of this process may not use the command line, or null when it may.
     */
    private function systemAccountDenial(): ?string
    {
        $allowed = self::splitList($this->allowedUsers);
        if ($allowed === array()) {
            return null;
        }

        $account = CliSystemAccount::current();

        if (!$account->isKnown()) {
            return 'CLI access is restricted to specific system accounts, but the account running '
                . 'this process could not be determined (neither ext-posix nor whoami.exe is '
                . 'available). Configured in ' . $this->path . ' ($gCliAllowedUsers).';
        }

        foreach ($allowed as $token) {
            if ($account->matches($token)) {
                return null;
            }
        }

        return 'The system account "' . $account->description() . '" may not use the Admidio '
            . 'command line. Allowed are: ' . implode(', ', $allowed)
            . ' (configured in ' . $this->path . ', $gCliAllowedUsers).';
    }

    /**
     * Why the command may not be run, or null when it may.
     */
    private function commandDenial(string $command): ?string
    {
        foreach (self::splitList($this->deniedCommands) as $pattern) {
            if (fnmatch($pattern, $command)) {
                return 'The command "' . $command . '" is excluded from command-line use in '
                    . $this->path . ' ($gCliDeniedCommands).';
            }
        }

        $allowed = self::splitList($this->allowedCommands);
        if ($allowed === array()) {
            return null;
        }

        foreach ($allowed as $pattern) {
            if (fnmatch($pattern, $command)) {
                return null;
            }
        }

        return 'The command "' . $command . '" is not among the commands allowed in ' . $this->path
            . ' ($gCliAllowedCommands): ' . implode(', ', $allowed) . '.';
    }

    /**
     * Whether a default for the option makes sense for this command and these options.
     *
     * @param array<string,mixed>|null $task
     * @param array<string,mixed> $options
     */
    private function defaultApplies(string $name, ?array $task, array $options): bool
    {
        if ($name === 'as') {
            // Every command that does not act as a user rejects --as, so a default must not reach it.
            return $task !== null && ($task['actorRequired'] ?? false) === true;
        }

        if ($name === 'dry-run') {
            return $task !== null && ($task['supportsDryRun'] ?? false) === true;
        }

        if ($name === 'width' || $name === 'no-truncate') {
            if (isset($options['width']) || isset($options['no-truncate'])) {
                return false;
            }

            $format = strtolower(CliApplication::optionString($options, 'format', 'text'));

            return in_array($format, array('text', 'table'), true);
        }

        return true;
    }

    /**
     * The command as it has to be typed, for the messages that explain how to enable it.
     */
    private function program(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'php admidio' : './admidio';
    }

    /**
     * Whether this invocation was told to ignore the defaults of the configuration file.
     */
    private static function defaultsIgnored(): bool
    {
        $value = getenv(self::IGNORE_DEFAULTS_VARIABLE);

        return is_string($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Split a comma-separated configuration value into its entries.
     *
     * @return array<int,string>
     */
    private static function splitList(string $value): array
    {
        $entries = array();

        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * The arguments of the process as one line, with the values of secrets removed.
     *
     * @param array<int,string> $argv
     */
    private static function redactArguments(array $argv): string
    {
        $arguments = array();
        $redactNext = false;

        foreach (array_slice($argv, 1) as $argument) {
            if ($redactNext) {
                $arguments[] = '***';
                $redactNext = false;
                continue;
            }

            if (preg_match('/^--([A-Za-z0-9-]*(?:password|token|secret)[A-Za-z0-9-]*)(=|$)/i', $argument, $matches) === 1) {
                if ($matches[2] === '=') {
                    $arguments[] = '--' . $matches[1] . '=***';
                    continue;
                }

                $arguments[] = $argument;
                $redactNext = true;
                continue;
            }

            $arguments[] = $argument;
        }

        return implode(' ', $arguments);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private static function isWritablePath(string $path): bool
    {
        return is_file($path) ? is_writable($path) : is_writable(dirname($path));
    }

    /**
     * The value of a configuration entry as the command line would have received it.
     */
    private static function scalarToString(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string)$value;
        }

        return null;
    }
}
