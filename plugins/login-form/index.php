<?php
use LoginForm\classes\LoginForm;

/**
 ***********************************************************************************************
 * Login Form
 *
 * Login Form represents the login form with the appropriate fields for a user to log in.
 * If the user is logged in, useful information of the user is now displayed in the place
 * of the fields.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    require_once(__DIR__ . '/../../system/common.php');
    // the plugin manager only loads the classes of plugins on the overview page, so load the main class
    // if the plugin is called directly; getInstance() then registers the autoloader for all other classes
    if (!class_exists(LoginForm::class, false)) {
        require_once(__DIR__ . '/classes/LoginForm.php');
    }

    $pluginLoginForm = LoginForm::getInstance();
    $pluginLoginForm->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
