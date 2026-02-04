<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Infrastructure\Image;

$currentUser = validateApiKey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

$userId = (int)$currentUser->getValue('usr_id');
if ($userId <= 0) {
    http_response_code(403);
    echo json_encode(array('error' => 'Invalid user'));
    exit;
}

$uploaded = null;

// Accept either "photo" or Admidio's usual "userfile" input name.
if (isset($_FILES['photo'])) {
    $uploaded = $_FILES['photo'];
} elseif (isset($_FILES['userfile'])) {
    // HtmlForm uses userfile[]; accept first entry.
    if (is_array($_FILES['userfile']['tmp_name'] ?? null)) {
        $uploaded = array(
            'name' => $_FILES['userfile']['name'][0] ?? '',
            'type' => $_FILES['userfile']['type'][0] ?? '',
            'tmp_name' => $_FILES['userfile']['tmp_name'][0] ?? '',
            'error' => $_FILES['userfile']['error'][0] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['userfile']['size'][0] ?? 0
        );
    } else {
        $uploaded = $_FILES['userfile'];
    }
}

if ($uploaded === null) {
    http_response_code(400);
    echo json_encode(array('error' => 'No file uploaded'));
    exit;
}

$err = (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(array('error' => 'No file uploaded'));
    exit;
}
if ($err === UPLOAD_ERR_INI_SIZE) {
    http_response_code(413);
    echo json_encode(array('error' => 'Uploaded file is too large'));
    exit;
}
if ($err !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(array('error' => 'Upload failed'));
    exit;
}

$tmpName = (string)($uploaded['tmp_name'] ?? '');
if ($tmpName === '' || !file_exists($tmpName) || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid upload'));
    exit;
}

$imageProperties = @getimagesize($tmpName);
if ($imageProperties === false || !isset($imageProperties['mime'])) {
    http_response_code(415);
    echo json_encode(array('error' => 'Invalid image'));
    exit;
}

$mime = (string)$imageProperties['mime'];
if (!in_array($mime, array('image/jpeg', 'image/png'), true)) {
    http_response_code(415);
    echo json_encode(array('error' => 'Unsupported image type'));
    exit;
}

try {
    // Align with Admidio profile photo behavior: save as JPG.
    $userImage = new Image($tmpName);
    $userImage->setImageType('jpeg');
    $userImage->scale(130, 170);

    $storage = (int)$gSettingsManager->get('profile_photo_storage');
    if ($storage === 1) {
        $dir = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos';
        if (class_exists('FileSystemUtils')) {
            FileSystemUtils::createDirectoryIfNotExists($dir);
    } elseif (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
    }

        $filePath = $dir . '/' . $userId . '.jpg';
        $userImage->copyToFile(null, $filePath);
    } else {
        // Store binary in DB column usr_photo.
        $userImage->copyToFile(null, $tmpName);
        $data = file_get_contents($tmpName);
        if ($data === false) {
            throw new RuntimeException('Could not read image data');
    }

        $currentUser->setValue('usr_photo', $data);
        $currentUser->save();
        if (isset($gCurrentSession) && method_exists($gCurrentSession, 'reload')) {
            $gCurrentSession->reload($userId);
    }
    }

    $userImage->delete();

    // Return updated profile + photo payload (same shape as profile.php)
    $photoFile = null;
    if ($storage === 0) {
        $photoFile = $currentUser->getValue('usr_photo');
    } else {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $userId . '.jpg';
        if (is_file($file)) {
            $photoFile = file_get_contents($file);
    }
    }

    $photo = null;
    if (!empty($photoFile)) {
        $base64 = base64_encode($photoFile);
        $photo = array(
            'mime' => 'image/jpeg',
            'base64' => $base64,
            'data_uri' => 'data:image/jpeg;base64,' . $base64
        );
    }

    echo json_encode(array(
    'user' => array(
            'id' => $userId,
            'login' => (string)$currentUser->getValue('usr_login_name'),
            'first_name' => (string)$currentUser->getValue('FIRST_NAME'),
            'last_name' => (string)$currentUser->getValue('LAST_NAME')
    ),
    'photo' => $photo
    ));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
