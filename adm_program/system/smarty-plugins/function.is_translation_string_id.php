<?php
/**
 * Function for the Smarty template engine that could be used within the templates to check if the given string
 * is a valid Admidio translation string.
 * @param array                    $params   Array with all the variables that are set within the template file.
 * @param Smarty_Internal_Template $template The Smarty template object that could be used within the function.
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
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
function smarty_function_is_translation_string_id(array $params, Smarty_Internal_Template $template)
{
    if (empty($params['string'])) {
        throw new \UnexpectedValueException('Smarty function is_translation_string_id: missing "string" parameter');
    }

    if (Language::isTranslationStringId($params['string'])) {
        return true;
    }
    return false;
}
