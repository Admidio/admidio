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
use Admidio\Inventory\Service\ItemService;
use Admidio\Inventory\Service\ItemFieldService;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\UI\Presenter\InventoryFieldsPresenter;
use Admidio\UI\Presenter\InventoryItemPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'show_html', 'validValues' => array('show_html', 'field_list', 'field_edit', 'field_save', 'field_delete', 'sequence', 'item_edit', 'item_save', 'item_delete_explain_msg', 'item_make_former', 'item_undo_former', 'item_delete', 'print_preview', 'print_xlsx', 'print_ods', 'print_csv-ms', 'print_csv-oo', 'print_pdf', 'print_pdfl')));
    $getinfId = admFuncVariableIsValid($_GET, 'uuid', 'int', array('defaultValue' => 0));
    $getiniId = admFuncVariableIsValid($_GET, 'item_id', 'int', array('defaultValue' => 0));
    $postCopyNumber = admFuncVariableIsValid($_POST, 'item_copy_number', 'numeric', array('defaultValue' => 1));
    $postCopyField = admFuncVariableIsValid($_POST, 'item_copy_field', 'int', array('defaultValue' => 0));
    $postRedirect = admFuncVariableIsValid($_POST, 'redirect', 'numeric', array('defaultValue' => 1));
    $postImported = admFuncVariableIsValid($_POST, 'imported', 'numeric', array('defaultValue' => 0));
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool', array('defaultValue' => false));
    $getRedirectToImport = admFuncVariableIsValid($_GET, 'redirect_to_import', 'bool', array('defaultValue' => false));
    

    // only authorized users can edit the item fields
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    switch ($getMode) {
        case 'show_html':    
            $page = new InventoryPresenter('admidio-inventory');
            $page->createHTMLPage();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-box-seam-fill');
            $page->show();
            break;
#region fields
        case 'field_list':
            $headline = $gL10n->get('SYS_INVENTORY_ITEMFIELDS');
            $gNavigation->addUrl(CURRENT_URL, $headline);
            $itemFields = new InventoryFieldsPresenter('adm_inventory_item_fields', $headline);
            $itemFields->createList();
            $itemFields->show();
            break;

        case 'field_edit':
            // set headline of the script
            if ($getinfId !== 0) {
                $headline = $gL10n->get('SYS_INVENTORY_ITEMFIELD_EDIT');
            } else {
                $headline = $gL10n->get('SYS_INVENTORY_ITEMFIELD_CREATE');
            }

            $gNavigation->addUrl(CURRENT_URL, $headline);
            $itemFields = new InventoryFieldsPresenter('adm_item_fields_edit');
            $itemFields->createEditForm($getinfId);
            $itemFields->show();
            break;

        case 'field_save':
            $itemFieldsModule = new ItemFieldService($gDb, $getinfId);
            $itemFieldsModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'field_delete':
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
#endregion
#region items
        case 'item_edit':
            // set headline of the script
            if ($getiniId !== 0 && $getCopy) {
                $headline = $gL10n->get('SYS_INVENTORY_ITEM_COPY');
            }
            elseif ($getiniId !== 0) {
                $headline = $gL10n->get('SYS_INVENTORY_ITEM_EDIT');
            }
            else {
                $headline = $gL10n->get('SYS_INVENTORY_ITEM_CREATE');
            }

            $gNavigation->addUrl(CURRENT_URL, $headline);
            $item = new InventoryItemPresenter('adm_item_edit');
            $item->createEditForm($getiniId, $getCopy);
            $item->show();
            break;

        case 'item_save':
            if ($getiniId !== 0 && $getCopy) {
                $getiniId = 0;
            }
            $itemModule = new ItemService($gDb, $getiniId);
            $itemModule->save();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'item_delete_explain_msg':
            echo '
                <div class="modal-header">
                    <h3 class="modal-title">' . $gL10n->get('SYS_INVENTORY_ITEM_DELETE') . '</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><i class="bi bi-person-fill-dash"></i>&nbsp;' . $gL10n->get('SYS_INVENTORY_FORMER_DESC', array($gL10n->get('SYS_INVENTORY_FORMER'))) . '</p>
                    <p><i class="bi bi-trash"></i>&nbsp;' . $gL10n->get('SYS_INVENTORY_ITEM_DELETE_DESC', array($gL10n->get('SYS_DELETE'))) . '</p>
                </div>
                <div class="modal-footer">
                    <button id="adm_button_former" type="button" class="btn btn-primary mr-4" onclick="callUrlHideElement(\'adm_item_' . $getiniId . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_make_former', 'item_id' => $getiniId)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-person-fill-dash"></i>' . $gL10n->get('SYS_INVENTORY_FORMER') . '</button>
                    <button id="adm_button_delete" type="button" class="btn btn-primary" onclick="callUrlHideElement(\'adm_item_' . $getiniId . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_delete', 'item_id' => $getiniId)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-trash"></i>' . $gL10n->get('SYS_DELETE') . '</button>
                    <div id="adm_status_message" class="mt-4 w-100"></div>
                </div>';
            break;

        case 'item_make_former':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $itemModule = new ItemService($gDb, $getiniId);
            $itemModule->makeItemFormer();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_INVENTORY_ITEM_MADE_FORMER')));
            break;

        case 'item_undo_former':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $itemModule = new ItemService($gDb, $getiniId);
            $itemModule->undoItemFormer();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_INVENTORY_ITEM_UNDO_FORMER')));
            break;
    
        case 'item_delete':
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

            $itemModule = new ItemService($gDb, $getiniId);
            $itemModule->delete();
            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_DELETE_DATA')));
            break;
#endregion

        case 'print_preview':
            $page = new InventoryPresenter('adm-inventory-print-preview');
            $page->setPrintMode();
            $page->createPrintPreview();
            $page->show();
            break;

        case 'print_xlsx':
        case 'print_ods':
        case 'print_csv-ms':
        case 'print_csv-oo':
        case 'print_pdf':
        case 'print_pdfl':
            $page = new InventoryPresenter('adm-inventory-print');
            $exportMode = str_replace('print_', '', $getMode);
            $page->createExport($exportMode);
            break;

        default:
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('field_save', 'field_delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}

