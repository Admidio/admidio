<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$currentUser = validateApiKey();

$userId = (int) $currentUser->getValue('usr_id');
$login = (string) $currentUser->getValue('usr_login_name');
$firstName = (string) $currentUser->getValue('FIRST_NAME');
$lastName = (string) $currentUser->getValue('LAST_NAME');
// Get organization's configured language from settings (not session)
$appLanguage = $gSettingsManager->getString('system_language') ?: 'en';

$photoData = null;

$storage = (int) $gSettingsManager->get('profile_photo_storage');
if ($storage === 0) {
    $usrPhoto = $currentUser->getValue('usr_photo');
    if (!empty($usrPhoto)) {
        $photoData = (string) $usrPhoto;
    }
} else {
    $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $userId . '.jpg';
    if (is_file($file)) {
        $photoData = file_get_contents($file);
    }
}

$photo = null;
if (!empty($photoData)) {
    $mime = 'image/jpeg';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_buffer($finfo, $photoData);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($finfo);
    }
    }

    $base64 = base64_encode($photoData);
    $photo = [
    'mime' => $mime,
    'base64' => $base64,
    'data_uri' => 'data:' . $mime . ';base64,' . $base64,
    ];
}

echo json_encode([
    'user' => [
    'id' => $userId,
    'login' => $login,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'language' => $appLanguage ?: 'en',
    ],
    'photo' => $photo,
]);
