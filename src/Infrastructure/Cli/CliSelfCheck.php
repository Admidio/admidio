<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Preferences\Service\PreferenceDefinitions;
use ReflectionClass;
use Throwable;

/**
 ***********************************************************************************************
 * Static self-validation of the Admidio command line.
 *
 * These checks need no database content and no acting user. They verify the wiring of the CLI
 * itself: that every registered command can be executed and documented, and that the source of the
 * CLI does not call a helper with the wrong number of arguments or reference a class or table
 * constant that does not exist.
 *
 * That is deliberately the class of defect that is invisible until the affected command is run:
 * PHP resolves a method call, a class name or a constant only at the moment it is reached, so a
 * command can be registered, listed and documented and still abort on its first real invocation.
 *
 * Behavioural tests of Admidio domain logic are explicitly not part of this. They need a scratch
 * database and fixtures and belong in the general Admidio test setup, which can drive the command
 * line through its machine-readable output instead of duplicating it here.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliSelfCheck
{
    /**
     * Run every check and return the problems that were found.
     *
     * @return array<int,array<string,string>> Empty when everything is in order.
     */
    public static function run(): array
    {
        $problems = array_merge(
            self::checkRegistry(),
            self::checkHelp(),
            self::checkPreferenceDefinitions(),
            self::checkSources()
        );

        usort(
            $problems,
            static fn (array $left, array $right): int =>
                array($left['check'], $left['location']) <=> array($right['check'], $right['location'])
        );

        return $problems;
    }

    /**
     * Counts of what was inspected, for the summary line.
     *
     * @return array<string,int>
     */
    public static function statistics(): array
    {
        $files = self::sourceFiles();
        $calls = 0;
        $imports = 0;

        foreach ($files as $file) {
            $source = (string)file_get_contents($file);
            $calls += preg_match_all('/self::\w+\(/', $source);
            $imports += preg_match_all('/^use\s+[A-Za-z0-9_\\\\]+/m', $source);
        }

        return array(
            'commands' => count(CliTaskRegistry::getAll()),
            'source_files' => count($files),
            'internal_calls' => $calls,
            'imports' => $imports
        );
    }

    /**
     * Every command must be executable and consistently declared.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkRegistry(): array
    {
        $problems = array();

        foreach (CliTaskRegistry::getAll() as $name => $task) {
            if (!is_callable($task['callback'])) {
                $problems[] = self::problem('registry', $name, 'The command has no callable callback.');
            }

            if ($task['aliasOf'] !== null && CliTaskRegistry::get($task['aliasOf']) === null) {
                $problems[] = self::problem(
                    'registry',
                    $name,
                    'Alias of the unknown command "' . $task['aliasOf'] . '".'
                );
            }

            if ($task['requiredRight'] !== null
                && !isset(CliTaskRegistry::ADDITIONAL_RIGHTS[$task['requiredRight']])) {
                $problems[] = self::problem(
                    'registry',
                    $name,
                    'Requires the unknown right "' . $task['requiredRight'] . '".'
                );
            }

            if ($task['component'] !== null && !$task['actorRequired']) {
                $problems[] = self::problem(
                    'registry',
                    $name,
                    'Has a component but requires no acting user, so the component could never be checked.'
                );
            }

            $problems = array_merge($problems, self::checkArguments($name, $task['arguments']));
            $problems = array_merge($problems, self::checkOptions($name, $task['options']));
            $problems = array_merge($problems, self::checkFormatMetadata($name, $task));
        }

        return $problems;
    }

    /**
     * @param array<int,array<string,mixed>> $arguments
     * @return array<int,array<string,string>>
     */
    private static function checkArguments(string $command, array $arguments): array
    {
        $problems = array();
        $optionalSeen = '';
        $multipleSeen = '';

        foreach ($arguments as $argument) {
            $argumentName = (string)($argument['name'] ?? '');
            if ($argumentName === '') {
                $problems[] = self::problem('arguments', $command, 'An argument has no name.');
                continue;
            }

            if ($multipleSeen !== '') {
                $problems[] = self::problem(
                    'arguments',
                    $command,
                    'Argument "' . $argumentName . '" follows the repeatable argument "' . $multipleSeen
                    . '" and can therefore never be filled.'
                );
            }

            if (($argument['required'] ?? false) === true && $optionalSeen !== '') {
                $problems[] = self::problem(
                    'arguments',
                    $command,
                    'Required argument "' . $argumentName . '" follows the optional argument "'
                    . $optionalSeen . '".'
                );
            }

            if (($argument['required'] ?? false) !== true) {
                $optionalSeen = $argumentName;
            }
            if (($argument['multiple'] ?? false) === true) {
                $multipleSeen = $argumentName;
            }
        }

        return $problems;
    }

    /**
     * @param array<int,array<string,mixed>> $options
     * @return array<int,array<string,string>>
     */
    private static function checkOptions(string $command, array $options): array
    {
        $problems = array();
        $seen = array();
        $globalNames = CliApplication::globalOptionNames();

        foreach ($options as $option) {
            $optionName = (string)($option['name'] ?? '');
            if ($optionName === '') {
                $problems[] = self::problem('options', $command, 'An option has no name.');
                continue;
            }

            if (isset($seen[$optionName])) {
                $problems[] = self::problem('options', $command, 'Option "--' . $optionName . '" is declared twice.');
            }
            $seen[$optionName] = true;

            // "format" and "output" are global but may be redeclared to document their values.
            if (in_array($optionName, $globalNames, true)
                && !in_array($optionName, array('format', 'output', 'yes'), true)) {
                $problems[] = self::problem(
                    'options',
                    $command,
                    'Option "--' . $optionName . '" shadows a global option.'
                );
            }

            if (($option['flag'] ?? false) === true && isset($option['values'])) {
                $problems[] = self::problem(
                    'options',
                    $command,
                    'Option "--' . $optionName . '" is a flag but declares a value list.'
                );
            }
        }

        return $problems;
    }

    /**
     * JSON API support is an explicit part of a command's declared format contract. The registry
     * must never infer or repair it after registration: help, validation and callers all need to
     * see the same metadata that the command author wrote.
     *
     * @param array<string,mixed> $task
     * @return array<int,array<string,string>>
     */
    private static function checkFormatMetadata(string $command, array $task): array
    {
        $problems = array();
        $formatOption = null;

        foreach ($task['options'] as $option) {
            if (($option['name'] ?? '') === 'format') {
                $formatOption = $option;
                break;
            }
        }

        if ($formatOption === null) {
            return $problems;
        }

        $values = is_array($formatOption['values'] ?? null) ? $formatOption['values'] : array();
        $hasJson = in_array('json', $values, true);
        $hasJsonApi = in_array('json-api', $values, true);

        if ($hasJson !== $hasJsonApi) {
            $problems[] = self::problem(
                'formats',
                $command,
                'Structured JSON commands must explicitly declare both "json" and "json-api".'
            );
        }

        $usage = (string)($task['usage'] ?? '');
        if ($hasJsonApi
            && preg_match('/--format=([^\]\s]+)/', $usage, $matches) === 1
            && $matches[1] !== 'FORMAT') {
            $usageFormats = explode('|', $matches[1]);
            if (!in_array('json-api', $usageFormats, true)) {
                $problems[] = self::problem(
                    'formats',
                    $command,
                    'The explicit --format list in the usage text omits "json-api".'
                );
            }
        }

        return $problems;
    }

    /**
     * Every command must be able to render its help. This exercises the argument and option
     * declarations, which are otherwise only read when the command is actually used.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkHelp(): array
    {
        $problems = array();
        $application = new CliApplication();

        foreach (array_keys(CliTaskRegistry::getAll()) as $name) {
            foreach (array('text', 'md', 'dokuwiki', 'json') as $format) {
                try {
                    ob_start();
                    $application->showHelp(array($name), array('format' => $format));
                    $rendered = (string)ob_get_clean();

                    if (trim($rendered) === '') {
                        $problems[] = self::problem('help', $name, 'The help is empty for format "' . $format . '".');
                    }
                } catch (Throwable $exception) {
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    $problems[] = self::problem(
                        'help',
                        $name,
                        'The help cannot be rendered as "' . $format . '": ' . $exception->getMessage()
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * Ensure that every seeded organization preference has an explicit supported/internal
     * classification. This prevents config:* from silently becoming a raw bypass when the web
     * preferences surface grows.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkPreferenceDefinitions(): array
    {
        return array_map(
            static fn (string $message): array => self::problem('preferences', 'PreferenceDefinitions', $message),
            PreferenceDefinitions::coverageProblems()
        );
    }

    /**
     * @return array<int,string>
     */
    private static function sourceFiles(): array
    {
        return glob(__DIR__ . '/*.php') ?: array();
    }

    /**
     * Static analysis of the CLI sources: internal call arity, imported classes and table constants.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkSources(): array
    {
        $problems = array();

        foreach (self::sourceFiles() as $file) {
            $source = (string)file_get_contents($file);
            $label = basename($file);

            $problems = array_merge($problems, self::checkCallArity($label, $source));
            $problems = array_merge($problems, self::checkSymbols($label, $source));
        }

        return $problems;
    }

    /**
     * Compare every self::method() call with the signature of that method.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkCallArity(string $label, string $source): array
    {
        $signatures = array();
        preg_match_all(
            '/(?:private|public|protected)\s+static\s+function\s+(\w+)\s*\(/',
            $source,
            $declarations,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        foreach ($declarations as $declaration) {
            $parameters = self::readBalanced($source, $declaration[0][1] + strlen($declaration[0][0]));
            $parameters = trim($parameters);

            if ($parameters === '') {
                $signatures[$declaration[1][0]] = array(0, 0);
                continue;
            }

            $parts = preg_split('/,(?![^(]*\))/', $parameters) ?: array();
            $required = 0;
            foreach ($parts as $part) {
                if (!str_contains($part, '=')) {
                    ++$required;
                }
            }
            $signatures[$declaration[1][0]] = array($required, count($parts));
        }

        $problems = array();
        preg_match_all('/self::(\w+)\(/', $source, $calls, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($calls as $call) {
            $name = $call[1][0];
            if (!isset($signatures[$name])) {
                continue;
            }

            $arguments = self::countArguments($source, $call[0][1] + strlen($call[0][0]));
            [$minimum, $maximum] = $signatures[$name];

            if ($arguments < $minimum || $arguments > $maximum) {
                $line = substr_count(substr($source, 0, $call[0][1]), "\n") + 1;
                $problems[] = self::problem(
                    'arity',
                    $label . ':' . $line,
                    'self::' . $name . '() is called with ' . $arguments . ' argument(s) but expects '
                    . $minimum . ' to ' . $maximum . '.'
                );
            }
        }

        return $problems;
    }

    /**
     * Verify that imported classes and used table constants exist.
     *
     * @return array<int,array<string,string>>
     */
    private static function checkSymbols(string $label, string $source): array
    {
        $problems = array();

        $imported = array();
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $uses, PREG_SET_ORDER);

        foreach ($uses as $use) {
            $className = $use[1];
            $imported[$use[2] ?? substr((string)strrchr('\\' . $className, '\\'), 1)] = true;

            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                $problems[] = self::problem(
                    'imports',
                    $label,
                    'The imported class "' . $className . '" does not exist.'
                );
            }
        }

        // Classes of this namespace do not need an import.
        foreach ((array)glob(__DIR__ . '/*.php') as $sibling) {
            $imported[basename((string)$sibling, '.php')] = true;
        }

        foreach (array_unique(self::matches('/new\s+([A-Z][A-Za-z0-9_]*)\s*\(/', $source)) as $instantiated) {
            if (!isset($imported[$instantiated])) {
                $problems[] = self::problem(
                    'imports',
                    $label,
                    'The class "' . $instantiated . '" is instantiated but not imported.'
                );
            }
        }

        foreach (array_unique(self::matches('/\b(TBL_[A-Z0-9_]+)\b/', $source)) as $constant) {
            if (!defined($constant)) {
                $problems[] = self::problem(
                    'constants',
                    $label,
                    'The table constant "' . $constant . '" is not defined.'
                );
            }
        }

        return $problems;
    }

    /**
     * @return array<int,string>
     */
    private static function matches(string $pattern, string $source): array
    {
        preg_match_all($pattern, $source, $found);

        return $found[1] ?? array();
    }

    /**
     * Return the text up to the parenthesis that closes the one opened before $start.
     */
    private static function readBalanced(string $source, int $start): string
    {
        $depth = 1;
        $result = '';
        $length = strlen($source);

        for ($index = $start; $index < $length && $depth > 0; ++$index) {
            $character = $source[$index];

            if ($character === '(') {
                ++$depth;
            } elseif ($character === ')') {
                --$depth;
                if ($depth === 0) {
                    break;
                }
            }

            $result .= $character;
        }

        return $result;
    }

    /**
     * Count the top-level arguments of a call whose opening parenthesis ends at $start.
     */
    private static function countArguments(string $source, int $start): int
    {
        $depth = 1;
        $arguments = 0;
        $hasContent = false;
        $length = strlen($source);
        $index = $start;

        while ($index < $length && $depth > 0) {
            $character = $source[$index];

            if ($character === "'" || $character === '"') {
                $quote = $character;
                ++$index;
                while ($index < $length && ($source[$index] !== $quote || $source[$index - 1] === '\\')) {
                    ++$index;
                }
                $hasContent = true;
            } elseif ($character === '(' || $character === '[' || $character === '{') {
                ++$depth;
                $hasContent = true;
            } elseif ($character === ')' || $character === ']' || $character === '}') {
                --$depth;
                if ($depth > 0) {
                    $hasContent = true;
                }
            } elseif ($character === ',' && $depth === 1) {
                ++$arguments;
            } elseif (!ctype_space($character)) {
                $hasContent = true;
            }

            ++$index;
        }

        return $hasContent ? $arguments + 1 : 0;
    }

    /**
     * @return array<string,string>
     */
    private static function problem(string $check, string $location, string $problem): array
    {
        return array('check' => $check, 'location' => $location, 'problem' => $problem);
    }
}
