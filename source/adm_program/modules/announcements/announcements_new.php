<?php
/******************************************************************************
 * Create and edit announcements
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * ann_id    - ID of the announcement that should be edited
 * headline  - Title of the announcements module. This will be shown in the whole module.
 *             (Default) ANN_ANNOUNCEMENTS
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

if(!$gCurrentUser->editAnnouncements())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getAnnId    = admFuncVariableIsValid($_GET, 'ann_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('ANN_ANNOUNCEMENTS'));

// Ankuendigungsobjekt anlegen
$announcement = new TableAnnouncement($gDb);

if($getAnnId > 0)
{
    $announcement->readDataById($getAnnId);

    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$announcement->setArray($_SESSION['announcements_request']);
    unset($_SESSION['announcements_request']);
}

// Html-Kopf ausgeben
if($getAnnId > 0)
{
    $gLayout['title'] = $gL10n->get('SYS_EDIT_VAR', $gL10n->get('ANN_ANNOUNCEMENT'));
}
else
{
    $gLayout['title'] = $gL10n->get('SYS_CREATE_VAR', $gL10n->get('ANN_ANNOUNCEMENT'));
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $gLayout['title']);

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// show back link
echo $gNavigation->getHtmlBackButton();

// show headline of module
echo '<h1 class="admHeadline">'.$gLayout['title'].'</h1>';

// show form
$form = new Form('announcements-edit-form', $g_root_path.'/adm_program/modules/announcements/announcements_function.php?ann_id='.$getAnnId.'&amp;headline='. $getHeadline. '&amp;mode=1');
$form->openGroupBox('gb-announcement-description');
$form->addTextInput('ann_headline', $gL10n->get('SYS_TITLE'), $announcement->getValue('ann_headline'), 100, true);

// if current organization has a parent organization or is child organizations then show option to set this announcement to global
if($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->hasChildOrganizations())
{
	$form->addCheckbox('ann_global', $gL10n->get('SYS_ENTRY_MULTI_ORGA'), $announcement->getValue('ann_global'), false, 'SYS_DATA_GLOBAL');
}
$form->addEditor('ann_description', $gL10n->get('SYS_TEXT'), $announcement->getValue('ann_description'), true);
$form->closeGroupBox();
$form->addString(admFuncShowCreateChangeInfoById($announcement->getValue('ann_usr_id_create'), $announcement->getValue('ann_timestamp_create'), $announcement->getValue('ann_usr_id_change'), $announcement->getValue('ann_timestamp_change')));
$form->addSubmitButton('btnSave', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->show();

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>