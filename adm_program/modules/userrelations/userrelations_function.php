<?php
/**
 ***********************************************************************************************
 * Various functions for user relations
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * ure_id: Id of the user relation that should be edited
 * mode  : 1 - Create relation
 *         2 - Delete relation
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUreId = admFuncVariableIsValid($_GET, 'ure_id', 'int');
$getMode  = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

if ($gPreferences['members_enable_user_relations'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// only users who can edit all users are allowed to create user relations
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$relation = new TableUserRelation($gDb);
$user1 = new User($gDb, $gProfileFields);
$user2 = new User($gDb, $gProfileFields);

if($getUreId > 0)
{
    $relation->readDataById($getUreId);
    $user1->readDataById($relation->getValue('ure_usr_id1'));
    $user2->readDataById($relation->getValue('ure_usr_id2'));
    if(!$gCurrentUser->hasRightEditProfile($user1) || !$gCurrentUser->hasRightEditProfile($user2))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getMode === 1)
{
    $getUsrId = admFuncVariableIsValid($_GET, 'usr_id', 'int');
    $user1->readDataById($getUsrId);

    if($user1->isNewRecord())
    {
        $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
        // => EXIT
    }

    if(!$gCurrentUser->hasRightEditProfile($user1))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $postUsrId2 = admFuncVariableIsValid($_POST, 'usr_id2', 'int');
    $user2->readDataById($postUsrId2);

    if($user2->isNewRecord())
    {
        $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
        // => EXIT
    }

    if(!$gCurrentUser->hasRightEditProfile($user2))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $postUrtId = admFuncVariableIsValid($_POST, 'urt_id', 'int');
    $relationtype = new TableUserRelationType($gDb, $postUrtId);

    if($relationtype->isNewRecord())
    {
        $gMessage->show($gL10n->get('SYS_NO_ENTRY'));
        // => EXIT
    }

    $gDb->startTransaction();

    $relation1 = new TableUserRelation($gDb);
    $relation1->setValue('ure_urt_id', $relationtype->getValue('urt_id'));
    $relation1->setValue('ure_usr_id1', $user1->getValue('usr_id'));
    $relation1->setValue('ure_usr_id2', $user2->getValue('usr_id'));
    $relation1->save();

    if (!$relationtype->isUnidirectional())
    {
        $relation2 = new TableUserRelation($gDb);
        $relation2->setValue('ure_urt_id', $relationtype->getValue('urt_id_inverse'));
        $relation2->setValue('ure_usr_id1', $user2->getValue('usr_id'));
        $relation2->setValue('ure_usr_id2', $user1->getValue('usr_id'));
        $relation2->save();
    }

    $gDb->endTransaction();

    $gNavigation->deleteLastUrl();
    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif($getMode === 2)
{
    // delete relation
    try
    {
        if($relation->delete())
        {
            echo 'done';
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
        // => EXIT
    }
}
