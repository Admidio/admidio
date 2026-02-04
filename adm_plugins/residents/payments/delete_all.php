<?php
/**
 ***********************************************************************************************
 * Bulk delete payments (admins only)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gCurrentUser;

if (!isPaymentAdmin()) {
    http_response_code(403);
    echo 'NO_RIGHTS';
    exit;
}

try {
    SecurityUtils::validateCsrfToken((string)($_POST['admidio-csrf-token'] ?? ''));
} catch (Throwable $e) {
    http_response_code(403);
    echo 'CSRF';
    exit;
}

$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : array();
$ids = array_values(array_unique(array_map('intval', $ids)));

if (empty($ids)) {
    echo 'EMPTY';
    exit;
}

try {
    $actingUserId = (int)$gCurrentUser->getValue('usr_id');
    foreach ($ids as $id) {
        $pay = new TableResidentsPayment($gDb, (int)$id);
        if (!$pay->isNewRecord() && $pay->getValue('rpa_pay_type') !== 'Online') {
            $pay->deleteWithRelations($actingUserId);
    }
    }
    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR';
}
