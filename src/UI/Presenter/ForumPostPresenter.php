<?php

namespace Admidio\UI\Presenter;

use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;

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
class ForumPostPresenter extends PagePresenter
{
    /**
     * @var string UUID of the topic.
     */
    protected string $postUUID = '';
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
     * @param string $postUUID UUID of the topic.
     * @throws Exception
     */
    public function __construct(string $postUUID = '')
    {
        $this->postUUID = $postUUID;
        parent::__construct($postUUID);
    }

    /**
     * Create the data for the edit form of a forum post.
     * @param string $topicUUID UUID of the topic that must be set if a new post is created.
     * @throws Exception
     */
    public function createEditForm(string $topicUUID = '')
    {
        global $gDb, $gL10n, $gCurrentSession;

        // create post object
        $post = new Post($gDb);

        if ($this->postUUID !== '') {
            $post->readDataByUuid($this->postUUID);
        }

        $this->setHtmlID('adm_forum_post_edit');
        if ($this->postUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_POST'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_POST'))));
        }

        // show form
        $form = new FormPresenter(
            'adm_forum_post_edit_form',
            'modules/forum.posts.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php',
                array(
                    'post_uuid' => $this->postUUID,
                    'topic_uuid' => $topicUUID,
                    'mode' => 'post_save'
                )
            ),
            $this
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

        $this->smarty->assign('nameUserCreated', $post->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $post->getValue('fot_timestamp_create'));
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
            $this->topicUUID
        ), $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData()
    {
        global $gDb, $gL10n, $gCurrentUser, $gCurrentSession, $gSettingsManager;

        $templateRow = array();
        $data = $this->getData();
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
            $templateRow['category'] = '';
            $templateRow['editable'] = false;

            if (count($categories) > 1) {
                $templateRow['category'] = Language::translateIfTranslationStrId($forumPost['cat_name']);
            }

            if ($gCurrentUser->administrateForum()) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'post_edit', 'post_uuid' => $forumPost['fop_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT_TOPIC')
                );
                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'adm_post_' . $forumPost['fop_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/forum.php', array('mode' => 'post_delete', 'post_uuid' => $forumPost['fop_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array('SYS_POST')),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_VAR', array('SYS_POST'))
                );
            }

            $this->templateData[] = $templateRow;
        }
    }
}
