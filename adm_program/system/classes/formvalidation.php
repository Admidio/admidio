<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * @class FormValidation
 * @brief Validate various content of form elements
 *
 */

require_once(SERVER_PATH.'/adm_program/libs/securimage/securimage.php');

class FormValidation
{
    /** Checks if the value of the captcha input matches with the captcha image.
     *  @param string $value Value of the captcha input field.
     *  @return Returns @b true if the value matches the captcha image. Otherwise throw an
     *          exception SYS_CAPTCHA_CODE_INVALID.
     */
    public static function checkCaptcha($value)
    {
        global $gPreferences;

        $securimage = new Securimage();

        if ($securimage->check($value) === false)
        {
            if($gPreferences['captcha_type'] === 'calc')
            {
                throw new AdmException('SYS_CAPTCHA_CALC_CODE_INVALID');
            }
            else
            {
                throw new AdmException('SYS_CAPTCHA_CODE_INVALID');
            }
        }
        return true;
    }
}
