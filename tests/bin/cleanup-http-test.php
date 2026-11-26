#!/usr/bin/env php
<?php

use Admidio\Tests\Support\HttpTestEnvironment;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/environment.php';

try {
    if ($argc !== 2) {
        throw new RuntimeException('Usage: php tests/bin/cleanup-http-test.php <retained-runtime-directory>');
    }
    HttpTestEnvironment::cleanupDirectory($argv[1]);
    echo "HTTP test installation removed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
