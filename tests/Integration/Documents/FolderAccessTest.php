<?php
/**
 * Folder Access Tests
 *
 * Tests the access rules of the documents module. Unlike the other modules a folder is not
 * guarded by a category: it belongs to an organization directly and carries its own
 * folder_view and folder_upload rights, plus a public flag that opens it for everybody.
 *
 * Only the database side is covered here, the tests never touch the file system.
 */

namespace Admidio\Tests\Integration\Documents;

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class FolderAccessTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create a folder record as the given user. Folder::save() takes the organization and the
     * owner from the globals, so the call has to run inside the user context.
     */
    private function createFolder(User $owner, int $orgId, string $name, bool $public = false, ?int $parentId = null): int
    {
        return $this->withCurrentUser($owner, $orgId, true, function () use ($name, $public, $parentId) {
            $folder = new Folder($this->getDatabase());
            $folder->setValue('fol_type', 'DOCUMENTS');
            $folder->setValue('fol_name', $name);
            $folder->setValue('fol_path', '/adm_my_files');
            $folder->setValue('fol_public', $public ? 1 : 0);
            if ($parentId !== null) {
                $folder->setValue('fol_fol_id_parent', $parentId);
            }
            $folder->save();

            return (int) $folder->getValue('fol_id');
        });
    }

    private function folderUuid(int $folderId): string
    {
        $sql = 'SELECT fol_uuid FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';

        return $this->getDatabase()->queryPrepared($sql, [$folderId])->fetch()['fol_uuid'];
    }

    /**
     * Build a documents administrator of the given organization.
     */
    private function makeDocumentsAdmin(AdmidioTestFixture $fixture, int $orgId, string $login): User
    {
        $role = $fixture->createAndSaveRoleWithRights('Doc Admins', $orgId, ['rol_documents_files' => 1]);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $this->loadUserInOrganization($user['usr_id'], $orgId);
    }

    /**
     * Test that a folder is stored under the organization it was created in
     *
     * @testdox A folder is stored under the organization it was created in
     */
    public function testFolderBelongsToTheCurrentOrganization(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'documents');

        $sql = 'SELECT fol_org_id, fol_type, fol_name, fol_fol_id_parent FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$folderId])->fetch();

        // Folder::save() takes the organization from the current context
        $this->assertEquals($org['org_id'], (int) $row['fol_org_id']);
        $this->assertEquals('DOCUMENTS', $row['fol_type']);
        $this->assertEquals('documents', $row['fol_name']);
        $this->assertNull($row['fol_fol_id_parent']);
    }

    /**
     * Test that folders form a hierarchy
     *
     * @testdox Folders form a hierarchy through their parent folder
     */
    public function testFoldersFormAHierarchy(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $childId = $this->createFolder($admin, $org['org_id'], 'minutes', false, $rootId);
        $grandChildId = $this->createFolder($admin, $org['org_id'], '2026', false, $childId);

        $parentOf = function (int $folderId): ?int {
            $sql = 'SELECT fol_fol_id_parent FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';
            $value = $this->getDatabase()->queryPrepared($sql, [$folderId])->fetch()['fol_fol_id_parent'];

            return $value === null ? null : (int) $value;
        };

        $this->assertNull($parentOf($rootId));
        $this->assertEquals($rootId, $parentOf($childId));
        $this->assertEquals($childId, $parentOf($grandChildId));

        // the root has exactly the one direct child
        $sql = 'SELECT fol_id FROM ' . TBL_FOLDERS . ' WHERE fol_fol_id_parent = ?';
        $children = array_map('intval', array_column(
            $this->getDatabase()->queryPrepared($sql, [$rootId])->fetchAll(),
            'fol_id'
        ));
        $this->assertEquals([$childId], $children);
    }

    /**
     * Test that a documents administrator may open any folder
     *
     * @testdox A documents administrator may open a folder without an explicit right
     */
    public function testDocumentsAdministratorMayOpenFolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'documents');
        $uuid = $this->folderUuid($folderId);

        $allowed = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($uuid) {
            $folder = new Folder($this->getDatabase());

            return $folder->getFolderForDownload($uuid);
        });

        $this->assertTrue($allowed);
    }

    /**
     * Test that a folder_view right opens the folder
     *
     * @testdox A role with folder_view may open the folder
     */
    public function testRoleWithViewRightMayOpenFolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'documents');
        $uuid = $this->folderUuid($folderId);

        $role = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);
        $reader = $fixture->createAndSaveUser('docreader', 'dr@example.local');
        $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);

        $rights = new RolesRights($this->getDatabase(), 'folder_view', $folderId);
        $rights->saveRoles([$role['rol_id']]);

        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $result = $this->withCurrentUser($readerUser, $org['org_id'], true, function () use ($uuid) {
            $folder = new Folder($this->getDatabase());
            $allowed = $folder->getFolderForDownload($uuid);

            return [$allowed, $folder->hasViewRight(), $folder->hasUploadRight(), $folder->getViewRolesIds()];
        });

        [$allowed, $mayView, $mayUpload, $viewRoles] = $result;

        $this->assertTrue($allowed);
        $this->assertTrue($mayView);

        // viewing is not uploading, that needs its own right
        $this->assertFalse($mayUpload);
        $this->assertEquals([$role['rol_id']], $viewRoles);
    }

    /**
     * Test that a user without any right is refused
     *
     * @testdox A user without a folder right may not open the folder
     */
    public function testUserWithoutRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'documents');
        $uuid = $this->folderUuid($folderId);

        // restrict the folder to a role the stranger is not in
        $insiders = $fixture->createAndSaveRoleWithRights('Insiders', $org['org_id']);
        $rights = new RolesRights($this->getDatabase(), 'folder_view', $folderId);
        $rights->saveRoles([$insiders['rol_id']]);

        $stranger = $fixture->createAndSaveUser('docstranger', 'ds@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $this->expectException(Exception::class);

        $this->withCurrentUser($strangerUser, $org['org_id'], true, function () use ($uuid) {
            $folder = new Folder($this->getDatabase());
            $folder->getFolderForDownload($uuid);
        });
    }

    /**
     * Test that a public folder is open to everybody
     *
     * @testdox A public folder may be opened without any role right
     */
    public function testPublicFolderIsOpenToEverybody(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'public-documents', true);
        $uuid = $this->folderUuid($folderId);

        $stranger = $fixture->createAndSaveUser('docstranger', 'ds@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $allowed = $this->withCurrentUser($strangerUser, $org['org_id'], true, function () use ($uuid) {
            $folder = new Folder($this->getDatabase());

            return $folder->getFolderForDownload($uuid);
        });

        $this->assertTrue($allowed);
    }

    /**
     * Test that a locked public folder is refused
     *
     * @testdox A locked folder is refused although it is public
     */
    public function testLockedFolderIsRefused(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'locked-documents', true);
        $uuid = $this->folderUuid($folderId);

        $db->queryPrepared('UPDATE ' . TBL_FOLDERS . ' SET fol_locked = ? WHERE fol_id = ?', [1, $folderId]);

        $stranger = $fixture->createAndSaveUser('docstranger', 'ds@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $this->expectException(Exception::class);

        $this->withCurrentUser($strangerUser, $org['org_id'], true, function () use ($uuid) {
            $folder = new Folder($this->getDatabase());
            $folder->getFolderForDownload($uuid);
        });
    }

    /**
     * Test that the upload right is reported separately
     *
     * @testdox A role with folder_upload is reported as an upload role
     */
    public function testUploadRightIsReportedSeparately(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id'], 'docadmin');

        $folderId = $this->createFolder($admin, $org['org_id'], 'documents');
        $uuid = $this->folderUuid($folderId);

        $role = $fixture->createAndSaveRoleWithRights('Uploaders', $org['org_id']);
        $uploader = $fixture->createAndSaveUser('docuploader', 'du@example.local');
        $fixture->assignUserToRole($uploader['usr_id'], $role['rol_id']);

        // the same role gets both rights on this folder
        (new RolesRights($this->getDatabase(), 'folder_view', $folderId))->saveRoles([$role['rol_id']]);
        (new RolesRights($this->getDatabase(), 'folder_upload', $folderId))->saveRoles([$role['rol_id']]);

        $uploaderUser = $this->loadUserInOrganization($uploader['usr_id'], $org['org_id']);

        [$mayView, $mayUpload, $uploadRoles] = $this->withCurrentUser(
            $uploaderUser,
            $org['org_id'],
            true,
            function () use ($uuid) {
                $folder = new Folder($this->getDatabase());
                $folder->getFolderForDownload($uuid);

                return [$folder->hasViewRight(), $folder->hasUploadRight(), $folder->getUploadRolesIds()];
            }
        );

        $this->assertTrue($mayView);
        $this->assertTrue($mayUpload);
        $this->assertEquals([$role['rol_id']], $uploadRoles);
    }

    /**
     * Test that folders of another organization are not reachable
     *
     * @testdox A folder of one organization is not listed for another
     */
    public function testFoldersAreScopedToTheirOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'doca');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'docb');

        $adminA = $this->makeDocumentsAdmin($fixture, $orgA['org_id'], 'docadmina');
        $adminB = $this->makeDocumentsAdmin($fixture, $orgB['org_id'], 'docadminb');

        $folderA = $this->createFolder($adminA, $orgA['org_id'], 'documents-a');
        $folderB = $this->createFolder($adminB, $orgB['org_id'], 'documents-b');

        $sql = 'SELECT fol_id FROM ' . TBL_FOLDERS . ' WHERE fol_org_id = ? AND fol_type = ?';
        $idsA = array_map('intval', array_column(
            $this->getDatabase()->queryPrepared($sql, [$orgA['org_id'], 'DOCUMENTS'])->fetchAll(),
            'fol_id'
        ));

        $this->assertContains($folderA, $idsA);
        $this->assertNotContains($folderB, $idsA);
    }
}
