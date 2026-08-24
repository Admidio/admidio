<?php

namespace Admidio\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Base class for integration tests that deliberately exercise production filesystem code.
 *
 * The guard is intentionally strict: destructive cleanup is only allowed below
 * tests/adm_my_files and only if the committed marker file is present.
 */
abstract class FilesystemTestCase extends AdministratorTestCase
{
    private string $testDataRoot = '';

    /** @var array<int,string> */
    private array $cleanupPaths = array();

    protected function setUp(): void
    {
        parent::setUp();

        $expectedRoot = realpath(ADMIDIO_PATH . '/tests/adm_my_files');
        $configuredRoot = realpath(ADMIDIO_PATH . FOLDER_DATA);

        if ($expectedRoot === false || $configuredRoot === false || $configuredRoot !== $expectedRoot) {
            throw new RuntimeException(
                'Filesystem regression tests require FOLDER_DATA to resolve to tests/adm_my_files.'
            );
        }

        if (!is_file($configuredRoot . '/.admidio-regression-test')) {
            throw new RuntimeException(
                'Filesystem regression test marker is missing; refusing destructive test operations.'
            );
        }

        $this->testDataRoot = $configuredRoot;

        $tempPath = ADMIDIO_PATH . FOLDER_TEMP_DATA;
        if (!is_dir($tempPath) && !mkdir($tempPath, 0775, true) && !is_dir($tempPath)) {
            throw new RuntimeException('Could not create the regression-test temporary directory.');
        }
        $this->assertPathInsideTestDataRoot($tempPath);
    }

    protected function getTestDataRoot(): string
    {
        return $this->testDataRoot;
    }

    protected function createIsolatedDirectory(string $prefix): string
    {
        $path = $this->testDataRoot . '/' . $prefix . '-' . bin2hex(random_bytes(6));
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Could not create regression-test fixture directory: ' . $path);
        }

        $this->registerCleanupPath($path);

        return $path;
    }

    protected function createFixtureFile(string $directory, string $filename, string $contents): string
    {
        $this->assertPathInsideTestDataRoot($directory);

        $path = $directory . '/' . $filename;
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not create regression-test fixture file: ' . $path);
        }

        return $path;
    }

    protected function registerCleanupPath(string $path): void
    {
        $this->assertPathInsideTestDataRoot($path);
        $this->cleanupPaths[] = $path;
    }

    protected function assertPathInsideTestDataRoot(string $path): void
    {
        $root = rtrim(str_replace('\\', '/', $this->testDataRoot), '/');
        $candidate = str_replace('\\', '/', $path);

        $resolved = realpath($path);
        if ($resolved !== false) {
            $candidate = str_replace('\\', '/', $resolved);
        } else {
            $parent = realpath(dirname($path));
            if ($parent !== false) {
                $candidate = str_replace('\\', '/', $parent) . '/' . basename($path);
            }
        }

        if ($candidate !== $root && !str_starts_with($candidate, $root . '/')) {
            throw new RuntimeException(
                'Refusing filesystem regression operation outside tests/adm_my_files: ' . $path
            );
        }
    }

    protected function tearDown(): void
    {
        $cleanupFailure = null;

        try {
            foreach (array_reverse(array_unique($this->cleanupPaths)) as $path) {
                try {
                    $this->removePath($path);
                    if (file_exists($path) || is_link($path)) {
                        throw new RuntimeException('Cleanup did not remove: ' . $path);
                    }
                } catch (\Throwable $exception) {
                    $cleanupFailure ??= $exception;
                }
            }
        } finally {
            parent::tearDown();
        }

        if ($cleanupFailure !== null) {
            throw $cleanupFailure;
        }
    }

    private function removePath(string $path): void
    {
        $this->assertPathInsideTestDataRoot($path);

        if (is_link($path) || is_file($path)) {
            if (!@unlink($path) && (file_exists($path) || is_link($path))) {
                throw new RuntimeException('Could not delete regression-test file: ' . $path);
            }
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $entryPath = $entry->getPathname();
            $this->assertPathInsideTestDataRoot($entryPath);

            if ($entry->isLink() || $entry->isFile()) {
                if (!@unlink($entryPath) && file_exists($entryPath)) {
                    throw new RuntimeException('Could not delete regression-test file: ' . $entryPath);
                }
            } elseif (!@rmdir($entryPath) && is_dir($entryPath)) {
                throw new RuntimeException('Could not delete regression-test directory: ' . $entryPath);
            }
        }

        if (!@rmdir($path) && is_dir($path)) {
            throw new RuntimeException('Could not delete regression-test directory: ' . $path);
        }
    }
}
