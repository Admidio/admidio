<?php
namespace Admidio\Tests\Hooks;

use PDO;
use PDOStatement;

/**
 * A PDOStatement that answers rowCount() for a SELECT.
 *
 * The SQLite driver of PDO returns 0 there, it only counts the rows an INSERT, UPDATE or DELETE
 * touched. Entity::readData() decides whether it found its record by exactly that number, so
 * without this the real code would take the not-found branch for every record that is read back.
 * The rows of a SELECT are therefore buffered on execute() and served from that buffer.
 */
class BufferedStatement extends PDOStatement
{
    /** @var array<int,array>|null The buffered rows of a SELECT, null for any other statement. */
    private ?array $rows = null;

    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $result = parent::execute($params);

        if (stripos(ltrim($this->queryString), 'SELECT') === 0) {
            $this->rows = parent::fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    public function rowCount(): int
    {
        return ($this->rows === null) ? parent::rowCount() : count($this->rows);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->rows === null) {
            return parent::fetch($mode, $cursorOrientation, $cursorOffset);
        }

        return (count($this->rows) > 0) ? array_shift($this->rows) : false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->rows === null) {
            return parent::fetchAll($mode, ...$args);
        }

        $rows = $this->rows;
        $this->rows = array();
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->rows === null) {
            return parent::fetchColumn($column);
        }

        $row = $this->fetch();
        return ($row === false) ? false : array_values($row)[$column];
    }
}
