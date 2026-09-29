<?php

namespace Admidio\Tests\Integration\Infrastructure;

use Admidio\Photos\Entity\Album;
use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\DatabaseTestCase;

class EntityFormValueEncodingTest extends DatabaseTestCase
{
    public function testTextareaBreakoutPayloadIsEncodedForRoleAndPhotoDescriptions(): void
    {
        $payload = '</textarea><script>alert(1)</script>';
        $expected = '&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;';
        $database = $this->getDatabase();

        $roleId = (int) $database->queryPrepared(
            'SELECT rol_id FROM ' . TBL_ROLES . ' WHERE rol_administrator = true'
        )->fetchColumn();
        $role = new Role($database, $roleId);
        // Simulate a legacy value that bypassed normal input filtering before it reached the form.
        $role->setValue('rol_description', $payload, false);
        $this->assertSame($expected, $role->getValue('rol_description'));

        $album = new Album($database);
        $album->setValue('pho_description', $payload, false);
        $this->assertSame($expected, $album->getValue('pho_description'));
    }
}