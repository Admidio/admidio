<?php
/**
 * Vercel front controller — Ghana Council e.V.
 *
 * Single serverless function entry point (compatible with Vercel free plan).
 * Routes every request to the correct Admidio PHP file, or falls back to
 * the application root index.php.
 */

define('APP_ROOT', dirname(__DIR__));

// Public directories whose PHP files may be entered directly via URL
$publicDirs = [
    '/modules/',
    '/plugins/',
    '/install/',
    '/rss/',
];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = '/' . ltrim($requestPath, '/');

$targetFile  = APP_ROOT . $requestPath;
$realRoot    = realpath(APP_ROOT);
$realTarget  = realpath($targetFile);

$isPhpFile     = $realTarget && is_file($realTarget) && pathinfo($realTarget, PATHINFO_EXTENSION) === 'php';
$withinRoot    = $realTarget && strncmp($realTarget, $realRoot, strlen($realRoot)) === 0;
$inPublicDir   = false;

if ($isPhpFile && $withinRoot) {
    $relative = substr($realTarget, strlen($realRoot));
    // Allow root-level PHP files (e.g. /index.php, /sso.php) and public dirs
    if (!str_contains(substr($relative, 1), '/')) {
        $inPublicDir = true;
    } else {
        foreach ($publicDirs as $dir) {
            if (str_starts_with($relative, $dir)) {
                $inPublicDir = true;
                break;
            }
        }
    }
}

if ($isPhpFile && $withinRoot && $inPublicDir) {
    $_SERVER['SCRIPT_FILENAME'] = $realTarget;
    $_SERVER['PHP_SELF']        = $requestPath;
    chdir(dirname($realTarget));
    /** @noinspection PhpIncludeInspection */
    require $realTarget;
} else {
    // Default: application entry point
    $entry = APP_ROOT . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $entry;
    $_SERVER['PHP_SELF']        = '/index.php';
    chdir(APP_ROOT);
    require $entry;
}
