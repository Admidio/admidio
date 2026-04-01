<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Service\RegistrationService;
use Admidio\Organizations\Entity\Organization;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\UserRegistration;
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
 * $page = new RegistrationPresenter();
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RegistrationPresenter extends PagePresenter
{
    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws Exception
     */
    public function createRegistrationList(): void
    {
        global $gL10n, $gSettingsManager, $gMessage, $gHomepage, $gDb, $gProfileFields, $gCurrentUser, $gCurrentSession, $gCurrentOrganization;

        $this->setHtmlID('adm_registration');
        $this->setHeadline($gL10n->get('SYS_REGISTRATIONS'));

        $registrationService = new RegistrationService($gDb);
        $registrations = $registrationService->findAll();
        $templateData = array();

        if (count($registrations) === 0) {
            $gMessage->setForwardUrl($gHomepage);
            $gMessage->show($gL10n->get('SYS_NO_NEW_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
            // => EXIT
        }

        foreach($registrations as $row) {
            $user = new UserRegistration($gDb, $gProfileFields, $row['usr_id']);
            $similarUserIDs = $user->searchSimilarUsers();
            $parentOrganizationId = (int)$gCurrentOrganization->getValue('org_org_id_parent');
            $useParentOrganizationMembers = false;

            if ($parentOrganizationId > 0) {
                $parentOrganization = new Organization($gDb, $parentOrganizationId);
                $parentSettingsManager = $parentOrganization->getSettingsManager();
                $useParentOrganizationMembers = $parentSettingsManager->has('contacts_suborganization_use_same_members')
                    && $parentSettingsManager->getBool('contacts_suborganization_use_same_members');
            } else {
                $useParentOrganizationMembers = $gSettingsManager->has('contacts_suborganization_use_same_members')
                    && $gSettingsManager->getBool('contacts_suborganization_use_same_members');
            }

            $searchInOrganizationId = $useParentOrganizationMembers
                ? $parentOrganizationId
                : (int)$gCurrentOrganization->getValue('org_id');

            if (count($similarUserIDs) > 0) {
                if ($searchInOrganizationId <= 0) {
                    $similarUserIDs = array();
                } else {
                    $sql = 'SELECT DISTINCT mem_usr_id
                              FROM ' . TBL_MEMBERS . '
                        INNER JOIN ' . TBL_ROLES . '
                                ON rol_id = mem_rol_id
                        INNER JOIN ' . TBL_CATEGORIES . '
                                ON cat_id = rol_cat_id
                             WHERE rol_valid = true
                               AND mem_usr_id IN (' . Database::getQmForValues($similarUserIDs) . ')
                               AND cat_org_id = ? -- $searchInOrganizationId';
                    $queryParams = array_merge($similarUserIDs, array($searchInOrganizationId));
                    $membershipsStatement = $gDb->queryPrepared($sql, $queryParams);

                    $organizationMembers = array();
                    while ($memberRow = $membershipsStatement->fetch()) {
                        $organizationMembers[(int)$memberRow['mem_usr_id']] = true;
                    }

                    $similarUserIDs = array_values(array_filter($similarUserIDs, function (int $similarUserId) use ($organizationMembers): bool {
                        return array_key_exists($similarUserId, $organizationMembers);
                    }));
                }
            }

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
                'dataHref' => 'callUrlHideElement(\'user_' . $row['usr_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/registration.php', array('mode' => 'delete_user', 'user_uuid' => $row['usr_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                'dataMessage' => $gL10n->get('SYS_WANT_DELETE_ENTRY', array($user->getValue('FIRST_NAME', 'database') . ' ' .$user->getValue('LAST_NAME'))),
                'icon' => 'bi bi-trash',
                'tooltip' => $gL10n->get('SYS_DELETE')
            );
            if (count($similarUserIDs) > 0) {
                $templateRow['buttons'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration.php', array('mode' => 'show_similar', 'user_uuid' => $row['usr_uuid'])),
                    'name' => $gL10n->get('SYS_ASSIGN_REGISTRATION')
                );
            } else {
                if ($gCurrentUser->isAdministratorUsers()) {
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('accept_registration' => true, 'user_uuid' => $row['usr_uuid']));
                } else {
                    $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/registration.php', array('mode' => 'create_user', 'user_uuid' => $row['usr_uuid']));
                }
                $templateRow['buttons'][] = array(
                    'csrfToken' => $gCurrentSession->getCsrfToken(),
                    'url' => $url,
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
}
