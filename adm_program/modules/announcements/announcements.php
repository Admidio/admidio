<?php
/**
 ***********************************************************************************************
 * Show a list of all announcements
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * start     - Position of query recordset where the visual output should start
 * headline  - Title of the announcements module. This will be shown in the whole module.
 *             (Default) ANN_ANNOUNCEMENTS
 * cat_id    : Show only announcements of this category id, if id is not set than show all announcements.
 * id        - Id of a single announcement that should be shown.
 * date_from - is set to 01.01.1970,
 *             if no date information is delivered
 * date_to   - is set to actual date,
 *             if no date information is delivered
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['announcements_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start',     'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline',  'string', array('defaultValue' => $gL10n->get('ANN_ANNOUNCEMENTS')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',    'int');
$getId       = admFuncVariableIsValid($_GET, 'id',        'int');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to',   'date');

// check if module is enabled
if ((int) $gSettingsManager->get('enable_announcements_module') === 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif((int) $gSettingsManager->get('enable_announcements_module') === 2)
{
    // Access only with valid login
    require(__DIR__ . '/../../system/login_valid.php');
}

// create object for announcements
$announcements = new ModuleAnnouncements();
$announcements->setParameter('id', $getId);
$announcements->setParameter('cat_id', $getCatId);
$announcements->setDateRange($getDateFrom, $getDateTo);

// get parameters and number of recordsets
$announcementsCount = $announcements->getDataSetCount();

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $getHeadline);

// create html page object
$page = new HtmlPage($getHeadline);
$page->enableModal();

// add rss feed to announcements
if($gSettingsManager->getBool('enable_rss'))
{
    $page->addRssFile(
        safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/rss_announcements.php', array('headline' => $getHeadline)),
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname').' - '.$getHeadline))
    );
}

// number of announcements per page
if($gSettingsManager->getInt('announcements_per_page') > 0)
{
    $announcementsPerPage = $gSettingsManager->getInt('announcements_per_page');
}
else
{
    $announcementsPerPage = $announcementsCount;
}

// get module menu
$announcementsMenu = $page->getMenu();

if(count($gCurrentUser->getAllEditableCategories('ANN')) > 0)
{
    // show link to create new announcement
    $announcementsMenu->addItem(
        'menu_item_new_announcement', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('headline' => $getHeadline)),
        $gL10n->get('SYS_CREATE_ENTRY'), 'add.png'
    );
}

$page->addJavascript('
    $("#cat_id").change(function() {
        $("#navbar_cat_id_form").submit();
    });',
    true
);

$navbarForm = new HtmlForm('navbar_cat_id_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('headline' => $getHeadline)), $page, array('type' => 'navbar', 'setFocus' => false));
$navbarForm->addSelectBoxForCategories(
    'cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'ANN', HtmlForm::SELECT_BOX_MODUS_FILTER,
    array('defaultValue' => $getCatId)
);
$announcementsMenu->addForm($navbarForm->show());

if($gCurrentUser->editAnnouncements())
{
    // if no calendar selectbox is shown, then show link to edit calendars
    $announcementsMenu->addItem(
        'admMenuItemCategories', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'ANN')),
        $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_view_tile.png'
    );
}

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of announcements
    $announcementsMenu->addItem(
        'menu_item_preferences', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'announcements')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right'
    );
}

if($announcementsCount === 0)
{
    // no announcements found
    if($getId > 0)
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
    }
    else
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
}
else
{
    // get all recordsets
    $announcementsArray = $announcements->getDataSet($getStart, $announcementsPerPage);
    $announcement = new TableAnnouncement($gDb);

    // show all announcements
    foreach($announcementsArray['recordset'] as $row)
    {
        $announcement->clear();
        $announcement->setArray($row);

        $annId = (int) $announcement->getValue('ann_id');
        $annHeadline = noHTML($announcement->getValue('ann_headline'));

        $page->addHtml('
        <div class="panel panel-primary" id="ann_'.$annId.'">
            <div class="panel-heading">
                <div class="pull-left">
                    <img class="admidio-panel-heading-icon" src="'. THEME_URL. '/icons/announcements.png" alt="'. $annHeadline. '" />'.
                    $annHeadline. '
                </div>
                <div class="pull-right text-right">'.$announcement->getValue('ann_timestamp_create', $gSettingsManager->getString('system_date')));

                    // check if the user could edit this announcement
                    if($announcement->isEditable())
                    {
                        $page->addHtml('
                        <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_id' => $annId, 'copy' => '1', 'headline' => $getHeadline)).'"><img
                            src="'.THEME_URL.'/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>
                        <a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_id' => $annId, 'headline' => $getHeadline)).'"><img
                            src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'ann', 'element_id' => 'ann_'.$annId, 'name' => $announcement->getValue('ann_headline'), 'database_id' => $annId)).'"><img
                            src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                    }
                $page->addHtml('</div>
            </div>

            <div class="panel-body">'.
                $announcement->getValue('ann_description').
            '</div>
            <div class="panel-footer">'.
                // show information about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName(
                    $row['create_name'], $announcement->getValue('ann_timestamp_create'),
                    $row['change_name'], $announcement->getValue('ann_timestamp_change'),
                    (int) $announcement->getValue('ann_usr_id_create'), (int) $announcement->getValue('ann_usr_id_change')
                ) .
                '<div class="admidio-info-category">' .
                    $gL10n->get('SYS_CATEGORY') .
                    '&nbsp;<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('headline' => $getHeadline, 'cat_id' => $announcement->getValue('ann_cat_id'))).'">' . noHTML($announcement->getValue('cat_name')).'</a>
                </div>
            </div>
        </div>');
    }  // Ende foreach

    // If necessary show links to navigate to next and previous recordsets of the query
    $baseUrl = safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('headline' => $getHeadline, 'cat_id' => $getCatId));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $announcementsCount, $announcementsPerPage, $getStart));
}

// show html of complete page
$page->show();
