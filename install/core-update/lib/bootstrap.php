<?php
/**
 * Loads the classes of the standalone core updater.
 *
 * The updater replaces src/ and vendor/, so it cannot use the Admidio autoloader or any Admidio
 * class: it only depends on adm_my_files/config.php, the database and PHP itself. Its classes are
 * therefore loaded here explicitly. install/update.php and the settings page of Admidio load this
 * file as well, so that the state of an update and the updater cookie have one implementation.
 *
 * Everything below must stay compatible with the lowest PHP version of any supported source
 * release (8.2 for all 5.x), because an old installation runs the updater it is given.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

require_once __DIR__ . '/UpdaterException.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texts.php';
require_once __DIR__ . '/StateStore.php';
require_once __DIR__ . '/Credentials.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Throttle.php';
require_once __DIR__ . '/Login.php';
require_once __DIR__ . '/Page.php';
