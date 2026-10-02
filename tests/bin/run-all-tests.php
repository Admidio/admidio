#!/usr/bin/env php
<?php
/**
 * Admidio Regression Test Suite - Run All Suites
 *
 * Runs the Unit, Integration and CLI suites as three separate phpunit processes, so that a
 * constant one suite defines for its own purposes - DB_TYPE in particular, which the Hooks unit
 * tests fix at a SQLite-compatible value regardless of the configured test database engine - never
 * leaks into a later suite that needs the real configured value. A PHP constant cannot be
 * redefined, so the only way to give every suite its correct value is to give each one its own
 * process; see tests/bootstrap-admidio.php for the guard this depends on.
 *
 * Every suite always runs, even if an earlier one fails, so a single run reports all of them; the
 * script exits with the worst exit code so a failure anywhere still fails the overall run.
 */

$testsuites = array('Unit Tests', 'Integration Tests', 'CLI Tests');
$worstExitCode = 0;

foreach ($testsuites as $testsuite) {
    passthru('phpunit --testsuite=' . escapeshellarg($testsuite), $exitCode);
    $worstExitCode = max($worstExitCode, $exitCode);
}

exit($worstExitCode);
