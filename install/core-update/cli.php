<?php
/**
 * Entry point of the core updater on the command line. The command line itself is
 * updater-cli.php; entry.php decides whether the installed one or the unpacked one of a running
 * update is loaded.
 *
 *   php install/core-update/cli.php --help
 *
 * Like entry.php, this file must stay compatible in every release.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/entry.php';
require coreUpdaterFile('updater-cli.php');
