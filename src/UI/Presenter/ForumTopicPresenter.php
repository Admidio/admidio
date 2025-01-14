<?php

namespace Admidio\UI\Presenter;

use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\UI\Presenter\FormPresenter;
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
class ForumTopicPresenter extends PagePresenter
{
    /**
     * @var array Array with all read forum topics and their first post.
     */
    protected array $data = array();
    /**
     * @var array Array with all read groups and roles
     */
    protected array $templateData = array();

    /**
     * Create content that is used on several pages and could be called in other methods. It will
     * create a functions menu and a filter navbar.
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @return void
     * @throws Exception
     */
    protected function createSharedHeader(string $categoryUUID = '')
    {
        global $gCurrentUser, $gL10n, $gDb;

        // show link to create new topic
        $this->addPageFunctionsMenuItem(
            'menu_item_forum_topic_add',
            $gL10n->get('SYS_CREATE_TOPIC'),
            ADMIDIO_URL . FOLDER_MODULES . '/forum.php?mode=topic_edit',
            'bi-plus-circle-fill'
        );

        if ($gCurrentUser->administrateForum()) {
            $this->addPageFunctionsMenuItem(
                'menu_item_announcement_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'FOT')),
                'bi-hdd-stack-fill'
            );
        }

        // add filter navbar
        $this->addJavascript('
            $("#role_type").change(function() {
                $("#adm_navbar_filter_form").submit();
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
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'ROL',
            FormPresenter::SELECT_BOX_MODUS_FILTER,
            array('defaultValue' => $categoryUUID)
        );
        $form->addToHtmlPage();
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @throws Exception
     */
    public function createCards(string $categoryUUID = '')
    {
        global $gL10n;

        $this->prepareData($categoryUUID);
        $this->createSharedHeader($categoryUUID);

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        try {
            $this->pageContent .= $this->smarty->fetch('modules/forum.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Create the data for the edit form of a menu entry.
     * @param string $topicUUID UUID of the topic that should be edited.
     * @throws Exception
     */
    public function createTopicEditForm(string $topicUUID = '')
    {
        global $gDb, $gL10n, $gCurrentSession;

        // create menu object
        $topic = new Topic($gDb);
        $post = new Post($gDb);
        $forumService = new ForumService($gDb);
        $categories = $forumService->getCategories();

        if ($topicUUID !== '') {
            $topic->readDataByUuid($topicUUID);
            $post->readDataById($topic->getValue('fot_fop_id_first_post'));
        }

        // show form
        $form = new FormPresenter(
            'adm_forum_topic_edit_form',
            'modules/forum.topic.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('topic_uuid' => $topicUUID, 'mode' => 'topic_save')),
            $this
        );
        if (count($categories) > 1) {
            $categoriesValues = array();
            $categoryDefault = '';
            foreach ($categories as $category) {
                $categoriesValues[$category['cat_uuid']] = $category['cat_name'];
                if ($category['cat_default'] == 1) {
                    $categoryDefault = $category['cat_uuid'];
                }
            }
            $form->addSelectBox(
                'adm_category_uuid',
                $gL10n->get('SYS_CATEGORY'),
                $categoriesValues,
                array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $categoryDefault)
            );
        }
        $form->addInput(
            'fot_title',
            $gL10n->get('SYS_TITLE'),
            $topic->getValue('fot_title'),
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addEditor(
            'fop_text',
            $gL10n->get('SYS_TEXT'),
            $post->getValue('fop_text'),
            array('property' => FormPresenter::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        $this->smarty->assign('nameUserCreated', $topic->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $topic->getValue('fot_timestamp_create'));
        $this->smarty->assign('nameLastUserEdited', $post->getNameOfLastEditingUser());
        $this->smarty->assign('timestampLastUserEdited', $post->getValue('fop_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Read the data of the forum in an array.
     * @param string $topicUUID UUID of the topic for which the posts should be filtered.
     * @throws Exception
     */
    public function getData(string $topicUUID = ''): array
    {
        global $gDb, $gProfileFields;

        $sqlQueryParameters = array();

        $sql = 'SELECT fot_uuid, fot_title, fot_views, fop_uuid, fop_text, fop_timestamp_create, fop_usr_id_create,
                       cat_name, usr_uuid,
                       cre_surname.usd_value AS surname, cre_firstname.usd_value AS firstname
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON fot_cat_id = cat_id
            INNER JOIN ' . TBL_FORUM_POSTS . '
                    ON fop_fot_id = fot_id
            INNER JOIN ' . TBL_USERS . '
                    ON usr_id = fop_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                    ON cre_surname.usd_usr_id = usr_id
                   AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                    ON cre_firstname.usd_usr_id = usr_id
                   AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
                 WHERE fot_uuid = ? -- $topicUUID
                 ORDER BY fop_timestamp_create';

        $queryParameters = array_merge(array(
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $topicUUID
        ), $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData(string $categoryUUID = '')
    {
        global $gDb, $gL10n, $gCurrentUser, $gCurrentSession, $gSettingsManager;

        $templateRow = array();
        $data = $this->getData($categoryUUID);
        $forum = new ForumService($gDb);
        $categories = $forum->getCategories();

        foreach ($data as $forumPost) {
            $templateRow['topic_uuid'] = $forumPost['fot_uuid'];
            $templateRow['post_uuid'] = $forumPost['fop_uuid'];
            $templateRow['title'] = $forumPost['fot_title'];
            $templateRow['views'] = $forumPost['fot_views'];
            $templateRow['text'] = $forumPost['fop_text'];
            $templateRow['userUUID'] = $forumPost['usr_uuid'];
            $templateRow['userName'] = $forumPost['firstname'] . ' ' . $forumPost['surname'];
            $datetime = new \DateTime($forumPost['fop_timestamp_create']);
            $templateRow['timestamp'] = $datetime->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic', 'topic_uuid' => $forumPost['fot_uuid']));
            $templateRow['editable'] = false;

            if (count($categories) > 1) {
                $templateRow['category'] = Language::translateIfTranslationStrId($forumPost['cat_name']);
            }

            if ($gCurrentUser->administrateForum()) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic_edit', 'topic_uuid' => $forumPost['fot_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT_TOPIC')
                );
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'adm_topic_' . $forumPost['fot_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/forum.php', array('mode' => 'topic_delete', 'topic_uuid' => $forumPost['fot_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($forumPost['fot_title'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_TOPIC')
                );
            }

            $this->tenplateData[] = $templateRow;
        }
    }
}
