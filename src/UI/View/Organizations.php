<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\UI\Component\Form;
use HtmlPage;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the organization module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new Organizations('adm_organization', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Organizations extends HtmlPage
{
    /**
     * Create the data for the edit form of an organization.
     * @throws Exception
     */
    public function createEditForm()
    {
        global $gL10n, $gCurrentOrganization, $gDb, $gCurrentOrgId, $gCurrentSession, $gSettingsManager;
        $this->addJavascript('
            $("#adm_button_save").hide();

            $("input").on("input", function() {
                $("#adm_button_save").show("slow");
            })
            $("select").on("input", function() {
                $("#adm_button_save").show("slow");
            })
        ', true);

        // show link to view profile field change history
        if ($gSettingsManager->getBool('profile_log_edit_fields')) {
            $this->addPageFunctionsMenuItem(
                'menu_item_organizations_change_history',
                $gL10n->get('SYS_CHANGE_HISTORY'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog.php', array('table' => 'organizations')),
                'bi-clock-history'
            );
        }

        // show form
        $formOrganization = new Form(
            'adm_organization_edit_form',
            'modules/organizations.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations.php', array('mode' => 'save')),
            $this
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
            array('type' => 'email', 'property' => Form::FIELD_REQUIRED, 'maxLength' => 254)
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
                    $gL10n->get('SYS_PARENT_ORGANIZATION'),
                    $gDb,
                    $sqlData,
                    array('defaultValue' => $gCurrentOrganization->getValue('org_org_id_parent'), 'helpTextId' => 'SYS_PARENT_ORGANIZATION_DESC')
                );
            }

            $formOrganization->addCheckbox(
                'org_show_org_select',
                $gL10n->get('SYS_SHOW_ORGANIZATION_SELECT'),
                $gCurrentOrganization->getValue('org_show_org_select'),
                array('helpTextId' => 'SYS_SHOW_ORGANIZATION_SELECT_DESC')
            );
        }
        $formOrganization->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        // create list with all subordinate organizations of the current organization
        $sql = 'SELECT org.* FROM ' . TBL_ORGANIZATIONS . ' org
                 WHERE org_org_id_parent = ? /* $gCurrentOrgId */';
        $queryParameters = array($gCurrentOrgId);
        $organizations = $gDb->getArrayFromSql($sql, $queryParameters);
        $this->assignSmartyVariable('organizationsList', $organizations);

        $formOrganization->addToHtmlPage();
        $gCurrentSession->addFormObject($formOrganization);
    }

    /**
     * Create the data for a form to add a new sub-organization to the current organization.
     * @throws Exception
     */
    public function createSubOrganizationForm()
    {
        global $gL10n, $gCurrentSession;

        $form = new Form(
            'adm_new_sub_organization_form',
            'modules/organizations.new.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations.php', array('mode' => 'create')),
            $this
        );
        $form->addInput(
            'adm_organization_short_name',
            $gL10n->get('SYS_NAME_ABBREVIATION'),
            '',
            array('maxLength' => 10, 'property' => Form::FIELD_REQUIRED, 'class' => 'form-control-small')
        );
        $form->addInput(
            'adm_organization_long_name',
            $gL10n->get('SYS_NAME'),
            '',
            array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
        );
        $form->addInput(
            'adm_organization_email',
            $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
            '',
            array('type' => 'email', 'maxLength' => 254, 'property' => Form::FIELD_REQUIRED)
        );
        $form->addSubmitButton(
            'adm_button_forward',
            $gL10n->get('INS_SET_UP_ORGANIZATION'),
            array('icon' => 'bi-wrench', 'class' => 'offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
