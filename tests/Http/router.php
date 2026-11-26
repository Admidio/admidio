<?php

// Record every response, including routes handled directly by this router. PHP's built-in
// access log omits status lines for those responses. Keep queries out of this diagnostic line.
register_shutdown_function(static function (): void {
    error_log('ADMIDIO_HTTP ' . (http_response_code() ?: 200) . ' ' . $_SERVER['REQUEST_METHOD']
        . ' ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
});

// PHP's development server otherwise falls back to index.php for unknown paths.
// Serve real files normally, matching the existing file-based application routes.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($_SERVER['DOCUMENT_ROOT'] . $path);
$root = realpath($_SERVER['DOCUMENT_ROOT']);
if ($file === false || ($file !== $root && !str_starts_with($file, $root . DIRECTORY_SEPARATOR))) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}
return false;
