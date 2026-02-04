<?php
/**
 ***********************************************************************************************
 * API endpoint to upload photos to an album
 * 
 * This endpoint mirrors the web upload logic from UploadHandlerPhoto.php
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\FileSystemUtils;

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$currentUser = validateApiKey();

$currentOrgId = isset($gCurrentOrgId)
    ? (int) $gCurrentOrgId
    : (isset($gCurrentOrganization) ? (int) $gCurrentOrganization->getValue('org_id') : 0);

// Check user permissions for photos
$canEditPhotos = $currentUser->isAdministratorPhotos();

if (!$canEditPhotos) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to upload photos.']);
    exit;
}

// Get album ID from POST data
$albumId = isset($_POST['album_id']) ? (int) $_POST['album_id'] : 0;

if ($albumId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Album ID is required.']);
    exit;
}

// Verify album exists and belongs to current organization
$sql = 'SELECT pho_id, pho_name, pho_begin, pho_quantity
        FROM adm_photos
        WHERE pho_id = ? AND pho_org_id = ? AND pho_locked = 0';
$albumStatement = $gDb->queryPrepared($sql, [$albumId, $currentOrgId]);
$album = $albumStatement->fetch();

if ($album === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Album not found or access denied.']);
    exit;
}

// DEBUG: Log what we're receiving
error_log("Photo upload - POST: " . json_encode($_POST));
error_log("Photo upload - FILES: " . json_encode($_FILES));
error_log("Photo upload - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Check if photo file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];
    
    $errorCode = isset($_FILES['photo']) ? $_FILES['photo']['error'] : UPLOAD_ERR_NO_FILE;
    $errorMessage = $uploadErrors[$errorCode] ?? 'Unknown upload error.';
    
    // DEBUG: Log the error
    error_log("Photo upload error: " . $errorMessage . " (code: " . $errorCode . ")");
    
    http_response_code(400);
    echo json_encode(['error' => $errorMessage]);
    exit;
}

$uploadedFile = $_FILES['photo'];
$tempPath = $uploadedFile['tmp_name'];
$originalName = $uploadedFile['name'];
$fileSize = $uploadedFile['size'];

// Validate file type - only JPEG and PNG allowed (matching web upload logic)
$imageProperties = getimagesize($tempPath);
if ($imageProperties === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image file. Could not read image properties.']);
    exit;
}

// Check mime type - only JPEG and PNG allowed (matching web UploadHandlerPhoto)
$detectedMime = $imageProperties['mime'];
$fileExtension = 'jpg';

switch ($detectedMime) {
    case 'image/jpeg':
        $fileExtension = 'jpg';
        break;
    case 'image/png':
        $fileExtension = 'png';
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only JPEG and PNG images are allowed.']);
        exit;
}

// Check image resolution against processable size
$imageDimensions = $imageProperties[0] * $imageProperties[1];
$processableImageSize = 16000000; // ~16MP default, same as web
if (function_exists('Admidio\\Infrastructure\\Utils\\SystemInfoUtils::getProcessableImageSize')) {
    $processableImageSize = \Admidio\Infrastructure\Utils\SystemInfoUtils::getProcessableImageSize();
}

if ($imageDimensions > $processableImageSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Image resolution is too large. Maximum allowed: ' . round($processableImageSize / 1000000, 2) . ' megapixels.']);
    exit;
}

// Determine target directory using date format Y-m-d (matching web logic)
$baseFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/';
$albumBeginDate = date('Y-m-d', strtotime($album['pho_begin']));
$albumFolder = $baseFolder . $albumBeginDate . '_' . $albumId;

// Create album folder if it doesn't exist
if (!is_dir($albumFolder)) {
    if (!mkdir($albumFolder, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create album directory.']);
        exit;
    }
}

// Get next photo number based on pho_quantity (matching web logic exactly)
$newPhotoFileNumber = (int) $album['pho_quantity'] + 1;

// Get settings for image scaling
$photoShowWidth = 1200;  // default
$photoShowHeight = 1200; // default
$photoThumbsScale = 200; // default
$photoKeepOriginal = false;

if (isset($gSettingsManager)) {
    $photoShowWidth = $gSettingsManager->getInt('photo_show_width') ?: 1200;
    $photoShowHeight = $gSettingsManager->getInt('photo_show_height') ?: 1200;
    $photoThumbsScale = $gSettingsManager->getInt('photo_thumbs_scale') ?: 200;
    $photoKeepOriginal = $gSettingsManager->getBool('photo_keep_original');
}

try {
    // Use Admidio Image class for consistent processing with web
    $image = new Image($tempPath);
    $image->setImageType('jpeg');
    
    // Scale image to configured dimensions (matching web logic)
    $image->scale($photoShowWidth, $photoShowHeight);
    
    // Save scaled image as JPG (web always saves as .jpg regardless of original format)
    $targetPath = $albumFolder . '/' . $newPhotoFileNumber . '.jpg';
    $image->copyToFile(null, $targetPath);
    $image->delete();
    
    // Save original if setting is enabled
    if ($photoKeepOriginal) {
        $originalsFolder = $albumFolder . '/originals';
        if (!is_dir($originalsFolder)) {
            mkdir($originalsFolder, 0755, true);
        }
        
        // Copy original file with original extension
        $originalTargetPath = $originalsFolder . '/' . $newPhotoFileNumber . '.' . $fileExtension;
        copy($tempPath, $originalTargetPath);
    }
    
    // Create thumbnail (matching web logic)
    $thumbnailFolder = $albumFolder . '/thumbnails';
    if (!is_dir($thumbnailFolder)) {
        mkdir($thumbnailFolder, 0755, true);
    }
    
    $thumbImage = new Image($tempPath);
    $thumbImage->scaleLargerSide($photoThumbsScale);
    $thumbImage->copyToFile(null, $thumbnailFolder . '/' . $newPhotoFileNumber . '.jpg');
    $thumbImage->delete();
    
    // Verify the file was created successfully
    if (!is_file($targetPath)) {
        throw new Exception('Failed to save photo to album.');
    }
    
    // Update photo count in database (only after successful file save)
    $newQuantity = $newPhotoFileNumber;
    $updateSql = 'UPDATE adm_photos SET pho_quantity = ? WHERE pho_id = ?';
    $gDb->queryPrepared($updateSql, [$newQuantity, $albumId]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'photoId' => $albumId . '-' . $newPhotoFileNumber,
        'filename' => $newPhotoFileNumber . '.jpg',
        'photoNumber' => $newPhotoFileNumber,
        'albumId' => $albumId,
        'newQuantity' => $newQuantity,
    ]);
    
} catch (Exception $e) {
    // Clean up any partial uploads
    if (isset($targetPath) && file_exists($targetPath)) {
        @unlink($targetPath);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Photo processing error: ' . $e->getMessage()]);
    exit;
}
