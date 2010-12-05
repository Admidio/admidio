<?php
/******************************************************************************
 * Ausgabe einer Captcha Vorschau
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *   
 *****************************************************************************/

require_once('../../system/common.php');

// Falls die Ausgabe des Catpcha die Ausgabe als Rechenaufgabe eingestellt wurde, 
// muss die Klasse geladen werden
if ($g_preferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

echo '<b>'.$g_l10n->get("ORG_CAPTCHA_PREVIEW").'</b><br><br>';

if($g_preferences['captcha_type'] == 'pic')
{
	$height = $g_preferences['captcha_height']+25;
	$width = $g_preferences['captcha_width']+25;
	echo '<div style="width: '.$width.'px; height: '.$height.'px">
	<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$g_l10n->get('SYS_CAPTCHA').'" />
	';
}
else if($g_preferences['captcha_type']=='calc')
{
	echo '<div style="width: 450px; height: 50px">
		';
	$captcha = new Captcha();
	$captcha->getCaptchaCalc($g_l10n->get('SYS_CAPTCHA_CALC_PART1'),$g_l10n->get('SYS_CAPTCHA_CALC_PART2'),$g_l10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$g_l10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$g_l10n->get('SYS_CAPTCHA_CALC_PART4'));
	
	echo '<br><i>('.$g_l10n->get("SYS_CAPTCHA_CALC").': '.$_SESSION['captchacode'].')</i>';
}

echo '</div>';

?>