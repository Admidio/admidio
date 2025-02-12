<?php
/**
 ***********************************************************************************************
 * Show the photo within the Admidio html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid : UUID of the album of the photo that should be shown
 * photo_nr   : Number of the photo that should be shown
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\Entity\Album;
use Admidio\UI\Presenter\PagePresenter;

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid', array('requireValue' => true));
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users are allowed to use this page
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // get album data if it's not already stored in session
    if (isset($_SESSION['photo_album']) && (int)$_SESSION['photo_album']->getValue('pho_uuid') === $getPhotoUuid) {
        $photoAlbum =& $_SESSION['photo_album'];
    } else {
        $photoAlbum = new Album($gDb);
        $photoAlbum->readDataByUuid($getPhotoUuid);
        $_SESSION['photo_album'] = $photoAlbum;
    }

    // check if the current user could view this photo album
    if (!$photoAlbum->isVisible()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // get number of next and previous photo
    $previousImage = $getPhotoNr - 1;
    $nextImage = $getPhotoNr + 1;
    $urlPreviousImage = '#';
    $urlNextImage = '#';
    $urlCurrentImage = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $getPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height')));

    if ($previousImage > 0) {
        $urlPreviousImage = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_presenter.php', array('photo_nr' => $previousImage, 'photo_uuid' => $getPhotoUuid));
    }
    if ($nextImage <= $photoAlbum->getValue('pho_quantity')) {
        $urlNextImage = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_presenter.php', array('photo_nr' => $nextImage, 'photo_uuid' => $getPhotoUuid));
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-photos-presenter', $photoAlbum->getValue('pho_name'));

    // show additional album information
    $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin') && strlen($photoAlbum->getValue('pho_end')) > 0) {
        $datePeriod .= ' ' . $gL10n->get('SYS_DATE_TO') . ' ' . $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
    }

    $page->addHtml('<p class="lead">' . $datePeriod . '<br />' . $gL10n->get('SYS_PHOTOS_BY_VAR', array($photoAlbum->getPhotographer())) . '</p>');

    // Show photo with link to next photo
    if ($nextImage <= $photoAlbum->getValue('pho_quantity')) {
        $page->addHtml('<div class="admidio-img-presenter"><a href="' . $urlNextImage . '"><img src="' . $urlCurrentImage . '" alt="Foto"></a></div>');
    } else {
        $page->addHtml('<div class="admidio-img-presenter"><img src="' . $urlCurrentImage . '" alt="' . $gL10n->get('SYS_PHOTO') . '" /></div>');
    }

    // show link to navigate to next and previous photos
    $page->addHtml('<div class="btn-group admidio-margin-bottom">');

    if ($previousImage > 0) {
        $page->addHtml('
    <button class="btn btn-secondary" onclick="window.location.href=\'' . $urlPreviousImage . '\'">
        <i class="bi bi-arrow-left-circle-fill"></i>' . $gL10n->get('SYS_PREVIOUS_PHOTO') . '</button>');
    }
    if ($nextImage <= $photoAlbum->getValue('pho_quantity')) {
        $page->addHtml('
    <button class="btn btn-primary" onclick="window.location.href=\'' . $urlNextImage . '\'">
        <i class="bi bi-arrow-right-circle-fill"></i>' . $gL10n->get('SYS_NEXT_PHOTO') . '</button>');
    }
    $page->addHtml('</div>');

    // show html of complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
