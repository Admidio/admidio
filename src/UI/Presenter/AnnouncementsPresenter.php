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
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'edit')),
                'bi-plus-circle-fill'
            );
        }

        ChangelogService::displayHistoryButton($this, 'announcements', 'announcements');

        if ($gCurrentUser->isAdministratorAnnouncements()) {
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
     * Create the data for the edit form of an announcement.
     * @param string $announcementUUID UUID of the announcement that should be edited.
     * @param bool $copy Flag if the announcement should be copied.
     * @return void
     * @throws Exception
     */
    public function createEditForm(string $announcementUUID, bool $copy): void
    {
        global $gL10n, $gCurrentUser, $gDb, $gCurrentSession;

        $this->setHtmlID('adm_announcements_edit');

        if ($copy) {
            $this->setHeadline($gL10n->get('SYS_COPY_ENTRY'));
        } elseif ($announcementUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_ENTRY'));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_ENTRY'));
        }

        // Create announcements object
        $announcement = new Announcement($gDb);

        if ($announcementUUID !== '') {
            $announcement->readDataByUuid($announcementUUID);

            if ($copy === true) {
                $announcementUUID = '';
            }

            // check if the current user could edit this announcement
            if (!$announcement->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } else {
            // check if the user has the right to edit at least one category
            if (count($gCurrentUser->getAllEditableCategories('ANN')) === 0) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        ChangelogService::displayHistoryButton($this, 'announcements', 'announcements', !empty($getAnnUuid), array('uuid' => $announcementUUID));

        // show form
        $form = new FormPresenter(
            'adm_announcements_edit_form',
            'modules/announcements.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'edit', 'announcement_uuid' => $announcementUUID)),
            $this
        );
        $form->addInput(
            'ann_headline',
            $gL10n->get('SYS_TITLE'),
            $announcement->getValue('ann_headline'),
            array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addSelectBoxForCategories(
            'ann_cat_id',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'ANN',
            FormPresenter::SELECT_BOX_MODUS_EDIT,
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $announcement->getValue('cat_uuid'))
        );
        $form->addEditor(
            'ann_description',
            $gL10n->get('SYS_TEXT'),
            $announcement->getValue('ann_description'),
            array('property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $announcement->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $announcement->getValue('ann_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $announcement->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $announcement->getValue('ann_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
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

            if ($announcement->isEditable()) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'edit', 'copy' => true, 'announcement_uuid' => $announcementData['ann_uuid'])),
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
                            ADMIDIO_URL . FOLDER_MODULES . '/announcements.php',
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
