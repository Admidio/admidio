<?php
/******************************************************************************
 * Show a random captcha with consideration of all preferences
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * id : A random integer that is used for a unique call of this script
 *
 *****************************************************************************/

require('common.php');

// creates a small picture that shows random characters
if($gPreferences['captcha_type'] === 'pic')
{
    $captcha = new Captcha();
    $captcha->getCaptcha();
}
