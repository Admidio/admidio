<?php
/**
 ***********************************************************************************************
 * Create user relations
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid: UUID of the first user in the new relation
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // only users who can edit all users are allowed to create user relations
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getUserUuid === '') {
        throw new Exception('SYS_NO_ENTRY');
    }

    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    if ($user->isNewRecord()) {
        throw new Exception('SYS_NO_ENTRY');
    }

    $sql = 'SELECT COUNT(urt_id) AS count FROM ' . TBL_USER_RELATION_TYPES;
    $relationsStatement = $gDb->queryPrepared($sql);

    if ((int)$relationsStatement->fetchColumn() === 0) {
        throw new Exception('REL_NO_RELATION_TYPES_FOUND');
    }

    $headline = $gL10n->get('SYS_CREATE_RELATIONSHIP');
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create an HTML page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-userrelations-edit', $headline);

    // show form
    $form = new FormPresenter(
        'adm_user_relations_edit_form',
        'modules/user-relations.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/userrelations_function.php', array('user_uuid' => $getUserUuid, 'mode' => 'create')),
        $page
    );

    $sqlData = array();
    if ($gCurrentUser->isAdministratorUsers()) {
        // the user has the edit right, therefore, he can edit all visible users
        $sqlData['query'] = 'SELECT usr_uuid, CONCAT(first_name.usd_value, \' \', last_name.usd_value) AS name
                           FROM ' . TBL_MEMBERS . '
                     INNER JOIN ' . TBL_ROLES . '
                             ON rol_id = mem_rol_id
                     INNER JOIN ' . TBL_CATEGORIES . '
                             ON cat_id = rol_cat_id
                     INNER JOIN ' . TBL_USERS . '
                             ON usr_id = mem_usr_id
                      LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                             ON last_name.usd_usr_id = usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                             ON first_name.usd_usr_id = usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                          WHERE usr_id <> ? -- $user->getValue(\'usr_id\')
                            AND rol_uuid IN (' . Database::getQmForValues($gCurrentUser->getRolesViewMemberships()) . ')
                            AND rol_valid   = true
                            AND cat_name_intern <> \'EVENTS\'
                            AND ( cat_org_id = ? -- $gCurrentOrgId
                                OR cat_org_id IS NULL )
                            AND mem_begin <= ? -- DATE_NOW
                            AND mem_end   >= ? -- DATE_NOW
                            AND usr_valid  = true
                       ORDER BY last_name.usd_value, first_name.usd_value, usr_id';
        $sqlData['params'] = array_merge(
            array(
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                $user->getValue('usr_id')
            ),
            $gCurrentUser->getRolesViewMemberships(),
            array(
                $gCurrentOrgId,
                DATE_NOW,
                DATE_NOW
            )
        );
    } else {
        // select all users that the current user can edit because of role leader rights
        $sqlData['query'] = 'SELECT usr_uuid, CONCAT(first_name.usd_value, \' \', last_name.usd_value) AS name
                           FROM ' . TBL_MEMBERS . '
                     INNER JOIN ' . TBL_USERS . '
                             ON usr_id = mem_usr_id
                      LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                             ON last_name.usd_usr_id = usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                             ON first_name.usd_usr_id = usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                          WHERE usr_id <> ? -- $user->getValue(\'usr_id\')
                            AND mem_rol_id IN (SELECT mem_rol_id
                                                 FROM ' . TBL_MEMBERS . '
                                           INNER JOIN ' . TBL_ROLES . '
                                                   ON rol_id = mem_rol_id
                                           INNER JOIN ' . TBL_CATEGORIES . '
                                                   ON cat_id = rol_cat_id
                                                WHERE mem_usr_id  = ? -- $gCurrentUserId
                                                  AND mem_begin  <= ? -- DATE_NOW
                                                  AND mem_end     > ? -- DATE_NOW
                                                  AND mem_leader  = true
                                                  AND rol_valid   = true
                                                  AND cat_name_intern <> \'EVENTS\'
                                                  AND rol_leader_rights IN (?,?) -- ROLE_LEADER_MEMBERS_EDIT, ROLE_LEADER_MEMBERS_ASSIGN_EDIT
                                                  AND ( cat_org_id = ? -- $gCurrentOrgId
                                                      OR cat_org_id IS NULL ))
                            AND mem_begin <= ? -- DATE_NOW
                            AND mem_end   >= ? -- DATE_NOW
                            AND usr_valid  = true
                       ORDER BY last_name.usd_value, first_name.usd_value, usr_id';
        $sqlData['params'] = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $user->getValue('usr_id'),
            $gCurrentUserId,
            DATE_NOW,
            DATE_NOW,
            Role::ROLE_LEADER_MEMBERS_EDIT,
            Role::ROLE_LEADER_MEMBERS_ASSIGN_EDIT,
            $gCurrentOrgId,
            DATE_NOW,
            DATE_NOW
        );
    }

    $form->addSelectBoxFromSql(
        'usr_uuid2',
        $gL10n->get('SYS_MEMBER'),
        $gDb,
        $sqlData,
        array('property' => FormPresenter::FIELD_REQUIRED, 'search' => true, 'placeholder' => '- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -')
    );
    // select box showing all relation types
    $sql = 'SELECT urt_uuid, urt_name
          FROM ' . TBL_USER_RELATION_TYPES . '
      ORDER BY urt_name';
    $userRelationTypeStatement = $gDb->queryPrepared($sql);
    $userRelationTypes = array();
    while ($row = $userRelationTypeStatement->fetch()) {
        $userRelationTypes[] = array(
            $row['urt_uuid'],
            $gL10n->get('SYS_IS_VAR_FROM', array(
                (Language::isTranslationStringId($row['urt_name']) ? $gL10n->get($row['urt_name']) : $row['urt_name'])
            ))
        );
    }
    $form->addSelectBox(
        'urt_uuid',
        $gL10n->get('SYS_USER_RELATION'),
        $userRelationTypes,
        array('property' => FormPresenter::FIELD_REQUIRED)
    );

    $form->addInput(
        'selectedUser',
        $gL10n->get('SYS_CURRENT_MEMBER'),
        $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'),
        array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED)
    );

    $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
