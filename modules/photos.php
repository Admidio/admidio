<?php
/**
 * Display and manage photos and photo albums.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\AlbumService;
use Admidio\Photos\Service\ECardService;
use Admidio\Photos\Service\PhotoService;
use Admidio\Photos\ValueObject\ECard;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\UI\Presenter\PhotosPresenter;
use Ramsey\Uuid\Uuid;

$getMode = 'albums';

try {
    require_once(__DIR__ . '/../system/common.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'albums',
        'validValues' => array(
            'albums', 'album_edit', 'album_save', 'album_delete', 'album_lock', 'album_unlock',
            'photo_present', 'photo_show', 'photo_download', 'photo_delete', 'photo_rotate',
            'ecard', 'ecard_preview', 'ecard_send'
        )
    ));
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid');
    $getParentPhotoUuid = admFuncVariableIsValid($_GET, 'parent_photo_uuid', 'uuid');
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int');
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', array('validValues' => array('left', 'right')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getMaxWidth = admFuncVariableIsValid($_GET, 'max_width', 'int', array('defaultValue' => 0));
    $getMaxHeight = admFuncVariableIsValid($_GET, 'max_height', 'int', array('defaultValue' => 0));
    $getThumbnail = admFuncVariableIsValid($_GET, 'thumb', 'bool');

    $moduleEnabled = $gSettingsManager->getInt('photo_module_enabled');
    if ($moduleEnabled === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    $loginModes = array(
        'album_edit', 'album_save', 'album_delete', 'album_lock', 'album_unlock',
        'photo_download', 'photo_delete', 'photo_rotate', 'ecard', 'ecard_preview', 'ecard_send'
    );
    if ($moduleEnabled === 2 || in_array($getMode, $loginModes, true)) {
        require(__DIR__ . '/../system/login_valid.php');
    }

    if (str_starts_with($getMode, 'ecard') && !$gSettingsManager->getBool('photo_ecard_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    switch ($getMode) {
        case 'albums':
            $page = new PhotosPresenter();
            $page->createAlbums($getPhotoUuid, $getStart, $getPhotoNr);
            if ($getPhotoUuid === '') {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-image-fill');
            } else {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            }
            $page->show();
            break;

        case 'album_edit':
            $page = new PhotosPresenter();
            $page->createAlbumEditForm($getPhotoUuid, $getParentPhotoUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'album_save':
            $photoAlbum = new Album($gDb);
            if ($getPhotoUuid !== '' && !$photoAlbum->readDataByUuid($getPhotoUuid)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            if (!$photoAlbum->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $form->validate($_POST);
            (new AlbumService($gDb))->saveData($photoAlbum, $formValues, (string)$formValues['parent_album_uuid']);
            unset($_SESSION['photo_album']);
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'album_delete':
        case 'album_lock':
        case 'album_unlock':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
            $photoAlbum = new Album($gDb);
            if (!$photoAlbum->readDataByUuid($getPhotoUuid) || !$photoAlbum->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            if ($getMode === 'album_delete') {
                $photoAlbum->delete();
                echo json_encode(array('status' => 'success'));
            } else {
                $photoAlbum->setValue('pho_locked', $getMode === 'album_lock' ? 1 : 0);
                $photoAlbum->save();
                echo 'done';
            }
            break;

        case 'photo_present':
            $page = new PhotosPresenter();
            $page->createPhotoPresenter($getPhotoUuid, $getPhotoNr);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'photo_show':
            $photoAlbum = new Album($gDb);
            $albumFound = $getPhotoUuid !== '' && $photoAlbum->readDataByUuid($getPhotoUuid);
            if ((!$albumFound && !($getThumbnail && $getPhotoNr === 0)) || !$photoAlbum->isVisible()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $_SESSION['photo_album'] = $photoAlbum;

            $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/'
                . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');
            $photoPath = $albumFolder . '/' . $getPhotoNr . '.jpg';
            $thumbnailPath = $albumFolder . '/thumbnails/' . $getPhotoNr . '.jpg';
            $image = null;

            if ($getThumbnail) {
                if ($getPhotoNr > 0) {
                    $thumbnailLength = null;
                    if (is_file($thumbnailPath)) {
                        $properties = getimagesize($thumbnailPath);
                        if (is_array($properties)) {
                            $thumbnailLength = max($properties[0], $properties[1]);
                        }
                    }
                    if (!is_file($thumbnailPath) || $thumbnailLength !== $gSettingsManager->getInt('photo_thumbs_scale')) {
                        FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/thumbnails');
                        $image = new Image($photoPath);
                        $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
                        $image->copyToFile(null, $thumbnailPath);
                    } else {
                        header('Content-Type: image/jpeg');
                        readfile($thumbnailPath);
                    }
                } else {
                    $image = new Image(getThemedFile('/images/no_photo_found.png'));
                    $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
                }
            } else {
                if (!is_file($photoPath)) {
                    $photoPath = getThemedFile('/images/no_photo_found.png');
                }
                $image = new Image($photoPath);
                if ($getMaxWidth > 0 || $getMaxHeight > 0) {
                    $image->scale($getMaxWidth, $getMaxHeight);
                } else {
                    [$getMaxWidth, $getMaxHeight] = $image->getImageSize();
                }
            }

            if ($image !== null) {
                if ($getMaxWidth > 200 && $gSettingsManager->getString('photo_image_text') !== '') {
                    $fontSize = $getMaxWidth / max(1, $gSettingsManager->getInt('photo_image_text_size') ?: 40);
                    $imageSize = $image->getImageSize();
                    $fontColor = imagecolorallocate($image->getImageResource(), 255, 255, 255);
                    $text = $photoAlbum->getValue('pho_photographers') !== ''
                        ? '© ' . $photoAlbum->getValue('pho_photographers', 'database')
                        : $gSettingsManager->getString('photo_image_text');
                    imagettftext($image->getImageResource(), $fontSize, 0, (int)$fontSize,
                        (int)($imageSize[1] - $fontSize), $fontColor,
                        ADMIDIO_PATH . FOLDER_SYSTEM . '/fonts/mrphone.ttf', $text);
                }
                header('Content-Type: ' . $image->getMimeType());
                $image->copyToBrowser();
                $image->delete();
            }
            break;

        case 'photo_download':
            if (!$gSettingsManager->getBool('photo_download_enabled')) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $photoAlbum = new Album($gDb);
            if (!$photoAlbum->readDataByUuid($getPhotoUuid) || !$photoAlbum->isVisible()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $photoService = new PhotoService($gDb, $photoAlbum);
            $temporaryFile = $getPhotoNr === 0;
            $download = $temporaryFile
                ? $photoService->createAlbumArchive()
                : $photoService->getDownloadFile($getPhotoNr);
            $fileSize = filesize($download['path']);
            if ($fileSize === false) {
                throw new Exception('SYS_FILE_NOT_EXIST');
            }
            header('Content-Type: ' . $download['contentType']);
            header('Content-Length: ' . $fileSize);
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
            header('Expires: 0');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: private');
            $file = fopen($download['path'], 'rb');
            if ($file === false) {
                throw new Exception('SYS_FILE_NOT_EXIST');
            }
            fpassthru($file);
            fclose($file);
            if ($temporaryFile) {
                try {
                    FileSystemUtils::deleteFileIfExists($download['path']);
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $download['path']));
                }
            }
            break;

        case 'photo_delete':
        case 'photo_rotate':
            if (!$gCurrentUser->isAdministratorPhotos()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
            $photoAlbum = new Album($gDb);
            if (!$photoAlbum->readDataByUuid($getPhotoUuid) || !$photoAlbum->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $photoService = new PhotoService($gDb, $photoAlbum);
            if ($getMode === 'photo_rotate') {
                $photoService->rotatePhoto($getPhotoNr, $getDirection);
            } else {
                $photoService->deletePhoto($getPhotoNr);
                $_SESSION['photo_album'] = $photoAlbum;
            }
            echo 'done';
            break;

        case 'ecard':
            $page = new PhotosPresenter();
            $page->createEcardForm($getPhotoUuid, $getPhotoNr, $getUserUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'ecard_preview':
            $form = clone $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            foreach (array('ecard_recipients', 'ecard_message') as $optionalElementId) {
                $element = $form->getElement($optionalElementId);
                if ($element !== null) {
                    $element['property'] = FormPresenter::FIELD_DEFAULT;
                    $form->replaceElement($optionalElementId, $element);
                }
            }
            $formValues = $form->validate($_POST);
            $imageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array(
                'mode' => 'photo_show', 'photo_uuid' => $formValues['photo_uuid'],
                'photo_nr' => $formValues['photo_nr'], 'max_width' => 350,
                'max_height' => $gSettingsManager->getInt('photo_ecard_scale')
            ));
            $ecard = new ECard($gL10n);
            $ecardTemplate = $ecard->getEcardTemplate($formValues['ecard_template']);
            if ($ecardTemplate === null) {
                throw new Exception('SYS_ERROR_PAGE_NOT_FOUND');
            }
            $smarty = PagePresenter::createSmartyObject();
            $smarty->assign('l10n', $gL10n);
            $smarty->assign('ecardContent', $ecard->parseEcardTemplate(
                $imageUrl, $formValues['ecard_message'] ?? '', $ecardTemplate, '', ''
            ));
            echo $smarty->fetch('modules/photos.ecard.preview.tpl');
            break;

        case 'ecard_send':
            $postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
            $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $form->validate($_POST);
            $roleUuids = array();
            $userUuids = array();
            foreach ($formValues['ecard_recipients'] as $value) {
                if (str_starts_with($value, 'groupID: ')) {
                    $roleUuid = substr($value, 9);
                    if (Uuid::isValid($roleUuid)) {
                        $roleUuids[] = $roleUuid;
                    }
                } elseif (Uuid::isValid($value)) {
                    $userUuids[] = $value;
                }
            }
            (new ECardService($gDb))->send(
                $formValues['photo_uuid'], (int)$formValues['photo_nr'], $postTemplateName,
                $formValues['ecard_message'], $roleUuids, $userUuids
            );
            echo json_encode(array(
                'status' => 'success',
                'message' => $gL10n->get('SYS_ECARD_SUCCESSFULLY_SEND'),
                'url' => $gNavigation->getPreviousUrl()
            ));
            break;
    }
} catch (Throwable $e) {
    if ($getMode === 'ecard_preview') {
        $gMessage->showInModalWindow();
    }
    handleException($e, in_array($getMode, array('album_save', 'album_delete', 'ecard_send'), true));
}
