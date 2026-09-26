<?php

namespace Admidio\UI\Presenter;

use Admidio\Categories\Entity\Category;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\CollapsibleHtml;
use Admidio\Weblinks\Entity\Weblink;
use Admidio\Weblinks\Service\WeblinksService;

class WeblinksPresenter extends PagePresenter
{
    public function createCards(string $categoryUUID = '', string $linkUUID = '', int $start = 0): void
    {
        global $gDb, $gCurrentUser, $gCurrentSession, $gL10n, $gSettingsManager, $gCurrentOrganization;

        $this->setHtmlID('adm_links');
        $headline = $gL10n->get('SYS_WEBLINKS');
        $category = new Category($gDb);
        if ($categoryUUID !== '') {
            $category->readDataByUuid($categoryUUID);
            $headline .= ' - ' . $category->getValue('cat_name');
        }
        $this->setHeadline($headline);

        $links = new WeblinksService($gDb);
        $categoryId = (int)$category->getValue('cat_id');
        $count = $links->count($categoryId, $linkUUID);
        $perPage = $gSettingsManager->getInt('weblinks_per_page');
        $records = $links->findAll($categoryId, $linkUUID, $start, $perPage);

        if ($gSettingsManager->getBool('enable_rss')) {
            $this->addRssFile(
                ADMIDIO_URL . '/rss/links.php?organization=' . $gCurrentOrganization->getValue('org_shortname'),
                $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $headline))
            );
        }

        if ($linkUUID === '') {
            if (count($gCurrentUser->getAllEditableCategories('LNK')) > 0) {
                $this->addPageFunctionsMenuItem('menu_item_links_add', $gL10n->get('SYS_CREATE_WEBLINK'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('mode' => 'edit')), 'bi-plus-circle-fill');
            }
            if ($gCurrentUser->isAdministratorWeblinks()) {
                $this->addPageFunctionsMenuItem('menu_item_links_maintain_categories', $gL10n->get('SYS_EDIT_CATEGORIES'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'LNK')), 'bi-hdd-stack-fill');
            }
            ChangelogService::displayHistoryButton($this, 'weblinks', 'links');

            $form = new FormPresenter('adm_navbar_filter_form', 'sys-template-parts/form.filter.tpl',
                ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', $this, array('type' => 'navbar', 'setFocus' => false));
            $form->addSelectBoxForCategories('cat_uuid', $gL10n->get('SYS_CATEGORY'), $gDb, 'LNK',
                FormPresenter::SELECT_BOX_MODUS_FILTER, array('defaultValue' => $categoryUUID));
            $form->addToHtmlPage();

            $this->addJavascript('
                $("#cat_uuid").change(function() { $("#adm_navbar_filter_form").submit(); });
                $(".admidio-link-move").click(function() {
                    moveTableRow($(this), "' . ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php", "' . $gCurrentSession->getCsrfToken() . '");
                });
                function updateWeblinkMoveActions() {
                    $(".admidio-weblinks-grid").each(function() {
                        var cards = $(this).children("[id^=lnk_]").filter(function() {
                            return $(this).css("display") !== "none";
                        });
                        cards.each(function(index) {
                            $(this).find(".admidio-link-move[data-direction=UP]").toggle(index > 0);
                            $(this).find(".admidio-link-move[data-direction=DOWN]").toggle(index < cards.length - 1);
                        });
                    });
                }
                $(document).ajaxComplete(function(event, xhr, settings) {
                    if (settings.url.indexOf("mode=delete") !== -1) {
                        setTimeout(updateWeblinkMoveActions, 1000);
                    } else {
                        updateWeblinkMoveActions();
                    }
                });
                updateWeblinkMoveActions();', true);
        }

        $weblink = new Weblink($gDb);
        $categories = array();
        foreach ($records as $row) {
            $weblink->clear();
            $weblink->setArray($row);
            $uuid = $weblink->getValue('lnk_uuid');
            $catId = (int)$weblink->getValue('lnk_cat_id');
            $destination = (string)$weblink->getValue('lnk_url', 'database');
            $destinationHost = parse_url($destination, PHP_URL_HOST);
            $description = trim((string)$weblink->getValue('lnk_description'));
            $description = CollapsibleHtml::render(
                $description,
                $gSettingsManager->getInt('weblinks_preview_characters'),
                'viewdetails-link-' . $uuid,
                $gL10n->get('SYS_SHOW_MORE')
            );
            if (!isset($categories[$catId])) {
                $categories[$catId] = array('name' => $weblink->getValue('cat_name'), 'links' => array());
            }
            $categories[$catId]['links'][] = array(
                'uuid' => $uuid,
                'name' => $weblink->getValue('lnk_name'),
                'description' => $description,
                'destination' => $weblink->getValue('lnk_url'),
                'destinationHost' => $destinationHost ?: $destination,
                'counter' => (int)$weblink->getValue('lnk_counter'),
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('mode' => 'redirect', 'link_uuid' => $uuid)),
                'editUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('mode' => 'edit', 'link_uuid' => $uuid)),
                'deleteUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('mode' => 'delete', 'link_uuid' => $uuid)),
                'editable' => $weblink->isEditable()
            );
        }

        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('cat_uuid' => $categoryUUID));
        $this->smarty->assign('categories', array_values($categories));
        $this->smarty->assign('singleLink', $linkUUID !== '');
        $this->smarty->assign('target', $gSettingsManager->getString('weblinks_target'));
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('pagination', admFuncGeneratePagination($baseUrl, $count, $perPage > 0 ? $perPage : $count, $start));
        $this->addHtmlByTemplate('modules/weblinks.cards.tpl');
    }

    public function createEditForm(string $uuid): void
    {
        global $gDb, $gCurrentUser, $gCurrentSession, $gL10n;

        $link = new Weblink($gDb);
        if ($uuid !== '') {
            if (!$link->readDataByUuid($uuid) || !$link->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } elseif (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $this->setHtmlID('adm_links_edit');
        $this->setHeadline($gL10n->get($uuid !== '' ? 'SYS_EDIT_WEBLINK' : 'SYS_CREATE_WEBLINK'));
        ChangelogService::displayHistoryButton($this, 'weblinks', 'links', $uuid !== '', array('uuid' => $uuid));

        $form = new FormPresenter('adm_weblinks_edit_form', 'modules/weblinks.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/weblinks.php', array('mode' => 'save', 'link_uuid' => $uuid)), $this);
        $form->addInput('lnk_name', $gL10n->get('SYS_LINK_NAME'), $link->getValue('lnk_name'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED));
        $form->addInput('lnk_url', $gL10n->get('SYS_LINK_ADDRESS'), $link->getValue('lnk_url'),
            array('type' => 'url', 'maxLength' => 2000, 'property' => FormPresenter::FIELD_REQUIRED));
        $form->addSelectBoxForCategories('lnk_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'LNK',
            FormPresenter::SELECT_BOX_MODUS_EDIT, array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $link->getValue('cat_uuid')));
        $form->addEditor('lnk_description', $gL10n->get('SYS_DESCRIPTION'), $link->getValue('lnk_description'),
            array('toolbar' => 'AdmidioComments'));
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $link->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $link->getValue('lnk_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $link->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $link->getValue('lnk_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    public function createRedirectPage(string $uuid, string $url): void
    {
        global $gDb, $gL10n, $gSettingsManager, $gCurrentOrganization;

        $link = new Weblink($gDb);
        $link->readDataByUuid($uuid);
        $seconds = $gSettingsManager->getInt('weblinks_redirect_seconds');
        $this->setHtmlID('adm_links_redirect');
        $this->setHeadline($gL10n->get('SYS_REDIRECT'));
        $this->addHeader('<meta http-equiv="refresh" content="' . $seconds . '; url=' . htmlspecialchars($url, ENT_QUOTES) . '">');
        $this->addJavascript('
            function countDown(init) {
                if (init || --document.getElementById("counter").firstChild.nodeValue > 0) {
                    window.setTimeout("countDown()", 1000);
                }
            }
            countDown(true);');
        $this->smarty->assign('message', $gL10n->get('SYS_REDIRECT_DESC', array(
            $gCurrentOrganization->getValue('org_longname'),
            '<span id="counter">' . $seconds . '</span>',
            '<strong>' . htmlspecialchars($link->getValue('lnk_name'), ENT_QUOTES) . '</strong> (' . htmlspecialchars($url, ENT_QUOTES) . ')',
            '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_self">', '</a>'
        )));
        $this->addHtmlByTemplate('modules/weblinks.redirect.tpl');
    }
}
