<?php
/******************************************************************************
 * Create and edit guestbook comments
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * id            - ID des Eintrages, dem ein Kommentar hinzugefuegt werden soll
 * cid           - ID des Kommentars der editiert werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/ckeditor_special.php');
require_once('../../system/classes/table_guestbook_comment.php');

// Initialize and check the parameters
$getGboId    = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$getGbcId    = admFuncVariableIsValid($_GET, 'cid', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('GBO_GUESTBOOK'));

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse für nicht eigeloggte Benutzer geladen werden
if (!$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1 && $gPreferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Es muss ein (nicht zwei) Parameter uebergeben werden: Entweder id oder cid...
if($getGboId > 0 && $getGbcId > 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Erst einmal die Rechte abklopfen...
if(($gPreferences['enable_guestbook_module'] == 2 || $gPreferences['enable_gbook_comments4all'] == 0)
&& $getGboId > 0)
{
    // Falls anonymes kommentieren nicht erlaubt ist, muss der User eingeloggt sein zum kommentieren
    require_once('../../system/login_valid.php');

    if (!$gCurrentUser->commentGuestbookRight())
    {
        // der User hat kein Recht zu kommentieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if($getGbcId > 0)
{
    // Zum editieren von Kommentaren muss der User auch eingeloggt sein
    require_once('../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight())
    {
        // der User hat kein Recht Kommentare zu editieren
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Gaestebuchkommentarobjekt anlegen
$guestbook_comment = new TableGuestbookComment($gDb);

if($getGbcId > 0)
{
    $guestbook_comment->readData($getGbcId);

    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if($guestbook_comment->getValue('gbo_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
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
if($getGbcId == 0 && $gValidLogin)
{
    $guestbook_comment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'));
    $guestbook_comment->setValue('gbc_email', $gCurrentUser->getValue('EMAIL'));
}


if (!$gValidLogin && $gPreferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = 'SELECT count(*) FROM '. TBL_GUESTBOOK_COMMENTS. '
            where unix_timestamp(gbc_timestamp_create) > unix_timestamp()-'. $gPreferences['flooding_protection_time']. '
              and gbc_ip_address = \''. $guestbook_comment->getValue('gbc_ip_address'). '\'';
    $result = $gDb->query($sql);
    $row = $gDb->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', $gPreferences['flooding_protection_time']));
    }
}

// Html-Kopf ausgeben
if($getGboId > 0)
{
    $id   = $getGboId;
    $mode = '4';
    $gLayout['title'] = $gL10n->get('GBO_CREATE_COMMENT');
}
else
{
    $id   = $getGbcId;
    $mode = '8';
    $gLayout['title'] = $gL10n->get('GBO_EDIT_COMMENT');
}

// create an object of ckeditor and replace textarea-element
$ckEditor = new CKEditorSpecial();

if ($gCurrentUser->getValue('usr_id') == 0)
{
    $focusField = 'gbc_name';
}
else
{
    $focusField = 'gbc_text';
}

$gLayout['header'] = '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#'.$focusField.'").focus();
	 	}); 
	//--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<form action="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_function.php?id='.$id.'&amp;headline='.$getHeadline.'&amp;mode='.$mode.'" method="post">
<div class="formLayout" id="edit_guestbook_comment_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admContactDetails">
			<div class="groupBoxHeadline" id="admContactDetailsHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admContactDetailsBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admContactDetailsBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_CONTACT_DETAILS').'
			</div>

			<div class="groupBoxBody" id="admContactDetailsBody">
                <ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="gbc_name">'.$gL10n->get('SYS_NAME').':</label></dt>
							<dd>';
								if ($gCurrentUser->getValue('usr_id') > 0)
								{
									// Eingeloggte User sollen ihren Namen nicht aendern duerfen
									echo '<input type="text" id="gbc_name" name="gbc_name" disabled="disabled" style="width: 90%;" maxlength="60" value="'. $guestbook_comment->getValue('gbc_name'). '" />';
								}
								else
								{
									echo '<input type="text" id="gbc_name" name="gbc_name" style="width: 90%;" maxlength="60" value="'. $guestbook_comment->getValue('gbc_name'). '" />
									<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
								}
							echo '</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="gbc_email">'.$gL10n->get('SYS_EMAIL').':</label></dt>
							<dd>
								<input type="text" id="gbc_email" name="gbc_email" style="width: 90%;" maxlength="50" value="'. $guestbook_comment->getValue('gbc_email'). '" />
							</dd>
						</dl>
					</li>
                </ul>
            </div>
        </div>
        <div class="groupBox" id="admComment">
			<div class="groupBoxHeadline" id="admCommentHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admCommentBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admCommentBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_COMMENT').'
			</div>

			<div class="groupBoxBody" id="admCommentBody">
                <ul class="formFieldList">
                    <li>
                         '.$ckEditor->createEditor('gbc_text', $guestbook_comment->getValue('gbc_text'), 'AdmidioGuestbook').'
                         <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </li>
                </ul>
            </div>
        </div>';

		// Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
		// falls es in den Orgaeinstellungen aktiviert wurde...
		if (!$gValidLogin && $gPreferences['enable_guestbook_captcha'] == 1)
		{
            echo '<div class="groupBox" id="admConfirmationOfEntry">
    			<div class="groupBoxHeadline" id="admConfirmationOfEntryHead">
    				<a class="iconShowHide" href="javascript:showHideBlock(\'admConfirmationOfEntryBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
    				id="admConfirmationOfEntryBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('SYS_CONFIRMATION_OF_INPUT').'
    			</div>
    
    			<div class="groupBoxBody" id="admConfirmationOfEntryBody">
                    <ul class="formFieldList">
                        <li>
                            <dl>
                                <dt>&nbsp;</dt>
                                <dd>';
									if($gPreferences['captcha_type']=='pic')
									{
										echo '<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />';
										$captcha_label = $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
										$captcha_description = 'SYS_CAPTCHA_DESCRIPTION';
									}
									else if($gPreferences['captcha_type']=='calc')
									{
										$captcha = new Captcha();
										$captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4'));
										$captcha_label = $gL10n->get('SYS_CAPTCHA_CALC');
										$captcha_description = 'SYS_CAPTCHA_CALC_DESCRIPTION';
									}
								echo '</dd>
							</dl>
						</li>
						<li>
							<dl>
								   <dt><label for="captcha">'.$captcha_label.':</label></dt>
								   <dd>
									   <input type="text" id="captcha" name="captcha" style="width: 200px;" maxlength="8" value="" />
									   <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
									   <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$captcha_description.'&amp;inline=true"><img 
										onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$captcha_description.'\',this)" onmouseout="ajax_hideTooltip()"
										class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
								   </dd>
							</dl>
                        </li>
                    </ul>
                </div>
            </div>';
		}

        if($guestbook_comment->getValue('gbc_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $guestbook_comment->getValue('gbc_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $guestbook_comment->getValue('gbc_timestamp_create'));

                if($guestbook_comment->getValue('gbc_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $guestbook_comment->getValue('gbc_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $guestbook_comment->getValue('gbc_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>';

    echo '</div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>