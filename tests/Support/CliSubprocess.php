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

use Admidio\Infrastructure\Database;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Symfony\Component\Process\Process;

trait CliSubprocess
{
    /**
     * Path of the configuration file that points the utility at the test database.
     */
    private static string $cliConfigurationFile = '';

    /**
     * Write the configuration file of the test database, once per PHPUnit process.
     *
     * The file is created by the Installation service, so it has the format the installation
     * writes rather than one this test invented.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    protected function cliConfigurationFile(): string
    {
        if (self::$cliConfigurationFile !== '') {
            return self::$cliConfigurationFile;
        }

        $config = admidioTestDatabaseConfig();

        if ($config['engine'] === 'mariadb') {
            $dbType = Database::DB_TYPE_MARIADB;
        } elseif ($config['engine'] === 'postgres') {
            $dbType = Database::DB_TYPE_PGSQL;
        } else {
            $dbType = Database::DB_TYPE_MYSQL;
        }

        $installationConfig = InstallationConfig::fromArray(array(
            'dbType' => $dbType,
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
            ADMIDIO_PATH . FOLDER_DATA . '/cli-config.php'
        );

        return self::$cliConfigurationFile;
    }

    /**
     * Run ./admidio with the given arguments and wait for it to finish.
     *
     * @param array<int,string> $arguments Everything behind the name of the program
     * @param string|null $configurationFile Configuration to use instead of the one of the test database
     */
    protected function runCli(array $arguments, ?string $configurationFile = null): Process
    {
        $command = array_merge(
            array(
                PHP_BINARY,
                ADMIDIO_PATH . '/admidio',
                '--config=' . ($configurationFile ?? $this->cliConfigurationFile())
            ),
            $arguments
        );

        $process = new Process($command, ADMIDIO_PATH);
        $process->setTimeout(120);
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
