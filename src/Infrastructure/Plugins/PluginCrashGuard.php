<?php

namespace Admidio\Infrastructure\Plugins;

use Error;
use Throwable;

/**
 * Notices when a plugin crashes Admidio and keeps it out until somebody decides to try it again.
 *
 * PluginLoader::load() already catches what a plugin throws while it is being loaded. What it cannot
 * catch is everything after that, and everything PHP does not throw at all:
 *
 * - a fatal error (E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), for example a class whose
 *   method signature does not match the Admidio class it extends any more;
 * - a PHP **Error** (TypeError, a call to an undefined method, ...) that nobody caught, in a hook,
 *   in an autoloaded class or on a page of the plugin.
 *
 * Such a crash ends the request, and if the plugin is loaded on every request it ends every request,
 * including the ones of the plugin administration that could switch it off. So the guard writes the
 * crash to **adm_my_files/plugins-failed.json**, and PluginRegistry::getLoadable() leaves the plugin
 * out - and every plugin that requires it - from the next request on.
 *
 * The record is installation-wide and deliberately not a preference: whether a plugin is enabled is
 * the decision of an organization, it is written to the changelog, and a crash must not change it.
 * An entry only applies to the plugin version and the Admidio version it was recorded with, so
 * updating either gives the plugin a new chance. The administrators are shown the crash and can
 * remove the entry with "Try again".
 *
 * Not a crash, and therefore never recorded:
 * - an application exception. A hook that throws one fails the operation it extends, which is the
 *   hook policy, and a page reports it like any other error.
 * - exhausting the memory or the execution time. Whatever runs last is merely where the limit was
 *   reached, not what caused it.
 *
 * A crash is attributed to the plugin whose directory contains the file the error occurred in, or,
 * if that is a file of Admidio, the innermost stack frame inside a plugin directory.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginCrashGuard
{
    /**
     * Name of the record in the adm_my_files directory.
     */
    public const RECORD_FILE = 'plugins-failed.json';

    /**
     * The error types PHP cannot recover from. Anything else lets the request continue.
     */
    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR;

    /**
     * Fatal errors that say the request ran out of a resource. Whatever code ran last only happened
     * to be where the limit was reached.
     */
    private const LIMIT_MESSAGES = array('Allowed memory size of', 'Out of memory', 'Maximum execution time of');

    /**
     * An error message is shown to administrators and kept in a file, so it is cut to this length.
     */
    private const MAX_ERROR_LENGTH = 1000;

    /**
     * Overrides the record file. Only used by tests.
     */
    private static ?string $recordFile = null;

    /**
     * The decoded record, or **null** while it has not been read in this request.
     * @var array<string,array<string,mixed>>|null
     */
    private static ?array $records = null;

    private static bool $active = false;

    /**
     * Whether this request already recorded a crash. A request ends with its first crash, so a
     * second one can only be a consequence of it.
     */
    private static bool $recorded = false;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Start watching the request for plugin crashes. PluginLoader calls this before it runs any
     * plugin code; calling it again is harmless.
     *
     * A command line run is not watched: whoever started it sees the error, and a failed command
     * should not take a plugin away from the web interface.
     * @return void
     */
    public static function activate(): void
    {
        if (self::$active || PHP_SAPI === 'cli') {
            return;
        }
        self::$active = true;

        register_shutdown_function(array(self::class, 'handleShutdown'));
    }

    /**
     * Record a Throwable that ended the request, if it is a crash of a plugin. handleException()
     * calls this for every exception a script did not handle itself.
     * @param Throwable $throwable
     * @return void
     */
    public static function handleThrowable(Throwable $throwable): void
    {
        if (!self::$active) {
            return;
        }

        try {
            $crash = self::attributeThrowable($throwable);
            if ($crash !== null) {
                self::record($crash['plugin'], $crash['error'], $crash['file'], $crash['line']);
            }
        } catch (Throwable) {
            // The request is already failing; the guard must not replace its error with its own.
        }
    }

    /**
     * Shutdown function: record the fatal error that ended the request, if a plugin caused it.
     *
     * A Throwable that no script caught ends up here as well, as the fatal error "Uncaught ...". PHP
     * prints and logs it as usual; the guard only reads it. An exception handler could see the
     * object itself, but it would have to replace what PHP does with an uncaught exception.
     * @return void
     */
    public static function handleShutdown(): void
    {
        try {
            $crash = self::attributeError(error_get_last());
            if ($crash !== null) {
                self::record($crash['plugin'], $crash['error'], $crash['file'], $crash['line']);
            }
        } catch (Throwable) {
            // Nothing can be reported any more while PHP shuts down.
        }
    }

    /**
     * The plugin crash a Throwable stands for, or **null** if it is none.
     * @param Throwable $throwable
     * @return array{plugin: string, error: string, file: string, line: int}|null
     */
    public static function attributeThrowable(Throwable $throwable): ?array
    {
        if (!$throwable instanceof Error) {
            return null;
        }

        $locations = array(array('file' => $throwable->getFile(), 'line' => $throwable->getLine()));
        foreach ($throwable->getTrace() as $frame) {
            if (isset($frame['file'])) {
                $locations[] = array('file' => (string)$frame['file'], 'line' => (int)($frame['line'] ?? 0));
            }
        }

        return self::attribute(get_class($throwable) . ': ' . $throwable->getMessage(), $locations);
    }

    /**
     * The plugin crash an error of error_get_last() stands for, or **null** if it is none.
     * @param array{type: int, message: string, file: string, line: int}|null $error
     * @return array{plugin: string, error: string, file: string, line: int}|null
     */
    public static function attributeError(?array $error): ?array
    {
        if ($error === null || ($error['type'] & self::FATAL_ERRORS) === 0) {
            return null;
        }

        $message = (string)$error['message'];
        foreach (self::LIMIT_MESSAGES as $limit) {
            if (str_starts_with($message, $limit)) {
                return null;
            }
        }

        $locations = array(array('file' => (string)$error['file'], 'line' => (int)$error['line']));

        if (str_starts_with($message, 'Uncaught ')) {
            /*
             * A Throwable nobody caught. PHP puts its class and its stack trace into the message;
             * only an Error is a crash. The class is known, because it was thrown, so it is checked
             * without the autoloader.
             */
            if (preg_match('/^Uncaught ([\w\\\\]+)/', $message, $matches) !== 1
                || !class_exists($matches[1], false) || !is_a($matches[1], Error::class, true)) {
                return null;
            }

            $parts = explode("\nStack trace:\n", $message, 2);
            $message = substr($parts[0], strlen('Uncaught '));
            if (isset($parts[1]) && preg_match_all('/^#\d+ (.+)\((\d+)\): /m', $parts[1], $frames, PREG_SET_ORDER) > 0) {
                foreach ($frames as $frame) {
                    $locations[] = array('file' => $frame[1], 'line' => (int)$frame[2]);
                }
            }
        }

        return self::attribute($message, $locations);
    }

    /**
     * Find the plugin the first of the locations belongs to.
     * @param string $error
     * @param array<int,array{file: string, line: int}> $locations The location of the error first,
     *        then the stack frames from the innermost outwards.
     * @return array{plugin: string, error: string, file: string, line: int}|null
     */
    private static function attribute(string $error, array $locations): ?array
    {
        foreach ($locations as $location) {
            $id = self::getPluginIdOfFile($location['file']);
            if ($id !== null) {
                return array('plugin' => $id, 'error' => $error, 'file' => $location['file'], 'line' => $location['line']);
            }
        }

        return null;
    }

    /**
     * The ID of the plugin whose directory contains the file, or **null**. Only a plugin that the
     * loader can load counts: a directory without a usable manifest is never loaded, so there is
     * nothing to keep out.
     * @param string $file
     * @return string|null
     */
    public static function getPluginIdOfFile(string $file): ?string
    {
        $root = realpath(PluginRegistry::getPluginsPath());
        if ($file === '' || $root === false) {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);

        $compare = PHP_OS_FAMILY === 'Windows' ? 'strncasecmp' : 'strncmp';
        if ($compare($file, $root, strlen($root)) !== 0) {
            return null;
        }

        $id = strstr(substr($file, strlen($root)), '/', true);
        if ($id === false || !Plugin::isValidId($id)) {
            return null;
        }

        $plugin = PluginRegistry::get($id);

        return ($plugin !== null && $plugin->isValid()) ? $plugin->id : null;
    }

    /**
     * Write a crash of a plugin to the record, so that the next request leaves it out.
     * @param string $id ID of the plugin.
     * @param string $error What went wrong.
     * @param string $file The file in which it went wrong.
     * @param int $line
     * @return void
     */
    public static function record(string $id, string $error, string $file, int $line): void
    {
        global $gLogger;

        $plugin = PluginRegistry::get($id);
        if ($plugin === null || self::$recorded) {
            return;
        }
        self::$recorded = true;

        if (strlen($error) > self::MAX_ERROR_LENGTH) {
            $error = mb_strcut($error, 0, self::MAX_ERROR_LENGTH) . ' ...';
        }

        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', ADMIDIO_PATH), '/') . '/';
        if (str_starts_with($file, $base)) {
            $file = substr($file, strlen($base));
        }

        $entry = array(
            'pluginVersion' => $plugin->version,
            'admidioVersion' => ADMIDIO_VERSION_TEXT,
            'error' => $error,
            'file' => $file,
            'line' => $line,
            'time' => date(DATE_ATOM)
        );

        self::update(static function (array $records) use ($id, $entry): array {
            // An entry that no longer applies is of no use to anybody, so this is where it goes.
            $records = array_intersect_key($records, self::filterActive($records));
            $records[$id] = $entry;

            return $records;
        });

        if (isset($gLogger)) {
            $gLogger->error('PLUGIN: Plugin "' . $id . '" crashed and is not loaded until it is tried again.',
                array('plugin' => $id) + $entry);
        }
    }

    /**
     * The crashes that still apply, as pluginId => entry. An entry no longer applies once the
     * plugin is gone or its version or the Admidio version differs from the recorded one.
     * @return array<string,array{pluginVersion: string, admidioVersion: string, error: string, file: string, line: int, time: string}>
     */
    public static function getActive(): array
    {
        return self::filterActive(self::getRecords());
    }

    /**
     * Whether a crash of the plugin is recorded and still applies.
     * @param string $id
     * @return bool
     */
    public static function hasCrashed(string $id): bool
    {
        return isset(self::getActive()[$id]);
    }

    /**
     * Remove the entry of a plugin, so that the next request loads it again.
     * @param string $id
     * @return bool Whether there was an entry.
     */
    public static function remove(string $id): bool
    {
        $found = false;

        self::update(static function (array $records) use ($id, &$found): array {
            $found = isset($records[$id]);
            unset($records[$id]);

            return $records;
        });

        return $found;
    }

    /**
     * Read the record from another file. Pass **null** to restore the regular one. Only tests use
     * this.
     * @param string|null $file
     * @return void
     */
    public static function setRecordFile(?string $file): void
    {
        self::$recordFile = $file;
        self::$records = null;
    }

    /**
     * Forget what was read and recorded in this request. Only tests need this.
     * @return void
     */
    public static function reset(): void
    {
        self::$records = null;
        self::$recorded = false;
    }

    /**
     * Absolute path of the record.
     * @return string
     */
    private static function getRecordFile(): string
    {
        return self::$recordFile ?? ADMIDIO_PATH . FOLDER_DATA . '/' . self::RECORD_FILE;
    }

    /**
     * All entries of the record, whether they still apply or not.
     *
     * A record that cannot be read or decoded is treated as empty: the worst outcome is that a
     * plugin crashes once more and is recorded again, while a guard that fails would take the
     * installation down itself.
     * @return array<string,array<string,mixed>>
     */
    private static function getRecords(): array
    {
        if (self::$records !== null) {
            return self::$records;
        }

        self::$records = array();
        $file = self::getRecordFile();
        if (!is_file($file)) {
            return self::$records;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return self::$records;
        }
        flock($handle, LOCK_SH);
        $json = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        self::$records = self::decode($json === false ? '' : $json);

        return self::$records;
    }

    /**
     * Change the record under an exclusive lock, so that two requests that crash at the same time
     * do not lose an entry. An empty record removes the file.
     * @param callable(array<string,array<string,mixed>>): array<string,array<string,mixed>> $change
     * @return void
     */
    private static function update(callable $change): void
    {
        global $gLogger;

        $file = self::getRecordFile();
        $handle = @fopen($file, 'c+b');
        if ($handle === false) {
            if (isset($gLogger)) {
                $gLogger->error('PLUGIN: The record of crashed plugins could not be opened.', array('file' => $file));
            }
            return;
        }

        flock($handle, LOCK_EX);
        $json = stream_get_contents($handle);
        $records = $change(self::decode($json === false ? '' : $json));

        ftruncate($handle, 0);
        rewind($handle);
        if ($records !== array()) {
            fwrite($handle, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
        }
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        // Windows cannot remove a file that is still open, so this happens after the lock is gone.
        if ($records === array()) {
            @unlink($file);
        }

        self::$records = $records;
    }

    /**
     * Decode the record, dropping anything that is not an entry.
     * @param string $json
     * @return array<string,array<string,mixed>>
     */
    private static function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array();
        }

        return array_filter($decoded, static fn(mixed $entry, mixed $id): bool => is_string($id) && is_array($entry),
            ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The entries that still apply to the plugins and the Admidio version that are there now.
     * @param array<string,array<string,mixed>> $records
     * @return array<string,array{pluginVersion: string, admidioVersion: string, error: string, file: string, line: int, time: string}>
     */
    private static function filterActive(array $records): array
    {
        $active = array();
        foreach ($records as $id => $entry) {
            $plugin = PluginRegistry::get((string)$id);
            if ($plugin === null || $plugin->version !== (string)($entry['pluginVersion'] ?? '')
                || ADMIDIO_VERSION_TEXT !== (string)($entry['admidioVersion'] ?? '')) {
                continue;
            }

            $active[(string)$id] = array(
                'pluginVersion' => $plugin->version,
                'admidioVersion' => ADMIDIO_VERSION_TEXT,
                'error' => (string)($entry['error'] ?? ''),
                'file' => (string)($entry['file'] ?? ''),
                'line' => (int)($entry['line'] ?? 0),
                'time' => (string)($entry['time'] ?? '')
            );
        }

        return $active;
    }
}
