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
     * Function for the Smarty template engine that could be used within the templates to check if a string
     * is inside another string.
     * @param array                    $params   Array with all the variables that are set within the template file.
     * @param Template $template The Smarty template object that could be used within the function.
     * @return bool Returns **true** if the string is found, otherwise **false**
     *
     * **Code example**
     * ```
     * // example of this function within a template file
     * {if {string_contains haystack=$myText needle='searchString'}}
     *    ...
     * {else}
     *    ...
     * {/if}
     * ```
     */
    public static function stringContains(array $params, Template $template): bool
    {
        if (empty($params['haystack'])) {
            throw new \UnexpectedValueException('Smarty function string_contains: missing "haystack" parameter');
        }

        if (empty($params['needle'])) {
            throw new \UnexpectedValueException('Smarty function string_contains: missing "needle" parameter');
        }

        if (strpos($params['haystack'], $params['needle']) !== false) {
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
    
    /**
     * Function for the Smarty template engine to resolve resource files (css, js, images) within the theme
     * directory structure. Admidio allows a primary and a fallback theme, where the primary theme is basically
     * an override for the fallback theme and does not have to be a complete theme with all files available.
     * However, the URLs generated by Admidio need to point to the correct file, so when generating the link/URL,
     * we cannot simply concat the themeURL and the path, but need to check whether the file exists in the 
     * primary theme or the fallback theme.
     * This function works similar to the getThemendFile function, but is meant to be called as a Smarty plugin
     * and will return externally resolveable URLs rather than local paths.
     * 
     * @param array $params   Array with all the variables that are set within the template file.
     * @param Template $template The Smarty template object that could be used within the function.
     * @return string the URL to the file including the theme /
     *                fallback theme, depening on which of them provides
     *                the file for real. If the file is not found in either 
     *                the theme and the fallback theme, the URL inside
     *                the fallback theme is returned without error.
     *
     * **Code example**
     * ```
     * // Include a logo that can be placed in either the primary theme or the fallback theme folder structure
     * <img src="{get_themed_file file='/images/admidio_logo.png'}">
     * ```
     */
    
    public static function smarty_tag_getThemedFile(array $params, \Smarty\Template $template): string {
        $filepath = $params['filepath']??'';
        if (file_exists(THEME_PATH . $filepath)) {
            return THEME_URL . $filepath;
        }
        if (defined('THEME_FALLBACK_PATH') && 
        file_exists(THEME_FALLBACK_PATH . $filepath)) {
            return THEME_FALLBACK_URL . $filepath;
        }
        // If the file exists, this should never execute. If it does not exist, we will cause a 404 call, anyway.
        return THEME_URL . $filepath;
    }
    
}