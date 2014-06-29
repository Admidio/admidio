<?php
/******************************************************************************
 * Show a list of all announcements
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * start     - Position of query recordset where the visual output should start
 * headline  - Title of the announcements module. This will be shown in the whole module.
 *             (Default) ANN_ANNOUNCEMENTS
 * id        - Id of a single announcement that should be shown.
 * date      - If set all announcements of that date will be shown.
 *             Format: YYYYMMDD
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['announcements_request']);

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
// get parameters and number of recordsets
$parameter = $announcements->getParameter();
$announcementsCount = $announcements->getDataSetCount();

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $announcements->getHeadline());

// create html page object
$page = new HtmlPage();

// add rss feed to announcements
if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile($g_root_path.'/adm_program/modules/announcements/rss_announcements.php?headline='.$announcements->getHeadline(), $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname').' - '.$announcements->getHeadline()));
};

$page->addJavascript('$("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});', true);

$page->addHeadline($announcements->getHeadline());

// number of announcements per page
if($gPreferences['announcements_per_page'] > 0)
{
    $announcementsPerPage = $gPreferences['announcements_per_page'];
}
else
{
    $announcementsPerPage = $announcementsCount;
}

// create module menu
$announcementsMenu = new ModuleMenu('admMenuAnnouncements');

if($gCurrentUser->editAnnouncements())
{
	// show link to create new announcement
	$announcementsMenu->addItem('admMenuItemNewAnnouncement', $g_root_path.'/adm_program/modules/announcements/announcements_new.php?headline='.$announcements->getHeadline(), 
								$gL10n->get('SYS_CREATE_VAR', $announcements->getHeadline()), 'add.png');
}

if($gCurrentUser->isWebmaster())
{
	// show link to system preferences of announcements
	$announcementsMenu->addItem('admMenuItemPreferencesAnnouncements', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=announcements', 
								$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
}

$page->addHtml($announcementsMenu->show(false));

if($announcementsCount == 0)
{
    // no announcements found
    if($parameter['id'] > 0)
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
    $announcementsArray = $announcements->getDataSet($parameter['startelement'], $announcementsPerPage);    
    $announcement = new TableAnnouncement($gDb);
    
    // show all announcements
    foreach($announcementsArray['recordset'] as $row)
    {
        $announcement->clear();
        $announcement->setArray($row);
        $page->addHtml('
        <div class="panel panel-primary" id="ann_'.$announcement->getValue('ann_id').'">
            <div class="panel-heading">
                <span class="panel-heading-left">
                    <img src="'. THEME_PATH. '/icons/announcements.png" alt="'. $announcement->getValue("ann_headline"). '" />'.
                    $announcement->getValue('ann_headline'). '
                </span>
                <span class="panel-heading-right">'.$announcement->getValue('ann_timestamp_create', $gPreferences['system_date']).'&nbsp;');
                    
                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if($gCurrentUser->editAnnouncements())
                    {
                        if($announcement->editRight() == true)
                        {
                            $page->addHtml('
                            <a class="admIconLink" href="'.$g_root_path.'/adm_program/modules/announcements/announcements_new.php?ann_id='. $announcement->getValue('ann_id'). '&amp;headline='.$announcements->getHeadline().'"><img 
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>');
                        }

                        // Loeschen darf man nur Ankuendigungen der eigenen Gliedgemeinschaft
                        if($announcement->getValue('ann_org_shortname') == $gCurrentOrganization->getValue('org_shortname'))
                        {
                            $page->addHtml('
                            <a class="admIconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=ann&amp;element_id=ann_'.
                                $announcement->getValue('ann_id').'&amp;name='.urlencode($announcement->getValue('ann_headline')).'&amp;database_id='.$announcement->getValue('ann_id').'"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                        }    
                    }
                $page->addHtml('</span>
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
    
    // If neccessary show links to navigate to next and previous recordsets of the query
    $base_url = $g_root_path.'/adm_program/modules/announcements/announcements.php?headline='.$announcements->getHeadline();
    $page->addHtml(admFuncGeneratePagination($base_url, $announcementsCount, $announcementsPerPage, $parameter['startelement'], TRUE));
}

// show html of complete page
$page->show();

?>