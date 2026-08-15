<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Changelog\Entity\LogChanges;
use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 ***********************************************************************************************
 * Application helper for the native Admidio command-line utility.
 *
 * This class only implements command-line concerns (argument parsing, task dispatch, help/output
 * rendering and selection of the acting Admidio user). Domain operations remain in the existing
 * Admidio entities/services or in CoreTasks callbacks.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliApplication
{
    private static string $currentCommand = '';

    /**
     * Whether every session has to be marked for reload before the process ends.
     */
    private static bool $reloadAllSessions = false;

    /**
     * Ids of the users whose sessions have to be marked for reload, used as a set.
     *
     * @var array<int,bool>
     */
    private static array $reloadUserSessions = array();

    /**
     * Single-letter aliases for the global flags that are typed most often.
     *
     * Only flags are abbreviated: a short option that takes a value would have to define whether
     * the value is attached or a separate token, which is not worth the ambiguity here.
     *
     * @var array<string,string>
     */
    private const SHORT_OPTIONS = array(
        'h' => 'help',
        'q' => 'quiet',
        'y' => 'yes'
    );

    /**
     * The command finished successfully.
     */
    public const EXIT_SUCCESS = 0;

    /**
     * The command could not be executed: invalid input, missing rights or an internal error.
     */
    public const EXIT_ERROR = 1;

    /**
     * The command ran successfully but the state it reports is not the desired one, for example
     * a pending database update or an unprotected adm_my_files directory.
     */
    public const EXIT_STATE_NOT_OK = 3;

    /**
     * The command ran successfully and a newer Admidio release is available.
     */
    public const EXIT_UPDATE_AVAILABLE = 4;

    /**
     * The arguments or options were wrong: unknown command, missing argument, invalid value.
     */
    public const EXIT_USAGE = 2;

    /**
     * Admidio rejected the operation: missing rights or a failed domain validation.
     */
    public const EXIT_REJECTED = 5;

    /**
     * The operation was valid and permitted but could not be completed.
     */
    public const EXIT_FAILED = 6;

    /**
     * Global options understood by every command.
     *
     * @var array<int,array<string,mixed>>
     */
    private const GLOBAL_OPTIONS = array(
        array('name' => 'host', 'value' => 'HOST', 'description' => 'Host used by host-dependent config.php files.'),
        array('name' => 'organization', 'value' => 'ORG', 'description' => 'Organization short name.'),
        array('name' => 'as', 'value' => 'USER', 'description' => 'Acting Admidio user for rights and audit identity.'),
        array(
            'name' => 'format',
            'value' => 'FORMAT',
            'description' => 'Output format where supported.',
            'values' => array('text', 'table', 'record', 'json', 'csv', 'md', 'dokuwiki')
        ),
        array('name' => 'output', 'value' => 'FILE', 'description' => 'Write command output to a file.'),
        array('name' => 'quiet', 'flag' => true, 'description' => 'Suppress confirmation messages. Requested data and errors are still printed. Short form -q.'),
        array('name' => 'no-interaction', 'flag' => true, 'description' => 'Never ask an interactive question.'),
        array('name' => 'yes', 'flag' => true, 'description' => 'Confirm destructive operations. Short form -y.'),
        array('name' => 'help', 'flag' => true, 'description' => 'Show help for the selected command. Short form -h.')
    );

    /**
     * Execute the parsed CLI command.
     *
     * @param array<int,string> $argv
     */
    public function run(array $argv): int
    {
        CoreTasks::register();
        $this->loadModuleTasks();

        $found = $this->findCommand($argv);
        if ($found['error'] !== null) {
            throw new InvalidArgumentException($found['error']);
        }

        $command = $found['command'] !== '' ? $found['command'] : 'help';

        $input = $this->parseInput($argv, $command);

        if (self::optionExists($input['options'], 'help') && $command !== 'help') {
            return $this->showHelp(array($command), $input['options']);
        }

        $task = CliTaskRegistry::get($command);
        if ($task === null) {
            throw new InvalidArgumentException(
                'Unknown command "' . $command . '". Run "admidio list" to show all commands.'
            );
        }

        $this->validateInput($task, $input['arguments'], $input['options']);

        if ($task['unavailableReason'] !== null) {
            throw new RuntimeException(
                'Command "' . $command . '" is not available: ' . $task['unavailableReason']
            );
        }

        if ($task['actorRequired']) {
            self::requireActor($input['options']);
        }

        if ($task['component'] !== null) {
            /*
             * A read-only command only requires that the component is visible; it performs the same
             * record-level check as its web counterpart. Everything that changes data requires the
             * component to be administrable.
             */
            $permitted = $task['componentAccess'] === CliTaskRegistry::ACCESS_VISIBLE
                ? Component::isVisible($task['component'])
                : Component::isAdministrable($task['component']);

            if (!$permitted) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        /*
         * Some commands need a right on top of their component, for example the contacts module and
         * a full administrator. Declaring it keeps the requirement in the registry, where "admidio
         * help" can show it, instead of hiding it in the first lines of the callback.
         */
        if ($task['requiredRight'] !== null) {
            $method = CliTaskRegistry::ADDITIONAL_RIGHTS[$task['requiredRight']];
            if (!$GLOBALS['gCurrentUser']->$method()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        self::$currentCommand = $command;

        /*
         * --as is an impersonation switch, not an authentication. Record which command produced a
         * changelog record so an administrator can tell a headless change apart from one the user
         * made in the browser.
         */
        LogChanges::setOriginComment('CLI: ' . $command);

        try {
            $result = ($task['callback'])($input['arguments'], $input['options']);
        } finally {
            // Also on failure: parts of the work may have been committed, and marking a session for
            // reload is idempotent.
            self::flushSessionReload();
        }

        return is_int($result) ? $result : 0;
    }

    public static function currentCommand(): string
    {
        return self::$currentCommand;
    }

    /**
     * Names of the options every command understands, without the leading dashes.
     *
     * @return array<int,string>
     */
    public static function globalOptionNames(): array
    {
        return array_map(
            static fn (array $option): string => (string)$option['name'],
            self::GLOBAL_OPTIONS
        );
    }

    /**
     * Remember that sessions have to re-read their data, without writing immediately.
     *
     * Marking sessions is a full-table UPDATE on adm_sessions. A command that changes several
     * objects - or a provisioning script looping over config:set - triggered one such update per
     * change, although a single one at the end of the process has exactly the same effect.
     *
     * @param int|null $userId Restrict to one user, or null for every session.
     */
    public static function queueSessionReload(?int $userId = null): void
    {
        if ($userId === null) {
            self::$reloadAllSessions = true;
            return;
        }

        if ($userId > 0) {
            self::$reloadUserSessions[$userId] = true;
        }
    }

    /**
     * Perform the session reload that was requested during this process, at most one statement.
     */
    public static function flushSessionReload(): void
    {
        global $gDb;

        if (self::$reloadAllSessions) {
            $gDb->queryPrepared('UPDATE ' . TBL_SESSIONS . ' SET ses_reload = true');
        } elseif (self::$reloadUserSessions !== array()) {
            $userIds = array_keys(self::$reloadUserSessions);
            $gDb->queryPrepared(
                'UPDATE ' . TBL_SESSIONS . '
                    SET ses_reload = true
                  WHERE ses_usr_id IN (' . implode(', ', array_fill(0, count($userIds), '?')) . ')',
                $userIds
            );
        }

        self::$reloadAllSessions = false;
        self::$reloadUserSessions = array();
    }

    /**
     * Report a failure and return the exit code that describes it.
     *
     * A calling script could previously not tell a typo apart from a missing right or a database
     * outage: everything was printed as "Error: ..." and exited with 1. The exception class carries
     * that distinction, so it is mapped to a dedicated code, and the message is emitted as JSON
     * when the caller asked for JSON.
     *
     * @param array<int,string> $argv Raw arguments; the command line may not have been parsed yet.
     */
    public static function handleThrowable(Throwable $exception, array $argv = array()): int
    {
        $exitCode = match (true) {
            $exception instanceof InvalidArgumentException => self::EXIT_USAGE,
            $exception instanceof Exception => self::EXIT_REJECTED,
            // PDOException extends RuntimeException, but a database failure is an internal error.
            $exception instanceof \PDOException, $exception instanceof \Error => self::EXIT_ERROR,
            $exception instanceof RuntimeException => self::EXIT_FAILED,
            default => self::EXIT_ERROR
        };

        // The message of an Admidio exception is already translated by its constructor.
        $message = $exception->getMessage();

        if (self::wantsJsonOutput($argv)) {
            fwrite(
                STDERR,
                json_encode(
                    array(
                        'success' => false,
                        'error' => array(
                            'message' => $message,
                            'type' => (new \ReflectionClass($exception))->getShortName(),
                            'exitCode' => $exitCode
                        )
                    ),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );
        } else {
            fwrite(STDERR, 'Error: ' . $message . PHP_EOL);
        }

        return $exitCode;
    }

    /**
     * Detect --format=json directly in the raw arguments, because a failure may happen before or
     * while the command line is parsed.
     *
     * @param array<int,string> $argv
     */
    private static function wantsJsonOutput(array $argv): bool
    {
        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            if ($argv[$index] === '--format=json') {
                return true;
            }
            if ($argv[$index] === '--format' && ($argv[$index + 1] ?? '') === 'json') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the command name from the argument list.
     *
     * Only the global options may precede the command, because the task-specific options are not
     * known before the command is. A token that cannot be classified is reported through the error
     * element rather than silently falling back to the help command, which would produce a message
     * naming the wrong command.
     *
     * @param array<int,string> $argv
     * @return array{command:string,error:?string}
     */
    private function findCommand(array $argv): array
    {
        $globalFlags = array('quiet', 'no-interaction', 'yes', 'help');
        $globalValueOptions = array('host', 'organization', 'as', 'format', 'output');

        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            $token = $argv[$index];

            if ($token === '--') {
                return array('command' => $argv[$index + 1] ?? '', 'error' => null);
            }

            // Short flags may precede the command just like their long forms.
            if (!str_starts_with($token, '--') && str_starts_with($token, '-') && strlen($token) > 1) {
                if (isset(self::SHORT_OPTIONS[substr($token, 1)])) {
                    continue;
                }

                return array(
                    'command' => '',
                    'error' => 'Unknown option "' . $token . '". Known short options are -'
                        . implode(', -', array_keys(self::SHORT_OPTIONS)) . '.'
                );
            }

            if (!str_starts_with($token, '--')) {
                return array('command' => $token, 'error' => null);
            }

            $optionName = substr($token, 2);
            if (str_contains($optionName, '=')) {
                continue;
            }

            if (in_array($optionName, $globalFlags, true)) {
                continue;
            }

            if (in_array($optionName, $globalValueOptions, true)) {
                $value = $argv[$index + 1] ?? null;

                if ($value === null || str_starts_with($value, '--')) {
                    return array('command' => '', 'error' => '--' . $optionName . ' expects a value.');
                }

                /*
                 * "admidio --format user:list" would consume the command as the option value and
                 * leave no command at all. Point at the = form instead of silently showing help.
                 */
                if (CliTaskRegistry::get($value) !== null) {
                    return array(
                        'command' => '',
                        'error' => 'The value of --' . $optionName . ' is the command name "' . $value
                            . '". Use --' . $optionName . '=VALUE when the option precedes the command.'
                    );
                }

                ++$index;
                continue;
            }

            return array(
                'command' => '',
                'error' => 'Unknown option "--' . $optionName . '" before the command name. '
                    . 'Only global options may precede the command.'
            );
        }

        return array('command' => '', 'error' => null);
    }

    /**
     * @param array<int,string> $argv
     * @return array{arguments:array<int,string>,options:array<string,mixed>}
     */
    private function parseInput(array $argv, string $command): array
    {
        $task = CliTaskRegistry::get($command);
        $flagOptions = array('quiet', 'no-interaction', 'yes', 'help');

        if ($task !== null) {
            foreach ($task['options'] as $definition) {
                if (($definition['flag'] ?? false) === true) {
                    $flagOptions[] = (string)$definition['name'];
                }
            }
        }

        $flagOptions = array_values(array_unique($flagOptions));
        $arguments = array();
        $options = array();
        $commandConsumed = false;
        $parseOptions = true;

        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            $token = $argv[$index];

            if ($parseOptions && $token === '--') {
                $parseOptions = false;
                continue;
            }

            if ($parseOptions
                && !str_starts_with($token, '--')
                && str_starts_with($token, '-')
                && strlen($token) > 1) {
                $letter = substr($token, 1);
                if (!isset(self::SHORT_OPTIONS[$letter])) {
                    throw new InvalidArgumentException(
                        'Unknown option "' . $token . '". Known short options are -'
                        . implode(', -', array_keys(self::SHORT_OPTIONS)) . '.'
                    );
                }

                self::appendOption($options, self::SHORT_OPTIONS[$letter], true);
                continue;
            }

            if ($parseOptions && str_starts_with($token, '--')) {
                $optionText = substr($token, 2);
                if ($optionText === '') {
                    throw new InvalidArgumentException('Invalid empty command-line option.');
                }

                if (str_contains($optionText, '=')) {
                    [$name, $value] = explode('=', $optionText, 2);
                } else {
                    $name = $optionText;
                    if (in_array($name, $flagOptions, true)) {
                        $value = true;
                    } elseif (isset($argv[$index + 1]) && $argv[$index + 1] !== '--') {
                        $value = $argv[++$index];
                    } else {
                        throw new InvalidArgumentException('--' . $name . ' expects a value.');
                    }
                }

                self::appendOption($options, $name, $value);
                continue;
            }

            if (!$commandConsumed && $token === $command) {
                $commandConsumed = true;
                continue;
            }

            $arguments[] = $token;
        }

        if (!$commandConsumed && $command !== 'help') {
            throw new InvalidArgumentException('Command "' . $command . '" could not be parsed.');
        }

        return array('arguments' => $arguments, 'options' => $options);
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function appendOption(array &$options, string $name, mixed $value): void
    {
        if (isset($options[$name])) {
            if (!is_array($options[$name])) {
                $options[$name] = array($options[$name]);
            }
            $options[$name][] = $value;
        } else {
            $options[$name] = $value;
        }
    }

    /**
     * @param array<string,mixed> $task
     * @param array<int,string> $arguments
     * @param array<string,mixed> $options
     */
    private function validateInput(array $task, array $arguments, array $options): void
    {
        $knownOptions = array_column(self::GLOBAL_OPTIONS, 'name');
        foreach ($task['options'] as $definition) {
            $knownOptions[] = (string)$definition['name'];
        }

        foreach (array_keys($options) as $name) {
            if (!in_array($name, $knownOptions, true)) {
                throw new InvalidArgumentException(
                    'Unknown option "--' . $name . '" for command "' . $task['name'] . '".'
                );
            }
        }

        $argumentIndex = 0;
        foreach ($task['arguments'] as $definition) {
            $multiple = (bool)($definition['multiple'] ?? false);
            $required = (bool)($definition['required'] ?? false);

            if ($required && !isset($arguments[$argumentIndex])) {
                throw new InvalidArgumentException(
                    'Missing required argument ' . strtoupper((string)$definition['name']) . '.'
                );
            }

            if ($multiple) {
                $argumentIndex = count($arguments);
                break;
            }

            ++$argumentIndex;
        }

        if ($argumentIndex < count($arguments)) {
            throw new InvalidArgumentException('Too many arguments for command "' . $task['name'] . '".');
        }

        foreach ($task['options'] as $definition) {
            $name = (string)$definition['name'];

            if (($definition['required'] ?? false) === true && !self::optionExists($options, $name)) {
                throw new InvalidArgumentException('Missing required option --' . $name . '.');
            }

            if (!self::optionExists($options, $name)) {
                continue;
            }

            $values = self::optionValuesRaw($options, $name);
            if (($definition['multiple'] ?? false) !== true && count($values) > 1) {
                throw new InvalidArgumentException('Option --' . $name . ' may only be specified once.');
            }

            if (isset($definition['values']) && is_array($definition['values'])) {
                foreach ($values as $value) {
                    if (!in_array((string)$value, $definition['values'], true)) {
                        throw new InvalidArgumentException(
                            '--' . $name . ' expects one of: ' . implode(', ', $definition['values']) . '.'
                        );
                    }
                }
            }
        }
    }

    /**
     * Load optional module-specific CLI registrations.
     */
    private function loadModuleTasks(): void
    {
        $registrationFiles = glob(ADMIDIO_PATH . FOLDER_MODULES . '/*/cli.php') ?: array();
        sort($registrationFiles);

        foreach ($registrationFiles as $registrationFile) {
            $module = basename(dirname($registrationFile));

            /*
             * A module may only register commands of its own namespace, and a broken module must
             * not take the whole command line down with it: without this, a single faulty cli.php
             * would make even "admidio help" unusable. The problem is reported and the remaining
             * commands stay available.
             */
            CliTaskRegistry::setModuleContext($module);
            try {
                require_once $registrationFile;
            } catch (Throwable $exception) {
                fwrite(
                    STDERR,
                    'Warning: the CLI commands of module "' . $module . '" were not registered: '
                    . $exception->getMessage() . PHP_EOL
                );
            } finally {
                CliTaskRegistry::setModuleContext(null);
            }
        }
    }

    /**
     * Callback for the "help" task.
     *
     * @param array<int,string> $arguments
     * @param array<string,mixed> $options
     */
    public function showHelp(array $arguments, array $options): int
    {
        $command = $arguments[0] ?? '';
        $showAll = self::optionBool($options, 'all', false) ?? false;
        $format = strtolower(self::optionString($options, 'format', 'text'));

        if (!in_array($format, array('text', 'md', 'dokuwiki', 'json'), true)) {
            throw new InvalidArgumentException('help --format expects text, md, dokuwiki or json.');
        }

        if ($format === 'json') {
            if ($command !== '') {
                $task = CliTaskRegistry::get($command);
                if ($task === null) {
                    throw new InvalidArgumentException('Unknown command "' . $command . '".');
                }
                $documentation = $task;
                unset($documentation['callback']);
            } elseif ($showAll) {
                $documentation = CliTaskRegistry::getDocumentation();
            } else {
                $documentation = array(
                    'description' => 'Admidio command-line administration utility',
                    'usage' => 'admidio [global-options] COMMAND [arguments] [options]',
                    'globalOptions' => self::GLOBAL_OPTIONS
                );
            }

            self::writeOutput(
                json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                $options
            );
            return 0;
        }

        if ($command !== '') {
            $task = CliTaskRegistry::get($command);
            if ($task === null) {
                throw new InvalidArgumentException('Unknown command "' . $command . '".');
            }

            self::writeOutput($this->renderTaskHelp($task, $format), $options);
            return 0;
        }

        if ($showAll) {
            self::writeOutput($this->renderAllHelp($format), $options);
        } else {
            self::writeOutput($this->renderOverviewHelp($format), $options);
        }

        return 0;
    }

    /**
     * Callback for the "list" task.
     *
     * @param array<int,string> $arguments
     * @param array<string,mixed> $options
     */
    public function showList(array $arguments, array $options): int
    {
        $namespace = $arguments[0] ?? '';
        $rows = array();

        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if ($name === 'help' || $name === 'list') {
                continue;
            }

            if ($namespace !== '' && !str_starts_with($name, $namespace . ':') && $name !== $namespace) {
                continue;
            }

            $rows[] = array(
                'command' => $name,
                'alias of' => $task['aliasOf'] ?? '',
                'available' => $task['unavailableReason'] === null ? 'yes' : 'no',
                'description' => $task['description']
            );
        }

        $format = strtolower(self::optionString($options, 'format', 'table'));
        self::writeRows($rows, $format, $options);

        return 0;
    }

    /**
     * @param array<string,mixed> $task
     */
    private function renderTaskHelp(array $task, string $format, int $headlineLevel = 1): string
    {
        $usage = $task['usage'] !== '' ? $task['usage'] : $task['name'];
        $sectionLevel = min($headlineLevel + 1, 5);

        $text = $this->renderHeadline((string)$task['name'], $headlineLevel, $format);
        $text .= $this->renderParagraph((string)$task['description'], $format);

        $text .= $this->renderHeadline('Usage', $sectionLevel, $format);
        $text .= $this->renderCodeBlock('admidio ' . $usage, $format);

        if (($task['aliasOf'] ?? null) !== null) {
            $text .= $this->renderHeadline('Alias', $sectionLevel, $format);
            $text .= $this->renderParagraph(
                'This command is another name for ' . $task['aliasOf'] . ' and behaves identically.',
                $format
            );
        }

        if (($task['requiredRight'] ?? null) !== null) {
            $text .= $this->renderHeadline('Required right', $sectionLevel, $format);
            $text .= $this->renderParagraph(
                'The acting user selected with --as must be an Admidio ' . $task['requiredRight'] . '.',
                $format
            );
        }

        $text .= $this->renderAvailability($task['unavailableReason'], $format, $sectionLevel);
        $text .= $this->renderArguments($task['arguments'], $format, $sectionLevel);
        $text .= $this->renderOptions($task['options'], $format, $sectionLevel);
        $text .= $this->renderExamples($task['examples'], $format, $sectionLevel);

        return $text;
    }

    private function renderOverviewHelp(string $format): string
    {
        $text = $this->renderHeadline  ('Admidio command-line administration utility', 1, $format);
        $text .= $this->renderHeadline ('Usage', 2, $format);
        $text .= $this->renderCodeBlock('admidio [global-options] COMMAND [arguments] [options]', $format);

        $text .= $this->renderOptions(self::GLOBAL_OPTIONS, $format, 2, 'Global options');

        $text .= $this->renderHeadline('Exit codes', 2, $format);
        $text .= $this->renderTable(
            array('Code', 'Meaning'),
            array(
                array((string)self::EXIT_SUCCESS, 'The command finished successfully.'),
                array((string)self::EXIT_ERROR, 'An internal error, for example a database failure.'),
                array((string)self::EXIT_USAGE, 'Wrong arguments or options: unknown command, missing argument, invalid value.'),
                array(
                    (string)self::EXIT_STATE_NOT_OK,
                    'The command ran, but the reported state is not the desired one. '
                        . 'Used by status and htaccess:status.'
                ),
                array(
                    (string)self::EXIT_UPDATE_AVAILABLE,
                    'The command ran and a newer Admidio release is available. Used by update:check.'
                ),
                array((string)self::EXIT_REJECTED, 'Admidio rejected the operation: missing rights or a failed validation.'),
                array((string)self::EXIT_FAILED, 'The operation was permitted but could not be completed.')
            ),
            $format
        );

        $text .= $this->renderHeadline('Help', 2, $format);
        $text .= $this->renderList(
            array(
                $this->renderInlineCode('admidio list', $format) . ' — ' . $this->escapeText('List available commands.', $format),
                $this->renderInlineCode('admidio help COMMAND', $format) . ' — ' . $this->escapeText('Show help for one command.', $format),
                $this->renderInlineCode('admidio help --all', $format) . ' — ' . $this->escapeText('Show complete command documentation.', $format),
                $this->renderInlineCode('admidio help --all --format=md', $format) . ' — ' . $this->escapeText('Generate Markdown documentation.', $format),
                $this->renderInlineCode('admidio help --all --format=dokuwiki', $format) . ' — ' . $this->escapeText('Generate native DokuWiki documentation.', $format)
            ),
            $format
        );

        return $text;
    }

    private function renderAllHelp(string $format): string
    {
        $text  = $this->renderHeadline ('Admidio command-line utility', 1, $format);
        $text .= $this->renderHeadline ('Usage', 2, $format);
        $text .= $this->renderCodeBlock('admidio [global-options] COMMAND [arguments] [options]', $format);

        $text .= $this->renderOptions(self::GLOBAL_OPTIONS, $format, 2, 'Global options');

        foreach (CliTaskRegistry::getAll() as $task) {
            $text .= $this->renderTaskHelp($task, $format, 2);
        }

        return $text;
    }

    private function renderAvailability(?string $unavailableReason, string $format, int $headlineLevel): string
    {
        if ($unavailableReason === null) {
            return '';
        }

        return $this->renderHeadline('Availability', $headlineLevel, $format)
             . $this->renderParagraph('Unavailable: ' . $unavailableReason, $format);
    }

    /**
     * @param array<int,array<string,mixed>> $arguments
     */
    private function renderArguments(array $arguments, string $format, int $headlineLevel): string
    {
        if (count($arguments) === 0) {
            return '';
        }
 
        $rows = array();
        foreach ($arguments as $argument) {
            $rows[] = array(
                (string)$argument['name'],
                ($argument['required'] ?? false) ? 'yes' : 'no',
                (string)($argument['description'] ?? '')
            );
        }
        return $this->renderHeadline('Arguments', $headlineLevel, $format)
             . $this->renderTable(array('Argument', 'Required', 'Description'), $rows, $format);
     }

    /**
     * @param array<int,array<string,mixed>> $options
     */
    private function renderOptions(array $options, string $format, int $headlineLevel, string $headline = 'Options'): string
     {
        if (count($options) === 0) {
            return '';
        }

        $rows = array();
        foreach ($options as $option) {
            $rows[] = array(
                $this->optionSynopsis($option),
                $this->optionDescription($option)
            );
        }
 
        return $this->renderHeadline($headline, $headlineLevel, $format)
             . $this->renderTable(array('Option', 'Description'), $rows, $format);
    }

    /**
     * @param array<int,string> $examples
     */
    private function renderExamples(array $examples, string $format, int $headlineLevel): string
    {
        if (count($examples) === 0) {
            return '';
        }

        return $this->renderHeadline('Examples', $headlineLevel, $format)
             . $this->renderCodeBlock(implode(PHP_EOL, $examples), $format, 'bash');
     }

     private function renderHeadline(string $headline, int $level, string $format): string
    {
        $level = max(1, min(5, $level));
        $headline = $this->escapeText($headline, $format);

        if ($format === 'md') {
            return str_repeat('#', $level) . ' ' . $headline . PHP_EOL . PHP_EOL;
        }

        if ($format === 'dokuwiki') {
            $marker = str_repeat('=', 7 - $level);
            return $marker . ' ' . $headline . ' ' . $marker . PHP_EOL . PHP_EOL;
        }

        $underlineCharacters = array(1 => '=', 2 => '-', 3 => '~', 4 => '.', 5 => '.');
        return $headline . PHP_EOL
            . str_repeat($underlineCharacters[$level], self::displayWidth($headline))
            . PHP_EOL . PHP_EOL;
    }

    private function renderParagraph(string $text, string $format): string
    {
        return $this->escapeText($text, $format) . PHP_EOL . PHP_EOL;
    }

    private function renderInlineCode(string $text, string $format): string
    {
        if ($format === 'md') {
            return '`' . str_replace('`', '\`', $text) . '`';
        }

        if ($format === 'dokuwiki') {
            return "''" . self::escapeDokuWiki($text) . "''";
        }

        return $text;
    }

    private function renderCodeBlock(string $code, string $format, string $language = ''): string
    {
        if ($format === 'md') {
            return '```' . $language . PHP_EOL
                . $code . PHP_EOL
                . '```' . PHP_EOL . PHP_EOL;
        }

        if ($format === 'dokuwiki') {
            $languageAttribute = $language !== '' ? ' ' . $language : '';
            return '<code' . $languageAttribute . '>' . PHP_EOL
                . self::escapeDokuWikiCode($code) . PHP_EOL
                . '</code>' . PHP_EOL . PHP_EOL;
        }

        $lines = preg_split('/\R/', $code) ?: array($code);
        return implode(
            PHP_EOL,
            array_map(static fn (string $line): string => '  ' . $line, $lines)
        ) . PHP_EOL . PHP_EOL;
    }

    /**
     * @param array<int,string> $items
     */
    private function renderList(array $items, string $format): string
    {
        $marker = $format === 'dokuwiki' ? '  * ' : '- ';

        return implode(
            PHP_EOL,
            array_map(static fn (string $item): string => $marker . $item, $items)
        ) . PHP_EOL . PHP_EOL;
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $rows
     */
    private function renderTable(array $headers, array $rows, string $format): string
    {
        if ($format === 'md') {
            $output = '| '
                . implode(' | ', array_map(array(self::class, 'escapeMarkdown'), $headers))
                . " |\n";
            $output .= '|' . str_repeat('---|', count($headers)) . "\n";

            foreach ($rows as $row) {
                $output .= '| '
                    . implode(' | ', array_map(array(self::class, 'escapeMarkdown'), $row))
                    . " |\n";
            }

            return $output . PHP_EOL;
        }

        if ($format === 'dokuwiki') {
            $output = '^ '
                . implode(' ^ ', array_map(array(self::class, 'escapeDokuWiki'), $headers))
                . " ^\n";

            foreach ($rows as $row) {
                $output .= '| '
                    . implode(' | ', array_map(array(self::class, 'escapeDokuWiki'), $row))
                    . " |\n";
            }

            return $output . PHP_EOL;
        }

        $widths = array_fill(0, count($headers), 0);
        foreach ($headers as $index => $header) {
            $widths[$index] = self::displayWidth($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index], self::displayWidth($value));
            }
        }

        $output = '';
        $allRows = array_merge(array($headers), $rows);
        foreach ($allRows as $rowIndex => $row) {
            foreach ($row as $index => $value) {
                $output .= self::padCell($value, $widths[$index] + 2);
            }
            $output = rtrim($output) . PHP_EOL;

            if ($rowIndex === 0) {
                foreach ($widths as $width) {
                    $output .= str_repeat('-', $width) . '  ';
                }
                $output = rtrim($output) . PHP_EOL;
            }
        }

        return $output . PHP_EOL;
    }
 
    /**
     * @param array<string,mixed> $definition
     */
    private function optionDescription(array $definition): string
    {
        $description = (string)($definition['description'] ?? '');

        if (isset($definition['values'])
            && is_array($definition['values'])
            && count($definition['values']) > 0) {
            if ($description !== '' && !str_ends_with($description, '.')) {
                $description .= '.';
            }

            if ($description !== '') {
                $description .= ' ';
            }

            $description .= 'Possible values: '
                . implode(', ', $definition['values'])
                . '.';
        }

        return $description;
    }

    /**
     * @param array<string,mixed> $definition
     */
    private function optionSynopsis(array $definition): string
    {
        $synopsis = '--' . $definition['name'];
        if (($definition['flag'] ?? false) !== true) {
            $synopsis .= '=' . ($definition['value'] ?? 'VALUE');
        }

        if (($definition['multiple'] ?? false) === true) {
            $synopsis .= ' ...';
        }

        return $synopsis;
    }

    private function escapeText(string $value, string $format): string
    {
        if ($format === 'md') {
            return self::escapeMarkdown($value);
        }

        if ($format === 'dokuwiki') {
            return self::escapeDokuWiki($value);
        }

        return $value;
    }

    private static function escapeMarkdown(string $value): string
    {
        return str_replace(
            array('\\', '|', "\r", "\n"),
            array('\\\\', '\|', '', '<br>'),
            $value
        );
    }

    private static function escapeDokuWiki(string $value): string
    {
        return str_replace(array('|', "\r", "\n"), array('\|', '', ' '), $value);
    }

    private static function escapeDokuWikiCode(string $value): string
    {
        return str_replace('</code>', '<\/code>', $value);
    }

    /**
     * Select the Admidio user whose rights and audit identity are used for this command.
     *
     * --as does not authenticate the selected user. Local execution rights and access to the
     * Admidio configuration/database are the command-line security boundary.
     *
     * The selected account must nevertheless be usable: it has to be activated (usr_valid) and an
     * active member of the current organization, so the command line cannot act as an identity
     * that would be rejected by User::checkLogin().
     *
     * @param array<string,mixed> $options
     */
    public static function requireActor(array $options): User
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gCurrentUserUUID, $gValidLogin;
        global $gProfileFields, $gChangeNotification;

        if (isset($gCurrentUser) && $gCurrentUser instanceof User && $gValidLogin) {
            return $gCurrentUser;
        }

        $reference = self::optionString($options, 'as');
        if ($reference === '') {
            throw new InvalidArgumentException(
                'This command requires --as=<user UUID, login name or id>.'
            );
        }

        if (!isset($gProfileFields) || !$gProfileFields instanceof ProfileFields) {
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        }

        if (!isset($gChangeNotification) || !$gChangeNotification instanceof ChangeNotification) {
            $gChangeNotification = new ChangeNotification();
        }

        $user = self::resolveUser($reference);

        /*
         * --as does not authenticate, but it must not grant an identity that could not log in at
         * all. Both checks are evaluated before $gValidLogin is set, because every following
         * Component and User right check derives from that state.
         */
        if (!(bool)$user->getValue('usr_valid')) {
            throw new RuntimeException(
                'The Admidio account "' . $reference . '" is not activated and cannot be used with --as.'
            );
        }

        if (!$user->isMemberOfOrganization()) {
            throw new RuntimeException(
                'The Admidio account "' . $reference . '" is not an active member of the current organization.'
            );
        }

        $gCurrentUser = $user;
        $gCurrentUserId = (int)$user->getValue('usr_id');
        $gCurrentUserUUID = (string)$user->getValue('usr_uuid');
        $gValidLogin = true;

        return $user;
    }

    /**
     * Resolve a user reference to a User entity.
     *
     * The lookup is restricted to members of the current organization, so a USER argument cannot
     * address contacts of a foreign organization. Only user:delete needs the unrestricted lookup,
     * because it has to recognise memberships in other organizations before deleting an account;
     * it opts in through $anyOrganization.
     */
    /**
     * Provide the globals that Admidio code expects even when a command runs without --as.
     *
     * A web request always has a $gCurrentUser object, an anonymous one for a visitor who is not
     * logged in. Commands that deliberately run without an acting user - the counterparts of the
     * public self-service pages - have to establish the same invariant, otherwise any code they
     * reach that dereferences $gCurrentUser fails on an undefined variable.
     */
    public static function ensureAnonymousActor(): void
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser, $gProfileFields, $gChangeNotification;

        if (!isset($gProfileFields) || !$gProfileFields instanceof ProfileFields) {
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        }

        if (!isset($gChangeNotification) || !$gChangeNotification instanceof ChangeNotification) {
            $gChangeNotification = new ChangeNotification();
        }

        if (!isset($gCurrentUser) || !$gCurrentUser instanceof User) {
            // An empty user object, exactly as a visitor without a login has.
            $gCurrentUser = new User($gDb, $gProfileFields);
        }
    }

    public static function resolveUser(string $reference, bool $anyOrganization = false): User
    {
        global $gDb, $gCurrentOrgId, $gProfileFields;

        if (!isset($gProfileFields) || !$gProfileFields instanceof ProfileFields) {
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        }

        $scope = '';
        $scopeParams = array();
        if (!$anyOrganization) {
            /*
             * A user belongs to the organization if they hold a membership in one of its roles -
             * current or former, so former members stay addressable - or if they have a pending
             * registration for it, because a registration has no membership until it is approved.
             */
            $scope = ' AND (EXISTS (
                        SELECT 1
                          FROM ' . TBL_MEMBERS . '
                    INNER JOIN ' . TBL_ROLES . '      ON rol_id = mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                         WHERE mem_usr_id = usr_id
                           AND rol_valid = true
                           AND (cat_org_id = ? OR cat_org_id IS NULL)
                      ) OR EXISTS (
                        SELECT 1
                          FROM ' . TBL_REGISTRATIONS . '
                         WHERE reg_usr_id = usr_id
                           AND reg_org_id = ?
                      ))';
            $scopeParams[] = $gCurrentOrgId;
            $scopeParams[] = $gCurrentOrgId;
        }

        if (ctype_digit($reference)) {
            $statement = $gDb->queryPrepared(
                'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_id = ?' . $scope,
                array_merge(array((int)$reference), $scopeParams)
            );
        } else {
            $statement = $gDb->queryPrepared(
                'SELECT usr_id
                   FROM ' . TBL_USERS . '
                  WHERE (usr_uuid = ?
                     OR UPPER(usr_login_name) = UPPER(?))' . $scope,
                array_merge(array($reference, $reference), $scopeParams)
            );
        }

        $ids = array_values(array_unique(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN))));
        if (count($ids) === 0) {
            throw new InvalidArgumentException('User "' . $reference . '" was not found.');
        }
        if (count($ids) > 1) {
            throw new InvalidArgumentException('User reference "' . $reference . '" is ambiguous.');
        }

        return new User($gDb, $gProfileFields, $ids[0]);
    }

    /**
     * Resolve a UUID/id selector through a table without bypassing the entity for the actual
     * domain operation.
     */
    public static function resolveId(
        string $table,
        string $idColumn,
        string $uuidColumn,
        string $reference,
        string $label,
        string $additionalWhere = '',
        array $additionalParams = array()
    ): int {
        global $gDb;

        if (ctype_digit($reference)) {
            $sql = 'SELECT ' . $idColumn . ' FROM ' . $table . ' WHERE ' . $idColumn . ' = ?';
            $params = array((int)$reference);
        } else {
            $sql = 'SELECT ' . $idColumn . ' FROM ' . $table . ' WHERE ' . $uuidColumn . ' = ?';
            $params = array($reference);
        }

        if ($additionalWhere !== '') {
            $sql .= ' AND (' . $additionalWhere . ')';
            $params = array_merge($params, $additionalParams);
        }

        $ids = array_values(array_unique(array_map(
            'intval',
            $gDb->queryPrepared($sql, $params)->fetchAll(\PDO::FETCH_COLUMN)
        )));

        if (count($ids) === 0) {
            throw new InvalidArgumentException($label . ' "' . $reference . '" was not found.');
        }
        if (count($ids) > 1) {
            throw new InvalidArgumentException($label . ' reference "' . $reference . '" is ambiguous.');
        }

        return $ids[0];
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function optionString(array $options, string $name, string $default = ''): string
    {
        if (!array_key_exists($name, $options)) {
            return $default;
        }

        $value = $options[$name];
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === true) {
            return '1';
        }

        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,string>
     */
    public static function optionValues(array $options, string $name): array
    {
        if (!array_key_exists($name, $options)) {
            return array();
        }

        $values = is_array($options[$name]) ? $options[$name] : array($options[$name]);

        return array_values(array_map(
            static fn (mixed $value): string => $value === true ? '1' : (string)$value,
            $values
        ));
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,mixed>
     */
    private static function optionValuesRaw(array $options, string $name): array
    {
        if (!array_key_exists($name, $options)) {
            return array();
        }

        return is_array($options[$name]) ? $options[$name] : array($options[$name]);
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function optionExists(array $options, string $name): bool
    {
        return array_key_exists($name, $options);
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function optionBool(array $options, string $name, ?bool $default = null): ?bool
    {
        if (!array_key_exists($name, $options)) {
            return $default;
        }

        $value = $options[$name];
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === true || $value === 1 || in_array(strtolower((string)$value), array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }

        if ($value === false || $value === 0 || in_array(strtolower((string)$value), array('0', 'false', 'no', 'off'), true)) {
            return false;
        }

        throw new InvalidArgumentException('--' . $name . ' expects 0/1, false/true, no/yes or off/on.');
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function optionInt(array $options, string $name, ?int $default = null): ?int
    {
        if (!self::optionExists($options, $name)) {
            return $default;
        }

        $value = self::optionString($options, $name);
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new InvalidArgumentException('--' . $name . ' expects an integer.');
        }

        return (int)$value;
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function optionFloat(array $options, string $name, ?float $default = null): ?float
    {
        if (!self::optionExists($options, $name)) {
            return $default;
        }

        $value = self::optionString($options, $name);
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('--' . $name . ' expects a number.');
        }

        return (float)$value;
    }

    public static function requireArgument(array $arguments, int $index, string $name): string
    {
        if (!isset($arguments[$index]) || $arguments[$index] === '') {
            throw new InvalidArgumentException('Missing required argument ' . strtoupper($name) . '.');
        }

        return $arguments[$index];
    }

    public static function validateDateTime(string $dateTime, string $label = 'date/time'): string
    {
        foreach (array('!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i') as $format) {
            $object = \DateTime::createFromFormat($format, $dateTime);
            if ($object !== false) {
                return $object->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidArgumentException($label . ' must use YYYY-MM-DDTHH:MM.');
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function confirm(string $question, array $options): void
    {
        if (self::optionBool($options, 'yes', false)) {
            return;
        }

        if (self::optionBool($options, 'no-interaction', false)) {
            throw new RuntimeException($question . ' Re-run with --yes.');
        }

        fwrite(STDERR, $question . ' [y/N] ');
        $answer = fgets(STDIN);
        if ($answer === false || !in_array(strtolower(trim($answer)), array('y', 'yes'), true)) {
            throw new RuntimeException('Operation cancelled.');
        }
    }

    /**
     * Read a secret either from a normal option or one line from STDIN.
     *
     * @param array<string,mixed> $options
     */
    public static function readSecret(array $options, string $optionName, string $stdinFlag): string
    {
        if (self::optionBool($options, $stdinFlag, false)) {
            return self::readSecretLine();
        }

        return self::optionString($options, $optionName);
    }

    /**
     * Read one line from STDIN without echoing it back when STDIN is a terminal, so an
     * interactively typed secret does not stay visible on screen or in a captured session.
     *
     * Terminal echo can only be switched off through stty, which is not available on Windows.
     * There the value is read as before.
     */
    private static function readSecretLine(): string
    {
        $restore = null;

        if (PHP_OS_FAMILY !== 'Windows'
            && function_exists('stream_isatty')
            && @stream_isatty(STDIN)
            && function_exists('shell_exec')) {
            $settings = @shell_exec('stty -g 2>/dev/null');
            if (is_string($settings) && trim($settings) !== '') {
                $restore = trim($settings);
                @shell_exec('stty -echo 2>/dev/null');
            }
        }

        try {
            $value = fgets(STDIN);
        } finally {
            if ($restore !== null) {
                @shell_exec('stty ' . escapeshellarg($restore) . ' 2>/dev/null');
                // The user's newline was swallowed together with the echo.
                fwrite(STDERR, PHP_EOL);
            }
        }

        if ($value === false) {
            throw new RuntimeException('Could not read value from STDIN.');
        }

        return rtrim($value, "\r\n");
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $options
     */
    public static function writeRows(array $rows, string $format, array $options): void
    {
        $format = strtolower($format);
        if ($format === 'text') {
            $format = 'table';
        }

        if (count($rows) === 0) {
            self::writeOutput($format === 'json' ? "[]\n" : '', $options);
            return;
        }

        /*
         * Result sets are not guaranteed to be homogeneous - inventory:list for example builds one
         * cell per configured item field. Align every row to the union of all keys so the column
         * order is stable and no branch can read an undefined index.
         */
        $headers = self::collectHeaders($rows);
        $rows = array_map(
            static fn (array $row): array => self::alignRow($row, $headers),
            $rows
        );

        switch ($format) {
            case 'json':
                $output = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
                break;

            case 'csv':
                $stream = fopen('php://temp', 'w+');
                fputcsv($stream, array_map(array(self::class, 'neutralizeFormula'), $headers));
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(
                        static fn (mixed $value): string => self::neutralizeFormula(self::normalizeCell($value)),
                        $row
                    ));
                }
                rewind($stream);
                $output = stream_get_contents($stream);
                fclose($stream);
                break;

            case 'md':
                $output = '| ' . implode(' | ', array_map(array(self::class, 'escapeMarkdown'), $headers)) . " |\n";
                $output .= '|' . str_repeat('---|', count($headers)) . "\n";
                foreach ($rows as $row) {
                    $cells = array_map(
                        static fn (mixed $value): string => self::escapeMarkdown(self::normalizeCell($value)),
                        array_values($row)
                    );
                    $output .= '| ' . implode(' | ', $cells) . " |\n";
                }
                break;

            case 'dokuwiki':
                $output = '^ ' . implode(' ^ ', array_map(array(self::class, 'escapeDokuWiki'), $headers)) . " ^\n";
                foreach ($rows as $row) {
                    $cells = array_map(
                        static fn (mixed $value): string => self::escapeDokuWiki(self::normalizeCell($value)),
                        array_values($row)
                    );
                    $output .= '| ' . implode(' | ', $cells) . " |\n";
                }
                break;

            case 'record':
                $output = '';
                $lastRow = count($rows) - 1;

                foreach ($rows as $rowIndex => $row) {
                    $output .= self::renderRecord($row);

                    if ($rowIndex < $lastRow) {
                        $output .= PHP_EOL;
                    }
                }
                break;

            case 'table':
                $widths = array_fill(0, count($headers), 0);
                foreach ($headers as $index => $header) {
                    $widths[$index] = self::displayWidth($header);
                }
                foreach ($rows as $row) {
                    foreach (array_values($row) as $index => $value) {
                        $widths[$index] = max($widths[$index], self::displayWidth(self::normalizeCell($value)));
                    }
                }

                $output = '';
                $allRows = array_merge(array(array_combine($headers, $headers)), $rows);
                foreach ($allRows as $rowIndex => $row) {
                    $cells = array_values($row);
                    foreach ($cells as $index => $value) {
                        $output .= self::padCell(self::normalizeCell($value), $widths[$index] + 2);
                    }
                    $output = rtrim($output) . PHP_EOL;
                    if ($rowIndex === 0) {
                        foreach ($widths as $width) {
                            $output .= str_repeat('-', $width) . '  ';
                        }
                        $output = rtrim($output) . PHP_EOL;
                    }
                }
                break;

            default:
                throw new InvalidArgumentException(
                    'Unsupported output format "' . $format . '".'
                );
        }

        self::writeOutput($output, $options);
    }

    /**
     * Render one associative data record in a shell-friendly field/value layout.
     *
     * @param array<mixed> $record
     */
    private static function renderRecord(array $record, int $indent = 0): string
    {
        $output = '';
        $prefix = str_repeat(' ', $indent);

        foreach ($record as $field => $value) {
            if (!is_array($value)) {
                $output .= $prefix . $field . ': ' . self::normalizeCell($value) . PHP_EOL;
                continue;
            }

            $output .= $prefix . $field . ':' . PHP_EOL;

            if (array_is_list($value)) {
                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        $output .= str_repeat(' ', $indent + 2) . '-' . PHP_EOL;
                        $output .= self::renderRecord($entry, $indent + 4);
                    } else {
                        $output .= str_repeat(' ', $indent + 2)
                            . '- ' . self::normalizeCell($entry) . PHP_EOL;
                    }
                }
            } else {
                $output .= self::renderRecord($value, $indent + 2);
            }
        }

        return $output;
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function writeValue(mixed $value, array $options, string $defaultFormat = 'text'): void
    {
        $format = strtolower(self::optionString($options, 'format', $defaultFormat));

        if ($format === 'json') {
            self::writeOutput(
                json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                $options
            );
            return;
        }

        if (is_array($value)) {
            self::writeRows(array($value), $format, $options);
            return;
        }

        self::writeOutput(self::normalizeCell($value) . PHP_EOL, $options);
    }

    /**
     * Determine where an exported file should be written.
     *
     * An empty --output writes $filename into the working directory. A directory given as --output
     * receives the natural filename, so "--output=/var/backups/" behaves as a user expects instead
     * of failing to create a file with that name.
     *
     * @param array<string,mixed> $options
     */
    public static function resolveOutputPath(array $options, string $filename): string
    {
        $target = self::optionString($options, 'output');

        if ($target === '') {
            return getcwd() . DIRECTORY_SEPARATOR . $filename;
        }

        if (is_dir($target)) {
            return rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $filename;
        }

        return $target;
    }

    /**
     * Restrict an exported file to the current user. Used for exports that carry secrets, such as
     * a database dump or a PKCS#12 container holding a private key.
     */
    public static function protectExportedFile(string $path): void
    {
        if (is_file($path)) {
            @chmod($path, 0600);
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function writeSuccess(string $message, array $options): void
    {
        /*
         * A caller that asked for JSON has to receive JSON from every command, otherwise a script
         * gets an object from group:add and an English sentence from group:update. The confirmation
         * is the result document of a command that has nothing else to report.
         */
        if (strtolower(self::optionString($options, 'format')) === 'json') {
            self::writeOutput(
                json_encode(
                    array('success' => true, 'message' => $message),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL,
                $options,
                false
            );
            return;
        }

        if (!self::optionBool($options, 'quiet', false)) {
            self::writeOutput($message . PHP_EOL, $options, false);
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function writeOutput(string $content, array $options, bool $honorOutputFile = true): void
    {
        $filename = $honorOutputFile ? self::optionString($options, 'output') : '';

        if ($filename !== '') {
            if (file_put_contents($filename, $content) === false) {
                throw new RuntimeException('Could not write output file "' . $filename . '".');
            }
            return;
        }

        echo $content;
    }

    /**
     * Return the union of all row keys, preserving first-seen order.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function collectHeaders(array $rows): array
    {
        $headers = array();

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $headers[(string)$key] = true;
            }
        }

        return array_keys($headers);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $headers
     * @return array<string,mixed>
     */
    private static function alignRow(array $row, array $headers): array
    {
        $aligned = array();

        foreach ($headers as $header) {
            $aligned[$header] = $row[$header] ?? '';
        }

        return $aligned;
    }

    /**
     * Number of terminal columns a value occupies. strlen() counts bytes, so every umlaut in a
     * role, category or user name would shift the following columns of a text table.
     */
    private static function displayWidth(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    private static function padCell(string $value, int $width): string
    {
        $padding = $width - self::displayWidth($value);

        return $padding > 0 ? $value . str_repeat(' ', $padding) : $value;
    }

    /**
     * Prevent a spreadsheet from interpreting an exported cell as a formula.
     *
     * A CSV export is normally opened in a spreadsheet application, which treats a leading =, +, -
     * or @ as the start of a formula. Admidio content such as a profile field or a role name is
     * free text and must never be evaluated, so such a cell is prefixed with a single quote.
     */
    private static function neutralizeFormula(string $value): string
    {
        // A plain number such as -5 is data, not a formula, and must stay numeric in the export.
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        if (str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }

        return $value;
    }

    private static function normalizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            return implode(', ', array_map(array(self::class, 'normalizeCell'), $value));
        }

        return (string)$value;
    }
}
