<?php
/**
 * Base test case for CLI regression tests
 * Handles both in-process and subprocess CLI execution
 */

namespace Admidio\Tests\Support;

use Symfony\Component\Process\Process;

abstract class CliTestCase extends DatabaseTestCase
{
    /**
     * Execute CLI command in-process
     * Fast, can participate in test transaction
     *
     * @param string $command CLI command name (e.g., 'user:show')
     * @param array $arguments Command arguments
     * @param array $options Command options
     * @return CliResult The command result
     */
    protected function executeCliCommand(string $command, array $arguments = [], array $options = []): CliResult
    {
        $result = new CliResult();

        try {
            // Build command line
            $commandLine = $this->buildCommandLine($command, $arguments, $options);

            // Execute through Admidio's CLI registry
            // This will be implemented in Phase 4 when CLI infrastructure is available
            $output = $this->runCliCommand($command, $arguments, $options);

            $result->setExitCode(0);
            $result->setOutput($output);
            $result->setSuccess(true);
        } catch (\Exception $e) {
            $result->setExitCode(1);
            $result->setOutput($e->getMessage());
            $result->setSuccess(false);
            $result->setException($e);
        }

        return $result;
    }

    /**
     * Execute CLI command as subprocess
     * Realistic, exercises real bootstrap and autoloading
     *
     * @param string $command CLI command name
     * @param array $arguments Command arguments
     * @param array $options Command options
     * @return CliResult The command result
     */
    protected function executeCliSubprocess(string $command, array $arguments = [], array $options = []): CliResult
    {
        $result = new CliResult();

        // Build command line
        $commandLine = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/index.php', // or appropriate CLI entry point
        ];

        // Add command
        $commandLine[] = $command;

        // Add arguments
        foreach ($arguments as $key => $value) {
            if (is_numeric($key)) {
                $commandLine[] = $value;
            } else {
                $commandLine[] = "--$key=$value";
            }
        }

        // Add options
        foreach ($options as $key => $value) {
            if ($value === true) {
                $commandLine[] = "--$key";
            } else {
                $commandLine[] = "--$key=$value";
            }
        }

        // Add format option for testing (usually JSON)
        if (!isset($options['format'])) {
            $commandLine[] = '--format=json';
        }

        // Run subprocess
        $process = new Process($commandLine);
        $process->run();

        $result->setExitCode($process->getExitCode());
        $result->setOutput($process->getOutput());
        $result->setErrorOutput($process->getErrorOutput());
        $result->setSuccess($process->isSuccessful());

        return $result;
    }

    /**
     * Assert CLI command succeeded
     *
     * @param CliResult $result The command result
     * @param string $message Optional message
     */
    protected function assertCliSuccess(CliResult $result, string $message = ''): void
    {
        $this->assertTrue(
            $result->isSuccess(),
            $message ?: sprintf(
                "CLI command failed with exit code %d.\nOutput: %s\nError: %s",
                $result->getExitCode(),
                $result->getOutput(),
                $result->getErrorOutput()
            )
        );
    }

    /**
     * Assert CLI command failed
     *
     * @param CliResult $result The command result
     * @param int $expectedExitCode Optional expected exit code
     * @param string $message Optional message
     */
    protected function assertCliFails(CliResult $result, int $expectedExitCode = 1, string $message = ''): void
    {
        $this->assertFalse(
            $result->isSuccess(),
            $message ?: 'CLI command should have failed'
        );

        if ($expectedExitCode !== 0) {
            $this->assertEquals(
                $expectedExitCode,
                $result->getExitCode(),
                $message ?: "Expected exit code $expectedExitCode, got {$result->getExitCode()}"
            );
        }
    }

    /**
     * Assert JSON output contains specific key/value
     *
     * @param CliResult $result The command result
     * @param string $key JSON key path (e.g., 'user.id')
     * @param mixed $expectedValue Expected value
     * @param string $message Optional message
     */
    protected function assertCliJsonContains(CliResult $result, string $key, $expectedValue, string $message = ''): void
    {
        $json = json_decode($result->getOutput(), true);
        $this->assertIsArray($json, 'CLI output is not valid JSON');

        $value = $this->getJsonValue($json, $key);
        $this->assertEquals(
            $expectedValue,
            $value,
            $message ?: "JSON key '$key' expected '$expectedValue', got '$value'"
        );
    }

    /**
     * Build command line string
     */
    private function buildCommandLine(string $command, array $arguments, array $options): string
    {
        $line = $command;

        foreach ($arguments as $key => $value) {
            if (is_numeric($key)) {
                $line .= " '$value'";
            } else {
                $line .= " --$key='$value'";
            }
        }

        foreach ($options as $key => $value) {
            if ($value === true) {
                $line .= " --$key";
            } else {
                $line .= " --$key='$value'";
            }
        }

        return $line;
    }

    /**
     * Run CLI command (placeholder for actual implementation)
     */
    private function runCliCommand(string $command, array $arguments, array $options): string
    {
        // This will be implemented when CLI infrastructure is available
        throw new \RuntimeException('In-process CLI execution not yet implemented');
    }

    /**
     * Get value from JSON using dot notation
     */
    private function getJsonValue(array $json, string $key)
    {
        $keys = explode('.', $key);
        $value = $json;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

/**
 * CLI command result wrapper
 */
class CliResult
{
    private int $exitCode = 0;
    private string $output = '';
    private string $errorOutput = '';
    private bool $success = false;
    private ?\Exception $exception = null;

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function setExitCode(int $exitCode): self
    {
        $this->exitCode = $exitCode;
        return $this;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function setOutput(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    public function setErrorOutput(string $errorOutput): self
    {
        $this->errorOutput = $errorOutput;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getException(): ?\Exception
    {
        return $this->exception;
    }

    public function setException(?\Exception $exception): self
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * Get JSON-decoded output
     */
    public function getJsonOutput(): ?array
    {
        if (empty($this->output)) {
            return null;
        }
        return json_decode($this->output, true);
    }
}
