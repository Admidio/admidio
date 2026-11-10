<?php
/**
 * Decides which updater handles a request.
 *
 * Normally that is the installed updater in this directory. But while an update installs a new
 * version, the updater of the new version handles it instead: phase A has unpacked the whole new
 * version to adm_my_files/core-update/<update id>/extracted/, and the "unpacked updater" runs from
 * there. That way no updater ever has to overwrite its own files.
 *
 * index.php, cli.php and this file must stay compatible in every release, because an update starts
 * with the old ones and ends with the new ones.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

// The Admidio directory. The unpacked updater cannot derive it from its own location.
define('ADMIDIO_CORE_UPDATE_ROOT', dirname(__DIR__, 2));

/**
 * The file to load: $file of the unpacked updater if an update says so, otherwise $file of the
 * installed updater.
 */
function coreUpdaterFile(string $file): string
{
    $installed = __DIR__ . '/' . $file;
    $updates = ADMIDIO_CORE_UPDATE_ROOT . '/adm_my_files/core-update';

    // Is an update running?
    $active = json_decode((string)@file_get_contents($updates . '/active.json'), true);
    $updateId = (string)($active['updateId'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $updateId) !== 1) {
        return $installed;
    }

    // Has phase A finished unpacking the new version?
    $state = json_decode((string)@file_get_contents($updates . '/' . $updateId . '/state.json'), true);
    if (($state['runUnpackedUpdater'] ?? false) !== true) {
        return $installed;
    }

    // Is the unpacked updater there? If not, the installed updater reports the broken update.
    $unpacked = $updates . '/' . $updateId . '/extracted/install/core-update/' . $file;

    return is_file($unpacked) ? $unpacked : $installed;
}
