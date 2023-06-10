<?php
/**
 ***********************************************************************************************
 * Create and edit relation types
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * urt_uuid : UUID of the relation type that should be edited
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUrtUuid = admFuncVariableIsValid($_GET, 'urt_uuid', 'string');

if (!$gSettingsManager->getBool('members_enable_user_relations')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$headline = $gL10n->get('SYS_RELATIONSHIP_CONFIGURATION');
$gNavigation->addUrl(CURRENT_URL, $headline);

$relationType1 = new TableUserRelationType($gDb);
$relationType2 = new TableUserRelationType($gDb);

if ($getUrtUuid !== '') {
    $relationType1->readDataByUuid($getUrtUuid);
    $relationType2->readDataById((int) $relationType1->getValue('urt_id_inverse'));
}

// create html page object
$page = new HtmlPage('admidio-relationtypes-edit', $headline);

// show form
$form = new HtmlForm('relationtype_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_function.php', array('urt_uuid' => $getUrtUuid, 'mode' => '1')), $page);

$form->openGroupBox('gb_user_relationship', $gL10n->get('SYS_USER_RELATION'));
$form->addInput(
    'urt_name',
    $gL10n->get('SYS_NAME'),
    $relationType1->getValue('urt_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'urt_name_male',
    $gL10n->get('SYS_MALE'),
    ($relationType1->getValue('urt_name_male') !== $relationType1->getValue('urt_name')) ? $relationType1->getValue('urt_name_male') : '',
    array('maxLength' => 100)
);
$form->addInput(
    'urt_name_female',
    $gL10n->get('SYS_FEMALE'),
    ($relationType1->getValue('urt_name_female') !== $relationType1->getValue('urt_name')) ? $relationType1->getValue('urt_name_female') : '',
    array('maxLength' => 100)
);
$form->addCheckbox(
    'urt_edit_user',
    $gL10n->get('SYS_EDIT_USER_IN_RELATION'),
    (bool) $relationType1->getValue('urt_edit_user'),
    array('helpTextIdLabel' => 'SYS_RELATIONSHIP_TYPE_EDIT_USER_DESC')
);

$options = array('defaultValue' => $relationType1->getRelationTypeString(), 'helpTextIdLabel' => 'SYS_RELATIONSHIP_TYPE_DESC');
if (!$relationType1->isNewRecord()) {
    $options['property'] = HtmlForm::FIELD_DISABLED;
}

$form->addRadioButton(
    'relation_type',
    $gL10n->get('SYS_USER_RELATION_TYPE'),
    array(
        'asymmetrical'   => $gL10n->get('SYS_ASYMMETRICAL'),
        'symmetrical'    => $gL10n->get('SYS_SYMMETRICAL'),
        'unidirectional' => $gL10n->get('SYS_UNIDIRECTIONAL')
    ),
    $options
);
$form->closeGroupBox();

$form->openGroupBox('gb_opposite_relationship', $gL10n->get('SYS_OPPOSITE_RELATIONSHIP'));
$page->addJavascript(
    '
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
            $("#gb_opposite_relationship").hide(duration);
            $("#urt_name_inverse_group").hide(duration);
            $("#urt_name_male_inverse_group").hide(duration);
            $("#urt_name_female_inverse_group").hide(duration);
            $("#urt_edit_user_inverse_group").hide(duration);
        }
        else if ($(element).val() === "asymmetrical") {
            $("#urt_name_inverse").prop("required", true);
            $("#gb_opposite_relationship").show(duration);
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
    'urt_name_inverse',
    $gL10n->get('SYS_NAME'),
    $relationType2->getValue('urt_name'),
    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'urt_name_male_inverse',
    $gL10n->get('SYS_MALE'),
    ($relationType2->getValue('urt_name_male') !== $relationType2->getValue('urt_name')) ? $relationType2->getValue('urt_name_male') : '',
    array('maxLength' => 100)
);
$form->addInput(
    'urt_name_female_inverse',
    $gL10n->get('SYS_FEMALE'),
    ($relationType2->getValue('urt_name_female') !== $relationType2->getValue('urt_name')) ? $relationType2->getValue('urt_name_female') : '',
    array('maxLength' => 100)
);
$form->addCheckbox(
    'urt_edit_user_inverse',
    $gL10n->get('SYS_EDIT_USER_IN_RELATION'),
    (bool) $relationType2->getValue('urt_edit_user'),
    array('helpTextIdLabel' => 'SYS_RELATIONSHIP_TYPE_EDIT_USER_DESC')
);
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $relationType1->getValue('urt_usr_id_create'),
    $relationType1->getValue('urt_timestamp_create'),
    (int) $relationType1->getValue('urt_usr_id_change'),
    $relationType1->getValue('urt_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
