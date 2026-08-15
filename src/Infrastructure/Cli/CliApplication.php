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
        array('name' => 'quiet', 'flag' => true, 'description' => 'Suppress non-essential success output.'),
        array('name' => 'no-interaction', 'flag' => true, 'description' => 'Never ask an interactive question.'),
        array('name' => 'yes', 'flag' => true, 'description' => 'Confirm destructive operations.'),
        array('name' => 'help', 'flag' => true, 'description' => 'Show help for the selected command.')
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

        $command = $this->findCommand($argv);
        if ($command === '') {
            $command = 'help';
        }

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

        if ($task['component'] !== null && !Component::isAdministrable($task['component'])) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        self::$currentCommand = $command;

        /*
         * --as is an impersonation switch, not an authentication. Record which command produced a
         * changelog record so an administrator can tell a headless change apart from one the user
         * made in the browser.
         */
        LogChanges::setOriginComment('CLI: ' . $command);

        $result = ($task['callback'])($input['arguments'], $input['options']);

        return is_int($result) ? $result : 0;
    }

    public static function currentCommand(): string
    {
        return self::$currentCommand;
    }

    /**
     * Extract the small set of options needed before the Admidio bootstrap is loaded.
     *
     * @param array<int,string> $argv
     * @return array{host:string,organization:string}
     */
    public static function getBootstrapOptions(array $argv): array
    {
        return array(
            'host' => self::readRawOption($argv, 'host'),
            'organization' => self::readRawOption($argv, 'organization')
        );
    }

    /**
     * @param array<int,string> $argv
     */
    private static function readRawOption(array $argv, string $name): string
    {
        $prefix = '--' . $name . '=';

        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            if (str_starts_with($argv[$index], $prefix)) {
                return substr($argv[$index], strlen($prefix));
            }

            if ($argv[$index] === '--' . $name && isset($argv[$index + 1])) {
                return $argv[$index + 1];
            }
        }

        return '';
    }

    /**
     * @param array<int,string> $argv
     */
    private function findCommand(array $argv): string
    {
        $globalFlags = array('quiet', 'no-interaction', 'yes', 'help');
        $globalValueOptions = array('host', 'organization', 'as', 'format', 'output');

        for ($index = 1, $count = count($argv); $index < $count; ++$index) {
            $token = $argv[$index];

            if ($token === '--') {
                return $argv[$index + 1] ?? '';
            }

            if (!str_starts_with($token, '--')) {
                return $token;
            }

            $optionName = substr($token, 2);
            if (str_contains($optionName, '=')) {
                continue;
            }

            if (in_array($optionName, $globalFlags, true)) {
                continue;
            }

            if (in_array($optionName, $globalValueOptions, true)) {
                ++$index;
                continue;
            }

            // Unknown pre-command options cannot be classified safely. Leave validation to parseInput.
            return '';
        }

        return '';
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
            require_once $registrationFile;
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

    public static function resolveUser(string $reference): User
    {
        global $gDb, $gCurrentOrgId, $gProfileFields;

        if (!isset($gProfileFields) || !$gProfileFields instanceof ProfileFields) {
            $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
        }

        if (ctype_digit($reference)) {
            $statement = $gDb->queryPrepared(
                'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_id = ?',
                array((int)$reference)
            );
        } else {
            $statement = $gDb->queryPrepared(
                'SELECT usr_id
                   FROM ' . TBL_USERS . '
                  WHERE usr_uuid = ?
                     OR UPPER(usr_login_name) = UPPER(?)',
                array($reference, $reference)
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

    public static function validateDate(string $date, string $label = 'date'): string
    {
        $object = \DateTime::createFromFormat('!Y-m-d', $date);
        if ($object === false || $object->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException($label . ' must use YYYY-MM-DD.');
        }

        return $date;
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
