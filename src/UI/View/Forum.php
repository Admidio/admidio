<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\Users\Entity\UserRegistration;
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
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws Exception
     */
    public function createForumCards(array $categoryUUIDs = null)
    {
        global $gL10n;

        $this->prepareForumData($categoryUUIDs);

        $this->smarty->assign('cards', $this->templateForumData);
        $this->smarty->assign('l10n', $gL10n);
        try {
            $this->pageContent .= $this->smarty->fetch('modules/registration.cards.tpl');
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Read the data of the forum in an array.
     * @param array|null $categoryUUIDs Array with all categories that should be filtered.
     * @throws Exception
     */
    public function getForumData(array $categoryUUIDs = null): array
    {
        global $gDb, $gCurrentOrgId;

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
                 ORDER BY fot_timestamp_create DESC';

        $queryParameters = array(
            $gCurrentOrgId
        );

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    public function prepareForumData(array $categoryUUIDs = null)
    {
        global $gL10n, $gCurrentUser, $gCurrentSession;

        $templateRow = array();
        $data = $this->getForumData($categoryUUIDs);

        foreach ($data as $forumTopic) {
            $templateRow['uuid'] = $forumTopic['fot_uuid'];
            $templateRow['title'] = $forumTopic['fot_title'];
            $templateRow['views'] = $forumTopic['fot_views'];
            $templateRow['text'] = $forumTopic['fop_text'];
            $templateRow['userUUID'] = $forumTopic['usr_uuid'];

            if ($gCurrentUser->administrateForum()) {
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
