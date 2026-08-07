<?php
/**
 ***********************************************************************************************
 * Import members to a role from CSV/JSON file
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * role_uuid  : UUID of role to which members should be imported
 * mode       : select    : Show file selection form (default)
 *              preview   : Show import preview
 *              execute   : Execute the import
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    $getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'uuid', array('requireValue' => true, 'directOutput' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'select', 'validValues' => array('select', 'preview', 'execute', 'log')));

    $role = new Role($gDb);
    $role->readDataByUuid($getRoleUuid);

    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new Exception('SYS_SERVER_NO_UPLOAD');
    }

    $headline = $gL10n->get('SYS_IMPORT_MEMBERS') . ' - ' . $role->getValue('rol_name');

    $gNavigation->addUrl(CURRENT_URL, $headline);

    $page = PagePresenter::withHtmlIDAndHeadline('admidio-members-import', $headline);

    if ($getMode === 'select') {
        showFileSelectionForm($page, $role, $getRoleUuid);
    } elseif ($getMode === 'log') {
        showImportLog($page, $role, $getRoleUuid);
    }

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

function showFileSelectionForm(PagePresenter $page, Role $role, string $roleUuid): void
{
    global $gL10n, $gCurrentSession, $gCurrentUser, $gDb, $gCurrentOrgId;

    $form = new FormPresenter(
        'adm_members_import_form',
        'modules/groups-roles.members-import.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_import_read_file.php?role_uuid=' . $roleUuid,
        $page,
        array('enableFileUpload' => true)
    );

    $formats = array(
        'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
        'CSV' => $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
        'JSON' => $gL10n->get('SYS_JSON_FORMAT'),
    );
    $form->addSelectBox(
        'format',
        $gL10n->get('SYS_FORMAT'),
        $formats,
        array(
            'showContextDependentFirstEntry' => false,
            'defaultValue' => 'AUTO',
            'property' => FormPresenter::FIELD_REQUIRED
        )
    );

    $page->addJavascript(
        '
        $("#format").change(function() {
            const format = $(this).children("option:selected").val();
            $(".import-setting").prop("disabled", true).parents("div.admidio-form-group").hide();
            $(".import-"+format).prop("disabled", false).parents("div.admidio-form-group").show("slow");
        });
        $("#format").trigger("change");',
        true
    );

    $form->addFileUpload(
        'userfile',
        $gL10n->get('SYS_CHOOSE_FILE'),
        array(
            'property' => FormPresenter::FIELD_REQUIRED,
            'allowedMimeTypes' => array(
                'text/comma-separated-values',
                'text/csv',
                'application/csv',
                'text/json',
                'application/json'
            ),
            'helpTextId' => 'SYS_IMPORT_MEMBERS_FILE_DESC'
        )
    );

    $selectBoxEntries = array(
        '' => $gL10n->get('SYS_AUTO_DETECT'),
        'UTF-8' => $gL10n->get('SYS_UTF8'),
        'CP1252' => $gL10n->get('SYS_CP1252'),
        'ISO-8859-1' => $gL10n->get('SYS_ISO_8859_1')
    );
    $form->addSelectBox(
        'import_coding',
        $gL10n->get('SYS_CODING'),
        $selectBoxEntries,
        array(
            'showContextDependentFirstEntry' => true,
            'class' => 'import-setting import-CSV import-AUTO'
        )
    );

    $selectBoxEntries = array(
        '' => $gL10n->get('SYS_AUTO_DETECT'),
        ',' => $gL10n->get('SYS_COMMA'),
        ';' => $gL10n->get('SYS_SEMICOLON'),
        '\t' => $gL10n->get('SYS_TAB'),
        '|' => $gL10n->get('SYS_PIPE')
    );
    $form->addSelectBox(
        'import_separator',
        $gL10n->get('SYS_SEPARATOR_FOR_CSV_FILE'),
        $selectBoxEntries,
        array(
            'showContextDependentFirstEntry' => true,
            'class' => 'import-setting import-CSV import-AUTO'
        )
    );

    $identifyMethods = array(
        'email' => $gL10n->get('SYS_EMAIL'),
        'uuid' => $gL10n->get('SYS_USER_UUID'),
        'login' => $gL10n->get('SYS_LOGIN_NAME'),
        'name' => $gL10n->get('SYS_FIRSTNAME_LASTNAME'),
    );
    $form->addSelectBox(
        'identify_method',
        $gL10n->get('SYS_IDENTIFY_USER_BY'),
        $identifyMethods,
        array(
            'showContextDependentFirstEntry' => false,
            'defaultValue' => 'email',
            'helpTextId' => 'SYS_IDENTIFY_USER_BY_DESC'
        )
    );

    $form->addCheckbox(
        'first_row_header',
        $gL10n->get('SYS_FIRST_ROW_CONTAINS_HEADERS'),
        true,
        array('helpTextId' => 'SYS_FIRST_ROW_CONTAINS_HEADERS_DESC')
    );

    $form->addSubmitButton(
        'btn_preview',
        $gL10n->get('SYS_PREVIEW_IMPORT'),
        array('icon' => 'bi-eye-fill')
    );

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
}

function showImportLog(PagePresenter $page, Role $role, string $roleUuid): void
{
    global $gL10n, $gNavigation;

    if (!isset($_SESSION['members_import_log'])) {
        throw new Exception('SYS_NO_IMPORT_LOG');
    }

    $log = $_SESSION['members_import_log'];

    $html = '<div class="card">';
    $html .= '<div class="card-header">' . $gL10n->get('SYS_IMPORT_RESULT') . '</div>';
    $html .= '<div class="card-body">';

    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-3">';
    $html .= '<div class="alert alert-success text-center">';
    $html .= '<h3>' . $log['count_success'] . '</h3>';
    $html .= '<small>' . $gL10n->get('SYS_SUCCESSFUL') . '</small>';
    $html .= '</div></div>';

    if ($log['count_errors'] > 0) {
        $html .= '<div class="col-md-3">';
        $html .= '<div class="alert alert-danger text-center">';
        $html .= '<h3>' . $log['count_errors'] . '</h3>';
        $html .= '<small>' . $gL10n->get('SYS_ERRORS') . '</small>';
        $html .= '</div></div>';
    }
    $html .= '</div>';

    if (!empty($log['log'])) {
        $html .= '<h5>' . $gL10n->get('SYS_LOG') . '</h5>';
        $html .= '<table class="table table-sm table-striped">';
        $html .= '<thead><tr><th>' . $gL10n->get('SYS_ROW') . '</th><th>' . $gL10n->get('SYS_USER') . '</th><th>' . $gL10n->get('SYS_STATUS') . '</th><th>' . $gL10n->get('SYS_MESSAGE') . '</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($log['log'] as $entry) {
            $statusClass = $entry['status'] === 'success' ? 'text-success' : 'text-danger';
            $statusIcon = $entry['status'] === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            $html .= '<tr>';
            $html .= '<td>' . ($entry['row_index'] + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($entry['user_name'] ?: '-') . '</td>';
            $html .= '<td class="' . $statusClass . '"><i class="bi ' . $statusIcon . '"></i> ' . ucfirst($entry['status']) . '</td>';
            $html .= '<td>' . htmlspecialchars($entry['message']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '<div class="mt-4">';
    $html .= '<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php?role_uuid=' . $roleUuid . '" class="btn btn-secondary">';
    $html .= '<i class="bi bi-arrow-left-circle-fill"></i> ' . $gL10n->get('SYS_BACK_TO_MEMBER_ASSIGNMENT');
    $html .= '</a>';
    $html .= '</div>';

    $html .= '</div></div>';

    $page->addHtml($html);

    unset($_SESSION['members_import_log']);
}
