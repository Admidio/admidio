<?php
/**
    * Bulk delete charges (admin-only)
    */
use Admidio\Exception; // if available

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

try {
    if (!isResidentsAdminBySettings()) {
        http_response_code(403);
        echo 'FORBIDDEN';
        exit;
    }

    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $deleted = 0;
    $failed = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
    }
        // Delete charge and possible relations
        $charge = new TableResidentsCharge($gDb);
        if ($charge->readDataById($id)) {
            if ($charge->delete()) {
                $deleted++;
            } else {
                $failed++;
            }
    }
    }

    if ($failed > 0) {
        http_response_code(500);
        echo 'ERROR';
        exit;
    }

    echo 'OK';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR';
}
