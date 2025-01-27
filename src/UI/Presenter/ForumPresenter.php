<?php

namespace Admidio\UI\Presenter;

use Admidio\Categories\Service\CategoryService;
use Admidio\Infrastructure\Database;
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
     * @var array A list of all categories for the forum.
     */
    protected array $categoryList = array();
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
        $categories = new CategoryService($gDb, 'FOT');
        $this->categoryList = $categories->getVisibleCategories();

        parent::__construct($categoryUUID);
    }

    /**
     * Create content that is used on several pages and could be called in other methods. It will
     * create a functions menu and a filter navbar.
     * @return void
     * @throws Exception
     */
    protected function createSharedHeader(): void
    {
        global $gCurrentUser, $gL10n, $gDb;

        $this->setHeadline($gL10n->get('SYS_FORUM'));

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
        if (count($this->categoryList) > 1) {
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
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function createCards(): void
    {
        global $gL10n;

        $this->prepareData();

        $this->setHtmlID('adm_forum_cards');
        $this->createSharedHeader();

        $this->smarty->assign('cards', $this->templateData);
        $this->smarty->assign('l10n', $gL10n);
        try {
            $this->pageContent .= $this->smarty->fetch('modules/forum.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Read the data of the forum in an array.
     * @throws Exception
     */
    public function getData(): array
    {
        global $gDb, $gProfileFields, $gCurrentUser;

        $sqlConditions = '';
        $sqlQueryParameters = array();
        $visibleCategoryIDs = $gCurrentUser->getAllVisibleCategories('FOT');

        if ($this->categoryUUID !== '') {
            $sqlConditions .= ' AND cat_uuid = ?';
            $sqlQueryParameters[] = $this->categoryUUID;
        }

        $sql = 'SELECT fot_uuid, fot_title, fot_views, first_post.fop_text, fot_timestamp_create, fot_usr_id_create,
                       cat_id, cat_name, usr.usr_uuid, usr.usr_login_name, usr.usr_timestamp_change,
                       cre_surname.usd_value AS surname, cre_firstname.usd_value AS firstname,
                       (SELECT COUNT(*) - 1 FROM ' . TBL_FORUM_POSTS . ' WHERE fop_fot_id = fot_id) AS replies_count,
                       last_reply.fop_timestamp_create AS last_reply_timestamp, last_reply_usr.usr_login_name AS last_reply_login_name,
                       last_reply_surname.usd_value AS last_reply_surname, last_reply_firstname.usd_value AS last_reply_firstname
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON fot_cat_id = cat_id
            INNER JOIN ' . TBL_FORUM_POSTS . ' as first_post
                    ON first_post.fop_id = fot_fop_id_first_post
            INNER JOIN ' . TBL_USERS . ' AS usr
                    ON usr.usr_id = fot_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                    ON cre_surname.usd_usr_id = usr.usr_id
                   AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                    ON cre_firstname.usd_usr_id = usr.usr_id
                   AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
             LEFT JOIN ' . TBL_FORUM_POSTS . ' AS last_reply
                    ON last_reply.fop_id = (SELECT MAX(fop_id) FROM ' . TBL_FORUM_POSTS . ' WHERE fop_fot_id = fot_id)
             LEFT JOIN ' . TBL_USERS . ' AS last_reply_usr
                    ON last_reply_usr.usr_id = last_reply.fop_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_reply_surname
                    ON last_reply_surname.usd_usr_id = last_reply_usr.usr_id
                   AND last_reply_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_reply_firstname
                    ON last_reply_firstname.usd_usr_id = last_reply_usr.usr_id
                   AND last_reply_firstname.usd_usf_id = ? -- $firstNameUsfId
                 WHERE  cat_id IN (' . Database::getQmForValues($visibleCategoryIDs) . ')
                       ' . $sqlConditions . '
                 ORDER BY fot_timestamp_create DESC';

        $queryParameters = array_merge(array(
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        ), $visibleCategoryIDs, $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    public function prepareData(): void
    {
        global $gL10n, $gCurrentUser, $gCurrentSession, $gSettingsManager;

        $data = $this->getData();

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

            if (strlen($forumTopic['fop_text']) > 250) {
                $templateRow['text'] = substr(
                        substr($forumTopic['fop_text'], 0, 250),
                        0,
                        strrpos(substr($forumTopic['fop_text'], 0, 250), ' ')
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
            $templateRow['userProfilePhotoUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $forumTopic['usr_uuid'], 'timestamp' => $forumTopic['usr_timestamp_change']));
            $datetime = new \DateTime($forumTopic['fot_timestamp_create']);
            $templateRow['timestamp'] = $datetime->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            $templateRow['url'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic', 'topic_uuid' => $forumTopic['fot_uuid']));
            $templateRow['category'] = '';
            $templateRow['editable'] = false;

            if (count($this->categoryList) > 1) {
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
                    'dataHref' => 'callUrlHideElement(\'adm_topic_' . $forumTopic['fot_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/forum.php', array('mode' => 'topic_delete', 'topic_uuid' => $forumTopic['fot_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($forumTopic['fot_title'])),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE_VAR', array('SYS_TOPIC'))
                );
            }

            $this->templateData[] = $templateRow;
        }
    }
}
