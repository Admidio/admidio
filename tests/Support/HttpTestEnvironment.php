<?php

namespace Admidio\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** A disposable installation served by the real PHP HTTP SAPI, without application bypasses. */
final class HttpTestEnvironment
{
    public readonly string $directory;
    public readonly string $url;
    private string $prefix;
    private ?Process $server = null;
    private bool $prepared = false;
    private bool $created = false;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/env.php';
        admidioTestLoadEnvironment();
        \TestEnvironment::validateTestEnvironment();

        $root = dirname(__DIR__, 2);
        $data = realpath(admidioTestEnv('TEST_FILES_PATH'));
        if ($data === false || !str_starts_with($data, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('HTTP test files must be inside the checkout test directory.');
        }
        $this->prefix = 'ht' . bin2hex(random_bytes(4));
        $this->directory = $data . '/' . $this->prefix;

        try {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $error);
            if ($socket === false) {
                throw new RuntimeException('Cannot reserve a loopback port: ' . $error);
            }
            $this->url = 'http://' . stream_socket_get_name($socket, false);
            fclose($socket);
            if (!mkdir($this->directory, 0700)) {
                throw new RuntimeException('Cannot create the private HTTP runtime directory.');
            }
            $this->created = true;
            // Copy runtime files, never the checkout's config, uploads, .git or test secrets.
            foreach (['src', 'system', 'modules', 'install', 'languages', 'libs', 'themes', 'plugins', 'hooks', 'rss', 'vendor'] as $name) {
                $source = $root . '/' . $name;
                mkdir($this->directory . '/' . $name);
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                    $target = $this->directory . '/' . $name . '/' . substr($entry->getPathname(), strlen($source) + 1);
                    if ($entry->isDir()) {
                        mkdir($target);
                    } elseif (!copy($entry->getPathname(), $target)) {
                        throw new RuntimeException('Cannot copy HTTP runtime file: ' . $entry->getPathname());
                    }
                }
            }
            copy($root . '/index.php', $this->directory . '/index.php');
            mkdir($this->directory . '/adm_my_files', 0700);
            foreach (['templates', 'temp', 'logs', 'sessions'] as $name) {
                mkdir($this->directory . '/adm_my_files/' . $name, 0700);
            }
            $this->prepared = true;
            $this->fixture('install');
            $address = substr($this->url, strlen('http://'));
            $this->server = new Process([
                PHP_BINARY, '-d', 'session.save_path=' . $this->directory . '/adm_my_files/sessions',
                '-S', $address, '-t', $this->directory, $root . '/tests/Http/router.php',
            ], $this->directory);
            $this->server->setTimeout(null);
            $this->server->start();
            $deadline = microtime(true) + 10;
            do {
                if (!$this->server->isRunning()) {
                    throw new RuntimeException('HTTP server exited: ' . $this->server->getErrorOutput());
                }
                $connection = @stream_socket_client('tcp://' . $address, $errorCode, $error, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    return;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            throw new RuntimeException('HTTP server did not become ready.');
        } catch (Throwable $exception) {
            $failure = $this->failure('setup', $exception);
            try {
                $this->close();
            } catch (Throwable $cleanupException) {
                throw new RuntimeException($failure->getMessage() . "\nHTTP cleanup also failed: " . $cleanupException->getMessage(), 0, $exception);
            }
            throw $failure;
        }
    }

    private function fixture(string $action): void
    {
        self::runFixture($action, $this->directory, $this->prefix, $this->url);
    }

    private static function runFixture(string $action, string $directory, string $prefix, string $url): void
    {
        $process = new Process([
            PHP_BINARY, dirname(__DIR__) . '/Http/fixture.php', $action,
            $directory, $prefix, $url,
        ], dirname(__DIR__, 2));
        $process->setTimeout(120);
        $process->mustRun();
    }

    /** Only report request paths/statuses, never raw logs, query strings, cookies or configuration. */
    public function diagnostics(): string
    {
        $output = 'HTTP test server: ' . ($this->server?->isRunning() ? 'running' : 'stopped') . "\n";
        foreach (explode("\n", $this->server?->getErrorOutput() ?? '') as $line) {
            if (preg_match('/ADMIDIO_HTTP (\d{3}) ([A-Z]+) (\S+)/', $line, $matches)) {
                $output .= $matches[1] . ' ' . $matches[2] . ' ' . parse_url($matches[3], PHP_URL_PATH) . "\n";
            }
        }
        return $output;
    }

    public function writeDiagnostics(string $name, string $phase): string
    {
        $directory = dirname(__DIR__) . '/reports/http';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $file = $directory . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name) . '.log';
        file_put_contents($file, 'Phase: ' . $phase . "\nRuntime directory: " . $this->directory
            . "\nRecovery (same test database settings): TEST_DATABASE_ENGINE=" . escapeshellarg(admidioTestEnv('TEST_DATABASE_ENGINE'))
            . ' TEST_FILES_PATH=' . escapeshellarg(dirname($this->directory))
            . ' php tests/bin/cleanup-http-test.php ' . escapeshellarg($this->directory)
            . "\n" . $this->diagnostics());
        return $file;
    }

    private function failure(string $phase, Throwable $exception): RuntimeException
    {
        try {
            $report = $this->writeDiagnostics($phase . '-' . $this->prefix, $phase);
        } catch (Throwable $reportException) {
            $report = 'could not write HTTP diagnostics (' . get_class($reportException) . ')';
        }
        return new RuntimeException($exception->getMessage() . "\nHTTP runtime directory: " . $this->directory
            . "\nHTTP diagnostic: " . $report, 0, $exception);
    }

    /** Recover a retained installation without needing the original PHPUnit object. */
    public static function cleanupDirectory(string $directory): void
    {
        require_once dirname(__DIR__) . '/env.php';
        admidioTestLoadEnvironment();
        \TestEnvironment::validateTestEnvironment();
        $site = realpath($directory);
        $data = realpath(admidioTestEnv('TEST_FILES_PATH'));
        $root = dirname(__DIR__, 2);
        if ($site === false || $data === false || !str_starts_with($data, $root . DIRECTORY_SEPARATOR)
            || dirname($site) !== $data || is_link($directory) || !preg_match('/^ht[a-f0-9]{8}$/D', basename($site))) {
            throw new RuntimeException('Refusing an invalid HTTP cleanup directory.');
        }
        self::runFixture('drop', $site, basename($site), 'http://127.0.0.1');
        self::removeDirectory($site);
    }

    public function close(): void
    {
        try {
            $this->server?->stop();
            if ($this->prepared) {
                // Retain the bootstrap and configuration on failure so cleanup can be retried.
                $this->fixture('drop');
                $this->prepared = false;
            }
            if ($this->created) {
                self::removeDirectory($this->directory);
                $this->created = false;
            }
            $this->server = null;
        } catch (Throwable $exception) {
            throw $this->failure('cleanup', $exception);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                if ($entry->isDir() && !$entry->isLink()) {
                    rmdir($entry->getPathname());
                } else {
                    unlink($entry->getPathname());
                }
            }
            rmdir($directory);
        }
    }
}
