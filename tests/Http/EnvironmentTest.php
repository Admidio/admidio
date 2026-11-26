<?php

namespace Admidio\Tests\Http;

use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Support\HttpClient;
use Admidio\Tests\Support\HttpTestEnvironment;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class EnvironmentTest extends AdmidioTestCase
{
    public function testFailedCleanupRetainsInstallationAndCanBeRetried(): void
    {
        $environment = new HttpTestEnvironment();
        $autoload = $environment->directory . '/vendor/autoload.php';
        try {
            $response = (new HttpClient($environment->url))->request('/system/login.php?secret=private-test-value');
            $this->assertSame(200, $response->status);
            $response = (new HttpClient($environment->url))->request('/modules/does-not-exist.php?secret=private-test-value');
            $this->assertSame(404, $response->status);
            $diagnostics = $environment->diagnostics();
            $this->assertStringContainsString('200 GET /system/login.php', $diagnostics);
            $this->assertStringContainsString('404 GET /modules/does-not-exist.php', $diagnostics);
            $this->assertStringNotContainsString('private-test-value', $diagnostics);
            $this->assertStringNotContainsString('secret=', $diagnostics);
            // Make the cleanup subprocess fail before it can connect or drop tables.
            $this->assertTrue(rename($autoload, $autoload . '.disabled'));
            try {
                $environment->close();
                $this->fail('Cleanup must report a failed fixture subprocess.');
            } catch (RuntimeException $exception) {
                $this->assertInstanceOf(ProcessFailedException::class, $exception->getPrevious());
                $this->assertStringContainsString($environment->directory, $exception->getMessage());
                $report = dirname(__DIR__) . '/reports/http/cleanup-' . basename($environment->directory) . '.log';
                $this->assertFileExists($report);
                $this->assertStringContainsString('Phase: cleanup', file_get_contents($report));
                $this->assertStringContainsString('cleanup-http-test.php', file_get_contents($report));
            }
            $this->assertFileExists($environment->directory . '/adm_my_files/config.php');
            $this->assertFileExists($autoload . '.disabled');
        } finally {
            if (is_file($autoload . '.disabled')) {
                rename($autoload . '.disabled', $autoload);
            }
            $environment->close();
            if (isset($report) && is_file($report)) {
                unlink($report);
            }
        }
        $this->assertDirectoryDoesNotExist($environment->directory);
        // Teardown must be safe even if a caller has already closed the environment.
        $environment->close();
        $this->assertDirectoryDoesNotExist($environment->directory);
    }

    public function testSetupFailureProducesDiagnosticsAndRemovesPartialCopy(): void
    {
        // Fail copying before installation, without adding a test hook to the environment.
        $process = new Process([
            PHP_BINARY, '-d', 'disable_functions=copy', '-r',
            'require "tests/bootstrap.php"; new Admidio\\Tests\\Support\\HttpTestEnvironment();',
        ], dirname(__DIR__, 2));
        $process->run();
        $this->assertNotSame(0, $process->getExitCode());
        $output = $process->getErrorOutput() . $process->getOutput();
        $this->assertSame(1, preg_match('/HTTP diagnostic: (\S+\.log)/', $output, $matches), $output);
        $report = $matches[1];
        try {
            $this->assertFileExists($report);
            $diagnostics = file_get_contents($report);
            $this->assertStringContainsString('Phase: setup', $diagnostics);
            $this->assertStringContainsString('cleanup-http-test.php', $diagnostics);
            $this->assertSame(1, preg_match('/Runtime directory: ([^\r\n]+)/', $diagnostics, $directory));
            $this->assertDirectoryDoesNotExist($directory[1]);
        } finally {
            if (is_file($report)) {
                unlink($report);
            }
        }
    }

    public function testRetainedInstallationCanBeCleanedInAnotherProcess(): void
    {
        $environment = new HttpTestEnvironment();
        $autoload = $environment->directory . '/vendor/autoload.php';
        $report = dirname(__DIR__) . '/reports/http/cleanup-' . basename($environment->directory) . '.log';
        try {
            rename($autoload, $autoload . '.disabled');
            try {
                $environment->close();
                $this->fail('Expected a failed cleanup.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($environment->directory, $exception->getMessage());
            } finally {
                rename($autoload . '.disabled', $autoload);
            }
            $userVariable = 'TEST_DB_' . strtoupper(admidioTestEnv('TEST_DATABASE_ENGINE')) . '_USER';
            $wrongSettings = new Process([PHP_BINARY, 'tests/bin/cleanup-http-test.php', $environment->directory],
                dirname(__DIR__, 2), [$userVariable => 'not-the-original-test-user']);
            $wrongSettings->run();
            $this->assertSame(1, $wrongSettings->getExitCode());
            $this->assertStringContainsString('database settings do not match', $wrongSettings->getErrorOutput());
            $this->assertDirectoryExists($environment->directory);
            $process = new Process([PHP_BINARY, 'tests/bin/cleanup-http-test.php', $environment->directory], dirname(__DIR__, 2));
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertDirectoryDoesNotExist($environment->directory);
            $this->assertStringContainsString('HTTP test installation removed.', $process->getOutput());
        } finally {
            if (is_file($autoload . '.disabled')) {
                rename($autoload . '.disabled', $autoload);
            }
            if (is_dir($environment->directory)) {
                $environment->close();
            }
            if (is_file($report)) {
                unlink($report);
            }
        }
    }

    public function testCleanupCommandRejectsUnrelatedDirectory(): void
    {
        $process = new Process([PHP_BINARY, 'tests/bin/cleanup-http-test.php', __DIR__], dirname(__DIR__, 2));
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Refusing an invalid HTTP cleanup directory.', $process->getErrorOutput());
        $this->assertDirectoryExists(__DIR__);
    }
}
