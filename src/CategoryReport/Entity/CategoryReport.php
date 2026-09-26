<?php

namespace Admidio\CategoryReport\Entity;

use Admidio\Changelog\Entity\LogChanges;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

/**
 * Entity for a category report configuration and its ordered columns.
 */
class CategoryReport extends Entity
{
    /** @var array<int,CategoryReportColumn> */
    private array $columns = array();

    /** @var array<int,CategoryReportColumn> */
    private array $removedColumns = array();

    /** @var array<int,array{field:string,condition:string}> */
    private array $storedColumnDefinitions = array();

    /**
     * Creates a category report entity and loads its column entities if a report ID is supplied.
     *
     * @param Database $database Database connection used to load and persist the report.
     * @param int $reportId ID of the category report that should be loaded. If 0, an empty entity is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $reportId = 0)
    {
        parent::__construct($database, TBL_CATEGORY_REPORT, 'crt', $reportId);
    }

    /**
     * Returns the hook identifier used for category report persistence events.
     *
     * @return string|null Hook identifier of this entity.
     */
    public function getHookId(): ?string
    {
        return 'category_report';
    }

    /**
     * Loads a category report and its ordered column entities by its database ID.
     *
     * @param int $id ID of the category report that should be loaded.
     * @return bool Returns true if the report was found, otherwise false.
     * @throws Exception
     */
    public function readDataById(int $id): bool
    {
        $found = parent::readDataById($id);
        if ($found) {
            $this->readColumns();
        }

        return $found;
    }

    /**
     * Returns all column entities of this report indexed by their column number.
     *
     * @return array<int,CategoryReportColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns the ordered column configuration as field and condition pairs.
     *
     * @return array<int,array{field:string,condition:string}>
     */
    public function getColumnDefinitions(): array
    {
        $definitions = array();
        foreach ($this->columns as $column) {
            $definitions[] = array(
                'field' => (string)$column->getValue('crc_field', 'database'),
                'condition' => (string)$column->getValue('crc_condition', 'database')
            );
        }

        return $definitions;
    }

    /**
     * Replace all configured columns while preserving their submitted order.
     *
     * Existing column entities at the same position are reused. Surplus columns are marked for
     * deletion and new positions receive a new column entity.
     *
     * @param array<int,array{field:string,condition?:string}> $definitions Ordered column definitions.
     * @return void
     * @throws Exception
     */
    public function setColumns(array $definitions): void
    {
        $existingColumns = $this->columns;
        $this->columns = array();
        foreach (array_values($definitions) as $index => $definition) {
            $number = $index + 1;
            if (isset($existingColumns[$number])) {
                $column = $existingColumns[$number];
                unset($existingColumns[$number]);
            } else {
                $column = new CategoryReportColumn($this->db);
            }
            $column->setValue('crc_crt_id', (int)$this->getValue('crt_id'));
            $column->setValue('crc_number', $number);
            $column->setValue('crc_field', $definition['field']);
            $column->setValue('crc_condition', $definition['condition'] ?? '');
            $this->columns[$number] = $column;
        }

        foreach ($existingColumns as $column) {
            if ((int)$column->getValue('crc_id') > 0) {
                $this->removedColumns[] = $column;
            }
        }
    }

    /**
     * Saves the report and all associated column entities within one transaction.
     *
     * Removed columns are deleted before new or changed columns are saved. Column changes are
     * recorded as part of the parent report's changelog entry.
     *
     * @param bool $updateFingerPrint Whether creator and editor metadata should be updated.
     * @return bool Returns true if the report or at least one of its columns was changed.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        $this->db->startTransaction();
        $previousChangeSet = LogChanges::startChangeSet();

        $oldFields = implode(',', array_column($this->storedColumnDefinitions, 'field'));
        $oldConditions = implode(',', array_column($this->storedColumnDefinitions, 'condition'));
        $newDefinitions = $this->getColumnDefinitions();
        $newFields = implode(',', array_column($newDefinitions, 'field'));
        $newConditions = implode(',', array_column($newDefinitions, 'condition'));

        $saved = parent::save($updateFingerPrint);
        $reportId = (int)$this->getValue('crt_id');

        foreach ($this->removedColumns as $column) {
            $saved = $column->delete() || $saved;
        }
        $this->removedColumns = array();

        foreach ($this->columns as $column) {
            if ((int)$column->getValue('crc_crt_id') === 0) {
                $column->setValue('crc_crt_id', $reportId);
            }
            $saved = $column->save($updateFingerPrint) || $saved;
        }

        $columnChanges = array();
        if ($oldFields !== $newFields) {
            $columnChanges['crt_col_fields'] = array('oldValue' => $oldFields, 'newValue' => $newFields);
        }
        if ($oldConditions !== $newConditions) {
            $columnChanges['crt_col_conditions'] = array('oldValue' => $oldConditions, 'newValue' => $newConditions);
        }
        if (count($columnChanges) > 0) {
            $this->logModifications($columnChanges);
            $saved = true;
        }
        $this->storedColumnDefinitions = $newDefinitions;

        LogChanges::endChangeSet($previousChangeSet);
        $this->db->endTransaction();

        return $saved;
    }

    /**
     * Deletes the report and each associated column through their entity lifecycle.
     *
     * @return bool Returns true if the report was deleted successfully.
     * @throws Exception
     */
    public function delete(): bool
    {
        $this->db->startTransaction();

        foreach ($this->columns as $column) {
            $column->delete();
        }
        $deleted = parent::delete();

        $this->db->endTransaction();

        return $deleted;
    }

    /**
     * Resets the report entity and its loaded or pending column state.
     *
     * @return void
     * @throws Exception
     */
    public function clear(): void
    {
        $this->columns = array();
        $this->removedColumns = array();
        $this->storedColumnDefinitions = array();
        parent::clear();
    }

    /**
     * Loads the ordered column records of the current report into column entities.
     *
     * @return void
     * @throws Exception
     */
    private function readColumns(): void
    {
        $statement = $this->db->queryPrepared(
            'SELECT *
               FROM ' . TBL_CATEGORY_REPORT_COLUMNS . '
              WHERE crc_crt_id = ?
           ORDER BY crc_number',
            array((int)$this->getValue('crt_id'))
        );

        while ($row = $statement->fetch()) {
            $column = new CategoryReportColumn($this->db);
            $column->setArray($row);
            $this->columns[(int)$row['crc_number']] = $column;
        }
        $this->storedColumnDefinitions = $this->getColumnDefinitions();
    }
}
