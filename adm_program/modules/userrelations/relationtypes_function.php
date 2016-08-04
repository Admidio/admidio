<?php
/**
 ***********************************************************************************************
 * Various functions for relationtypes
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * urt_id: Id of the category that should be edited
 * mode  : 1 - Create or edit relationtype
 *         2 - Delete relationtype
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUrtId    = admFuncVariableIsValid($_GET, 'urt_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));

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

    $relationtype->setValue('urt_name_singular', empty($_POST['urt_name_singular']) ? $_POST['urt_name_plural'] : $_POST['urt_name_singular']);
    $relationtype->setValue('urt_name_plural', empty($_POST['urt_name_plural']) ? $_POST['urt_name_singular'] : $_POST['urt_name_plural']);
    $relationtype2->setValue('urt_name_singular', empty($_POST['urt_name_singular_inverse']) ? $_POST['urt_name_plural_inverse'] : $_POST['urt_name_singular_inverse']);
    $relationtype2->setValue('urt_name_plural', empty($_POST['urt_name_plural_inverse']) ? $_POST['urt_name_singular_inverse'] : $_POST['urt_name_plural_inverse']);

    // Daten in Datenbank schreiben
    $gDb->startTransaction();
    
    $relationtype->save();
    
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

    $gDb->endTransaction();
    
    $gNavigation->deleteLastUrl();
    header('Location: '. $gNavigation->getUrl());
    exit();
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
