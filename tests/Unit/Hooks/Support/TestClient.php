<?php
namespace Admidio\Tests\Unit\Hooks\Support;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

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
