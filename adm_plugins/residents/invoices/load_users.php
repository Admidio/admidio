<?php
require_once __DIR__ . '/../common_function.php';
require_once __DIR__ . '/../../../system/login_valid.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
    if (!isUserAuthorizedForResidents($scriptUrl)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $groupID = (int) admFuncVariableIsValid($_REQUEST, 'group_id', 'int');

    $users = residentsGetOwnerOptions($groupID);

    echo json_encode($users, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
    'error' => 'Internal error',
    'details' => $exception->getMessage()
    ]);
}
