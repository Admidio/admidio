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
 * mode     : edit    - (Default) Edit current organization and show sub-organizations
 *            new_sub - Create a new sub-organization for the current organization
 *            save    - Save form data of the current organization
 *            create  - Creates a nre organization for the current organization
 *            create_success - Show dialog if organization was successfully added
 *            delete  - Deletes a sub-organization
 * org_uuid : UUID of the sub-organization
 ****************************************************************************/

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;
use Admidio\Organizations\Service\OrganizationService;
use Admidio\UI\Presenter\OrganizationPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'edit', 'validValues' => array('edit', 'new_sub', 'save', 'create', 'create_success', 'delete')));
    $getOrganizationUUID = admFuncVariableIsValid($_GET, 'org_uuid', 'uuid');

    // check if the current user has the right to
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if ($getMode === 'edit') {
        // Edit current organization and show sub-organizations.
        $page = new OrganizationPresenter();
        $page->createEditForm();
        $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-diagram-3-fill');
        $page->show();
    } elseif ($getMode === 'new_sub') {
        // Create a new sub-organization for the current organization
        $headline = $gL10n->get('SYS_ADD_ORGANIZATION');
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new OrganizationPresenter('adm_new_sub_organization', $headline);
        $page->createSubOrganizationForm();
        $page->show();
    } elseif ($getMode === 'save') {
        $organizationModule = new OrganizationService();
        $organizationModule->save($_POST);

        echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA')));
        exit();
    } elseif ($getMode === 'create') {
        // Creates a new organization for the current organization
        $organizationModule = new OrganizationService();
        $organizationModule->create();
        $gNavigation->deleteLastUrl();

        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations.php', array('mode' => 'create_success'))
        ));
    } elseif ($getMode === 'create_success') {
        // Show dialog if organization was successfully added
        $subOrganization = new Organization($gDb);
        $subOrganization->readDataByUuid($getOrganizationUUID);
        $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/organizations.php');
        $gMessage->show($gL10n->get('ORG_ORGANIZATION_SUCCESSFULLY_ADDED', array($_SESSION['organizationLongName'])), $gL10n->get('INS_SETUP_WAS_SUCCESSFUL'));
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete sub-organization
        $subOrganization = new Organization($gDb);
        $subOrganization->readDataByUuid($getOrganizationUUID);
        if ($subOrganization->getValue('org_org_id_parent') === $gCurrentOrgId) {
            $subOrganization->delete();
        } else {
            throw new Exception('The organization ' . $subOrganization->getValue('org_longname') . ' is not
            a sub-organization of the current organization ' . $gCurrentOrganization->getValue('org_longname') . '!');
        }

        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('save', 'create', 'delete')));
}
