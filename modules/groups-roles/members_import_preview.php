<?php
/**
 ***********************************************************************************************
 * Preview import data for role members
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * role_uuid  : UUID of role to which members should be imported
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Service\RoleMembersImportService;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('requireValue' => true, 'directOutput' => true));

    if (!isset($_SESSION['role_members_import'])) {
        throw new Exception('SYS_NO_IMPORT_DATA');
    }

    $importData = $_SESSION['role_members_import'];

    if ($importData['role_uuid'] !== $getRoleUuid) {
        throw new Exception('SYS_ROLE_MISMATCH');
    }

    $role = new Role($gDb);
    $role->readDataByUuid($getRoleUuid);

    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $headline = $gL10n->get('SYS_PREVIEW_IMPORT') . ' - ' . $role->getValue('rol_name');

    $gNavigation->addUrl(CURRENT_URL, $headline);

    $page = PagePresenter::withHtmlIDAndHeadline('admidio-members-import-preview', $headline);

    $importService = new RoleMembersImportService($gDb, $role);
    $importService->setParsedData($importData['parsed_data'], $importData['headers']);
    $importService->setIdentifyMethod($importData['identify_method']);

    $autoMapping = $importService->autoDetectFieldMapping();
    $importService->setFieldMapping($autoMapping);

    $preview = $importService->getPreview();

    $_SESSION['role_members_import']['preview'] = $preview;

    showPreviewPage($page, $role, $preview, $importData);

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

function showPreviewPage(PagePresenter $page, Role $role, array $preview, array $importData): void
{
    global $gL10n, $gCurrentSession, $gDb;

    $html = '<div class="card mb-4">';
    $html .= '<div class="card-header">' . $gL10n->get('SYS_IMPORT_SUMMARY') . '</div>';
    $html .= '<div class="card-body">';
    $html .= '<div class="row">';

    $html .= '<div class="col-md-2">';
    $html .= '<div class="alert alert-info text-center">';
    $html .= '<h4>' . $preview['total_rows'] . '</h4>';
    $html .= '<small>' . $gL10n->get('SYS_TOTAL_ROWS') . '</small>';
    $html .= '</div></div>';

    $html .= '<div class="col-md-2">';
    $html .= '<div class="alert alert-success text-center">';
    $html .= '<h4>' . $preview['count_success'] . '</h4>';
    $html .= '<small>' . $gL10n->get('SYS_READY_TO_IMPORT') . '</small>';
    $html .= '</div></div>';

    if ($preview['count_already_member'] > 0) {
        $html .= '<div class="col-md-2">';
        $html .= '<div class="alert alert-warning text-center">';
        $html .= '<h4>' . $preview['count_already_member'] . '</h4>';
        $html .= '<small>' . $gL10n->get('SYS_ALREADY_MEMBERS') . '</small>';
        $html .= '</div></div>';
    }

    if ($preview['count_errors'] > 0 || $preview['count_not_found'] > 0) {
        $html .= '<div class="col-md-2">';
        $html .= '<div class="alert alert-danger text-center">';
        $html .= '<h4>' . ($preview['count_errors'] + $preview['count_not_found']) . '</h4>';
        $html .= '<small>' . $gL10n->get('SYS_ERRORS') . '</small>';
        $html .= '</div></div>';
    }

    if ($preview['count_duplicates'] > 0) {
        $html .= '<div class="col-md-2">';
        $html .= '<div class="alert alert-secondary text-center">';
        $html .= '<h4>' . $preview['count_duplicates'] . '</h4>';
        $html .= '<small>' . $gL10n->get('SYS_DUPLICATES_IN_FILE') . '</small>';
        $html .= '</div></div>';
    }

    $html .= '</div></div></div>';

    $html .= '<div class="card mb-4">';
    $html .= '<div class="card-header">' . $gL10n->get('SYS_IMPORT_DETAILS') . '</div>';
    $html .= '<div class="card-body">';

    $html .= '<div class="table-responsive">';
    $html .= '<table id="adm_import_preview_table" class="table table-striped table-hover">';
    $html .= '<thead><tr>';
    $html .= '<th><input type="checkbox" id="select_all" checked /></th>';
    $html .= '<th>' . $gL10n->get('SYS_ROW') . '</th>';
    $html .= '<th>' . $gL10n->get('SYS_STATUS') . '</th>';
    $html .= '<th>' . $gL10n->get('SYS_USER') . '</th>';
    $html .= '<th>' . $gL10n->get('SYS_LEADER') . '</th>';
    $html .= '<th>' . $gL10n->get('SYS_MESSAGE') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($preview['results'] as $result) {
        $rowClass = '';
        $statusIcon = '';
        $statusText = '';

        if ($result['status'] === RoleMembersImportService::RESULT_SUCCESS) {
            $rowClass = 'table-success';
            $statusIcon = 'bi-check-circle-fill text-success';
            $statusText = $gL10n->get('SYS_READY');
        } elseif ($result['status'] === RoleMembersImportService::RESULT_WARNING) {
            $rowClass = 'table-warning';
            $statusIcon = 'bi-exclamation-triangle-fill text-warning';
            $statusText = $gL10n->get('SYS_WARNING');
        } elseif ($result['status'] === RoleMembersImportService::RESULT_ALREADY_MEMBER) {
            $rowClass = 'table-info';
            $statusIcon = 'bi-info-circle-fill text-info';
            $statusText = $gL10n->get('SYS_ALREADY_MEMBER');
        } elseif ($result['status'] === RoleMembersImportService::RESULT_DUPLICATE) {
            $rowClass = 'table-secondary';
            $statusIcon = 'bi-copy text-secondary';
            $statusText = $gL10n->get('SYS_DUPLICATE');
        } elseif ($result['status'] === RoleMembersImportService::RESULT_NOT_FOUND) {
            $rowClass = 'table-danger';
            $statusIcon = 'bi-x-circle-fill text-danger';
            $statusText = $gL10n->get('SYS_NOT_FOUND');
        } else {
            $rowClass = 'table-danger';
            $statusIcon = 'bi-x-circle-fill text-danger';
            $statusText = $gL10n->get('SYS_ERROR');
        }

        $canSelect = $result['can_import'];
        $checked = $canSelect ? 'checked' : '';
        $disabled = $canSelect ? '' : 'disabled';

        $html .= '<tr class="' . $rowClass . '">';
        $html .= '<td><input type="checkbox" name="import_row[]" value="' . $result['row_index'] . '" ' . $checked . ' ' . $disabled . ' class="import-checkbox" /></td>';
        $html .= '<td>' . ($result['row_index'] + 1) . '</td>';
        $html .= '<td><i class="bi ' . $statusIcon . '"></i> ' . $statusText . '</td>';
        $html .= '<td>';
        if ($result['user_name']) {
            $html .= htmlspecialchars($result['user_name']);
        } else {
            $html .= '<span class="text-muted">(' . $gL10n->get('SYS_UNKNOWN') . ')</span>';
        }
        $html .= '</td>';
        $html .= '<td>';
        if ($result['is_leader']) {
            $html .= '<i class="bi bi-star-fill text-warning"></i> ' . $gL10n->get('SYS_YES');
        } else {
            $html .= '<span class="text-muted">' . $gL10n->get('SYS_NO') . '</span>';
        }
        $html .= '</td>';
        $html .= '<td>';
        if (!empty($result['messages'])) {
            $html .= implode('<br />', array_map('htmlspecialchars', $result['messages']));
        }
        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</div>';
    $html .= '</div></div>';

    $form = new FormPresenter(
        'adm_members_import_preview_form',
        'sys-template-parts/form.button-bar.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_import_execute.php?role_uuid=' . $preview['role_uuid'],
        $page,
        array('type' => 'navbar', 'setFocus' => false)
    );

    $form->addSubmitButton(
        'btn_execute',
        $gL10n->get('SYS_EXECUTE_IMPORT'),
        array('icon' => 'bi-arrow-right-circle-fill', 'class' => 'btn-primary')
    );

    $form->addButton(
        'btn_cancel',
        $gL10n->get('SYS_CANCEL'),
        array(
            'icon' => 'bi-x-circle-fill',
            'class' => 'btn-secondary',
            'link' => ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php?role_uuid=' . $preview['role_uuid']
        )
    );

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $javascript = '
        $("#select_all").change(function() {
            $(".import-checkbox:enabled").prop("checked", this.checked);
        });

        $(".import-checkbox").change(function() {
            const allChecked = $(".import-checkbox:enabled").length === $(".import-checkbox:enabled:checked").length;
            $("#select_all").prop("checked", allChecked);
        });
    ';

    $page->addJavascript($javascript, true);
    $page->addHtml($html);
}
