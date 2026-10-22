<?php

namespace Admidio\Tests\Support;

use Admidio\Users\Entity\User;
use RuntimeException;

/**
 * Base class for integration tests that exercise production paths requiring administrator rights.
 *
 * The administrator is not mocked and no rights are injected. The class resolves the real
 * administrator membership created by the production installer and loads that user through the
 * normal User entity.
 */
abstract class AdministratorTestCase extends DatabaseTestCase
{
    private mixed $previousCurrentUser = null;
    private int $previousCurrentUserId = 0;
    private bool $previousValidLogin = false;
    private bool $administratorContextActive = false;

    protected function setUp(): void
    {
        parent::setUp();

        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gProfileFields, $gValidLogin;

        $this->previousCurrentUser = $gCurrentUser;
        $this->previousCurrentUserId = (int)$gCurrentUserId;
        $this->previousValidLogin = (bool)$gValidLogin;

        $administratorId = (int)$this->getDatabase()->queryPrepared(
            'SELECT mem_usr_id
               FROM ' . TBL_MEMBERS . '
         INNER JOIN ' . TBL_ROLES . '
                 ON rol_id = mem_rol_id
         INNER JOIN ' . TBL_CATEGORIES . '
                 ON cat_id = rol_cat_id
              WHERE cat_org_id = ?
                AND rol_administrator = true
                AND rol_valid = true
                AND mem_begin <= ?
                AND mem_end > ?
           ORDER BY mem_id
              LIMIT 1',
            array($gCurrentOrgId, DATE_NOW, DATE_NOW)
        )->fetchColumn();

        if ($administratorId <= 0) {
            throw new RuntimeException(
                'The production test installation did not create an active Administrator membership.'
            );
        }

        $gCurrentUserId = $administratorId;
        $gCurrentUser = new User($this->getDatabase(), $gProfileFields, $administratorId);
        $gValidLogin = true;
        $this->administratorContextActive = true;
    }

    protected function tearDown(): void
    {
        global $gCurrentUser, $gCurrentUserId, $gValidLogin;

        if ($this->administratorContextActive) {
            $gCurrentUser = $this->previousCurrentUser;
            $gCurrentUserId = $this->previousCurrentUserId;
            $gValidLogin = $this->previousValidLogin;
            $this->administratorContextActive = false;
        }

        parent::tearDown();
    }
}
