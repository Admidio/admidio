<?php
/******************************************************************************
 * E-Mails verschicken
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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

require("../../system/common.php");

// Pruefungen, ob die Seite regulaer aufgerufen wurde
if ($g_preferences['enable_mail_module'] != 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $g_message->show("module_disabled");
}


if ($g_valid_login && !isValidEmailAddress($g_current_user->getValue("E-Mail")))
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $g_message->addVariableContent("$g_root_path/adm_program/modules/profile/profile.php", 1, false);
    $g_message->show("profile_mail");
}


//Falls ein Rollenname uebergeben wurde muss auch der Kategoriename uebergeben werden und umgekehrt...
if ( (isset($_GET["rolle"]) && !isset($_GET["cat"])) || (!isset($_GET["rolle"]) && isset($_GET["cat"])) )
{
    $g_message->show("invalid");
}


if (isset($_GET["usr_id"]))
{
    // Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
    if (!$g_valid_login)
    {
        //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
        $g_message->show("invalid");
    }

    if (is_numeric($_GET["usr_id"]) == false)
    {
        $g_message->show("invalid");
    }

    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden    
    if((  $g_current_user->editUser() == false
       && isMember($user->getValue("usr_id")) == false)
    || strlen($user->getValue("usr_id")) == 0 )
    {
        $g_message->show("usrid_not_found");
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue("E-Mail")))
    {
        $g_message->show("usrmail_not_found");
    }

    $userEmail = $user->getValue("E-Mail");
}
elseif (isset($_GET["rol_id"]))
{
    // Falls eine rol_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    if (is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }

    if ($g_valid_login)
    {
        $sql    = "SELECT rol_mail_login, rol_name 
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                    WHERE rol_id = ". $_GET['rol_id']. "
                      AND rol_cat_id = cat_id
                      AND cat_org_id = ". $g_current_organization->getValue("org_id");
    }
    else
    {
        $sql    = "SELECT rol_mail_logout, rol_name 
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                    WHERE rol_id = ". $_GET['rol_id']. "
                      AND rol_cat_id = cat_id
                      AND cat_org_id = ". $g_current_organization->getValue("org_id");
    }
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);

    if ($row[0] != 1)
    {
        $g_message->show("invalid");
    }

    $rollenName = $row['rol_name'];
    $rollenID   = $_GET['rol_id'];
}
elseif (isset($_GET["rolle"]) && isset($_GET["cat"]))
{
    // Falls eine rolle und eine category uebergeben wurde, muss geprueft werden ob der User ueberhaupt
    // auf diese zugreifen darf
    $_GET["rolle"] = strStripTags($_GET["rolle"]);
    $_GET["cat"]   = strStripTags($_GET["cat"]);

    if ($g_valid_login)
    {
        $sql    = "SELECT rol_mail_login, rol_id
                    FROM ". TBL_ROLES. " ,". TBL_CATEGORIES. "
                   WHERE UPPER(rol_name) = UPPER('". $_GET['rolle']. "')
                   AND rol_cat_id        = cat_id
                   AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
                   AND UPPER(cat_name)   = UPPER('". $_GET['cat']. "')";
    }
    else
    {
        $sql    = "SELECT rol_mail_logout, rol_id
                    FROM ". TBL_ROLES. " ,". TBL_CATEGORIES. "
                   WHERE UPPER(rol_name) = UPPER('". $_GET['rolle']. "')
                   AND rol_cat_id        = cat_id
                   AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
                   AND UPPER(cat_name)   = UPPER('". $_GET['cat']. "')";
    }
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);

    if ($row[0] != 1)
    {
        $g_message->show("invalid");
    }

    $rollenName = $_GET['rolle'];
    $rollenID   = $row[1];
}

if (array_key_exists("subject", $_GET))
{
    $_GET["subject"] = strStripTags($_GET["subject"]);
}
else
{
    $_GET["subject"] = "";
}

if (array_key_exists("body", $_GET))
{
    $_GET["body"] = strStripTags($_GET["body"]);
}
else
{
    $_GET["body"]  = "";
}

if (!array_key_exists("kopie", $_GET) || !is_numeric($_GET["kopie"]))
{
    $_GET["kopie"] = "1";
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
    $form_values['name']         = "";
    $form_values['mailfrom']     = "";
    $form_values['subject']      = "";
    $form_values['body']         = "";
    $form_values['rol_id']       = "";
}



// Seiten fuer Zuruecknavigation merken
if(isset($_GET["usr_id"]) == false && isset($_GET["rol_id"]) == false)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
if (strlen($_GET["subject"]) > 0)
{
    $g_layout['title'] = $_GET["subject"];
}
else
{
    $g_layout['title'] = "E-Mail verschicken";
}
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Ankuendigungen\"
    href=\"$g_root_path/adm_program/modules/announcements/rss_announcements.php\" />
	";
};
require(THEME_SERVER_PATH. "/overall_header.php");
echo "
<form action=\"$g_root_path/adm_program/modules/mail/mail_send.php?";
    // usr_id wird mit GET uebergeben,
    // da keine E-Mail-Adresse von mail_send angenommen werden soll
    if (array_key_exists("usr_id", $_GET))
    {
        echo "usr_id=". $_GET['usr_id']. "&";
    }
    echo "\" method=\"post\" enctype=\"multipart/form-data\">

    <div class=\"formLayout\" id=\"write_mail_form\">
        <div class=\"formHead\">". $g_layout['title']. "</div>
        <div class=\"formBody\">
            <ul class=\"formFieldList\">
                <li>
                    <dl>
                        <dt><label for=\"rol_id\">an:</label></dt>
                        <dd>";
                            if (array_key_exists("usr_id", $_GET))
                            {
                                // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                                echo "<input type=\"text\" class=\"readonly\" readonly=\"readonly\" id=\"mailto\" name=\"mailto\" style=\"width: 350px;\" maxlength=\"50\" value=\"$userEmail\" />";
                            }
                            elseif ( array_key_exists("rol_id", $_GET) || (array_key_exists("rolle", $_GET) && array_key_exists("cat", $_GET)) )
                            {
                                // Rolle wurde uebergeben, dann E-Mails nur an diese Rolle schreiben
                                echo "
                                <select size=\"1\" id=\"rol_id\" name=\"rol_id\"><option value=\"$rollenID\" selected=\"selected\">$rollenName</option></select>";
                            }
                            else
                            {
                                // keine Uebergabe, dann alle Rollen entsprechend Login/Logout auflisten
                                echo "<select size=\"1\" id=\"rol_id\" name=\"rol_id\">";
                                if ($form_values['rol_id'] == "")
                                {
                                    echo "<option value=\"\" selected=\"selected\">- Bitte wählen -</option>";
                                }

                                if ($g_valid_login)
                                {
                                    if ($g_current_user->assignRoles())
                                    {
                                        // im eingeloggten Zustand duerfen nur Moderatoren an gelocked Rollen schreiben
                                       $sql    = "SELECT rol_name, rol_id, cat_name 
                                               FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                               WHERE rol_mail_login = 1
                                               AND rol_valid        = 1
                                               AND rol_cat_id       = cat_id
                                               AND cat_org_id       = ". $g_current_organization->getValue("org_id"). "
                                               ORDER BY cat_sequence, rol_name ";
                                    }
                                    else
                                    {
                                        // alle nicht gelocked Rollen auflisten,
                                        // an die im eingeloggten Zustand Mails versendet werden duerfen
                                       $sql    = "SELECT rol_name, rol_id, cat_name 
                                               FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                               WHERE rol_mail_login = 1
                                               AND rol_locked       = 0
                                               AND rol_valid        = 1
                                               AND rol_cat_id       = cat_id
                                               AND cat_org_id       = ". $g_current_organization->getValue("org_id"). "
                                               ORDER BY cat_sequence, rol_name ";
                                    }
                                }
                                else
                                {
                                    // alle Rollen auflisten,
                                    // an die im nicht eingeloggten Zustand Mails versendet werden duerfen
                                    $sql    = "SELECT rol_name, rol_id, cat_name 
                                               FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                                               WHERE rol_mail_logout = 1
                                               AND rol_valid         = 1
                                               AND rol_cat_id        = cat_id
                                               AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
                                               ORDER BY cat_sequence, rol_name ";
                                }
                                $result = $g_db->query($sql);
                                $act_category = "";

                                while ($row = $g_db->fetch_object($result))
                                {
                                    if($act_category != $row->cat_name)
                                    {
                                        if(strlen($act_category) > 0)
                                        {
                                            echo "</optgroup>";
                                        }
                                        echo "<optgroup label=\"$row->cat_name\">";
                                        $act_category = $row->cat_name;
                                    }
                                    echo "<option value=\"$row->rol_id\" ";
                                    if ($row->rol_id == $form_values['rol_id'])
                                    {
                                        echo "selected=\"selected\"";
                                    }
                                    echo ">$row->rol_name</option>";
                                }

                                echo "</optgroup>
                                </select>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"                       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_mail&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"  onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=rolle_mail',this)\" onmouseout=\"ajax_hideTooltip()\"/>";
                            }
                            echo "<span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
                <li>
                    <dl>
                        <dt><label for=\"name\">Name:</label></dt>
                        <dd>";
                            if ($g_current_user->getValue("usr_id") > 0)
                            {
                               echo "<input type=\"text\" class=\"readonly\" id=\"name\" name=\"name\" readonly=\"readonly\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname"). "\" />";
                            }
                            else
                            {
                               echo "<input type=\"text\" id=\"name\" name=\"name\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $form_values['name']. "\" />";
                            }
                            echo "<span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for=\"mailfrom\">E-Mail:</label></dt>
                        <dd>";
                            if ($g_current_user->getValue("usr_id") > 0)
                            {
                               echo "<input type=\"text\" class=\"readonly\" id=\"mailfrom\" name=\"mailfrom\" readonly=\"readonly\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $g_current_user->getValue("E-Mail"). "\" />";
                            }
                            else
                            {
                               echo "<input type=\"text\" id=\"mailfrom\" name=\"mailfrom\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $form_values['mailfrom']. "\" />";
                            }
                            echo "<span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <hr />
                </li>
                <li>
                    <dl>
                        <dt><label for=\"subject\">Betreff:</label></dt>
                        <dd>";
                            if (strlen($_GET['subject']) > 0)
                            {
                               echo "<input type=\"text\" class=\"readonly\" readonly=\"readonly\" id=\"subject\" name=\"subject\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $_GET['subject']. "\" />";
                            }
                            else
                            {
                               echo "<input type=\"text\" id=\"subject\" name=\"subject\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $form_values['subject']. "\" />";
                            }
                            echo "<span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        </dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for=\"body\">Nachricht:</label></dt>
                        <dd>";
                            if (strlen($form_values['body']) > 0)
                            {
                               echo "<textarea id=\"body\" name=\"body\" style=\"width: 350px;\" rows=\"10\" cols=\"45\">". $form_values['body']. "</textarea>";
                            }
                            else
                            {
                               echo "<textarea id=\"body\" name=\"body\" style=\"width: 350px;\" rows=\"10\" cols=\"45\">". $_GET['body']. "</textarea>";
                            }
                        echo "</dd>
                    </dl>
                </li>";

                // Nur eingeloggte User duerfen Attachments mit max 3MB anhaengen...
                if (($g_valid_login) && ($g_preferences['max_email_attachment_size'] > 0) && (ini_get('file_uploads') == '1'))
                {
                    // das Feld userfile wird in der Breite mit size und width gesetzt, da FF nur size benutzt und IE size zu breit macht :(
                    echo "
                    <li>
                        <dl>
                            <dt><label for=\"userfile\">Anhang:</label></dt>
                            <dd>
                                <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . ($g_preferences['max_email_attachment_size'] * 1024) . "\" />
                                <input id=\"userfile\" name=\"userfile\" size=\"35\" style=\"width: 350px;\" type=\"file\" />
                            </dd>
                        </dl>
                    </li>";
                }

                echo "
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type=\"checkbox\" id=\"kopie\" name=\"kopie\" value=\"1\" ";
                            if ($_GET['kopie'] == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " /> <label for=\"kopie\">Kopie der E-Mail an mich senden</label>
                        </dd>
                    </dl>
                </li>";

                // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
                // falls es in den Orgaeinstellungen aktiviert wurde...
                if (!$g_valid_login && $g_preferences['enable_mail_captcha'] == 1)
                {
                    echo "
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>
                                <img src=\"$g_root_path/adm_program/system/captcha_class.php?id=". time(). "\" alt=\"Captcha\" />
                            </dd>
                        </dt>
                    </li>
                    <li>
                        <dl>
                            <dt><label for=\"captcha\">Best&auml;tigungscode:</label></dt>
                            <dd>
                                <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\" />
                                <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"                       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help&amp;window=true','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help',this)\" onmouseout=\"ajax_hideTooltip()\" />
                            </dd>
                        </dl>
                    </li>";
                }
            echo "</ul>
            
            <hr />

            <div class=\"formSubmit\">
                <button name=\"abschicken\" type=\"submit\" value=\"abschicken\"><img src=\"". THEME_PATH. "/icons/email.png\" alt=\"Abschicken\" />&nbsp;Abschicken</button>
            </div>
        </div>
    </div>
</form>";

if(isset($_GET['usr_id']) || isset($_GET['rol_id']))
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>";
}

// Focus auf das erste Eingabefeld setzen
if (!isset($_GET['usr_id'])
 && !isset($_GET['rol_id'])
 && !isset($_GET['rolle']) )
{
    $focus_field = "rol_id";
}
else if($g_current_user->getValue("usr_id") == 0)
{
    $focus_field = "name";
}
else
{
    $focus_field = "subject";
}

echo "<script type=\"text/javascript\"><!--
    document.getElementById('$focus_field').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>