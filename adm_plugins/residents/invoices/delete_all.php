<?php
/**
    * Bulk delete invoices (admins only).
    */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gMessage;

if (!isResidentsAdminBySettings()) {
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
    foreach ($ids as $id) {
        $inv = new TableResidentsInvoice($gDb, (int)$id);
        if ($inv->isNewRecord()) {
            continue;
    }
        $isPaid = (int)$inv->getValue('riv_is_paid') === 1;
        if ($isPaid) {
            http_response_code(409);
            echo 'PAID';
            exit;
    }
    }
    foreach ($ids as $id) {
        $inv = new TableResidentsInvoice($gDb, (int)$id);
        if (!$inv->isNewRecord()) {
            $inv->deleteWithRelations();
    }
    }
    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR';
}
