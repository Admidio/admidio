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
 * mode     : edit    - (Default) Edit current organization and show sub-organizations.
 *            new_sub - Create a new sub-organization for the current organization.
 *            save    - Save form data of the current organization
 ****************************************************************************/
use Admidio\Exception;
use Admidio\UserInterface\Organizations;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'edit', 'validValues' => array('edit', 'new_sub', 'save')));

    // check if the current user has the right to
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if($getMode === 'edit') {
        // Edit current organization and show sub-organizations.
        $headline = $gL10n->get('SYS_ORGANIZATION');
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-diagram-3-fill');

        // create html page object
        $page = new Organizations('adm_organization_edit', $headline);
        $page->createContentEditForm();
        $page->show();
    } elseif ($getMode === 'new_sub') {
        // Create a new sub-organization for the current organization
        $headline = $gL10n->get('SYS_ADD_ORGANIZATION');
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new Organizations('adm_new_sub_organization', $headline);
        $page->createContentSubOrganizationForm();
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
