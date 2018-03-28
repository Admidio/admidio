<?php
/**
 ***********************************************************************************************
 * Create and edit announcements
 *
 * @copyright 2004-2018 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_announcements_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getAnnId    = admFuncVariableIsValid($_GET, 'ann_id',   'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('ANN_ANNOUNCEMENTS')));
$getCopy     = admFuncVariableIsValid($_GET, 'copy',     'bool');

// set headline of the script
if($getCopy)
{
    $headline = $gL10n->get('SYS_COPY_VAR', array($getHeadline));
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

    // check if the current user could edit this announcement
    if(!$announcement->isEditable())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}
else
{
    // check if the user has the right to edit at least one category
    if(count($gCurrentUser->getAllEditableCategories('ANN')) === 0)
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
$form = new HtmlForm('announcements_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_function.php', array('ann_id' => $getAnnId, 'headline' => $getHeadline, 'mode' => '1')), $page);
$form->addInput(
    'ann_headline', $gL10n->get('SYS_TITLE'), noHTML($announcement->getValue('ann_headline')),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'ann_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ANN', HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => (int) $announcement->getValue('ann_cat_id'))
);
$form->addEditor(
    'ann_description', $gL10n->get('SYS_TEXT'), $announcement->getValue('ann_description'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'height' => '400')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $announcement->getValue('ann_usr_id_create'), $announcement->getValue('ann_timestamp_create'),
    (int) $announcement->getValue('ann_usr_id_change'), $announcement->getValue('ann_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
