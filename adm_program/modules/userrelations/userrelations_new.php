<?php
/**
 ***********************************************************************************************
 * Create user relations
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id : user id of the first user in the new relation
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUsrId = admFuncVariableIsValid($_GET, 'usr_id', 'int');

if ($gPreferences['members_enable_user_relations'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if($getUsrId <= 0)
{
    $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
    // => EXIT
}

$user = new User($gDb, $gProfileFields, $getUsrId);

if($user->isNewRecord())
{
    $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
    // => EXIT
}

if(!$gCurrentUser->hasRightEditProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$sql = 'SELECT count(urt_id) FROM '.TBL_USER_RELATION_TYPES;
$relationsStatement = $gDb->query($sql);
if($relationsStatement->fetchColumn() == 0)
{
    $gMessage->show($gL10n->get('REL_NO_RELATION_TYPES_FOUND'));
    // => EXIT
}

$headline = $gL10n->get('PRO_ADD_USER_RELATION');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$relationEditMenu = $page->getMenu();
$relationEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('relation_edit_form',
                     $g_root_path.'/adm_program/modules/userrelations/userrelations_function.php?usr_id='.$getUsrId.'&amp;mode=1', $page);

$form->addInput('usr_id', $gL10n->get('SYS_USER'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'),
         array('maxLength' => 100, 'property' => FIELD_DISABLED));

// select box showing all relation types 
$sql = 'SELECT urt_id, urt_name
          FROM '.TBL_USER_RELATION_TYPES.'
      ORDER BY urt_name';
$form->addSelectBoxFromSql('urt_id', $gL10n->get('SYS_USER_RELATION'), $gDb, $sql, array('property' => FIELD_REQUIRED));

// select box showing all users (TODO: check edit right)
$sql = 'SELECT usr_id, concat(LAST_NAME.usd_value, \' \', FIRST_NAME.usd_value) AS name
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = mem_usr_id
             LEFT JOIN '.TBL_USER_DATA.' LAST_NAME
                    ON LAST_NAME.usd_usr_id = usr_id
                   AND LAST_NAME.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
             LEFT JOIN '.TBL_USER_DATA.' FIRST_NAME
                    ON FIRST_NAME.usd_usr_id = usr_id
                   AND FIRST_NAME.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                 WHERE usr_id <> '.$user->getValue('usr_id').'
                   AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end   >= \''.DATE_NOW.'\'
                   AND usr_valid  = 1
              ORDER BY LAST_NAME.usd_value, FIRST_NAME.usd_value, usr_id';
$form->addSelectBoxFromSql('usr_id2', $gL10n->get('SYS_MEMBER'), $gDb, $sql, array('property' => FIELD_REQUIRED));

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
