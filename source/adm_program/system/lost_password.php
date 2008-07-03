<?php
/******************************************************************************
 * Passwort vergessen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
require("common.php");
require("classes/system_mail.php");

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);


/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
$g_layout['title'] = $g_organization." - Passwort vergessen?";

require(THEME_SERVER_PATH. "/overall_header.php");
getVars();

// Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if (! empty($abschicken) && !$g_valid_login && $g_preferences['enable_mail_captcha'] == 1 && !empty($captcha))
{
    if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
    {
        $g_message->show("captcha_code");
        die();
    }
}
if($g_valid_login)
{
    $g_message->setForwardUrl("".$g_root_path."/adm_program/", 2000);
    $g_message->show("lost_password_allready_logged_in");   
    die();
}

if(! empty($abschicken) && ! empty($empfaenger_email) && !empty($captcha))
{
    $sql = "SELECT MAX(usr_id) as usr_id
              FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
              LEFT JOIN ". TBL_USER_DATA. " as email
                ON email.usd_usr_id = usr_id
               AND email.usd_usf_id = ".$g_current_user->getProperty("E-Mail", "usf_id")."
               AND email.usd_value  = '".$empfaenger_email."'
             WHERE rol_cat_id = cat_id
               AND cat_org_id = ".$g_current_organization->getValue("org_id")."
               AND rol_id     = mem_rol_id
               AND mem_valid  = 1
               AND mem_usr_id = usr_id
               AND usr_valid  = 1
               AND email.usd_value = '".$empfaenger_email."'";   
    $result = $g_db->query($sql);
    $row    = $g_db->fetch_array($result);
    
    if(strlen($row['usr_id']) == 0)
    {
        $g_message->show('lost_password_email_error',$empfaenger_email);    
        die();
    }

    $user = new User($g_db, $row['usr_id']);

    // Passwort und Aktivierungs-ID erzeugen und speichern
    $neues_passwort = generatePassword();
    $activation_id  = generateActivationId($user->getValue("E-Mail"));
    $user->setValue("usr_password", $neues_passwort);
    $user->setValue("usr_activation_code", $activation_id);
    
    $sysmail = new SystemMail($g_db);
    $sysmail->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
    $sysmail->setVariable(1, $g_root_path."/adm_program/system/password_activation.php?usr_id=3D".$user->getValue("usr_id")."&aid=3D".$activation_id);
    if($sysmail->sendSystemMail("SYSMAIL_ACTIVATION_LINK", $user) == true)
    {
        $user->save();

        $g_message->setForwardUrl($g_root_path."/adm_program/system/login.php");
        $g_message->show('lost_password_send',$empfaenger_email);
    }
    else
    {
        $g_message->show("lost_password_send_error",$empfaenger_email); 
    }
}
else
{
    echo'
    <div class="formLayout" id="profile_form">
        <div class="formHead">Passwort vergessen?</div>
            <div class="formBody">
            <form name="password_form" action="'.$g_root_path.'/adm_program/system/lost_password.php" method="post">
                <ul class="formFieldList">
                    <li>
                        <div>
                            Wenn du dein Passwort vergessen hast, kann das System ein Neues erstellen und an deine E-Mail Adresse senden. 
                            Gib dazu deine E-Mail-Adresse in das untenstehende Formular ein und klicke auf die Schaltfläche "Neues Passwort zusenden".
                        </div>
                    </li>
                    <li>&nbsp;</li>
                    <li>
                        <dl>
                            <dt>
                                <label>E-Mail:</label>
                            </dt>
                            <dd>
                                <input type="text" name="empfaenger_email" style="width: 300px;" maxlength="50" />
                            </dd>
                        </dl>
                    </li>';
                // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
                // falls es in den Orgaeinstellungen aktiviert wurde...
                if (!$g_valid_login && $g_preferences['enable_mail_captcha'] == 1)
                {
                    echo "
                    <li>&nbsp;</li>
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>
                                <img src=\"$g_root_path/adm_program/system/classes/captcha.php?id=". time(). "\" alt=\"Captcha\" />
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"captcha\">Best&auml;tigungscode:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\" />
                                <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"                       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help&amp;window=true','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help',this);\" onmouseout=\"ajax_hideTooltip()\" />
                            </dd>
                        </dl>
                    </li>";
                }
                echo'<hr />                                 
                <button name="abschicken" type="submit" value="abschicken"><img src="'. THEME_PATH.'/icons/email.png" alt="Abschicken" />&nbsp;Neues Passwort zusenden</button>
                </ul>
            </form>
            </div>
        </div>
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="$g_root_path/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="Zurück"></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
            </span>
        </li>
    </ul>';
}

/***************************Seitenende***************************/
require(THEME_SERVER_PATH. "/overall_footer.php");

//************************* Funktionen/Unterprogramme ***********/

// Diese Funktion holt alle Variablen ab und speichert sie in einem array
function getVars() 
{
  global $_POST;
  foreach ($_POST as $key => $value) 
  {
    global $$key;
    $$key = $value;
  }
}

function generatePassword()
{
    // neues Passwort generieren
    $password = "";
    $password = substr(md5(time()), 0, 8);
    return $password;
}

function generateActivationId($text)
{
    $aid = "";
    $aid = substr(md5(uniqid($text.time())),0,10);
    return $aid;
}
?>