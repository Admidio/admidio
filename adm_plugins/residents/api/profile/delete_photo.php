<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$currentUser = validateApiKey();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

$userUuid = $currentUser->getValue('usr_uuid');
if (!$userUuid) {
    http_response_code(403);
    echo json_encode(array('error' => 'Invalid user'));
    exit;
}

try {
    if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
        $filePath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $currentUser->getValue('usr_id') . '.jpg';
        try {
            FileSystemUtils::deleteFileIfExists($filePath);
    } catch (\RuntimeException $exception) {
            http_response_code(403);
            echo json_encode(array('error' => 'Could not delete profile'));
            exit;
    }
    }
    else {
            $currentUser->setValue('usr_photo', '');
            $currentUser->save();
    }

    echo json_encode(array('isDelete' => true));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
