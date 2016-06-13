<?php
/**
 ***********************************************************************************************
 * Ausgabe einer Captcha Vorschau
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('../../system/common.php');

echo '
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('ORG_CAPTCHA_PREVIEW').'</h4>
</div>
<div class="modal-body">';

    if($gPreferences['captcha_type'] === 'pic')
    {
        $height = $gPreferences['captcha_height'] + 25;
        $width = $gPreferences['captcha_width'] + 25;
        echo '<img src="'.$g_root_path.'/adm_program/system/show_captcha.php?id='.time().'" alt="'.$gL10n->get('SYS_CAPTCHA').'" />';
    }
    elseif($gPreferences['captcha_type'] === 'calc')
    {
        $captcha = new Captcha();
        echo $captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'), $gL10n->get('SYS_CAPTCHA_CALC_PART2'),
                                      $gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),
                                      $gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'), $gL10n->get('SYS_CAPTCHA_CALC_PART4'));

        echo '<br><i>('.$gL10n->get('SYS_CAPTCHA_CALC').': '.$_SESSION['captcha_code'].')</i>';
    }

echo '</div>';
