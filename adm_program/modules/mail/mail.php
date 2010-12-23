<?php
/******************************************************************************
 * E-Mails verschicken
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id  - E-Mail an den entsprechenden Benutzer schreiben
 * rolle   - E-Mail an alle Mitglieder der Rolle schreiben
 * cat     - In Kombination mit dem Rollennamen muss auch der Kategoriename uebergeben werden
 * rol_id  - Statt einem Rollennamen/Kategorienamen kann auch eine RollenId uebergeben werden
 * subject - Betreff der E-Mail
 * body    - Inhalt der E-Mail
 * kopie   - 1 (Default) Checkbox "Kopie an mich senden" ist gesetzt
 *         - 0 Checkbox "Kopie an mich senden" ist NICHT gesetzt
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/email.php');

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse für nicht eigeloggte Benutzer geladen werden
if (!$g_valid_login && $g_preferences['enable_mail_captcha'] == 1 && $g_preferences['captcha_type']=='calc')
{
	require_once('../../system/classes/captcha.php');
}

// Pruefungen, ob die Seite regulaer aufgerufen wurde
if ($g_preferences['enable_mail_module'] != 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}


if ($g_valid_login && !isValidEmailAddress($g_current_user->getValue('EMAIL')))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->show($g_l10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}


//Falls ein Rollenname uebergeben wurde muss auch der Kategoriename uebergeben werden und umgekehrt...
if ( (isset($_GET['rolle']) && !isset($_GET['cat'])) || (!isset($_GET['rolle']) && isset($_GET['cat'])) )
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}


if (isset($_GET['usr_id']))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_valid_login)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if (is_numeric($_GET['usr_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden    
    if((  $g_current_user->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0 )
    {
        $g_message->show($g_l10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue('EMAIL')))
    {
        $g_message->show($g_l10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }

    $userEmail = $user->getValue('EMAIL');
}
elseif (isset($_GET['rol_id']))
{
    // Falls eine rol_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    if (is_numeric($_GET['rol_id']) == false || ($g_valid_login && !$g_current_user->mailRole($_GET['rol_id'])))
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $sql    = 'SELECT rol_mail_this_role, rol_name, rol_id 
                 FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_id = '. $_GET['rol_id']. '
                  AND rol_cat_id = cat_id
                  AND cat_org_id = '. $g_current_organization->getValue('org_id');
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);

    if(($g_valid_login == false && $row['rol_mail_this_role'] != 3)
    || ($g_valid_login == true  && $g_current_user->mailRole($row['rol_id']) == false))
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $rollenName = $row['rol_name'];
    $rollenID   = $_GET['rol_id'];
}
elseif (isset($_GET['rolle']) && isset($_GET['cat']))
{
    // Falls eine rolle und eine category uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    $sql = 'SELECT rol_mail_this_role, rol_id
              FROM '. TBL_ROLES. ' ,'. TBL_CATEGORIES. '
             WHERE UPPER(rol_name) = UPPER("'. $_GET['rolle']. '")
               AND rol_cat_id        = cat_id
               AND cat_org_id        = '. $g_current_organization->getValue('org_id'). '
               AND UPPER(cat_name)   = UPPER("'. $_GET['cat']. '")';
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);

    // Ausgeloggte duerfen nur an Rollen mit dem Flag "alle Besucher der Seite" Mails schreiben
    // Eingeloggte duerfen nur an Rollen Mails schreiben, zu denen sie berechtigt sind
    if(($g_valid_login == false && $row['rol_mail_this_role'] != 3)
    || ($g_valid_login == true  && $g_current_user->mailRole($row['rol_id']) == false))
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $rollenName = $_GET['rolle'];
    $rollenID   = $row['rol_id'];
}

if (array_key_exists('subject', $_GET) == false)
{
    $_GET['subject'] = '';
}

if (array_key_exists('body', $_GET) == false)
{
    $_GET['body']  = '';
}

if (!array_key_exists('kopie', $_GET) || !is_numeric($_GET['kopie']))
{
    $_GET['kopie'] = '1';
}

// Wenn die letzte URL in der Zuruecknavigation die des Scriptes mail_send.php ist,
// dann soll das Formular gefuellt werden mit den Werten aus der Session
if (strpos($_SESSION['navigation']->getUrl(),'mail_send.php') > 0 && isset($_SESSION['mail_request']))
{
    // Das Formular wurde also schon einmal ausgefüllt,
    // da der User hier wieder gelandet ist nach der Mailversand-Seite
    $form_values = strStripSlashesDeep($_SESSION['mail_request']);
    unset($_SESSION['mail_request']);

    $_SESSION['navigation']->deleteLastUrl();
}
else
{
    $form_values['name']         = '';
    $form_values['mailfrom']     = '';
    $form_values['subject']      = '';
    $form_values['body']         = '';
    $form_values['rol_id']       = '';
}



// Seiten fuer Zuruecknavigation merken
if(isset($_GET['usr_id']) == false && isset($_GET['rol_id']) == false)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Focus auf das erste Eingabefeld setzen
if (!isset($_GET['usr_id'])
 && !isset($_GET['rol_id'])
 && !isset($_GET['rolle']) )
{
    $focusField = 'rol_id';
}
else if($g_current_user->getValue('usr_id') == 0)
{
    $focusField = 'name';
}
else
{
    $focusField = 'subject';
}

// Html-Kopf ausgeben
if (strlen($_GET['subject']) > 0)
{
    $g_layout['title'] = $_GET['subject'];
}
else
{
    $g_layout['title'] = $g_l10n->get('MAI_SEND_EMAIL');
}

$g_layout['header'] =  '
<script type="text/javascript">

    // neue Zeile mit Button zum Hinzufuegen von Dateipfaden einblenden
    function addAttachment()
    {
        new_attachment = document.createElement("input");
        $(new_attachment).attr("type", "file");
        $(new_attachment).attr("name", "userfile[]");
        $(new_attachment).attr("size", "35");
        $(new_attachment).css("display", "block");
        $(new_attachment).css("width", "350px");
        $(new_attachment).hide();
        $("#add_attachment").before(new_attachment);
        $(new_attachment).show("slow");
    }

    $(document).ready(function() 
	{
        $("#'.$focusField.'").focus();
 	});
</script>';

require(THEME_SERVER_PATH. '/overall_header.php');
echo '
<form action="'.$g_root_path.'/adm_program/modules/mail/mail_send.php?';
    // usr_id wird mit GET uebergeben,
    // da keine E-Mail-Adresse von mail_send angenommen werden soll
    if (array_key_exists("usr_id", $_GET))
    {
        echo "usr_id=". $_GET['usr_id']. "&";
    }
    echo '" method="post" enctype="multipart/form-data">

    <div class="formLayout" id="write_mail_form">
        <div class="formHead">'. $g_layout['title']. '</div>
        <div class="formBody">
            <ul class="formFieldList">
                <li>
                    <dl>
                        <dt><label for="rol_id">'.$g_l10n->get('SYS_TO').':</label></dt>
                        <dd>';
                            if (array_key_exists('usr_id', $_GET))
                            {
                                // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                                echo '<input type="text" readonly="readonly" id="mailto" name="mailto" style="width: 350px;" maxlength="50" value="'.$userEmail.'" />
                                <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>';
                            }
                            elseif ( array_key_exists("rol_id", $_GET) || (array_key_exists("rolle", $_GET) && array_key_exists("cat", $_GET)) )
                            {
                                // Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
                                echo '<select size="1" id="rol_id" name="rol_id"><option value="'.$rollenID.'" selected="selected">'.$rollenName.'</option></select>
                                <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>';
                            }
                            else
                            {
                                // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
                                echo '<select size="1" id="rol_id" name="rol_id">';
                                if ($form_values['rol_id'] == "")
                                {
                                    echo '<option value="" selected="selected">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';
                                }

                                if ($g_valid_login)
                                {
                                    // alle Rollen auflisten,
                                    // an die im eingeloggten Zustand Mails versendet werden duerfen
                                    $sql    = 'SELECT rol_name, rol_id, cat_name 
                                               FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                                               WHERE rol_valid        = 1
                                               AND rol_cat_id       = cat_id
                                               AND cat_org_id       = '. $g_current_organization->getValue('org_id'). '
                                               ORDER BY cat_sequence, rol_name ';
                                }
                                else
                                {
                                    // alle Rollen auflisten,
                                    // an die im nicht eingeloggten Zustand Mails versendet werden duerfen
                                    $sql    = 'SELECT rol_name, rol_id, cat_name 
                                               FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                                               WHERE rol_mail_this_role = 3
                                               AND rol_valid         = 1
                                               AND rol_cat_id        = cat_id
                                               AND cat_org_id        = '. $g_current_organization->getValue('org_id'). '
                                               ORDER BY cat_sequence, rol_name ';
                                }
                                $result = $g_db->query($sql);
                                $act_category = '';

                                while ($row = $g_db->fetch_object($result))
                                {
                                  	if(!$g_valid_login || ($g_valid_login && $g_current_user->mailRole($row->rol_id)))
                                    {
                                        if($act_category != $row->cat_name)
                                        {
                                            if(strlen($act_category) > 0)
                                            {
                                                echo '</optgroup>';
                                            }
                                            echo '<optgroup label="'.$row->cat_name.'">';
                                            $act_category = $row->cat_name;
                                        }
                                        echo '<option value="'.$row->rol_id.'" ';
                                        if (isset($form_values['rol_id']) 
                                        && $row->rol_id == $form_values['rol_id'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$row->rol_name.'</option>';
                                    }
                                }

                                echo '</optgroup>
                                </select>
                                <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
	                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MAI_SEND_MAIL_TO_ROLE&amp;inline=true"><img 
						            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MAI_SEND_MAIL_TO_ROLE\',this)" onmouseout="ajax_hideTooltip()"
						            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
                            }
                        echo'
                        </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
                <li>
                    <dl>
                        <dt><label for="name">'.$g_l10n->get('SYS_NAME').':</label></dt>
                        <dd>';
                            if ($g_current_user->getValue("usr_id") > 0)
                            {
                               echo '<input type="text" id="name" name="name" readonly="readonly" style="width: 200px;" maxlength="50" value="'. $g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME'). '" />';
                            }
                            else
                            {
                               echo '<input type="text" id="name" name="name" style="width: 200px;" maxlength="50" value="'. $form_values['name']. '" />';
                            }
                            echo '<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for="mailfrom">'.$g_l10n->get('SYS_EMAIL').':</label></dt>
                        <dd>';
                            if ($g_current_user->getValue("usr_id") > 0)
                            {
                               echo '<input type="text" id="mailfrom" name="mailfrom" readonly="readonly" style="width: 350px;" maxlength="50" value="'. $g_current_user->getValue('EMAIL'). '" />';
                            }
                            else
                            {
                               echo '<input type="text" id="mailfrom" name="mailfrom" style="width: 350px;" maxlength="50" value="'. $form_values['mailfrom']. '" />';
                            }
                            echo '<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
                <li>
                    <dl>
                        <dt><label for="subject">'.$g_l10n->get('MAI_SUBJECT').':</label></dt>
                        <dd>';
                            if (strlen($_GET['subject']) > 0)
                            {
                               echo '<input type="text" readonly="readonly" id="subject" name="subject" style="width: 350px;" maxlength="50" value="'. $_GET['subject']. '" />';
                            }
                            else
                            {
                               echo '<input type="text" id="subject" name="subject" style="width: 350px;" maxlength="50" value="'. $form_values['subject']. '" />';
                            }
                            echo '<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for="body">'.$g_l10n->get('MAI_MESSAGE').':</label></dt>
                        <dd>';
                            if (strlen($form_values['body']) > 0)
                            {
                               echo '<textarea id="body" name="body" style="width: 350px;" rows="10" cols="45">'. $form_values['body']. '</textarea>';
                            }
                            else
                            {
                               echo '<textarea id="body" name="body" style="width: 350px;" rows="10" cols="45">'. $_GET['body']. '</textarea>';
                            }
                        echo '</dd>
                    </dl>
                </li>';

                // Nur eingeloggte User duerfen Attachments mit max 3MB anhaengen...
                if (($g_valid_login) && ($g_preferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
                {
                    // das Feld userfile wird in der Breite mit size und width gesetzt, da FF nur size benutzt und IE size zu breit macht :(
                    echo '
                    <li>
                        <dl>
                            <dt><label for="add_attachment">Anhang:</label></dt>
                            <dd id="attachments">
                                <input type="hidden" name="MAX_FILE_SIZE" value="' . ($g_preferences['max_email_attachment_size'] * 1024) . '" />
                                <span id="add_attachment" class="iconTextLink" style="display: block;">
                                    <a href="javascript:addAttachment()"><img
                                    src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('MAI_ADD_ATTACHEMENT').'" /></a>
                                    <a href="javascript:addAttachment()">'.$g_l10n->get('MAI_ADD_ATTACHEMENT').'</a>
                                    <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=MAI_MAX_ATTACHMENT_SIZE&amp;message_var1='. Email::getMaxAttachementSize('mb').'&amp;inline=true"><img 
                                        onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=MAI_MAX_ATTACHMENT_SIZE&amp;message_var1='. Email::getMaxAttachementSize('mb').'\',this)" onmouseout="ajax_hideTooltip()"
                                        class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                                </span>
                            </dd>
                        </dl>
                    </li>';
                }

                echo '
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type="checkbox" id="kopie" name="kopie" value="1" ';
                            if ($_GET['kopie'] == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' /> <label for="kopie">'.$g_l10n->get('MAI_SEND_COPY').'</label>
                        </dd>
                    </dl>
                </li>';

                // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
                // falls es in den Orgaeinstellungen aktiviert wurde...
                if (!$g_valid_login && $g_preferences['enable_mail_captcha'] == 1)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>';
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
            
            <hr />

            <div class="formSubmit">
                <button id="btnSend" type="submit"><img src="'. THEME_PATH. '/icons/email.png" alt="'.$g_l10n->get('SYS_SEND').'" />&nbsp;'.$g_l10n->get('SYS_SEND').'</button>
            </div>
        </div>
    </div>
</form>';

if(isset($_GET['usr_id']) || isset($_GET['rol_id']))
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}

require(THEME_SERVER_PATH. '/overall_footer.php');

?>