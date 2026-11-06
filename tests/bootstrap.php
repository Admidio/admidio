<?php
/**
 * PHPUnit Bootstrap File
 * Loads environment variables and initializes test framework
 */

// Unit tests must stay independent of the database and filesystem. DatabaseTestCase loads
// tests/bootstrap-admidio.php lazily when the first integration/CLI test actually needs it.
require dirname(__DIR__) . '/vendor/autoload.php';

/*
 * The Session entity resolves its id from session_id() and regenerates it when there is none.
 * The CLI SAPI starts no session on its own, and PHP refuses to start one once output has been
 * written, so this has to happen in the PHPUnit bootstrap and not in the lazily loaded
 * tests/bootstrap-admidio.php, which is required after PHPUnit has printed its header.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/environment.php';
