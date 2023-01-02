<?php
/**
 ***********************************************************************************************
 * Create user relations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid : UUID of the first user in the new relation
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');

if (!$gSettingsManager->getBool('members_enable_user_relations')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// only users who can edit all users are allowed to create user relations
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ($getUserUuid === '') {
    $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
    // => EXIT
}

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if ($user->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
    // => EXIT
}

$sql = 'SELECT COUNT(urt_id) AS count FROM '.TBL_USER_RELATION_TYPES;
$relationsStatement = $gDb->queryPrepared($sql);

if ((int) $relationsStatement->fetchColumn() === 0) {
    $gMessage->show($gL10n->get('REL_NO_RELATION_TYPES_FOUND'));
    // => EXIT
}

$headline = $gL10n->get('SYS_CREATE_RELATIONSHIP');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('admidio-userrelations-edit', $headline);

// show form
$form = new HtmlForm('relation_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/userrelations_function.php', array('user_uuid' => $getUserUuid, 'mode' => '1')), $page);

$sqlData = array();
if ($gCurrentUser->editUsers()) {
    // the user has the edit right, therefore he can edit all visible users
    $sqlData['query'] = 'SELECT usr_id, CONCAT(first_name.usd_value, \' \', last_name.usd_value) AS name
                           FROM '.TBL_MEMBERS.'
                     INNER JOIN '.TBL_ROLES.'
                             ON rol_id = mem_rol_id
                     INNER JOIN '.TBL_CATEGORIES.'
                             ON cat_id = rol_cat_id
                     INNER JOIN '.TBL_USERS.'
                             ON usr_id = mem_usr_id
                      LEFT JOIN '.TBL_USER_DATA.' AS last_name
                             ON last_name.usd_usr_id = usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN '.TBL_USER_DATA.' AS first_name
                             ON first_name.usd_usr_id = usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                          WHERE usr_id <> ? -- $user->getValue(\'usr_id\')
                            AND rol_id IN ('.Database::getQmForValues($gCurrentUser->getRolesViewMemberships()).')
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
    // select all users which the current user can edit because of role leader rights
    $sqlData['query'] = 'SELECT usr_id, CONCAT(first_name.usd_value, \' \', last_name.usd_value) AS name
                           FROM '.TBL_MEMBERS.'
                     INNER JOIN '.TBL_USERS.'
                             ON usr_id = mem_usr_id
                      LEFT JOIN '.TBL_USER_DATA.' AS last_name
                             ON last_name.usd_usr_id = usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN '.TBL_USER_DATA.' AS first_name
                             ON first_name.usd_usr_id = usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                          WHERE usr_id <> ? -- $user->getValue(\'usr_id\')
                            AND mem_rol_id IN (SELECT mem_rol_id
                                                 FROM '.TBL_MEMBERS.'
                                           INNER JOIN '.TBL_ROLES.'
                                                   ON rol_id = mem_rol_id
                                           INNER JOIN '.TBL_CATEGORIES.'
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
        ROLE_LEADER_MEMBERS_EDIT,
        ROLE_LEADER_MEMBERS_ASSIGN_EDIT,
        $gCurrentOrgId,
        DATE_NOW,
        DATE_NOW
    );
}

$form->addSelectBoxFromSql(
    'usr_id2',
    $gL10n->get('SYS_MEMBER'),
    $gDb,
    $sqlData,
    array('property' => HtmlForm::FIELD_REQUIRED, 'search' => true, 'placeholder' => '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -')
);
// select box showing all relation types
$sql = 'SELECT urt_id, REPLACE(\'' . $gL10n->get('SYS_IS_VAR_FROM') . '\', \'#VAR1#\', urt_name)
          FROM '.TBL_USER_RELATION_TYPES.'
      ORDER BY urt_name';
$form->addSelectBoxFromSql(
    'urt_id',
    $gL10n->get('SYS_USER_RELATION'),
    $gDb,
    $sql,
    array('property' => HtmlForm::FIELD_REQUIRED)
);

$form->addInput(
    'usr_id',
    $gL10n->get('SYS_CURRENT_MEMBER'),
    $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED)
);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
