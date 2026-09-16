<?php
namespace Admidio\Tests\Unit\Hooks\Support;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

/** An entity that opts out, standing in for Session, AutoLogin, LogChanges and the tokens. */
class TestSession extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_sessions', 'ses', $id);
    }
}
