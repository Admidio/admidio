<?php
/**
 ***********************************************************************************************
 * Create and edit announcements
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_id    - ID of the announcement that should be edited
 * headline  - Title of the announcements module. This will be shown in the whole module.
 *             (Default) ANN_ANNOUNCEMENTS
 * copy : true - The announcement of the ann_id will be copied and the base for this new announcement
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if(!$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Initialize and check the parameters
$getAnnId    = admFuncVariableIsValid($_GET, 'ann_id',   'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('ANN_ANNOUNCEMENTS')));
$getCopy     = admFuncVariableIsValid($_GET, 'copy',     'bool');

// set headline of the script
if($getCopy)
{
    $headline = $gL10n->get('SYS_COPY_VAR', $getHeadline);
}
elseif($getAnnId > 0)
{
    $headline = $getHeadline. ' - '. $gL10n->get('SYS_EDIT_ENTRY');
}
else
{
    $headline = $getHeadline. ' - '. $gL10n->get('SYS_CREATE_ENTRY');
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Create announcements object
$announcement = new TableAnnouncement($gDb);

if($getAnnId > 0)
{
    $announcement->readDataById($getAnnId);

    if($getCopy === true)
    {
        $getAnnId = 0;
    }

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if(!$announcement->editRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $announcement->setArray($_SESSION['announcements_request']);
    unset($_SESSION['announcements_request']);
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$announcementsMenu = $page->getMenu();
$announcementsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('announcements_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_function.php?ann_id='.$getAnnId.'&amp;headline='. $getHeadline. '&amp;mode=1', $page);
$form->addInput('ann_headline', $gL10n->get('SYS_TITLE'), $announcement->getValue('ann_headline'), array('maxLength' => 100, 'property' => FIELD_REQUIRED));
$form->addSelectBoxForCategories('ann_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ANN', 'EDIT_CATEGORIES',
                                 array('property' => FIELD_REQUIRED, 'defaultValue' => $announcement->getValue('ann_cat_id')));

// if current organization has a parent organization or is child organizations then show option to set this announcement to global
if($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->hasChildOrganizations())
{
    // show all organizations where this organization is mother or child organization
    $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
    $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

    $form->addCheckbox('ann_global', $gL10n->get('SYS_ENTRY_MULTI_ORGA'), (bool) $announcement->getValue('ann_global'), array('helpTextIdLabel' => array('SYS_DATA_GLOBAL', $organizations)));
}
$form->addEditor('ann_description', $gL10n->get('SYS_TEXT'), $announcement->getValue('ann_description'), array('property' => FIELD_REQUIRED, 'height' => '400'));
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    $announcement->getValue('ann_usr_id_create'), $announcement->getValue('ann_timestamp_create'),
    $announcement->getValue('ann_usr_id_change'), $announcement->getValue('ann_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
