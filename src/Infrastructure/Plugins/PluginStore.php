<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;

/**
 * The catalogue of plugins published for Admidio.
 *
 * The catalogue is one JSON file on a web server - by default plugins.json next to the update.txt
 * that the version check already reads. This class fetches it, caches it and answers what of it
 * applies to this Admidio version. It never throws while reading: a catalogue that cannot be
 * reached or cannot be understood makes the store unavailable, exactly as a broken manifest makes
 * one plugin broken rather than breaking the page.
 *
 * The remote call never happens while a page is being built. The catalogue is cached in
 * adm_my_files and refreshed at most once a day, or when an administrator asks for it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginStore
{
    /**
     * The catalogue format this class understands. A file that announces anything else is refused
     * rather than guessed at.
     */
    public const FORMAT = 1;

    /**
     * Where the cached catalogue is kept, below adm_my_files.
     */
    public const CACHE_FILE = '/plugin-store.json';

    /**
     * How long a cached catalogue is used before it is fetched again, in seconds.
     *
     * A day. The catalogue only changes when somebody publishes a plugin, but a plugin that was
     * just published should not take a week to appear.
     */
    public const CACHE_SECONDS = 86400;

    /**
     * How long the fetch may take before it is given up on, in seconds. The store is a convenience;
     * it must never hold a page open.
     */
    public const TIMEOUT_SECONDS = 10;

    /**
     * The catalogue of this request, or **null** while it has not been read yet.
     * @var array<string,mixed>|null
     */
    private static ?array $catalogue = null;

    /**
     * Why the catalogue could not be read, or **null** if it could. English, for the administrator.
     */
    private static ?string $error = null;

    /**
     * A catalogue URL that overrides everything else. Only tests set this.
     */
    private static ?string $url = null;

    /**
     * Where the cache is kept, when it is not the regular place. Only tests set this.
     */
    private static ?string $cacheFile = null;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * The plugins of the catalogue that this Admidio could run, whether or not it has them.
     *
     * A plugin that is already on disk stays in the list and is marked, rather than disappearing:
     * seeing that the store knows a plugin and that this installation already has it is more useful
     * than a list that silently omits half of what was published.
     * @return array<int,array<string,mixed>> Each entry is the catalogue entry with the single
     *                                        **release** that applies to this Admidio resolved, plus
     *                                        **installed** and **installedVersion**.
     * @throws Exception
     */
    public static function getPlugins(): array
    {
        $plugins = array();

        foreach (self::read()['plugins'] ?? array() as $entry) {
            $id = (string)($entry['id'] ?? '');
            if (!Plugin::isValidId($id)) {
                continue;
            }

            $release = self::pickRelease($entry);
            if ($release === null) {
                continue;
            }

            $plugin = PluginRegistry::get($id);

            $entry['release'] = $release;
            $entry['installed'] = $plugin !== null;
            $entry['installedVersion'] = $plugin?->version ?? '';
            $plugins[] = $entry;
        }

        return $plugins;
    }

    /**
     * Fetch the archive of one catalogue entry and install it.
     *
     * The archive is downloaded to a temporary file and handed to the same validated extraction an
     * uploaded file goes through, so nothing the catalogue says is trusted beyond where to look: the
     * ID, the version and the contents are all read out of the archive itself.
     * @param string $id ID of the plugin, as the catalogue names it.
     * @return string The ID of the plugin that was installed.
     * @throws Exception
     */
    public static function install(string $id): string
    {
        $entry = null;
        foreach (self::getPlugins() as $candidate) {
            if ((string)$candidate['id'] === $id) {
                $entry = $candidate;
                break;
            }
        }

        if ($entry === null) {
            throw new Exception('SYS_PLUGIN_STORE_NOT_OFFERED', array($id));
        }

        // Refused before the download, not after it: there is no point fetching an archive that the
        // extraction is going to turn away, and "already installed" is the honest reason.
        if ($entry['installed']) {
            throw new Exception('SYS_PLUGIN_ALREADY_EXISTS', array($id));
        }

        $archive = self::downloadRelease($entry['release']);

        try {
            return PluginPackage::install($archive);
        } finally {
            @unlink($archive);
        }
    }


    /**
     * The release the catalogue offers for an installed plugin, if it is newer than the files.
     *
     * This is what makes updating one action for the administrator: the plugin manager asks whether
     * newer files exist, and does not have to know or explain where the plugin originally came from.
     * @param string $id
     * @return array<string,mixed>|null The release, or **null** if the catalogue has nothing newer -
     *                                  including when there is no catalogue at all.
     */
    public static function getNewerRelease(string $id): ?array
    {
        $plugin = PluginRegistry::get($id);
        if ($plugin === null || !$plugin->isValid()) {
            return null;
        }

        foreach (self::getPlugins() as $entry) {
            if ((string)$entry['id'] !== $id) {
                continue;
            }

            $offered = (string)$entry['release']['version'];

            return version_compare($offered, $plugin->version, '>') ? $entry['release'] : null;
        }

        return null;
    }

    /**
     * Replace the files of an installed plugin with a newer release from the catalogue.
     *
     * Only the files change here. Whatever the new version needs done to the database is the update
     * scripts' job, and PluginInstaller runs those afterwards - the same ones it would run for a
     * plugin whose files somebody replaced by hand.
     * @param string $id
     * @return string The version that was put in place.
     * @throws Exception
     */
    public static function updateFiles(string $id): string
    {
        $release = self::getNewerRelease($id);
        if ($release === null) {
            throw new Exception('SYS_PLUGIN_STORE_NOTHING_NEWER', array($id));
        }

        $archive = self::downloadRelease($release);

        try {
            PluginPackage::install($archive, true);
        } finally {
            @unlink($archive);
        }

        return (string)$release['version'];
    }
    /**
     * Download the archive of a release to a temporary file.
     * @param array<string,mixed> $release
     * @return string Absolute path of the downloaded file.
     * @throws Exception
     */
    private static function downloadRelease(array $release): string
    {
        $source = self::resolveDownload((string)($release['download'] ?? ''));

        $context = stream_context_create(array(
            'http' => array('timeout' => self::TIMEOUT_SECONDS, 'follow_location' => 1),
            'https' => array('timeout' => self::TIMEOUT_SECONDS, 'follow_location' => 1)
        ));

        $body = @file_get_contents($source, false, $context);
        if ($body === false || $body === '') {
            throw new Exception('SYS_PLUGIN_STORE_DOWNLOAD_FAILED', array($source));
        }

        $file = sys_get_temp_dir() . '/admidio-plugin-download-' . uniqid('', true) . '.zip';
        if (@file_put_contents($file, $body) === false) {
            throw new Exception('SYS_PLUGIN_PACKAGE_NOT_EXTRACTED');
        }

        return $file;
    }

    /**
     * Where the archive of a release is actually read from.
     *
     * A published catalogue always names an address. A catalogue a developer configured may instead
     * name a file inside the Admidio directory, so that a plugin can be tried out without publishing
     * it anywhere - but only that one may, because a catalogue on a host somebody else controls must
     * never be able to point the installer at a local file.
     * @param string $download The download as the catalogue states it.
     * @return string
     * @throws Exception
     */
    private static function resolveDownload(string $download): string
    {
        if ($download === '') {
            throw new Exception('SYS_PLUGIN_STORE_DOWNLOAD_FAILED', array($download));
        }

        if (preg_match('#^https?://#i', $download) === 1) {
            return $download;
        }

        if (!self::isDeveloperCatalogue()) {
            throw new Exception('SYS_PLUGIN_STORE_DOWNLOAD_NOT_A_URL', array($download));
        }

        // A relative path belongs to the installation, so it is resolved against it and has to stay
        // inside it - a developer catalogue is trusted, but not to reach out of the installation.
        $path = $download;
        if (!preg_match('#^([A-Za-z]:|/)#', $path)) {
            $path = ADMIDIO_PATH . '/' . ltrim($path, '/');
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            throw new Exception('SYS_PLUGIN_STORE_DOWNLOAD_FAILED', array($download));
        }

        return $resolved;
    }
    /**
     * The newest release of a catalogue entry this Admidio satisfies, or **null** if there is none.
     *
     * The constraint is read with the same matcher a manifest is read with, so the catalogue says
     * what a plugin requires in exactly the syntax the plugin itself would.
     * @param array<string,mixed> $entry
     * @return array<string,mixed>|null
     */
    private static function pickRelease(array $entry): ?array
    {
        $best = null;

        foreach ((array)($entry['releases'] ?? array()) as $release) {
            if (!is_array($release)) {
                continue;
            }

            $version = (string)($release['version'] ?? '');
            $download = (string)($release['download'] ?? '');
            if ($version === '' || $download === '') {
                continue;
            }

            $constraint = (string)($release['requires']['admidio'] ?? '');
            if ($constraint !== '' && !Plugin::versionMatches(ADMIDIO_VERSION, $constraint)) {
                continue;
            }

            if ($best === null || version_compare($version, (string)$best['version'], '>')) {
                $best = $release;
            }
        }

        return $best;
    }

    /**
     * The catalogue, from the cache when it is fresh enough and from the network otherwise.
     * @return array<string,mixed> An empty array when the catalogue cannot be read.
     */
    public static function read(): array
    {
        if (self::$catalogue !== null) {
            return self::$catalogue;
        }

        $cached = self::readCache();
        if ($cached !== null) {
            self::$catalogue = $cached;
            return self::$catalogue;
        }

        return self::refresh();
    }

    /**
     * Fetch the catalogue now, whatever the cache says. This is what the refresh action calls.
     * @return array<string,mixed> An empty array when the catalogue cannot be read.
     */
    public static function refresh(): array
    {
        self::$error = null;

        $url = self::getUrl();
        $body = self::fetch($url);

        if ($body === null) {
            self::$catalogue = array();
            return self::$catalogue;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            self::$error = 'The catalogue at ' . $url . ' is not valid JSON.';
            self::$catalogue = array();
            return self::$catalogue;
        }

        $format = (int)($decoded['format'] ?? 0);
        if ($format !== self::FORMAT) {
            self::$error = 'The catalogue at ' . $url . ' announces format ' . $format
                . ', and this Admidio understands format ' . self::FORMAT . '.';
            self::$catalogue = array();
            return self::$catalogue;
        }

        self::writeCache($decoded);
        self::$catalogue = $decoded;

        return self::$catalogue;
    }

    /**
     * Read the catalogue from the given place. Local paths are allowed, which is what makes a
     * catalogue in adm_my_files usable for testing without exposing it to the web.
     * @param string $url
     * @return string|null **null** when nothing could be read, with the reason in $error.
     */
    private static function fetch(string $url): ?string
    {
        $context = stream_context_create(array(
            'http' => array('timeout' => self::TIMEOUT_SECONDS, 'follow_location' => 1),
            'https' => array('timeout' => self::TIMEOUT_SECONDS, 'follow_location' => 1)
        ));

        /*
         * The read is suppressed because an unreachable catalogue is a state, not an error - but the
         * reason PHP gives is the only thing that distinguishes a host that is down from a path the
         * web server may not read, so it is kept and reported.
         */
        $before = error_get_last();
        $body = @file_get_contents($url, false, $context);

        if ($body === false || $body === '') {
            $reason = error_get_last();
            self::$error = 'The catalogue at ' . $url . ' could not be read.'
                . ($reason !== null && $reason !== $before ? ' ' . $reason['message'] : '');

            return null;
        }

        return $body;
    }

    /**
     * Where the catalogue is read from.
     *
     * A developer working on a plugin can point Admidio at a catalogue of their own with
     * **$gPluginStoreUrl** in config.php, which may also be a path on disk. That only applies while
     * $gDebug is on: not a security boundary - anybody who can edit config.php can do anything -
     * but a line left over from development must not quietly repoint a production installation at a
     * catalogue nobody is watching, because the store hands the installer PHP to run.
     * @return string
     */
    public static function getUrl(): string
    {
        global $gDebug, $gPluginStoreUrl;

        if (self::$url !== null) {
            return self::$url;
        }

        if (!empty($gDebug) && isset($gPluginStoreUrl) && is_string($gPluginStoreUrl) && $gPluginStoreUrl !== '') {
            return self::resolveLocation($gPluginStoreUrl);
        }

        return ADMIDIO_HOMEPAGE . 'plugins.json';
    }

    /**
     * Where a configured address or path actually points.
     *
     * A path is taken to be relative to the Admidio directory unless it is absolute, so that a
     * catalogue kept inside the installation is named the same way wherever that installation runs.
     * That matters more than it looks: an Admidio in a container sees its own path, not the one the
     * person editing config.php sees, and a path from the host would simply not exist inside it.
     * @param string $location A web address, an absolute path, or a path inside the installation.
     * @return string
     */
    private static function resolveLocation(string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        if (preg_match('#^([A-Za-z]:|/)#', $location) === 1) {
            return $location;
        }

        return ADMIDIO_PATH . '/' . ltrim($location, '/');
    }

    /**
     * Whether the catalogue Admidio is reading was named by a developer in config.php.
     *
     * A download from such a catalogue may be a path on disk, so that a plugin can be tried out
     * without publishing it anywhere. A download from the real store is always a URL.
     * @return bool
     */
    public static function isDeveloperCatalogue(): bool
    {
        global $gDebug, $gPluginStoreUrl;

        return self::$url !== null
            || (!empty($gDebug) && isset($gPluginStoreUrl) && is_string($gPluginStoreUrl) && $gPluginStoreUrl !== '');
    }

    /**
     * Why the catalogue could not be read, or **null** if it could. English, like the diagnostics of
     * a broken plugin: it names a host or a file, not something a translation could improve.
     * @return string|null
     */
    public static function getError(): ?string
    {
        return self::$error;
    }

    /**
     * The cached catalogue, if there is one and it is still fresh.
     * @return array<string,mixed>|null
     */
    private static function readCache(): ?array
    {
        $file = self::getCacheFile();

        if (!is_file($file) || filemtime($file) < time() - self::CACHE_SECONDS) {
            return null;
        }

        $cached = json_decode((string)@file_get_contents($file), true);
        if (!is_array($cached) || !is_array($cached['catalogue'] ?? null)) {
            return null;
        }

        /*
         * A cache belongs to the catalogue it came from. Without this, turning the developer
         * override in config.php on or off would keep answering from the other catalogue until the
         * cache went stale, which is exactly when somebody is trying to see their own plugin.
         */
        if ((string)($cached['url'] ?? '') !== self::getUrl()) {
            return null;
        }

        return $cached['catalogue'];
    }

    /**
     * Keep the fetched catalogue, so that the next request does not go to the network.
     * @param array<string,mixed> $catalogue
     * @return void
     */
    private static function writeCache(array $catalogue): void
    {
        $payload = json_encode(array(
            'url' => self::getUrl(),
            'catalogue' => $catalogue
        ));

        // A cache that cannot be written is not an error; it only means the next request fetches again.
        @file_put_contents(self::getCacheFile(), (string)$payload);
    }

    /**
     * @return string
     */
    private static function getCacheFile(): string
    {
        return self::$cacheFile ?? ADMIDIO_PATH . FOLDER_DATA . self::CACHE_FILE;
    }

    /**
     * Keep the cache somewhere else. Only tests use this, so that they exercise the real caching
     * without writing into the adm_my_files of the installation they run in.
     * @param string|null $file
     * @return void
     */
    public static function setCacheFile(?string $file): void
    {
        self::$cacheFile = $file;
    }

    /**
     * Read the catalogue from somewhere else. Only tests use this; pass **null** to restore the
     * regular resolution.
     * @param string|null $url
     * @return void
     */
    public static function setUrl(?string $url): void
    {
        self::$url = $url;
        self::reset();
    }

    /**
     * Forget the catalogue of this request, so that it is read again.
     * @return void
     */
    public static function reset(): void
    {
        self::$catalogue = null;
        self::$error = null;
    }
}
