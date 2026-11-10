<?php
/**
 * File Copy Tests
 *
 * Tests the rules for copying a file of the documents module: the name of the copy in the destination folder,
 * the destination must be allowed for the user and the file must exist. These checks only need the database,
 * the tests never write to the file system.
 */

namespace Admidio\Tests\Integration\Documents;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class FileCopyTest extends DatabaseTestCase
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

    private function createFolder(User $owner, int $orgId, string $name): int
    {
        return $this->withCurrentUser($owner, $orgId, true, function () use ($name) {
            $folder = new Folder($this->getDatabase());
            $folder->setValue('fol_type', 'DOCUMENTS');
            $folder->setValue('fol_name', $name);
            $folder->setValue('fol_path', '/adm_my_files/copy-test-not-existing');
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

    private function countFiles(): int
    {
        return (int) $this->getDatabase()->queryPrepared('SELECT COUNT(*) AS num FROM ' . TBL_FILES)->fetch()['num'];
    }

    /**
     * @testdox A copy keeps the name if the destination folder has no file with this name
     */
    public function testCopyKeepsTheNameInAFreeFolder(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $sourceId = $this->createFolder($admin, $org['org_id'], 'minutes');
        $targetId = $this->createFolder($admin, $org['org_id'], 'archive');
        $fileId = $this->createFile($admin, $org['org_id'], $sourceId, 'agenda.pdf');

        $name = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($fileId, $targetId) {
            $file = new File($this->getDatabase(), $fileId);

            return $file->getNameOfCopy(new Folder($this->getDatabase(), $targetId));
        });

        $this->assertEquals('agenda.pdf', $name);
    }

    /**
     * @testdox A copy gets the next free number if the name is already used
     */
    public function testCopyGetsTheNextFreeNumber(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $folderId = $this->createFolder($admin, $org['org_id'], 'minutes');
        $fileId = $this->createFile($admin, $org['org_id'], $folderId, 'agenda.pdf');

        $nameOfCopy = function () use ($admin, $org, $fileId, $folderId): string {
            return $this->withCurrentUser($admin, $org['org_id'], true, function () use ($fileId, $folderId) {
                $file = new File($this->getDatabase(), $fileId);

                return $file->getNameOfCopy(new Folder($this->getDatabase(), $folderId));
            });
        };

        // the name of the file itself is used in its own folder
        $this->assertEquals('agenda (2).pdf', $nameOfCopy());

        $this->createFile($admin, $org['org_id'], $folderId, 'agenda (2).pdf');
        $this->assertEquals('agenda (3).pdf', $nameOfCopy());

        // a name without extension gets the number at the end
        $plainId = $this->createFile($admin, $org['org_id'], $folderId, 'README');
        $plainName = $this->withCurrentUser($admin, $org['org_id'], true, function () use ($plainId, $folderId) {
            $file = new File($this->getDatabase(), $plainId);

            return $file->getNameOfCopy(new Folder($this->getDatabase(), $folderId));
        });
        $this->assertEquals('README (2)', $plainName);
    }

    /**
     * @testdox A copy into a folder without the right to upload is refused and nothing is created
     */
    public function testCopyToFolderWithoutUploadRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $sourceId = $this->createFolder($admin, $org['org_id'], 'minutes');
        $targetId = $this->createFolder($admin, $org['org_id'], 'archive');
        $fileId = $this->createFile($admin, $org['org_id'], $sourceId, 'agenda.pdf');
        $numberOfFiles = $this->countFiles();

        $stranger = $fixture->createAndSaveUser('docstranger', 'ds@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);
        $targetUuid = $this->folderUuid($targetId);

        $this->expectException(Exception::class);

        try {
            $this->withCurrentUser($strangerUser, $org['org_id'], true, function () use ($fileId, $targetUuid) {
                $file = new File($this->getDatabase(), $fileId);
                $file->copyToFolder($targetUuid, true);
            });
        } finally {
            $this->assertEquals($numberOfFiles, $this->countFiles());
        }
    }

    /**
     * @testdox A file that does not exist on the server can't be copied
     */
    public function testCopyOfAMissingFileIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Doc Org', 'docorg');
        $admin = $this->makeDocumentsAdmin($fixture, $org['org_id']);
        $sourceId = $this->createFolder($admin, $org['org_id'], 'minutes');
        $targetId = $this->createFolder($admin, $org['org_id'], 'archive');
        $fileId = $this->createFile($admin, $org['org_id'], $sourceId, 'agenda.pdf');
        $numberOfFiles = $this->countFiles();
        $targetUuid = $this->folderUuid($targetId);

        try {
            $this->withCurrentUser($admin, $org['org_id'], true, function () use ($fileId, $targetUuid) {
                $file = new File($this->getDatabase(), $fileId);
                $file->copyToFolder($targetUuid, true);
            });
            $this->fail('The copy of a file that does not exist was not refused.');
        } catch (Exception $exception) {
            // the database entry exists, the file on the server does not
            $this->assertEquals($numberOfFiles, $this->countFiles());
        }
    }
}
