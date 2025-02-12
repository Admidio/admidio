<?php

namespace Admidio\UI\Presenter;

use Admidio\Categories\Service\CategoryService;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief Class with methods to display the module pages of the registration.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ForumPresenter extends PagePresenter
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

        $this->setHeadline($gL10n->get('SYS_FORUM'));

        // add rss feed to forum
        if ($gSettingsManager->getBool('enable_rss') && $gSettingsManager->getInt('forum_module_enabled') === 1) {
            $this->addRssFile(
                ADMIDIO_URL . '/rss/forum.php?organization=' . $gCurrentOrganization->getValue('org_shortname'),
                $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $gL10n->get('SYS_FORUM')))
            );
        }

        // show link to create new topic
        $this->addPageFunctionsMenuItem(
            'menu_item_forum_topic_add',
            $gL10n->get('SYS_CREATE_VAR', array('SYS_TOPIC')),
            ADMIDIO_URL . FOLDER_MODULES . '/forum.php?mode=topic_edit',
            'bi-plus-circle-fill'
        );

        if ($gCurrentUser->administrateForum()) {
            $this->addPageFunctionsMenuItem(
                'menu_item_forum_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'FOT')),
                'bi-hdd-stack-fill'
            );
        }

        // add filter navbar
        $this->addJavascript('
            $("#category_uuid").change(function() {
                $("#adm_navbar_forum_filter_form").submit();
            });',
            true
        );

        // create filter menu with elements for category
        $form = new FormPresenter(
            'adm_navbar_forum_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL . FOLDER_MODULES . '/forum.php',
            $this,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addButtonGroupRadio(
            'adm_forum_view',
            array(array('adm_forum_view_cards', $gL10n->get('SYS_DETAILED'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'cards'))),
                array('adm_forum_view_list', $gL10n->get('SYS_LIST'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'list')))
            ),
            array('defaultValue' => 'adm_forum_view_' . $view)
        );
        if (count($this->categories->getVisibleCategories()) > 1) {
            $form->addSelectBoxForCategories(
                'category_uuid',
                $gL10n->get('SYS_CATEGORY'),
                $gDb,
                'FOT',
                FormPresenter::SELECT_BOX_MODUS_FILTER,
                array('defaultValue' => $this->categoryUUID)
            );
        }
        $form->addToHtmlPage();
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

        $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'cards', 'cat_uuid' => $this->categoryUUID));

        $this->prepareData($offset);
        $categoryService = new ForumService($gDb, $this->categoryUUID);

        $this->setHtmlID('adm_forum_cards');
        $this->createSharedHeader('cards');

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('pagination', admFuncGeneratePagination($baseUrl, $categoryService->getTopicCount(), $gSettingsManager->getInt('forum_topics_per_page'), $offset, true, 'offset'));
        try {
            $this->pageContent .= $this->smarty->fetch('modules/forum.cards.tpl');
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

        $forumService = new ForumService($gDb, $this->categoryUUID);
        $data = $forumService->getData($offset, $gSettingsManager->getInt('forum_topics_per_page'));

        foreach ($data as $forumTopic) {
            $templateRow = array();
            $templateRow['uuid'] = $forumTopic['fot_uuid'];
            $templateRow['title'] = $forumTopic['fot_title'];
            $templateRow['views'] = $forumTopic['fot_views'];

            $templateRow['repliesCount'] = $forumTopic['replies_count'];
            $lastReplyTimestamp = new \DateTime($forumTopic['last_reply_timestamp']);
            $templateRow['lastReplyTimestamp'] = $lastReplyTimestamp->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            if ($gSettingsManager->getInt('system_show_create_edit') === 2) {
                $templateRow['lastReplyUserName'] = $forumTopic['last_reply_login_name'];
            } else {
                $templateRow['lastReplyUserName'] = $forumTopic['last_reply_firstname'] . ' ' . $forumTopic['last_reply_surname'];
            }
            $templateRow['lastReplyUserNameWithLink'] = '<a href="' . SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $forumTopic['last_reply_usr_uuid'])) .
                '" title="' . $gL10n->get('SYS_PROFILE') . '">' . $templateRow['lastReplyUserName'] . '</a>';

            // calculate offset of last reply
            (float)$lastPage = ($forumTopic['replies_count'] + 1) / $gSettingsManager->getInt('forum_posts_per_page');
            if (fmod($lastPage, 1) == 0) {
                $lastPage = $lastPage - 1;
            } else {
                $lastPage = (int)$lastPage;
            }
            $lastOffset = ($lastPage * $gSettingsManager->getInt('forum_posts_per_page'));

            $templateRow['lastReplyUrl'] = SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/forum.php',
                array('mode' => 'topic', 'topic_uuid' => $forumTopic['fot_uuid'], 'offset' => $lastOffset),
                'adm_post_' . $forumTopic['last_reply_uuid']
            );
            $templateRow['lastReplyInfo'] = $gL10n->get('SYS_LAST_REPLY_BY_AT', array(
                '<a href="' . $templateRow['lastReplyUrl'] . '">',
                '</a>',
                $templateRow['lastReplyUserNameWithLink'],
                $templateRow['lastReplyTimestamp']));

            if (strlen($forumTopic['fop_text']) > 250) {
                $templateRow['text'] = substr(
                        substr(strip_tags($forumTopic['fop_text']), 0, 250),
                        0,
                        strrpos(substr(strip_tags($forumTopic['fop_text']), 0, 250), ' ')
                    ) . ' ...';
            } else {
                $templateRow['text'] = $forumTopic['fop_text'];
            }
            $templateRow['userUUID'] = $forumTopic['usr_uuid'];
            if ($gSettingsManager->getInt('system_show_create_edit') === 2) {
                $templateRow['userName'] = $forumTopic['usr_login_name'];
            } else {
                $templateRow['userName'] = $forumTopic['firstname'] . ' ' . $forumTopic['surname'];
            }
            $templateRow['userNameWithLink'] = '<a href="' . SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $forumTopic['usr_uuid'])) .
                '" title="' . $gL10n->get('SYS_PROFILE') . '">' . $templateRow['userName'] . '</a>';
            $templateRow['userProfilePhotoUrl'] = SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php',
                array('user_uuid' => $forumTopic['usr_uuid'], 'timestamp' => $forumTopic['usr_timestamp_change'])
            );
            $datetime = new \DateTime($forumTopic['fot_timestamp_create']);
            $templateRow['timestamp'] = $datetime->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic', 'topic_uuid' => $forumTopic['fot_uuid']));
            $templateRow['category'] = '';
            $templateRow['editable'] = false;

            if (count($this->categories->getVisibleCategories()) > 1) {
                $templateRow['category'] = Language::translateIfTranslationStrId($forumTopic['cat_name']);
            }

            if ($gCurrentUser->administrateForum()
                || $gCurrentUser->getValue('usr_uuid') === $forumTopic['usr_uuid']) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic_edit', 'topic_uuid' => $forumTopic['fot_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT_VAR', array('SYS_TOPIC'))
                );
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'adm_topic_' . $forumTopic['fot_uuid'] . '\', \'' .
                        SecurityUtils::encodeUrl(
                            ADMIDIO_URL . '/adm_program/modules/forum.php',
                            array('mode' => 'topic_delete', 'topic_uuid' => $forumTopic['fot_uuid'])
                        ) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($forumTopic['fot_title'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_VAR', array('SYS_TOPIC'))
                );
            }

            $this->templateData[] = $templateRow;
        }
    }
}
