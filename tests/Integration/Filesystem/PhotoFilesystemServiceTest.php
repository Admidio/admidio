<?php

namespace Admidio\Tests\Integration\Filesystem;

use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\AlbumService;
use Admidio\Photos\Service\PhotoService;
use Admidio\Tests\Support\FilesystemTestCase;
use ZipArchive;

/**
 * Production-path filesystem regression coverage for photo albums.
 */
class PhotoFilesystemServiceTest extends FilesystemTestCase
{
    /**
     * @testdox AlbumService and PhotoService create display thumbnail original download and ZIP files
     */
    public function testPhotoLifecycleUsesProductionImageAndArchivePaths(): void
    {
        global $gCurrentOrgId, $gSettingsManager;

        $this->assertTrue(
            function_exists('imagecreatetruecolor'),
            'The Admidio regression environment requires the GD extension.'
        );

        $settings = array(
            'photo_keep_original' => $gSettingsManager->getBool('photo_keep_original'),
            'photo_download_enabled' => $gSettingsManager->getBool('photo_download_enabled'),
            'photo_show_width' => $gSettingsManager->getInt('photo_show_width'),
            'photo_show_height' => $gSettingsManager->getInt('photo_show_height'),
            'photo_thumbs_scale' => $gSettingsManager->getInt('photo_thumbs_scale')
        );

        $gSettingsManager->set('photo_keep_original', true);
        $gSettingsManager->set('photo_download_enabled', true);
        $gSettingsManager->set('photo_show_width', 320);
        $gSettingsManager->set('photo_show_height', 240);
        $gSettingsManager->set('photo_thumbs_scale', 80);

        $db = $this->getDatabase();
        $album = new Album($db);

        try {
            $suffix = bin2hex(random_bytes(5));
            (new AlbumService($db))->saveData($album, array(
                'pho_name' => 'Regression album ' . $suffix,
                'pho_begin' => '2026-08-24',
                'pho_end' => '2026-08-24',
                'pho_org_id' => $gCurrentOrgId,
                'pho_locked' => 0,
                'pho_quantity' => 0
            ));

            $albumId = (int)$album->getValue('pho_id');
            $albumUuid = (string)$album->getValue('pho_uuid');
            $albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/2026-08-24_' . $albumId;
            $this->registerCleanupPath($albumPath);
            $this->assertDirectoryExists($albumPath);

            $fixtureDirectory = $this->createIsolatedDirectory('photo-source');
            $sourcePath = $fixtureDirectory . '/source-' . $suffix . '.png';

            $image = imagecreatetruecolor(96, 64);
            $this->assertNotFalse($image);
            $this->assertTrue(imagepng($image, $sourcePath));
            imagedestroy($image);

            $service = new PhotoService($db, $album);
            $photoNumber = $service->uploadFromFile($sourcePath);
            $this->assertSame(1, $photoNumber);

            $displayPath = $albumPath . '/1.jpg';
            $thumbnailPath = $albumPath . '/thumbnails/1.jpg';
            $originalPath = $albumPath . '/originals/1.png';

            $this->assertFileExists($displayPath);
            $this->assertFileExists($thumbnailPath);
            $this->assertFileExists($originalPath);
            $this->assertSame(hash_file('sha256', $sourcePath), hash_file('sha256', $originalPath));

            $quantity = (int)$db->queryPrepared(
                'SELECT pho_quantity FROM ' . TBL_PHOTOS . ' WHERE pho_uuid = ?',
                array($albumUuid)
            )->fetchColumn();
            $this->assertSame(1, $quantity);

            $download = $service->getDownloadFile(1);
            $this->assertSame($originalPath, $download['path']);
            $this->assertSame('1.png', $download['filename']);
            $this->assertSame('image/png', $download['contentType']);

            $archive = $service->createAlbumArchive();
            $this->registerCleanupPath($archive['path']);
            $this->assertFileExists($archive['path']);
            $this->assertSame('application/zip', $archive['contentType']);

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($archive['path']) === true);
            $this->assertNotFalse($zip->locateName('1.png'));
            $zip->close();

            $service->deletePhoto(1);
            $this->assertFileDoesNotExist($displayPath);
            $this->assertFileDoesNotExist($thumbnailPath);
            $this->assertFileDoesNotExist($originalPath);

            $quantity = (int)$db->queryPrepared(
                'SELECT pho_quantity FROM ' . TBL_PHOTOS . ' WHERE pho_uuid = ?',
                array($albumUuid)
            )->fetchColumn();
            $this->assertSame(0, $quantity);

            $this->assertTrue($album->delete());
            $this->assertDirectoryDoesNotExist($albumPath);
            $this->assertSame(
                0,
                (int)$db->queryPrepared(
                    'SELECT COUNT(*) FROM ' . TBL_PHOTOS . ' WHERE pho_uuid = ?',
                    array($albumUuid)
                )->fetchColumn()
            );
        } finally {
            if (!$album->isNewRecord()) {
                $album->delete();
            }

            foreach ($settings as $name => $value) {
                $gSettingsManager->set($name, $value);
            }
        }
    }
}
