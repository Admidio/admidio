<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all profile fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode : list     - (default) Show page with a list of all profile fields
 *         edit     - Show form to create or edit a profile field
 *         save     - Save the data of the form
 *         delete   - Delete a profile field
 *         sequence - Change sequence for a profile field
 * uuid  : UUID of the profile field that should be edited
 * direction : Direction to change the sequence of the profile field
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\Entity\SelectOptions;
use Admidio\ProfileFields\Service\ProfileFieldService;
use Admidio\UI\Presenter\ProfileFieldsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit', 'save', 'delete', 'check_option_entry_status', 'delete_option_entry', 'sequence')));
    $getProfileFieldUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getOptionID = admFuncVariableIsValid($_GET, 'option_id', 'int', array('defaultValue' => 0));

    // only authorized users can edit the profile fields
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'list':
            $profileFields = new ProfileFieldsPresenter();
            $profileFields->createList();
            $gNavigation->addUrl(CURRENT_URL, $profileFields->getHeadline());
            $profileFields->show();
            break;

        case 'edit':
            // set headline of the script
            if ($getProfileFieldUUID !== '') {
                $headline = $gL10n->get('ORG_EDIT_PROFILE_FIELD');
            } else {
                $headline = $gL10n->get('ORG_CREATE_PROFILE_FIELD');
            }

            $gNavigation->addUrl(CURRENT_URL, $headline);
            $profileFields = new ProfileFieldsPresenter('adm_profile_fields_edit');
            $profileFields->createEditForm($getProfileFieldUUID);
            $profileFields->show();
            break;

        case 'save':
            $profileFieldsModule = new ProfileFieldService($gDb, $getProfileFieldUUID);
            $profileFieldsModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $profileFields = new ProfileField($gDb);
            $profileFields->readDataByUuid($getProfileFieldUUID);
            $profileFields->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'check_option_entry_status':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $status = 'error';
            // check if the option entry has any dependencies in the database
            if ($getOptionID > 0) {
                $profileFields = new ProfileField($gDb);
                $profileFields->readDataByUuid($getProfileFieldUUID);

                $option = new SelectOptions($gDb, $profileFields->getValue('usf_id'));
                if ($option->isOptionUsed($getOptionID)) {
                    // if the option is used in a profile field, then it cannot be deleted
                    $status = 'used';
                } else {
                    // option entry can be deleted
                    $status = 'unused';
                }
            }
            echo json_encode(array('status' => $status));
            break;

        case 'delete_option_entry':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $status = 'error';
            // check if the option entry has any dependencies in the database
            if ($getOptionID > 0) {
                $profileFields = new ProfileField($gDb);
                $profileFields->readDataByUuid($getProfileFieldUUID);

                $option = new SelectOptions($gDb, $profileFields->getValue('usf_id'));
                // delete the option entry
                $option->deleteOption($getOptionID);
                $status = 'success';
            }
            echo json_encode(array('status' => $status));
            break;

        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));
            $getOrder      = admFuncVariableIsValid($_GET, 'order', 'array');

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $profileFields = new ProfileField($gDb);
            $profileFields->readDataByUuid($getProfileFieldUUID);
            if (!empty($getOrder)) {
                // set new order (drag and drop)
                $profileFields->setSequence(explode(',', $getOrder));
            } else {
                $profileFields->moveSequence($postDirection);
            }
            echo json_encode(array('status' => 'success'));
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('save', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}

