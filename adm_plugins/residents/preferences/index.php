<?php
/**
 ***********************************************************************************************
 * Preferences tab: select roles that act as Residents admins
 *
 * Expects $page, $isAdmin, $config to be available from residents.php
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Image;

global $gDb, $gCurrentOrganization, $gL10n, $gCurrentUser;

if (!$isAdmin) {
    $page->addHtml('<div class="alert alert-warning">'.$gL10n->get('RE_ONLY_ADMIN').'</div>');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSel = isset($_POST['admin_roles']) ? array_map('intval', (array)$_POST['admin_roles']) : array();
    $config['access']['admin_roles'] = $newSel;

    // Payment Admin Roles
    $newPaymentSel = isset($_POST['payment_admin_roles']) ? array_map('intval', (array)$_POST['payment_admin_roles']) : array();
    $config['access']['payment_admin_roles'] = $newPaymentSel;

    // Default due date days
    $dueDays = isset($_POST['due_days']) ? (int)$_POST['due_days'] : 15;
    if ($dueDays <= 0) {
        $dueDays = 15;
    }
    $config['defaults']['due_days'] = $dueDays;

    $defaultNoteSetting = isset($_POST['default_note']) ? trim((string)$_POST['default_note']) : '';
    $config['defaults']['invoice_note'] = $defaultNoteSetting;

    // Payment Gateway Configuration
    $config['payment_gateway']['name'] = isset($_POST['pg_name']) ? trim((string)$_POST['pg_name']) : '';
    $config['payment_gateway']['currency'] = isset($_POST['pg_currency']) ? trim((string)$_POST['pg_currency']) : '';
    $config['payment_gateway']['merchant_id'] = isset($_POST['pg_merchant_id']) ? trim((string)$_POST['pg_merchant_id']) : '';
    $config['payment_gateway']['working_key'] = isset($_POST['pg_working_key']) ? trim((string)$_POST['pg_working_key']) : '';
    $config['payment_gateway']['access_code'] = isset($_POST['pg_access_code']) ? trim((string)$_POST['pg_access_code']) : '';
    $config['payment_gateway']['redirect_url'] = isset($_POST['pg_redirect_url']) ? trim((string)$_POST['pg_redirect_url']) : '';
    $config['payment_gateway']['cancel_url'] = isset($_POST['pg_cancel_url']) ? trim((string)$_POST['pg_cancel_url']) : '';
    $config['payment_gateway']['gateway_url'] = isset($_POST['pg_gateway_url']) ? trim((string)$_POST['pg_gateway_url']) : '';
    $config['payment_gateway']['timeout'] = isset($_POST['pg_timeout']) ? (int)$_POST['pg_timeout'] : 15;

    // Organization logo upload (used in invoice PDFs)
    $orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
    if ($orgId > 0) {
        $logoDir = ADMIDIO_PATH . FOLDER_DATA . '/residents';
        $logoFile = $logoDir . '/org_logo_' . $orgId . '.png';

        if (!empty($_POST['org_logo_remove'])) {
            try {
                if (class_exists('FileSystemUtils')) {
                    FileSystemUtils::deleteFileIfExists($logoFile);
                } elseif (file_exists($logoFile)) {
                    @unlink($logoFile);
                }
            } catch (Exception $e) {
                // ignore delete errors to keep preferences usable
            }
        } elseif (isset($_FILES['userfile']) && isset($_FILES['userfile']['tmp_name'][0])) {
            $uploadError = (int)($_FILES['userfile']['error'][0] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                // No file was uploaded, skip processing
            } elseif ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $gMessage->show($gL10n->get('SYS_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2))));
            } elseif ($uploadError !== UPLOAD_ERR_OK) {
                // Other upload errors (partial upload, no tmp dir, write error, extension blocked)
                $gMessage->show($gL10n->get('SYS_NO_PICTURE_SELECTED'));
            } elseif (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
                $gMessage->show($gL10n->get('SYS_NO_PICTURE_SELECTED'));
            } else {
                $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
                if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
                    $gMessage->show($gL10n->get('SYS_PHOTO_FORMAT_INVALID'));
                }

                try {
                    if (class_exists('FileSystemUtils')) {
                        FileSystemUtils::createDirectoryIfNotExists($logoDir);
                    } elseif (!is_dir($logoDir)) {
                        @mkdir($logoDir, 0775, true);
                    }

                    $logoImage = new Image($_FILES['userfile']['tmp_name'][0]);
                    $logoImage->setImageType('png');
                    // Keep aspect ratio inside a reasonable box for PDFs
                    $logoImage->scale(600, 200);
                    $logoImage->copyToFile(null, $logoFile);
                    $logoImage->delete();
                } catch (Exception $e) {
                    $gMessage->show($gL10n->get('SYS_DATABASE_ERROR') . ': ' . htmlspecialchars($e->getMessage()));
                }
            }
        }
    }

    residentsWriteConfig($config);
    // Decide where to redirect based on updated permissions
    $stillCanSeePreferences = isResidentsAdmin();
    $redirectTab = $stillCanSeePreferences ? 'preferences' : 'invoices';
    // Redirect after POST (PRG) to refresh permissions/tabs with appropriate target tab
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => $redirectTab, 'pref_status' => 'saved')));
    return;
}

$roles = residentsGetRoleOptions();
$selected = $config['access']['admin_roles'] ?? array();
$selectedPayment = $config['access']['payment_admin_roles'] ?? array();

$orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
$orgLogoUrl = '';
if ($orgId > 0) {
    $orgLogoPath = ADMIDIO_PATH . FOLDER_DATA . '/residents/org_logo_' . $orgId . '.png';
    if (file_exists($orgLogoPath)) {
        // Use PHP endpoint to serve logo (direct file access is blocked by .htaccess)
        $orgLogoUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/preferences/get_logo.php?v=' . rawurlencode((string)filemtime($orgLogoPath));
    }
}

$form = new HtmlForm('re_preferences', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'preferences')), $page, array('enableFileUpload' => true));
$form->addSelectBox('admin_roles', $gL10n->get('RE_PREF_ADMIN_ROLES'), $roles, array('defaultValue' => $selected, 'multiselect' => true));
$form->addSelectBox('payment_admin_roles', $gL10n->get('RE_PREF_PAYMENT_ADMIN'), $roles, array('defaultValue' => $selectedPayment, 'multiselect' => true));

// Organization Logo (used in invoice PDFs)
// Render as a single grouped box (preview + upload + remove button) to avoid duplicate labels.
$logoBoxHtml = '<div class="border rounded bg-light p-3">'
    . '<div class="d-flex flex-wrap align-items-start" style="gap: 16px;">';

if ($orgLogoUrl !== '') {
    $logoBoxHtml .= '<div class="border rounded bg-white p-2" style="max-width:260px;">'
    . '<img class="img-fluid" src="' . htmlspecialchars($orgLogoUrl) . '" alt="' . htmlspecialchars($gL10n->get('RE_ORG_LOGO')) . '" style="max-height:70px; width:auto; display:block;" />'
    . '</div>';
}

$logoBoxHtml .= '<div style="min-width:260px;">'
    . '<input type="file" class="form-control" name="userfile[]" accept="image/png,image/jpeg" />'
    . '<div class="form-text">' . htmlspecialchars($gL10n->get('RE_ORG_LOGO_HELP')) . '</div>';

if ($orgLogoUrl !== '') {
    $logoBoxHtml .= '<button type="submit" name="org_logo_remove" value="1" class="btn btn-sm btn-danger mt-2">'
    . '<i class="bi bi-trash"></i> ' . htmlspecialchars($gL10n->get('RE_REMOVE_ORG_LOGO'))
    . '</button>';
}

$logoBoxHtml .= '</div>'
    . '</div>'
    . '</div>';

$form->addCustomContent($gL10n->get('RE_ORG_LOGO'), $logoBoxHtml);

// choose single group whose members fill owner dropdown
// Default due date configuration
$form->addInput('due_days', $gL10n->get('RE_PREF_DUE_DAYS'), (string)((int)($config['defaults']['due_days'] ?? 15)), array('maxLength' => 3));
$form->addMultilineTextInput('default_note', $gL10n->get('RE_PREF_DEFAULT_NOTE'), (string)($config['defaults']['invoice_note'] ?? ''), 3);

// Payment Gateway Configuration Section
$pgConf = $config['payment_gateway'];

// Modal for Payment Gateway Configuration
// Placed inside the form so inputs are submitted automatically
$modalHtml = '
<div class="modal fade" id="pgModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                    <div id="pg_modal_error" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div class="row gx-3" style="--bs-gutter-y: 2rem;">
            <div class="col-12">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_NAME').' <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="pg_name" id="pg_name" value="'.htmlspecialchars((string)$pgConf['name']).'" placeholder="e.g. CCAvenue">
            </div>
            
            <div class="col-md-6">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_MERCHANT_ID').' <span class="text-danger">*</span></label>
        <input type="text" class="form-control font-monospace" name="pg_merchant_id" id="pg_merchant_id" value="'.htmlspecialchars((string)$pgConf['merchant_id']).'">
            </div>
            <div class="col-md-6">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_ACCESS_CODE').' <span class="text-danger">*</span></label>
        <input type="text" class="form-control font-monospace" name="pg_access_code" id="pg_access_code" value="'.htmlspecialchars((string)$pgConf['access_code']).'">
            </div>
            
            <div class="col-12">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_WORKING_KEY').' <span class="text-danger">*</span></label>
        <input type="text" class="form-control font-monospace" name="pg_working_key" id="pg_working_key" value="'.htmlspecialchars((string)$pgConf['working_key']).'">
            </div>
            
            <div class="col-12">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_GATEWAY_URL').' <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="pg_gateway_url" id="pg_gateway_url" value="'.htmlspecialchars((string)$pgConf['gateway_url']).'">
            </div>
            
            <div class="col-12">
        <label class="form-label fw-bold">'.$gL10n->get('RE_PG_TIMEOUT').'</label>
        <input type="number" class="form-control" name="pg_timeout" id="pg_timeout" min="1" value="'.htmlspecialchars((string)($pgConf['timeout'] ?? 15)).'">
            </div>
                    </div>
            </div>
            <div class="modal-footer bg-light">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">'.$gL10n->get('RE_CANCEL').'</button>
    <button type="button" class="btn btn-primary px-4" id="pg_btn_modal_save"><i class="bi bi-check-lg me-1"></i> '.$gL10n->get('RE_OK').'</button>
            </div>
    </div>
    </div>
</div>';

// Modern UI for Single Gateway
$gatewayName = trim((string)$pgConf['name']);
$hasGateway = $gatewayName !== '';

$uiHtml = '
<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">'.$gL10n->get('RE_PAYMENT_GATEWAY_LABEL').'</label>
    <div class="col-sm-9">
    <!-- Configured Gateway Card -->
    <div id="pg_card" class="card shadow-sm border-0 bg-light" style="'.($hasGateway ? '' : 'display:none;').'">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center">
                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm text-primary">
            <i class="bi bi-credit-card fs-4"></i>
                    </div>
                    <div>
            <h6 class="mb-0 fw-bold" id="pg_card_name">'.htmlspecialchars($gatewayName).'</h6>
                    </div>
        </div>
        <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-light text-primary" id="pg_btn_edit" title="'.$gL10n->get('RE_EDIT').'"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-light text-danger" id="pg_btn_delete" title="'.$gL10n->get('RE_DELETE').'"><i class="bi bi-trash"></i></button>
        </div>
            </div>
    </div>

    <!-- Add Button (Empty State) -->
    <div id="pg_add_container" class="text-start" style="'.($hasGateway ? 'display:none;' : '').'">
            <button type="button" id="pg_btn_add" class="btn btn-outline-primary border-dashed w-100 p-3 text-center">
        <i class="bi bi-plus-circle fs-2 mb-2 d-block"></i>
        <span class="fw-bold">'.$gL10n->get('RE_PG_ADD_BTN').'</span>
            </button>
    </div>
    <small class="form-text text-muted mt-2 d-block"><i class="bi bi-info-circle me-1"></i>' . htmlspecialchars($gL10n->get('RE_GATEWAY_LIMITATION')) . '</small>
    </div>
</div>
<style>
#pg_btn_add:hover {
    background-color: #4a9a9a !important;
    border-color: #4a9a9a !important;
    color: #fff !important;
}
</style>';
$form->addHtml($uiHtml);
$form->addHtml($modalHtml);

$form->addSubmitButton('btnSave', $gL10n->get('RE_SAVE'));
$page->addHtml($form->show(false));

// Modal for Delete Confirmation
$page->addHtml('
<div class="modal fade" id="pgDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
    <div class="mb-3 text-danger"><i class="bi bi-exclamation-circle fs-1"></i></div>
    <h5 class="fw-bold mb-2">'.$gL10n->get('RE_PG_DELETE_TITLE').'</h5>
    <p class="text-muted mb-4">'.$gL10n->get('RE_PG_DELETE_CONFIRM').'</p>
    <div class="d-grid gap-2">
            <button type="button" class="btn btn-danger" id="pg_btn_modal_delete_confirm">'.$gL10n->get('RE_DELETE').'</button>
            <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal" data-dismiss="modal">'.$gL10n->get('RE_CANCEL').'</button>
    </div>
            </div>
    </div>
    </div>
</div>

<script>
$(function(){
    var pgModal = new bootstrap.Modal(document.getElementById("pgModal"));
    var pgDeleteModal = new bootstrap.Modal(document.getElementById("pgDeleteModal"));

    function clearData() {
        $("#pg_name").val("");
        $("#pg_currency").val("");
        $("#pg_merchant_id").val("");
        $("#pg_working_key").val("");
        $("#pg_access_code").val("");
        $("#pg_redirect_url").val("");
        $("#pg_cancel_url").val("");
        $("#pg_gateway_url").val("");
        $("#pg_timeout").val("15");
    
        // Remove error classes
        $(".is-invalid").removeClass("is-invalid");
        $("#pg_modal_error").addClass("d-none").text("");

        // Update UI
        $("#pg_card").hide();
        $("#pg_add_container").show();
    }

    // Real-time validation removal
    var requiredMsgIds = ["pg_name", "pg_merchant_id", "pg_access_code", "pg_working_key", "pg_gateway_url"];
    requiredMsgIds.forEach(function(id){
        $("#" + id).on("input", function(){
            if($(this).val().trim() !== "") {
                $(this).removeClass("is-invalid");
            }
            // Hide global error message on any input
            $("#pg_modal_error").addClass("d-none");
    });
    });

    $("#pg_btn_add").on("click", function(e){
        e.preventDefault();
        // Clear modal fields for add or keep previous?
        // If clicking add, usage is new.
        // But if deleting then adding, we want clear.
        if($("#pg_name").val() === "") {
                $("#pgModalTitle").text("'.$gL10n->get('RE_ADD_PAYMENT').' ".split(" ")[0] + " Gateway");
    } else {
                // If fields are populated but card is hidden (not yet saved), we might want to keep them?
                // Or clear them to be safe.
                clearData();
                $("#pgModalTitle").text("'.$gL10n->get('RE_ADD_PAYMENT').' ".split(" ")[0] + " Gateway");
    }
        pgModal.show();
    });

    $("#pg_btn_edit").on("click", function(e){
        e.preventDefault();
        // Config already in inputs
        // Just show modal
        pgModal.show();
    });

    $("#pg_btn_delete").on("click", function(e){
        e.preventDefault();
        pgDeleteModal.show();
    });

    $("#pg_btn_modal_save").on("click", function(){
        // Hand-rolled validation on OK click
        var requiredIds = ["pg_name", "pg_merchant_id", "pg_access_code", "pg_working_key", "pg_gateway_url"];
        var isValid = true;
    
        // Reset errors
        requiredIds.forEach(function(id){
            $("#" + id).removeClass("is-invalid");
    });
        $("#pg_modal_error").addClass("d-none").text("");

        // Check required fields
        requiredIds.forEach(function(id){
            var el = $("#" + id);
            if(el.val().trim() === "") {
                el.addClass("is-invalid");
                isValid = false;
            }
    });

        if (!isValid) {
            $("#pg_modal_error").text("Please fill in all required Payment Gateway fields.").removeClass("d-none");
            return;
    }

        var name = $("#pg_name").val().trim();
        $("#pg_card_name").text(name);
        $("#pg_card").show();
        $("#pg_add_container").hide();
    
        pgModal.hide();
    });

    $("#pg_btn_modal_delete_confirm").on("click", function(){
        clearData();
        pgDeleteModal.hide();
    });
});
</script>
');

$uninstallUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'uninstall'));
$page->addHtml('<div class="mt-3"><a class="btn btn-danger text-white" href="' . $uninstallUrl . '" onclick="return confirm(\'' . htmlspecialchars($gL10n->get('RE_UNINSTALL_CONFIRM'), ENT_QUOTES, 'UTF-8') . '\');"font-size: i class="bi bi-trash"></i> ' . $gL10n->get('RE_UNINSTALL_RESIDENTS') . '</a></div>');
