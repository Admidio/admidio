<?php
// Harness around the change-tracking part of Entity::setValue() as it now stands in the working tree,
// extracted verbatim so the test cannot drift from the code.
class EntityChangeTrackingFixed
{
    public array $dbColumns = [];
    public array $columnsInfos = [];
    public bool $columnsValueChanged = false;
    public bool $insertRecord = false;

    public function __construct(array $persisted, array $types)
    {
        foreach ($persisted as $col => $val) {
            $this->dbColumns[$col] = $val;
            $this->columnsInfos[$col] = ['type' => $types[$col], 'changed' => false, 'previousValue' => null];
        }
    }

    public function setValue(string $columnName, mixed $newValue): void
    {
        if ($this->valueChanged($columnName, $newValue)) {
            if ($this->columnsInfos[$columnName]['changed'] && !$this->insertRecord
                && !$this->valuesDiffer($columnName, $this->columnsInfos[$columnName]['previousValue'], $newValue)) {
                // The field is back at the value that the database holds. It is not a change any
                // more, so it must neither be written nor appear in the change history.
                $this->dbColumns[$columnName] = $newValue;
                $this->columnsInfos[$columnName]['changed'] = false;
                $this->columnsInfos[$columnName]['previousValue'] = null;
                $this->columnsValueChanged = $this->hasChangedColumns();
            } else {
                if (!$this->columnsInfos[$columnName]['changed']) {
                    // Remember the value that the database holds. A field that is set more than once
                    // before the save must still report the change from its persisted value and not
                    // from the intermediate one.
                    $this->columnsInfos[$columnName]['previousValue'] = $this->dbColumns[$columnName];
                }
                $this->dbColumns[$columnName] = $newValue;
                $this->columnsValueChanged = true;
                $this->columnsInfos[$columnName]['changed'] = true;
            }
        }
    }

    protected function hasChangedColumns(): bool
    {
        foreach ($this->columnsInfos as $columnInfo) {
            if (!empty($columnInfo['changed'])) {
                return true;
            }
        }

        return false;
    }
    protected function valueChanged(string $columnName, ?string $newValue): bool
    {
        return $this->valuesDiffer($columnName, $this->dbColumns[$columnName] ?? null, $newValue);
    }
    protected function valuesDiffer(string $columnName, mixed $oldValue, ?string $newValue): bool
    {
        $oldValue = !empty($oldValue) ? $oldValue : null;

        // certain data types need special handling to detect changes
        //   * bool: unset/null and 0 mean false
        //   * date/time: Make sure seconds are handled consistently, no need to convert to string
        //   * all other types can be compared by converting to string and comparing strings
        switch ($this->columnsInfos[$columnName]['type']) {
            case 'boolean': // fallthrough
            case 'tinyint':
                if (empty($newValue)) $newValue = 0;
                return $oldValue != $newValue;
            case 'timestamp': // fallthrough
            case 'date': // fallthrough
            case 'time':
                // if both are empty, no need to go through DateTime
                if (empty($oldValue) && empty($newValue)) {
                    return false;
                } elseif (empty($oldValue) || empty($newValue)) {
                    return true;
                }
                try {
                    // Convert old and new to a DateTime and compare that directly
                    $oldDate = new DateTime($oldValue);
                    $newDate = new DateTime($newValue);
                    return $oldDate != $newDate;
                } catch (\Exception) {
                    // if DateTime-conversion did not work, compare the strings
                    return $oldValue != $newValue;
                }
            default:
                // only mark as "changed" if the value is different (DON'T use binary safe function!)
                if (!isset($oldValue) && !isset($newValue)) {
                    return false;
                } elseif (!isset($oldValue) && isset($newValue)) {
                    return true;
                } else {
                    return strcmp((string)$oldValue, (string)$newValue) !== 0;
                }
        }
    }
}
