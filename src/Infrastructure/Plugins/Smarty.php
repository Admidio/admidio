<?php
namespace Admidio\Infrastructure\Plugins;

use Smarty\Template;

/**
 * @brief Plugins for the template engine Smarty
 *
 * This class includes several plugins for the template engine Smarty. Each method represents a
 * plugin and must be registered to a Smarty instance. After that the method could be used as a
 * function in every template.
 *
 * **Code example**
 * ```
 * // register method to smarty instance
 * $smarty->registerPlugin('method', 'array_key_exists', array('SmartyPlugins' => 'array_key_exists'));
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Smarty
{
    /**
     * Method for the Smarty template engine that could be used within the templates to check if a special key
     * exists within an array.
     * @param array                    $params   Array with all the variables that are set within the template file.
     * @param Template $template The Smarty template object that could be used within the function.
     * @return bool Returns **true** if the key exists and **false** if the key doesn't exist.
     *
     * **Code example**
     * ```
     * // example of this function within a template file
     * {if {array_key_exists array=$menuItem key='items'}}
     *    ...
     * {else}
     *    ...
     * {/if}
     * ```
     */
    public static function arrayKeyExists(array $params, Template $template): bool
    {
        if (empty($params['array'])) {
            throw new \UnexpectedValueException('Smarty function array_key_exists: missing "array" parameter');
        }

        if (empty($params['key'])) {
            throw new \UnexpectedValueException('Smarty function array_key_exists: missing "key" parameter');
        }

        if (array_key_exists($params['key'], $params['array'])) {
            return true;
        }
        return false;
    }

    /**
     * Function for the Smarty template engine that could be used within the templates to check if the given string
     * is a valid Admidio translation string.
     * @param array                    $params   Array with all the variables that are set within the template file.
     * @param Template $template The Smarty template object that could be used within the function.
     * @return bool Returns **true** if the string is a translation string, otherwise **false**
     *
     * **Code example**
     * ```
     * // example of this function within a template file
     * {if {is_translation_string_id string=$myText}}
     *    ...
     * {else}
     *    ...
     * {/if}
     * ```
     */
    public static function isTranslationStringID(array $params, Template $template): bool
    {
        if (empty($params['string'])) {
            throw new \UnexpectedValueException('Smarty function is_translation_string_id: missing "string" parameter');
        }

        if (\Admidio\Infrastructure\Language::isTranslationStringId($params['string'])) {
            return true;
        }
        return false;
    }

    /**
     * Function for the Smarty template engine that could be used within the templates to load
     * the html of Admidio plugins. The Admidio plugins must generate the output directly in the php
     * code with the function **echo**.
     * @param array                    $params   Array with all the variables that are set within the template file.
     * @param Template $template The Smarty template object that could be used within the function.
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
     */
    public static function loadAdmidioPlugin(array $params, Template $template): string
    {
        global $gLogger, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser;
        global $gValidLogin, $gProfileFields, $gHomepage, $gDbType, $gSettingsManager;
        global $g_root_path, $gPreferences, $gCurrentOrgId, $gCurrentUserId, $gMessage, $page;

        if (empty($params['plugin'])) {
            throw new \UnexpectedValueException('Smarty function load_admidio_plugin: missing "plugin" parameter');
        }

        if (empty($params['file'])) {
            throw new \UnexpectedValueException('Smarty function load_admidio_plugin: missing "file" parameter');
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
}
