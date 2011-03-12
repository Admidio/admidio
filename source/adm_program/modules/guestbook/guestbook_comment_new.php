<?php
/******************************************************************************
 * Gaestebuchkommentare anlegen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id            - ID des Eintrages, dem ein Kommentar hinzugefuegt werden soll
 * cid           - ID des Kommentars der editiert werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook_comment.php');

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

// Es muss ein (nicht zwei) Parameter uebergeben werden: Entweder id oder cid...
if (isset($_GET['id']) && isset($_GET['cid']))
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Erst einmal die Rechte abklopfen...
if(($g_preferences['enable_guestbook_module'] == 2 || $g_preferences['enable_gbook_comments4all'] == 0)
&& isset($_GET['id']))
{
    // Falls anonymes kommentieren nicht erlaubt ist, muss der User eingeloggt sein zum kommentieren
    require_once('../../system/login_valid.php');

    if (!$g_current_user->commentGuestbookRight())
    {
        // der User hat kein Recht zu kommentieren
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

if (isset($_GET['cid']))
{
    // Zum editieren von Kommentaren muss der User auch eingeloggt sein
    require_once('../../system/login_valid.php');

    if (!$g_current_user->editGuestbookRight())
    {
        // der User hat kein Recht Kommentare zu editieren
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

}


// Uebergabevariablen pruefen
if (array_key_exists('id', $_GET))
{
    if (is_numeric($_GET['id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
elseif (array_key_exists('cid', $_GET))
{
    if (is_numeric($_GET['cid']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}


if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = $g_l10n->get('GBO_GUESTBOOK');
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Gaestebuchkommentarobjekt anlegen
$guestbook_comment = new TableGuestbookComment($g_db);

if(isset($_GET['cid']) && $_GET['cid'] > 0)
{
    $guestbook_comment->readData($_GET['cid']);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if($guestbook_comment->getValue('gbo_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['guestbook_comment_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$guestbook_comment->setArray($_SESSION['guestbook_comment_request']);
    unset($_SESSION['guestbook_comment_request']);
}

// Wenn der User eingeloggt ist und keine cid uebergeben wurde
// koennen zumindest Name und Emailadresse vorbelegt werden...
if (isset($_GET['cid']) == false && $g_valid_login)
{
    $guestbook_comment->setValue('gbc_name', $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME'));
    $guestbook_comment->setValue('gbc_email', $g_current_user->getValue('EMAIL'));
}


if (!$g_valid_login && $g_preferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK_COMMENTS. '
            where unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $g_preferences['flooding_protection_time']. '
              and gbc_ip_address = "'. $guestbook_comment->getValue('gbc_ip_address'). '"';
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $g_message->show($g_l10n->get('GBO_FLOODING_PROTECTION', $g_preferences['flooding_protection_time']));
    }
}

// Html-Kopf ausgeben
if (isset($_GET['id']))
{
    $id   = $_GET['id'];
    $mode = '4';
    $g_layout['title'] = $g_l10n->get('GBO_CREATE_COMMENT');
}
else
{
    $id   = $_GET['cid'];
    $mode = '8';
    $g_layout['title'] = $g_l10n->get('GBO_EDIT_COMMENT');
}

//Script für BBCode laden
$javascript = '';
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('gbc_text');
}

if ($g_current_user->getValue('usr_id') == 0)
{
    $focusField = 'gbc_name';
}
else
{
    $focusField = 'gbc_text';
}

$g_layout['header'] = $javascript. '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#'.$focusField.'").focus();
	 	}); 
	//--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<form action="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_function.php?id='.$id.'&amp;headline='. $_GET['headline']. '&amp;mode='.$mode.'" method="post">
<div class="formLayout" id="edit_guestbook_comment_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="gbc_name">'.$g_l10n->get('SYS_NAME').':</label></dt>
                    <dd>';
                        if ($g_current_user->getValue('usr_id') > 0)
                        {
                            // Eingeloggte User sollen ihren Namen nicht aendern duerfen
                            echo '<input readonly="readonly" type="text" id="gbc_name" name="gbc_name" tabindex="1" style="width: 345px;" maxlength="60" value="'. $guestbook_comment->getValue('gbc_name'). '" />';
                        }
                        else
                        {
                            echo '<input type="text" id="gbc_name" name="gbc_name" tabindex="1" style="width: 345px;" maxlength="60" value="'. $guestbook_comment->getValue('gbc_name'). '" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>';
                        }
                    echo '</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="gbc_email">'.$g_l10n->get('SYS_EMAIL').':</label></dt>
                    <dd>
                        <input type="text" id="gbc_email" name="gbc_email" tabindex="2" style="width: 345px;" maxlength="50" value="'. $guestbook_comment->getValue('gbc_email'). '" />
                    </dd>
                </dl>
            </li>';
         if ($g_preferences['enable_bbcode'] == 1)
         {
            printBBcodeIcons();
         }
         echo '
            <li>
                <dl>
                    <dt><label for="gbc_text">'.$g_l10n->get('SYS_COMMENT').':</label>';
                        //Einfügen der Smilies
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea  id="gbc_text" name="gbc_text" tabindex="3" style="width: 345px;" rows="10" cols="40">'. $guestbook_comment->getValue('gbc_text'). '</textarea>&nbsp;<span title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'" style="color: #990000;">*</span>
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
                </li>
                <li>
                    <dl>
                           <dt><label for="captcha">'.$captcha_label.':</label></dt>
                           <dd>
                               <input type="text" id="captcha" name="captcha" tabindex="4" style="width: 200px;" maxlength="8" value="" />
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

        if($guestbook_comment->getValue('gbc_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $guestbook_comment->getValue('gbc_usr_id_create'));
                echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $guestbook_comment->getValue('gbc_timestamp_create'));

                if($guestbook_comment->getValue('gbc_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $guestbook_comment->getValue('gbc_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $guestbook_comment->getValue('gbc_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit" tabindex="5"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>';

    echo '</div>
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

require(THEME_SERVER_PATH. '/overall_footer.php');

?>