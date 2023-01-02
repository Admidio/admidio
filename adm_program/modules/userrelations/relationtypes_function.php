<?php
/**
 ***********************************************************************************************
 * Various functions for relationtypes
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * urt_uuid : UUID of the relation type that should be edited
 * mode     : 1 - Create or edit relationtype
 *            2 - Delete relationtype
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUrtUuid = admFuncVariableIsValid($_GET, 'urt_uuid', 'string');
$getMode    = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));

if (!$gSettingsManager->getBool('members_enable_user_relations')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($getMode === 1) {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

$relationType = new TableUserRelationType($gDb);

if ($getUrtUuid !== '') {
    $relationType->readDataByUuid($getUrtUuid);
}

if ($getMode === 1) {
    // create or edit relationtype

    if (!isset($_POST['urt_edit_user'])) {
        $_POST['urt_edit_user'] = 0;
    }

    if (!isset($_POST['urt_edit_user_inverse'])) {
        $_POST['urt_edit_user_inverse'] = 0;
    }

    $relationType2 = new TableUserRelationType($gDb);
    if ($getUrtUuid !== '') {
        $relationType2->readDataById((int) $relationType->getValue('urt_id_inverse'));
    }

    $relationType->setValue('urt_name', $_POST['urt_name']);
    $relationType->setValue('urt_name_male', empty($_POST['urt_name_male']) ? $_POST['urt_name'] : $_POST['urt_name_male']);
    $relationType->setValue('urt_name_female', empty($_POST['urt_name_female']) ? $_POST['urt_name'] : $_POST['urt_name_female']);
    $relationType->setValue('urt_edit_user', $_POST['urt_edit_user']);

    $postRelationType = admFuncVariableIsValid(
        $_POST,
        'relation_type',
        'string',
        array(
            'defaultValue' => $relationType->getRelationTypeString(),
            'validValues' => array(
                TableUserRelationType::USER_RELATION_TYPE_ASYMMETRICAL,
                TableUserRelationType::USER_RELATION_TYPE_SYMMETRICAL,
                TableUserRelationType::USER_RELATION_TYPE_UNIDIRECTIONAL
            )
        )
    );
    if ($postRelationType === 'asymmetrical') {
        $relationType2->setValue('urt_name', $_POST['urt_name_inverse']);
        $relationType2->setValue('urt_name_male', empty($_POST['urt_name_male_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_male_inverse']);
        $relationType2->setValue('urt_name_female', empty($_POST['urt_name_female_inverse']) ? $_POST['urt_name_inverse'] : $_POST['urt_name_female_inverse']);
        $relationType2->setValue('urt_edit_user', $_POST['urt_edit_user_inverse']);
    }

    // write data into database
    $gDb->startTransaction();

    $relationType->save();

    if ($postRelationType === 'asymmetrical') {
        if ($getUrtUuid === '') {
            $relationType2->setValue('urt_id_inverse', (int) $relationType->getValue('urt_id'));
        }

        $relationType2->save();

        if ($getUrtUuid === '') {
            $relationType->setValue('urt_id_inverse', (int) $relationType2->getValue('urt_id'));
            $relationType->save();
        }
    } elseif ($postRelationType === 'symmetrical') {
        $relationType->setValue('urt_id_inverse', (int) $relationType->getValue('urt_id'));
        $relationType->save();
    }

    $gDb->endTransaction();

    $gNavigation->deleteLastUrl();
    admRedirect($gNavigation->getUrl());
// => EXIT
} elseif ($getMode === 2) {
    // delete relationtype
    try {
        if ($relationType->delete()) {
            echo 'done';
        }
    } catch (AdmException $e) {
        $e->showText();
        // => EXIT
    }
}
