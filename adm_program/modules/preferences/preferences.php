<?php
/**
 ***********************************************************************************************
 * Organization preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * show_option : show preferences of module with this text id
 *               Example: SYS_COMMON or
 ***********************************************************************************************
 */
use Admidio\UserInterface\Preferences;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $showOption = admFuncVariableIsValid($_GET, 'show_option', 'string');

    // only administrators are allowed to edit organization preferences
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    $headline = $gL10n->get('SYS_SETTINGS');

    if ($showOption !== '') {
        // add current url to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $headline);
    } else {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-gear-fill');
    }

    // create html page object
    $page = new Preferences('admidio-preferences', $headline);
    $page->show();
    exit();
} catch (AdmException $e) {
    $gMessage->show($e->getMessage());
}
