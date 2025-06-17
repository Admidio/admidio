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

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Weblinks\Entity\Weblink;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'uuid');

    // check if the module is enabled for use
    if ($gSettingsManager->getInt('weblinks_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ($gSettingsManager->getInt('weblinks_module_enabled') === 2) {
        // available only with valid login
        require(__DIR__ . '/../../system/login_valid.php');
    }

    $headline = $gL10n->get('SYS_WEBLINKS');

    $category = new Category($gDb);

    if ($getCatUuid !== '') {
        $category->readDataByUuid($getCatUuid);
        $headline .= ' - ' . $category->getValue('cat_name');
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
    if ($getLinkUuid !== '') {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-link-45deg');
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-weblinks', $headline);

    if ($gSettingsManager->getBool('enable_rss')) {
        $page->addRssFile(
            ADMIDIO_URL . '/rss/links.php?organization_short_name=' . $gCurrentOrganization->getValue('org_shortname'),
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
                ADMIDIO_URL . FOLDER_MODULES . '/links/links_new.php',
                'bi-plus-circle-fill'
            );
        }

        if ($gCurrentUser->isAdministratorWeblinks()) {
            // show link to maintain categories
            $page->addPageFunctionsMenuItem(
                'menu_item_links_maintain_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'LNK')),
                'bi-hdd-stack-fill'
            );
        }

        ChangelogService::displayHistoryButton($page, 'weblinks', 'links');

        $page->addJavascript('
            $("#cat_uuid").change(function() {
                $("#adm_navbar_filter_form").submit();
            });
            $(".admidio-field-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/links/links_function.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });',
            true
        );

        // create filter menu with elements for category
        $form = new FormPresenter(
            'adm_navbar_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL . FOLDER_MODULES . '/links/links.php',
            $page,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'LNK',
            FormPresenter::SELECT_BOX_MODUS_FILTER,
            array('defaultValue' => $getCatUuid)
        );
        $form->addToHtmlPage();
    }

    if ($weblinksCount === 0) {
        // no weblink found
        if ($weblinks->getId() > 0) {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
        } else {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
        }
    } else {
        $getStart = $weblinks->getStartElement();
        $weblinksDataSet = $weblinks->getDataSet($getStart);
        $weblink = new Weblink($gDb);

        $j = 0;         // counter for fetchObject
        $i = 0;         // counter for links in category
        $previousCatId = -1;  // previous category ID
        $newCategory = true;  // maybe new category

        if ($weblinksDataSet['numResults'] > 0) {
            // show all weblinks
            foreach ($weblinksDataSet['recordset'] as $row) {
                // initialize weblink object and read new recordset into this object
                $weblink->clear();
                $weblink->setArray($row);

                $lnkUuid = $weblink->getValue('lnk_uuid');
                $lnkCatId = (int)$weblink->getValue('lnk_cat_id');
                $lnkName = $weblink->getValue('lnk_name');
                $lnkDescription = $weblink->getValue('lnk_description');
                // count the number of links in this category
                $lnkCatCount = count(array_filter($weblinksDataSet['recordset'], function($record) use ($lnkCatId) {
                    return (int)$record['lnk_cat_id'] === $lnkCatId;
                }));

                if ($lnkCatId !== $previousCatId) {
                    $i = 0;
                    $newCategory = true;
                    if ($j > 0) {
                        $page->addHtml('</div></div>');
                    }
                    $page->addHtml('<div class="card admidio-blog">
                    <div class="card-header">' . $weblink->getValue('cat_name') . '</div>
                    <div class="card-body">');
                }

                $page->addHtml('<div class="mb-3" id="lnk_' . $lnkUuid . '">');

                // show weblink
                $page->addHtml('
                <a class="icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_redirect.php', array('link_uuid' => $lnkUuid)) . '" target="' . $gSettingsManager->getString('weblinks_target') . '">
                    <i class="bi bi-link"></i>' . $lnkName . '</a>');

                // change and delete only users with rights
                if ($weblink->isEditable()) {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_new.php', array('link_uuid' => $lnkUuid)) . '">
                        <i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>'
                    );
                    //  only show up arrow if this is not the first link in the category
                    if ($i > 0) {
                        $page->addHtml('
                            <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="' . $lnkUuid . '"
                                data-direction="UP" data-target="lnk_' . $lnkUuid . '">
                                <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array('SYS_WEBLINK')) . '"></i></a>'
                        );
                    }
                    // only show down arrow if this is not the last link in the category
                    if ($i < $lnkCatCount - 1) {
                        // show down arrow
                        $page->addHtml('
                            <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="' . $lnkUuid . '"
                                data-direction="DOWN" data-target="lnk_' . $lnkUuid . '">
                                <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array('SYS_WEBLINK')) . '"></i></a>'
                        );
                    }
                    $page->addHtml('
                        <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                            data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($weblink->getValue('lnk_name', 'database'))) . '"
                            data-href="callUrlHideElement(\'lnk_' . $lnkUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links_function.php', array('mode' => 'delete', 'link_uuid' => $lnkUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i></a>'
                    );
                }

                // get available description
                if (strlen($lnkDescription) > 0) {
                    $page->addHtml('<div class="admidio-weblink-description">' . $lnkDescription . '</div>');
                }

                $page->addHtml('<div class="weblink-counter"><small>' . $gL10n->get('SYS_COUNTER') . ': ' . (int)$weblink->getValue('lnk_counter') . '</small></div>
            </div>');

                ++$j;
                ++$i;

                // set current category to previous
                $previousCatId = $lnkCatId;

                $newCategory = false;
            }  // End While-loop

            $page->addHtml('</div></div>');
        } else {
            // No links or 1 link is hidden
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
        }
    } // end if at least 1 recordset

    $page->addHtml('</div>');

    // If necessary show links to navigate to next and previous records of the query
    $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/links/links.php', array('cat_uuid' => $getCatUuid));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $weblinksCount, $weblinksPerPage, $weblinks->getStartElement()));

    // show html of complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
