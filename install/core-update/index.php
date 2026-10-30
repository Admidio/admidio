<?php
/**
 * Entry point of the core updater in the browser. The updater itself is updater.php; entry.php
 * decides whether the installed one or the unpacked one of a running update is loaded.
 *
 * Like entry.php, this file must stay compatible in every release.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

require_once __DIR__ . '/entry.php';
require coreUpdaterFile('updater.php');
