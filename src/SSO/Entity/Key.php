<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Roles\Entity\RolesRights;


class Key extends Entity 
{
    public function __construct(Database $database, int $id = 0) {
        parent::__construct($database, TBL_SSO_KEYS, 'key', $id);
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * In addition to the default ignored columns, don't log fot_views
     *
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
        ($this->newRecord)?[$this->columnPrefix.'_name']:[]);
    }
}
