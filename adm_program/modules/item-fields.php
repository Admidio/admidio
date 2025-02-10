<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all item fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *  Parameters:
 *
 *  mode : list     - (default) Show page with a list of all item fields
 *         edit     - Show form to create or edit a item field
 *         save     - Save the data of the form
 *         delete   - Delete a item field
 *         sequence - Change sequence for a item field
 * direction : Direction to change the sequence of the item field
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Service\ItemFieldService;

use Admidio\UI\View\ItemFields;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list', 'validValues' => array('list', 'edit', 'save', 'delete', 'sequence')));
    $getinfId = admFuncVariableIsValid($_GET, 'uuid', 'int', array('defaultValue' => 0));
    $getRedirectToImport = admFuncVariableIsValid($_GET, 'redirect_to_import', 'bool', array('defaultValue' => false));
    

    // only authorized users can edit the item fields
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'list':
            $headline = $gL10n->get('SYS_INVENTORY_ITEM_FIELDS');
            $gNavigation->addUrl(CURRENT_URL, $headline);
            $itemFields = new ItemFields('adm_inventory_item_fields', $headline);
            $itemFields->createList();
            $itemFields->show();
            break;

        case 'edit':
            // set headline of the script
            if ($getinfId !== '') {
                $headline = $gL10n->get('SYS_INVENTORY_EDIT_ITEM_FIELD');
            } else {
                $headline = $gL10n->get('SYS_INVENTORY_CREATE_ITEM_FIELD');
            }

            $gNavigation->addUrl(CURRENT_URL, $headline);
            $itemFields = new ItemFields('adm_item_fields_edit');
            $itemFields->createEditForm($getinfId);
            $itemFields->show();
            break;

        case 'save':
            $itemFieldsModule = new ItemFieldService($gDb, $getinfId);
            $itemFieldsModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $itemFields = new ItemField($gDb);
            $itemFields->readDataById($getinfId);
            $itemFields->delete();
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            // Update menu entry sequence
            $postDirection = admFuncVariableIsValid($_POST, 'direction', 'string', array('validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));
            $getOrder      = admFuncVariableIsValid($_GET, 'order', 'array');

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $itemFields = new ItemField($gDb);
            $itemFields->readDataById($getinfId);
            if (!empty($getOrder)) {
                // set new order (drag and drop)
                $itemFields->setSequence(explode(',', $getOrder));
            } else {
                $itemFields->moveSequence($postDirection);
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

