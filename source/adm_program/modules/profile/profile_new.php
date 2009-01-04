<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Dialog um neue Benutzer hinzuzufuegen.
 *            2 - Dialog um Registrierung entgegenzunehmen
 *            3 - Registrierung zuordnen/akzeptieren
 *
 *****************************************************************************/

require("../../system/common.php");
// Registrierung muss ausgeloggt moeglich sein
if(isset($_GET['new_user']) && $_GET['new_user'] != 2)
{
    require("../../system/login_valid.php");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $usr_id = $_GET["user_id"];
}
else
{
    $usr_id = 0;
}

// pruefen, ob Modus neues Mitglied oder Registrierung erfassen
if(isset($_GET['new_user']))
{
    if(is_numeric($_GET['new_user']))
    {
        $new_user = $_GET['new_user'];
    }
    else
    {
        $new_user = 0;
    }
}
else
{
    $new_user = 0;
}

if($new_user == 1 || $new_user == 2)
{
    $usr_id = 0;
}

// pruefen, ob Modul aufgerufen werden darf
if($new_user == 2)
{
    // Registrierung deaktiviert, also auch diesen Modus sperren
    if($g_preferences['registration_mode'] == 0)
    {
        $g_message->show("module_disabled");
    }
}
else
{
    // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
    if($g_current_user->editProfile($usr_id) == false)
    {
        $g_message->show("norights");
    }


}

// User auslesen
$user = new User($g_db, $usr_id);

if($new_user == 0)
{
    // jetzt noch schauen, ob User ueberhaupt Mitglied in der Gliedgemeinschaft ist
    if(isMember($usr_id) == false)
    {
        // pruefen, ob der User noch in anderen Organisationen aktiv ist
        $sql    = "SELECT *
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
                    WHERE rol_valid   = 1
                      AND rol_cat_id  = cat_id
                      AND cat_org_id <> ". $g_current_organization->getValue("org_id"). "
                      AND mem_rol_id  = rol_id
                      AND mem_begin  <= '".DATE_NOW."'
                      AND mem_end     > '".DATE_NOW."'
                      AND mem_usr_id  = $usr_id ";
        $g_db->query($sql);
        $b_other_orga = false;

        if($g_db->num_rows() > 0)
        {
            // User, der woanders noch aktiv ist, darf in dieser Orga nicht bearbeitet werden
            // falls doch eine Registrierung vorliegt, dann darf Profil angezeigt werden
            if($user->getValue("usr_valid") != 0 
            || $user->getValue("usr_reg_org_shortname") != $g_current_organization->getValue("org_shortname"))
            {
                $g_message->show("norights");
            }
        }

    }
}

$b_history = false;     // History-Funktion bereits aktiviert ja/nein
$_SESSION['navigation']->addUrl(CURRENT_URL);

if(isset($_SESSION['profile_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['profile_request']);
    
    foreach($user->userFieldData as $field)
    {
        $field_name = "usf-". $field->getValue("usf_id");
        if(isset($form_values[$field_name]))
        {
            // Datum rest einmal wieder in MySQL-Format bringen
            if($field->getValue("usf_type") == "DATE" && strlen($form_values[$field_name]) > 0)
            {
                $form_values[$field_name] = dtFormatDate($form_values[$field_name], "Y-m-d");
            }
            $user->setValue($field->getValue("usf_name"), $form_values[$field_name]);
        }
    }
    
    if(isset($form_values["usr_login_name"]))
    {
        $user->setValue("usr_login_name", $form_values["usr_login_name"]);
    }
    
    unset($_SESSION['profile_request']);
    $b_history = true;
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($field, $user, $new_user)
{
    global $g_preferences, $g_root_path, $g_current_user;
    $value    = "";
    
    // Felder sperren, falls dies so eingestellt wurde
    $readonly = "";
    if($field->getValue("usf_disabled") == 1 && $g_current_user->editUsers() == false && $new_user == 0)
    {
        if($field->getValue("usf_type") == "CHECKBOX" || $field->getValue("usf_name") == "Geschlecht")
        {
            $readonly = ' disabled="disabled" ';
        }
        else
        {
            $readonly = ' class="readonly" readonly="readonly" ';
        }
    }

    // Code fuer die einzelnen Felder zusammensetzen    
    if($field->getValue("usf_name") == "Geschlecht")
    {
        $checked_female = "";
        $checked_male   = "";
        if($field->getValue("usd_value") == 2)
        {
            $checked_female = ' checked="checked" ';
        }
        elseif($field->getValue("usd_value") == 1)
        {
            $checked_male = ' checked="checked" ';
        }
        $value = '<input type="radio" id="female" name="usf-'. $field->getValue("usf_id"). '" value="2" '.$checked_female.' '.$readonly.' />
            <label for="female"><img src="'. THEME_PATH. '/icons/female.png" title="weiblich" alt="weiblich" /></label>
            &nbsp;
            <input type="radio" id="male" name="usf-'. $field->getValue("usf_id"). '" value="1" '.$checked_male.' '.$readonly.' />
            <label for="male"><img src="'. THEME_PATH. '/icons/male.png" title="männlich" alt="männlich" /></label>';
    }
    elseif($field->getValue("usf_name") == "Land")
    {
        //Laenderliste oeffnen
        $landlist = fopen(SERVER_PATH. "/adm_program/system/staaten.txt", "r");
        $value = '
        <select size="1" id="usf-'. $field->getValue("usf_id"). '" name="usf-'. $field->getValue("usf_id"). '">
            <option value="" ';
                if(strlen($g_preferences['default_country']) == 0
                && strlen($field->getValue("usd_value")) == 0)
                {
                    $value = $value. ' selected="selected" ';
                }
            $value = $value. '></option>';
            if(strlen($g_preferences['default_country']) > 0)
            {
                $value = $value. '<option value="'. $g_preferences['default_country']. '">'. $g_preferences['default_country']. '</option>
                <option value="">--------------------------------</option>';
            }

            $land = trim(fgets($landlist));
            while (!feof($landlist))
            {
                $value = $value. '<option value="'.$land.'"';
                     if($new_user > 0 && $land == $g_preferences['default_country'])
                     {
                        $value = $value. ' selected="selected" ';
                     }
                     if(!$new_user > 0 && $land == $field->getValue("usd_value"))
                     {
                        $value = $value. ' selected="selected" ';
                     }
                $value = $value. '>'.$land.'</option>';
                $land = trim(fgets($landlist));
            }
        $value = $value. '</select>';
    }
    elseif($field->getValue("usf_type") == "CHECKBOX")
    {
        $mode = "";
        if($field->getValue("usd_value") == 1)
        {
            $mode = ' checked="checked" ';
        }
        $value = '<input type="checkbox" id="usf-'. $field->getValue("usf_id"). '" name="usf-'. $field->getValue("usf_id"). '" '.$mode.' '.$readonly.' value="1" />';
    }
    elseif($field->getValue("usf_type") == "TEXT_BIG")
    {
        $value = '<textarea name="usf-'. $field->getValue("usf_id"). '" id="usf-'. $field->getValue("usf_id"). '" '.$readonly.' style="width: 300px;" rows="2" cols="40">'. $field->getValue("usd_value"). '</textarea>';
    }
    else
    {
        if($field->getValue("usf_type") == "DATE")
        {
            $width = "80px";
            $maxlength = "10";
            if(strlen($field->getValue("usd_value")) > 0)
            {
                $field->setValue("usd_value", mysqldate('d.m.y', $field->getValue("usd_value")));
            }
        }
        elseif($field->getValue("usf_type") == "EMAIL" || $field->getValue("usf_type") == "URL")
        {
            $width     = "300px";
            $maxlength = "50";
        }
        else
        {
            $width = "200px";
            $maxlength = "50";
        }
        if($field->getValue("usf_type") == "DATE")
        {
            if($field->getValue("usf_name") == 'Geburtstag')
            {
                $value = '<script type="text/javascript">
                            var calBirthday = new CalendarPopup("calendardiv");
                            calBirthday.setCssPrefix("calendar");
                            calBirthday.showNavigationDropdowns();
                            calBirthday.setYearSelectStartOffset(90);
                            calBirthday.setYearSelectEndOffset(0);
                        </script>';
                $calObject = "calBirthday";
            }
            else
            {
                $value = '<script type="text/javascript">
                            var calDate = new CalendarPopup("calendardiv");
                            calDate.setCssPrefix("calendar");
                            calDate.showNavigationDropdowns();
                            calDate.setYearSelectStartOffset(50);
                            calDate.setYearSelectEndOffset(10);
                        </script>';
                $calObject = "calDate";
            }
            $value .= '
                    <input type="text" id="usf-'. $field->getValue("usf_id"). '" name="usf-'. $field->getValue("usf_id"). '" style="width: '.$width.';" 
                        maxlength="'.$maxlength.'" '.$readonly.' value="'. $field->getValue("usd_value"). '" '.$readonly.' />
                    <a class="iconLink" id="anchor_'. $field->getValue("usf_id"). '" href="javascript:'.$calObject.'.select(document.getElementById(\'usf-'. $field->getValue("usf_id"). '\'),\'anchor_'. $field->getValue("usf_id"). '\',\'dd.MM.yyyy\');"><img 
                    	src="'. THEME_PATH. '/icons/calendar.png" alt="Kalender anzeigen" title="Kalender anzeigen" /></a>
                    <span id="calendardiv" style="position: absolute; visibility: hidden;"></span>';
        }
        else
        {
            $value = '<input type="text" id="usf-'. $field->getValue("usf_id"). '" name="usf-'. $field->getValue("usf_id"). '" style="width: '.$width.';" maxlength="'.$maxlength.'" '.$readonly.' value="'. $field->getValue("usd_value"). '" '.$readonly.' />';
        }
    }
    
    // Icons der Messenger anzeigen
    $icon = "";
    if($field->getValue("usf_name") == 'AIM')
    {
        $icon = "aim.png";
    }
    elseif($field->getValue("usf_name") == 'Google Talk')
    {
        $icon = "google.gif";
    }
    elseif($field->getValue("usf_name") == 'ICQ')
    {
        $icon = "icq.png";
    }
    elseif($field->getValue("usf_name") == 'MSN')
    {
        $icon = "msn.png";
    }
    elseif($field->getValue("usf_name") == 'Skype')
    {
        $icon = "skype.png";
    }
    elseif($field->getValue("usf_name") == 'Yahoo')
    {
        $icon = "yahoo.png";
    }
    if(strlen($icon) > 0)
    {
        $icon = '<img src="'. THEME_PATH. '/icons/'. $icon. '" style="vertical-align: middle;" alt="'. $field->getValue("usf_name"). '" />&nbsp;';
    }
        
    // Kennzeichen fuer Pflichtfeld setzen
    $mandatory = "";
    if($field->getValue("usf_mandatory") == 1)
    {
        $mandatory = '<span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>';
    }
    
    // Fragezeichen mit Feldbeschreibung anzeigen, wenn diese hinterlegt ist
    $description = "";
    if(strlen($field->getValue("usf_description")) > 0 && $field->getValue("cat_name") != "Messenger")
    {
        $description = '<img class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title=""
            onclick="window.open(\''. $g_root_path. '/adm_program/system/msg_window.php?err_code=user_field_description&amp;err_text='. $field->getValue("usf_name"). '&amp;window=true\',\'Message\',\'width=400,height=250,left=310,top=200,scrollbars=yes\')"
            onmouseover="ajax_showTooltip(event,\''. $g_root_path. '/adm_program/system/msg_window.php?err_code=user_field_description&amp;err_text='. $field->getValue("usf_name"). '\',this);" onmouseout="ajax_hideTooltip()" />';
    }
    
    // nun den Html-Code fuer das Feld zusammensetzen
    $html = '<li>
                <dl>
                    <dt><label for="usf-'. $field->getValue("usf_id"). '">'. $icon. $field->getValue("usf_name"). ':</label></dt>
                    <dd>'. $value. $mandatory. $description. '</dd>
                </dl>
            </li>';
             
    return $html;
}

// Html-Kopf ausgeben
if($new_user == 1)
{
    $g_layout['title'] = "Neuer Benutzer";
}
elseif($new_user == 2)
{
    $g_layout['title'] = "Registrieren";
}
elseif($usr_id == $g_current_user->getValue("usr_id"))
{
    $g_layout['title'] = "Mein Profil";
}
else
{
    $g_layout['title'] = "Profil von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
}
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/thickbox/thickbox.js"></script>
	<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
    <link rel="stylesheet" href="'.THEME_PATH. '/css/thickbox.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="'.THEME_PATH.'/css/calendar.css" type="text/css" />';

require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<form action=\"$g_root_path/adm_program/modules/profile/profile_save.php?user_id=$usr_id&amp;new_user=$new_user\" method=\"post\">
<div class=\"formLayout\" id=\"edit_profile_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">"; 
        // *******************************************************************************
        // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
        // *******************************************************************************

        $category = "";
        
        foreach($user->userFieldData as $field)
        {
            $show_field = false;
            
            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            // E-Mail ist Ausnahme und muss immer angezeigt werden
            if($new_user == 2 
            && $g_preferences['registration_mode'] == 1 
            && ($field->getValue("usf_mandatory") == 1 || $field->getValue("usf_name") == "E-Mail"))
            {
                $show_field = true;
            }
            elseif($new_user == 2
            && $g_preferences['registration_mode'] == 2)
            {
                // bei der vollstaendigen Registrierung alle Felder anzeigen
                $show_field = true;
            }
            elseif($new_user != 2 
            && ($usr_id == $g_current_user->getValue("usr_id") || $g_current_user->editUsers()))
            {
                // bei fremden Profilen duerfen versteckte Felder nur berechtigten Personen angezeigt werden
                // Leiter duerfen dies nicht !!!
                $show_field = true;
            }
        
            // Kategorienwechsel den Kategorienheader anzeigen
            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($category != $field->getValue("cat_name")
            && $show_field == true)
            {
                if(strlen($category) > 0)
                {
                    // div-Container groupBoxBody und groupBox schliessen
                    echo "</ul></div></div>";
                }
                $category = $field->getValue("cat_name");

                echo "<a name=\"cat-". $field->getValue("cat_id"). "\"></a>
                <div class=\"groupBox\">
                    <div class=\"groupBoxHeadline\">". $field->getValue("cat_name"). "</div>
                    <div class=\"groupBoxBody\">
                        <ul class=\"formFieldList\">";
                        
                if($field->getValue("cat_name") == "Stammdaten")
                {
                    // bei den Stammdaten erst einmal Benutzername und Passwort anzeigen
                    if($usr_id > 0 || $new_user == 2)
                    {
                        echo "<li>
                            <dl>
                                <dt><label for=\"usr_login_name\">Benutzername:</label></dt>
                                <dd>
                                    <input type=\"text\" id=\"usr_login_name\" name=\"usr_login_name\" style=\"width: 200px;\" maxlength=\"35\" value=\"". $user->getValue("usr_login_name"). "\" ";
                                    if($g_current_user->isWebmaster() == false && $new_user == 0)
                                    {
                                        echo " class=\"readonly\" readonly=\"readonly\" ";
                                    }
                                    echo " />";
                                    if($new_user > 0)
                                    {
                                        echo "<span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"
                                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profile_login_name&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\" 
                                            onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=profile_login_name',this);\" onmouseout=\"ajax_hideTooltip()\"/>";
                                    }
                                echo "</dd>
                            </dl>
                        </li>";

                        if($new_user == 2)
                        {
                            echo "<li>
                                <dl>
                                    <dt><label for=\"usr_password\">Passwort:</label></dt>
                                    <dd>
                                        <input type=\"password\" id=\"usr_password\" name=\"usr_password\" style=\"width: 130px;\" maxlength=\"20\" />
                                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"
                                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=profile_password&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\" 
                                            onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=profile_password',this);\" onmouseout=\"ajax_hideTooltip()\" />
                                    </dd>
                                </dl>
                            </li>
                            <li>
                                <dl>
                                    <dt><label for=\"password2\">Passwort (Wdh):</label></dt>
                                    <dd>
                                        <input type=\"password\" id=\"password2\" name=\"password2\" style=\"width: 130px;\" maxlength=\"20\" />
                                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                                    </dd>
                                </dl>
                            </li>";
                        }
                        else
                        {
                            // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                            if($g_current_user->isWebmaster() || $g_current_user->getValue("usr_id") == $usr_id )
                            {
                                echo '<li>
                                    <dl>
                                        <dt><label>Passwort:</label></dt>
                                        <dd>
                                            <span class="iconTextLink">
                                                <a class="thickbox" href="password.php?usr_id='. $usr_id. '&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=350"><img 
                                                	src="'. THEME_PATH. '/icons/key.png" alt="Passwort ändern" title="Passwort ändern" /></a>
                                                <a class="thickbox" href="password.php?usr_id='. $usr_id. '&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=350">Passwort ändern</a>
                                            </span>
                                        </dd>
                                    </dl>
                                </li>';
                            }
                        }
                        echo '<li><hr /></li>';
                    }
                }
            }

            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($show_field == true)
            {
                // Html des Feldes ausgeben
                echo getFieldCode($field, $user, $new_user);
            }
        }
        
        // div-Container groupBoxBody und groupBox schliessen
        echo "</ul></div></div>";

        // User, die sich registrieren wollen, bekommen jetzt noch das Captcha praesentiert,
        // falls es in den Orgaeinstellungen aktiviert wurde...
        if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
        {
            echo "
            <ul class=\"formFieldList\">
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd><img src=\"$g_root_path/adm_program/system/classes/captcha.php?id=". time(). "\" alt=\"Captcha\" /></dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt>Bestätigungscode:</dt>
                        <dd>
                            <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\" />
                            <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help&amp;window=true','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\" 
                                onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help',this);\" onmouseout=\"ajax_hideTooltip()\" />
                        </dd>
                    </dl>
                </li>
            </ul>
            <hr />";
        }

        // Bild und Text fuer den Speichern-Button
        if($new_user == 2)
        {
            // Registrierung
            $btn_image = "email.png";
            $btn_text  = "Abschicken";
        }
        else
        {
            $btn_image = "disk.png";
            $btn_text  = "Speichern";
        }

        if($new_user == 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $user->getValue("usr_usr_id_create"));
                echo 'Angelegt von '. $user_create->getValue("Vorname"). ' '. $user_create->getValue("Nachname").
                ' am '. mysqldatetime("d.m.y h:i", $user->getValue("usr_timestamp_create"));

                if($user->getValue("usr_usr_id_change") > 0)
                {
                    $user_change = new User($g_db, $user->getValue("usr_usr_id_change"));
                    echo '<br />Zuletzt bearbeitet von '. $user_change->getValue("Vorname"). ' '. $user_change->getValue("Nachname").
                    ' am '. mysqldatetime("d.m.y h:i", $user->getValue("usr_timestamp_change"));
                }
            echo '</div>';
        }

        echo '
        <div class="formSubmit">
            <button name="speichern" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/'. $btn_image. '" alt="'. $btn_text. '" />&nbsp;'. $btn_text. '</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'. $g_root_path. '/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'. $g_root_path. '/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>

<script type="text/javascript"><!--\n';
    if($g_current_user->editUsers() || $new_user > 0)
    {
        echo 'document.getElementById(\'usf-'. $g_current_user->getProperty("Nachname", "usf_id"). '\').focus();';
    }
    else
    {
        echo 'document.getElementById(\'usf-'. $g_current_user->getProperty("Adresse", "usf_id"). '\').focus();';
    }
echo '\n--></script>';

require(THEME_SERVER_PATH. "/overall_footer.php");

?>