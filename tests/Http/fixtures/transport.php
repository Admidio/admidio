<?php

// Isolated HTTP transport fixture; never loaded by the Admidio runtime router.
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/redirect':
        header('Location: /echo', true, 303);
        echo 'Redirect response';
        break;
    case '/cookie':
        setcookie('http_test', 'browser-state', ['httponly' => true, 'path' => '/']);
        break;
    default:
        header('Content-Type: application/json');
        header('X-Test-Value: first');
        header('X-Test-Value: second', false);
        echo json_encode([
            'method' => $_SERVER['REQUEST_METHOD'],
            'form' => $_POST,
            'cookie' => $_COOKIE['http_test'] ?? null,
        ], JSON_THROW_ON_ERROR);
}
