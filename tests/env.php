<?php
/**
 * Environment configuration of the regression test suite.
 *
 * The values are read from .env.test, but a variable that is already present in the process
 * environment wins over the file. A CI job therefore configures a run through env: and needs no
 * .env.test at all, while a local checkout keeps working with the file alone.
 */

/**
 * Load .env.test into the process environment and verify that a test run is configured.
 *
 * @param string|null $envFile Path of the environment file, by default .env.test in the repository root
 * @throws RuntimeException if neither the environment nor the file configures the run
 */
function admidioTestLoadEnvironment(?string $envFile = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    if ($envFile === null) {
        $envFile = dirname(__DIR__) . '/.env.test';
    }

    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            $value = trim($value);
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }

    foreach (array('TEST_DATABASE_ENGINE', 'TEST_FILES_PATH') as $variable) {
        if (admidioTestEnv($variable) === '') {
            throw new RuntimeException(
                $variable . " is not set.\n"
                . "Configure the test run in the environment or in .env.test:\n"
                . "  cp .env.test.example .env.test"
            );
        }
    }

    $loaded = true;
}

/**
 * Read one test environment variable.
 *
 * @param string $name Name of the variable
 * @param string $default Value to use if the variable is unset or empty
 */
function admidioTestEnv(string $name, string $default = ''): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

/**
 * Read the connection settings of one database engine.
 *
 * @param string|null $engine mariadb, mysql or postgres, by default the engine of the current run
 * @return array<string,string|int>
 */
function admidioTestDatabaseConfig(?string $engine = null): array
{
    if ($engine === null || $engine === '') {
        $engine = admidioTestEnv('TEST_DATABASE_ENGINE', 'mariadb');
    }

    $prefix = 'TEST_DB_' . strtoupper($engine);

    return array(
        'engine' => $engine,
        'host' => admidioTestEnv($prefix . '_HOST', '127.0.0.1'),
        'port' => (int) admidioTestEnv($prefix . '_PORT', $engine === 'postgres' ? '5432' : '3306'),
        'user' => admidioTestEnv($prefix . '_USER', 'admidio'),
        'password' => admidioTestEnv($prefix . '_PASS'),
        'database' => admidioTestEnv($prefix . '_NAME', 'admidio_test')
    );
}

/**
 * The data folder of the test run, as the path below the repository root that Admidio appends to
 * ADMIDIO_PATH.
 *
 * Admidio addresses everything it writes as ADMIDIO_PATH . FOLDER_DATA, so the constant has to
 * point at the directory of the test run. Otherwise TEST_FILES_PATH is decorative and the run
 * writes into the adm_my_files of the checkout, which on a developer machine is a real
 * installation.
 *
 * @param string $admidioRoot Path of the repository
 * @throws RuntimeException if the directory is not inside the repository
 */
function admidioTestDataFolder(string $admidioRoot): string
{
    $path = admidioTestEnv('TEST_FILES_PATH', './tests/adm_my_files');

    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    $folder = realpath($path);
    $root = realpath($admidioRoot);

    if ($folder === false || $root === false || !str_starts_with($folder, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException(
            "TEST_FILES_PATH has to name a directory inside the Admidio checkout, because Admidio\n"
            . "addresses its data folder as ADMIDIO_PATH . FOLDER_DATA.\n"
            . 'Current: ' . $path
        );
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', substr($folder, strlen($root)));
}
