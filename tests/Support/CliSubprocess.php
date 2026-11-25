<?php
/**
 * Runs the Admidio command line utility as a real process
 *
 * The tests that use this trait do not call into CliApplication, they start ./admidio the way an
 * administrator or a cron job does. That is only possible since the CLI takes --config: the entry
 * point otherwise reads adm_my_files/config.php, which on a developer machine describes a real
 * installation.
 *
 * The process opens its own connection, so it sees committed installation data and nothing a test
 * wrote inside its transaction. Database-only E2E workflows may deliberately write through the
 * subprocess when they clean up after themselves. File-writing commands are intentionally excluded,
 * because the real CLI bootstrap uses the checkout's adm_my_files rather than the test data folder.
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Cli\CliPolicy;
use Admidio\Infrastructure\Database;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Symfony\Component\Process\Process;
use RuntimeException;

trait CliSubprocess
{
    /**
     * Path of the configuration file that points the utility at the test database.
     */
    private static string $cliConfigurationFile = '';

    /**
     * Make sure the command line of this checkout may be used at all.
     *
     * The real ./admidio reads adm_my_files/cli-config.php of the code it executes, so these tests
     * depend on the configuration of the checkout and not on the data folder of the test run. The
     * file is written only when the checkout is not an installation of its own, which is the case
     * on a build server; a developer machine whose adm_my_files belongs to a real installation is
     * never modified, the prerequisite is stated instead.
     */
    protected static function ensureCliPolicy(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }
        $ensured = true;

        $policyFile = ADMIDIO_PATH . '/adm_my_files/' . CliPolicy::FILE_NAME;

        if (is_file($policyFile)) {
            if (CliPolicy::read(ADMIDIO_PATH)->isEnabled()) {
                return;
            }

            throw new RuntimeException(
                'The Admidio command line is disabled in ' . $policyFile . '. Set $gCliEnabled = true'
                . ' there to run the tests that execute ./admidio as a real process.'
            );
        }

        if (is_file(ADMIDIO_PATH . '/adm_my_files/config.php')) {
            throw new RuntimeException(
                'The tests that execute ./admidio as a real process need ' . $policyFile . ', and this'
                . ' checkout is an Admidio installation of its own, whose configuration the test suite'
                . ' must not write. Create the file with $gCliEnabled = true, or remove'
                . ' adm_my_files/config.php.'
            );
        }

        $written = @file_put_contents(
            $policyFile,
            '<?php' . PHP_EOL
            . '// Written by the Admidio regression test suite, see tests/Support/CliSubprocess.php.' . PHP_EOL
            . '$gCliEnabled = true;' . PHP_EOL
            . '$gCliLogFile = ' . chr(39) . chr(39) . ';' . PHP_EOL
        );

        if ($written === false) {
            throw new RuntimeException(
                'The tests that execute ./admidio as a real process need ' . $policyFile
                . ', which could not be written.'
            );
        }

        // the file belongs to this test run, so it does not stay behind in the checkout
        register_shutdown_function(static function () use ($policyFile): void {
            @unlink($policyFile);
        });
    }

    /**
     * Write the configuration file of the test database, once per PHPUnit process.
     *
     * The file is created by the Installation service, so it has the format the installation
     * writes rather than one this test invented.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    protected static function cliConfigurationFile(): string
    {
        if (self::$cliConfigurationFile !== '') {
            return self::$cliConfigurationFile;
        }

        $config = admidioTestDatabaseConfig();

        $installationConfig = InstallationConfig::fromArray(array(
            'dbType' => self::cliDatabaseType($config),
            'dbHost' => $config['host'],
            'dbPort' => $config['port'],
            'dbName' => $config['database'],
            'dbUsername' => $config['user'],
            'dbPassword' => $config['password'],
            'tablePrefix' => TABLE_PREFIX,
            'rootUrl' => 'http://localhost/admidio',
            'language' => 'en',
            'timezone' => 'UTC'
        ));

        self::$cliConfigurationFile = Installation::writeConfigFile(
            $installationConfig,
            ADMIDIO_PATH . FOLDER_DATA . '/config.php'
        );

        return self::$cliConfigurationFile;
    }

    /**
     * The Admidio name of the database system the test database runs on.
     *
     * @param array<string,mixed> $config Description of the test database
     */
    protected static function cliDatabaseType(array $config): string
    {
        if ($config['engine'] === 'mariadb') {
            return Database::DB_TYPE_MARIADB;
        }
        if ($config['engine'] === 'postgres') {
            return Database::DB_TYPE_PGSQL;
        }

        return Database::DB_TYPE_MYSQL;
    }

    /**
     * Run ./admidio with the given arguments and wait for it to finish.
     *
     * @param array<int,string> $arguments Everything behind the name of the program
     * @param string|null $configurationFile Configuration to use instead of the one of the test database
     * @param string|null $input What the command reads from STDIN, for the options that take a secret
     * @param int $timeout Seconds the command may take. An installation needs more than a query does.
     * @param array<string,string>|null $environment Environment variables on top of the inherited ones
     */
    protected static function runCli(
        array $arguments,
        ?string $configurationFile = null,
        ?string $input = null,
        int $timeout = 120,
        ?array $environment = null
    ): Process {
        self::ensureCliPolicy();

        $command = array_merge(
            array(
                PHP_BINARY,
                ADMIDIO_PATH . '/admidio',
                '--config=' . ($configurationFile ?? self::cliConfigurationFile())
            ),
            $arguments
        );

        /*
         * The subprocess reads adm_my_files/cli-config.php of the checkout, which on a developer
         * machine may configure option defaults. A test asserts the documented behaviour of the
         * command line and not the preferences of whoever runs it, so the defaults are switched
         * off; a test that needs one passes it in $environment.
         */
        $environment = array_merge(array('ADMIDIO_NO_DEFAULTS' => '1'), $environment ?? array());

        $process = new Process($command, ADMIDIO_PATH, $environment);
        $process->setTimeout($timeout);
        if ($input !== null) {
            $process->setInput($input);
        }
        $process->run();

        return $process;
    }

    /**
     * The output of a command that was asked for JSON.
     *
     * @return array<string,mixed>
     */
    protected function cliJson(Process $process): array
    {
        $decoded = json_decode($process->getOutput(), true);

        $this->assertIsArray(
            $decoded,
            'The command did not answer with JSON.' . PHP_EOL
            . 'Output: ' . $process->getOutput() . PHP_EOL
            . 'Errors: ' . $process->getErrorOutput()
        );

        return $decoded;
    }
}
