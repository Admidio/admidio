<?php
/**
 ***********************************************************************************************
 * Show a list of all announcements
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * start     - Position of query recordset where the visual output should start
 * cat_uuid  - Show only announcements of this category, if UUID is not set than show all announcements.
 * ann_uuid  - Uuid of a single announcement that should be shown.
 * date_from - is set to 01.01.1970,
 *             if no date information is delivered
 * date_to   - is set to actual date,
 *             if no date information is delivered
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['announcements_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'int');
$getCatUuid  = admFuncVariableIsValid($_GET, 'cat_uuid', 'string');
$getAnnUuid  = admFuncVariableIsValid($_GET, 'ann_uuid', 'string');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

// check if module is enabled
if ((int) $gSettingsManager->get('announcements_module_enabled') === 0) {
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('announcements_module_enabled') === 2) {
    // Access only with valid login
    require(__DIR__ . '/../../system/login_valid.php');
}

$headline = $gL10n->get('SYS_ANNOUNCEMENTS');
$category = new TableCategory($gDb);

if ($getCatUuid !== '') {
    $category->readDataByUuid($getCatUuid);
    $headline .= ' - '.$category->getValue('cat_name');
}

// create object for announcements
$announcements = new ModuleAnnouncements();
$announcements->setParameter('ann_uuid', $getAnnUuid);
$announcements->setParameter('cat_id', $category->getValue('cat_id'));
$announcements->setDateRange($getDateFrom, $getDateTo);

// get parameters and number of recordsets
$announcementsCount = $announcements->getDataSetCount();

try {
    // add url to navigation stack
    if ($getAnnUuid !== '') {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-newspaper');
    }
} catch (AdmException $e) {
    $e->showHtml();
}

// create html page object
$page = new HtmlPage('admidio-announcements', $headline);

// add rss feed to announcements
if ($gSettingsManager->getBool('enable_rss')) {
    $page->addRssFile(
        ADMIDIO_URL.FOLDER_MODULES.'/announcements/rss_announcements.php',
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $headline))
    );
}

// number of announcements per page
if ($gSettingsManager->getInt('announcements_per_page') > 0) {
    $announcementsPerPage = $gSettingsManager->getInt('announcements_per_page');
} else {
    $announcementsPerPage = $announcementsCount;
}

// create module specific functions menu
if (count($gCurrentUser->getAllEditableCategories('ANN')) > 0) {
    // show link to create new announcement
    $page->addPageFunctionsMenuItem(
        'menu_item_announcement_add',
        $gL10n->get('SYS_CREATE_ENTRY'),
        ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php',
        'fa-plus-circle'
    );
}

if ($gCurrentUser->editAnnouncements()) {
    $page->addPageFunctionsMenuItem(
        'menu_item_announcement_categories',
        $gL10n->get('SYS_EDIT_CATEGORIES'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'ANN')),
        'fa-th-large'
    );
}

// add filter navbar
$page->addJavascript(
    '
    $("#cat_uuid").change(function() {
        $("#navbar_filter_form").submit();
    });',
    true
);

if ($getAnnUuid === '') {
    // create filter menu with elements for category
    $filterNavbar = new HtmlNavbar('navbar_filter', '', null, 'filter');
    $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', $page, array('type' => 'navbar', 'setFocus' => false));
    $form->addSelectBoxForCategories(
        'cat_uuid',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'ANN',
        HtmlForm::SELECT_BOX_MODUS_FILTER,
        array('defaultValue' => $getCatUuid)
    );
    $filterNavbar->addForm($form->show());
    $page->addHtml($filterNavbar->show());
}

if ($announcementsCount === 0) {
    // no announcements found
    if ($getAnnUuid !== '') {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
    } else {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
} else {
    // get all recordsets
    $announcementsArray = $announcements->getDataSet($getStart, $announcementsPerPage);
    $announcement = new TableAnnouncement($gDb);

    // show all announcements
    foreach ($announcementsArray['recordset'] as $row) {
        $announcement->clear();
        $announcement->setArray($row);

        $annUuid = $announcement->getValue('ann_uuid');

        $page->addHtml('
        <div class="card admidio-blog" id="ann_'.$annUuid.'">
            <div class="card-header">
                <i class="fas fa-newspaper"></i>' . $announcement->getValue('ann_headline'));

        // check if the user could edit this announcement
        if ($announcement->isEditable()) {
            $page->addHtml('
                    <div class="dropdown float-right">
                        <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_uuid' => $annUuid, 'copy' => '1')).'">
                                <i class="fas fa-clone" data-toggle="tooltip"></i> '.$gL10n->get('SYS_COPY').'</a>
                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_uuid' => $annUuid)).'">
                                <i class="fas fa-edit" data-toggle="tooltip"></i> '.$gL10n->get('SYS_EDIT').'</a>
                            <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'ann', 'element_id' => 'ann_'.$annUuid, 'name' => $announcement->getValue('ann_headline'), 'database_id' => $annUuid)).'">
                                <i class="fas fa-trash-alt" data-toggle="tooltip"></i> '.$gL10n->get('SYS_DELETE').'</a>
                        </div>
                    </div>');
        }
        $page->addHtml('</div>

            <div class="card-body">'.
                $announcement->getValue('ann_description').
            '</div>
            <div class="card-footer">'.
                // show information about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName(
                    $row['create_name'],
                    $announcement->getValue('ann_timestamp_create'),
                    $row['change_name'],
                    $announcement->getValue('ann_timestamp_change'),
                    $row['create_uuid'],
                    $row['change_uuid']
                ) .
                '<div class="admidio-info-category">' .
                    $gL10n->get('SYS_CATEGORY') .
                    '&nbsp;<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('cat_uuid' => $announcement->getValue('cat_uuid'))).'">' . $announcement->getValue('cat_name').'</a>
                </div>
            </div>
        </div>');
    }  // Ende foreach

    // If necessary show links to navigate to next and previous recordsets of the query
    $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('cat_uuid' => $getCatUuid));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $announcementsCount, $announcementsPerPage, $getStart));
}

// show html of complete page
$page->show();
