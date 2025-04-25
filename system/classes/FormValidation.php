<?php
use Admidio\Infrastructure\Exception;

/**
 * @brief Validate various content of form elements
 *
 * This class can be used to validate form input. Therefore, the methods can be called and get the
 * form input as parameter. The method will return **true** if validation was successful. Otherwise,
 * an Exception will be thrown. To catch this exception all method calls of this class should
 * be within a try and catch structure. Also, all method are declared static.
 *
 *
 * **Code example**
 * ```
 * // validate the captcha code
 * try
 * {
 *     FormValidation::checkCaptcha($_POST['adm_captcha_code']);
 * }
 * catch(Exception $e)
 * {
 *     $e->showHtml();
 * }
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * @deprecated 5.0.0:5.1.0 Class FormValidation is deprecated, use class Admidio/UserInterface/Form instead.
 */
class FormValidation
{
    /**
     * Checks if the value of the captcha input matches with the captcha image.
     * @param string $value Value of the captcha input field.
     * @return true Returns **true** if the value matches the captcha image.
     *              Otherwise, throw an exception SYS_CAPTCHA_CODE_INVALID.
     * @throws Exception SYS_CAPTCHA_CALC_CODE_INVALID, SYS_CAPTCHA_CODE_INVALID
     * @deprecated 5.0.0:5.1.0 Method "FormValidation::checkCaptcha" is deprecated, use Method  "Form::validateCaptcha" instead.
     */
    public static function checkCaptcha(string $value): bool
    {
        global $gSettingsManager;

        $secureImage = new Securimage();

        if ($secureImage->check($value)) {
            return true;
        }

        if ($gSettingsManager->getString('captcha_type') === 'calc') {
            throw new Exception('SYS_CAPTCHA_CALC_CODE_INVALID');
        }

        throw new Exception('SYS_CAPTCHA_CODE_INVALID');
    }
}
