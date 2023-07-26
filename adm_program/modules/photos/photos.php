<?php
/**
 ***********************************************************************************************
 * Show a list of all photo albums
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid : UUID of album which photos should be shown
 * headline   : Headline of the module that will be displayed
 *              (Default) PHO_PHOTO_ALBUMS
 * start_thumbnail : Number of the thumbnail which is the first that should be shown
 * start      : Position of query recordset where the visual output should start
 *
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_photo_module') === 2) {
    // only logged-in users can access the module
    require(__DIR__ . '/../../system/login_valid.php');
}

// Initialize and check the parameters
$getPhotoUuid      = admFuncVariableIsValid($_GET, 'photo_uuid', 'string');
$getHeadline       = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('PHO_PHOTO_ALBUMS')));
$getStart          = admFuncVariableIsValid($_GET, 'start', 'int');
$getStartThumbnail = admFuncVariableIsValid($_GET, 'start_thumbnail', 'int', array('defaultValue' => 1));
$getPhotoNr        = admFuncVariableIsValid($_GET, 'photo_nr', 'int');

unset($_SESSION['photo_album_request'], $_SESSION['ecard_request']);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if (isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_uuid') === $getPhotoUuid) {
    $photoAlbum =& $_SESSION['photo_album'];
} else {
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photoAlbum = new TablePhotos($gDb);
    if ($getPhotoUuid !== '') {
        $photoAlbum->readDataByUuid($getPhotoUuid);
    }

    $_SESSION['photo_album'] = $photoAlbum;
}

// set headline of module
if ($getPhotoUuid !== '') {
    // check if the current user could view this photo album
    if (!$photoAlbum->isVisible()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $headline = $photoAlbum->getValue('pho_name');

    // Drop URL on navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
} else {
    $headline = $getHeadline;

    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-image');
}

// create html page object
$page = new HtmlPage('admidio-photos', $headline);

// add rss feed to photos
if ($gSettingsManager->getBool('enable_rss')) {
    $page->addRssFile(
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/rss_photos.php', array('headline' => $getHeadline)),
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline))
    );
}

if ($photoAlbum->isEditable()) {
    $page->addJavascript('
        $(".admidio-image-rotate").click(function() {
            imageNr = $(this).data("image");
            $.post("'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_function.php?photo_uuid='.$getPhotoUuid.'&photo_nr=" + $(this).data("image") + "&job=rotate&direction=" + $(this).data("direction"),
                {"admidio-csrf-token": "' . $gCurrentSession->getCsrfToken() . '"},
                function(data) {
                    if (data === "done") {
                        // Appending the random number is necessary to trick the browser cache
                        $("#img_" + imageNr).attr("src", "'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php?photo_uuid='.$getPhotoUuid.'&thumb=1&photo_nr=" + imageNr + "&rand=" + Math.random());
                    } else {
                        alert(data);
                    }
                }
            );
        });

        $(".admidio-album-lock").click(function() {
            $.post("'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_function.php?mode=" + $(this).data("mode") + "&photo_uuid=" + $(this).data("id"),
                {"admidio-csrf-token": "' . $gCurrentSession->getCsrfToken() . '"},
                function(data) {
                    if (data === "done") {
                        location.reload();
                    } else {
                        alert(data);
                    }
                }
            );
        });',
        true
    );
}

// integrate bootstrap ekko lightbox addon
if ((int) $gSettingsManager->get('photo_show_mode') === 1) {
    $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/lightbox/ekko-lightbox.css');
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/lightbox/ekko-lightbox.js');

    $page->addJavascript('
        $("*[data-toggle=\"lightbox\"]").click(function(event) {
            event.preventDefault();
            $(this).ekkoLightbox();
        });',
        true
    );
}

// if a photo number was committed then simulate a left mouse click
if ($getPhotoNr > 0) {
    $page->addJavascript('$("#img_'.$getPhotoNr.'").trigger("click");', true);
}

if ($gCurrentUser->editPhotoRight()) {
    // show link to create new album
    $page->addPageFunctionsMenuItem(
        'menu_item_photos_new_album',
        $gL10n->get('PHO_CREATE_ALBUM'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_new.php', array('mode' => 'new', 'photo_uuid' => $getPhotoUuid)),
        'fa-plus-circle'
    );

    if ($getPhotoUuid !== '') {
        // show link to edit album
        $page->addPageFunctionsMenuItem(
            'menu_item_photos_edit_album',
            $gL10n->get('PHO_EDIT_ALBUM'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_new.php', array('mode' => 'change', 'photo_uuid' => $getPhotoUuid)),
            'fa-edit'
        );

        // show link to upload photos
        $page->addPageFunctionsMenuItem(
            'menu_item_photos_upload_photo',
            $gL10n->get('PHO_UPLOAD_PHOTOS'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/file_upload.php', array('module' => 'photos', 'uuid' => $getPhotoUuid)),
            'fa-upload'
        );
    }
}

// show link to download photos if enabled
if ($gSettingsManager->getBool('photo_download_enabled') && $photoAlbum->getValue('pho_quantity') > 0) {
    // show link to download photos
    $page->addPageFunctionsMenuItem(
        'menu_item_photos_download',
        $gL10n->get('SYS_DOWNLOAD_ALBUM'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_download.php', array('photo_uuid' => $getPhotoUuid)),
        'fa-download'
    );
}

if ($getPhotoUuid !== '') {
    // show additional album information
    $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin') && strlen($photoAlbum->getValue('pho_end')) > 0) {
        $datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
    }

    // Notice for users with foto edit right that this album is locked
    if ($photoAlbum->getValue('pho_locked') == 1) {
        $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('PHO_ALBUM_NOT_APPROVED').'</div></p>');
    }

    $page->addHtml('
    <p class="lead">
        <p class="font-weight-bold">' . $datePeriod . '</p>
        <p>' . $photoAlbum->countImages() . ' ' . $gL10n->get('PHO_PHOTOGRAPHER') . ' ' . $photoAlbum->getPhotographer() . '</p>');

    if (strlen($photoAlbum->getValue('pho_description')) > 0) {
        $page->addHtml('<p>' . $photoAlbum->getValue('pho_description', 'html') . '</p>');
    }

    $page->addHtml('</p>');
}

// THUMBNAILS
// Only if current album contains images
if ($photoAlbum->getValue('pho_quantity') > 0) {
    $photoThumbnailTable = '';
    $firstPhotoNr        = 1;
    $lastPhotoNr         = $gSettingsManager->getInt('photo_thumbs_page');

    // Open the correct album page when image number has been set
    if ($getPhotoNr > 0) {
        $firstPhotoNr = (round(($getPhotoNr - 1) / $gSettingsManager->getInt('photo_thumbs_page')) * $gSettingsManager->getInt('photo_thumbs_page')) + 1;
        $lastPhotoNr  = $firstPhotoNr + $gSettingsManager->getInt('photo_thumbs_page') - 1;
    }

    // create thumbnail container
    $page->addHtml('<div class="row">');

    for ($actThumbnail = $firstPhotoNr; $actThumbnail <= $lastPhotoNr && $actThumbnail <= $photoAlbum->getValue('pho_quantity'); ++$actThumbnail) {
        if ($actThumbnail <= $photoAlbum->getValue('pho_quantity')) {
            $photoThumbnailTable .= '<div class="col-sm-6 col-lg-4 col-xl-3 admidio-album-thumbnail" id="div_image_'.$actThumbnail.'">';

            // Popup window
            if ((int) $gSettingsManager->get('photo_show_mode') === 0) {
                $photoThumbnailTable .= '
                        <img class="rounded" id="img_'.$actThumbnail.'" style="cursor: pointer"
                            onclick="window.open(\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $actThumbnail, 'photo_uuid' => $getPhotoUuid)).'\',\'msg\', \'height='.($gSettingsManager->getInt('photo_show_height') + 300).', width='.($gSettingsManager->getInt('photo_show_width')+70).',left=162,top=5\')"
                            src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'thumb' => 1)).'" alt="'.$actThumbnail.'" />';
            }

            // Bootstrap modal with lightbox
            elseif ((int) $gSettingsManager->get('photo_show_mode') === 1) {
                $photoThumbnailTable .= '
                        <a data-gallery="admidio-gallery" data-type="image" data-parent=".admidio-album-thumbnail" data-toggle="lightbox" data-title="'.$headline.'"
                            href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height'))).'"><img
                            class="rounded" id="img_'.$actThumbnail.'" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'thumb' => 1)).'" alt="'.$actThumbnail.'" /></a>';
            }

            // Same window
            elseif ((int) $gSettingsManager->get('photo_show_mode') === 2) {
                $photoThumbnailTable .= '
                        <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $actThumbnail, 'photo_uuid' => $getPhotoUuid)).'"><img
                            class="rounded" id="img_'.$actThumbnail.'" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail, 'thumb' => 1)).'" alt="'.$actThumbnail.'" />
                        </a>';
            }

            if ($gCurrentUser->editPhotoRight() || ($gValidLogin && $gSettingsManager->getBool('enable_ecard_module')) || $gSettingsManager->getBool('photo_download_enabled')) {
                $photoThumbnailTable .= '<div id="image_preferences_'.$actThumbnail.'" class="text-center" style="width: ' . $gSettingsManager->getInt('photo_thumbs_scale') . 'px">';
            }


            if ($gValidLogin && $gSettingsManager->getBool('enable_ecard_module')) {
                $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/ecards/ecards.php', array('photo_nr' => $actThumbnail, 'photo_uuid' => $getPhotoUuid, 'show_page' => $getPhotoNr)).'">
                            <i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('PHO_PHOTO_SEND_ECARD').'"></i></a>';
            }

            if ($gSettingsManager->getBool('photo_download_enabled')) {
                // show link to download photo
                $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_download.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $actThumbnail)).'">
                            <i class="fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('SYS_DOWNLOAD_PHOTO').'"></i></a>';
            }

            // buttons for moderation
            if ($gCurrentUser->editPhotoRight()) {
                $photoThumbnailTable .= '
                        <a class="admidio-icon-link admidio-image-rotate" href="javascript:void(0)" data-image="'.$actThumbnail.'" data-direction="right">
                            <i class="fas fa-redo-alt" data-toggle="tooltip" title="'.$gL10n->get('PHO_PHOTO_ROTATE_RIGHT').'"></i></a>
                        <a class="admidio-icon-link admidio-image-rotate"  href="javascript:void(0)"  data-image="'.$actThumbnail.'" data-direction="left"">
                            <i class="fas fa-undo-alt" data-toggle="tooltip" title="'.$gL10n->get('PHO_PHOTO_ROTATE_LEFT').'"></i></a>
                        <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'pho', 'element_id' => 'div_image_'.$actThumbnail,
                            'database_id' => $actThumbnail, 'database_id_2' => $getPhotoUuid)).'">
                            <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
            }

            if ($gCurrentUser->editPhotoRight() || ($gValidLogin && $gSettingsManager->getBool('enable_ecard_module')) || $gSettingsManager->getBool('photo_download_enabled')) {
                $photoThumbnailTable .= '</div>';
            }
            $photoThumbnailTable .= '</div>';
        }
    }

    // the lightbox should be able to go through the whole album, therefore we must
    // integrate links to the photos of the album pages to this page and container but hidden
    if ((int) $gSettingsManager->get('photo_show_mode') === 1) {
        $photoThumbnailTableShown = false;

        for ($hiddenPhotoNr = 1; $hiddenPhotoNr <= $photoAlbum->getValue('pho_quantity'); ++$hiddenPhotoNr) {
            if ($hiddenPhotoNr >= $firstPhotoNr && $hiddenPhotoNr <= $actThumbnail) {
                if (!$photoThumbnailTableShown) {
                    $page->addHtml($photoThumbnailTable);
                    $photoThumbnailTableShown = true;
                }
            } else {
                $page->addHtml('
                    <a class="d-none" data-gallery="admidio-gallery" data-type="image" data-toggle="lightbox" data-title="'.$headline.'"
                        href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $getPhotoUuid, 'photo_nr' => $hiddenPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height'))).'">&nbsp;</a>
                ');
            }
        }
        $page->addHtml('</div>');   // close album-container
    } else {
        // show photos if lightbox is not used
        $photoThumbnailTable .= '</div>';   // close album-container
        $page->addHtml($photoThumbnailTable);
    }

    // show information about user who creates the recordset and changed it
    $page->addHtml(admFuncShowCreateChangeInfoById(
        $photoAlbum->getValue('pho_usr_id_create'),
        $photoAlbum->getValue('pho_timestamp_create'),
        $photoAlbum->getValue('pho_usr_id_change'),
        $photoAlbum->getValue('pho_timestamp_change')
    ));

    // show page navigations through thumbnails
    $page->addHtml(admFuncGeneratePagination(
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $photoAlbum->getValue('pho_uuid'))),
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
          FROM '.TBL_PHOTOS.'
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

if (!$gCurrentUser->editPhotoRight()) {
    $sql .= '
        AND pho_locked = false ';
}

$sql .= '
    ORDER BY pho_begin DESC';

$albumStatement = $gDb->queryPrepared($sql, $queryParams);
$albumList      = $albumStatement->fetchAll();
$albumsCount    = $albumStatement->rowCount();

if ($albumsCount > 0) {
    // if there are photos in the current album and a sub albums exists, then show a separator
    if ($photoAlbum->getValue('pho_quantity') > 0) {
        $page->addHtml('<hr />');
    }

    $childPhotoAlbum = new TablePhotos($gDb);

    $page->addHtml('<div class="row admidio-margin-bottom">');

    for ($x = $getStart; $x <= $getStart + $gSettingsManager->getInt('photo_albums_per_page') - 1 && $x < $albumsCount; ++$x) {
        $htmlLock = '';

        $childPhotoAlbum->clear();
        $childPhotoAlbum->setArray($albumList[$x]);

        // folder of the album
        $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $childPhotoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $childPhotoAlbum->getValue('pho_id');

        // show album if album is not locked, or it has child albums or the user has the photo module edit right
        if ((is_dir($albumFolder) && $childPhotoAlbum->isVisible())
        || $childPhotoAlbum->hasChildAlbums()) {
            // Get random image for preview
            $shuffleImage = $childPhotoAlbum->shuffleImage();

            // album title
            if (is_dir($albumFolder) || $childPhotoAlbum->hasChildAlbums()) {
                $albumTitle = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))).'">'.$childPhotoAlbum->getValue('pho_name').'</a>';
            } else {
                $albumTitle = $childPhotoAlbum->getValue('pho_name');
            }

            $albumDate = $childPhotoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
            if ($childPhotoAlbum->getValue('pho_end') !== $childPhotoAlbum->getValue('pho_begin')) {
                $albumDate .= ' '.$gL10n->get('SYS_DATE_TO').' '.$childPhotoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
            }

            $page->addHtml('
                <div class="admidio-album col-sm-6 col-lg-4 col-xl-3" id="panel_pho_'.$childPhotoAlbum->getValue('pho_uuid').'">
                    <div class="card admidio-card">
                        <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'))).'"><img
                            class="card-img-top" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('photo_uuid' => $shuffleImage['shuffle_pho_uuid'], 'photo_nr' => $shuffleImage['shuffle_img_nr'], 'thumb' => 1)).'" alt="'.$gL10n->get('SYS_PHOTOS').'" /></a>
                        <div class="card-body">
                            <h5 class="card-title">'.$albumTitle);
            // if user has admin rights for photo module then show some functions
            if ($gCurrentUser->editPhotoRight()) {
                if ((bool) $childPhotoAlbum->getValue('pho_locked') === false) {
                    $htmlLock = '<a class="dropdown-item btn admidio-album-lock" href="javascript:void(0)" data-id="'.$childPhotoAlbum->getValue('pho_uuid').'" data-mode="lock">
                                            <i class="fas fa-lock" data-toggle="tooltip"></i> '.$gL10n->get('PHO_ALBUM_LOCK').'</a>';
                }

                $page->addHtml('
                                    <div class="dropdown float-right">
                                        <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_new.php', array('photo_uuid' => $childPhotoAlbum->getValue('pho_uuid'), 'mode' => 'change')).'">
                                                <i class="fas fa-edit" data-toggle="tooltip"></i> '.$gL10n->get('PHO_EDIT_ALBUM').'</a>
                                            ' .$htmlLock . '
                                            <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'pho_album', 'element_id' => 'panel_pho_' . $childPhotoAlbum->getValue('pho_uuid'),
                                            'name' => $childPhotoAlbum->getValue('pho_name'), 'database_id' => $childPhotoAlbum->getValue('pho_uuid'))).'">
                                                <i class="fas fa-trash-alt" data-toggle="tooltip"></i> '.$gL10n->get('PHO_ALBUM_DELETE').'</a>
                                        </div>
                                    </div>');
            }
            $page->addHtml('</h5>

                            <p class="card-text">' . $albumDate . '</p>');

            if (strlen($childPhotoAlbum->getValue('pho_description')) > 0) {
                $albumDescription = $childPhotoAlbum->getValue('pho_description', 'html');

                if (strlen($albumDescription) > 200) {
                    // read first 200 chars of text, then search for last space and cut the text there. After that add a "more" link
                    $textPrev = substr($albumDescription, 0, 200);
                    $maxPosPrev = strrpos($textPrev, ' ');
                    $albumDescription = substr($textPrev, 0, $maxPosPrev).
                                        ' <span class="collapse" id="viewdetails'.$childPhotoAlbum->getValue('pho_uuid').'">'.substr($albumDescription, $maxPosPrev).'.
                                        </span> <a class="admidio-icon-link" data-toggle="collapse" data-target="#viewdetails'.$childPhotoAlbum->getValue('uuid').'"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';
                }

                $page->addHtml('<p class="card-text">' . $albumDescription . '</p>');
            }

            $page->addHtml('<p class="card-text">' . $childPhotoAlbum->countImages() . ' ' . $gL10n->get('PHO_PHOTOGRAPHER') . ' ' . $childPhotoAlbum->getPhotographer() . '</p>');

            // Notice for users with foto edit rights that the folder of the album doesn't exist
            if (!is_dir($albumFolder) && !$childPhotoAlbum->hasChildAlbums() && $gCurrentUser->editPhotoRight()) {
                $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('PHO_FOLDER_NOT_FOUND').'</div></p>');
            }

            // Notice for users with foto edit right that this album is locked
            if ($childPhotoAlbum->getValue('pho_locked') == 1) {
                $page->addHtml('<p class="card-text"><div class="alert alert-warning alert-small" role="alert"><i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('PHO_ALBUM_NOT_APPROVED').'</div></p>');
            }

            if ($gCurrentUser->editPhotoRight() && $childPhotoAlbum->getValue('pho_locked') == 1) {
                $page->addHtml('<button class="btn btn-primary admidio-album-lock" data-id="'.$childPhotoAlbum->getValue('pho_uuid').'" data-mode="unlock">'.$gL10n->get('PHO_ALBUM_UNLOCK').'</button>');
            }

            $page->addHtml('</div>
                    </div>
                </div>
            ');
        }//Ende wenn Ordner existiert
    }//for

    $page->addHtml('</div>');
}

// Empty album, if the album contains neither photos nor sub-folders
if ($albumsCount === 0 && ($photoAlbum->getValue('pho_quantity') == 0 || strlen($photoAlbum->getValue('pho_quantity')) === 0)) {  // alle vorhandenen Albumen werden ignoriert
    $page->addHtml($gL10n->get('PHO_NO_ALBUM_CONTENT'));
}

// If necessary show links to navigate to next and previous albums of the query
$baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $getPhotoUuid));
$page->addHtml(admFuncGeneratePagination($baseUrl, $albumsCount, $gSettingsManager->getInt('photo_albums_per_page'), $getStart));

// show html of complete page
$page->show();
