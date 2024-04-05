<?php
/**
 * Function for the Smarty template engine that could be used within the templates to check if the given string
 * is a valid fontAwesome icon.
 * @param array                    $params   Array with all the variables that are set within the template file.
 * @param Smarty\Template $template The Smarty template object that could be used within the function.
 * @return bool Returns **true** if the string is a valid fontAwesome icon, otherwise **false**.
 *
 * **Code example**
 * ```
 * // example of this function within a template file
 * {if {is_font_awesome_icon icon=$iconName}}
 *    ...
 * {else}
 *    ...
 * {/if}
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
function smarty_function_is_font_awesome_icon(array $params, Smarty\Template $template)
{
    if (empty($params['icon'])) {
        throw new \UnexpectedValueException('Smarty function is_font_awesome_icon: missing "icon" parameter');
    }

    if (Image::isFontAwesomeIcon($params['icon'])) {
        return true;
    }
    return false;
}
