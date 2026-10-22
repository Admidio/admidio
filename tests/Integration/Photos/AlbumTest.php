<?php
/**
 * Photo Album Tests
 *
 * Tests the photo albums. Like folders and unlike the other modules an album is not guarded by a
 * category: it belongs to an organization directly, forms a hierarchy, and its visibility follows
 * the organization, the locked flag and the photo administration right.
 *
 * Only the database side is covered here, the tests never touch the file system.
 */

namespace Admidio\Tests\Integration\Photos;

use Admidio\Infrastructure\Exception;
use Admidio\Photos\Entity\Album;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class AlbumTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create an album as the given user. Album::save() takes the organization from the globals,
     * so the call has to run inside the user context.
     */
    private function createAlbum(User $owner, int $orgId, string $name, ?int $parentId = null): int
    {
        return $this->withCurrentUser($owner, $orgId, true, function () use ($name, $parentId) {
            $album = new Album($this->getDatabase());
            $album->setValue('pho_name', $name);
            $album->setValue('pho_begin', '2030-07-01');
            $album->setValue('pho_end', '2030-07-14');
            if ($parentId !== null) {
                $album->setValue('pho_pho_id_parent', $parentId);
            }
            $album->save();

            return (int) $album->getValue('pho_id');
        });
    }

    /**
     * Build a photo administrator of the given organization.
     */
    private function makePhotoAdmin(AdmidioTestFixture $fixture, int $orgId, string $login): User
    {
        $role = $fixture->createAndSaveRoleWithRights('Photo Admins', $orgId, ['rol_photo' => 1]);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $this->loadUserInOrganization($user['usr_id'], $orgId);
    }

    /**
     * Ask a question about an album as the given user.
     */
    private function askAs(User $user, int $orgId, int $albumId, string $method): bool
    {
        return $this->withCurrentUser($user, $orgId, true, function () use ($albumId, $method) {
            $album = new Album($this->getDatabase(), $albumId);

            return $album->$method();
        });
    }

    /**
     * Test that an album belongs to the organization it was created in
     *
     * @testdox An album is stored under the organization it was created in
     */
    public function testAlbumBelongsToTheCurrentOrganization(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $albumId = $this->createAlbum($admin, $org['org_id'], 'Summer 2030');

        $sql = 'SELECT pho_org_id, pho_name, pho_quantity, pho_locked, pho_pho_id_parent
                  FROM ' . TBL_PHOTOS . ' WHERE pho_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$albumId])->fetch();

        // Album::save() takes the organization from the current context
        $this->assertEquals($org['org_id'], (int) $row['pho_org_id']);
        $this->assertEquals('Summer 2030', $row['pho_name']);
        $this->assertNull($row['pho_pho_id_parent']);

        // a new album starts empty and unlocked
        $this->assertEquals(0, (int) $row['pho_quantity']);
        $this->assertFalse((bool) $row['pho_locked']);
    }

    /**
     * Test that albums form a hierarchy
     *
     * @testdox Albums form a hierarchy through their parent album
     */
    public function testAlbumsFormAHierarchy(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $parentId = $this->createAlbum($admin, $org['org_id'], 'Trips');
        $childId = $this->createAlbum($admin, $org['org_id'], 'Alps 2030', $parentId);

        $sql = 'SELECT pho_pho_id_parent FROM ' . TBL_PHOTOS . ' WHERE pho_id = ?';
        $this->assertEquals(
            $parentId,
            (int) $this->getDatabase()->queryPrepared($sql, [$childId])->fetch()['pho_pho_id_parent']
        );

        $hasChildren = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($parentId, $childId) {
            $parent = new Album($this->getDatabase(), $parentId);
            $child = new Album($this->getDatabase(), $childId);

            return [$parent->hasChildAlbums(), $child->hasChildAlbums()];
        });

        $this->assertTrue($hasChildren[0]);
        $this->assertFalse($hasChildren[1]);
    }

    /**
     * Test that a new album holds no images
     *
     * @testdox A new album holds no images
     */
    public function testNewAlbumHoldsNoImages(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $albumId = $this->createAlbum($admin, $org['org_id'], 'Empty Album');

        $count = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($albumId) {
            $album = new Album($this->getDatabase(), $albumId);

            return $album->countImages();
        });

        $this->assertEquals(0, $count);
    }

    /**
     * Test that an ordinary user may view an album but not edit it
     *
     * @testdox An album is visible to everybody but editable only by a photo administrator
     */
    public function testAlbumIsVisibleButNotEditableWithoutTheRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $albumId = $this->createAlbum($admin, $org['org_id'], 'Public Album');

        $plain = $fixture->createAndSaveUser('phoplain', 'pp@example.local');
        $plainUser = $this->loadUserInOrganization($plain['usr_id'], $org['org_id']);

        $this->assertTrue($this->askAs($plainUser, $org['org_id'], $albumId, 'isVisible'));
        $this->assertFalse($this->askAs($plainUser, $org['org_id'], $albumId, 'isEditable'));

        $this->assertTrue($this->askAs($admin, $org['org_id'], $albumId, 'isVisible'));
        $this->assertTrue($this->askAs($admin, $org['org_id'], $albumId, 'isEditable'));
    }

    /**
     * Test that a locked album is reserved for the administrators
     *
     * @testdox A locked album is visible only to a photo administrator
     */
    public function testLockedAlbumIsOnlyVisibleToAdministrators(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $albumId = $this->createAlbum($admin, $org['org_id'], 'Locked Album');
        $db->queryPrepared('UPDATE ' . TBL_PHOTOS . ' SET pho_locked = ? WHERE pho_id = ?', [1, $albumId]);

        $plain = $fixture->createAndSaveUser('phoplain', 'pp@example.local');
        $plainUser = $this->loadUserInOrganization($plain['usr_id'], $org['org_id']);

        $this->assertFalse($this->askAs($plainUser, $org['org_id'], $albumId, 'isVisible'));
        $this->assertTrue($this->askAs($admin, $org['org_id'], $albumId, 'isVisible'));
    }

    /**
     * Test that an album of another organization is not visible
     *
     * @testdox An album of one organization is not visible in another
     */
    public function testAlbumIsNotVisibleInAnotherOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'phoa');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'phob');

        $adminA = $this->makePhotoAdmin($fixture, $orgA['org_id'], 'phoadmina');
        $adminB = $this->makePhotoAdmin($fixture, $orgB['org_id'], 'phoadminb');

        $albumId = $this->createAlbum($adminA, $orgA['org_id'], 'Org A Album');

        // the album belongs to org A, so even a photo administrator of org B does not reach it:
        // the entity already refuses to read a record of another organization
        $this->assertTrue($this->askAs($adminA, $orgA['org_id'], $albumId, 'isVisible'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('belongs to another organization');
        $this->askAs($adminB, $orgB['org_id'], $albumId, 'isVisible');
    }

    /**
     * Test that albums are listed per organization
     *
     * @testdox Albums are listed only for the organization they belong to
     */
    public function testAlbumsAreScopedToTheirOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'phoa');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'phob');

        $adminA = $this->makePhotoAdmin($fixture, $orgA['org_id'], 'phoadmina');
        $adminB = $this->makePhotoAdmin($fixture, $orgB['org_id'], 'phoadminb');

        $albumA = $this->createAlbum($adminA, $orgA['org_id'], 'Album A');
        $albumB = $this->createAlbum($adminB, $orgB['org_id'], 'Album B');

        $sql = 'SELECT pho_id FROM ' . TBL_PHOTOS . ' WHERE pho_org_id = ?';
        $idsA = array_map('intval', array_column(
            $this->getDatabase()->queryPrepared($sql, [$orgA['org_id']])->fetchAll(),
            'pho_id'
        ));

        $this->assertContains($albumA, $idsA);
        $this->assertNotContains($albumB, $idsA);
    }

    /**
     * Test that the album period is stored as given
     *
     * @testdox The period of an album is stored as given
     */
    public function testAlbumPeriodIsStored(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Photo Org', 'phoorg');
        $admin = $this->makePhotoAdmin($fixture, $org['org_id'], 'phoadmin');

        $albumId = $this->createAlbum($admin, $org['org_id'], 'Summer 2030');

        $sql = 'SELECT pho_begin, pho_end FROM ' . TBL_PHOTOS . ' WHERE pho_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$albumId])->fetch();

        $this->assertEquals('2030-07-01', $row['pho_begin']);
        $this->assertEquals('2030-07-14', $row['pho_end']);
    }
}
