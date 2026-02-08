<?php
/**
    * Serve the organization logo image from the protected adm_my_files folder.
    * This script streams the logo image with proper headers for browser display.
    */

require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/../common_function.php');

// Only allow admins to view the logo (or you could allow all authenticated users)
if (!$gValidLogin) {
    http_response_code(403);
    exit('Access denied');
}

$orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
if ($orgId <= 0) {
    http_response_code(404);
    exit('Organization not found');
}

$logoPath = ADMIDIO_PATH . FOLDER_DATA . '/residents/org_logo_' . $orgId . '.png';

if (!file_exists($logoPath)) {
    http_response_code(404);
    exit('Logo not found');
}

// Get file modification time for caching
$mtime = filemtime($logoPath);
$etag = '"' . md5($mtime . $logoPath) . '"';

// Check if browser has cached version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
    http_response_code(304);
    exit;
}

// Set headers for image delivery
header('Content-Type: image/png');
header('Content-Length: ' . filesize($logoPath));
header('Cache-Control: public, max-age=86400'); // Cache for 1 day
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);

// Stream the file
readfile($logoPath);
exit;
