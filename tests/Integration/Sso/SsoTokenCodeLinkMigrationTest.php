<?php
/**
 * Regression coverage for the update steps that link OIDC tokens to their authorization code.
 *
 * An installation of the previous version has token tables without oat_oac_id / ort_oac_id. The
 * update has to add them with a foreign key that lets the cleanup of expired codes go on: the
 * link is only needed while a code can still be presented again, so deleting a code sets it NULL.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\DatabaseTestCase;
use PDO;
use ReflectionMethod;

class SsoTokenCodeLinkMigrationTest extends DatabaseTestCase
{
    private const PREFIX = 'oidc_link_migration';

    /**
     * @testdox The update links the token tables of the previous version to the authorization codes
     */
    public function testTheUpdateLinksTokensToAuthorizationCodes(): void
    {
        // A separate connection, because MySQL commits DDL implicitly. Foreign keys do not work on
        // temporary tables, so real tables are created and dropped again.
        $pdo = $this->connect();
        $prefix = self::PREFIX;
        $this->dropTables($pdo);

        try {
            $this->exec($pdo, 'CREATE TABLE ' . $prefix . '_oidc_auth_codes (oac_id integer unsigned NOT NULL, PRIMARY KEY (oac_id))');
            $this->exec($pdo, 'CREATE TABLE ' . $prefix . '_oidc_access_tokens (oat_id integer unsigned NOT NULL, oat_revoked boolean DEFAULT FALSE, PRIMARY KEY (oat_id))');
            $this->exec($pdo, 'CREATE TABLE ' . $prefix . '_oidc_refresh_tokens (ort_id integer unsigned NOT NULL, ort_revoked boolean DEFAULT FALSE, PRIMARY KEY (ort_id))');

            $update = simplexml_load_file(ADMIDIO_PATH . '/install/db_scripts/update_6_0.xml');
            $steps = 0;
            foreach ($update->step as $step) {
                if (!preg_match('/\bo[ar]t_oac_id\b/', (string) $step)) {
                    continue;
                }
                $this->exec($pdo, str_replace('%PREFIX%', $prefix, (string) $step));
                ++$steps;
            }
            $this->assertSame(4, $steps);

            $pdo->exec('INSERT INTO ' . $prefix . '_oidc_auth_codes (oac_id) VALUES (1)');
            $pdo->exec('INSERT INTO ' . $prefix . '_oidc_access_tokens (oat_id, oat_oac_id) VALUES (1, 1)');
            $pdo->exec('INSERT INTO ' . $prefix . '_oidc_refresh_tokens (ort_id, ort_oac_id) VALUES (1, 1)');

            // The cleanup of an expired code keeps its tokens and only drops the link
            $pdo->exec('DELETE FROM ' . $prefix . '_oidc_auth_codes WHERE oac_id = 1');
            $this->assertNull($pdo->query('SELECT oat_oac_id FROM ' . $prefix . '_oidc_access_tokens WHERE oat_id = 1')->fetchColumn());
            $this->assertNull($pdo->query('SELECT ort_oac_id FROM ' . $prefix . '_oidc_refresh_tokens WHERE ort_id = 1')->fetchColumn());
        } finally {
            $this->dropTables($pdo);
        }
    }

    /**
     * Execute a statement the way the update does, with the Postgres conversions of Database.
     */
    private function exec(PDO $pdo, string $sql): void
    {
        if ($this->getDatabase()->getEngine() === Database::PDO_ENGINE_PGSQL) {
            $sql = (new ReflectionMethod(Database::class, 'preparePgSqlQuery'))->invoke($this->getDatabase(), $sql);
        }
        $pdo->exec($sql);
    }

    private function dropTables(PDO $pdo): void
    {
        foreach (array('oidc_access_tokens', 'oidc_refresh_tokens', 'oidc_auth_codes') as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . self::PREFIX . '_' . $table);
        }
    }

    private function connect(): PDO
    {
        $config = self::getTestDatabaseConfig();
        $engine = $this->getDatabase()->getEngine();
        return new PDO(
            $engine . ':host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'],
            $config['user'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
