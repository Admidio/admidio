<?php
/**
 ***********************************************************************************************
 * Edit current organization and create child organizations
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ******************************************************************************
 * Parameters:
 *
 * cat_uuid : Uuid of the category, that should be edited
 * mode     :
 ****************************************************************************/
use Admidio\Exception;
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('save')));

    // check if the current user has the right to
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if($getMode === '') {
        $headline = $gL10n->get('SYS_ORGANIZATION');
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new HtmlPage('organizationEdit', $headline);

        // show form
        $formOrganization = new Form(
            'organizationEditForm',
            'modules/organizations.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations/organizations.php', array('mode' => 'save')),
            $page
        );
        $formOrganization->addInput(
            'org_shortname',
            $gL10n->get('SYS_NAME_ABBREVIATION'),
            $gCurrentOrganization->getValue('org_shortname'),
            array('property' => Form::FIELD_DISABLED, 'class' => 'form-control-small')
        );
        $formOrganization->addInput(
            'org_longname',
            $gL10n->get('SYS_NAME'),
            $gCurrentOrganization->getValue('org_longname'),
            array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
        );
        $formOrganization->addInput(
            'org_homepage',
            $gL10n->get('SYS_WEBSITE'),
            $gCurrentOrganization->getValue('org_homepage'),
            array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
        );
        $formOrganization->addInput(
            'org_email_administrator',
            $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
            $gCurrentOrganization->getValue('org_email_administrator'),
            array('type' => 'email', 'property' => Form::FIELD_REQUIRED, 'maxLength' => 254, 'helpTextId' => 'SYS_EMAIL_ADMINISTRATOR_DESC')
        );

        if ($gCurrentOrganization->countAllRecords() > 1) {
            // Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
            if (!$gCurrentOrganization->isParentOrganization()) {
                $sqlData = array();
                $sqlData['query'] = 'SELECT org_id, org_longname
                               FROM ' . TBL_ORGANIZATIONS . '
                              WHERE org_id <> ? -- $gCurrentOrgId
                                AND org_org_id_parent IS NULL
                           ORDER BY org_longname, org_shortname';
                $sqlData['params'] = array($gCurrentOrgId);
                $formOrganization->addSelectBoxFromSql(
                    'org_org_id_parent',
                    $gL10n->get('ORG_PARENT_ORGANIZATION'),
                    $gDb,
                    $sqlData,
                    array('defaultValue' => $formValues['org_org_id_parent'], 'helpTextId' => 'ORG_PARENT_ORGANIZATION_DESC')
                );
            }

            $formOrganization->addCheckbox(
                'org_show_org_select',
                $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'),
                $gCurrentOrganization->getValue('org_show_org_select'),
                array('helpTextId' => 'ORG_SHOW_ORGANIZATION_SELECT_DESC')
            );
        }
        $formOrganization->addSubmitButton(
            'btn_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formOrganization->addToHtmlPage();
        $gCurrentSession->addFormObject($formOrganization);

        $page->show();
    } elseif ($getMode === 'save') {
        // check form field input and sanitized it from malicious content
        $organizationEditForm = $gCurrentSession->getFormObject($_POST['admidio-csrf-token']);
        $formValues = $organizationEditForm->validate($_POST);

        // write form values in category object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'org_') && $key !== 'org_shortname') {
                $gCurrentOrganization->setValue($key, $value);
            }
        }

        // write category into database
        $gCurrentOrganization->save();

        echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA')));
        exit();
    }
} catch (Exception $e) {
    if ($getMode === 'save') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
