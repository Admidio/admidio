<?php

namespace Admidio\Photos\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Photos\Entity\Album;
use RuntimeException;
use ZipArchive;

/**
 * Service for photo filesystem operations independent of HTTP upload/download handling.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PhotoService
{
    public function __construct(
        private readonly Database $db,
        private readonly Album $album
    ) {
    }

    /**
     * Validate and add a local image file to the album.
     *
     * @return int Number assigned to the newly added photo.
     * @throws Exception
     */
    public function uploadFromFile(string $sourcePath): int
    {
        global $gSettingsManager;

        $this->assertEditable();

        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $imageProperties = getimagesize($sourcePath);
        if ($imageProperties === false) {
            throw new Exception('SYS_PHOTO_FORMAT_INVALID');
        }

        $extension = match ($imageProperties['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new Exception('SYS_PHOTO_FORMAT_INVALID')
        };

        $imageDimensions = $imageProperties[0] * $imageProperties[1];
        $processableImageSize = SystemInfoUtils::getProcessableImageSize();
        if ($imageDimensions > $processableImageSize) {
            throw new Exception(
                'SYS_RESOLUTION_TOO_LARGE',
                array(round($processableImageSize / 1000000, 2))
            );
        }

        $albumFolder = $this->getAlbumFolder();
        if (!is_dir($albumFolder)) {
            $error = $this->album->createFolder();
            if (is_array($error)) {
                throw new Exception($error['text'], array($error['path']));
            }
        }

        $this->db->startTransaction();
        try {
            $oldQuantity = $this->lockAndReadQuantity();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
        $this->album->setValue('pho_quantity', $oldQuantity);
        $photoNumber = $oldQuantity + 1;
        $displayFile = $albumFolder . '/' . $photoNumber . '.jpg';
        $thumbnailFile = $albumFolder . '/thumbnails/' . $photoNumber . '.jpg';
        $originalFile = '';

        try {
            $image = new Image($sourcePath);
            $image->setImageType('jpeg');
            $image->scale(
                $gSettingsManager->getInt('photo_show_width'),
                $gSettingsManager->getInt('photo_show_height')
            );
            $image->copyToFile(null, $displayFile);
            $image->delete();

            if ($gSettingsManager->getBool('photo_keep_original')) {
                FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/originals');
                $originalFile = $albumFolder . '/originals/' . $photoNumber . '.' . $extension;
                FileSystemUtils::copyFile($sourcePath, $originalFile);
            }

            FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/thumbnails');
            $image = new Image($sourcePath);
            $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
            $image->copyToFile(null, $thumbnailFile);
            $image->delete();

            if (!is_file($displayFile)) {
                throw new Exception('SYS_PHOTO_PROCESSING_ERROR');
            }

            $this->album->setValue('pho_quantity', $photoNumber);
            $this->album->save();
            $this->db->endTransaction();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            $this->album->setValue('pho_quantity', $oldQuantity);

            foreach (array($displayFile, $thumbnailFile, $originalFile) as $file) {
                if ($file !== '') {
                    try {
                        FileSystemUtils::deleteFileIfExists($file);
                    } catch (RuntimeException) {
                    }
                }
            }

            throw $exception;
        }

        return $photoNumber;
    }

    /**
     * Delete a photo and compact the numeric filenames of all following photos.
     *
     * Files that may change are first moved to a private staging directory. The database row is
     * locked for the whole operation. If saving/committing fails, every staged file is restored and
     * the Album object is put back to the locked quantity as well.
     *
     * @throws Exception
     */
    public function deletePhoto(int $photoNumber): void
    {
        $this->assertEditable();

        $albumFolder = $this->getAlbumFolder();
        $this->db->startTransaction();
        try {
            $oldQuantity = $this->lockAndReadQuantity();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
        $this->album->setValue('pho_quantity', $oldQuantity);

        if ($photoNumber < 1 || $photoNumber > $oldQuantity) {
            $this->db->rollback();
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $stagingDirectory = $albumFolder . '/.delete-' . bin2hex(random_bytes(8));
        if (!mkdir($stagingDirectory, 0700, true) && !is_dir($stagingDirectory)) {
            $this->db->rollback();
            throw new RuntimeException('Could not create temporary photo staging directory.');
        }

        /** @var array<string,string> $staged original path => backup path */
        $staged = array();
        /** @var array<int,string> $created */
        $created = array();

        try {
            for ($number = $photoNumber; $number <= $oldQuantity; ++$number) {
                foreach ($this->photoPaths($albumFolder, $number) as $path) {
                    if (!is_file($path)) {
                        continue;
                    }
                    $backup = $stagingDirectory . '/' . count($staged);
                    if (!rename($path, $backup)) {
                        throw new RuntimeException('Could not stage photo file "' . $path . '".');
                    }
                    $staged[$path] = $backup;
                }
            }

            // Compact display and original files. Thumbnails from the deleted point onward are
            // deliberately invalidated and will be generated again by the normal thumbnail path.
            for ($number = $photoNumber + 1; $number <= $oldQuantity; ++$number) {
                foreach (array(
                    $albumFolder . '/' . $number . '.jpg' => $albumFolder . '/' . ($number - 1) . '.jpg',
                    $albumFolder . '/originals/' . $number . '.jpg' => $albumFolder . '/originals/' . ($number - 1) . '.jpg',
                    $albumFolder . '/originals/' . $number . '.png' => $albumFolder . '/originals/' . ($number - 1) . '.png'
                ) as $source => $target) {
                    if (!isset($staged[$source])) {
                        continue;
                    }
                    FileSystemUtils::createDirectoryIfNotExists(dirname($target));
                    if (!copy($staged[$source], $target)) {
                        throw new RuntimeException('Could not compact photo file "' . $source . '".');
                    }
                    $created[] = $target;
                }
            }

            $this->album->setValue('pho_quantity', $oldQuantity - 1);
            $this->album->save();
            $this->db->endTransaction();

            foreach ($staged as $backup) {
                @unlink($backup);
            }
            @rmdir($stagingDirectory);
        } catch (\Throwable $exception) {
            $this->db->rollback();
            $this->album->setValue('pho_quantity', $oldQuantity);

            foreach (array_reverse($created) as $path) {
                @unlink($path);
            }
            foreach ($staged as $original => $backup) {
                if (is_file($backup)) {
                    FileSystemUtils::createDirectoryIfNotExists(dirname($original));
                    @rename($backup, $original);
                }
            }
            @rmdir($stagingDirectory);

            throw $exception;
        }
    }

    /**
     * Rotate the scaled photo by 90 degrees and invalidate its thumbnail.
     *
     * @throws Exception
     */
    public function rotatePhoto(int $photoNumber, string $direction): void
    {
        $this->assertEditable();
        $this->assertPhotoNumber($photoNumber);

        if (!in_array($direction, array('left', 'right'), true)) {
            throw new \InvalidArgumentException('Direction must be left or right.');
        }

        $this->deleteThumbnail($photoNumber);

        $photoPath = $this->getAlbumFolder() . '/' . $photoNumber . '.jpg';
        if (!is_file($photoPath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $image = new Image($photoPath);
        $image->rotate($direction);
        $image->delete();
    }

    /**
     * Resolve a photo to the original file, if configured and present, or the scaled JPEG.
     *
     * @return array{path:string,filename:string,contentType:string}
     * @throws Exception
     */
    public function getDownloadFile(int $photoNumber): array
    {
        global $gSettingsManager;

        $this->assertDownloadAllowed();
        $this->assertPhotoNumber($photoNumber);

        $albumFolder = $this->getAlbumFolder();

        if ($gSettingsManager->getBool('photo_keep_original')) {
            if (is_file($albumFolder . '/originals/' . $photoNumber . '.jpg')) {
                return array(
                    'path' => $albumFolder . '/originals/' . $photoNumber . '.jpg',
                    'filename' => $photoNumber . '.jpg',
                    'contentType' => 'image/jpeg'
                );
            }

            if (is_file($albumFolder . '/originals/' . $photoNumber . '.png')) {
                return array(
                    'path' => $albumFolder . '/originals/' . $photoNumber . '.png',
                    'filename' => $photoNumber . '.png',
                    'contentType' => 'image/png'
                );
            }
        }

        $path = $albumFolder . '/' . $photoNumber . '.jpg';
        if (!is_file($path)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        return array(
            'path' => $path,
            'filename' => $photoNumber . '.jpg',
            'contentType' => 'image/jpeg'
        );
    }

    /**
     * Build the same album ZIP download as the web module without sending HTTP output.
     *
     * @return array{path:string,filename:string,contentType:string}
     * @throws Exception
     */
    public function createAlbumArchive(): array
    {
        global $gCurrentOrgId, $gCurrentUser, $gSettingsManager;

        $this->assertDownloadAllowed();

        $sqlConditions = '';
        if (!$gCurrentUser->isAdministratorPhotos()) {
            $sqlConditions = ' AND pho_locked = false ';
        }

        $sql = 'SELECT pho_id
                  FROM ' . TBL_PHOTOS . '
                 WHERE pho_org_id = ?
                   AND pho_pho_id_parent = ?
                       ' . $sqlConditions . '
              ORDER BY pho_begin DESC';
        $statement = $this->db->queryPrepared(
            $sql,
            array($gCurrentOrgId, (int)$this->album->getValue('pho_id'))
        );
        $childIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if ((int)$this->album->getValue('pho_quantity') === 0 && count($childIds) === 0) {
            throw new Exception('SYS_ALBUM_CONTAINS_NO_PHOTOS');
        }

        $tempFile = tempnam(ADMIDIO_PATH . FOLDER_TEMP_DATA, 'photo-album-');
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temporary photo archive.');
        }
        @unlink($tempFile);

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE) !== true) {
            throw new Exception('SYS_DOWNLOAD_ZIP_ERROR');
        }

        $takeOriginals = $gSettingsManager->getBool('photo_keep_original');
        $this->addAlbumToArchive($zip, $this->album, '', $takeOriginals);

        foreach ($childIds as $childId) {
            $childAlbum = new Album($this->db, (int)$childId);
            if (!$childAlbum->isVisible()) {
                continue;
            }

            $folderName = (string)preg_replace(
                '/[^\p{L}\p{N} _.-]/u',
                '',
                (string)$childAlbum->getValue('pho_name', 'database')
            );
            $this->addAlbumToArchive($zip, $childAlbum, $folderName . '/', $takeOriginals);
        }

        if (!$zip->close()) {
            try {
                FileSystemUtils::deleteFileIfExists($tempFile);
            } catch (RuntimeException) {
            }
            throw new Exception('SYS_DOWNLOAD_ZIP_ERROR');
        }

        $filename = (string)preg_replace(
            '/[^\p{L}\p{N} _.-]/u',
            '',
            (string)$this->album->getValue('pho_name', 'database')
        ) . '.zip';
        $filename = FileSystemUtils::getSanitizedPathEntry($filename);

        return array(
            'path' => $tempFile,
            'filename' => $filename,
            'contentType' => 'application/zip'
        );
    }

    /**
     * Lock the album record to serialize quantity-based photo numbering.
     */
    private function lockAndReadQuantity(): int
    {
        $quantity = $this->db->queryPrepared(
            'SELECT pho_quantity FROM ' . TBL_PHOTOS . ' WHERE pho_id = ? FOR UPDATE',
            array((int)$this->album->getValue('pho_id'))
        )->fetchColumn();

        if ($quantity === false) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return (int)$quantity;
    }

    /** @return array<int,string> */
    private function photoPaths(string $albumFolder, int $photoNumber): array
    {
        return array(
            $albumFolder . '/' . $photoNumber . '.jpg',
            $albumFolder . '/originals/' . $photoNumber . '.jpg',
            $albumFolder . '/originals/' . $photoNumber . '.png',
            $albumFolder . '/thumbnails/' . $photoNumber . '.jpg'
        );
    }

    private function getAlbumFolder(): string
    {
        return ADMIDIO_PATH . FOLDER_DATA . '/photos/'
            . $this->album->getValue('pho_begin', 'Y-m-d')
            . '_' . (int)$this->album->getValue('pho_id');
    }

    /**
     * @throws Exception
     */
    private function assertEditable(): void
    {
        if (!$this->album->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * @throws Exception
     */
    private function assertDownloadAllowed(): void
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('photo_download_enabled') || !$this->album->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * @throws Exception
     */
    private function assertPhotoNumber(int $photoNumber): void
    {
        if ($photoNumber < 1 || $photoNumber > (int)$this->album->getValue('pho_quantity')) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }
    }

    private function deleteThumbnail(int $photoNumber): void
    {
        try {
            FileSystemUtils::deleteFileIfExists(
                $this->getAlbumFolder() . '/thumbnails/' . $photoNumber . '.jpg'
            );
        } catch (RuntimeException) {
        }
    }

    private function addAlbumToArchive(
        ZipArchive $zip,
        Album $album,
        string $prefix,
        bool $takeOriginals
    ): void {
        $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/'
            . $album->getValue('pho_begin', 'Y-m-d')
            . '_' . (int)$album->getValue('pho_id');

        for ($number = 1; $number <= (int)$album->getValue('pho_quantity'); ++$number) {
            if ($takeOriginals) {
                if (is_file($albumFolder . '/originals/' . $number . '.jpg')) {
                    $zip->addFile(
                        $albumFolder . '/originals/' . $number . '.jpg',
                        $prefix . $number . '.jpg'
                    );
                    continue;
                }

                if (is_file($albumFolder . '/originals/' . $number . '.png')) {
                    $zip->addFile(
                        $albumFolder . '/originals/' . $number . '.png',
                        $prefix . $number . '.png'
                    );
                    continue;
                }
            }

            if (is_file($albumFolder . '/' . $number . '.jpg')) {
                $zip->addFile(
                    $albumFolder . '/' . $number . '.jpg',
                    $prefix . $number . '.jpg'
                );
            }
        }
    }
}
