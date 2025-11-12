<?php
/**
 ***********************************************************************************************
 * Preferences functions for the admidio module CategoryReport
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * form     - The name of the form preferences that were submitted.
 *
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // only authorized user are allowed to start this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Initialize and check the parameters
    $getForm = admFuncVariableIsValid($_GET, 'form', 'string');

    switch ($getForm) {
        case 'configurations':
            // check the CSRF token of the form against the session token
            $categoryReportConfigForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            if ($_POST['adm_csrf_token'] !== $categoryReportConfigForm->getCsrfToken()) {
                throw new Exception('Invalid or missing CSRF token!');
            }

            for ($conf = 0; isset($_POST['name' . $conf]); $conf++) {
                $values['id'] = $_POST['id' . $conf];
                $values['name'] = $_POST['name' . $conf];
                $values['selection_role'] = isset($_POST['selection_role' . $conf]) ? trim(implode(',', $_POST['selection_role' . $conf]), ',') : '';
                $values['selection_cat'] = isset($_POST['selection_cat' . $conf]) ? trim(implode(',', $_POST['selection_cat' . $conf]), ',') : '';
                $values['number_col'] = isset($_POST['number_col' . $conf]) ? 1 : 0;
                $values['default_conf'] = (bool)$_POST['default_conf' . $conf];

                if (empty($_POST['columns' . $conf])) {
                    throw new Exception('SYS_FIELD_EMPTY', array('SYS_COLUMN'));
                }

                $values['col_fields'] = implode(',', $_POST['columns' . $conf]);
                $config[] = $values;
            }

            $report = new CategoryReport();
            $config = $report->saveConfigArray($config);
            echo json_encode(array('status' => 'success'));
            break;

        default:
            throw new Exception('SYS_INVALID_PAGE_VIEW');
    }
} catch (Throwable $e) {
    handleException($e, true);
}
