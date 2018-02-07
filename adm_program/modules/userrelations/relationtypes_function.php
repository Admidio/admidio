<?php
/**
 ***********************************************************************************************
 * Various functions for relationtypes
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * urt_id: Id of the relation type that should be edited
 * mode  : 1 - Create or edit relationtype
 *         2 - Delete relationtype
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUrtId = admFuncVariableIsValid($_GET, 'urt_id', 'int');
$getMode  = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

if (!$gSettingsManager->getBool('members_enable_user_relations'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$relationtype = new TableUserRelationType($gDb);

if($getUrtId > 0)
{
    $relationtype->readDataById($getUrtId);
}

if($getMode === 1)
{
    // create or edit relationtype

    if(!isset($_POST['urt_edit_user']))
    {
        $_POST['urt_edit_user'] = 0;
    }

    if(!isset($_POST['urt_edit_user_inverse']))
    {
        $_POST['urt_edit_user_inverse'] = 0;
    }

    $relationtype2 = new TableUserRelationType($gDb);
    if($getUrtId > 0)
    {
        $relationtype2->readDataById($relationtype->getValue('urt_id_inverse'));
    }

    $relationtype->setValue('urt_name', $_POST['urt_name']);
    $relationtype->setValue('urt_name_male', empty($_POST['urt_name_male']) ? $_POST['urt_name'] : $_POST['urt_name_male']);
    $relationtype->setValue('urt_name_female', empty($_POST['urt_name_female']) ? $_POST['urt_name'] : $_POST['urt_name_female']);
    $relationtype->setValue('urt_edit_user', $_POST['urt_edit_user']);

    $postRelationType = admFuncVariableIsValid(
        $_POST, 'relation_type', 'string',
        array(
            'defaultValue' => $relationtype->getRelationTypeString(),
            'validValues' => array(
                TableUserRelationType::USER_RELATION_TYPE_ASYMMETRICAL,
                TableUserRelationType::USER_RELATION_TYPE_SYMMETRICAL,
                TableUserRelationType::USER_RELATION_TYPE_UNIDIRECTIONAL
            )
        )
    );
    if ($postRelationType === 'asymmetrical')
    {
        $relationtype2->setValue('urt_name', $_POST['urt_name_inverse']);
        $relationtype2->setValue('urt_name_male', empty($_POST['urt_name_male_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_male_inverse']);
        $relationtype2->setValue('urt_name_female', empty($_POST['urt_name_female_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_female_inverse']);
        $relationtype2->setValue('urt_edit_user', $_POST['urt_edit_user_inverse']);
    }

    // write data into database
    $gDb->startTransaction();

    $relationtype->save();

    if ($postRelationType === 'asymmetrical')
    {
        if($getUrtId <= 0)
        {
            $relationtype2->setValue('urt_id_inverse', $relationtype->getValue('urt_id'));
        }

        $relationtype2->save();

        if($getUrtId <= 0)
        {
            $relationtype->setValue('urt_id_inverse', $relationtype2->getValue('urt_id'));
            $relationtype->save();
        }
    }
    elseif ($postRelationType === 'symmetrical')
    {
        $relationtype->setValue('urt_id_inverse', $relationtype->getValue('urt_id'));
        $relationtype->save();
    }

    $gDb->endTransaction();

    $gNavigation->deleteLastUrl();
    admRedirect($gNavigation->getUrl());
    // => EXIT
}
elseif($getMode === 2)
{
    // delete relationtype
    try
    {
        if($relationtype->delete())
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
