<?php

namespace Admidio\UI\Presenter;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Categories\Service\CategoryService;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief Class with methods to display the module pages of announcements.
 *
 * This class adds some functions that are used in the announcements module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available announcements
 * $page = new AnnouncementsPresenter();
 * $page->createCards();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class AnnouncementsPresenter extends PagePresenter
{
    /**
     * @var string UUID of the category for which the topics should be filtered.
     */
    protected string $categoryUUID = '';
    /**
     * @var CategoryService An object of the class CategoryService to get all categories.
     */
    protected CategoryService $categories;
    /**
     * @var array Array with all read forum topics and their first post.
     */
    protected array $data = array();
    /**
     * @var array Array with all read groups and roles
     */
    protected array $templateData = array();

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @throws Exception
     */
    public function __construct(string $categoryUUID = '')
    {
        global $gDb;

        $this->categoryUUID = $categoryUUID;
        $this->categories = new CategoryService($gDb, 'FOT');

        parent::__construct($categoryUUID);
    }

    /**
     * Create content that is used on several pages and could be called in other methods. It will
     * create a functions menu and a filter navbar.
     * @param string $view Name of the view that should be created. This could be 'cards' or 'list'.
     * @return void
     * @throws Exception
     */
    protected function createSharedHeader(string $view): void
    {
        global $gCurrentUser, $gL10n, $gDb, $gSettingsManager, $gCurrentOrganization;

        $this->setHeadline($gL10n->get('SYS_ANNOUNCEMENTS'));

        // add rss feed to announcements
        if ($gSettingsManager->getBool('enable_rss')) {
            $this->addRssFile(
                ADMIDIO_URL . '/rss/announcements.php?organization=' . $gCurrentOrganization->getValue('org_shortname'),
                $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $this->getHeadline()))
            );
        }

        // create module specific functions menu
        if (count($gCurrentUser->getAllEditableCategories('ANN')) > 0) {
            // show link to create new announcement
            $this->addPageFunctionsMenuItem(
                'menu_item_announcement_add',
                $gL10n->get('SYS_CREATE_ENTRY'),
                ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_new.php',
                'bi-plus-circle-fill'
            );
        }

        ChangelogService::displayHistoryButton($this, 'announcements', 'announcements');

        if ($gCurrentUser->editAnnouncements()) {
            $this->addPageFunctionsMenuItem(
                'menu_item_announcement_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'ANN')),
                'bi-hdd-stack-fill'
            );
        }

        // add filter navbar
        $this->addJavascript('
            $("#cat_uuid").change(function() {
                $("#adm_navbar_filter_form").submit();
            });', true
        );
/*
        //if ($getAnnUuid === '') {
            // create filter menu with elements for category
            $form = new FormPresenter(
                'adm_navbar_filter_form',
                'sys-template-parts/form.filter.tpl',
                ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php',
                $this,
                array('type' => 'navbar', 'setFocus' => false)
            );
            $form->addSelectBoxForCategories(
                'cat_uuid',
                $gL10n->get('SYS_CATEGORY'),
                $gDb,
                'ANN',
                FormPresenter::SELECT_BOX_MODUS_FILTER,
                array('defaultValue' => $this->categoryUUID)
            );
            $form->addToHtmlPage();
        //}*/
    }

    /**
     * Read all available forum topics from the database and create a Bootstrap card for each topic.
     * @param int $offset Offset of the first record that should be returned.
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function createCards(int $offset = 0): void
    {
        global $gL10n, $gSettingsManager, $gDb;

        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'cards', 'category_uuid' => $this->categoryUUID));

        $this->prepareData($offset);
        $announcementsService = new AnnouncementsService($gDb, $this->categoryUUID);

        $this->setHtmlID('adm_announcements_cards');
        $this->createSharedHeader('cards');

        if (count($this->categories->getVisibleCategories()) > 1) {
            $this->smarty->assign('showCategories', true);
        } else {
            $this->smarty->assign('showCategories', false);
        }

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('pagination', admFuncGeneratePagination($baseUrl, $announcementsService->count(), $gSettingsManager->getInt('announcements_per_page'), $offset, true, 'offset'));
        try {
            $this->pageContent .= $this->smarty->fetch('modules/announcements.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Read all available forum topics from the database and an HTML list with all topics.
     * @param int $offset Offset of the first record that should be returned.
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function createList(int $offset = 0): void
    {
        global $gL10n, $gSettingsManager, $gDb;

        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'cards', 'cat_uuid' => $this->categoryUUID));

        $this->prepareData($offset);
        $categoryService = new ForumService($gDb, $this->categoryUUID);

        $this->setHtmlID('adm_forum_cards');
        $this->createSharedHeader('list');

        if (count($this->categories->getVisibleCategories()) > 1) {
            $this->smarty->assign('showCategories', true);
        } else {
            $this->smarty->assign('showCategories', false);
        }

        $this->smarty->assign('list', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('pagination', admFuncGeneratePagination($baseUrl, $categoryService->getTopicCount(), $gSettingsManager->getInt('forum_topics_per_page'), $offset, true, 'offset'));
        try {
            $this->pageContent .= $this->smarty->fetch('modules/forum.list.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param int $offset Offset of the first record that should be returned.
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData(int $offset = 0): void
    {
        global $gSettingsManager, $gDb, $gCurrentUser, $gL10n, $gCurrentSession;

        $announcementsService = new AnnouncementsService($gDb, $this->categoryUUID);
        $data = $announcementsService->findAll($offset, $gSettingsManager->getInt('announcements_per_page'));
        $announcement = new Announcement($gDb);

        foreach ($data as $announcementData) {
            $announcement->clear();
            $announcement->setArray($announcementData);

            $templateRow = array();
            $templateRow['uuid'] = $announcementData['ann_uuid'];
            $templateRow['title'] = $announcement->getValue('ann_headline');
            $templateRow['description'] = $announcement->getValue('ann_description');

            $templateRow['userCreatedUUID'] = $announcementData['create_uuid'];
            if ($gSettingsManager->getInt('system_show_create_edit') === 2) {
                $templateRow['userCreatedName'] = $announcementData['create_login_name'];
            } else {
                $templateRow['userCreatedName'] = $announcementData['create_firstname'] . ' ' . $announcementData['create_surname'];
            }
            $templateRow['userCreatedName'] = '<a href="' . SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $announcementData['create_uuid'])) .
                '" title="' . $gL10n->get('SYS_PROFILE') . '">' . $templateRow['userCreatedName'] . '</a>';
            $templateRow['userCreatedProfilePhotoUrl'] = SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php',
                array('user_uuid' => $announcementData['create_uuid'], 'timestamp' => $announcementData['create_timestamp_change'])
            );
            $datetime = new \DateTime($announcementData['ann_timestamp_create']);
            $templateRow['userCreatedTimestamp'] = $datetime->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'cards', 'announcement_uuid' => $announcementData['ann_uuid']));
            $templateRow['categoryUUID'] = $announcementData['cat_uuid'];
            $templateRow['category'] = Language::translateIfTranslationStrId($announcementData['cat_name']);
            $templateRow['editable'] = false;

            if ($gCurrentUser->editAnnouncements()
                || $gCurrentUser->getValue('usr_uuid') === $announcementData['usr_uuid']) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'copy', 'announcement_uuid' => $announcementData['ann_uuid'])),
                    'icon' => 'bi bi-copy',
                    'tooltip' => $gL10n->get('SYS_COPY')
                );
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'edit', 'announcement_uuid' => $announcementData['ann_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT')
                );
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'adm_announcement_' . $announcementData['ann_uuid'] . '\', \'' .
                        SecurityUtils::encodeUrl(
                            ADMIDIO_URL . '/adm_program/modules/announcements.php',
                            array('mode' => 'delete', 'announcement_uuid' => $announcementData['ann_uuid'])
                        ) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($announcementData['ann_headline'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE')
                );
            }

            $this->templateData[] = $templateRow;
        }
    }
}
