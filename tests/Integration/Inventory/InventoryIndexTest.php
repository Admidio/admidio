<?php

namespace Admidio\Tests\Integration\Inventory;

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\DatabaseTestCase;
use PDO;

class InventoryIndexTest extends DatabaseTestCase
{
    public function testFreshInstallationCreatesInventoryLookupIndexes(): void
    {
        $pdo = $this->connect();
        $this->assertContains(['ind_ini_id'], $this->indexes($pdo, TBL_INVENTORY_ITEM_DATA));
        $this->assertContains(['ini_org_id', 'ini_status'], $this->indexes($pdo, TBL_INVENTORY_ITEMS));
        $this->assertContains(['ivr_ini_id', 'ivr_status', 'ivr_begin'], $this->indexes($pdo, TBL_INVENTORY_RESERVATIONS));
    }

    public function testUpdateAddsIndexesToPreviousInventorySchemaWithoutDuplicatingForeignKeyIndex(): void
    {
        // Use a separate connection: MySQL index DDL commits implicitly. All tables below
        // are temporary and disappear when this connection closes.
        $pdo = $this->connect();
        $prefix = 'inventory_sql_migration';
        $pdo->exec('CREATE TEMPORARY TABLE ' . $prefix . '_inventory_item_data (ind_inf_id INTEGER, ind_ini_id INTEGER)');
        $pdo->exec('CREATE UNIQUE INDEX ' . $prefix . '_field_item ON ' . $prefix . '_inventory_item_data (ind_inf_id, ind_ini_id)');
        $pdo->exec('CREATE TEMPORARY TABLE ' . $prefix . '_inventory_items (ini_org_id INTEGER, ini_status INTEGER)');
        $pdo->exec('CREATE TEMPORARY TABLE ' . $prefix . '_inventory_reservations (ivr_ini_id INTEGER, ivr_status VARCHAR(20), ivr_begin TIMESTAMP, ivr_end TIMESTAMP)');
        $pdo->exec('CREATE INDEX ' . $prefix . '_item_period ON ' . $prefix . '_inventory_reservations (ivr_ini_id, ivr_begin, ivr_end)');

        $engine = $this->getDatabase()->getEngine();
        if ($engine === Database::PDO_ENGINE_MYSQL) {
            $pdo->exec('CREATE INDEX ' . $prefix . '_legacy_foreign_key ON ' . $prefix . '_inventory_item_data (ind_ini_id)');
        }

        $update = simplexml_load_file(ADMIDIO_PATH . '/install/db_scripts/update_6_0.xml');
        $steps = 0;
        foreach ($update->step as $step) {
            if (!preg_match('/CREATE INDEX %PREFIX%_idx_(ind_ini_id|ini_org_status|ivr_item_status_period)\b/', (string) $step)) {
                continue;
            }
            if (!isset($step['database']) || (string) $step['database'] === $engine) {
                $pdo->exec(str_replace('%PREFIX%', $prefix, (string) $step));
                ++$steps;
            }
        }

        $this->assertSame($engine === Database::PDO_ENGINE_MYSQL ? 2 : 3, $steps);
        $itemDataIndexes = $this->indexes($pdo, $prefix . '_inventory_item_data');
        $this->assertCount(1, array_filter($itemDataIndexes, static fn(array $columns): bool => $columns === ['ind_ini_id']));
        $this->assertContains(['ini_org_id', 'ini_status'], $this->indexes($pdo, $prefix . '_inventory_items'));
        $this->assertContains(['ivr_ini_id', 'ivr_status', 'ivr_begin'], $this->indexes($pdo, $prefix . '_inventory_reservations'));
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

    private function indexes(PDO $pdo, string $table): array
    {
        $indexes = [];
        if ($this->getDatabase()->getEngine() === Database::PDO_ENGINE_PGSQL) {
            $statement = $pdo->prepare('SELECT indexname, indexdef FROM pg_indexes WHERE tablename = ? AND schemaname = ANY(current_schemas(true))');
            $statement->execute([$table]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $index) {
                preg_match('/USING btree \(([^)]+)\)/', $index['indexdef'], $matches);
                $indexes[$index['indexname']] = array_map('trim', explode(',', $matches[1]));
            }
        } else {
            foreach ($pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $index) {
                $indexes[$index['Key_name']][] = $index['Column_name'];
            }
        }
        return $indexes;
    }
}
