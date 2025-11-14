<?php
/**
 ***********************************************************************************************
 * Create and edit relation types
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * urt_uuid : UUID of the relation type that should be edited
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUrtUuid = admFuncVariableIsValid($_GET, 'urt_uuid', 'uuid');

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $headline = $gL10n->get('SYS_RELATIONSHIP_CONFIGURATION');
    $gNavigation->addUrl(CURRENT_URL, $headline);

    $relationType1 = new UserRelationType($gDb);
    $relationType2 = new UserRelationType($gDb);

    if ($getUrtUuid !== '') {
        $relationType1->readDataByUuid($getUrtUuid);
        $relationType2->readDataById((int)$relationType1->getValue('urt_id_inverse'));
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-relationtypes-edit', $headline);
    $page->addJavascript('
        function checkRelationTypeNames() {
            $("#adm_button_save").prop("disabled", $("#urt_name").val() === $("#urt_name_inverse").val());
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
                $("#urt_name_inverse_group").removeClass("admidio-form-group-required");
                $("#urt_name_inverse_group").hide(duration);
                $("#urt_name_male_inverse_group").hide(duration);
                $("#urt_name_female_inverse_group").hide(duration);
                $("#urt_edit_user_inverse_group").hide(duration);
            }
            else if ($(element).val() === "asymmetrical") {
                $("#urt_name_inverse").prop("required", true);
                $("#gb_opposite_relationship").show(duration);
                $("#urt_name_inverse_group").addClass("admidio-form-group-required");
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

    ChangelogService::displayHistoryButton($page, 'user_relation_types', 'user_relation_types', !empty($getUrtUuid), array('uuid' => $getUrtUuid));

    // show form
    $form = new FormPresenter(
        'adm_user_relations_type_edit_form',
        'modules/user-relations.type.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes_function.php', array('urt_uuid' => $getUrtUuid, 'mode' => 'edit')),
        $page
    );
    $form->addInput(
        'urt_name',
        $gL10n->get('SYS_NAME'),
        htmlentities($relationType1->getValue('urt_name', 'database'), ENT_QUOTES),
        array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'urt_name_male',
        $gL10n->get('SYS_MALE'),
        ($relationType1->getValue('urt_name_male', 'database') !== $relationType1->getValue('urt_name', 'database')) ? htmlentities($relationType1->getValue('urt_name_male', 'database'), ENT_QUOTES) : '',
        array('maxLength' => 100)
    );
    $form->addInput(
        'urt_name_female',
        $gL10n->get('SYS_FEMALE'),
        ($relationType1->getValue('urt_name_female', 'database') !== $relationType1->getValue('urt_name', 'database')) ? htmlentities($relationType1->getValue('urt_name_female', 'database'), ENT_QUOTES) : '',
        array('maxLength' => 100)
    );
    $form->addCheckbox(
        'urt_edit_user',
        $gL10n->get('SYS_EDIT_USER_IN_RELATION'),
        (bool)$relationType1->getValue('urt_edit_user'),
        array('helpTextId' => 'SYS_RELATIONSHIP_TYPE_EDIT_USER_DESC')
    );

    $options = array('defaultValue' => $relationType1->getRelationTypeString(), 'helpTextId' => 'SYS_RELATIONSHIP_TYPE_DESC');
    if (!$relationType1->isNewRecord()) {
        $options['property'] = FormPresenter::FIELD_DISABLED;
    }

    $form->addRadioButton(
        'relation_type',
        $gL10n->get('SYS_USER_RELATION_TYPE'),
        array(
            'asymmetrical' => $gL10n->get('SYS_ASYMMETRICAL'),
            'symmetrical' => $gL10n->get('SYS_SYMMETRICAL'),
            'unidirectional' => $gL10n->get('SYS_UNIDIRECTIONAL')
        ),
        $options
    );
    $form->addInput(
        'urt_name_inverse',
        $gL10n->get('SYS_NAME'),
        htmlentities($relationType2->getValue('urt_name', 'database'), ENT_QUOTES),
        array('maxLength' => 100)
    );
    $form->addInput(
        'urt_name_male_inverse',
        $gL10n->get('SYS_MALE'),
        ($relationType2->getValue('urt_name_male', 'database') !== $relationType2->getValue('urt_name', 'database')) ? htmlentities($relationType2->getValue('urt_name_male', 'database'), ENT_QUOTES) : '',
        array('maxLength' => 100)
    );
    $form->addInput(
        'urt_name_female_inverse',
        $gL10n->get('SYS_FEMALE'),
        ($relationType2->getValue('urt_name_female', 'database') !== $relationType2->getValue('urt_name', 'database')) ? htmlentities($relationType2->getValue('urt_name_female', 'database'), ENT_QUOTES) : '',
        array('maxLength' => 100)
    );
    $form->addCheckbox(
        'urt_edit_user_inverse',
        $gL10n->get('SYS_EDIT_USER_IN_RELATION'),
        (bool)$relationType2->getValue('urt_edit_user'),
        array('helpTextId' => 'SYS_RELATIONSHIP_TYPE_EDIT_USER_DESC')
    );
    $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->assignSmartyVariable('userCreatedName', $relationType1->getNameOfCreatingUser());
    $page->assignSmartyVariable('userCreatedTimestamp', $relationType1->getValue('ann_timestamp_create'));
    $page->assignSmartyVariable('lastUserEditedName', $relationType1->getNameOfLastEditingUser());
    $page->assignSmartyVariable('lastUserEditedTimestamp', $relationType1->getValue('ann_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
