<?php
namespace Admidio\Tests\Unit\Hooks\Support;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

/** A dependent record, removed together with the record it belongs to. */
class TestBooking extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_bookings', 'bok', $id);
    }

    public function getHookId(): ?string
    {
        return 'booking';
    }
}
