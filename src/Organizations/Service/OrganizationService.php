<?php
namespace Admidio\Organizations\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Organizations\Entity\Organization;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the organization module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class OrganizationService
{
    /**
     * Create the data for the edit form of an organization.
     * @throws Exception
     */
    public function create()
    {
        global $gDb, $gCurrentSession, $gCurrentOrgId, $gCurrentUserId, $gSettingsManager;

        // check form field input and sanitized it from malicious content
        $newOrganizationForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $newOrganizationForm->validate($_POST);

        // check if organization shortname exists
        $organization = new Organization($gDb, $formValues['adm_organization_short_name']);
        if ($organization->getValue('org_id') > 0) {
            throw new Exception('INS_ORGA_SHORTNAME_EXISTS', array($formValues['adm_organization_short_name']));
        }

        // allow only letters, numbers and special characters like .-_+@
        if (!StringUtils::strValidCharacters($formValues['adm_organization_short_name'], 'noSpecialChar')) {
            throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
        }

        // set execution time to 2 minutes because we have a lot to do
        PhpIniUtils::startNewExecutionTimeLimit(120);

        $gDb->startTransaction();

        // create new organization
        $_SESSION['organizationLongName'] = $formValues['adm_organization_long_name'];
        $newOrganization = new Organization($gDb, $formValues['adm_organization_short_name']);
        $newOrganization->setValue('org_longname', $formValues['adm_organization_long_name']);
        $newOrganization->setValue('org_shortname', $formValues['adm_organization_short_name']);
        $newOrganization->setValue('org_homepage', ADMIDIO_URL);
        $newOrganization->setValue('org_email_administrator', $formValues['adm_organization_email']);
        $newOrganization->setValue('org_show_org_select', true);
        $newOrganization->setValue('org_org_id_parent', $gCurrentOrgId);
        $newOrganization->save();

        // After setting up the base organization record, we don't want to add changelog entries for all the copying of the settings to the new org!
        Entity::setLoggingEnabled(false);

        // write all preferences from preferences.php in table adm_preferences
        require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

        // set some specific preferences whose values came from user input of the installation wizard
        $defaultOrgPreferences['system_language'] = $gSettingsManager->getString('system_language');

        // create all necessary data for this organization
        $settingsManager =& $newOrganization->getSettingsManager();
        $settingsManager->setMulti($defaultOrgPreferences, false);
        $newOrganization->createBasicData($gCurrentUserId);

        // now refresh the session organization object because of the new organization
        $currentOrganizationId = $gCurrentOrgId;
        $gCurrentOrganization = new Organization($gDb, $currentOrganizationId);

        // if installation of second organization than show organization select at login
        if ($gCurrentOrganization->countAllRecords() === 2) {
            $gCurrentOrganization->setValue('org_show_org_select', true);
            $gCurrentOrganization->save();
        }

        $gDb->endTransaction();
    }

    /**
     * Save the data of an organization's editing form.
     * @param array $formValues An array with all the form values that are stored in the global POST param
     * @throws Exception
     */
    public function save(array $formValues)
    {
        global $gCurrentSession, $gCurrentOrganization;

        // check form field input and sanitized it from malicious content
        $organizationEditForm = $gCurrentSession->getFormObject($formValues['adm_csrf_token']);
        $validatedFormValues = $organizationEditForm->validate($formValues);

        // write form values in category object
        foreach ($validatedFormValues as $key => $value) {
            if (str_starts_with($key, 'org_') && $key !== 'org_shortname') {
                $gCurrentOrganization->setValue($key, $value);
            }
        }

        // write category into database
        $gCurrentOrganization->save();
    }
}
