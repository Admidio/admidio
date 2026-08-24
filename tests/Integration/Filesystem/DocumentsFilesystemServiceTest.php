<?php

namespace Admidio\Tests\Integration\Filesystem;

use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Tests\Support\FilesystemTestCase;

/**
 * Production-path filesystem regression coverage for Documents & Files.
 */
class DocumentsFilesystemServiceTest extends FilesystemTestCase
{
    /**
     * @testdox DocumentsService copies a real file, registers it, increments downloads and deletes it
     */
    public function testUploadDownloadAndDeleteUseManagedFilesystemAndDatabase(): void
    {
        global $gSettingsManager;

        $db = $this->getDatabase();
        $oldMaxUploadSize = $gSettingsManager->getInt('documents_files_max_upload_size');
        $gSettingsManager->set('documents_files_max_upload_size', 5);

        $rootFolder = new Folder($db);
        $childFolder = null;

        try {
            $rootFolder->getFolderForDownload('');
            $rootPath = $rootFolder->getFullFolderPath();
            $this->assertPathInsideTestDataRoot($rootPath);

            if (!is_dir($rootPath) && !mkdir($rootPath, 0775, true) && !is_dir($rootPath)) {
                $this->fail('Could not create the production documents root inside the test data directory.');
            }

            $suffix = bin2hex(random_bytes(5));
            $folderName = 'regression-documents-' . $suffix;
            $this->assertNull($rootFolder->createFolder($folderName));
            $rootFolder->addFolderOrFileToDatabase($folderName);

            $row = $db->queryPrepared(
                'SELECT fol_id, fol_uuid
                   FROM ' . TBL_FOLDERS . '
                  WHERE fol_fol_id_parent = ?
                    AND fol_name = ?',
                array((int)$rootFolder->getValue('fol_id'), $folderName)
            )->fetch();

            $this->assertIsArray($row);
            $childFolder = new Folder($db, (int)$row['fol_id']);
            $childPath = $childFolder->getFullFolderPath();
            $this->registerCleanupPath($childPath);

            $fixtureDirectory = $this->createIsolatedDirectory('document-source');
            $fixtureContents = "Admidio regression document\n" . $suffix . "\n";
            $sourcePath = $this->createFixtureFile(
                $fixtureDirectory,
                'source-' . $suffix . '.txt',
                $fixtureContents
            );

            $targetName = 'uploaded-' . $suffix . '.txt';
            $service = new DocumentsService($db, (string)$row['fol_uuid']);
            $file = $service->uploadFile($sourcePath, $targetName);

            $fileUuid = (string)$file->getValue('fil_uuid');
            $targetPath = $file->getFullFilePath();
            $this->registerCleanupPath($targetPath);

            $this->assertFileExists($targetPath);
            $this->assertSame($fixtureContents, file_get_contents($targetPath));

            $databaseRow = $db->queryPrepared(
                'SELECT fil_name, fil_counter, fil_fol_id
                   FROM ' . TBL_FILES . '
                  WHERE fil_uuid = ?',
                array($fileUuid)
            )->fetch();

            $this->assertIsArray($databaseRow);
            $this->assertSame($targetName, $databaseRow['fil_name']);
            $this->assertSame(0, (int)$databaseRow['fil_counter']);
            $this->assertSame((int)$row['fol_id'], (int)$databaseRow['fil_fol_id']);

            $downloadFile = $service->prepareFileDownload($fileUuid);
            $this->assertSame($targetPath, $downloadFile->getFullFilePath());
            $this->assertSame($fixtureContents, file_get_contents($downloadFile->getFullFilePath()));

            $counter = (int)$db->queryPrepared(
                'SELECT fil_counter FROM ' . TBL_FILES . ' WHERE fil_uuid = ?',
                array($fileUuid)
            )->fetchColumn();
            $this->assertSame(1, $counter);

            $this->assertTrue($downloadFile->delete());
            $this->assertFileDoesNotExist($targetPath);
            $this->assertSame(
                0,
                (int)$db->queryPrepared(
                    'SELECT COUNT(*) FROM ' . TBL_FILES . ' WHERE fil_uuid = ?',
                    array($fileUuid)
                )->fetchColumn()
            );

            $this->assertTrue($childFolder->delete());
            $childFolder = null;
            $this->assertDirectoryDoesNotExist($childPath);
        } finally {
            if ($childFolder instanceof Folder && !$childFolder->isNewRecord()) {
                $childFolder->delete();
            }
            $gSettingsManager->set('documents_files_max_upload_size', $oldMaxUploadSize);
        }
    }
}
