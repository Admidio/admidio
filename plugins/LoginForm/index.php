<?php
use Plugins\LoginForm\classes\LoginForm;

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

    $pluginLoginForm = LoginForm::getInstance();
    $pluginLoginForm->doRender(isset($page) ? $page : null);

} catch (Throwable $e) {
    echo $e->getMessage();
}
