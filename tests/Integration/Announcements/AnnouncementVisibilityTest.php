<?php
/**
 * Announcement Visibility Tests
 *
 * Tests how a module record inherits its visibility from the category it lives in: a module
 * administrator reaches every category of the type, everybody else only reaches the categories
 * that carry a category_view or category_edit right for one of their roles.
 */

namespace Admidio\Tests\Integration\Announcements;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class AnnouncementVisibilityTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create an announcement in the given category, written by the given user.
     */
    private function createAnnouncement(User $author, int $orgId, int $catId, string $headline): int
    {
        return $this->withCurrentUser($author, $orgId, true, function () use ($catId, $headline) {
            $announcement = new Announcement($this->getDatabase());
            $announcement->setValue('ann_cat_id', $catId);
            $announcement->setValue('ann_headline', $headline);
            $announcement->setValue('ann_description', '<p>Body of ' . $headline . '</p>');
            $announcement->save();

            return (int) $announcement->getValue('ann_id');
        });
    }

    /**
     * Read an announcement back and answer a question about it as the given user.
     * The rights depend on the current user, so the object is read again for each of them.
     */
    private function askAs(User $user, int $orgId, int $annId, string $method): bool
    {
        return $this->withCurrentUser($user, $orgId, true, function () use ($annId, $method) {
            $announcement = new Announcement($this->getDatabase(), $annId);

            return $announcement->$method();
        });
    }

    /**
     * Test that a module administrator reaches every category of the type
     *
     * @testdox An announcement administrator sees and edits every announcement category
     */
    public function testModuleAdministratorReachesEveryCategory(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $catOne = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);
        $catTwo = $fixture->createAndSaveCategory('Notices', 'ANN', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Ann Admins', $org['org_id'], ['rol_announcements' => 1]);
        $admin = $fixture->createAndSaveUser('annadmin', 'aa@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $role['rol_id']);

        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        // no per category right was given, the module right alone opens both
        $visible = $adminUser->getAllVisibleCategories('ANN');
        $editable = $adminUser->getAllEditableCategories('ANN');

        $this->assertContains($catOne['cat_id'], $visible);
        $this->assertContains($catTwo['cat_id'], $visible);
        $this->assertContains($catOne['cat_id'], $editable);
        $this->assertContains($catTwo['cat_id'], $editable);
    }

    /**
     * Test that a view right opens a category for reading only
     *
     * @testdox A category_view right makes a category visible but not editable
     */
    public function testCategoryViewRightGrantsReadingOnly(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);
        $reader = $fixture->createAndSaveUser('annreader', 'ar@example.local');
        $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);

        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$role['rol_id']]);

        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $this->assertContains($category['cat_id'], $readerUser->getAllVisibleCategories('ANN'));
        $this->assertNotContains($category['cat_id'], $readerUser->getAllEditableCategories('ANN'));
    }

    /**
     * Test the default visibility of a category that carries no view right.
     * getAllVisibleCategories() matches a category either when one of the user's roles holds a
     * category_view right on it, or when no role holds that right at all. A category therefore
     * starts out public and becomes restricted with the first role that is granted.
     *
     * @testdox A category without any view right is visible to everybody
     */
    public function testCategoryWithoutViewRightIsPublic(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $stranger = $fixture->createAndSaveUser('annstranger', 'as@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        // nobody was granted a view right yet, so the category is open
        $this->assertContains($category['cat_id'], $strangerUser->getAllVisibleCategories('ANN'));

        // editing is not open in the same way, it always needs an explicit right
        $this->assertNotContains($category['cat_id'], $strangerUser->getAllEditableCategories('ANN'));
    }

    /**
     * Test that granting a view right restricts the category to that role
     *
     * @testdox Granting a view right restricts the category to the roles that hold it
     */
    public function testGrantingViewRightRestrictsTheCategory(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);
        $reader = $fixture->createAndSaveUser('annreader', 'ar@example.local');
        $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);

        $stranger = $fixture->createAndSaveUser('annstranger', 'as@example.local');

        // while no right exists the category is open to the stranger too
        $this->assertContains(
            $category['cat_id'],
            $this->loadUserInOrganization($stranger['usr_id'], $org['org_id'])->getAllVisibleCategories('ANN')
        );

        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$role['rol_id']]);

        // from now on only the granted role reaches it
        $this->assertContains(
            $category['cat_id'],
            $this->loadUserInOrganization($reader['usr_id'], $org['org_id'])->getAllVisibleCategories('ANN')
        );
        $this->assertNotContains(
            $category['cat_id'],
            $this->loadUserInOrganization($stranger['usr_id'], $org['org_id'])->getAllVisibleCategories('ANN')
        );
    }

    /**
     * Test that the announcement is stored in its category
     *
     * @testdox An announcement is stored with its category and headline
     */
    public function testAnnouncementIsStoredWithItsCategory(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Ann Admins', $org['org_id'], ['rol_announcements' => 1]);
        $admin = $fixture->createAndSaveUser('annauthor', 'au@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $role['rol_id']);
        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        $annId = $this->createAnnouncement($adminUser, $org['org_id'], $category['cat_id'], 'Spring Concert');
        $this->assertGreaterThan(0, $annId);

        $sql = 'SELECT ann_cat_id, ann_headline, ann_usr_id_create FROM ' . TBL_ANNOUNCEMENTS . ' WHERE ann_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$annId])->fetch();

        $this->assertEquals($category['cat_id'], (int) $row['ann_cat_id']);
        $this->assertEquals('Spring Concert', $row['ann_headline']);
        $this->assertEquals($admin['usr_id'], (int) $row['ann_usr_id_create']);
    }

    /**
     * Test that a reader of the category may view the announcement
     *
     * @testdox An announcement is visible to a user who may view its category
     */
    public function testAnnouncementIsVisibleToCategoryReader(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $adminRole = $fixture->createAndSaveRoleWithRights('Ann Admins', $org['org_id'], ['rol_announcements' => 1]);
        $readerRole = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);

        $admin = $fixture->createAndSaveUser('annauthor', 'au@example.local');
        $reader = $fixture->createAndSaveUser('annreader', 'ar@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $adminRole['rol_id']);
        $fixture->assignUserToRole($reader['usr_id'], $readerRole['rol_id']);

        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$readerRole['rol_id']]);

        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);
        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $annId = $this->createAnnouncement($adminUser, $org['org_id'], $category['cat_id'], 'Visible News');

        $this->assertTrue($this->askAs($readerUser, $org['org_id'], $annId, 'isVisible'));

        // reading does not imply editing
        $this->assertFalse($this->askAs($readerUser, $org['org_id'], $annId, 'isEditable'));
    }

    /**
     * Test that a user without the category right cannot see the announcement
     *
     * @testdox An announcement is hidden from a user who may not view its category
     */
    public function testAnnouncementIsHiddenFromStranger(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $adminRole = $fixture->createAndSaveRoleWithRights('Ann Admins', $org['org_id'], ['rol_announcements' => 1]);
        $admin = $fixture->createAndSaveUser('annauthor', 'au@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $adminRole['rol_id']);

        $stranger = $fixture->createAndSaveUser('annstranger', 'as@example.local');

        // restrict the category to a role the stranger is not in, otherwise it would stay public
        $insiderRole = $fixture->createAndSaveRoleWithRights('Insiders', $org['org_id']);
        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$insiderRole['rol_id']]);

        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $annId = $this->createAnnouncement($adminUser, $org['org_id'], $category['cat_id'], 'Secret News');

        $this->assertFalse($this->askAs($strangerUser, $org['org_id'], $annId, 'isVisible'));
        $this->assertFalse($this->askAs($strangerUser, $org['org_id'], $annId, 'isEditable'));
    }

    /**
     * Test that the module administrator may edit the announcement
     *
     * @testdox An announcement administrator may edit an announcement of their organization
     */
    public function testAdministratorMayEditAnnouncement(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Ann Org', 'annorg');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Ann Admins', $org['org_id'], ['rol_announcements' => 1]);
        $admin = $fixture->createAndSaveUser('annauthor', 'au@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $role['rol_id']);
        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        $annId = $this->createAnnouncement($adminUser, $org['org_id'], $category['cat_id'], 'Editable News');

        $this->assertTrue($this->askAs($adminUser, $org['org_id'], $annId, 'isVisible'));
        $this->assertTrue($this->askAs($adminUser, $org['org_id'], $annId, 'isEditable'));
    }

    /**
     * Test that the announcement does not leak into another organization
     *
     * @testdox An announcement of one organization is not visible in another
     */
    public function testAnnouncementDoesNotLeakIntoAnotherOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'annlka');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'annlkb');
        $category = $fixture->createAndSaveCategory('News', 'ANN', $orgA['org_id']);

        // the same role right in both organizations
        $roleA = $fixture->createAndSaveRoleWithRights('Ann Admins A', $orgA['org_id'], ['rol_announcements' => 1]);
        $roleB = $fixture->createAndSaveRoleWithRights('Ann Admins B', $orgB['org_id'], ['rol_announcements' => 1]);

        $author = $fixture->createAndSaveUser('annauthor', 'au@example.local');
        $other = $fixture->createAndSaveUser('annother', 'ao@example.local');
        $fixture->assignUserToRole($author['usr_id'], $roleA['rol_id']);
        $fixture->assignUserToRole($other['usr_id'], $roleB['rol_id']);

        $authorUser = $this->loadUserInOrganization($author['usr_id'], $orgA['org_id']);
        $otherUser = $this->loadUserInOrganization($other['usr_id'], $orgB['org_id']);

        $annId = $this->createAnnouncement($authorUser, $orgA['org_id'], $category['cat_id'], 'Org A News');

        // the administrator of the other organization does not reach the category
        $this->assertNotContains($category['cat_id'], $otherUser->getAllVisibleCategories('ANN'));

        // and the entity refuses to read a record of another organization at all, so the
        // question about its visibility is never reached
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('belongs to another organization');
        $this->askAs($otherUser, $orgB['org_id'], $annId, 'isVisible');
    }
}
