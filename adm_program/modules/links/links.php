<?php
/**
 ***********************************************************************************************
 * Show a list of all weblinks
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * start     : Position of query recordset where the visual output should start
 * cat_uuid  : show only links of this category, if UUID is not set than show all links
 * link_uuid : Uuid of a single link that should be shown.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['links_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'int');
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'string');

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0) {
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_weblinks_module') === 2) {
    // available only with valid login
    require(__DIR__ . '/../../system/login_valid.php');
}

$headline = $gL10n->get('SYS_WEBLINKS');

$category = new TableCategory($gDb);

if ($getCatUuid !== '') {
    $category->readDataByUuid($getCatUuid);
    $headline .= ' - '.$category->getValue('cat_name');
}

// Create Link object
$weblinks = new ModuleWeblinks();
$weblinks->setParameter('lnk_uuid', $getLinkUuid);
$weblinks->setParameter('cat_id', $category->getValue('cat_id'));
$weblinksCount = $weblinks->getDataSetCount();

// number of weblinks per page
if ($gSettingsManager->getInt('weblinks_per_page') > 0) {
    $weblinksPerPage = $gSettingsManager->getInt('weblinks_per_page');
} else {
    $weblinksPerPage = $weblinksCount;
}

// add url to navigation stack
if ($getLinkUuid  !== '') {
    $gNavigation->addUrl(CURRENT_URL, $headline);
} else {
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-link');
}

// create html page object
$page = new HtmlPage('admidio-weblinks', $headline);

if ($gSettingsManager->getBool('enable_rss')) {
    $page->addRssFile(
        ADMIDIO_URL. FOLDER_MODULES.'/links/rss_links.php',
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $headline))
    );
}

$page->addHtml('<div id="links_overview">');

// show icon links and navigation

if ($weblinks->getId() === 0) {
    if (count($gCurrentUser->getAllEditableCategories('LNK')) > 0) {
        // show link to create new weblink
        $page->addPageFunctionsMenuItem(
            'menu_item_links_add',
            $gL10n->get('SYS_CREATE_WEBLINK'),
            ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php',
            'fa-plus-circle'
        );
    }

    if ($gCurrentUser->editWeblinksRight()) {
        // show link to maintain categories
        $page->addPageFunctionsMenuItem(
            'menu_item_links_maintain_categories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'LNK')),
            'fa-th-large'
        );
    }

    $page->addJavascript(
        '
        $("#cat_uuid").change(function() {
            $("#navbar_filter_form").submit();
        });',
        true
    );

    // create filter menu with elements for category
    $filterNavbar = new HtmlNavbar('navbar_filter', '', null, 'filter');
    $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/links/links.php', $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxForCategories(
        'cat_uuid',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'LNK',
        HtmlForm::SELECT_BOX_MODUS_FILTER,
        array('defaultValue' => $getCatUuid)
    );
    $filterNavbar->addForm($form->show());
    $page->addHtml($filterNavbar->show());
}

if ($weblinksCount === 0) {
    // no weblink found
    if ($weblinks->getId() > 0) {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
    } else {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
} else {
    $getStart = $weblinks->getStartElement();
    $weblinksDataSet = $weblinks->getDataSet($getStart);
    $weblink = new TableWeblink($gDb);

    $j = 0;         // counter for fetchObject
    $i = 0;         // counter for links in category
    $previousCatId = -1;  // previous Cat Id
    $newCategory = true;  // maybe new category

    if ($weblinksDataSet['numResults'] > 0) {
        // show all weblinks
        foreach ($weblinksDataSet['recordset'] as $row) {
            // initialize weblink object and read new recordset into this object
            $weblink->clear();
            $weblink->setArray($row);

            $lnkUuid  = $weblink->getValue('lnk_uuid');
            $lnkCatId = (int) $weblink->getValue('lnk_cat_id');
            $lnkName  = $weblink->getValue('lnk_name');
            $lnkDescription = $weblink->getValue('lnk_description');

            if ($lnkCatId !== $previousCatId) {
                $i = 0;
                $newCategory = true;
                if ($j > 0) {
                    $page->addHtml('</div></div>');
                }
                $page->addHtml('<div class="card admidio-blog">
                    <div class="card-header">'.$weblink->getValue('cat_name').'</div>
                    <div class="card-body">');
            }

            $page->addHtml('<div class="mb-4" id="lnk_'.$lnkUuid.'">');

            // show weblink
            $page->addHtml('
                <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/links/links_redirect.php', array('link_uuid' => $lnkUuid)).'" target="'. $gSettingsManager->getString('weblinks_target'). '">
                    <i class="fas fa-link"></i>'.$lnkName.'</a>');

            // change and delete only users with rights
            if ($weblink->isEditable()) {
                $page->addHtml('
                    <a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php', array('link_uuid' => $lnkUuid)). '">
                        <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>
                    <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'lnk',
                        'element_id' => 'lnk_'.$lnkUuid, 'name' => $weblink->getValue('lnk_name'), 'database_id' => $lnkUuid)).'">
                        <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>');
            }

            // get available description
            if (strlen($lnkDescription) > 0) {
                $page->addHtml('<div class="admidio-weblink-description">'.$lnkDescription.'</div>');
            }

            $page->addHtml('<div class="weblink-counter"><small>'.$gL10n->get('SYS_COUNTER'). ': '.(int) $weblink->getValue('lnk_counter').'</small></div>
            </div>');

            ++$j;
            ++$i;

            // set current category to privious
            $previousCatId = $lnkCatId;

            $newCategory = false;
        }  // End While-loop

        $page->addHtml('</div></div>');
    } else {
        // No links or 1 link is hidden
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
} // end if at least 1 recordset

$page->addHtml('</div>');

// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/links/links.php', array('cat_uuid' => $getCatUuid));
$page->addHtml(admFuncGeneratePagination($baseUrl, $weblinksCount, $weblinksPerPage, $weblinks->getStartElement()));

// show html of complete page
$page->show();
