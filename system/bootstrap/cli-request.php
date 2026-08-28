<?php
/**
 ***********************************************************************************************
 * Request environment of Admidio command-line scripts.
 *
 * Admidio derives its URL constants from $_SERVER, and a config.php may select its database
 * settings by the host of the request. A CLI process has neither, so the bootstrap of the command
 * line fills both with deterministic values through the functions of this file.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be called from the command line!');
}

/**
 * Determine the host that config.php is read with.
 *
 * The value ends up in HTTP_HOST, SERVER_NAME and REQUEST_URI and is therefore read by config.php
 * and by the URL constants derived in constants.php. Accept only a host name with an optional port
 * so a caller cannot smuggle a path, a scheme or a header break into those values.
 *
 * @param string $cliHost Host of the --host option, empty if the option was not used.
 * @return string Returns the host name with an optional port.
 * @throws RuntimeException Throws if the given host is not a host name.
 */
function admCliRequestHost(string $cliHost = ''): string
{
    $host = $cliHost;

    if ($host === '') {
        $host = (string)getenv('ADMIDIO_HOST');
    }
    if ($host === '') {
        return 'localhost';
    }

    if (!preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*(?::\d{1,5})?$/', $host)
        && !preg_match('/^\[[0-9A-Fa-f:.]+](?::\d{1,5})?$/', $host)) {
        throw new RuntimeException(
            'The host "' . $host . '" is not a valid host name. --host and ADMIDIO_HOST expect '
            . 'a host name with an optional port, for example "example.org" or "example.org:8080".'
        );
    }

    return $host;
}

/**
 * Fill the request variables that Admidio reads while it is bootstrapping.
 *
 * @param string $rootPath Path of the Admidio installation.
 * @param string $host Host of the Admidio installation.
 * @param int $port Port of the Admidio installation.
 * @param string $urlPath Path of the Admidio installation within the URL, e.g. **subfolder**.
 * @return void
 */
function admCliRequestVariables(string $rootPath, string $host, int $port = 80, string $urlPath = ''): void
{
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    $_SERVER['SERVER_PORT'] = $port;
    $_SERVER['DOCUMENT_ROOT'] = $rootPath;
    $_SERVER['SCRIPT_FILENAME'] = $rootPath . '/admidio';
    $_SERVER['SCRIPT_NAME'] = $urlPath . '/admidio';
    $_SERVER['REQUEST_URI'] = $urlPath . '/admidio';
}

/**
 * Fill the request variables out of the URL of this Admidio installation, which is normally
 * defined as $g_root_path in config.php.
 *
 * @param string $rootPath Path of the Admidio installation.
 * @param string $rootUrl URL of the Admidio installation.
 * @return void
 */
function admCliRequestVariablesFromUrl(string $rootPath, string $rootUrl): void
{
    $url = parse_url($rootUrl);
    $scheme = is_array($url) && isset($url['scheme']) ? $url['scheme'] : 'http';
    $host = is_array($url) && isset($url['host']) ? $url['host'] : 'localhost';
    $port = is_array($url) && isset($url['port'])
        ? (int)$url['port']
        : ($scheme === 'https' ? 443 : 80);
    $urlPath = is_array($url) && isset($url['path']) ? rtrim($url['path'], '/') : '';

    admCliRequestVariables($rootPath, $host, $port, $urlPath);
}
