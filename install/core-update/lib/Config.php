<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * What the updater needs from adm_my_files/config.php.
 *
 * The file is included in a scope of its own and only its variables are read. The defaults are the
 * ones system/bootstrap/init_globals.php applies, so that the updater sees the installation exactly
 * as Admidio does.
 *
 * The two settings that change where updates come from and which key they are checked against are
 * development aids and are ignored unless **$gDebug** is switched on, so that a production
 * installation cannot be pointed somewhere else by a single line in its configuration.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Config
{
    /**
     * @param string $rootPath Absolute path of the Admidio installation, with forward slashes.
     * @param string $dbType **mysql** or **pgsql**.
     * @param string $dbHost
     * @param int|null $dbPort **null** for the default port of the database type.
     * @param string $dbName
     * @param string $dbUser
     * @param string $dbPassword
     * @param string $tablePrefix
     * @param string $baseUrl The URL of the installation, without a trailing slash.
     * @param bool $debug
     * @param string $serverUrl Test endpoint for updates, only set in debug mode.
     * @param string $rootKeyFile Test root key, only set in debug mode. Relative paths are resolved
     *                            from the installation directory.
     * @param string $timezone
     */
    public function __construct(
        public readonly string $rootPath,
        public readonly string $dbType,
        public readonly string $dbHost,
        public readonly ?int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $tablePrefix,
        public readonly string $baseUrl,
        public readonly bool $debug,
        public readonly string $serverUrl,
        public readonly string $rootKeyFile,
        public readonly string $timezone
    ) {
    }

    /**
     * Read the configuration of the installation in the given directory.
     * @param string $rootPath
     * @return self
     * @throws UpdaterException There is no configuration, so Admidio is not installed.
     */
    public static function load(string $rootPath): self
    {
        $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
        $file = $rootPath . '/adm_my_files/config.php';
        if (!is_file($file)) {
            throw new UpdaterException('Admidio is not installed here: adm_my_files/config.php is missing.');
        }

        $values = (static function (string $configFile): array {
            include $configFile;

            return get_defined_vars();
        })($file);

        return self::fromValues($rootPath, $values);
    }

    /**
     * Build the configuration from the variables of a config.php.
     * @param string $rootPath
     * @param array<string,mixed> $values
     * @return self
     */
    public static function fromValues(string $rootPath, array $values): self
    {
        $debug = !empty($values['gDebug']);
        $port = $values['g_adm_port'] ?? null;

        return new self(
            rtrim(str_replace('\\', '/', $rootPath), '/'),
            (string)($values['gDbType'] ?? 'mysql'),
            (string)($values['g_adm_srv'] ?? ''),
            ($port === null || $port === '') ? null : (int)$port,
            (string)($values['g_adm_db'] ?? ''),
            (string)($values['g_adm_usr'] ?? ''),
            (string)($values['g_adm_pw'] ?? ''),
            (string)($values['g_tbl_praefix'] ?? 'adm'),
            rtrim((string)($values['g_root_path'] ?? ''), '/'),
            $debug,
            $debug ? (string)($values['gCoreUpdateServerUrl'] ?? '') : '',
            $debug ? (string)($values['gCoreUpdateRootKeyFile'] ?? '') : '',
            (string)($values['gTimezone'] ?? 'Europe/Berlin')
        );
    }

    /**
     * The prefix of the cookies of this installation, the same as COOKIE_PREFIX.
     *
     * system/bootstrap/constants.php decides between two forms by isset($gDebug), but
     * init_globals.php always sets $gDebug before that, so the form with the database name and the
     * table prefix is the one every installation uses.
     * @return string
     */
    public function getCookiePrefix(): string
    {
        return (string)preg_replace('/\W/', '_', 'ADMIDIO_' . $this->dbName . '_' . $this->tablePrefix);
    }

    /**
     * The path part of the installation URL, for example "/admidio", or an empty string.
     * @return string
     */
    public function getBasePath(): string
    {
        return rtrim((string)parse_url($this->baseUrl, PHP_URL_PATH), '/');
    }

    /**
     * Whether the installation is served over HTTPS.
     * @return bool
     */
    public function isHttps(): bool
    {
        return strtolower((string)parse_url($this->baseUrl, PHP_URL_SCHEME)) === 'https';
    }
}
