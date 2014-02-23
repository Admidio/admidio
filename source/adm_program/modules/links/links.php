<?php
/****************************************************************************************
 * Show a list of all weblinks
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * start     : Position of query recordset where the visual output should start
 * headline  : Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * cat_id    : show only links of this category id, if id is not set than show all links
 * id        : Show only one link.
 *
 ***************************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['links_request']);

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_weblinks_module'] == 2)
{
    // avaiable only with valid login
    require_once('../../system/login_valid.php');
}

// Create Link object
$weblinks = new ModuleWeblinks();
$weblinksCount = $weblinks->getDataSetCount();

// Output head
$gLayout['title']  = $weblinks->getHeadline();
if($weblinks->getCatId() > 0)
{
    $category = new TableCategory($gDb, $weblinks->getCatId());
    $gLayout['title'] .= ' - '.$category->getValue('cat_name');
}

$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        }); 
    //--></script>';

if($gPreferences['enable_rss'] == 1)
{
    $gLayout['header'] = $gLayout['header']. '<link rel="alternate" type="application/rss+xml" title="'.$gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$weblinks->getHeadline()).'"
        href="'. $g_root_path. '/adm_program/modules/links/rss_links.php?headline='.$weblinks->getHeadline().'" />';
};

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $gLayout['title']);

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// output html of module
echo '<h1 class="admHeadline">'. $gLayout['title']. '</h1>
<div id="links_overview">';

// number of weblinks per page
if($gPreferences['weblinks_per_page'] > 0)
{
    $weblinksPerPage = $gPreferences['weblinks_per_page'];
}
else
{
    $weblinksPerPage = $weblinksCount;
}


// show icon links and navigation

if($weblinks->getId() == 0)
{	
	// create module menu
	$LinksMenu = new ModuleMenu('admMenuWeblinks');

	if($gCurrentUser->editWeblinksRight())
	{
		// show link to create new announcement
		$LinksMenu->addItem('admMenuItemNewLink', $g_root_path.'/adm_program/modules/links/links_new.php?headline='. $weblinks->getHeadline(), 
							$gL10n->get('LNK_CREATE_LINK'), 'add.png');
	}
	
	// show selectbox with all link categories
	$LinksMenu->addCategoryItem('admMenuItemCategory', 'LNK', $weblinks->getCatId(), 'links.php?headline='.$weblinks->getHeadline().'&cat_id=', 
								$gL10n->get('SYS_CATEGORY'), $gCurrentUser->editWeblinksRight());

	if($gCurrentUser->isWebmaster())
	{
		// show link to system preferences of weblinks
		$LinksMenu->addItem('admMenuItemPreferencesLinks', $g_root_path.'/adm_program/administration/organization/organization.php?show_option=LNK_WEBLINKS', 
							$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png');
	}

	$LinksMenu->show();

    // Navigation with forward and back button
    $baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $weblinks->getHeadline();
    echo admFuncGeneratePagination($baseUrl, $weblinksCount, $weblinksPerPage, $weblinks->getStartElement(), TRUE);
}

if ($weblinksCount == 0)
{
    // no weblink found
    if ($weblinks->getId() > 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $getStart = $weblinks->getStartElement();
    $getWeblinks = $weblinks->getDataSet($getStart);    
    $weblink = new TableWeblink($gDb);

    $j = 0;         // counter for fetch_object
    $i = 0;         // counter for links in category
    $previous_cat_id = -1;  // previous Cat Id
    $new_category = true;   // maybe new category
    
    // show all weblinks
    foreach($getWeblinks['recordset'] as $row)
    {
        // initialize weblink object and read new recordset into this object
        $weblink->clear();
        $weblink->setArray($row);

        if ($weblink->getValue('lnk_cat_id') != $previous_cat_id)
        {
            $i = 0;
            $new_category = true;
            if ($j>0)
            {
                echo '</div></div>';
            }
            echo '<div class="admBoxLayout">
                <div class="admBoxHead">'.$weblink->getValue('cat_name').'</div>
                <div class="admBoxBody">';
        }

        echo '<div class="admWeblinkItem" id="lnk_'.$weblink->getValue('lnk_id').'">';
                /*if($i > 0)
                {
                    echo '<hr />';
                }*/

            // show weblink
            echo '
            <span class="admIconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $gPreferences['weblinks_target']. '"><img src="'. THEME_PATH. '/icons/weblinks.png"
                    alt="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" title="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $gPreferences['weblinks_target']. '">'.$weblink->getValue('lnk_name').'</a>
            </span>';
            // change and delete only users with rights
            if ($gCurrentUser->editWeblinksRight())
            {
                echo '
                <a class="admIconLink" href="'.$g_root_path.'/adm_program/modules/links/links_new.php?lnk_id='.$weblink->getValue('lnk_id').'&amp;headline='. $weblinks->getHeadline(). '"><img
                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                <a class="admIconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=lnk&amp;element_id=lnk_'.
                    $weblink->getValue('lnk_id').'&amp;name='.urlencode($weblink->getValue('lnk_name')).'&amp;database_id='.$weblink->getValue('lnk_id').'"><img 
                    src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
            }

            // get available description
            if(strlen($weblink->getValue('lnk_description')) > 0)
            {
                echo '<div class="admWeblinkDescription">'.$weblink->getValue('lnk_description').'</div>';
            }
            
            echo '<div class="admSmallFont">'.$gL10n->get('LNK_COUNTER'). ': '.$weblink->getValue('lnk_counter').'</div>
		</div>';

        $j++;
        $i++;

        // set current category to privious
        $previous_cat_id = $weblink->getValue('lnk_cat_id');

        $new_category = false;
    }  // End While-loop

    // No links or 1 link is hidden
    if ($weblinksCount == 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }

    echo '</div></div>';
} // end if at least 1 recordset

echo '</div>';

// If neccessary show links to navigate to next and previous recordsets of the query
$baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $weblinks->getHeadline();
echo admFuncGeneratePagination($baseUrl, $weblinksCount, $weblinksPerPage, $weblinks->getStartElement(), TRUE);

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>
