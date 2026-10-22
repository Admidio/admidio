<?php
/**
 * A page of the hello plugin, reached through /modules/hello/ or /plugins/hello/modules/.
 * A real page starts with require_once(__DIR__ . '/../../../system/common.php'); this fixture must
 * stay free of the Admidio bootstrap so that the unit tests need no database.
 */

$GLOBALS['helloPageRuns'][] = 'index.php';
