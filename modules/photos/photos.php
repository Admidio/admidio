<?php
/**
 ***********************************************************************************************
 * Show a list of all photo albums
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid : UUID of the album which photos should be shown
 * start_thumbnail : Number of the thumbnail which is the first that should be shown
 * start      : Position of query recordset where the visual output should start
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\Entity\Album;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;

require_once(__DIR__ . '/../../system/common.php');

try {
    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid');
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getStartThumbnail = admFuncVariableIsValid($_GET, 'start_thumbnail', 'int', array('defaultValue' => 1));
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int');

    // Fotoalbums-Objekt erzeugen oder aus Session lesen
    if (isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_uuid') === $getPhotoUuid) {
        $photoAlbum =& $_SESSION['photo_album'];
    } else {
        // einlesen des Albums falls noch nicht in Session gespeichert
        $photoAlbum = new Album($gDb);
        if ($getPhotoUuid !== '') {
            $photoAlbum->readDataByUuid($getPhotoUuid);
        }

        $_SESSION['photo_album'] = $photoAlbum;
    }

    // set headline of module
    if ($getPhotoUuid !== '') {
        // check if the current user could view this photo album
        if (!$photoAlbum->isVisible()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $headline = $photoAlbum->getValue('pho_name');

        // Drop URL on the navigation stack
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        $headline = $gL10n->get('SYS_PHOTO_ALBUMS');

        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-image-fill');
    }

    // create an HTML page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-photos', $headline);
    $page->setContentFullWidth();

    // add rss feed to photos
    if ($gSettingsManager->getBool('enable_rss')) {
        $page->addRssFile(
            ADMIDIO_URL . '/rss/photos.php?organization_short_name=' . $gCurrentOrganization->getValue('org_shortname'),
            $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $gL10n->get('SYS_PHOTO_ALBUMS')))
        );
    }

    if ($photoAlbum->isEditable()) {
        $page->addJavascript('
        lightbox.option({
            "albumLabel": "' . strtr($gL10n->get('SYS_PHOTO_X_OF_Y'), array('#VAR1#' => '%1', '#VAR2#' => '%2')) . '"
        });

        $(".admidio-image-rotate").click(function() {
            imageNr = $(this).data("image");
            $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_function.php?photo_uuid=' . $getPhotoUuid . '&photo_nr=" + $(this).data("image") + "&mode=rotate&direction=" + $(this).data("direction"),
                {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"},
                function(data) {
                    if (data === "done") {
                        // Appending the random number is necessary to trick the browser cache
                        $("#img_" + imageNr).attr("src", "' . ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php?photo_uuid=' . $getPhotoUuid . '&thumb=1&photo_nr=" + imageNr + "&rand=" + Math.random());
                    } else {
                        messageBox(data, "' . $gL10n->get('SYS_ERROR') . '", "error");
                    }
                }
            );
        });

        $(".admidio-album-lock").click(function() {
            $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_function.php?mode=" + $(this).data("mode") + "&photo_uuid=" + $(this).data("id"),
                {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"},
                function(data) {
                    if (data === "done") {
                        location.reload();
                    } else {
                        messageBox(data, "' . $gL10n->get('SYS_ERROR') . '", "error");
                    }
                }
            );
        });',
            true
        );
    }

    // integrate lightbox 2 addon
    if ((int)$gSettingsManager->get('photo_show_mode') === 1) {
        $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/css/lightbox.css');
        $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/js/lightbox.js');
    }

    // if a photo number was committed, then simulate a left mouse click
    if ($getPhotoNr > 0) {
        $page->addJavascript('$("#img_' . $getPhotoNr . '").trigger("click");', true);
    }

    if ($gCurrentUser->isAdministratorPhotos()) {
        // show a link to create a new album
        $page->addPageFunctionsMenuItem(
            'menu_item_photos_new_album',
            $gL10n->get('SYS_CREATE_ALBUM'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_new.php', array('parent_photo_uuid' => $getPhotoUuid)),
            'bi-plus-circle-fill'
        );

        if ($getPhotoUuid !== '') {
            // show a link to edit the album
            $page->addPageFunctionsMenuItem(
                'menu_item_photos_edit_album',
                $gL10n->get('SYS_EDIT_ALBUM'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_new.php', array('photo_uuid' => $getPhotoUuid)),
                'bi-pencil-square'
            );

            // show a link to upload photos
            $page->addPageFunctionsMenuItem(
                'menu_item_photos_upload_photo',
                $gL10n->get('SYS_UPLOAD_PHOTOS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php', array('module' => 'photos', 'uuid' => $getPhotoUuid)),
                'bi-upload'
            );
        }
    }

    // show a link to download photos if enabled
    if ($gSettingsManager->getBool('photo_download_enabled') && $photoAlbum->getValue('pho_quantity') > 0) {
        // show a link to download photos
        $page->addPageFunctionsMenuItem(
            'menu_item_photos_download',
            $gL10n->get('SYS_DOWNLOAD_ALBUM'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_download.php', array('photo_uuid' => $getPhotoUuid)),
            'bi-download'
        );
    }

    ChangelogService::displayHistoryButton($page, 'photos', 'photos', !empty($getPhotoUuid), array('uuid' => $getPhotoUuid));

    if ($getPhotoUuid !== '') {
        // show additional album information
        $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

        if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin') && strlen($photoAlbum->getValue('pho_end')) > 0) {
            $datePeriod .= ' ' . $gL10n->get('SYS_DATE_TO') . ' ' . $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
        }

        // Notice for users with photo edit right that this album is locked
        if ($photoAlbum->getValue('pho_locked') == 1) {
            $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="bi bi-exclamation-triangle-fill"></i>' . $gL10n->get('SYS_ALBUM_NOT_APPROVED') . '</div></p>');
        }

        $page->addHtml('
    <p class="lead">
        <p class="fw-bold">' . $datePeriod . '</p class="fw-bold">
        <p>' . $photoAlbum->countImages() . ' ' . $gL10n->get('SYS_PHOTOS_BY_VAR', array($photoAlbum->getPhotographer())) . '</p>');

        if (strlen($photoAlbum->getValue('pho_description')) > 0) {
            $page->addHtml('<p>' . $photoAlbum->getValue('pho_description', 'html') . '</p>');
        }

        $page->addHtml('</p>');
    }

    // THUMBNAILS
    // Only if the current album contains images
    if ($photoAlbum->getValue('pho_quantity') > 0) {
        $photoThumbnailTable = '';
        $firstPhotoNr = 1;
        $lastPhotoNr = $gSettingsManager->getInt('photo_thumbs_page');

        // Open the correct album page when the image number has been set
        if ($getPhotoNr > 0) {
            $firstPhotoNr = (round(($getPhotoNr - 1) / $gSettingsManager->getInt('photo_thumbs_page')) * $gSettingsManager->getInt('photo_thumbs_page')) + 1;
            $lastPhotoNr = $firstPhotoNr + $gSettingsManager->getInt('photo_thumbs_page') - 1;
        }

        // create a thumbnail container
        $page->addHtml('<div class="row">');

        for ($actThumbnail = $firstPhotoNr; $actThumbnail <= $lastPhotoNr && $actThumbnail <= $photoAlbum->getValue('pho_quantity'); ++$actThumbnail) {
            if ($actThumbnail <= $photoAlbum->getValue('pho_quantity')) {
                $photoThumbnailTable .= '<div class="col-xxl-2 col-xl-3 col-lg-4 col-sm-6 admidio-photos-thumbnail" id="div_image_' . $actThumbnail . '">';

                // Modal with lightbox 2
                if ((int)$gSettingsManager->get('photo_show_mode') === 1) {
                    $photoThumbnailTable .= '
                        <a data-lightbox="admidio-gallery" data-title="' . $headline . '"
                            href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height'))) . '"><img
                            class="rounded" id="img_' . $actThumbnail . '" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'thumb' => 1)) . '" alt="' . $actThumbnail . '" /></a>';
                } // Same window
                elseif ((int)$gSettingsManager->get('photo_show_mode') === 2) {
                    $photoThumbnailTable .= '
                        <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_presenter.php', array('photo_nr' => $actThumbnail, 'photo_uuid' => $getPhotoUuid)) . '"><img
                            class="rounded" id="img_' . $actThumbnail . '" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'thumb' => 1)) . '" alt="' . $actThumbnail . '" />
                        </a>';
                }

                if ($gCurrentUser->isAdministratorPhotos() || ($gValidLogin && $gSettingsManager->getBool('photo_ecard_enabled')) || $gSettingsManager->getBool('photo_download_enabled')) {
                    $photoThumbnailTable .= '<div id="image_preferences_' . $actThumbnail . '" class="text-center">';
                }


                if ($gValidLogin && $gSettingsManager->getBool('photo_ecard_enabled')) {
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/ecards.php', array('photo_nr' => $actThumbnail, 'photo_uuid' => $getPhotoUuid, 'show_page' => $getPhotoNr)) . '">
                            <i class="bi bi-envelope" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_PHOTO_AS_ECARD') . '"></i></a>';
                }

                if ($gSettingsManager->getBool('photo_download_enabled')) {
                    // show a link to download a photo
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_download.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail)) . '">
                            <i class="bi bi-download" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DOWNLOAD_PHOTO') . '"></i></a>';
                }

                // buttons for moderation
                if ($gCurrentUser->isAdministratorPhotos()) {
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link admidio-image-rotate" href="javascript:void(0)" data-image="' . $actThumbnail . '" data-direction="right">
                            <i class="bi bi-arrow-clockwise" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ROTATE_PHOTO_RIGHT') . '"></i></a>
                        <a class="admidio-icon-link admidio-image-rotate"  href="javascript:void(0)"  data-image="' . $actThumbnail . '" data-direction="left"">
                            <i class="bi bi-arrow-counterclockwise" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_ROTATE_PHOTO_LEFT') . '"></i></a>
                        <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                            data-message="' . $gL10n->get('SYS_WANT_DELETE_PHOTO') . '"
                            data-href="callUrlHideElement(\'div_image_' . $actThumbnail . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_function.php', array('mode' => 'delete', 'photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i></a>';
                }

                if ($gCurrentUser->isAdministratorPhotos() || ($gValidLogin && $gSettingsManager->getBool('photo_ecard_enabled')) || $gSettingsManager->getBool('photo_download_enabled')) {
                    $photoThumbnailTable .= '</div>';
                }
                $photoThumbnailTable .= '</div>';
            }
        }

        // the lightbox should be able to go through the whole album, therefore, we must
        // integrate links to the photos of the album pages to this page and container but hidden
        if ((int)$gSettingsManager->get('photo_show_mode') === 1) {
            $photoThumbnailTableShown = false;

            for ($hiddenPhotoNr = 1; $hiddenPhotoNr <= $photoAlbum->getValue('pho_quantity'); ++$hiddenPhotoNr) {
                if ($hiddenPhotoNr >= $firstPhotoNr && $hiddenPhotoNr < $actThumbnail) {
                    if (!$photoThumbnailTableShown) {
                        $page->addHtml($photoThumbnailTable);
                        $photoThumbnailTableShown = true;
                    }
                } else {
                    $page->addHtml('
                    <a class="d-none" data-lightbox="admidio-gallery" data-title="' . $headline . '"
                        href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $hiddenPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height'))) . '">&nbsp;</a>
                ');
                }
            }
            $page->addHtml('</div>');   // close album-container
        } else {
            // show photos if the lightbox is not used
            $photoThumbnailTable .= '</div>';   // close album-container
            $page->addHtml($photoThumbnailTable);
        }

        // show information about user who creates the recordset and changed it
        $page->addHtml(admFuncShowCreateChangeInfoById(
            (int)$photoAlbum->getValue('pho_usr_id_create'),
            $photoAlbum->getValue('pho_timestamp_create'),
            (int)$photoAlbum->getValue('pho_usr_id_change'),
            $photoAlbum->getValue('pho_timestamp_change')
        ));

        // show page navigations through thumbnails
        $page->addHtml(admFuncGeneratePagination(
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('photo_uuid' => $photoAlbum->getValue('pho_uuid'))),
            $photoAlbum->getValue('pho_quantity'),
            $gSettingsManager->getInt('photo_thumbs_page'),
            $getPhotoNr,
            true,
            'photo_nr'
        ));
    }
    // Album list

    // show all albums of the current level
    $sql = 'SELECT *
          FROM ' . TBL_PHOTOS . '
         WHERE pho_org_id = ? -- $gCurrentOrgId';
    $queryParams = array($gCurrentOrgId);
    if ($getPhotoUuid !== '') {
        $sql .= '
        AND pho_pho_id_parent = ? -- $photoAlbum->getValue(\'pho_id\')';
        $queryParams[] = $photoAlbum->getValue('pho_id');
    } else {
        $sql .= '
        AND (pho_pho_id_parent IS NULL) ';
    }

    if (!$gCurrentUser->isAdministratorPhotos()) {
        $sql .= '
        AND pho_locked = false ';
    }

    $sql .= '
    ORDER BY pho_begin DESC';

    $albumStatement = $gDb->queryPrepared($sql, $queryParams);
    $albumList = $albumStatement->fetchAll();
    $albumsCount = $albumStatement->rowCount();

    if ($albumsCount > 0) {
        // if there are photos in the current album and sub albums exist, then show a separator
        if ($photoAlbum->getValue('pho_quantity') > 0) {
            $page->addHtml('<hr />');
        }

        $childPhotoAlbum = new Album($gDb);

        $page->addHtml('<div class="row admidio-margin-bottom">');

        for ($x = $getStart; $x <= $getStart + $gSettingsManager->getInt('photo_albums_per_page') - 1 && $x < $albumsCount; ++$x) {
            $htmlLock = '';

            $childPhotoAlbum->clear();
            $childPhotoAlbum->setArray($albumList[$x]);

            // folder of the album
            $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $childPhotoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $childPhotoAlbum->getValue('pho_id');

            // show album if the album is not locked, or it has child albums or the user has the photo module edit right
            if ((is_dir($albumFolder) && $childPhotoAlbum->isVisible())
                || $childPhotoAlbum->hasChildAlbums()) {
                // Get random image for preview
                $shuffleImage = $childPhotoAlbum->shuffleImage();

                // album title
                if (is_dir($albumFolder) || $childPhotoAlbum->hasChildAlbums()) {
                    $albumTitle = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))) . '">' . $childPhotoAlbum->getValue('pho_name') . '</a>';
                } else {
                    $albumTitle = $childPhotoAlbum->getValue('pho_name');
                }

                $albumDate = $childPhotoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
                if ($childPhotoAlbum->getValue('pho_end') !== $childPhotoAlbum->getValue('pho_begin')) {
                    $albumDate .= ' ' . $gL10n->get('SYS_DATE_TO') . ' ' . $childPhotoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
                }

                $page->addHtml('
                <div class="admidio-album col-sm-6 col-lg-4 col-xl-3" id="panel_pho_' . $childPhotoAlbum->getValue('pho_uuid') . '">
                    <div class="card admidio-card">
                        <a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))) . '"><img
                            class="card-img-top" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $shuffleImage['shuffle_pho_uuid'], 'photo_nr' => $shuffleImage['shuffle_img_nr'], 'thumb' => 1)) . '" alt="' . $gL10n->get('SYS_PHOTOS') . '" /></a>
                        <div class="card-body">
                            <h5 class="card-title">' . $albumTitle);
                // if the user has admin rights for photo module then show some functions
                if ($gCurrentUser->isAdministratorPhotos()) {
                    if ((bool)$childPhotoAlbum->getValue('pho_locked') === false) {
                        $htmlLock = '<li><a class="dropdown-item admidio-album-lock" href="javascript:void(0)" data-id="' . $childPhotoAlbum->getValue('pho_uuid') . '" data-mode="lock">
                                            <i class="bi bi-lock" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_LOCK_ALBUM') . '</a>
                                 </li>';
                    }

                    $page->addHtml('
                                    <div class="dropdown float-end">
                                        <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_new.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))) . '">
                                                <i class="bi bi-pencil-square" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_EDIT_ALBUM') . '</a>
                                            </li>
                                            ' . $htmlLock . '
                                            <li><a class="dropdown-item admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                                data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($childPhotoAlbum->getValue('pho_name', 'database'))) . '"
                                                data-href="callUrlHideElement(\'panel_pho_' . $childPhotoAlbum->getValue('pho_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_function.php', array('mode' => 'delete', 'photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                                                <i class="bi bi-trash" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_DELETE_ALBUM') . '</a>
                                            </li>
                                        </ul>
                                    </div>');
                }
                $page->addHtml('</h5>

                            <p class="card-text">' . $albumDate . '</p>');

                if (strlen($childPhotoAlbum->getValue('pho_description')) > 0) {
                    $albumDescription = $childPhotoAlbum->getValue('pho_description', 'html');

                    if (strlen($albumDescription) > 200) {
                        // read the first 200 chars of a text, then search for the last space and cut the text there. After that add a "more" link
                        $textPrev = substr($albumDescription, 0, 200);
                        $maxPosPrev = strrpos($textPrev, ' ');
                        $albumDescription = substr($textPrev, 0, $maxPosPrev) .
                            ' <span class="collapse" id="viewdetails-' . $childPhotoAlbum->getValue('pho_uuid') . '">' . substr($albumDescription, $maxPosPrev) . '.
                                        </span> <a class="admidio-icon-link"  data-bs-toggle="collapse" data-bs-target="#viewdetails-' . $childPhotoAlbum->getValue('pho_uuid') . '">»</a>';
                    }

                    $page->addHtml('<p class="card-text">' . $albumDescription . '</p>');
                }

                $page->addHtml('<p class="card-text">' . $childPhotoAlbum->countImages() . ' ' . $gL10n->get('SYS_PHOTOS_BY_VAR', array($childPhotoAlbum->getPhotographer())) . '</p>');

                // Notice for users with photo edit rights that the folder of the album doesn't exist
                if (!is_dir($albumFolder) && !$childPhotoAlbum->hasChildAlbums() && $gCurrentUser->isAdministratorPhotos()) {
                    $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="bi bi-exclamation-triangle-fill"></i>' . $gL10n->get('SYS_ALBUM_FOLDER_NOT_FOUND') . '</div></p>');
                }

                // Notice for users with photo edit right that this album is locked
                if ($childPhotoAlbum->getValue('pho_locked') == 1) {
                    $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="bi bi-exclamation-triangle-fill"></i>' . $gL10n->get('SYS_ALBUM_NOT_APPROVED') . '</div></p>');
                }

                if ($gCurrentUser->isAdministratorPhotos() && $childPhotoAlbum->getValue('pho_locked') == 1) {
                    $page->addHtml('<button class="btn btn-primary admidio-album-lock" data-id="' . $childPhotoAlbum->getValue('pho_uuid') . '" data-mode="unlock">' . $gL10n->get('SYS_UNLOCK_ALBUM') . '</button>');
                }

                $page->addHtml('</div>
                    </div>
                </div>
            ');
            }
        }//for

        $page->addHtml('</div>');
    }

    // Empty album, if the album contains neither photos nor subfolders
    if ($albumsCount === 0 && ($photoAlbum->getValue('pho_quantity') == 0 || strlen($photoAlbum->getValue('pho_quantity')) === 0)) {  // alle vorhandenen Albumen werden ignoriert
        $page->addHtml($gL10n->get('SYS_ALBUM_CONTAINS_NO_PHOTOS'));
    }

    // If necessary, show links to navigate to the next and previous albums of the query
    $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('photo_uuid' => $getPhotoUuid));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $albumsCount, $gSettingsManager->getInt('photo_albums_per_page'), $getStart));

    // show HTML of the complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
