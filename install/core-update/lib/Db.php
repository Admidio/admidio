<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;
use PDOException;

/**
 * The database connection of the updater: plain PDO, prepared statements only.
 *
 * The updater reads a few rows - the administrator who logs in, the language of the installation -
 * and never writes. The connection is built the way Admidio's Database class builds it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Db
{
    public function __construct(private readonly PDO $pdo, private readonly string $tablePrefix)
    {
    }

    /**
     * Connect to the database of the installation.
     * @param Config $config
     * @return self
     * @throws UpdaterException The database cannot be reached.
     */
    public static function connect(Config $config): self
    {
        $port = $config->dbPort !== null ? ';port=' . $config->dbPort : '';
        $dsn = match ($config->dbType) {
            'mysql' => 'mysql:host=' . $config->dbHost . $port . ';dbname=' . $config->dbName . ';charset=utf8',
            'pgsql', 'postgresql' => 'pgsql:host=' . $config->dbHost . $port . ';dbname=' . $config->dbName,
            default => throw new UpdaterException('The database type "' . $config->dbType . '" is not supported.')
        };

        try {
            $pdo = new PDO($dsn, $config->dbUser, $config->dbPassword, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ));
        } catch (PDOException $exception) {
            // The message of PDO can contain the host and the user name; it belongs in no page.
            throw new UpdaterException('The database of the installation could not be reached.', array(), $exception);
        }

        return new self($pdo, $config->tablePrefix);
    }

    /**
     * The full name of an Admidio table, for example **adm_users** for **users**.
     * @param string $name
     * @return string
     */
    public function table(string $name): string
    {
        return $this->tablePrefix . '_' . $name;
    }

    /**
     * The first row of a query, or **null**.
     * @param string $sql
     * @param array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchRow(string $sql, array $params = array()): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * The first column of the first row of a query, or **null**.
     * @param string $sql
     * @param array<int,mixed> $params
     * @return mixed
     */
    public function fetchValue(string $sql, array $params = array()): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }
}
