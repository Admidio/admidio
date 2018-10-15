<?php
/**
 ***********************************************************************************************
 * Show the photo within the Admidio html
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_nr : Number of the photo that should be shown
 * pho_id   : Id of the album of the photo that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id',   'int', array('requireValue' => true));
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ((int) $gSettingsManager->get('enable_photo_module') === 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require(__DIR__ . '/../../system/login_valid.php');
}

// erfassen des Albums falls noch nicht in Session gespeichert
if (isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_id') === $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
}
else
{
    $photoAlbum = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] = $photoAlbum;
}

// check if the current user could view this photo album
if(!$photoAlbum->isVisible())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Naechstes und Letztes Bild
$previousImage = $getPhotoNr - 1;
$nextImage     = $getPhotoNr + 1;
$urlPreviousImage = '#';
$urlNextImage     = '#';
$urlCurrentImage  = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $getPhotoId, 'photo_nr' => $getPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height')));

if ($previousImage > 0)
{
    $urlPreviousImage = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $previousImage, 'pho_id' => $getPhotoId));
}
if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
    $urlNextImage = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $nextImage, 'pho_id' => $getPhotoId));
}

// create html page object
$page = new HtmlPage($photoAlbum->getValue('pho_name'));

// wenn Popupmode oder Colorbox, dann normalen Kopf unterdruecken
if ((int) $gSettingsManager->get('photo_show_mode') === 0)
{
    $page->hideThemeHtml();
}

if ((int) $gSettingsManager->get('photo_show_mode') === 2)
{
    // get module menu
    $photoPresenterMenu = $page->getMenu();

    // if you have no popup or colorbox then show a button back to the album
    if ((int) $gSettingsManager->get('photo_show_mode') === 2)
    {
        $photoPresenterMenu->addItem(
            'menu_item_back_to_album', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('pho_id' => $getPhotoId)),
            $gL10n->get('PHO_BACK_TO_ALBUM'), 'fa-image'
        );
    }

    // show link to navigate to next and previous photos
    if ($previousImage > 0)
    {
        $photoPresenterMenu->addItem(
            'menu_item_previous_photo', $urlPreviousImage,
            $gL10n->get('PHO_PREVIOUS_PHOTO'), 'fa-arrow-alt-circle-left'
        );
    }

    if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
    {
        $photoPresenterMenu->addItem(
            'menu_item_next_photo', $urlNextImage,
            $gL10n->get('PHO_NEXT_PHOTO'), 'fa-arrow-alt-circle-right'
        );
    }
}

// Show photo with link to next photo
if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
    $page->addHtml('<div class="admidio-img-presenter"><a href="'.$urlNextImage.'"><img src="'.$urlCurrentImage.'" alt="Foto"></a></div>');
}
else
{
    $page->addHtml('<div class="admidio-img-presenter"><img src="'.$urlCurrentImage.'" alt="'.$gL10n->get('SYS_PHOTO').'" /></div>');
}

if ((int) $gSettingsManager->get('photo_show_mode') === 0)
{
    // in popup mode show buttons for prev, next and close
    $page->addHtml('
    <div class="btn-group">
        <button class="btn btn-default" onclick="window.location.href=\''.$urlPreviousImage.'\'">
            <i class="fas fa-arrow-alt-circle-left"></i>'.$gL10n->get('PHO_PREVIOUS_PHOTO').'</button>
        <button class="btn btn-default" onclick="parent.window.close()">
            <i class="fas fa-window-close"></i>'.$gL10n->get('SYS_CLOSE_WINDOW').'</button>
        <button class="btn btn-default" onclick="window.location.href=\''.$urlNextImage.'\'">
            <i class="fas fa-arrow-alt-circle-right"></i>'.$gL10n->get('PHO_NEXT_PHOTO').'</button>
    </div>');
}
elseif ((int) $gSettingsManager->get('photo_show_mode') === 2)
{
    // if no popup mode then show additional album information
    $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin')
    && strlen($photoAlbum->getValue('pho_end')) > 0)
    {
        $datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
    }

    $page->addHtml('
    <div class="row">
        <div class="col-sm-2 col-xs-4">'.$gL10n->get('SYS_DATE').'</div>
        <div class="col-sm-4 col-xs-8"><strong>'.$datePeriod.'</strong></div>
    </div>
    <div class="row">
        <div class="col-sm-2 col-xs-4">'.$gL10n->get('PHO_PHOTOGRAPHER').'</div>
        <div class="col-sm-4 col-xs-8"><strong>'.$photoAlbum->getValue('pho_photographers').'</strong></div>
    </div>');
}

// show html of complete page
$page->show();
