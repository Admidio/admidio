<?php
/**
 * User Relation Workflow Tests
 *
 * Tests the relations between two people, such as parent and child. A relation type carries its own
 * counterpart in urt_id_inverse, and a relation between two users is stored as two rows, one per
 * direction. Which of the three shapes a type has - asymmetrical, symmetrical or unidirectional -
 * follows from that column alone.
 */

namespace Admidio\Tests\Integration\Workflows;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;

class UserRelationWorkflowTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        $administrator = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);

        return $this->withCurrentUser($administrator, self::ORG_ID, true, $callback);
    }

    /**
     * Read a delivered relation type by its untranslated name.
     */
    private function relationType(string $nameIntern): UserRelationType
    {
        $type = new UserRelationType($this->getDatabase());
        $type->readDataByColumns(array('urt_name' => $nameIntern));

        return $type;
    }

    /**
     * Create both directions of a relation, the way the user relations module does.
     */
    private function relate(UserRelationType $type, int $usrId1, int $usrId2): int
    {
        $db = $this->getDatabase();

        $relation = new UserRelation($db);
        $relation->setValue('ure_urt_id', (int) $type->getValue('urt_id'));
        $relation->setValue('ure_usr_id1', $usrId1);
        $relation->setValue('ure_usr_id2', $usrId2);
        $relation->save();

        if (!$type->isUnidirectional()) {
            $inverse = new UserRelation($db);
            $inverse->setValue('ure_urt_id', (int) $type->getValue('urt_id_inverse'));
            $inverse->setValue('ure_usr_id1', $usrId2);
            $inverse->setValue('ure_usr_id2', $usrId1);
            $inverse->save();
        }

        return (int) $relation->getValue('ure_id');
    }

    /**
     * Test that the installation delivers the usual relation types
     *
     * @testdox The installation delivers the standard relation types with their counterparts
     */
    public function testInstallationDeliversTheStandardRelationTypes(): void
    {
        $sql = 'SELECT urt_name, urt_id, urt_id_inverse FROM ' . TBL_USER_RELATION_TYPES . ' ORDER BY urt_id';
        $types = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $names = array_column($types, 'urt_name');
        $this->assertContains('SYS_PARENT', $names);
        $this->assertContains('SYS_CHILD', $names);
        $this->assertContains('SYS_SIBLING', $names);

        // every delivered type has a counterpart, so no relation is left one sided
        foreach ($types as $type) {
            $this->assertNotNull($type['urt_id_inverse'], $type['urt_name']);
        }
    }

    /**
     * Test that a pair of types points at each other
     *
     * @testdox A type and its counterpart point at each other and are asymmetrical
     */
    public function testATypeAndItsCounterpartPointAtEachOther(): void
    {
        $this->asAdministrator(function () {
            $parent = $this->relationType('SYS_PARENT');
            $child = $this->relationType('SYS_CHILD');

            $this->assertEquals((int) $child->getValue('urt_id'), (int) $parent->getValue('urt_id_inverse'));
            $this->assertEquals((int) $parent->getValue('urt_id'), (int) $child->getValue('urt_id_inverse'));

            $this->assertEquals(UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL, $parent->getRelationTypeString());
            $this->assertTrue($parent->isAsymmetrical());
            $this->assertFalse($parent->isSymmetrical());

            // getInverse resolves the counterpart
            $this->assertEquals((int) $child->getValue('urt_id'), (int) $parent->getInverse()->getValue('urt_id'));

            // the delivered names are language ids that are translated on read
            $this->assertEquals('SYS_PARENT', $parent->getValue('urt_name', 'database'));
            $this->assertEquals('Parent', $parent->getValue('urt_name'));
        });
    }

    /**
     * Test that a type can be its own counterpart
     *
     * @testdox A type that is its own counterpart is symmetrical
     */
    public function testATypeThatIsItsOwnCounterpartIsSymmetrical(): void
    {
        $this->asAdministrator(function () {
            $sibling = $this->relationType('SYS_SIBLING');

            $this->assertEquals((int) $sibling->getValue('urt_id'), (int) $sibling->getValue('urt_id_inverse'));
            $this->assertEquals(UserRelationType::USER_RELATION_TYPE_SYMMETRICAL, $sibling->getRelationTypeString());
            $this->assertTrue($sibling->isSymmetrical());
            $this->assertFalse($sibling->isAsymmetrical());
        });
    }

    /**
     * Test that a type without a counterpart is one sided
     *
     * @testdox A type without a counterpart is unidirectional
     */
    public function testATypeWithoutACounterpartIsUnidirectional(): void
    {
        $urtId = $this->asAdministrator(function () {
            $type = new UserRelationType($this->getDatabase());
            $type->setValue('urt_name', 'Follows');
            $type->setValue('urt_name_male', 'Follows');
            $type->setValue('urt_name_female', 'Follows');
            $type->save();

            return (int) $type->getValue('urt_id');
        });

        $this->asAdministrator(function () use ($urtId) {
            // the type has to be read back: the shape is only decided for a stored record
            $type = new UserRelationType($this->getDatabase(), $urtId);

            $this->assertNull($type->getValue('urt_id_inverse'));
            $this->assertEquals(UserRelationType::USER_RELATION_TYPE_UNIDIRECTIONAL, $type->getRelationTypeString());
            $this->assertTrue($type->isUnidirectional());
            $this->assertNull($type->getInverse());
        });
    }

    /**
     * Test that a relation is stored in both directions
     *
     * @testdox Relating two users stores one row per direction
     */
    public function testRelatingTwoUsersStoresOneRowPerDirection(): void
    {
        $fixture = $this->getFixture();
        $parentUser = $fixture->createAndSaveUser('relparent', 'rp@example.local');
        $childUser = $fixture->createAndSaveUser('relchild', 'rc@example.local');

        $this->asAdministrator(function () use ($parentUser, $childUser) {
            $parentType = $this->relationType('SYS_PARENT');
            $this->relate($parentType, $parentUser['usr_id'], $childUser['usr_id']);
        });

        $sql = 'SELECT urt_name, ure_usr_id1, ure_usr_id2 FROM ' . TBL_USER_RELATIONS . '
                  INNER JOIN ' . TBL_USER_RELATION_TYPES . ' ON urt_id = ure_urt_id
                 ORDER BY ure_id';
        $rows = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertEquals('SYS_PARENT', $rows[0]['urt_name']);
        $this->assertEquals($parentUser['usr_id'], (int) $rows[0]['ure_usr_id1']);
        $this->assertEquals($childUser['usr_id'], (int) $rows[0]['ure_usr_id2']);

        // the mirrored row carries the counterpart type and the users the other way round
        $this->assertEquals('SYS_CHILD', $rows[1]['urt_name']);
        $this->assertEquals($childUser['usr_id'], (int) $rows[1]['ure_usr_id1']);
        $this->assertEquals($parentUser['usr_id'], (int) $rows[1]['ure_usr_id2']);
    }

    /**
     * Test that the mirrored relation can be found
     *
     * @testdox The counterpart of a relation is found through its type
     */
    public function testTheCounterpartOfARelationIsFound(): void
    {
        $fixture = $this->getFixture();
        $parentUser = $fixture->createAndSaveUser('relparent', 'rp@example.local');
        $childUser = $fixture->createAndSaveUser('relchild', 'rc@example.local');

        $this->asAdministrator(function () use ($parentUser, $childUser) {
            $parentType = $this->relationType('SYS_PARENT');
            $ureId = $this->relate($parentType, $parentUser['usr_id'], $childUser['usr_id']);

            $relation = new UserRelation($this->getDatabase(), $ureId);
            $inverse = $relation->getInverse();

            $this->assertNotNull($inverse);
            $this->assertEquals($childUser['usr_id'], (int) $inverse->getValue('ure_usr_id1'));
            $this->assertEquals($parentUser['usr_id'], (int) $inverse->getValue('ure_usr_id2'));
            $this->assertEquals((int) $parentType->getValue('urt_id_inverse'), (int) $inverse->getValue('ure_urt_id'));
        });
    }

    /**
     * Test that a one sided relation has no counterpart to find
     *
     * @testdox A relation of a unidirectional type has no counterpart
     */
    public function testARelationOfAUnidirectionalTypeHasNoCounterpart(): void
    {
        $fixture = $this->getFixture();
        $userA = $fixture->createAndSaveUser('relone', 'r1@example.local');
        $userB = $fixture->createAndSaveUser('reltwo', 'r2@example.local');

        $urtId = $this->asAdministrator(function () {
            $type = new UserRelationType($this->getDatabase());
            $type->setValue('urt_name', 'Follows');
            $type->setValue('urt_name_male', 'Follows');
            $type->setValue('urt_name_female', 'Follows');
            $type->save();

            return (int) $type->getValue('urt_id');
        });

        $this->asAdministrator(function () use ($urtId, $userA, $userB) {
            $type = new UserRelationType($this->getDatabase(), $urtId);
            $ureId = $this->relate($type, $userA['usr_id'], $userB['usr_id']);

            // only one row was written, because the type has no counterpart
            $sql = 'SELECT COUNT(*) FROM ' . TBL_USER_RELATIONS;
            $this->assertEquals(1, (int) $this->getDatabase()->queryPrepared($sql)->fetchColumn());

            $relation = new UserRelation($this->getDatabase(), $ureId);
            $this->assertNull($relation->getInverse());
        });
    }

    /**
     * Test that removing a relation removes both directions
     *
     * @testdox Deleting a relation deletes its counterpart as well
     */
    public function testDeletingARelationDeletesItsCounterpart(): void
    {
        $fixture = $this->getFixture();
        $parentUser = $fixture->createAndSaveUser('relparent', 'rp@example.local');
        $childUser = $fixture->createAndSaveUser('relchild', 'rc@example.local');

        $this->asAdministrator(function () use ($parentUser, $childUser) {
            $parentType = $this->relationType('SYS_PARENT');
            $ureId = $this->relate($parentType, $parentUser['usr_id'], $childUser['usr_id']);

            $sql = 'SELECT COUNT(*) FROM ' . TBL_USER_RELATIONS;
            $this->assertEquals(2, (int) $this->getDatabase()->queryPrepared($sql)->fetchColumn());

            $relation = new UserRelation($this->getDatabase(), $ureId);
            $relation->delete();

            $this->assertEquals(0, (int) $this->getDatabase()->queryPrepared($sql)->fetchColumn());
        });
    }
}
