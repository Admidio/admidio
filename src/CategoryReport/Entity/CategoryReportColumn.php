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
}
