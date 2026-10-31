<?php
/**
 * Folder Move Tests
 *
 * Tests the checks that run before a file or folder of the documents module is moved: the destination must be
 * allowed for the user, a folder can't be moved into itself or one of its subfolders and nothing is moved to the
 * folder where it already is. These checks only need the database, the tests never touch the file system.
 * They also cover the nodes that the folder tree of the move dialog is built from.
 */

namespace Admidio\Tests\Integration\Documents;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class FolderMoveTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function makeDocumentsAdmin(AdmidioTestFixture $fixture, int $orgId): User
    {
        $role = $fixture->createAndSaveRoleWithRights('Doc Admins', $orgId, ['rol_documents_files' => 1]);
        $user = $fixture->createAndSaveUser('docadmin', 'docadmin@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $this->loadUserInOrganization($user['usr_id'], $orgId);
    }

    private function createFolder(User $owner, int $orgId, string $name, ?int $parentId = null): int
    {
        return $this->withCurrentUser($owner, $orgId, true, function () use ($name, $parentId) {
            $folder = new Folder($this->getDatabase());
            $folder->setValue('fol_type', 'DOCUMENTS');
            $folder->setValue('fol_name', $name);
            $folder->setValue('fol_path', '/adm_my_files');
            if ($parentId !== null) {
                $folder->setValue('fol_fol_id_parent', $parentId);
            }
            $folder->save();

            return (int) $folder->getValue('fol_id');
        });
    }

    private function createFile(User $owner, int $orgId, int $folderId, string $name): int
    {
        return $this->withCurrentUser($owner, $orgId, true, function () use ($folderId, $name) {
            $file = new File($this->getDatabase());
            $file->setValue('fil_fol_id', $folderId);
            $file->setValue('fil_name', $name);
            $file->setValue('fil_locked', 0);
            $file->setValue('fil_counter', 0);
            $file->save();

            return (int) $file->getValue('fil_id');
        });
    }

    private function folderUuid(int $folderId): string
    {
        $sql = 'SELECT fol_uuid FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';

        return $this->getDatabase()->queryPrepared($sql, [$folderId])->fetch()['fol_uuid'];
    }

    private function parentOfFolder(int $folderId): ?int
    {
        $sql = 'SELECT fol_fol_id_parent FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';
        $value = $this->getDatabase()->queryPrepared($sql, [$folderId])->fetch()['fol_fol_id_parent'];

        return $value === null ? null : (int) $value;
    }

    private function folderOfFile(int $fileId): int
    {
        $sql = 'SELECT fil_fol_id FROM ' . TBL_FILES . ' WHERE fil_id = ?';

        return (int) $this->getDatabase()->queryPrepared($sql, [$fileId])->fetch()['fil_fol_id'];
    }

    /**
     * Try to move the folder as the given user.
     *
     * @throws Exception
     */
    private function moveFolder(User $user, int $orgId, int $folderId, int $destinationId): void
    {
        $destinationUuid = $this->folderUuid($destinationId);

        $this->withCurrentUser($user, $orgId, true, function () use ($folderId, $destinationUuid) {
            $folder = new Folder($this->getDatabase(), $folderId);
            $folder->moveToFolder($destinationUuid);
        });
    }

    /**
     * Try to move the file as the given user.
     *
     * @throws Exception
     */
    private function moveFile(User $user, int $orgId, int $fileId, int $destinationId): void
    {
        $destinationUuid = $this->folderUuid($destinationId);

        $this->withCurrentUser($user, $orgId, true, function () use ($fileId, $destinationUuid) {
            $file = new File($this->getDatabase(), $fileId);
            $file->moveToFolder($destinationUuid);
        });
    }

    /**
     * @testdox A folder can't be moved into itself
     */
    public function testFolderCannotBeMovedIntoItself(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $folderId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);

        try {
            $this->moveFolder($admin, $org['org_id'], $folderId, $folderId);
            $this->fail('The move into the folder itself was not refused.');
        } catch (Exception $exception) {
            $this->assertEquals($rootId, $this->parentOfFolder($folderId));
        }
    }

    /**
     * @testdox A folder can't be moved into one of its subfolders
     */
    public function testFolderCannotBeMovedIntoItsSubfolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $folderId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);
        $childId = $this->createFolder($admin, $org['org_id'], '2026', $folderId);
        $grandChildId = $this->createFolder($admin, $org['org_id'], 'march', $childId);

        foreach (array($childId, $grandChildId) as $destinationId) {
            try {
                $this->moveFolder($admin, $org['org_id'], $folderId, $destinationId);
                $this->fail('The move into a subfolder was not refused.');
            } catch (Exception $exception) {
                $this->assertEquals($rootId, $this->parentOfFolder($folderId));
            }
        }
    }

    /**
     * @testdox A folder can't be moved into the folder where it already is
     */
    public function testFolderCannotBeMovedToItsParent(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $folderId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);

        $this->expectException(Exception::class);

        $this->moveFolder($admin, $org['org_id'], $folderId, $rootId);
    }

    /**
     * @testdox A file can't be moved into the folder where it already is
     */
    public function testFileCannotBeMovedToItsFolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $fileId = $this->createFile($admin, $org['org_id'], $rootId, 'agenda.pdf');

        try {
            $this->moveFile($admin, $org['org_id'], $fileId, $rootId);
            $this->fail('The move into the same folder was not refused.');
        } catch (Exception $exception) {
            $this->assertEquals($rootId, $this->folderOfFile($fileId));
        }
    }

    /**
     * @testdox Moving to a folder without the right to upload is refused and not ignored
     */
    public function testMoveToFolderWithoutUploadRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $sourceId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);
        $targetId = $this->createFolder($admin, $org['org_id'], 'archive', $rootId);
        $fileId = $this->createFile($admin, $org['org_id'], $sourceId, 'agenda.pdf');

        $stranger = $fixture->createAndSaveUser('docstranger', 'ds@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        try {
            $this->moveFile($strangerUser, $org['org_id'], $fileId, $targetId);
            $this->fail('The move of the file to a folder without upload right was not refused.');
        } catch (Exception $exception) {
            $this->assertEquals($sourceId, $this->folderOfFile($fileId));
        }

        try {
            $this->moveFolder($strangerUser, $org['org_id'], $sourceId, $targetId);
            $this->fail('The move of the folder to a folder without upload right was not refused.');
        } catch (Exception $exception) {
            $this->assertEquals($rootId, $this->parentOfFolder($sourceId));
        }
    }

    /**
     * @testdox The path of a folder lists the folders from the root to the folder
     */
    public function testFolderPathLeadsFromTheRootToTheFolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $childId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);
        $grandChildId = $this->createFolder($admin, $org['org_id'], '2026', $childId);

        $pathOf = function (int $folderId) use ($admin, $org): array {
            $uuid = $this->folderUuid($folderId);

            return $this->withCurrentUser($admin, $org['org_id'], true, function () use ($uuid) {
                $service = new DocumentsService($this->getDatabase(), $uuid);

                return $service->findFolderPath();
            });
        };

        $this->assertEquals(array($this->folderUuid($rootId)), $pathOf($rootId));
        $this->assertEquals(
            array($this->folderUuid($rootId), $this->folderUuid($childId), $this->folderUuid($grandChildId)),
            $pathOf($grandChildId)
        );
    }

    /**
     * @testdox The nodes of the folder tree know their subfolders and the upload right
     */
    public function testFolderTreeNodes(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $rootId = $this->createFolder($admin, $org['org_id'], 'documents');
        $withChildId = $this->createFolder($admin, $org['org_id'], 'minutes', $rootId);
        $this->createFolder($admin, $org['org_id'], '2026', $withChildId);
        $leafId = $this->createFolder($admin, $org['org_id'], 'archive', $rootId);

        $rootUuid = $this->folderUuid($rootId);
        $nodes = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($rootUuid) {
            $service = new DocumentsService($this->getDatabase(), $rootUuid);

            return $service->findFolderTreeNodes();
        });

        $byName = array();
        foreach ($nodes as $node) {
            $byName[$node['name']] = $node;
        }

        $this->assertEquals(array('archive', 'minutes'), array_keys($byName));
        $this->assertEquals($this->folderUuid($leafId), $byName['archive']['id']);
        $this->assertFalse($byName['archive']['hasChildren']);
        $this->assertTrue($byName['minutes']['hasChildren']);
        $this->assertTrue($byName['minutes']['selectable']);
        $this->assertStringContainsString('folder_uuid=' . $this->folderUuid($withChildId), $byName['minutes']['url']);
    }
}
