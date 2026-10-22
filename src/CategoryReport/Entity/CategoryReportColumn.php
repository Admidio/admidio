<?php

namespace Admidio\CategoryReport\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

/**
 * Entity for one column of a category report configuration.
 */
class CategoryReportColumn extends Entity
{
    /** @var array<string,string> */
    private const FIELD_TYPES = array(
        'p' => 'profile_field',
        'c' => 'role_category',
        'r' => 'role_membership',
        'w' => 'role_membership_without_leader',
        'l' => 'role_leader',
        'f' => 'former_role_membership',
        'b' => 'membership_start',
        'e' => 'membership_end',
        'd' => 'membership_duration',
        'u' => 'user_field',
        'a' => 'role_memberships',
        'n' => 'row_number'
    );

    /**
     * Creates a column entity and optionally loads an existing category report column.
     *
     * @param Database $database Database connection used to load and persist the column.
     * @param int $columnId ID of the column that should be loaded. If 0, an empty entity is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $columnId = 0)
    {
        parent::__construct($database, TBL_CATEGORY_REPORT_COLUMNS, 'crc', $columnId);
    }

    /**
     * Returns the hook identifier used for category report column persistence events.
     *
     * @return string|null Hook identifier of this entity.
     */
    public function getHookId(): ?string
    {
        return 'category_report_column';
    }

    /**
     * Returns the legacy field identifier used by the category report UI and generator.
     */
    public function getField(): string
    {
        $fieldType = (string)$this->getValue('crc_field_type', 'database');
        if ($fieldType === 'special_field') {
            return (string)$this->getValue('crc_special_field', 'database');
        }
        $prefix = (string)array_search($fieldType, self::FIELD_TYPES, true);

        $profileFieldId = (int)$this->getValue('crc_usf_id');
        if ($profileFieldId > 0) {
            return $prefix . $profileFieldId;
        }

        $roleId = (int)$this->getValue('crc_rol_id');
        if ($roleId > 0) {
            return $prefix . $roleId;
        }

        $categoryId = (int)$this->getValue('crc_cat_id');
        if ($categoryId > 0) {
            return $prefix . $categoryId;
        }

        return $prefix . (string)$this->getValue('crc_special_field', 'database');
    }

    /**
     * Splits the field identifier used by the UI into its normalized database columns.
     */
    public function setField(string $field): void
    {
        foreach (self::getFieldDatabaseValues($field) as $column => $value) {
            $this->setValue($column, $value);
        }
    }

    /**
     * Converts a category report field identifier to normalized database values.
     *
     * @return array{crc_field_type:string,crc_usf_id:?int,crc_rol_id:?int,crc_cat_id:?int,crc_special_field:?string}
     */
    public static function getFieldDatabaseValues(string $field): array
    {
        $prefix = substr($field, 0, 1);
        $values = array(
            'crc_field_type' => 'special_field',
            'crc_usf_id' => null,
            'crc_rol_id' => null,
            'crc_cat_id' => null,
            'crc_special_field' => null
        );

        if (preg_match('/^p([1-9][0-9]*)$/', $field, $matches) === 1) {
            $values['crc_field_type'] = self::FIELD_TYPES['p'];
            $values['crc_usf_id'] = (int)$matches[1];
        } elseif (preg_match('/^c([1-9][0-9]*)$/', $field, $matches) === 1) {
            $values['crc_field_type'] = self::FIELD_TYPES['c'];
            $values['crc_cat_id'] = (int)$matches[1];
        } elseif (preg_match('/^([rwlfbed])([1-9][0-9]*)$/', $field, $matches) === 1) {
            $values['crc_field_type'] = self::FIELD_TYPES[$matches[1]];
            $values['crc_rol_id'] = (int)$matches[2];
        } elseif (in_array($prefix, array('u', 'a', 'd', 'n'), true)) {
            $values['crc_field_type'] = self::FIELD_TYPES[$prefix];
            $values['crc_special_field'] = substr($field, 1);
        } else {
            $values['crc_special_field'] = $field;
        }

        return $values;
    }
}
