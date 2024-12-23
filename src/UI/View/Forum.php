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
     * Creates an array with all available registrations. The array contains the following entries:
     * array(userID, userUUID, loginName, registrationTimestamp, lastName, firstName, email, validationID)
     * @return array Returns an array with information about every available registration
     * @throws Exception
     */
    public function getRegistrationsArray(): array
    {
        global $gDb, $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id, usr_uuid, usr_login_name, reg_timestamp, reg_validation_id
                  FROM '.TBL_REGISTRATIONS.'
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = reg_usr_id
                 WHERE usr_valid = false
                   AND reg_org_id = ? -- $gCurrentOrgId
              ORDER BY reg_validation_id DESC, reg_timestamp DESC';
        $queryParameters = array($gCurrentOrgId);
        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws Exception
     */
    public function createForumCards()
    {
        global $gL10n, $gSettingsManager, $gMessage, $gHomepage, $gDb, $gProfileFields, $gCurrentUser, $gCurrentSession;

        $registrations = $this->getRegistrationsArray();
        $templateData = array();

        if (count($registrations) === 0) {
            $gMessage->setForwardUrl($gHomepage);
            $gMessage->show($gL10n->get('SYS_NO_NEW_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
            // => EXIT
        }

        foreach($registrations as $row) {
            $user = new UserRegistration($gDb, $gProfileFields, $row['usr_id']);
            $similarUserIDs = $user->searchSimilarUsers();

            $templateRow = array();
            $templateRow['id'] = 'user_'.$row['usr_uuid'];
            $templateRow['title'] = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');

            $timestampCreate = \DateTime::createFromFormat('Y-m-d H:i:s', $row['reg_timestamp']);
            $templateRow['information'][] = $gL10n->get('SYS_REGISTRATION_AT', array($timestampCreate->format($gSettingsManager->getString('system_date')), $timestampCreate->format($gSettingsManager->getString('system_time'))));
            $templateRow['information'][] = $gL10n->get('SYS_USERNAME') . ': ' . $row['usr_login_name'];
            $templateRow['information'][] = $gL10n->get('SYS_EMAIL') . ': <a href="mailto:'.$user->getValue('EMAIL').'">'.$user->getValue('EMAIL').'</a>';

            if ((string) $row['reg_validation_id'] === '') {
                $templateRow['information'][] = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i>' . $gL10n->get('SYS_REGISTRATION_CONFIRMED') . '</div>';
            } else {
                $templateRow['information'][] = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i>' . $gL10n->get('SYS_REGISTRATION_NOT_CONFIRMED') . '</div>';
            }

            if (count($similarUserIDs) > 0) {
                $templateRow['information'][] = '<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i>' . (count($similarUserIDs) === 1 ? $gL10n->get('SYS_CONTACT_SIMILAR_NAME') : $gL10n->get('SYS_MEMBERS_SIMILAR_NAME') ) . '</div>';
            }

            $templateRow['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['usr_uuid'])),
                'icon' => 'bi bi-eye',
                'tooltip' => $gL10n->get('SYS_SHOW_PROFILE')
            );
            $templateRow['actions'][] = array(
                'dataHref' => 'callUrlHideElement(\'user_' . $row['usr_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/modules/registration.php', array('mode' => 'delete_user', 'user_uuid' => $row['usr_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($user->getValue('FIRST_NAME', 'database').' '.$user->getValue('LAST_NAME'))),
                'icon' => 'bi bi-trash',
                'tooltip' => $gL10n->get('SYS_DELETE')
            );
            if (count($similarUserIDs) > 0) {
                $templateRow['buttons'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration.php', array('mode' => 'show_similar', 'user_uuid' => $row['usr_uuid'])),
                    'name' => $gL10n->get('SYS_ASSIGN_REGISTRATION')
                );
            } else {
                $templateRow['buttons'][] = array(
                    'url' => ($gCurrentUser->editUsers() ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('accept_registration' => true, 'user_uuid' => $row['usr_uuid'])) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration.php', array('mode' => 'create_user', 'user_uuid' => $row['usr_uuid']))),
                    'name' => $gL10n->get('SYS_CONFIRM_REGISTRATION')
                );
            }

            $templateData[] = $templateRow;
        }

        $this->smarty->assign('cards', $templateData);
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
    public function getForumData(array $categoryUUIDs = null)
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

        $this->forumData = $gDb->getArrayFromSql($sql, $queryParameters);
    }

    public function prepareForumData()
    {
        global $gL10n, $gCurrentUser, $gCurrentSession;

        $templateRow = array();

        foreach ($this->data as $forumTopic) {
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
