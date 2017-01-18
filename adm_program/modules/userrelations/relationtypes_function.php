<?php
/**
 ***********************************************************************************************
 * Various functions for relationtypes
 *
 * @copyright 2004-2017 The Admidio Team
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

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUrtId = admFuncVariableIsValid($_GET, 'urt_id', 'int');
$getMode  = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

if ($gPreferences['members_enable_user_relations'] == 0)
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
    // relationtype anlegen oder updaten

    $relationtype2 = new TableUserRelationType($gDb);
    if($getUrtId > 0)
    {
        $relationtype2->readDataById($relationtype->getValue('urt_id_inverse'));
    }

    $relationtype->setValue('urt_name', $_POST['urt_name']);
    $relationtype->setValue('urt_name_male', empty($_POST['urt_name_male']) ? $_POST['urt_name'] : $_POST['urt_name_male']);
    $relationtype->setValue('urt_name_female', empty($_POST['urt_name_female']) ? $_POST['urt_name'] : $_POST['urt_name_female']);

    $postRelationType = admFuncVariableIsValid(
        $_POST, 'relation_type', 'string',
        array('defaultValue' => $relationtype->getRelationTypeString(), 'validValues' => array('asymmetrical', 'symmetrical', 'unidirectional'))
    );
    if ($postRelationType === 'asymmetrical')
    {
        $relationtype2->setValue('urt_name', $_POST['urt_name_inverse']);
        $relationtype2->setValue('urt_name_male', empty($_POST['urt_name_male_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_male_inverse']);
        $relationtype2->setValue('urt_name_female', empty($_POST['urt_name_female_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_female_inverse']);
    }

    // Daten in Datenbank schreiben
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
