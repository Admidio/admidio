<?php
namespace Admidio\Infrastructure\Cli;

use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use DateTimeZone;
use RuntimeException;

/**
 ***********************************************************************************************
 * Interactive questionnaire of install:check and install:run.
 *
 * The commands take every value of a new installation as an option, which is what a script needs
 * but a lot to type by hand. If a value is missing, this class asks for it, in the order of the
 * web installation wizard, and returns the completed command line.
 *
 * It runs before Admidio is bootstrapped, because the table prefix and the time zone are needed to
 * build the table names and the timestamps of the installation. Therefore it only uses plain PHP
 * and the two utility classes that need no Admidio constants. The authoritative validation is the
 * one of Admidio\InstallationUpdate\Service\Installation, which the command runs afterwards; the
 * checks here exist so that a typo is reported at the question instead of at the end of the
 * questionnaire.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliInstallationInput
{
    /**
     * Ask for every value of the installation that the command line doesn't contain.
     *
     * @param array<int,string> $argv Arguments of the process.
     * @param string $rootPath Path of the Admidio installation.
     * @return array<int,string> Returns the arguments with an option for every answer.
     */
    public static function complete(array $argv, string $rootPath): array
    {
        // --no-interaction is the promise of a caller that it answers no questions
        if (self::flag($argv, 'no-interaction')) {
            return $argv;
        }

        /*
         * An existing configuration file defines the database, the URL and the time zone of this
         * installation, so only the values of the organization are missing.
         */
        $configFileExists = is_file($rootPath . '/adm_my_files/config.php');
        $answers = array();

        self::write(PHP_EOL . 'Installation of Admidio. Press Ctrl+C to abort.' . PHP_EOL);

        self::ask($argv, $answers, 'language', 'Language of the organization', 'en', function (string $value) use ($rootPath): ?string {
            return array_key_exists($value, self::supportedLanguages($rootPath))
                ? null
                : 'Unknown language. Available: ' . implode(', ', array_keys(self::supportedLanguages($rootPath)));
        });

        if (!$configFileExists) {
            self::write(PHP_EOL . 'Address of the installation' . PHP_EOL);
            self::ask($argv, $answers, 'root-url', 'URL of this Admidio installation', '', function (string $value): ?string {
                return filter_var($value, FILTER_VALIDATE_URL) !== false
                    && in_array(parse_url($value, PHP_URL_SCHEME), array('http', 'https'), true)
                    ? null
                    : 'Enter the complete URL, for example https://www.example.org/admidio';
            });

            self::write(PHP_EOL . 'Access to the database' . PHP_EOL);
            self::ask($argv, $answers, 'db-type', 'Database system (mariadb, mysql, pgsql)', 'mariadb', function (string $value): ?string {
                return in_array($value, array('mariadb', 'mysql', 'pgsql'), true)
                    ? null
                    : 'Admidio only supports mariadb, mysql and pgsql.';
            });
            self::ask($argv, $answers, 'db-host', 'Host of the database server', 'localhost', function (string $value): ?string {
                return filter_var($value, FILTER_VALIDATE_DOMAIN) !== false || filter_var($value, FILTER_VALIDATE_IP) !== false
                    ? null
                    : 'Enter a host name or an IP address.';
            });
            self::ask($argv, $answers, 'db-port', 'Port of the database server, empty for the default port', '', function (string $value): ?string {
                if ($value === '') {
                    return null;
                }
                return is_numeric($value) && (int) $value >= 1 && (int) $value <= 65535 ? null : 'Enter a port between 1 and 65535.';
            });
            self::ask($argv, $answers, 'db-name', 'Name of the database', '', self::identifierValidator('database name', 64));
            self::ask($argv, $answers, 'db-user', 'User of the database', '', self::identifierValidator('user name', 64));
            self::askSecret($argv, $answers, 'db-password', 'Password of the database user', false);
            self::ask($argv, $answers, 'table-prefix', 'Prefix of the Admidio tables', 'adm', self::identifierValidator('table prefix', 10));
        }

        self::write(PHP_EOL . 'Organization' . PHP_EOL);
        self::ask($argv, $answers, 'organization-shortname', 'Short name of the organization', '', function (string $value): ?string {
            return StringUtils::strValidCharacters($value, 'noSpecialChar')
                ? null
                : 'Allowed are letters, numbers and the special characters .-_+@';
        });
        self::ask($argv, $answers, 'organization-name', 'Name of the organization', '', self::notEmptyValidator());
        self::ask($argv, $answers, 'organization-email', 'Email address of the organization', '', self::emailValidator());

        if (!$configFileExists) {
            self::ask($argv, $answers, 'timezone', 'Time zone of the organization', date_default_timezone_get(), function (string $value): ?string {
                return in_array($value, DateTimeZone::listIdentifiers(), true)
                    ? null
                    : 'Unknown time zone. Use a name of https://www.php.net/manual/en/timezones.php';
            });
        }

        self::write(PHP_EOL . 'Administrator' . PHP_EOL);
        self::ask($argv, $answers, 'admin-last-name', 'Last name of the administrator', '', self::notEmptyValidator());
        self::ask($argv, $answers, 'admin-first-name', 'First name of the administrator', '', self::notEmptyValidator());
        self::ask($argv, $answers, 'admin-email', 'Email address of the administrator', '', self::emailValidator());
        self::ask($argv, $answers, 'admin-login', 'Login name of the administrator', '', function (string $value): ?string {
            return StringUtils::strValidCharacters($value, 'noSpecialChar')
                ? null
                : 'Allowed are letters, numbers and the special characters .-_+@';
        });
        // the password may not consist of the data of the administrator, so zxcvbn needs to know it
        self::askSecret($argv, $answers, 'admin-password', 'Password of the administrator', true, array(
            self::value($argv, $answers, 'admin-last-name'),
            self::value($argv, $answers, 'admin-first-name'),
            self::value($argv, $answers, 'admin-email'),
            self::value($argv, $answers, 'admin-login')
        ));

        self::write(PHP_EOL);

        foreach ($answers as $name => $value) {
            /*
             * The answers are appended to the arguments of this process, they never become part of
             * the command line of the operating system, so a password stays invisible to others.
             */
            $argv[] = '--' . $name . '=' . $value;
        }

        return $argv;
    }

    /**
     * Ask for one value, until the answer is valid.
     *
     * @param array<int,string> $argv
     * @param array<string,string> $answers
     * @param callable|null $validator Returns an error text for an invalid answer, null if it is valid.
     */
    private static function ask(array $argv, array &$answers, string $option, string $question, string $default, ?callable $validator = null): void
    {
        if (self::option($argv, $option) !== null) {
            return;
        }

        while (true) {
            $answer = self::read($question . ($default === '' ? '' : ' [' . $default . ']') . ': ');

            if ($answer === '') {
                $answer = $default;
            }

            $error = $validator === null ? null : $validator($answer);
            if ($error === null) {
                $answers[$option] = $answer;
                return;
            }

            self::write($error . PHP_EOL);
        }
    }

    /**
     * Ask for a password without showing it. A password of the installation is repeated, a password
     * that only has to match an existing database is not.
     *
     * @param array<int,string> $argv
     * @param array<string,string> $answers
     * @param array<int,string> $userData Values that a password may not consist of.
     */
    private static function askSecret(array $argv, array &$answers, string $option, string $question, bool $repeat, array $userData = array()): void
    {
        if (self::option($argv, $option) !== null || self::flag($argv, $option . '-stdin')) {
            return;
        }

        while (true) {
            $answer = self::read($question . ': ', true);

            if (!$repeat) {
                $answers[$option] = $answer;
                return;
            }

            if (strlen($answer) < 8) {
                self::write('The password must have at least 8 characters.' . PHP_EOL);
                continue;
            }
            if (PasswordUtils::passwordStrength($answer, $userData) < 1) {
                self::write('The password is not secure enough.' . PHP_EOL);
                continue;
            }
            if ($answer !== self::read('Repeat the password: ', true)) {
                self::write('The passwords are not equal.' . PHP_EOL);
                continue;
            }

            $answers[$option] = $answer;
            return;
        }
    }

    /**
     * @return callable
     */
    private static function identifierValidator(string $label, int $maxLength): callable
    {
        return function (string $value) use ($label, $maxLength): ?string {
            if ($value === '' || strlen($value) > $maxLength) {
                return 'Enter a ' . $label . ' of 1 to ' . $maxLength . ' characters.';
            }

            return preg_match('/^[a-zA-Z0-9_$@-]+$/', $value) === 1
                ? null
                : 'Allowed are letters, numbers and the special characters _$@-';
        };
    }

    /**
     * @return callable
     */
    private static function notEmptyValidator(): callable
    {
        return function (string $value): ?string {
            return trim($value) === '' ? 'The value must not be empty.' : null;
        };
    }

    /**
     * @return callable
     */
    private static function emailValidator(): callable
    {
        return function (string $value): ?string {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter a valid email address.';
        };
    }

    /**
     * Read one line from the input of the process. A password is not echoed, which is only possible
     * through stty and therefore not on Windows.
     */
    private static function read(string $question, bool $secret = false): string
    {
        self::write($question);

        $restore = null;
        if ($secret && PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
            $settings = @shell_exec('stty -g 2>/dev/null');
            if (is_string($settings) && trim($settings) !== '') {
                $restore = trim($settings);
                @shell_exec('stty -echo 2>/dev/null');
            }
        }

        try {
            $answer = fgets(STDIN);
        } finally {
            if ($restore !== null) {
                @shell_exec('stty ' . escapeshellarg($restore) . ' 2>/dev/null');
                // the newline of the user was swallowed together with the echo
                self::write(PHP_EOL);
            }
        }

        if ($answer === false) {
            throw new RuntimeException(
                'The installation needs more values, but there is no more input. Give the missing values '
                . 'as options, or use --no-interaction to get the name of the first missing option.'
            );
        }

        return trim($answer);
    }

    private static function write(string $text): void
    {
        fwrite(STDERR, $text);
    }

    /**
     * Read the value of an option out of the arguments, in the two notations that the command line
     * of Admidio accepts.
     *
     * @param array<int,string> $argv
     * @return string|null Returns null if the option was not used.
     */
    private static function option(array $argv, string $name): ?string
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

        return null;
    }

    /**
     * Value of an option, no matter whether it was given on the command line or answered here.
     *
     * @param array<int,string> $argv
     * @param array<string,string> $answers
     */
    private static function value(array $argv, array $answers, string $name): string
    {
        return $answers[$name] ?? self::option($argv, $name) ?? '';
    }

    /**
     * @param array<int,string> $argv
     */
    private static function flag(array $argv, string $name): bool
    {
        return in_array('--' . $name, $argv, true);
    }

    /**
     * @return array<string,array<string,string>> Returns the languages that Admidio supports.
     */
    private static function supportedLanguages(string $rootPath): array
    {
        require $rootPath . '/languages/languages.php';

        return $gSupportedLanguages;
    }
}
