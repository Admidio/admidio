<?php
/******************************************************************************
 * Create and edit photo alben
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 * pho_id : Id of the album that should be edited
 * mode   : - new (new album)
 *          - change (edit album)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric');
$getMode    = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('new', 'change')));

$photoAlbumsArray = array(0 => $gL10n->get('PHO_PHOTO_ALBUMS'));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

if($getMode == 'new')
{
    $headline = $gL10n->get('PHO_CREATE_ALBUM');
}
elseif($getMode == 'change')
{
    $headline = $gL10n->get('PHO_EDIT_ALBUM');
}


$gNavigation->addUrl(CURRENT_URL, $headline);

// Fotoalbumobjekt anlegen
$photoAlbum = new TablePhotos($gDb);

// nur Daten holen, wenn Album editiert werden soll
if ($getMode == 'change')
{
    $photoAlbum->readDataById($getPhotoId);

    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photoAlbum->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['photo_album_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$photoAlbum->setArray($_SESSION['photo_album_request']);
    unset($_SESSION['photo_album_request']);
}

// die Albenstruktur fuer eine Auswahlbox darstellen und das aktuelle Album vorauswählen
function subfolder($parent_id, $vorschub, $photoAlbum, $pho_id)
{
    global $gDb, $gCurrentOrganization, $photoAlbumsArray;
    
    $vorschub = $vorschub.'&nbsp;&nbsp;&nbsp;';
    $pho_id_condition = '';
    $parentPhotoAlbum = new TablePhotos($gDb);

    //Erfassen des auszugebenden Albums
    if($parent_id > 0)
    {
        $pho_id_condition .= ' AND pho_pho_id_parent = \''.$parent_id.'\' ';
    }
    else
    {
        $pho_id_condition .= ' AND pho_pho_id_parent IS NULL ';
    }

    $sql = 'SELECT *
              FROM '. TBL_PHOTOS. '
             WHERE pho_id <> '. $photoAlbum->getValue('pho_id').
                   $pho_id_condition
                   .' AND pho_org_shortname LIKE \''.$gCurrentOrganization->getValue('org_shortname').'\'';
    $result_child = $gDb->query($sql);

    while($adm_photo_child = $gDb->fetch_array($result_child))
    {
        $selected = '';
        
        $parentPhotoAlbum->clear();
        $parentPhotoAlbum->setArray($adm_photo_child);
        
        //Wenn die Elternveranstaltung von pho_id dann selected
        if(($parentPhotoAlbum->getValue('pho_id') == $photoAlbum->getValue('pho_pho_id_parent'))
        ||  $parentPhotoAlbum->getValue('pho_id') == $pho_id)
        {
            $selected = 'selected="selected"';
        }
        
        // add entry to array of all photo albums
        $photoAlbumsArray[$parentPhotoAlbum->getValue('pho_id')] = 
            $vorschub.'&#151; '.$parentPhotoAlbum->getValue('pho_name').'&nbsp('.$parentPhotoAlbum->getValue('pho_begin', 'Y').')';

        subfolder($parentPhotoAlbum->getValue('pho_id'), $vorschub, $photoAlbum, $pho_id);
    }//while
}//function

// create html page object
$page = new HtmlPage();

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$photoAlbumMenu = new HtmlNavbar('menu_photo_album_create', $headline, $page);
$photoAlbumMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($photoAlbumMenu->show(false));

// show form
$form = new HtmlForm('photo_album_edit_form', $g_root_path.'/adm_program/modules/photos/photo_album_function.php?pho_id='.$getPhotoId.'&amp;mode='.$getMode, $page);
$form->addInput('pho_name', $gL10n->get('PHO_ALBUM'), $photoAlbum->getValue('pho_name'), array('property' => FIELD_MANDATORY, 'maxLength' => 10));
subfolder($photoAlbum->getValue('pho_pho_id_parent'), '', $photoAlbum, $getPhotoId);
$form->addSelectBox('pho_pho_id_parent', $gL10n->get('PHO_PARENT_ALBUM'), $photoAlbumsArray, array('property' => FIELD_MANDATORY, 
                    'defaultValue' => $photoAlbum->getValue('pho_pho_id_parent'), 'showContextDependentFirstEntry' => false, 
                    'helpTextIdLabel' => array('PHO_PARENT_ALBUM_DESC', $gL10n->get('PHO_PHOTO_ALBUMS'))));
$form->addInput('pho_begin', $gL10n->get('SYS_START'), $photoAlbum->getValue('pho_begin'), array('type' => 'date', 'maxLength' => 10));
$form->addInput('pho_end', $gL10n->get('SYS_END'), $photoAlbum->getValue('pho_end'), array('type' => 'date', 'maxLength' => 10));
$form->addInput('pho_photographers', $gL10n->get('PHO_PHOTOGRAPHER'), $photoAlbum->getValue('pho_photographers'), array('maxLength' => 100));
$form->addCheckbox('pho_locked', $gL10n->get('PHO_ALBUM_LOCK'), $photoAlbum->getValue('pho_locked'), array('helpTextIdLabel' => 'PHO_ALBUM_LOCK_DESC'));

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($photoAlbum->getValue('pho_usr_id_create'), $photoAlbum->getValue('pho_timestamp_create'), $photoAlbum->getValue('pho_usr_id_change'), $photoAlbum->getValue('pho_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

?>