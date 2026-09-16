<?php
namespace Admidio\Tests\Unit\Hooks\Support;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

/** A hookable entity, standing in for Room, Event, Announcement and the rest. */
class TestRoom extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_rooms', 'room', $id);
    }

    public function getHookId(): ?string
    {
        return 'room';
    }
}
