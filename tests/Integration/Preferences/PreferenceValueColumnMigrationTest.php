<?php
/**
 * The refresh token of SMTP with OAuth 2.0 is longer than 255 characters, so prf_value became a
 * text column: in a new installation through db.sql, in an existing one through update_6_0.xml.
 */

namespace Admidio\Tests\Integration\Preferences;

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\DatabaseTestCase;
use PDO;

class PreferenceValueColumnMigrationTest extends DatabaseTestCase
{
    private const LONG_VALUE_LENGTH = 4000;

    public function testAFreshInstallationStoresLongPreferenceValues(): void
    {
        $this->assertTrue($this->storesLongValues($this->connect(), TBL_PREFERENCES, false));
    }

    public function testTheUpdateWidensTheColumnAndKeepsTheStoredValues(): void
    {
        // A separate connection: MySQL commits DDL implicitly. The table is temporary and disappears
        // with the connection.
        $pdo = $this->connect();
        $prefix = 'preference_value_migration';
        $pdo->exec('CREATE TEMPORARY TABLE ' . $prefix . '_preferences (
            prf_id      integer         NOT NULL,
            prf_org_id  integer         NOT NULL,
            prf_name    varchar(50)     NOT NULL,
            prf_value   varchar(255)    NOT NULL
        )');
        $pdo->exec('INSERT INTO ' . $prefix . "_preferences VALUES (1, 1, 'mail_smtp_host', 'smtp.office365.com')");

        $update = simplexml_load_file(ADMIDIO_PATH . '/install/db_scripts/update_6_0.xml');
        $engine = $this->getDatabase()->getEngine();
        $steps = 0;
        foreach ($update->step as $step) {
            if (!str_contains((string) $step, 'ALTER TABLE %PREFIX%_preferences')) {
                continue;
            }
            if (!isset($step['database']) || (string) $step['database'] === $engine) {
                $pdo->exec(str_replace('%PREFIX%', $prefix, (string) $step));
                ++$steps;
            }
        }

        $this->assertSame(1, $steps, 'one step per database');
        $this->assertSame('smtp.office365.com', $pdo->query('SELECT prf_value FROM ' . $prefix . '_preferences WHERE prf_id = 1')->fetchColumn());
        $this->assertTrue($this->storesLongValues($pdo, $prefix . '_preferences', true));
    }

    private function storesLongValues(PDO $pdo, string $table, bool $temporary): bool
    {
        $value = str_repeat('0.AXoA-refresh-token-', (int) ceil(self::LONG_VALUE_LENGTH / 21));
        if (!$temporary) {
            // the table of the installation is written inside a transaction that is rolled back
            $pdo->beginTransaction();
        }
        try {
            $statement = $pdo->prepare('INSERT INTO ' . $table . ' (prf_id, prf_org_id, prf_name, prf_value) VALUES (?, ?, ?, ?)');
            $statement->execute(array(987654, 1, 'long_value_test', $value));
            $statement = $pdo->prepare('SELECT prf_value FROM ' . $table . ' WHERE prf_id = ?');
            $statement->execute(array(987654));
            return $statement->fetchColumn() === $value;
        } finally {
            if (!$temporary) {
                $pdo->rollBack();
            }
        }
    }

    private function connect(): PDO
    {
        $config = self::getTestDatabaseConfig();
        $engine = $this->getDatabase()->getEngine();
        $pdo = new PDO(
            $engine . ':host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'],
            $config['user'],
            $config['password'],
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        if ($engine === Database::PDO_ENGINE_MYSQL) {
            // strict mode, so a value that is too long is refused instead of cut off
            $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        }

        return $pdo;
    }
}
