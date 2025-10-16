<?php
/**
 ***********************************************************************************************
 * Create and edit photo alben
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 * photo_uuid : UUID of the album that should be edited
 * parent_photo_uuid: UUID of the parent album in which the new album should be created
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\Entity\Album;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid');
    $getParentPhotoUuid = admFuncVariableIsValid($_GET, 'parent_photo_uuid', 'uuid');

    $photoAlbumsArray = array('ALL' => $gL10n->get('SYS_PHOTO_ALBUMS'));

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create a photo album object
    $photoAlbum = new Album($gDb);

    if ($getPhotoUuid === '') {
        $headline = $gL10n->get('SYS_CREATE_ALBUM');
    } else {
        $headline = $gL10n->get('SYS_EDIT_ALBUM');
        $photoAlbum->readDataByUuid($getPhotoUuid);
        $parentAlbum = new Album($gDb, (int)$photoAlbum->getValue('pho_pho_id_parent'));
        $getParentPhotoUuid = $parentAlbum->getValue('pho_uuid');
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // check if the user is allowed to edit this photo album
    if (!$photoAlbum->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    /**
     * Read the album structure to an array that could be used for a select box.
     * @param int $parentId
     * @param string $vorschub
     * @param int $currentAlbumPhoId
     */
    function subfolder(int $parentId, string $vorschub, int $currentAlbumPhoId): void
    {
        global $gDb, $gCurrentOrgId, $photoAlbumsArray;

        $vorschub .= '&nbsp;&nbsp;&nbsp;';
        $sqlConditionParentId = '';
        $parentPhotoAlbum = new Album($gDb);

        $queryParams = array($currentAlbumPhoId, $gCurrentOrgId);

        // read all sub albums of the parent album
        if ($parentId > 0) {
            $sqlConditionParentId .= ' AND pho_pho_id_parent = ? -- $parentId';
            $queryParams[] = $parentId;
        } else {
            $sqlConditionParentId .= ' AND pho_pho_id_parent IS NULL';
        }

        $sql = 'SELECT *
              FROM ' . TBL_PHOTOS . '
             WHERE pho_id    <> ? -- $photoAlbum->getValue(\'pho_id\')
               AND pho_org_id = ? -- $gCurrentOrgId
                   ' . $sqlConditionParentId;
        $childStatement = $gDb->queryPrepared($sql, $queryParams);

        while ($admPhotoChild = $childStatement->fetch()) {
            $parentPhotoAlbum->clear();
            $parentPhotoAlbum->setArray($admPhotoChild);

            // add entry to an array of all photo albums
            $photoAlbumsArray[$parentPhotoAlbum->getValue('pho_uuid')] =
                $vorschub . '&#151; ' . $parentPhotoAlbum->getValue('pho_name') . '&nbsp(' . $parentPhotoAlbum->getValue('pho_begin', 'Y') . ')';

            subfolder((int)$parentPhotoAlbum->getValue('pho_id'), $vorschub, $currentAlbumPhoId);
        }//while
    }//function

    // create HTML page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-photo-album-edit', $headline);

    ChangelogService::displayHistoryButton($page, 'photos', 'photos', !empty($getPhotoUuid), array('uuid' => $getPhotoUuid));

    // show form
    $form = new FormPresenter(
        'adm_photos_edit_form',
        'modules/photos.album.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_album_function.php', array('photo_uuid' => $getPhotoUuid, 'mode' => 'edit')),
        $page
    );
    $form->addInput(
        'pho_name',
        $gL10n->get('SYS_ALBUM'),
        $photoAlbum->getValue('pho_name'),
        array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 50)
    );
    subfolder(0, '', $photoAlbum->getValue('pho_id'));
    $form->addSelectBox(
        'parent_album_uuid',
        $gL10n->get('SYS_PARENT_ALBUM'),
        $photoAlbumsArray,
        array(
            'property' => FormPresenter::FIELD_REQUIRED,
            'defaultValue' => $getParentPhotoUuid,
            'showContextDependentFirstEntry' => false,
            'helpTextId' => $gL10n->get('SYS_PARENT_ALBUM_DESC', array('SYS_PHOTO_ALBUMS'))
        )
    );
    $form->addInput(
        'pho_begin',
        $gL10n->get('SYS_START'),
        $photoAlbum->getValue('pho_begin'),
        array('property' => FormPresenter::FIELD_REQUIRED, 'type' => 'date', 'maxLength' => 10)
    );
    $form->addInput(
        'pho_end',
        $gL10n->get('SYS_END'),
        $photoAlbum->getValue('pho_end'),
        array('type' => 'date', 'maxLength' => 10)
    );
    $form->addInput(
        'pho_photographers',
        $gL10n->get('SYS_PHOTOS_BY'),
        $photoAlbum->getValue('pho_photographers'),
        array('maxLength' => 100)
    );
    $form->addMultilineTextInput(
        'pho_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $photoAlbum->getValue('pho_description'),
        6,
        array('maxLength' => 4000)
    );
    $form->addCheckbox(
        'pho_locked',
        $gL10n->get('SYS_LOCK_ALBUM'),
        (bool)$photoAlbum->getValue('pho_locked'),
        array('helpTextId' => 'SYS_LOCK_ALBUM_DESC')
    );
    $form->addSubmitButton(
        'adm_button_save',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
    );

    $page->assignSmartyVariable('userCreatedName', $photoAlbum->getNameOfCreatingUser());
    $page->assignSmartyVariable('userCreatedTimestamp', $photoAlbum->getValue('pho_timestamp_create'));
    $page->assignSmartyVariable('lastUserEditedName', $photoAlbum->getNameOfLastEditingUser());
    $page->assignSmartyVariable('lastUserEditedTimestamp', $photoAlbum->getValue('pho_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
