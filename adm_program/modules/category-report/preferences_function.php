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
use Admidio\Exception;

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
            $categoryReportConfigForm = $gCurrentSession->getFormObject($_POST['admidio-csrf-token']);
            if ($_POST['admidio-csrf-token'] !== $categoryReportConfigForm->getCsrfToken()) {
                throw new Exception('Invalid or missing CSRF token!');
            }

            for ($conf = 0; isset($_POST['name' . $conf]); $conf++) {
                $values['id'] = $_POST['id' . $conf];
                $values['name'] = $_POST['name' . $conf];
                $values['selection_role'] = isset($_POST['selection_role' . $conf]) ? trim(implode(',', $_POST['selection_role' . $conf]), ',') : '';
                $values['selection_cat'] = isset($_POST['selection_cat' . $conf]) ? trim(implode(',', $_POST['selection_cat' . $conf]), ',') : '';
                $values['number_col'] = isset($_POST['number_col' . $conf]) ? 1 : 0;
                $values['default_conf'] = (bool)$_POST['default_conf' . $conf];

                $allColumnsEmpty = true;

                $fields = '';
                for ($number = 0; isset($_POST['column' . $conf . '_' . $number]); $number++) {
                    if (strlen($_POST['column' . $conf . '_' . $number]) > 0) {
                        $allColumnsEmpty = false;
                        $fields .= $_POST['column' . $conf . '_' . $number] . ',';
                    }
                }

                if ($allColumnsEmpty) {
                    throw new Exception('SYS_FIELD_EMPTY', array('SYS_COLUMN'));
                }

                $values['col_fields'] = substr($fields, 0, -1);
                $config[] = $values;
            }

            $report = new CategoryReport();
            $config = $report->saveConfigArray($config);
            echo json_encode(array('status' => 'success'));
            exit();
            break;

        default:
            throw new Exception('SYS_INVALID_PAGE_VIEW');
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
