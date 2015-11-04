<?php
/******************************************************************************
 * Show a list of all announcements
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start     - Position of query recordset where the visual output should start
 * headline  - Title of the announcements module. This will be shown in the whole module.
 *             (Default) ANN_ANNOUNCEMENTS
 * id        - Id of a single announcement that should be shown.
 * date_from - is set to 01.01.1970,
 *             if no date information is delivered
 * date_to   - is set to actual date,
 *             if no date information is delivered
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['announcements_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('ANN_ANNOUNCEMENTS')));
$getId       = admFuncVariableIsValid($_GET, 'id', 'numeric');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

// check if module is enabled
if ($gPreferences['enable_announcements_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_announcements_module'] == 2)
{
    // Access only with valid login
    require('../../system/login_valid.php');
}

// create object for announcements
$announcements = new ModuleAnnouncements();
$announcements->setParameter('id', $getId);
$announcements->setDateRange($getDateFrom, $getDateTo);

// get parameters and number of recordsets
$announcementsCount = $announcements->getDataSetCount();

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $getHeadline);

// create html page object
$page = new HtmlPage($getHeadline);

// add rss feed to announcements
if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile($g_root_path.'/adm_program/modules/announcements/rss_announcements.php?headline='.$getHeadline, $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname').' - '.$getHeadline));
};

// number of announcements per page
if($gPreferences['announcements_per_page'] > 0)
{
    $announcementsPerPage = $gPreferences['announcements_per_page'];
}
else
{
    $announcementsPerPage = $announcementsCount;
}

// get module menu
$announcementsMenu = $page->getMenu();

if($gCurrentUser->editAnnouncements())
{
    // show link to create new announcement
    $announcementsMenu->addItem('menu_item_new_announcement', $g_root_path.'/adm_program/modules/announcements/announcements_new.php?headline='.$getHeadline,
                                $gL10n->get('SYS_CREATE_VAR', $getHeadline), 'add.png');
}

if($gCurrentUser->isWebmaster())
{
    // show link to system preferences of announcements
    $announcementsMenu->addItem('menu_item_preferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=announcements',
                                $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

if($announcementsCount == 0)
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
        $page->addHtml('
        <div class="panel panel-primary" id="ann_'.$announcement->getValue('ann_id').'">
            <div class="panel-heading">
                <div class="pull-left">
                    <img class="admidio-panel-heading-icon" src="'. THEME_PATH. '/icons/announcements.png" alt="'. $announcement->getValue('ann_headline'). '" />'.
                    $announcement->getValue('ann_headline'). '
                </div>
                <div class="pull-right text-right">'.$announcement->getValue('ann_timestamp_create', $gPreferences['system_date']));

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if($gCurrentUser->editAnnouncements())
                    {
                        if($announcement->editRight() == true)
                        {
                            $page->addHtml('
                            <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/announcements/announcements_new.php?ann_id='. $announcement->getValue('ann_id'). '&amp;headline='.$getHeadline.'"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>');
                        }

                        // Loeschen darf man nur Ankuendigungen der eigenen Gliedgemeinschaft
                        if($announcement->getValue('ann_org_id') == $gCurrentOrganization->getValue('org_id'))
                        {
                            $page->addHtml('
                            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                href="'.$g_root_path.'/adm_program/system/popup_message.php?type=ann&amp;element_id=ann_'.
                                $announcement->getValue('ann_id').'&amp;name='.urlencode($announcement->getValue('ann_headline')).'&amp;database_id='.$announcement->getValue('ann_id').'"><img
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                        }
                    }
                $page->addHtml('</div>
            </div>

            <div class="panel-body">'.
                $announcement->getValue('ann_description').

            '</div>
            <div class="panel-footer">'.
                // show informations about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName($row['create_name'], $announcement->getValue('ann_timestamp_create'),
                    $row['change_name'], $announcement->getValue('ann_timestamp_change'), $announcement->getValue('ann_usr_id_create'), $announcement->getValue('ann_usr_id_change')).'
            </div>
        </div>');
    }  // Ende foreach

    // If necessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/announcements/announcements.php?headline='.$getHeadline;
    $page->addHtml(admFuncGeneratePagination($base_url, $announcementsCount, $announcementsPerPage, $getStart, true));
}

// show html of complete page
$page->show();
