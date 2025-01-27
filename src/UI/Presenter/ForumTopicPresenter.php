<?php

namespace Admidio\UI\Presenter;

use Admidio\Categories\Service\CategoryService;
use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
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
class ForumTopicPresenter extends PagePresenter
{
    /**
     * @var string UUID of the topic.
     */
    protected string $topicUUID = '';
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
     * @param string $topicUUID UUID of the topic.
     * @throws Exception
     */
    public function __construct(string $topicUUID = '')
    {
        $this->topicUUID = $topicUUID;
        parent::__construct($topicUUID);
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws Exception|\DateMalformedStringException
     */
    public function createCards(): void
    {
        global $gL10n, $gDb;

        // update views counter
        $topic = new Topic($gDb);
        $topic->readDataByUuid($this->topicUUID);
        $topic->setValue('fot_views', $topic->getValue('fot_views') + 1);
        $topic->save();

        // read topics and posts from database
        $this->prepareData();
        $this->setHeadline($this->templateData[0]['title']);

        // show link to create new topic
        $this->addPageFunctionsMenuItem(
            'menu_item_forum_post_add',
            $gL10n->get('SYS_CREATE_VAR', array('SYS_POST')),
            ADMIDIO_URL . FOLDER_MODULES . '/forum.php?mode=post_edit&topic_uuid=' . $this->topicUUID,
            'bi-plus-circle-fill'
        );

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        try {
            $this->pageContent .= $this->smarty->fetch('modules/forum.posts.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Create the data for the edit form of a forum topic.
     * @throws Exception
     */
    public function createEditForm(): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gCurrentUser;

        // create menu object
        $topic = new Topic($gDb);
        $post = new Post($gDb);
        $categoryService = new CategoryService($gDb, 'FOT');

        if ($this->topicUUID === '') {
            if (!$gCurrentUser->administrateForum()
                && count($gCurrentUser->getAllEditableCategories('FOT')) === 0) {
                throw new Exception($gL10n->get('SYS_NO_RIGHTS'));
            }
        } else {
            $topic->readDataByUuid($this->topicUUID);
            $post->readDataById($topic->getValue('fot_fop_id_first_post'));

            if (!$gCurrentUser->administrateForum()
                && $gCurrentUser->getValue('usr_id') !== $post->getValue('fop_usr_id_create')) {
                throw new Exception($gL10n->get('SYS_NO_RIGHTS'));
            }
        }

        $this->setHtmlID('adm_forum_topic_edit');
        if ($this->topicUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_TOPIC'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_TOPIC'))));
        }

        // show form
        $form = new FormPresenter(
            'adm_forum_topic_edit_form',
            'modules/forum.topic.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('topic_uuid' => $this->topicUUID, 'mode' => 'topic_save')),
            $this
        );
        if ($categoryService->count() > 1) {
            $form->addSelectBoxForCategories(
                'adm_category_uuid',
                $gL10n->get('SYS_CATEGORY'),
                $gDb,
                'FOT',
                FormPresenter::SELECT_BOX_MODUS_EDIT,
                array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $topic->getValue('cat_uuid'))
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
     * @throws Exception
     */
    public function getData(): array
    {
        global $gDb, $gProfileFields;

        $sqlQueryParameters = array();

        $sql = 'SELECT fot_uuid, fot_title, fot_views, fop_uuid, fop_text, fop_timestamp_create, fop_usr_id_create,
                       fop_timestamp_change, cat_name, usr_uuid, usr_timestamp_change,
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
            $this->topicUUID
        ), $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData(): void
    {
        global $gDb, $gL10n, $gCurrentUser, $gCurrentSession, $gSettingsManager;

        $data = $this->getData();
        $categoryService = new CategoryService($gDb, 'FOT');
        $firstPost = true;

        foreach ($data as $forumPost) {
            $templateRow = array();
            $templateRow['topic_uuid'] = $forumPost['fot_uuid'];
            $templateRow['post_uuid'] = $forumPost['fop_uuid'];
            $templateRow['title'] = $forumPost['fot_title'];
            $templateRow['views'] = $forumPost['fot_views'];
            $templateRow['text'] = $forumPost['fop_text'];
            $templateRow['userUUID'] = $forumPost['usr_uuid'];
            $templateRow['userName'] = $forumPost['firstname'] . ' ' . $forumPost['surname'];
            $templateRow['userProfilePhotoUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $forumPost['usr_uuid'], 'timestamp' => $forumPost['usr_timestamp_change']));
            $datetimeCreated = new \DateTime($forumPost['fop_timestamp_create']);
            $templateRow['timestampCreated'] = $datetimeCreated->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            if (!empty($forumPost['fop_timestamp_change'])) {
                $datetimeChanged = new \DateTime($forumPost['fop_timestamp_change']);
                $templateRow['timestampChanged'] = $datetimeChanged->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            }
            $templateRow['category'] = '';
            $templateRow['editable'] = false;

            if ($categoryService->count() > 1) {
                $templateRow['category'] = Language::translateIfTranslationStrId($forumPost['cat_name']);
            }

            if ($gCurrentUser->administrateForum()
                || $gCurrentUser->getValue('usr_uuid') === $forumPost['usr_uuid']) {
                $templateRow['editable'] = true;

                if ($firstPost) {
                    $templateRow['actions'][] = array(
                        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic_edit', 'topic_uuid' => $forumPost['fot_uuid'])),
                        'icon' => 'bi bi-pencil-square',
                        'tooltip' => $gL10n->get('SYS_EDIT_VAR', array('SYS_TOPIC'))
                    );
                } else {
                    $templateRow['actions'][] = array(
                        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'post_edit', 'post_uuid' => $forumPost['fop_uuid'])),
                        'icon' => 'bi bi-pencil-square',
                        'tooltip' => $gL10n->get('SYS_EDIT_VAR', array('SYS_POST'))
                    );
                    $templateRow['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'adm_post_' . $forumPost['fop_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/forum.php', array('mode' => 'post_delete', 'post_uuid' => $forumPost['fop_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array('SYS_POST')),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE_VAR', array('SYS_POST'))
                    );
                }
            }

            $firstPost = false;
            $this->templateData[] = $templateRow;
        }
    }
}
