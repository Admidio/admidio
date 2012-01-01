<?php
/******************************************************************************
 * Ausgabe einer Captcha Vorschau
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *   
 *****************************************************************************/

require_once('../../system/common.php');

// Falls die Ausgabe des Catpcha die Ausgabe als Rechenaufgabe eingestellt wurde, 
// muss die Klasse geladen werden
if ($gPreferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

echo '<b>'.$gL10n->get("ORG_CAPTCHA_PREVIEW").'</b><br><br>';

if($gPreferences['captcha_type'] == 'pic')
{
	$height = $gPreferences['captcha_height']+25;
	$width = $gPreferences['captcha_width']+25;
	echo '<div style="width: '.$width.'px; height: '.$height.'px">
	<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />
	';
}
else if($gPreferences['captcha_type']=='calc')
{
	echo '<div style="width: 450px; height: 50px">
		';
	$captcha = new Captcha();
	$captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4'));
	
	echo '<br><i>('.$gL10n->get("SYS_CAPTCHA_CALC").': '.$_SESSION['captchacode'].')</i>';
}

echo '</div>';

?>