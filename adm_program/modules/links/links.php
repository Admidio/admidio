<?php
/**
 ***********************************************************************************************
 * Show a list of all weblinks
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * start     : Position of query recordset where the visual output should start
 * headline  : Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * cat_id    : show only links of this category id, if id is not set than show all links
 * id        : Show only one link.
 ***********************************************************************************************
 */
require_once('../../system/common.php');

unset($_SESSION['links_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start',    'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('LNK_WEBLINKS')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',   'int');
$getLinkId   = admFuncVariableIsValid($_GET, 'id',       'int');

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_weblinks_module'] == 2)
{
    // avaiable only with valid login
    require_once('../../system/login_valid.php');
}

// Create Link object
$weblinks = new ModuleWeblinks();
$weblinks->setParameter('cat_id', $getCatId);
$weblinksCount = $weblinks->getDataSetCount();

// number of weblinks per page
if($gPreferences['weblinks_per_page'] > 0)
{
    $weblinksPerPage = (int) $gPreferences['weblinks_per_page'];
}
else
{
    $weblinksPerPage = $weblinksCount;
}

// Output head
$headline = $weblinks->getHeadline($getHeadline);

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile(ADMIDIO_URL. FOLDER_MODULES.'/links/rss_links.php?headline='.$getHeadline, $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline));
}

$page->addHtml('<div id="links_overview">');

// show icon links and navigation

if($weblinks->getId() === 0)
{
    // get module menu
    $LinksMenu = $page->getMenu();

    if($gCurrentUser->editWeblinksRight())
    {
        // show link to create new announcement
        $LinksMenu->addItem('menu_item_new_link', ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php?headline='. $getHeadline,
                            $gL10n->get('LNK_CREATE_LINK'), 'add.png');
    }

    if($gCurrentUser->isAdministrator())
    {
        // show link to system preferences of weblinks
        $LinksMenu->addItem('menu_items_links_preferences', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=links',
                            $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
    }
    elseif($gCurrentUser->editWeblinksRight())
    {
        // show link to maintain categories
        $LinksMenu->addItem('menu_item_maintain_categories', ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php?type=LNK&title='. $getHeadline,
                            $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_view_tile.png');
    }

    $page->addJavascript('$("#cat_id").change(function () { $("#navbar_cat_id_form").submit(); });', true);

    $navbarForm = new HtmlForm('navbar_cat_id_form', ADMIDIO_URL.FOLDER_MODULES.'/links/links.php?headline='. $getHeadline, $page, array('type' => 'navbar', 'setFocus' => false));
    $navbarForm->addSelectBoxForCategories('cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'LNK', 'FILTER_CATEGORIES', array('defaultValue' => $getCatId));
    $LinksMenu->addForm($navbarForm->show(false));
}

if ($weblinksCount === 0)
{
    // no weblink found
    if ($weblinks->getId() > 0)
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
    $getStart = $weblinks->getStartElement();
    $weblinksDataSet = $weblinks->getDataSet($getStart);
    $weblink = new TableWeblink($gDb);

    $j = 0;         // counter for fetchObject
    $i = 0;         // counter for links in category
    $previous_cat_id = -1;  // previous Cat Id
    $new_category = true;   // maybe new category

    if($weblinksDataSet['numResults'] > 0)
    {
        // show all weblinks
        foreach($weblinksDataSet['recordset'] as $row)
        {
            // initialize weblink object and read new recordset into this object
            $weblink->clear();
            $weblink->setArray($row);

            if ((int) $weblink->getValue('lnk_cat_id') !== $previous_cat_id)
            {
                $i = 0;
                $new_category = true;
                if ($j > 0)
                {
                    $page->addHtml('</div></div>');
                }
                $page->addHtml('<div class="panel panel-primary">
                    <div class="panel-heading">'.$weblink->getValue('cat_name').'</div>
                    <div class="panel-body">');
            }

            $page->addHtml('<div class="admidio-weblink-item" id="lnk_'.$weblink->getValue('lnk_id').'">');
                // show weblink
                $page->addHtml('
                <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $gPreferences['weblinks_target']. '"><img src="'. THEME_URL. '/icons/weblinks.png"
                    alt="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" title="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" />'.$weblink->getValue('lnk_name').'</a>');

                // change and delete only users with rights
                if ($gCurrentUser->editWeblinksRight())
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php?lnk_id='.$weblink->getValue('lnk_id').'&amp;headline='. $getHeadline. '"><img
                        src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                    <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=lnk&amp;element_id=lnk_'.
                        $weblink->getValue('lnk_id').'&amp;name='.urlencode($weblink->getValue('lnk_name')).'&amp;database_id='.$weblink->getValue('lnk_id').'"><img
                        src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                }

                // get available description
                if(strlen($weblink->getValue('lnk_description')) > 0)
                {
                    $page->addHtml('<div class="admidio-weblink-description">'.$weblink->getValue('lnk_description').'</div>');
                }

                $page->addHtml('<div class="weblink-counter"><small>'.$gL10n->get('LNK_COUNTER'). ': '.$weblink->getValue('lnk_counter').'</small></div>
            </div>');

            ++$j;
            ++$i;

            // set current category to privious
            $previous_cat_id = (int) $weblink->getValue('lnk_cat_id');

            $new_category = false;
        }  // End While-loop

        $page->addHtml('</div></div>');
    }
    else
    {
        // No links or 1 link is hidden
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
} // end if at least 1 recordset

$page->addHtml('</div>');

// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = ADMIDIO_URL.FOLDER_MODULES.'/links/links.php?headline='. $getHeadline.'&cat_id='.$getCatId;
$page->addHtml(admFuncGeneratePagination($baseUrl, $weblinksCount, $weblinksPerPage, $weblinks->getStartElement(), true));

// show html of complete page
$page->show();
