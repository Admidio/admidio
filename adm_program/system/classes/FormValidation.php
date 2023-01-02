<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Validate various content of form elements
 *
 * This class can be used to validate form input. Therefore the methods can be called and get the
 * form input as parameter. The method will return **true** if validation was succesfull. Otherwise
 * an AdmException will be thrown. To catch this exception all method calls of this class should
 * be within a try and catch structure. Also all method are declared static.
 *
 *
 * **Code example**
 * ```
 * // validate the captcha code
 * try
 * {
 *     FormValidation::checkCaptcha($_POST['captcha_code']);
 * }
 * catch(AdmException $e)
 * {
 *     $e->showHtml();
 * }
 * ```
 */
class FormValidation
{
    /**
     * Checks if the value of the captcha input matches with the captcha image.
     * @param string $value Value of the captcha input field.
     * @throws AdmException SYS_CAPTCHA_CALC_CODE_INVALID, SYS_CAPTCHA_CODE_INVALID
     * @return true Returns **true** if the value matches the captcha image.
     *              Otherwise throw an exception SYS_CAPTCHA_CODE_INVALID.
     */
    public static function checkCaptcha($value)
    {
        global $gSettingsManager;

        $securimage = new Securimage();

        if ($securimage->check($value)) {
            return true;
        }

        if ($gSettingsManager->getString('captcha_type') === 'calc') {
            throw new AdmException('SYS_CAPTCHA_CALC_CODE_INVALID');
        }

        throw new AdmException('SYS_CAPTCHA_CODE_INVALID');
    }
}
