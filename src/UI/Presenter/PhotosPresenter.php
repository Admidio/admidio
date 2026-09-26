<?php

namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Photos\Entity\Album;
use Admidio\Users\Entity\User;

class PhotosPresenter extends PagePresenter
{
    public function createAlbums(string $photoUuid, int $start, int $photoNumber): void
    {
        global $gDb, $gCurrentOrgId, $gCurrentOrganization, $gCurrentSession, $gCurrentUser,
               $gL10n, $gSettingsManager, $gValidLogin;

        $photoAlbum = new Album($gDb);
        if ($photoUuid !== '') {
            if (!$photoAlbum->readDataByUuid($photoUuid) || !$photoAlbum->isVisible()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }
        $_SESSION['photo_album'] = $photoAlbum;

        $headline = $photoUuid === '' ? $gL10n->get('SYS_PHOTO_ALBUMS') : $photoAlbum->getValue('pho_name');
        $this->setHtmlID('adm_photos');
        $this->setHeadline($headline);
        $this->setContentFullWidth();

        $sql = 'SELECT *
                  FROM ' . TBL_PHOTOS . '
                 WHERE pho_org_id = ?';
        $queryParams = array($gCurrentOrgId);
        if ($photoUuid === '') {
            $sql .= ' AND pho_pho_id_parent IS NULL';
        } else {
            $sql .= ' AND pho_pho_id_parent = ?';
            $queryParams[] = $photoAlbum->getValue('pho_id');
        }
        if (!$gCurrentUser->isAdministratorPhotos()) {
            $sql .= ' AND pho_locked = false';
        }
        $sql .= ' ORDER BY pho_begin DESC';
        $albumRecords = $gDb->queryPrepared($sql, $queryParams)->fetchAll();
        $albumsCount = count($albumRecords);

        if ($gSettingsManager->getBool('enable_rss')) {
            $this->addRssFile(
                ADMIDIO_URL . '/rss/photos.php?organization=' . $gCurrentOrganization->getValue('org_shortname'),
                $gL10n->get('SYS_RSS_FEED_FOR_VAR', array(
                    $gCurrentOrganization->getValue('org_longname') . ' - ' . $gL10n->get('SYS_PHOTO_ALBUMS')
                ))
            );
        }

        if ($photoAlbum->isEditable()) {
            $this->addJavascript('lightbox.option({
                "albumLabel": "' . strtr($gL10n->get('SYS_PHOTO_X_OF_Y'), array('#VAR1#' => '%1', '#VAR2#' => '%2')) . '"
            });
            $(".admidio-image-rotate").click(function() {
                const imageNr = $(this).data("image");
                $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/photos.php?mode=photo_rotate&photo_uuid=' . $photoUuid . '&photo_nr=" + imageNr + "&direction=" + $(this).data("direction"),
                    {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}, function(data) {
                        if (data === "done") {
                            $("#img_" + imageNr).attr("src", "' . ADMIDIO_URL . FOLDER_MODULES . '/photos.php?mode=photo_show&photo_uuid=' . $photoUuid . '&thumb=1&photo_nr=" + imageNr + "&rand=" + Math.random());
                        } else {
                            messageBox(data, "' . $gL10n->get('SYS_ERROR') . '", "error");
                        }
                    }
                );
            });
            $(".admidio-album-lock").click(function() {
                $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/photos.php?mode=album_" + $(this).data("mode") + "&photo_uuid=" + $(this).data("id"),
                    {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}, function(data) {
                        if (data === "done") { location.reload(); }
                        else { messageBox(data, "' . $gL10n->get('SYS_ERROR') . '", "error"); }
                    }
                );
            });', true);
        }

        $showMode = $gSettingsManager->getInt('photo_show_mode');
        if ($showMode === 1) {
            $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/css/lightbox.css');
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/js/lightbox.js');
        }
        if ($photoNumber > 0) {
            $this->addJavascript('$("#img_' . $photoNumber . '").trigger("click");', true);
        }

        if ($gCurrentUser->isAdministratorPhotos()) {
            $this->addPageFunctionsMenuItem('menu_item_photos_new_album', $gL10n->get('SYS_CREATE_ALBUM'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array(
                    'mode' => 'album_edit', 'parent_photo_uuid' => $photoUuid
                )), 'bi-plus-circle-fill');
            if ($photoUuid !== '') {
                $this->addPageFunctionsMenuItem('menu_item_photos_edit_album', $gL10n->get('SYS_EDIT_ALBUM'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array(
                        'mode' => 'album_edit', 'photo_uuid' => $photoUuid
                    )), 'bi-pencil-square');
                $this->addPageFunctionsMenuItem('menu_item_photos_upload_photo', $gL10n->get('SYS_UPLOAD_PHOTOS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/file_upload.php', array(
                        'module' => 'photos', 'uuid' => $photoUuid
                    )), 'bi-upload');
            }
        }
        if ($gSettingsManager->getBool('photo_download_enabled') && $gValidLogin && $photoUuid !== ''
            && ((int)$photoAlbum->getValue('pho_quantity') > 0 || $albumsCount > 0)) {
            $this->addPageFunctionsMenuItem('menu_item_photos_download', $gL10n->get('SYS_DOWNLOAD_ALBUM'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array(
                    'mode' => 'photo_download', 'photo_uuid' => $photoUuid
                )), 'bi-download');
        }
        ChangelogService::displayHistoryButton($this, 'photos', 'photos', $photoUuid !== '', array('uuid' => $photoUuid));

        $albumInfo = null;
        if ($photoUuid !== '') {
            $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
            if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin')
                && $photoAlbum->getValue('pho_end') !== '') {
                $datePeriod .= ' ' . $gL10n->get('SYS_DATE_TO') . ' '
                    . $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
            }
            $albumInfo = array(
                'datePeriod' => $datePeriod,
                'photoCount' => $photoAlbum->countImages(),
                'photographer' => $photoAlbum->getPhotographer(),
                'description' => $photoAlbum->getValue('pho_description', 'html'),
                'locked' => (bool)$photoAlbum->getValue('pho_locked'),
                'createdName' => $photoAlbum->getNameOfCreatingUser(),
                'createdTimestamp' => $photoAlbum->getValue('pho_timestamp_create'),
                'editedName' => $photoAlbum->getNameOfLastEditingUser(),
                'editedTimestamp' => $photoAlbum->getValue('pho_timestamp_change')
            );
        }

        $photos = array();
        $hiddenPhotos = array();
        $quantity = (int)$photoAlbum->getValue('pho_quantity');
        if ($quantity > 0) {
            $perPage = $gSettingsManager->getInt('photo_thumbs_page');
            $firstPhoto = $photoNumber > 0 ? ((int)floor(($photoNumber - 1) / $perPage) * $perPage) + 1 : 1;
            $lastPhoto = min($quantity, $firstPhoto + $perPage - 1);
            for ($number = $firstPhoto; $number <= $lastPhoto; ++$number) {
                $photos[] = array(
                    'number' => $number,
                    'imageUrl' => $this->photoUrl('photo_show', $photoUuid, $number, array('thumb' => 1)),
                    'showUrl' => $showMode === 1
                        ? $this->photoUrl('photo_show', $photoUuid, $number, array(
                            'max_width' => $gSettingsManager->getInt('photo_show_width'),
                            'max_height' => $gSettingsManager->getInt('photo_show_height')
                        ))
                        : $this->photoUrl('photo_present', $photoUuid, $number),
                    'ecardUrl' => $this->photoUrl('ecard', $photoUuid, $number),
                    'downloadUrl' => $this->photoUrl('photo_download', $photoUuid, $number),
                    'deleteUrl' => $this->photoUrl('photo_delete', $photoUuid, $number),
                );
            }
            if ($showMode === 1) {
                for ($number = 1; $number <= $quantity; ++$number) {
                    if ($number < $firstPhoto || $number > $lastPhoto) {
                        $hiddenPhotos[] = $this->photoUrl('photo_show', $photoUuid, $number, array(
                            'max_width' => $gSettingsManager->getInt('photo_show_width'),
                            'max_height' => $gSettingsManager->getInt('photo_show_height')
                        ));
                    }
                }
            }
        }

        $albums = array();
        $childAlbum = new Album($gDb);
        $perPage = $gSettingsManager->getInt('photo_albums_per_page');
        foreach (array_slice($albumRecords, $start, $perPage) as $record) {
            $childAlbum->clear();
            $childAlbum->setArray($record);
            $folder = ADMIDIO_PATH . FOLDER_DATA . '/photos/'
                . $childAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $childAlbum->getValue('pho_id');
            $hasChildren = $childAlbum->hasChildAlbums();
            if ((!is_dir($folder) || !$childAlbum->isVisible()) && !$hasChildren) {
                continue;
            }
            $uuid = $childAlbum->getValue('pho_uuid');
            $shuffleImage = $childAlbum->shuffleImage();
            $date = $childAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
            if ($childAlbum->getValue('pho_end') !== $childAlbum->getValue('pho_begin')) {
                $date .= ' ' . $gL10n->get('SYS_DATE_TO') . ' '
                    . $childAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
            }
            $description = $childAlbum->getValue('pho_description', 'html');
            if (strlen($description) > 200) {
                $preview = substr($description, 0, 200);
                $cut = strrpos($preview, ' ');
                if ($cut !== false) {
                    $description = substr($preview, 0, $cut)
                        . ' <span class="collapse" id="viewdetails-' . $uuid . '">'
                        . substr($description, $cut) . '.</span> '
                        . '<a class="admidio-icon-link" data-bs-toggle="collapse" data-bs-target="#viewdetails-'
                        . $uuid . '">»</a>';
                }
            }
            $albums[] = array(
                'uuid' => $uuid,
                'name' => $childAlbum->getValue('pho_name'),
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('photo_uuid' => $uuid)),
                'imageUrl' => $this->photoUrl('photo_show', (string)($shuffleImage['shuffle_pho_uuid'] ?? ''), (int)$shuffleImage['shuffle_img_nr'], array('thumb' => 1)),
                'editUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('mode' => 'album_edit', 'photo_uuid' => $uuid)),
                'deleteUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('mode' => 'album_delete', 'photo_uuid' => $uuid)),
                'date' => $date,
                'description' => $description,
                'photoCount' => $childAlbum->countImages(),
                'locked' => (bool)$childAlbum->getValue('pho_locked'),
                'folderMissing' => !is_dir($folder) && !$hasChildren,
                'editable' => $gCurrentUser->isAdministratorPhotos()
            );
        }

        $this->smarty->assign('albumInfo', $albumInfo);
        $this->smarty->assign('albums', $albums);
        $this->smarty->assign('albumsCount', $albumsCount);
        $this->smarty->assign('photos', $photos);
        $this->smarty->assign('hiddenPhotos', $hiddenPhotos);
        $this->smarty->assign('showMode', $showMode);
        $this->smarty->assign('isPhotoAdministrator', $gCurrentUser->isAdministratorPhotos());
        $this->smarty->assign('showPhotoActions', $gValidLogin && (
            $gCurrentUser->isAdministratorPhotos() || $gSettingsManager->getBool('photo_ecard_enabled')
            || $gSettingsManager->getBool('photo_download_enabled')
        ));
        $this->smarty->assign('ecardEnabled', $gSettingsManager->getBool('photo_ecard_enabled'));
        $this->smarty->assign('downloadEnabled', $gSettingsManager->getBool('photo_download_enabled'));
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('photoPagination', $quantity > 0 ? admFuncGeneratePagination(
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('photo_uuid' => $photoUuid)),
            $quantity, $gSettingsManager->getInt('photo_thumbs_page'), $photoNumber, true, 'photo_nr'
        ) : '');
        $this->smarty->assign('albumPagination', admFuncGeneratePagination(
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('photo_uuid' => $photoUuid)),
            $albumsCount, $perPage, $start
        ));
        $this->addHtmlByTemplate('modules/photos.albums.tpl');
    }

    public function createAlbumEditForm(string $photoUuid, string $parentPhotoUuid): void
    {
        global $gDb, $gCurrentOrgId, $gCurrentSession, $gL10n;

        $photoAlbum = new Album($gDb);
        if ($photoUuid !== '') {
            if (!$photoAlbum->readDataByUuid($photoUuid)) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $parentAlbum = new Album($gDb, (int)$photoAlbum->getValue('pho_pho_id_parent'));
            $parentPhotoUuid = $parentAlbum->getValue('pho_uuid');
        }
        if (!$photoAlbum->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->setHtmlID('adm_photos_album_edit');
        $this->setHeadline($gL10n->get($photoUuid === '' ? 'SYS_CREATE_ALBUM' : 'SYS_EDIT_ALBUM'));
        ChangelogService::displayHistoryButton($this, 'photos', 'photos', $photoUuid !== '', array('uuid' => $photoUuid));

        $albumOptions = array('ALL' => $gL10n->get('SYS_PHOTO_ALBUMS'));
        $this->addAlbumOptions($albumOptions, null, '', (int)$photoAlbum->getValue('pho_id'), $gDb, $gCurrentOrgId);
        $form = new FormPresenter('adm_photos_edit_form', 'modules/photos.album.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array(
                'mode' => 'album_save', 'photo_uuid' => $photoUuid
            )), $this);
        $form->addInput('pho_name', $gL10n->get('SYS_ALBUM'), $photoAlbum->getValue('pho_name'),
            array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 50));
        $form->addSelectBox('parent_album_uuid', $gL10n->get('SYS_PARENT_ALBUM'), $albumOptions, array(
            'property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $parentPhotoUuid,
            'showContextDependentFirstEntry' => false,
            'helpTextId' => $gL10n->get('SYS_PARENT_ALBUM_DESC', array('SYS_PHOTO_ALBUMS'))
        ));
        $form->addInput('pho_begin', $gL10n->get('SYS_START'), $photoAlbum->getValue('pho_begin'),
            array('property' => FormPresenter::FIELD_REQUIRED, 'type' => 'date', 'maxLength' => 10));
        $form->addInput('pho_end', $gL10n->get('SYS_END'), $photoAlbum->getValue('pho_end'),
            array('type' => 'date', 'maxLength' => 10));
        $form->addInput('pho_photographers', $gL10n->get('SYS_PHOTOS_BY'), $photoAlbum->getValue('pho_photographers'),
            array('maxLength' => 100));
        $form->addMultilineTextInput('pho_description', $gL10n->get('SYS_DESCRIPTION'),
            $photoAlbum->getValue('pho_description'), 6, array('maxLength' => 4000));
        $form->addCheckbox('pho_locked', $gL10n->get('SYS_LOCK_ALBUM'), (bool)$photoAlbum->getValue('pho_locked'),
            array('helpTextId' => 'SYS_LOCK_ALBUM_DESC'));
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3'));
        $this->assignSmartyVariable('userCreatedName', $photoAlbum->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $photoAlbum->getValue('pho_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $photoAlbum->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $photoAlbum->getValue('pho_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    public function createPhotoPresenter(string $photoUuid, int $photoNumber): void
    {
        global $gDb, $gL10n, $gSettingsManager;

        $photoAlbum = new Album($gDb);
        if (!$photoAlbum->readDataByUuid($photoUuid) || !$photoAlbum->isVisible()
            || $photoNumber < 1 || $photoNumber > (int)$photoAlbum->getValue('pho_quantity')) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $_SESSION['photo_album'] = $photoAlbum;
        $this->setHtmlID('adm_photos_presenter');
        $this->setHeadline($photoAlbum->getValue('pho_name'));
        $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
        if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin')
            && $photoAlbum->getValue('pho_end') !== '') {
            $datePeriod .= ' ' . $gL10n->get('SYS_DATE_TO') . ' '
                . $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
        }
        $previous = $photoNumber > 1 ? $this->photoUrl('photo_present', $photoUuid, $photoNumber - 1) : '';
        $next = $photoNumber < (int)$photoAlbum->getValue('pho_quantity')
            ? $this->photoUrl('photo_present', $photoUuid, $photoNumber + 1) : '';
        $this->smarty->assign('datePeriod', $datePeriod);
        $this->smarty->assign('photographer', $photoAlbum->getPhotographer());
        $this->smarty->assign('photoUrl', $this->photoUrl('photo_show', $photoUuid, $photoNumber, array(
            'max_width' => $gSettingsManager->getInt('photo_show_width'),
            'max_height' => $gSettingsManager->getInt('photo_show_height')
        )));
        $this->smarty->assign('previousUrl', $previous);
        $this->smarty->assign('nextUrl', $next);
        $this->addHtmlByTemplate('modules/photos.presenter.tpl');
    }

    public function createEcardForm(string $photoUuid, int $photoNumber, string $userUuid): void
    {
        global $gDb, $gCurrentSession, $gCurrentUser, $gL10n, $gProfileFields, $gSettingsManager, $gValidLogin;

        $photoAlbum = new Album($gDb);
        if (!$photoAlbum->readDataByUuid($photoUuid) || !$photoAlbum->isVisible()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $_SESSION['photo_album'] = $photoAlbum;
        if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '') {
            throw new Exception('SYS_CURRENT_USER_NO_EMAIL', array(
                '<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php">', '</a>'
            ));
        }
        if ($userUuid !== '') {
            $user = new User($gDb, $gProfileFields);
            $user->readDataByUuid($userUuid);
            if ((!$gCurrentUser->isAdministratorUsers() && !isMember((int)$user->getValue('usr_id')))
                || $user->getValue('usr_id') === '') {
                throw new Exception('SYS_USER_ID_NOT_FOUND');
            }
            if (!StringUtils::strValidCharacters($user->getValue('EMAIL'), 'email')) {
                throw new Exception('SYS_USER_NO_EMAIL', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
            }
        }

        $this->setHtmlID('adm_ecards');
        $this->setHeadline($gL10n->get('SYS_SEND_GREETING_CARD'));
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/css/lightbox.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/js/lightbox.js');
        $this->addJavascript('$("#adm_button_ecard_preview").click(function(event) {
            event.preventDefault();
            $("#adm_ecard_send_form input[id=\'submit_action\']").val("preview");
            $("#adm_ecard_send_form textarea[name=\'ecard_message\']").text(editor.getData());
            $.post({
                data: $("#adm_ecard_send_form").serialize(),
                url: "' . ADMIDIO_URL . FOLDER_MODULES . '/photos.php?mode=ecard_preview",
                success: function(response) {
                    $(".modal-dialog").attr("class", "modal-dialog modal-lg");
                    $(".modal-content").html(response);
                    new bootstrap.Modal($("#adm_modal"), {}).show();
                }
            });
            return false;
        });', true);

        $templates = array_keys(FileSystemUtils::getDirectoryContent(
            ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)
        ));
        if (count($templates) === 0) {
            throw new Exception('SYS_TEMPLATE_FOLDER_OPEN');
        }
        $templateOptions = array();
        foreach ($templates as $templateName) {
            $templateOptions[$templateName] = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
        }

        $recipients = array();
        $writeRoleUuids = $gCurrentUser->getRolesWriteMails();
        if (count($writeRoleUuids) > 0) {
            $sql = 'SELECT rol_uuid, rol_name FROM ' . TBL_ROLES . '
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                    WHERE rol_uuid IN (' . Database::getQmForValues($writeRoleUuids) . ')
                      AND cat_name_intern <> \'EVENTS\' ORDER BY rol_name';
            foreach ($gDb->queryPrepared($sql, $writeRoleUuids) as $row) {
                $recipients[] = array('groupID: ' . $row['rol_uuid'], $row['rol_name'], $gL10n->get('SYS_ROLES'));
            }
        }
        $visibleRoleUuids = array_values(array_unique(array_merge(
            $writeRoleUuids, $gCurrentUser->getRolesViewMemberships()
        )));
        if (count($visibleRoleUuids) > 0) {
            $sql = 'SELECT DISTINCT usr_uuid, first_name.usd_value AS first_name, last_name.usd_value AS last_name
                      FROM ' . TBL_MEMBERS . '
                INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_USERS . ' ON usr_id = mem_usr_id
                 LEFT JOIN ' . TBL_USER_DATA . ' AS last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ?
                 LEFT JOIN ' . TBL_USER_DATA . ' AS first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ?
                     WHERE usr_valid = true AND mem_begin <= ? AND mem_end > ?
                       AND rol_uuid IN (' . Database::getQmForValues($visibleRoleUuids) . ')
                  GROUP BY usr_id, first_name.usd_value, last_name.usd_value ORDER BY last_name, first_name';
            $params = array_merge(array(
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), DATE_NOW, DATE_NOW
            ), $visibleRoleUuids);
            foreach ($gDb->queryPrepared($sql, $params) as $row) {
                $recipients[] = array($row['usr_uuid'], $row['last_name'] . ', ' . $row['first_name'], $gL10n->get('SYS_CONTACTS'));
            }
        }

        $form = new FormPresenter('adm_ecard_send_form', 'modules/photos.ecard.send.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array('mode' => 'ecard_send')), $this);
        $form->addInput('submit_action', '', '', array('property' => FormPresenter::FIELD_HIDDEN));
        $form->addInput('photo_uuid', '', $photoUuid, array('type' => 'uuid', 'property' => FormPresenter::FIELD_HIDDEN));
        $form->addInput('photo_nr', '', $photoNumber, array('type' => 'number', 'property' => FormPresenter::FIELD_HIDDEN));
        $form->addSelectBox('ecard_template', $gL10n->get('SYS_TEMPLATE'), $templateOptions, array(
            'defaultValue' => $gSettingsManager->getString('photo_ecard_template'),
            'property' => FormPresenter::FIELD_REQUIRED, 'showContextDependentFirstEntry' => false
        ));
        $form->addSelectBox('ecard_recipients', $gL10n->get('SYS_TO'), $recipients,
            array('property' => FormPresenter::FIELD_REQUIRED, 'multiselect' => true));
        $form->addInput('name_from', $gL10n->get('SYS_YOUR_NAME'),
            $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'),
            array('maxLength' => 50, 'property' => FormPresenter::FIELD_DISABLED));
        $form->addInput('mail_from', $gL10n->get('SYS_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'),
            array('type' => 'email', 'maxLength' => 50, 'property' => FormPresenter::FIELD_DISABLED));
        $form->addEditor('ecard_message', '', '',
            array('property' => FormPresenter::FIELD_REQUIRED, 'toolbar' => 'AdmidioComments'));
        $form->addButton('adm_button_ecard_preview', $gL10n->get('SYS_PREVIEW'), array('icon' => 'bi-eye-fill'));
        $form->addSubmitButton('adm_button_ecard_submit', $gL10n->get('SYS_SEND'), array('icon' => 'bi-envelope-fill'));
        $this->assignSmartyVariable('photoPreviewUrl', $this->photoUrl('photo_show', $photoUuid, $photoNumber, array(
            'max_width' => $gSettingsManager->getInt('photo_show_width'),
            'max_height' => $gSettingsManager->getInt('photo_show_height')
        )));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /** @param array<string,string> $options */
    private function addAlbumOptions(
        array &$options,
        ?int $parentId,
        string $indent,
        int $excludedAlbumId,
        Database $database,
        int $organizationId
    ): void {
        $sql = 'SELECT * FROM ' . TBL_PHOTOS . ' WHERE pho_id <> ? AND pho_org_id = ? AND ';
        $params = array($excludedAlbumId, $organizationId);
        if ($parentId === null) {
            $sql .= 'pho_pho_id_parent IS NULL';
        } else {
            $sql .= 'pho_pho_id_parent = ?';
            $params[] = $parentId;
        }
        $album = new Album($database);
        foreach ($database->queryPrepared($sql, $params) as $row) {
            $album->clear();
            $album->setArray($row);
            $options[$album->getValue('pho_uuid')] = $indent . '&#151; '
                . $album->getValue('pho_name') . '&nbsp;(' . $album->getValue('pho_begin', 'Y') . ')';
            $this->addAlbumOptions($options, (int)$album->getValue('pho_id'), $indent . '&nbsp;&nbsp;&nbsp;',
                $excludedAlbumId, $database, $organizationId);
        }
    }

    /** @param array<string,int|string> $parameters */
    private function photoUrl(string $mode, string $photoUuid, int $photoNumber, array $parameters = array()): string
    {
        return SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos.php', array_merge(array(
            'mode' => $mode, 'photo_uuid' => $photoUuid, 'photo_nr' => $photoNumber
        ), $parameters));
    }
}
