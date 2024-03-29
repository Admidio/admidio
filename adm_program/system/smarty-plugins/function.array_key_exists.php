<?php
/**
 * Function for the Smarty template engine that could be used within the templates to check if a special key
 * exists within an array.
 * @param array                    $params   Array with all the variables that are set within the template file.
 * @param Smarty_Internal_Template $template The Smarty template object that could be used within the function.
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
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
function smarty_function_array_key_exists(array $params, Smarty_Internal_Template $template)
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
