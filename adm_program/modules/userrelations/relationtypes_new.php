<?php
/**
 ***********************************************************************************************
 * Create and edit relation types
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * urt_id : Id of the relation type that should be edited
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUrtId = admFuncVariableIsValid($_GET, 'urt_id', 'int');

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

$headline = $gL10n->get('SYS_USER_RELATION_TYPES');
$gNavigation->addUrl(CURRENT_URL, $headline);

$relationtype1 = new TableUserRelationType($gDb);
$relationtype2 = new TableUserRelationType($gDb);

if($getUrtId > 0)
{
    $relationtype1->readDataById($getUrtId);
    $relationtype2->readDataById($relationtype1->getValue('urt_id_inverse'));
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$relationtypeEditMenu = $page->getMenu();
$relationtypeEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('relationtype_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_function.php', array('urt_id' => $getUrtId, 'mode' => '1')), $page);

$form->addInput(
    'urt_name', $gL10n->get('REL_USER_RELATION_TYPE_FORWARD'), $relationtype1->getValue('urt_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'urt_name_male', $gL10n->get('REL_USER_RELATION_TYPE_FORWARD').' '.$gL10n->get('SYS_MALE'),
    ($relationtype1->getValue('urt_name_male') !== $relationtype1->getValue('urt_name')) ? $relationtype1->getValue('urt_name_male') : '',
    array('maxLength' => 100)
);
$form->addInput(
    'urt_name_female', $gL10n->get('REL_USER_RELATION_TYPE_FORWARD').' '.$gL10n->get('SYS_FEMALE'),
    ($relationtype1->getValue('urt_name_female') !== $relationtype1->getValue('urt_name')) ? $relationtype1->getValue('urt_name_female') : '',
    array('maxLength' => 100)
);
$form->addCheckbox(
    'urt_edit_user', $gL10n->get('REL_EDIT_USER_IN_RELATION'), (bool) $relationtype1->getValue('urt_edit_user'),
    array('helpTextIdLabel' => 'REL_EDIT_USER_DESC')
);

$options = array('defaultValue' => $relationtype1->getRelationTypeString(), 'helpTextIdLabel' => 'REL_USER_RELATION_TYPE_DESC');
if (!$relationtype1->isNewRecord())
{
    $options['property'] = HtmlForm::FIELD_DISABLED;
}

$form->addRadioButton(
    'relation_type', $gL10n->get('SYS_USER_RELATION_TYPE'),
    array(
        'asymmetrical'   => $gL10n->get('REL_USER_RELATION_TYPE_ASYMMETRICAL'),
        'symmetrical'    => $gL10n->get('REL_USER_RELATION_TYPE_SYMMETRICAL'),
        'unidirectional' => $gL10n->get('REL_USER_RELATION_TYPE_UNIDIRECTIONAL')
    ),
    $options
);
$page->addJavascript('
    function checkRelationTypeNames() {
        $("#btn_save").prop("disabled", $("#urt_name").val() === $("#urt_name_inverse").val());
    }
    $("#urt_name").on("input", checkRelationTypeNames);
    $("#urt_name_inverse").on("input", checkRelationTypeNames);

    /**
     * @param {object} element
     * @param {int}    duration
     */
    function updateRelationType(element, duration) {
        if ($(element).val() === "unidirectional" || $(element).val() === "symmetrical") {
            $("#urt_name_inverse").prop("required", false);
            $("#urt_name_inverse_group").hide(duration);
            $("#urt_name_male_inverse_group").hide(duration);
            $("#urt_name_female_inverse_group").hide(duration);
            $("#urt_edit_user_inverse_group").hide(duration);
        }
        else if ($(element).val() === "asymmetrical") {
            $("#urt_name_inverse").prop("required", true);
            $("#urt_name_inverse_group").show(duration);
            $("#urt_name_male_inverse_group").show(duration);
            $("#urt_name_female_inverse_group").show(duration);
            $("#urt_edit_user_inverse_group").show(duration);
        }
    }
    $("input[type=radio][name=relation_type]").change(function() {
        updateRelationType(this, "slow");
    });
    updateRelationType($("input[type=radio][name=relation_type]:checked"));
    ',
    true
);

$form->addInput(
    'urt_name_inverse', $gL10n->get('REL_USER_RELATION_TYPE_BACKWARD'), $relationtype2->getValue('urt_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'urt_name_male_inverse', $gL10n->get('REL_USER_RELATION_TYPE_BACKWARD').' '.$gL10n->get('SYS_MALE'),
    ($relationtype2->getValue('urt_name_male') !== $relationtype2->getValue('urt_name')) ? $relationtype2->getValue('urt_name_male') : '',
    array('maxLength' => 100)
);
$form->addInput(
    'urt_name_female_inverse', $gL10n->get('REL_USER_RELATION_TYPE_BACKWARD').' '.$gL10n->get('SYS_FEMALE'),
    ($relationtype2->getValue('urt_name_female') !== $relationtype2->getValue('urt_name')) ? $relationtype2->getValue('urt_name_female') : '',
    array('maxLength' => 100)
);
$form->addCheckbox(
    'urt_edit_user_inverse', $gL10n->get('REL_EDIT_USER_IN_RELATION'), (bool) $relationtype2->getValue('urt_edit_user'),
    array('helpTextIdLabel' => 'REL_EDIT_USER_DESC')
);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $relationtype1->getValue('urt_usr_id_create'), $relationtype1->getValue('urt_timestamp_create'),
    (int) $relationtype1->getValue('urt_usr_id_change'), $relationtype1->getValue('urt_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
