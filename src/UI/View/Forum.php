<?php
namespace Admidio\UI\View;

use Admidio\Forum\Entity\Post;
use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\UI\Component\Form;
use HtmlPage;
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
class Forum extends HtmlPage
{
    /**
     * @var array Array with all read forum topics and their first post.
     */
    protected array $data = array();
    /**
     * @var array Array with all read groups and roles
     */
    protected array $templateForumData = array();


    /**
     * Create content that is used on several pages and could be called in other methods. It will
     * create a functions menu and a filter navbar.
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @return void
     * @throws Exception
     */
    protected function createSharedHeader(string $categoryUUID = '')
    {
        global $gCurrentUser, $gSettingsManager, $gL10n, $gDb;

        // show link to create new topic
        $this->addPageFunctionsMenuItem(
            'menu_item_forum_topic_add',
            $gL10n->get('SYS_CREATE_TOPIC'),
            ADMIDIO_URL.FOLDER_MODULES.'/forum.php?mode=topic_edit',
            'bi-plus-circle-fill'
        );

        if ($gCurrentUser->administrateForum()) {
            $this->addPageFunctionsMenuItem(
                'menu_item_announcement_categories',
                $gL10n->get('SYS_EDIT_CATEGORIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'ANN')),
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
        $form = new Form(
            'adm_navbar_forum_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL.FOLDER_MODULES.'/forum.php',
            $this,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addSelectBoxForCategories(
            'cat_uuid',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'ROL',
            Form::SELECT_BOX_MODUS_FILTER,
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
    public function createForumCards(string $categoryUUID = '')
    {
        global $gL10n;

        $this->prepareForumData($categoryUUID);
        $this->createSharedHeader($categoryUUID);

        $this->smarty->assign('cards', $this->templateForumData);
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
        $post  = new Post($gDb);

        if ($topicUUID !== '') {
            $topic->readDataByUuid($topicUUID);
            $post->readDataById($topic->getValue('fot_first_fop_id'));
        }

        // show form
        $form = new Form(
            'adm_forum_topic_edit_form',
            'modules/forum.topic.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('topic_uuid' => $topicUUID, 'mode' => 'topic_save')),
            $this
        );
        $form->addInput(
            'fot_title',
            $gL10n->get('SYS_TITLE'),
            $topic->getValue('fot_title'),
            array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
        );
        $form->addEditor(
            'fop_text',
            $gL10n->get('SYS_TEXT'),
            $post->getValue('fop_text'),
            array('property' => Form::FIELD_REQUIRED, 'toolbar' => 'AdmidioDefault')
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
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @throws Exception
     */
    public function getForumData(string $categoryUUID = ''): array
    {
        global $gDb, $gCurrentOrgId;

        $sqlConditions = '';
        $sqlQueryParameters = array();

        if ($categoryUUID !== '') {
            $sqlConditions .= ' AND cat_uuid = ?';
            $sqlQueryParameters[] = $categoryUUID;
        }

        $sql = 'SELECT fot_uuid, fot_title, fot_views, fop_text, usr_uuid
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON fot_cat_id = cat_id
            INNER JOIN ' . TBL_FORUM_POSTS . '
                    ON fop_id = fot_first_fop_id
            INNER JOIN ' . TBL_USERS . '
                    ON usr_id = fot_usr_id_create
                 WHERE (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )
                       ' . $sqlConditions . '
                 ORDER BY fot_timestamp_create DESC';

        $queryParameters = array_merge(array(
            $gCurrentOrgId
        ), $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    public function prepareForumData(string $categoryUUID = '')
    {
        global $gL10n, $gCurrentUser, $gCurrentSession;

        $templateRow = array();
        $data = $this->getForumData($categoryUUID);

        foreach ($data as $forumTopic) {
            $templateRow['uuid'] = $forumTopic['fot_uuid'];
            $templateRow['title'] = $forumTopic['fot_title'];
            $templateRow['views'] = $forumTopic['fot_views'];
            $templateRow['text'] = $forumTopic['fop_text'];
            $templateRow['userUUID'] = $forumTopic['usr_uuid'];
            $templateRow['editable'] = false;

            if ($gCurrentUser->administrateForum()) {
                $templateRow['editable'] = true;

                $templateRow['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'role_' . $forumTopic['fot_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/forum.php', array('mode' => 'topic_delete', 'uuid' => $forumTopic['fot_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($forumTopic['fot_title'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_TOPIC')
                );
            }

            $this->templateForumData[] = $templateRow;
        }
    }
}
