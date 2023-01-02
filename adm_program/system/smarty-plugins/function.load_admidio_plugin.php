<?php
/**
 * Function for the Smarty template engine that could be used within the templates to load
 * the html of Admidio plugins. The Admidio plugins must generate the output directly in the php
 * code with the function **echo**.
 * @param array                    $params   Array with all the variables that are set within the template file.
 * @param Smarty_Internal_Template $template The Smarty template object that could be used within the function.
 * @return string Returns the html code of the called Admidio plugin.
 *
 * **Code example**
 * ```
 * // example of this function within a template file
 * // load the content of the Admidio plugin login_form
 * <h1>Some html code</h1>
 * {load_admidio_plugin plugin="login_form" file="login_form.php"}
 * <span>Some other html code</span>
 * ```
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
function smarty_function_load_admidio_plugin(array $params, Smarty_Internal_Template $template)
{
    global $gLogger, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser;
    global $gValidLogin, $gProfileFields, $gHomepage, $gDbType, $gSettingsManager;
    global $g_root_path, $gPreferences, $gCurrentOrgId, $gCurrentUserId;

    if (empty($params['plugin'])) {
        throw new \UnexpectedValueException('Smarty funxtion load_admidio_plugin: missing "plugin" parameter');
    }

    if (empty($params['file'])) {
        throw new \UnexpectedValueException('Smarty funxtion load_admidio_plugin: missing "file" parameter');
    }

    $filename = ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $params['plugin'] . '/' . $params['file'];

    if (!is_file($filename)) {
        throw new \UnexpectedValueException('Invalid plugin file ' . $filename . ' !');
    }

    ob_start();
    require($filename);
    $fileContent = ob_get_contents();
    ob_end_clean();

    return $fileContent;
}
