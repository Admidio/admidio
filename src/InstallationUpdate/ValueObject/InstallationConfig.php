<?php
namespace Admidio\InstallationUpdate\ValueObject;

use Admidio\Infrastructure\Exception;

/**
 * @brief All input values that are needed to install a new Admidio database.
 *
 * The object holds what an installation needs and nothing about the way it was collected. The web
 * wizard fills it from the session of its steps, the command line from its options. Therefore the
 * installation itself does not depend on FormPresenter, $_POST or $_SESSION and can also be
 * executed headless.
 *
 * The values are not checked here. Admidio\InstallationUpdate\Service\Installation validates them,
 * either as a whole through validateConfiguration() or per wizard step through
 * validateDatabaseInput(), validateOrganizationInput() and validateAdministratorInput().
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InstallationConfig
{
    /**
     * @param string $dbType Database system, one of the Database::DB_TYPE_* values.
     * @param string $dbHost Host name or IP address of the database server.
     * @param int|null $dbPort Port of the database server or **null** for the default port.
     * @param string $dbName Name of the database.
     * @param string $dbUsername User of the database.
     * @param string $dbPassword Password of the database user.
     * @param string $tablePrefix Prefix of all Admidio tables, e.g. **adm**.
     * @param string $rootUrl URL of this Admidio installation, e.g. **https://www.example.org/admidio**.
     * @param string $language ISO code of the language of the new organization, e.g. **de**.
     * @param string $timezone PHP time zone of the new organization, e.g. **Europe/Berlin**.
     * @param string $organizationShortName Short name of the first organization.
     * @param string $organizationName Long name of the first organization.
     * @param string $organizationEmail Email address of the organization administrator.
     * @param string $adminLogin Login name of the administrator that will be created.
     * @param string $adminFirstName First name of the administrator.
     * @param string $adminLastName Last name of the administrator.
     * @param string $adminEmail Email address of the administrator.
     * @param string $adminPassword Password of the administrator.
     */
    public function __construct(
        public readonly string $dbType,
        public readonly string $dbHost,
        public readonly ?int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUsername,
        public readonly string $dbPassword,
        public readonly string $tablePrefix,
        public readonly string $rootUrl,
        public readonly string $language,
        public readonly string $timezone,
        public readonly string $organizationShortName,
        public readonly string $organizationName,
        public readonly string $organizationEmail,
        public readonly string $adminLogin,
        public readonly string $adminFirstName,
        public readonly string $adminLastName,
        public readonly string $adminEmail,
        public readonly string $adminPassword
    ) {
    }

    /**
     * Create the object from an array with the property names as keys. Missing values are empty,
     * only the table prefix falls back to the Admidio default **adm**.
     * @param array<string,mixed> $values
     * @return self
     * @throws Exception
     */
    public static function fromArray(array $values): self
    {
        return new self(
            (string) ($values['dbType'] ?? ''),
            (string) ($values['dbHost'] ?? ''),
            self::normalizePort($values['dbPort'] ?? null),
            (string) ($values['dbName'] ?? ''),
            (string) ($values['dbUsername'] ?? ''),
            (string) ($values['dbPassword'] ?? ''),
            (string) ($values['tablePrefix'] ?? 'adm'),
            rtrim((string) ($values['rootUrl'] ?? ''), '/'),
            (string) ($values['language'] ?? ''),
            (string) ($values['timezone'] ?? ''),
            (string) ($values['organizationShortName'] ?? ''),
            (string) ($values['organizationName'] ?? ''),
            (string) ($values['organizationEmail'] ?? ''),
            (string) ($values['adminLogin'] ?? ''),
            (string) ($values['adminFirstName'] ?? ''),
            (string) ($values['adminLastName'] ?? ''),
            (string) ($values['adminEmail'] ?? ''),
            (string) ($values['adminPassword'] ?? '')
        );
    }

    /**
     * Create the object from the session of the web installation wizard. Every wizard step stores
     * its values under its own session keys, this method maps them to the installation input.
     * @param array<string,mixed> $session Normally $_SESSION of the installation wizard.
     * @param string $rootUrl URL of this Admidio installation.
     * @return self
     * @throws Exception
     */
    public static function fromInstallerSession(array $session, string $rootUrl): self
    {
        return self::fromArray(array(
            'dbType' => $session['db_type'] ?? '',
            'dbHost' => $session['db_host'] ?? '',
            'dbPort' => $session['db_port'] ?? null,
            'dbName' => $session['db_name'] ?? '',
            'dbUsername' => $session['db_username'] ?? '',
            'dbPassword' => $session['db_password'] ?? '',
            'tablePrefix' => $session['table_prefix'] ?? 'adm',
            'rootUrl' => $rootUrl,
            'language' => $session['language'] ?? '',
            'timezone' => $session['orga_timezone'] ?? '',
            'organizationShortName' => $session['orga_shortname'] ?? '',
            'organizationName' => $session['orga_longname'] ?? '',
            'organizationEmail' => $session['orga_email'] ?? '',
            'adminLogin' => $session['user_login'] ?? '',
            'adminFirstName' => $session['user_first_name'] ?? '',
            'adminLastName' => $session['user_last_name'] ?? '',
            'adminEmail' => $session['user_email'] ?? '',
            'adminPassword' => $session['user_password'] ?? ''
        ));
    }

    /**
     * An empty port means the default port of the database system. Every other value must be a
     * number, so that a typo cannot end up in config.php as the port of the database server.
     * @param mixed $port
     * @return int|null Returns the port as a number or **null** for the default port.
     * @throws Exception
     */
    public static function normalizePort(mixed $port): ?int
    {
        if ($port === null || $port === '') {
            return null;
        }

        if (!is_numeric($port)) {
            throw new Exception('INS_DATABASE_PORT_INVALID');
        }

        // A configured port 0 is the notation of some config.php files for "use the default port".
        return (int) $port === 0 ? null : (int) $port;
    }
}
