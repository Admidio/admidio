<?php
namespace Admidio\Tests\Hooks;

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

/** A second hookable entity, to prove that a listener of one is not called for the other. */
class TestClient extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_clients', 'ocl', $id);
    }

    public function getHookId(): ?string
    {
        return 'oidc_client';
    }

    public function getSensitiveHookColumns(): array
    {
        return array('ocl_secret');
    }
}

/** An entity that opts out, standing in for Session, AutoLogin, LogChanges and the tokens. */
class TestSession extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_sessions', 'ses', $id);
    }
}
