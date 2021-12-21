<?php
/**
 ***********************************************************************************************
 * Create and edit photo alben
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 * photo_uuid : UUID of the album that should be edited
 * mode       : - new (new album)
 *              - change (edit album)
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'string');
$getMode      = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('new', 'change')));

$photoAlbumsArray = array(0 => $gL10n->get('PHO_PHOTO_ALBUMS'));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$headline = '';
if ($getMode === 'new') {
    $headline = $gL10n->get('PHO_CREATE_ALBUM');
} elseif ($getMode === 'change') {
    $headline = $gL10n->get('PHO_EDIT_ALBUM');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create photo album object
$photoAlbum = new TablePhotos($gDb);

if ($getMode === 'change') {
    $photoAlbum->readDataByUuid($getPhotoUuid);
}

// check if the user is allowed to edit this photo album
if (!$photoAlbum->isEditable()) {
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

if (isset($_SESSION['photo_album_request'])) {
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $photoAlbum->setArray($_SESSION['photo_album_request']);
    unset($_SESSION['photo_album_request']);
}

/**
 * die Albenstruktur fuer eine Auswahlbox darstellen und das aktuelle Album vorauswählen
 * @param int         $parentId
 * @param string      $vorschub
 * @param TablePhotos $photoAlbum
 * @param int         $phoId
 */
function subfolder($parentId, $vorschub, TablePhotos $photoAlbum, $phoId)
{
    global $gDb, $gCurrentOrgId, $photoAlbumsArray;

    $vorschub .= '&nbsp;&nbsp;&nbsp;';
    $sqlConditionParentId = '';
    $parentPhotoAlbum = new TablePhotos($gDb);

    $queryParams = array((int) $photoAlbum->getValue('pho_id'), $gCurrentOrgId);
    // Erfassen des auszugebenden Albums
    if ($parentId > 0) {
        $sqlConditionParentId .= ' AND pho_pho_id_parent = ? -- $parentId';
        $queryParams[] = $parentId;
    } else {
        $sqlConditionParentId .= ' AND pho_pho_id_parent IS NULL';
    }

    $sql = 'SELECT *
              FROM '.TBL_PHOTOS.'
             WHERE pho_id    <> ? -- $photoAlbum->getValue(\'pho_id\')
               AND pho_org_id = ? -- $gCurrentOrgId
                   '.$sqlConditionParentId;
    $childStatement = $gDb->queryPrepared($sql, $queryParams);

    while ($admPhotoChild = $childStatement->fetch()) {
        $parentPhotoAlbum->clear();
        $parentPhotoAlbum->setArray($admPhotoChild);

        // add entry to array of all photo albums
        $photoAlbumsArray[(int) $parentPhotoAlbum->getValue('pho_id')] =
            $vorschub.'&#151; '.$parentPhotoAlbum->getValue('pho_name').'&nbsp('.$parentPhotoAlbum->getValue('pho_begin', 'Y').')';

        subfolder((int) $parentPhotoAlbum->getValue('pho_id'), $vorschub, $photoAlbum, $phoId);
    }//while
}//function

// create html page object
$page = new HtmlPage('admidio-photo-album-edit', $headline);

if ($getMode === 'new') {
    $parentAlbumId = $photoAlbum->getValue('pho_id');
} else {
    $parentAlbumId = $photoAlbum->getValue('pho_pho_id_parent');
}

// show form
$form = new HtmlForm('photo_album_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_function.php', array('photo_uuid' => $getPhotoUuid, 'mode' => $getMode)), $page);
$form->addInput(
    'pho_name',
    $gL10n->get('PHO_ALBUM'),
    $photoAlbum->getValue('pho_name'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'maxLength' => 50)
);
subfolder(0, '', $photoAlbum, $photoAlbum->getValue('pho_id'));
$form->addSelectBox(
    'pho_pho_id_parent',
    $gL10n->get('PHO_PARENT_ALBUM'),
    $photoAlbumsArray,
    array(
        'property'                       => HtmlForm::FIELD_REQUIRED,
        'defaultValue'                   => $parentAlbumId,
        'showContextDependentFirstEntry' => false,
        'helpTextIdLabel'                => $gL10n->get('PHO_PARENT_ALBUM_DESC', array('PHO_PHOTO_ALBUMS'))
    )
);
$form->addInput(
    'pho_begin',
    $gL10n->get('SYS_START'),
    $photoAlbum->getValue('pho_begin'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'type' => 'date', 'maxLength' => 10)
);
$form->addInput(
    'pho_end',
    $gL10n->get('SYS_END'),
    $photoAlbum->getValue('pho_end'),
    array('type' => 'date', 'maxLength' => 10)
);
$form->addInput(
    'pho_photographers',
    $gL10n->get('PHO_PHOTOGRAPHER'),
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
    $gL10n->get('PHO_ALBUM_LOCK'),
    (bool) $photoAlbum->getValue('pho_locked'),
    array('helpTextIdLabel' => 'PHO_ALBUM_LOCK_DESC')
);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $photoAlbum->getValue('pho_usr_id_create'),
    $photoAlbum->getValue('pho_timestamp_create'),
    (int) $photoAlbum->getValue('pho_usr_id_change'),
    $photoAlbum->getValue('pho_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
