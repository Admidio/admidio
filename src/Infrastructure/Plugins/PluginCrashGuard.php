<?php

namespace Admidio\Infrastructure\Plugins;

use Throwable;

/**
 * Notices when a plugin crashes Admidio while it is loaded and keeps it out until somebody decides
 * to try it again.
 *
 * PluginLoader::load() already catches what a plugin throws while it is being loaded. What it cannot
 * catch is a fatal error (E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR), for
 * example a class whose method signature does not match the Admidio class it extends any more. Such
 * a crash ends the request, and as a plugin is loaded on every request it ends every request,
 * including the ones of the plugin administration that could switch it off. So PluginLoader tells
 * the guard which plugin it is loading, and if the request ends in a fatal error meanwhile, the guard
 * writes the crash to **adm_my_files/plugins-failed.json**. PluginRegistry::getLoadable() leaves the
 * plugin out - and every plugin that requires it - from the next request on.
 *
 * Only loading counts. Once all plugins are loaded, a crash is a bug of the plugin that is reported
 * and fixed, while the plugin stays active: what a visitor sends with a request must never be able to
 * take a plugin away from everybody.
 *
 * The record is installation-wide and deliberately not a preference: whether a plugin is enabled is
 * the decision of an organization, it is written to the changelog, and a crash must not change it.
 * An entry only applies to the plugin version and the Admidio version it was recorded with, so
 * updating either gives the plugin a new chance. The administrators are shown the crash and can
 * remove the entry with "Try again".
 *
 * Exhausting the memory or the execution time is not a crash of the plugin: whatever runs last is
 * merely where the limit was reached, not what caused it.
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
    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR;

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
     * ID of the plugin that is being loaded right now, or **null**. A request that ends in a fatal
     * error while this is set was ended by that plugin.
     */
    private static ?string $loading = null;

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
     * Register the shutdown function that records a fatal error during loading. Calling it again is
     * harmless.
     *
     * The bootstrap calls this before it registers any other shutdown function: they run in the
     * order of registration, and a warning or an error in an earlier one would replace the fatal
     * error that error_get_last() still reports.
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
     * Note that the code of a plugin is about to run for loading. PluginLoader calls this before it
     * loads a plugin and endLoading() afterwards.
     * @param string $id ID of the plugin.
     * @return void
     */
    public static function beginLoading(string $id): void
    {
        self::$loading = $id;
    }

    /**
     * Note that the plugin was loaded, whether it succeeded or threw something that the loader
     * caught. A fatal error never gets here, which is how the guard knows it happened while loading.
     * @return void
     */
    public static function endLoading(): void
    {
        self::$loading = null;
    }

    /**
     * Shutdown function: record the fatal error that ended the request, if it happened while a
     * plugin was loaded.
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
     * The plugin crash an error of error_get_last() stands for, or **null** if it is none.
     * @param array{type: int, message: string, file: string, line: int}|null $error
     * @return array{plugin: string, error: string, file: string, line: int}|null
     */
    public static function attributeError(?array $error): ?array
    {
        if (self::$loading === null || $error === null || ($error['type'] & self::FATAL_ERRORS) === 0) {
            return null;
        }

        $message = (string)$error['message'];
        foreach (self::LIMIT_MESSAGES as $limit) {
            if (str_starts_with($message, $limit)) {
                return null;
            }
        }

        return array(
            'plugin' => self::$loading,
            'error' => $message,
            'file' => (string)$error['file'],
            'line' => (int)$error['line']
        );
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
     * Remove the entry of a plugin, so that the next request loads it again.
     * @param string $id
     * @return bool Whether there was an entry.
     */
    public static function remove(string $id): bool
    {
        $found = false;
        if (!is_file(self::getRecordFile())) {
            return false;
        }

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
        PluginRegistry::forgetExcluded();
    }

    /**
     * Forget what was read and recorded in this request. Only tests need this.
     * @return void
     */
    public static function reset(): void
    {
        self::$records = null;
        self::$recorded = false;
        self::$loading = null;
        PluginRegistry::forgetExcluded();
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
     * do not lose an entry. An empty record leaves an empty file: deleting it after the lock is
     * released would also delete a crash that another request recorded in between.
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

        self::$records = $records;
        PluginRegistry::forgetExcluded();
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
