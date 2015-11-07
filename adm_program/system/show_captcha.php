<?php
/**
 ***********************************************************************************************
 * Show a random captcha with consideration of all preferences
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id : A random integer that is used for a unique call of this script
 ***********************************************************************************************
 */

require('common.php');

// creates a small picture that shows random characters
if($gPreferences['captcha_type'] === 'pic')
{
    $captcha = new Captcha();
    $captcha->getCaptcha();
}
