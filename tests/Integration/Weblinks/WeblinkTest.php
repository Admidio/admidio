<?php
/**
 * Weblink Tests
 *
 * Tests creating and reading weblinks and the category right that guards them.
 */

namespace Admidio\Tests\Integration\Weblinks;

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Weblinks\Entity\Weblink;

class WeblinkTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create a weblink in the given category as the given user.
     */
    private function createWeblink(User $author, int $orgId, int $catId, string $name, string $url): int
    {
        return $this->withCurrentUser($author, $orgId, true, function () use ($catId, $name, $url) {
            $link = new Weblink($this->getDatabase());
            $link->setValue('lnk_cat_id', $catId);
            $link->setValue('lnk_name', $name);
            $link->setValue('lnk_url', $url);
            // lnk_sequence is NOT NULL and is assigned by the module, not by the entity
            $link->setValue('lnk_sequence', 1);
            $link->save();

            return (int) $link->getValue('lnk_id');
        });
    }

    /**
     * Build a weblink administrator of the given organization.
     *
     * @return array{0: User, 1: array}
     */
    private function makeWeblinkAdmin(AdmidioTestFixture $fixture, int $orgId, string $login): array
    {
        $role = $fixture->createAndSaveRoleWithRights('Link Admins', $orgId, ['rol_weblinks' => 1]);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return [$this->loadUserInOrganization($user['usr_id'], $orgId), $user];
    }

    /**
     * Test that a weblinks administrator can create a weblink
     *
     * @testdox A weblinks administrator can create a weblink
     */
    public function testAdministratorCanCreateWeblink(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Link Org', 'lnkorg');
        $category = $fixture->createAndSaveCategory('Partners', 'LNK', $org['org_id']);
        [$adminUser] = $this->makeWeblinkAdmin($fixture, $org['org_id'], 'lnkadmin');

        $linkId = $this->createWeblink($adminUser, $org['org_id'], $category['cat_id'], 'Admidio', 'https://www.admidio.org/');

        $this->assertGreaterThan(0, $linkId);
    }

    /**
     * Test that creating a weblink without the right is refused
     *
     * @testdox Creating a weblink without the weblinks right is refused
     */
    public function testCreatingWeblinkWithoutRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Link Org', 'lnkorg');
        $category = $fixture->createAndSaveCategory('Partners', 'LNK', $org['org_id']);

        $stranger = $fixture->createAndSaveUser('lnkstranger', 'ls@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $this->expectException(Exception::class);

        $this->createWeblink($strangerUser, $org['org_id'], $category['cat_id'], 'Nope', 'https://example.org/');
    }

    /**
     * Test that the weblink data is stored as given
     *
     * @testdox A weblink is stored with its category, name and url
     */
    public function testWeblinkIsStoredWithItsData(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Link Org', 'lnkorg');
        $category = $fixture->createAndSaveCategory('Partners', 'LNK', $org['org_id']);
        [$adminUser, $admin] = $this->makeWeblinkAdmin($fixture, $org['org_id'], 'lnkadmin');

        $linkId = $this->createWeblink(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Admidio Homepage',
            'https://www.admidio.org/'
        );

        $sql = 'SELECT lnk_cat_id, lnk_name, lnk_url, lnk_counter, lnk_usr_id_create
                  FROM ' . TBL_LINKS . ' WHERE lnk_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$linkId])->fetch();

        $this->assertEquals($category['cat_id'], (int) $row['lnk_cat_id']);
        $this->assertEquals('Admidio Homepage', $row['lnk_name']);
        $this->assertEquals('https://www.admidio.org/', $row['lnk_url']);
        $this->assertEquals($admin['usr_id'], (int) $row['lnk_usr_id_create']);

        // a new weblink starts unvisited
        $this->assertEquals(0, (int) $row['lnk_counter']);
    }

    /**
     * Test that a weblink can be read back through its entity
     *
     * @testdox A weblink can be read back through the Weblink entity
     */
    public function testWeblinkCanBeReadBack(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Link Org', 'lnkorg');
        $category = $fixture->createAndSaveCategory('Partners', 'LNK', $org['org_id']);
        [$adminUser] = $this->makeWeblinkAdmin($fixture, $org['org_id'], 'lnkadmin');

        $linkId = $this->createWeblink($adminUser, $org['org_id'], $category['cat_id'], 'Readable', 'https://example.org/');

        $link = new Weblink($this->getDatabase(), $linkId);
        $this->assertEquals('Readable', $link->getValue('lnk_name'));
        $this->assertEquals($category['cat_id'], (int) $link->getValue('lnk_cat_id'));

        // the joined category is available once the record was read
        $this->assertEquals($org['org_id'], (int) $link->getValue('cat_org_id'));
    }

    /**
     * Test that a view right alone does not allow creating weblinks
     *
     * @testdox A category_view right does not allow creating weblinks
     */
    public function testCategoryViewRightDoesNotAllowCreating(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Link Org', 'lnkorg');
        $category = $fixture->createAndSaveCategory('Partners', 'LNK', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);
        $reader = $fixture->createAndSaveUser('lnkreader', 'lr@example.local');
        $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);

        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$role['rol_id']]);

        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $this->assertContains($category['cat_id'], $readerUser->getAllVisibleCategories('LNK'));
        $this->assertNotContains($category['cat_id'], $readerUser->getAllEditableCategories('LNK'));

        $this->expectException(Exception::class);
        $this->createWeblink($readerUser, $org['org_id'], $category['cat_id'], 'Nope', 'https://example.org/');
    }

    /**
     * Test that weblinks stay in their organization
     *
     * @testdox A weblink category of one organization is not editable from another
     */
    public function testWeblinksAreScopedToTheirOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'lnka');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'lnkb');

        $catA = $fixture->createAndSaveCategory('Partners A', 'LNK', $orgA['org_id']);
        $catB = $fixture->createAndSaveCategory('Partners B', 'LNK', $orgB['org_id']);

        [$adminA] = $this->makeWeblinkAdmin($fixture, $orgA['org_id'], 'lnkadmina');

        $this->assertContains($catA['cat_id'], $adminA->getAllEditableCategories('LNK'));
        $this->assertNotContains($catB['cat_id'], $adminA->getAllEditableCategories('LNK'));

        // creating in the other organization's category is refused
        $this->expectException(Exception::class);
        $this->createWeblink($adminA, $orgA['org_id'], $catB['cat_id'], 'Cross', 'https://example.org/');
    }
}
