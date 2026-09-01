<?php
namespace Admidio\Tests\Hooks;

use Admidio\Infrastructure\Database;
use PDO;
use PDOStatement;

/**
 * A Database that runs against an in-memory SQLite connection, so that the real Entity lifecycle
 * can be executed in a test without a server. Only the three methods that Entity uses are
 * overridden; the parent constructor is deliberately not called, because it would connect.
 */
class FakeDatabase extends Database
{
    private PDO $sqlite;
    /** @var array<string,array> */
    private array $columns = array();
    /** @var array<int,string> */
    public array $statements = array();
    /** Make the next statement fail, to reach the failure path of the lifecycle. */
    public bool $breakNextStatement = false;

    public function __construct()
    {
        $this->sqlite = new PDO('sqlite::memory:', null, null, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STATEMENT_CLASS => array(BufferedStatement::class, array())
        ));
    }

    /**
     * Create a table and remember the column properties in the shape Entity expects.
     * @param string $table Name of the table.
     * @param array $columns Column name to array(type, null, key, serial).
     * @return void
     */
    public function createTable(string $table, array $columns): void
    {
        $definitions = array();
        foreach ($columns as $name => $property) {
            $definitions[] = $property['serial']
                ? $name . ' INTEGER PRIMARY KEY AUTOINCREMENT'
                : $name . ' TEXT';
        }
        $this->sqlite->exec('CREATE TABLE ' . $table . ' (' . implode(', ', $definitions) . ')');
        $this->columns[$table] = $columns;
    }

    public function getTableColumnsProperties(string $table): array
    {
        return $this->columns[$table] ?? array();
    }

    public function queryPrepared(string $sql, array $params = array(), bool $showError = true): false|PDOStatement
    {
        $this->statements[] = $sql;

        if ($this->breakNextStatement) {
            $this->breakNextStatement = false;
            $sql = str_replace('INTO ' . TABLE_PREFIX, 'INTO missing_' . TABLE_PREFIX, $sql);
        }

        $statement = $this->sqlite->prepare($sql);
        $statement->execute(array_values($params));
        return $statement;
    }

    public function lastInsertId(): int
    {
        return (int)$this->sqlite->lastInsertId();
    }

    public function startTransaction(): bool
    {
        if ($this->transactions > 0) {
            ++$this->transactions;
            return true;
        }

        $this->sqlite->beginTransaction();
        $this->transactions = 1;
        return true;
    }

    public function endTransaction(): bool
    {
        if ($this->transactions === 0) {
            return true;
        }
        if ($this->transactions > 1) {
            --$this->transactions;
            return true;
        }

        $this->sqlite->commit();
        $this->transactions = 0;
        $this->runAfterCommitCallbacks();
        return true;
    }

    public function rollback(): bool
    {
        if ($this->transactions === 0) {
            return false;
        }

        $this->sqlite->rollBack();
        $this->transactions = 0;
        $this->runAfterRollbackCallbacks();
        return true;
    }

    /**
     * Read a whole table back, to check what the lifecycle actually wrote.
     * @param string $table Name of the table.
     * @return array Returns all rows.
     */
    public function fetchAll(string $table): array
    {
        return $this->sqlite->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    }
}
