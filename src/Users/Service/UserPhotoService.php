<?php

namespace Admidio\Users\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Users\Entity\User;
use RuntimeException;

/**
 * Service for storing and deleting user profile photos without depending on an HTTP upload/session.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class UserPhotoService
{
    /**
     * Validate, scale and store a profile photo from an existing local file.
     *
     * @throws Exception
     */
    public function saveFromFile(User $user, string $sourcePath): void
    {
        global $gSettingsManager, $gLogger;

        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $imageProperties = getimagesize($sourcePath);
        if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
            throw new Exception('SYS_PHOTO_FORMAT_INVALID');
        }

        $imageDimensions = $imageProperties[0] * $imageProperties[1];
        if ($imageDimensions > SystemInfoUtils::getProcessableImageSize()) {
            throw new Exception(
                'SYS_PHOTO_RESOLUTION_TO_LARGE',
                array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2))
            );
        }

        $userImage = new Image($sourcePath);
        $userImage->setImageType('jpeg');
        $userImage->scale(130, 170);

        $temporaryFile = '';

        try {
            if ($gSettingsManager->getInt('profile_photo_storage') === 1) {
                $directory = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos';

                try {
                    FileSystemUtils::createDirectoryIfNotExists($directory);
                } catch (RuntimeException $exception) {
                    throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/user_profile_photos'));
                }

                $targetPath = $directory . '/' . $user->getValue('usr_id') . '.jpg';
                if (!$userImage->copyToFile(null, $targetPath)) {
                    throw new Exception('SYS_PHOTO_PROCESSING_ERROR');
                }
            } else {
                $temporaryFile = tempnam(ADMIDIO_PATH . FOLDER_TEMP_DATA, 'profile-photo-');
                if ($temporaryFile === false || !$userImage->copyToFile(null, $temporaryFile)) {
                    throw new Exception('SYS_PHOTO_PROCESSING_ERROR');
                }

                $imageData = file_get_contents($temporaryFile);
                if ($imageData === false) {
                    throw new Exception('SYS_PHOTO_PROCESSING_ERROR');
                }

                $user->setValue('usr_photo', $imageData);
                $user->save();
            }
        } finally {
            $userImage->delete();

            if ($temporaryFile !== '' && is_file($temporaryFile)) {
                try {
                    FileSystemUtils::deleteFileIfExists($temporaryFile);
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $temporaryFile));
                }
            }
        }
    }

    /**
     * Delete the currently stored profile photo.
     *
     * @throws Exception
     */
    public function delete(User $user): void
    {
        global $gSettingsManager, $gLogger;

        if ($gSettingsManager->getInt('profile_photo_storage') === 1) {
            $filePath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';

            try {
                FileSystemUtils::deleteFileIfExists($filePath);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $filePath));
                throw new Exception('SYS_FOLDER_NOT_WRITABLE', array(dirname($filePath)));
            }
        } else {
            $user->setValue('usr_photo', '');
            $user->save();
        }
    }
}
