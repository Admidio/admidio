<?php
/******************************************************************************
 * Gaestebucheintraege anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id            - ID des Eintrages, der bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook.php');

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse für nicht eigeloggte Benutzer geladen werden
if (!$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1 && $g_preferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
elseif($g_preferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Uebergabevariablen pruefen und ggf. initialisieren
$get_gbo_id   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$get_headline = admFuncVariableIsValid($_GET, 'headline', 'string', $g_l10n->get('GBO_GUESTBOOK'));

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Gaestebuchobjekt anlegen
$guestbook = new TableGuestbook($g_db);

if($get_gbo_id > 0)
{
	// Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
    require('../../system/login_valid.php');

    if (!$g_current_user->editGuestbookRight())
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    $guestbook->readData($get_gbo_id);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if($guestbook->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

// Wenn keine ID uebergeben wurde, der User aber eingeloggt ist koennen zumindest
// Name, Emailadresse und Homepage vorbelegt werden...
if ($get_gbo_id == 0 && $g_valid_login)
{
    $guestbook->setValue('gbo_name', $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME'));
    $guestbook->setValue('gbo_email', $g_current_user->getValue('EMAIL'));
    $guestbook->setValue('gbo_homepage', $g_current_user->getValue('WEBSITE'));
}

if(isset($_SESSION['guestbook_entry_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$guestbook->setArray($_SESSION['guestbook_entry_request']);
    unset($_SESSION['guestbook_entry_request']);
}

if (!$g_valid_login && $g_preferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK. '
             WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
               AND gbo_org_id = '. $g_current_organization->getValue('org_id'). '
               AND gbo_ip_address = "'. $guestbook->getValue('gbo_ip_address'). '"';
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $g_message->show($g_l10n->get('GBO_FLOODING_PROTECTION', $g_preferences['flooding_protection_time']));
    }
}

// Html-Kopf ausgeben
if ($get_gbo_id > 0)
{
    $g_layout['title'] = $g_l10n->get('GBO_EDIT_ENTRY', $get_headline);
}
else
{
    $g_layout['title'] = $g_l10n->get('GBO_CREATE_VAR_ENTRY', $get_headline);
}

//Script für BBCode laden
$javascript = '';
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('gbo_text');
}

if ($g_current_user->getValue('usr_id') == 0)
{
    $focusField = 'gbo_name';
}
else
{
    $focusField = 'gbo_text';
}

$g_layout['header'] = $javascript. '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#'.$focusField.'").focus();
	 	}); 
	//--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
if ($get_gbo_id > 0)
{
    $mode = '3';
}
else
{
    $mode = '1';
}

echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_function.php?id='. $get_gbo_id. '&amp;headline='. $get_headline. '&amp;mode='.$mode.'" >
<div class="formLayout" id="edit_guestbook_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="gbo_name">'.$g_l10n->get('SYS_NAME').':</label></dt>
                    <dd>';
                        if ($g_current_user->getValue('usr_id') > 0)
                        {
                            // Eingeloggte User sollen ihren Namen nicht aendern duerfen
                            echo '<input type="text" id="gbo_name" name="gbo_name" readonly="readonly" style="width: 345px;" maxlength="60" value="'. $guestbook->getValue('gbo_name'). '" />';
                        }
                        else
                        {
                            echo '<input type="text" id="gbo_name" name="gbo_name" style="width: 345px;" maxlength="60" value="'. $guestbook->getValue('gbo_name'). '" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>';
                        }
                    echo '</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="gbo_email">'.$g_l10n->get('SYS_EMAIL').':</label></dt>
                    <dd>
                        <input type="text" id="gbo_email" name="gbo_email" style="width: 345px;" maxlength="50" value="'. $guestbook->getValue('gbo_email'). '" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="gbo_homepage">'.$g_l10n->get('SYS_WEBSITE').':</label></dt>
                    <dd>
                        <input type="text" id="gbo_homepage" name="gbo_homepage" style="width: 345px;" maxlength="50" value="'. $guestbook->getValue('gbo_homepage'). '" />
                    </dd>
                </dl>
            </li>';
         //BBCode
         if ($g_preferences['enable_bbcode'] == 1)
         {
            printBBcodeIcons();
         }
         echo '
            <li>
                <dl>
                    <dt><label for="gbo_text">'.$g_l10n->get('SYS_TEXT').':</label>';
                        //Einfügen der Smilies
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="gbo_text" name="gbo_text" style="width: 345px;" rows="10" cols="40">'. $guestbook->getValue('gbo_text'). '</textarea>
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>';

            // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
            // falls es in den Orgaeinstellungen aktiviert wurde...
            if (!$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            ';			
				if($g_preferences['captcha_type']=='pic')
				{
					echo '<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$g_l10n->get('SYS_CAPTCHA').'" />';
					$captcha_label = $g_l10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
					$captcha_description = 'SYS_CAPTCHA_DESCRIPTION';
				}
				else if($g_preferences['captcha_type']=='calc')
				{
					$captcha = new Captcha();
					$captcha->getCaptchaCalc($g_l10n->get('SYS_CAPTCHA_CALC_PART1'),$g_l10n->get('SYS_CAPTCHA_CALC_PART2'),$g_l10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$g_l10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$g_l10n->get('SYS_CAPTCHA_CALC_PART4'));
					$captcha_label = $g_l10n->get('SYS_CAPTCHA_CALC');
					$captcha_description = 'SYS_CAPTCHA_CALC_DESCRIPTION';
				}
				echo '
                        </dd>
                    </dl>

                    <dl>
                       <dt><label for="captcha">'.$captcha_label.':</label></dt>
                       <dd>
                           <input type="text" id="captcha" name="captcha" style="width: 200px;" maxlength="8" value="" />
                           <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                           <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$captcha_description.'&amp;inline=true"><img 
				               onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$captcha_description.'\',this)" onmouseout="ajax_hideTooltip()"
				               class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                       </dd>
                    </dl>
                </li>';
            }
        echo '</ul>

        <hr />';

        if($guestbook->getValue('gbo_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $guestbook->getValue('gbo_usr_id_create'));
                echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $guestbook->getValue('gbo_timestamp_create'));

                if($guestbook->getValue('gbo_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $guestbook->getValue('gbo_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $guestbook->getValue('gbo_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>
