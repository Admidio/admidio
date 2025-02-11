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

use Admidio\Announcements\Entity\Announcement;
use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getAnnUuid = admFuncVariableIsValid($_GET, 'ann_uuid', 'uuid');
    $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
    $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date');

    // check if module is enabled
    if ((int)$gSettingsManager->get('announcements_module_enabled') === 0) {
        // module is disabled
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('announcements_module_enabled') === 2) {
        // Access only with valid login
        require(__DIR__ . '/../../system/login_valid.php');
    }

    $headline = $gL10n->get('SYS_ANNOUNCEMENTS');
    $category = new Category($gDb);

    if ($getCatUuid !== '') {
        $category->readDataByUuid($getCatUuid);
        $headline .= ' - ' . $category->getValue('cat_name');
    }

    // create object for announcements
    $announcements = new ModuleAnnouncements();
    $announcements->setParameter('ann_uuid', $getAnnUuid);
    $announcements->setParameter('cat_id', $category->getValue('cat_id'));
    $announcements->setDateRange($getDateFrom, $getDateTo);

    // get parameters and number of data records
    $announcementsCount = $announcements->getDataSetCount();

    // add url to navigation stack
    if ($getAnnUuid !== '') {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-newspaper');
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-announcements', $headline);

    // add rss feed to announcements
    if ($gSettingsManager->getBool('enable_rss')) {
        $page->addRssFile(
            ADMIDIO_URL . FOLDER_MODULES . '/announcements/rss_announcements.php?organization_short_name=' . $gCurrentOrganization->getValue('org_shortname'),
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
            ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_new.php',
            'bi-plus-circle-fill'
        );
    }

    ChangelogService::displayHistoryButton($page, 'announcements', 'announcements');

    if ($gCurrentUser->editAnnouncements()) {
        $page->addPageFunctionsMenuItem(
            'menu_item_announcement_categories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'ANN')),
            'bi-hdd-stack-fill'
        );
    }

    // add filter navbar
    $page->addJavascript(
        '
        $("#cat_uuid").change(function() {
            $("#adm_navbar_filter_form").submit();
        });',
        true
    );

    if ($getAnnUuid === '') {
        // create filter menu with elements for category
        $form = new FormPresenter(
            'adm_navbar_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php',
            $page,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'ANN',
            FormPresenter::SELECT_BOX_MODUS_FILTER,
            array('defaultValue' => $getCatUuid)
        );
        $form->addToHtmlPage();
    }

    if ($announcementsCount === 0) {
        // no announcements found
        if ($getAnnUuid !== '') {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
        } else {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
        }
    } else {
        // get all data records
        $announcementsArray = $announcements->getDataSet($getStart, $announcementsPerPage);
        $announcement = new Announcement($gDb);

        // show all announcements
        foreach ($announcementsArray['recordset'] as $row) {
            $announcement->clear();
            $announcement->setArray($row);

            $announcementUUID = $announcement->getValue('ann_uuid');

            $page->addHtml('
        <div class="card admidio-blog" id="ann_' . $announcementUUID . '">
            <div class="card-header">
                <i class="bi bi-newspaper"></i>' . $announcement->getValue('ann_headline'));

            // check if the user could edit this announcement
            if ($announcement->isEditable()) {
                $page->addHtml('
                    <div class="dropdown float-end">
                        <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_new.php', array('ann_uuid' => $announcementUUID, 'copy' => '1')) . '">
                                <i class="bi bi-copy" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_COPY') . '</a>
                            </li>
                            <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_new.php', array('ann_uuid' => $announcementUUID)) . '">
                                <i class="bi bi-pencil-square" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_EDIT') . '</a>
                            </li>
                            <li><a class="dropdown-item admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($announcement->getValue('ann_headline', 'database'))) . '"
                                data-href="callUrlHideElement(\'ann_' . $announcementUUID . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_function.php', array('mode' => 'delete', 'ann_uuid' => $announcementUUID)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                                <i class="bi bi-trash" data-bs-toggle="tooltip"></i> ' . $gL10n->get('SYS_DELETE') . '</a>
                            </li>
                        </ul>
                    </div>');
            }
            $page->addHtml('</div>

            <div class="card-body">
                ' . $announcement->getValue('ann_description') . '
            </div>
            <div class="card-footer">' .
                // show information about user who creates the recordset and changed it
                admFuncShowCreateChangeInfoByName(
                    $row['create_name'],
                    $announcement->getValue('ann_timestamp_create'),
                    (string)$row['change_name'],
                    $announcement->getValue('ann_timestamp_change'),
                    $row['create_uuid'],
                    (string)$row['change_uuid']
                ) .
                '<div class="admidio-info-category">' .
                    $gL10n->get('SYS_CATEGORY') .
                    '&nbsp;<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php', array('cat_uuid' => $announcement->getValue('cat_uuid'))) . '">' . $announcement->getValue('cat_name') . '</a>
                </div>
            </div>
        </div>');
        }  // Ende foreach

        // If necessary show links to navigate to next and previous data records of the query
        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php', array('cat_uuid' => $getCatUuid));
        $page->addHtml(admFuncGeneratePagination($baseUrl, $announcementsCount, $announcementsPerPage, $getStart));
    }

    // show html of complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
