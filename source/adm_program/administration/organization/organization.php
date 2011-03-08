<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_text.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// der Installationsordner darf aus Sicherheitsgruenden nicht existieren
if($g_debug == 0 && file_exists('../../../adm_install'))
{
    $g_message->show($g_l10n->get('SYS_INSTALL_FOLDER_EXIST'));
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// verfuegbare Sprachen aus XML-Datei einlesen und in Array schreiben
$languages = array();
$data = implode('', file('../../languages/languages.xml'));
$p = xml_parser_create();
xml_parse_into_struct($p, $data, $vals, $index);
xml_parser_free($p);

for($i = 0; $i < count($index['ISOCODE']); $i++)
{
    $languages[$vals[$index['ISOCODE'][$i]]['value']] = $vals[$index['NAME'][$i]]['value'];
}

$html_icon_warning = '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />';

if(isset($_SESSION['organization_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['organization_request']);
    unset($_SESSION['organization_request']);
}
else
{
    foreach($g_current_organization->dbColumns as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // alle Systemeinstellungen in das form-Array schreiben
    foreach($g_preferences as $key => $value)
    {
        $form_values[$key] = $value;
    }

    // Forumpassword immer auf 0000 setzen, damit es nicht ausgelesen werden kann
    $form_values['forum_pw'] = '0000';
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = $g_l10n->get('ORG_ORGANIZATION_PROPERTIES');
$g_layout['header'] =  '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/administration/organization/organization.js" ></script>
    <script type="text/javascript"><!--
        var organizationJS = new organizationClass();
        organizationJS.ids = new Array("general", "register", "announcement-module", "download-module", "photo-module", "forum",
                    "guestbook-module", "list-module", "mail-module", "system-mail", "ecard-module", "profile-module",
                    "dates-module", "links-module", "systeminfo", "captcha");
        organizationJS.ecard_CCRecipients = "'.$form_values["ecard_cc_recipients"].'";
        organizationJS.ecard_textLength = "'.$form_values["ecard_text_length"].'";
        organizationJS.forum_Server = "'.$form_values["forum_srv"].'";
        organizationJS.forum_User = "'.$form_values["forum_usr"].'";
        organizationJS.forum_PW = "'.$form_values["forum_pw"].'";
        organizationJS.forum_DB = "'.$form_values["forum_db"].'";
        organizationJS.text_Server = "'.$g_l10n->get('SYS_SERVER').':";
        organizationJS.text_User = "'.$g_l10n->get('SYS_LOGIN').':";
        organizationJS.text_PW = "'.$g_l10n->get('SYS_PASSWORD').':";
        organizationJS.text_DB = "'.$g_l10n->get('SYS_DATABASE').':";
        $(document).ready(function()
        {
            organizationJS.init();
            organizationJS.toggleDiv(organizationJS.ids[0]);
            organizationJS.drawForumAccessDataTable();
            $("#org_longname").focus();
        });
    //--></script>';

// Html-Kopf ausgeben
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>

<div class="formLayout" id="organization_menu">
    <div class="formBody">
        <table style="border-width: 0px; width: 100%; text-align: left;">
        <tr>
        <td>
        <div id="general_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/options.png" alt="'.$g_l10n->get('SYS_COMMON').'" title="'.$g_l10n->get('SYS_COMMON').'" /></a>
            <a href="#">'.$g_l10n->get('SYS_COMMON').'</a>
        </div>
        </td>
        <td>
        <div id="register_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/new_registrations.png" alt="'.$g_l10n->get('SYS_REGISTRATION').'" title="'.$g_l10n->get('SYS_REGISTRATION').'" /></a>
            <a href="#">'.$g_l10n->get('SYS_REGISTRATION').'</a>
        </div>
        </td>
        <td>
        <div id="announcement-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/announcements.png" alt="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" title="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" /></a>
            <a href="#">'.$g_l10n->get('ANN_ANNOUNCEMENTS').'</a>
        </div>
        </td>
        <td>
        <div id="download-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/download.png" alt="'.$g_l10n->get('DOW_DOWNLOADS').'" title="'.$g_l10n->get('DOW_DOWNLOADS').'" /></a>
            <a href="#">'.$g_l10n->get('DOW_DOWNLOADS').'</a>
        </div>
        </td>
        <td>
        <div id="forum_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/forum.png" alt="'.$g_l10n->get('SYS_FORUM').'" title="'.$g_l10n->get('SYS_FORUM').'" /></a>
            <a href="#">'.$g_l10n->get('SYS_FORUM').'</a>
        </div>
        </td>
        </tr>
        <tr>
        <td>
        <div id="photo-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/photo.png" alt="'.$g_l10n->get('PHO_PHOTOS').'" title="'.$g_l10n->get('PHO_PHOTOS').'" /></a>
            <a href="#">'.$g_l10n->get('PHO_PHOTOS').'</a>
        </div>
        </td>
        <td>
        <div id="ecard-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/ecard.png" alt="'.$g_l10n->get('ECA_GREETING_CARDS').'" title="'.$g_l10n->get('ECA_GREETING_CARDS').'" /></a>
            <a href="#">'.$g_l10n->get('ECA_GREETING_CARDS').'</a>
        </div>
        </td>
        <td>
        <div id="guestbook-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/guestbook.png" alt="'.$g_l10n->get('GBO_GUESTBOOK').'" title="'.$g_l10n->get('GBO_GUESTBOOK').'" /></a>
            <a href="#">'.$g_l10n->get('GBO_GUESTBOOK').'</a>
        </div>
        </td>
        <td>
        <div id="mail-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/email.png" alt="'.$g_l10n->get('MAI_EMAILS').'" title="'.$g_l10n->get('MAI_EMAILS').'" /></a>
            <a href="#">'.$g_l10n->get('MAI_EMAILS').'</a>
        </div>
        </td>
        <td>
        <div id="system-mail_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/system_mail.png" alt="'.$g_l10n->get('SYS_SYSTEM_MAILS').'" title="'.$g_l10n->get('SYS_SYSTEM_MAILS').'" /></a>
            <a href="#">'.$g_l10n->get('SYS_SYSTEM_MAILS').'</a>
        </div>
        </td>
        </tr>
        <tr>
        <td>
        <div id="list-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/list.png" alt="'.$g_l10n->get('LST_LISTS').'" title="'.$g_l10n->get('LST_LISTS').'" /></a>
            <a href="#">'.$g_l10n->get('LST_LISTS').'</a>
        </div>
        </td>
        <td>
        <div id="profile-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/profile.png" alt="'.$g_l10n->get('PRO_PROFILE').'" title="'.$g_l10n->get('PRO_PROFILE').'" /></a>
            <a href="#">'.$g_l10n->get('PRO_PROFILE').'</a>
        </div>
        </td>
        <td>
        <div id="dates-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/dates.png" alt="'.$g_l10n->get('DAT_DATES').'" title="'.$g_l10n->get('DAT_DATES').'" /></a>
            <a href="#">'.$g_l10n->get('DAT_DATES').'</a>
        </div>
        </td>
        <td>
        <div id="links-module_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/weblinks.png" alt="'.$g_l10n->get('LNK_WEBLINKS').'" title="'.$g_l10n->get('LNK_WEBLINKS').'" /></a>
            <a href="#">'.$g_l10n->get('LNK_WEBLINKS').'</a>
        </div>
        </td>
        <td>
        <div id="systeminfo_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/info.png" alt="'.$g_l10n->get('ORG_SYSTEM_INFOS').'" title="'.$g_l10n->get('ORG_SYSTEM_INFOS').'" /></a>
            <a href="#">'.$g_l10n->get('ORG_SYSTEM_INFOS').'</a>
        </div>
        </td>
        </tr>
		<tr>
        <td>
        <div id="captcha_link" class="iconTextLink">
            <a href="#"><img src="'.THEME_PATH.'/icons/captcha.png" alt="'.$g_l10n->get('SYS_CAPTCHA').'" title="'.$g_l10n->get('SYS_CAPTCHA').'" /></a>
            <a href="#">'.$g_l10n->get('SYS_CAPTCHA').'</a>
        </div>
        </td>
        </tr>		
        </table>
    </div>
</div>

<form action="'.$g_root_path.'/adm_program/administration/organization/organization_function.php" method="post">
<div class="formLayout" id="organization_form">
    <div class="formBody">
        <div class="groupBox" id="general">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/options.png" alt="common" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('SYS_COMMON').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="org_shortname">'.$g_l10n->get('SYS_NAME_ABBREVIATION').':</label></dt>
                            <dd><input type="text" id="org_shortname" name="org_shortname" readonly="readonly" style="width: 100px;" maxlength="10" value="'. $form_values['org_shortname']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="org_longname">'.$g_l10n->get('SYS_NAME').':</label></dt>
                            <dd><input type="text" id="org_longname" name="org_longname" style="width: 200px;" maxlength="60" value="'. $form_values['org_longname']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="org_homepage">'.$g_l10n->get('SYS_WEBSITE').':</label></dt>
                            <dd><input type="text" id="org_homepage" name="org_homepage" style="width: 200px;" maxlength="60" value="'. $form_values['org_homepage']. '" /></dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="system_language">'.$g_l10n->get('SYS_LANGUAGE').':</label></dt>
                            <dd>
                                <select size="1" id="system_language" name="system_language">
                                    <option value="">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';
                                    foreach($languages as $key => $value)
                                    {
                                        echo '<option value="'.$key.'" ';
                                        if($key == $form_values['system_language'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$value.'</option>';
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="theme">'.$g_l10n->get('ORG_ADMIDIO_THEME').':</label></dt>
                            <dd>
                                <select size="1" id="theme" name="theme">
                                    <option value="">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';
                                    $themes_path = SERVER_PATH. '/adm_themes';
                                    $dir_handle  = opendir($themes_path);

                                    while (false !== ($filename = readdir($dir_handle)))
                                    {
                                        if(is_file($filename) == false
                                        && strpos($filename, '.') !== 0)
                                        {
                                            echo '<option value="'.$filename.'" ';
                                            if($form_values['theme'] == $filename)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$filename.'</option>';
                                        }
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ADMIDIO_THEME_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="system_date">'.$g_l10n->get('ORG_DATE_FORMAT').':</label></dt>
                            <dd><input type="text" id="system_date" name="system_date" style="width: 100px;" maxlength="20" value="'. $form_values['system_date']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_DATE_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>').'</li>
                    <li>
                        <dl>
                            <dt><label for="system_time">'.$g_l10n->get('ORG_TIME_FORMAT').':</label></dt>
                            <dd><input type="text" id="system_time" name="system_time" style="width: 100px;" maxlength="20" value="'. $form_values['system_time']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_TIME_FORMAT_DESC', '<a href="http://www.php.net/date">date()</a>').'</li>
                    <li>
                        <dl>
                            <dt><label for="system_time">'.$g_l10n->get('ORG_CURRENCY').':</label></dt>
                            <dd><input type="text" id="system_currency" name="system_currency" style="width: 100px;" maxlength="20" value="'. $form_values['system_currency']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_CURRENCY_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="homepage_logout">'.$g_l10n->get('SYS_HOMEPAGE').' ('.$g_l10n->get('SYS_VISITORS').'):</label></dt>
                            <dd><input type="text" id="homepage_logout" name="homepage_logout" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_logout']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_HOMEPAGE_VISITORS').'</li>
                    <li>
                        <dl>
                            <dt><label for="homepage_login">'.$g_l10n->get('SYS_HOMEPAGE').' ('.$g_l10n->get('ORG_REGISTERED_USERS').'):</label></dt>
                            <dd><input type="text" id="homepage_login" name="homepage_login" style="width: 200px;" maxlength="250" value="'. $form_values['homepage_login']. '" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_HOMEPAGE_REGISTERED_USERS').'</li>';

                    //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                    if($g_current_organization->hasChildOrganizations() == false)
                    {
                        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                                 WHERE org_id <> ". $g_current_organization->getValue("org_id"). "
                                   AND org_org_id_parent is NULL
                                 ORDER BY org_longname ASC, org_shortname ASC ";
                        $result = $g_db->query($sql);

                        if($g_db->num_rows($result) > 0)
                        {
                            // Auswahlfeld fuer die uebergeordnete Organisation
                            echo '
                            <li>
                                <dl>
                                    <dt><label for="org_org_id_parent">'.$g_l10n->get('ORG_PARENT_ORGANIZATION').':</label></dt>
                                    <dd>
                                        <select size="1" id="org_org_id_parent" name="org_org_id_parent">
                                            <option value="0" ';
                                            if(strlen($form_values['org_org_id_parent']) == 0)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>keine</option>';

                                            while($row = $g_db->fetch_object($result))
                                            {
                                                echo '<option value="'.$row->org_id.'" ';
                                                    if($form_values['org_org_id_parent'] == $row->org_id)
                                                    {
                                                        echo ' selected="selected" ';
                                                    }
                                                    echo '>'.$row->org_shortname.'</option>';
                                            }
                                        echo '</select>
                                    </dd>
                                </dl>
                            </li>
                            <li class="smallFontSize">'.$g_l10n->get('ORG_PARENT_ORGANIZATION_DESC').'</li>';
                        }
                    }

                    echo '
                    <li>
                        <dl>
                            <dt><label for="enable_bbcode">'.$g_l10n->get('ORG_ALLOW_BBCODE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_bbcode" name="enable_bbcode" ';
                                if(isset($form_values['enable_bbcode']) && $form_values['enable_bbcode'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ALLOW_BBCODE_DESC', '<a href="http://de.wikipedia.org/wiki/BBCode">BB-Code</a>').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_rss">'.$g_l10n->get('ORG_ENABLE_RSS_FEEDS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_rss" name="enable_rss" ';
                                if(isset($form_values['enable_rss']) && $form_values['enable_rss'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ENABLE_RSS_FEEDS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_auto_login">'.$g_l10n->get('ORG_LOGIN_AUTOMATICALLY').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_auto_login" name="enable_auto_login" ';
                                if(isset($form_values['enable_auto_login']) && $form_values['enable_auto_login'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_LOGIN_AUTOMATICALLY_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="logout_minutes">'.$g_l10n->get('ORG_AUTOMATOC_LOGOUT_AFTER').':</label></dt>
                            <dd><input type="text" id="logout_minutes" name="logout_minutes" style="width: 50px;" maxlength="4" value="'. $form_values['logout_minutes']. '" /> '.$g_l10n->get('SYS_MINUTES').'</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_AUTOMATOC_LOGOUT_AFTER_DESC', $g_l10n->get('SYS_REMEMBER_ME')).'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_password_recovery">'.$g_l10n->get('ORG_SEND_PASSWORD').':</label>
                            </dt>
                            <dd>
                                <input type="checkbox" id="enable_password_recovery" name="enable_password_recovery" ';
                                if(isset($form_values['enable_password_recovery']) && $form_values['enable_password_recovery'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_SEND_PASSWORD_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="system_search_similar">'.$g_l10n->get('ORG_SEARCH_SIMILAR_NAMES').':</label>
                            </dt>
                            <dd>
                                <input type="checkbox" id="system_search_similar" name="system_search_similar" ';
                                if(isset($form_values['system_search_similar']) && $form_values['system_search_similar'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_SEARCH_SIMILAR_NAMES_DESC').'</li>
                </ul>
            </div>
        </div>';



        /**************************************************************************************/
        // Einstellungen Registrierung
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="register">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/new_registrations.png" alt="registration" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('SYS_REGISTRATION').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="registration_mode">'.$g_l10n->get('SYS_REGISTRATION').':</label></dt>
                            <dd>
                                <select size="1" id="registration_mode" name="registration_mode">
                                    <option value="0" ';
                                    if($form_values['registration_mode'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['registration_mode'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_FAST_REGISTRATION').'</option>
                                    <option value="2" ';
                                    if($form_values['registration_mode'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ADVANCED_REGISTRATION').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_REGISTRATION_MODE').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_registration_captcha">'.$g_l10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_registration_captcha" name="enable_registration_captcha" ';
                                if(isset($form_values['enable_registration_captcha']) && $form_values['enable_registration_captcha'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_CAPTCHA_REGISTRATION').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_registration_admin_mail">'.$g_l10n->get('ORG_EMAIL_ALERTS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_registration_admin_mail" name="enable_registration_admin_mail" ';
                                if(isset($form_values['enable_registration_admin_mail']) && $form_values['enable_registration_admin_mail'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_EMAIL_ALERTS', $g_l10n->get('ROL_RIGHT_APPROVE_USERS')).'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Ankuendigungsmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="announcement-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/announcements.png" alt="announcements" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('ANN_ANNOUNCEMENTS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_announcements_module">'.$g_l10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>
                                <select size="1" id="enable_announcements_module" name="enable_announcements_module">
                                    <option value="0" ';
                                    if($form_values['enable_announcements_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_announcements_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ACTIVATED').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_announcements_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ONLY_FOR_REGISTERED_USER').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="announcements_per_page">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="announcements_per_page" name="announcements_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['announcements_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Downloadmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="download-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/download.png" alt="downloads" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('DOW_DOWNLOADS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_download_module">'.$g_l10n->get('DOW_ENABLE_DOWNLOAD_MODULE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_download_module" name="enable_download_module" ';
                                if(isset($form_values['enable_download_module']) && $form_values['enable_download_module'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DOW_ENABLE_DOWNLOAD_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="max_file_upload_size">'.$g_l10n->get('DOW_MAXIMUM_FILE_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="max_file_upload_size" name="max_file_upload_size" style="width: 50px;"
                                    maxlength="10" value="'. $form_values['max_file_upload_size']. '" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DOW_MAXIMUM_FILE_SIZE_DESC').'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Fotomodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="photo-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/photo.png" alt="photos" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('PHO_PHOTOS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_photo_module">'.$g_l10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>
                                <select size="1" id="enable_photo_module" name="enable_photo_module">
                                    <option value="0" ';
                                    if($form_values['enable_photo_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_photo_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ACTIVATED').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_photo_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ONLY_FOR_REGISTERED_USER').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_show_mode">'.$g_l10n->get('PHO_DISPLAY_PHOTOS').':</label></dt>
                            <dd>
                                <select size="1" id="photo_show_mode" name="photo_show_mode">
                                    <option value="0" ';
                                    if($form_values['photo_show_mode'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('PHO_POPUP_WINDOW').'</option>
                                    <option value="1" ';
                                    if($form_values['photo_show_mode'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('PHO_COLORBOX').'</option>
                                    <option value="2" ';
                                    if($form_values['photo_show_mode'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('PHO_SAME_WINDOW').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_DISPLAY_PHOTOS_DESC').'</li>
                     <li>
                        <dl>
                            <dt><label for="photo_upload_mode">'.$g_l10n->get('PHO_MULTIUPLOAD').':</label></dt>
                            <dd>
                                <input type="checkbox" id="photo_upload_mode" name="photo_upload_mode" ';
                                if(isset($form_values['photo_upload_mode']) && $form_values['photo_upload_mode'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_MULTIUPLOAD_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_thumbs_row">'.$g_l10n->get('PHO_THUMBNAILS_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="photo_thumbs_column" name="photo_thumbs_column" style="width: 50px;" maxlength="2" value="'. $form_values['photo_thumbs_column']. '" /> x
                                <input type="text" id="photo_thumbs_row" name="photo_thumbs_row" style="width: 50px;" maxlength="2" value="'. $form_values['photo_thumbs_row']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_THUMBNAILS_PER_PAGE_DESC').'</li>

                    <li>
                        <dl>
                            <dt><label for="photo_thumbs_scale">'.$g_l10n->get('PHO_SCALE_THUMBNAILS').':</label></dt>
                            <dd>
                                <input type="text" id="photo_thumbs_scale" name="photo_thumbs_scale" style="width: 50px;" maxlength="4" value="'. $form_values['photo_thumbs_scale']. '" /> '.$g_l10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_SCALE_THUMBNAILS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_save_scale">'.$g_l10n->get('PHO_SCALE_AT_UPLOAD').':</label></dt>
                            <dd>
                                <input type="text" id="photo_save_scale" name="photo_save_scale" style="width: 50px;" maxlength="4" value="'. $form_values['photo_save_scale']. '" /> '.$g_l10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_SCALE_AT_UPLOAD_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_show_width">'.$g_l10n->get('PHO_MAX_PHOTO_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="photo_show_width" name="photo_show_width" style="width: 50px;" maxlength="4" value="'. $form_values['photo_show_width']. '" /> x
                                <input type="text" id="photo_show_height" name="photo_show_height" style="width: 50px;" maxlength="4" value="'. $form_values['photo_show_height']. '" /> '.$g_l10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_MAX_PHOTO_SIZE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="photo_image_text">'.$g_l10n->get('PHO_SHOW_CAPTION').':</label></dt>
                            <dd>
                                <input type="text" id="photo_image_text" name="photo_image_text" maxlength="60" value="'.$form_values['photo_image_text']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PHO_SHOW_CAPTION_DESC' ,$g_current_organization->getValue('org_homepage')).'</li>
                </ul>
            </div>
        </div>';

        /**************************************************************************************/
        //Einstellungen Forum
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="forum">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/forum.png" alt="forum" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('SYS_FORUM').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_forum_interface">'.$g_l10n->get('ORG_ACTIVATE_FORUM').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_forum_interface" name="enable_forum_interface" ';
                                if(isset($form_values['enable_forum_interface']) && $form_values['enable_forum_interface'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACTIVATE_FORUM_DESC', $html_icon_warning).'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_version">'.$g_l10n->get('ORG_USED_FORUM').':</label></dt>
                            <dd>
                                <select size="1" id="forum_version" name="forum_version">
                                    <option value="phpBB2" ';
                                    if($form_values['forum_version'] == 'phpBB2')
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>phpBB2</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_WHICH_FORUM_USED').'<br/>
                        <table summary="Forum_Auflistung" border="0">
                            <tr><td>1) "phpbb2"</td><td> - PHP Bulletin Board 2.x (Standard)</td></tr>
                        </table>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_link_intern">'.$g_l10n->get('ORG_ACTIVATE_FORUM_LINK_INTERN').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_link_intern" name="forum_link_intern" ';
                                if(isset($form_values['forum_link_intern']) && $form_values['forum_link_intern'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACTIVATE_FORUM_LINK_INTERN_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_width">'.$g_l10n->get('ORG_FORUM_WIDTH').':</label></dt>
                            <dd>
                                <input type="text" id="forum_width" name="forum_width" maxlength="4" style="width: 50px;" value="'. $form_values['forum_width']. '" /> '.$g_l10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_FORUM_WIDTH_DESC', $html_icon_warning).'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_export_user">'.$g_l10n->get('ORG_EXPORT_ADMIDIO_USER').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_export_user" name="forum_export_user" ';
                                if(isset($form_values['forum_export_user']) && $form_values['forum_export_user'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_EXPORT_ADMIDIO_USER_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_set_admin">'.$g_l10n->get('ORG_EXPORT_WEBMASTER_STATUS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_set_admin" name="forum_set_admin" ';
                                if(isset($form_values['forum_set_admin']) && $form_values['forum_set_admin'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_EXPORT_WEBMASTER_STATUS_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="forum_praefix">'.$g_l10n->get('ORG_FORUM_TABLE_PREFIX').':</label></dt>
                            <dd>
                                <input type="text" id="forum_praefix" name="forum_praefix" style="width: 50px;" value="'. $form_values['forum_praefix']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_FORUM_TABLE_PREFIX_DESC').'</li>
                    <li>
                        <dl>
                            <dt><strong>'.$g_l10n->get('ORG_ACCESS_FORUM_DATABASE').'</strong></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="forum_sqldata_from_admidio">'.$g_l10n->get('ORG_ACCESS_DATA_ADMIDIO').':</label></dt>
                            <dd>
                                <input type="checkbox" id="forum_sqldata_from_admidio" name="forum_sqldata_from_admidio" onclick="javascript:organizationJS.drawForumAccessDataTable();" ';
                                if(isset($form_values['forum_sqldata_from_admidio']) && $form_values['forum_sqldata_from_admidio'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_DATA_ADMIDIO_DESC').'</li>
                    <li id="forum_access_data"></li>
                    <li id="forum_access_data_text" class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_FORUM_DATABASE_DESC').'</li>
                </ul>
            </div>
        </div>';

        /**************************************************************************************/
        //Einstellungen Gaestebuchmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="guestbook-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/guestbook.png" alt="guestbook" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('GBO_GUESTBOOK').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_module">'.$g_l10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>
                                <select size="1" id="enable_guestbook_module" name="enable_guestbook_module">
                                    <option value="0" ';
                                    if($form_values['enable_guestbook_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_guestbook_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ACTIVATED').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_guestbook_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ONLY_FOR_REGISTERED_USER').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="guestbook_entries_per_page">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="guestbook_entries_per_page" name="guestbook_entries_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['guestbook_entries_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_captcha">'.$g_l10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_guestbook_captcha" name="enable_guestbook_captcha" ';
                                if(isset($form_values['enable_guestbook_captcha']) && $form_values['enable_guestbook_captcha'] == 1)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('GBO_CAPTCHA_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_guestbook_moderation">'.$g_l10n->get('GBO_GUESTBOOK_MODERATION').':</label></dt>
                            <dd>
                                <select size="1" id="enable_guestbook_moderation" name="enable_guestbook_moderation">
                                    <option value="0" ';
                                    if($form_values['enable_guestbook_moderation'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_NOBODY').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_guestbook_moderation'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('GBO_ONLY_VISITORS').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_guestbook_moderation'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ALL').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('GBO_GUESTBOOK_MODERATION_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_gbook_comments4all">'.$g_l10n->get('GBO_COMMENTS4ALL').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_gbook_comments4all" name="enable_gbook_comments4all" ';
                                if(isset($form_values['enable_gbook_comments4all']) && $form_values['enable_gbook_comments4all'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('GBO_COMMENTS4ALL_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_intial_comments_loading">'.$g_l10n->get('GBO_INITIAL_COMMENTS_LOADING').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_intial_comments_loading" name="enable_intial_comments_loading" ';
                                if(isset($form_values['enable_intial_comments_loading']) && $form_values['enable_intial_comments_loading'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('GBO_INITIAL_COMMENTS_LOADING_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="flooding_protection_time">'.$g_l10n->get('GBO_FLOODING_PROTECTION_INTERVALL').':</label></dt>
                            <dd>
                                <input type="text" id="flooding_protection_time" name="flooding_protection_time" style="width: 50px;" 
                                    maxlength="4" value="'. $form_values['flooding_protection_time']. '" /> '.$g_l10n->get('SYS_SECONDS').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('GBO_FLOODING_PROTECTION_INTERVALL_DESC').'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Listenmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="list-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/list.png" alt="lists" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('LST_LISTS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="lists_roles_per_page">'.$g_l10n->get('LST_NUMBER_OF_ROLES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="lists_roles_per_page" name="lists_roles_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['lists_roles_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="lists_members_per_page">'.$g_l10n->get('LST_MEMBERS_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="lists_members_per_page" name="lists_members_per_page" style="width: 50px;"
                                    maxlength="4" value="'. $form_values['lists_members_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('LST_MEMBERS_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="lists_hide_overview_details">'.$g_l10n->get('LST_HIDE_DETAILS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="lists_hide_overview_details" name="lists_hide_overview_details" ';
                                if(isset($form_values['lists_hide_overview_details']) && $form_values['lists_hide_overview_details'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('LST_HIDE_DETAILS_DESC').'</li>                 
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Mailmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="mail-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/email.png" alt="E-Mails" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('MAI_EMAILS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_mail_module">'.$g_l10n->get('MAI_ACTIVATE_EMAIL_MODULE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_mail_module" name="enable_mail_module" ';
                                if(isset($form_values['enable_mail_module']) && $form_values['enable_mail_module'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('MAI_ACTIVATE_EMAIL_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="mail_bcc_count">'.$g_l10n->get('MAI_COUNT_BCC').':</label>
                            </dt>
                            <dd>
                                <input type="text" id="mail_bcc_count" name="mail_bcc_count" style="width: 50px;" maxlength="6" value="'. $form_values['mail_bcc_count']. '" />
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('MAI_COUNT_BCC_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="enable_mail_captcha">'.$g_l10n->get('ORG_ENABLE_CAPTCHA').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_mail_captcha" name="enable_mail_captcha" ';
                                if(isset($form_values['enable_mail_captcha']) && $form_values['enable_mail_captcha'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('MAI_SHOW_CAPTCHA_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="max_email_attachment_size">'.$g_l10n->get('MAI_ATTACHMENT_SIZE').':</label></dt>
                            <dd>
                                <input type="text" id="max_email_attachment_size" name="max_email_attachment_size" style="width: 50px;" maxlength="6" value="'.$form_values['max_email_attachment_size'].'" /> KB
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('MAI_ATTACHMENT_SIZE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="mail_sendmail_address">'.$g_l10n->get('MAI_SENDER_EMAIL').':</label></dt>
                            <dd><input type="text" id="mail_sendmail_address" name="mail_sendmail_address" style="width: 200px;" maxlength="50" value="'. $form_values['mail_sendmail_address'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('MAI_SENDER_EMAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']).'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Systemmails
        /**************************************************************************************/

        $text = new TableText($g_db);
        echo '
        <div class="groupBox" id="system-mail">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/system_mail.png" alt="system mails" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('SYS_SYSTEM_MAILS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_system_mails">'.$g_l10n->get('ORG_ACTIVATE_SYSTEM_MAILS').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_system_mails" name="enable_system_mails" ';
                                if(isset($form_values['enable_system_mails']) && $form_values['enable_system_mails'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACTIVATE_SYSTEM_MAILS_DESC').'</li>                  
                    <li>
                        <dl>
                            <dt><label for="email_administrator">'.$g_l10n->get('ORG_SYSTEM_MAIL_ADDRESS').':</label></dt>
                            <dd><input type="text" id="email_administrator" name="email_administrator" style="width: 200px;" maxlength="50" value="'. $form_values['email_administrator'].'" /></dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_SYSTEM_MAIL_ADDRESS_DESC', $_SERVER['HTTP_HOST']).'</li>
                    <li>
                        <dl>
                            <dt><label>'.$g_l10n->get('SYS_EMAIL_NOTIFICATION_ACTIVATE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_email_notification" name="enable_email_notification" ';
                                if(isset($form_values['enable_email_notification']) && $form_values['enable_email_notification'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('SYS_EMAIL_NOTIFICATION_DESCRIPTION', '<i>'.$g_preferences['email_administrator'].'</i>').'</li>
                    <li>
                        <dl>
                            <dt><label>'.$g_l10n->get('ORG_SYSTEM_MAIL_TEXTS').':</label></dt>
                            <dd><br /></dd>
                        </dl>
                    </li>
                    <li  class="smallFontSize">'.$g_l10n->get('ORG_SYSTEM_MAIL_TEXTS_DESC').':<br />
                        <strong>%user_first_name%</strong> - '.$g_l10n->get('ORG_VARIABLE_FIRST_NAME').'<br />
                        <strong>%user_last_name%</strong> - '.$g_l10n->get('ORG_VARIABLE_LAST_NAME').'<br />
                        <strong>%user_login_name%</strong> - '.$g_l10n->get('ORG_VARIABLE_USERNAME').'<br />
                        <strong>%user_email%</strong> - '.$g_l10n->get('ORG_VARIABLE_EMAIL').'<br />
                        <strong>%webmaster_email%</strong> - '.$g_l10n->get('ORG_VARIABLE_EMAIL_ORGANIZATION').'<br />
                        <strong>%organization_short_name%</strong> - '.$g_l10n->get('ORG_VARIABLE_SHORTNAME_ORGANIZATION').'<br />
                        <strong>%organization_long_name%</strong> - '.$g_l10n->get('ORG_VARIABLE_NAME_ORGANIZATION').'<br />
                        <strong>%organization_homepage%</strong> - '.$g_l10n->get('ORG_VARIABLE_URL_ORGANIZATION').'<br /><br />
                    </li>';

                    $text->readData("SYSMAIL_REGISTRATION_USER");
                    echo '<li>
                        '.$g_l10n->get('ORG_CONFIRM_REGISTRATION').':<br />
                        <textarea id="SYSMAIL_REGISTRATION_USER" name="SYSMAIL_REGISTRATION_USER" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_REGISTRATION_WEBMASTER");
                    echo '<li>
                        <br />'.$g_l10n->get('ORG_NOTIFY_WEBMASTER').':<br />
                        <textarea id="SYSMAIL_REGISTRATION_WEBMASTER" name="SYSMAIL_REGISTRATION_WEBMASTER" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_NEW_PASSWORD");
                    echo '<li>
                        <br />'.$g_l10n->get('ORG_SEND_NEW_PASSWORD').':<br />
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                        <strong>%variable1%</strong> - '.$g_l10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_NEW_PASSWORD" name="SYSMAIL_NEW_PASSWORD" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>';
                    $text->readData("SYSMAIL_ACTIVATION_LINK");
                    echo '<li>
                        <br />'.$g_l10n->get('ORG_NEW_PASSWORD_ACTIVATION_LINK').':<br />
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get('ORG_ADDITIONAL_VARIABLES').':<br />
                        <strong>%variable1%</strong> - '.$g_l10n->get('ORG_VARIABLE_NEW_PASSWORD').'<br />
                        <strong>%variable2%</strong> - '.$g_l10n->get('ORG_VARIABLE_ACTIVATION_LINK').'<br />
                    </li>
                    <li>
                        <textarea id="SYSMAIL_ACTIVATION_LINK" name="SYSMAIL_ACTIVATION_LINK" style="width: 100%;" rows="7" cols="40">'.$text->getValue('txt_text').'</textarea>
                    </li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Grußkartenmodul
        /**************************************************************************************/
        echo '
        <div class="groupBox" id="ecard-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/ecard.png" alt="greeting cards" /> 
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('ECA_GREETING_CARDS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_ecard_module">'.$g_l10n->get("ECA_ACTIVATE_GREETING_CARDS").':</label></dt>
                            <dd>
                                <input type="checkbox" id="enable_ecard_module" name="enable_ecard_module" ';
                                if(isset($form_values["enable_ecard_module"]) && $form_values["enable_ecard_module"] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
						'.$g_l10n->get("ECA_ACTIVATE_GREETING_CARDS_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_view_width">'.$g_l10n->get("ECA_SCALING_PREVIEW").':</label></dt>
                            <dd><input type="text" id="ecard_view_width" name="ecard_view_width" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_view_width"].'" />
                                x
                                <input type="text" id="ecard_view_height" name="ecard_view_height" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_view_height"].'" />
                                '.$g_l10n->get('ORG_PIXEL').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_SCALING_PREVIEW_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_card_picture_width">'.$g_l10n->get("ECA_SCALING_GREETING_CARD").':</label></dt>
                            <dd><input type="text" id="ecard_card_picture_width" name="ecard_card_picture_width" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_card_picture_width"].'" />
                                x
                                <input type="text" id="ecard_card_picture_height" name="ecard_card_picture_height" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_card_picture_height"].'" />
                                '.$g_l10n->get('ORG_PIXEL').'
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$g_l10n->get("ECA_SCALING_GREETING_CARD_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_cc_recipients">'.$g_l10n->get("ECA_MAX_CC").':</label>
                            </dt>
                            <dd>
                            <select size="1" id="enable_ecard_cc_recipients" name="enable_ecard_cc_recipients" style="margin-right:20px;" onchange="javascript:organizationJS.showHideMoreSettings(\'cc_recipients_count\',\'enable_ecard_cc_recipients\',\'ecard_cc_recipients\',0);">
                                    <option value="0" ';
                                    if($form_values["enable_ecard_cc_recipients"] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get("SYS_DEACTIVATED").'</option>
                                    <option value="1" ';
                                    if($form_values["enable_ecard_cc_recipients"] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get("SYS_ACTIVATED").'</option>
                                </select>
                                <div id="cc_recipients_count" style="display:inline;">';
                                if($form_values["enable_ecard_cc_recipients"] == 1)
                                {
                                echo '<input type="text" id="ecard_cc_recipients" name="ecard_cc_recipients" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_cc_recipients"].'" />';
                                }
                            echo '</div>
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_MAX_CC_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_text_length">'.$g_l10n->get("ECA_MAX_MESSAGE_LENGTH").':</label></dt>
                            <dd>
                             <select size="1" id="enable_ecard_text_length" name="enable_ecard_text_length" style="margin-right:20px;" onchange="javascript:organizationJS.showHideMoreSettings(\'text_length_count\',\'enable_ecard_text_length\',\'ecard_text_length\',1);">
                                    <option value="0" ';
                                    if($form_values["enable_ecard_text_length"] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get("SYS_DEACTIVATED").'</option>
                                    <option value="1" ';
                                    if($form_values["enable_ecard_text_length"] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get("SYS_ACTIVATED").'</option>
                                </select>
                                <div id="text_length_count" style="display:inline;">';
                                if($form_values["enable_ecard_text_length"] == 1)
                                {
                               		echo '<input type="text" id="ecard_text_length" name="ecard_text_length" style="width: 50px;" maxlength="4" value="'.$form_values["ecard_text_length"].'" />';
                                }
                            	echo '</div>
                             </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_MAX_MESSAGE_LENGTH_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_text_length">'.$g_l10n->get("ECA_TEMPLATE").':</label></dt>
                            <dd>';
                                echo getMenueSettings(getfilenames(THEME_SERVER_PATH.'/ecard_templates'),'ecard_template',$form_values["ecard_template"],'180','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_TEMPLATE_DESC").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_text_length">'.$g_l10n->get("SYS_FONT").':</label></dt>
                            <dd>';
                                echo getMenueSettings(getElementsFromFile('../../system/schriftarten.txt'),'ecard_text_font',$form_values["ecard_text_font"],'120','true','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_FONT").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_text_length">'.$g_l10n->get("SYS_FONT_SIZE").':</label></dt>
                            <dd>';
                                echo getMenueSettings(array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"),'ecard_text_size',$form_values["ecard_text_size"],'120','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$g_l10n->get("ECA_FONT_SIZE").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="ecard_text_color">'.$g_l10n->get("SYS_FONT_COLOR").':</label></dt>
                            <dd>';
                             echo getMenueSettings(getElementsFromFile('../../system/schriftfarben.txt'),'ecard_text_color',$form_values["ecard_text_color"],'120','false','true');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ECA_FONT_COLOR").'
                    </li>

                </ul>
            </div>
        </div>';
        function getfilenames($directory)
        {
            $array_files    = array();
            $i                = 0;
            if($curdir = opendir($directory))
            {
                while($file = readdir($curdir))
                {
                    if($file != '.' && $file != '..')
                    {
                        $array_files[$i] = $file;
                        $i++;
                    }
                }
            }
            closedir($curdir);
            return $array_files;
        }

        // oeffnet ein File und gibt alle Zeilen als Array zurueck
        // Uebergabe:
        //            $filepath .. Der Pfad zu dem File
        function getElementsFromFile($filepath)
        {
            $elementsFromFile = array();
            $list = fopen($filepath, "r");
            while (!feof($list))
            {
                array_push($elementsFromFile,trim(fgets($list)));
            }
            return $elementsFromFile;
        }

        // gibt ein Menue fuer die Einstellungen des Grußkartenmoduls aus
        // Uebergabe:
        //             $data_array            .. Daten fuer die Einstellungen in einem Array
        //            $name                .. Name des Drop down Menues
        //            $first_value        .. der Standart Wert oder eingestellte Wert vom Benutzer
        //            $width                .. die Groeße des Menues
        //            $schowfont            .. wenn gesetzt werden   die Menue Eintraege mit der übergebenen Schriftart dargestellt   (Darstellung der Schriftarten)
        //            $showcolor            .. wenn gesetzt bekommen die Menue Eintraege einen farbigen Hintergrund (Darstellung der Farben)
        function getMenueSettings($data_array,$name,$first_value,$width,$schowfont,$showcolor)
        {
            $temp_data = "";
            $temp_data .=  '<select size="1" id="'.$name.'" name="'.$name.'" style="width:'.$width.'px;">';
            for($i=0; $i<count($data_array);$i++)
            {
                $name = "";
                if(!is_integer($data_array[$i]) && strpos($data_array[$i],'.tpl') > 0)
                {
                    $name = ucfirst(preg_replace("/[_-]/"," ",str_replace(".tpl","",$data_array[$i])));
                }
                elseif(is_integer($data_array[$i]))
                {
                    $name = $data_array[$i];
                }
                else if(strpos($data_array[$i],'.') === false)
                {
                    $name = $data_array[$i];
                }
                if($name != "")
                {
                    if (strcmp($data_array[$i],$first_value) == 0 && $schowfont != "true" && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\'>'.$name.'</option>';
                    }
                    else if($schowfont != "true" && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'">'.$name.'</option>';
                    }
                    else if (strcmp($data_array[$i],$first_value) == 0 && $showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\' style="font-family:'.$name.';">'.$name.'</option>';
                    }
                    else if($showcolor != "true")
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" style="font-family:'.$name.';">'.$name.'</option>';
                    }
                    else if (strcmp($data_array[$i],$first_value) == 0)
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" selected=\'selected\' style="background-color:'.$name.';">'.$name.'</option>';
                    }
                    else
                    {
                        $temp_data .= '<option value="'.$data_array[$i].'" style="background-color:'.$name.';">'.$name.'</option>';
                    }
                }
            }
            $temp_data .='</select>';
            return $temp_data;
        }

        /**************************************************************************************/
        //Einstellungen Profilmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="profile-module">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/profile.png" alt="profile" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('PRO_PROFILE').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label>'.$g_l10n->get('PRO_MAINTAIN_PROFILE_FIELDS').':</label></dt>
                            <dd>
                                <div class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php"><img
                                    src="'. THEME_PATH. '/icons/application_form.png" alt="'.$g_l10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/organization/fields.php">'.$g_l10n->get('PRO_SWITCH_TO_MAINTAIN_PROFILE_FIELDS').'</a>
                                </div>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_MAINTAIN_PROFILE_FIELDS_DESC', '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />').'</li>
                    <li>
                        <dl>
                            <dt><label for="default_country">'.$g_l10n->get('PRO_DEFAULT_COUNTRY').':</label></dt>
                            <dd>
                                <select size="1" id="default_country" name="default_country">
                                    <option value=""';
                                    if(strlen($form_values['default_country']) == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';

                                    // Datei mit Laenderliste oeffnen und alle Laender einlesen
                                    $country_list = fopen("../../system/staaten.txt", "r");
                                    $country = trim(fgets($country_list));
                                    while (!feof($country_list))
                                    {
                                        echo '<option value="'.$country.'"';
                                        if($country == $form_values['default_country'])
                                        {
                                            echo ' selected="selected" ';
                                        }
                                        echo '>'.$country.'</option>';
                                        $country = trim(fgets($country_list));
                                    }
                                    fclose($country_list);
                                echo '</select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_DEFAULT_COUNTRY_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_map_link">'.$g_l10n->get('PRO_SHOW_MAP_LINK').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_map_link" name="profile_show_map_link" ';
                                if(isset($form_values['profile_show_map_link']) && $form_values['profile_show_map_link'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_SHOW_MAP_LINK_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_roles">'.$g_l10n->get('PRO_SHOW_ROLE_MEMBERSHIP').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_roles" name="profile_show_roles" ';
                                if(isset($form_values['profile_show_roles']) && $form_values['profile_show_roles'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_SHOW_ROLE_MEMBERSHIP_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_show_former_roles">'.$g_l10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP').':</label></dt>
                            <dd>
                                <input type="checkbox" id="profile_show_former_roles" name="profile_show_former_roles" ';
                                if(isset($form_values['profile_show_former_roles']) && $form_values['profile_show_former_roles'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_SHOW_FORMER_ROLE_MEMBERSHIP_DESC').'</li>';

                    if($g_current_organization->getValue('org_org_id_parent') > 0
                    || $g_current_organization->hasChildOrganizations() )
                    {
                        echo '
                        <li>
                            <dl>
                                <dt><label for="profile_show_extern_roles">'.$g_l10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS').':</label></dt>
                                <dd>
                                    <input type="checkbox" id="profile_show_extern_roles" name="profile_show_extern_roles" ';
                                    if(isset($form_values['profile_show_extern_roles']) && $form_values['profile_show_extern_roles'] == 1)
                                    {
                                        echo ' checked="checked" ';
                                    }
                                    echo ' value="1" />
                                </dd>
                            </dl>
                        </li>
                        <li class="smallFontSize">'.$g_l10n->get('PRO_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC').'</li>';
                    }
                    echo '
                    <li>
                        <dl>
                            <dt><label for="profile_photo_storage">'.$g_l10n->get('PRO_LOCATION_PROFILE_PICTURES').':</label></dt>
                            <dd>
                                <select size="1" id="profile_photo_storage" name="profile_photo_storage">
                                    <option value="">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>
                                    <option value="0" ';
                                            if($form_values['profile_photo_storage'] == 0)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$g_l10n->get('SYS_DATABASE').'
                                    </option>
                                    <option value="1" ';
                                            if($form_values['profile_photo_storage'] == 1)
                                            {
                                                echo ' selected="selected" ';
                                            }
                                            echo '>'.$g_l10n->get('SYS_FOLDER').'
                                    </option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_LOCATION_PROFILE_PICTURES_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="profile_default_role">'.$g_l10n->get('PRO_DEFAULT_ROLE').':</label></dt>
                            <dd>
                                '.generateRoleSelectBox($g_preferences['profile_default_role'], 'profile_default_role').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('PRO_DEFAULT_ROLE_DESC').'</li>                       
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Terminmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="dates-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/dates.png" alt="dates" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('DAT_DATES').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_dates_module">'.$g_l10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>
                                <select size="1" id="enable_dates_module" name="enable_dates_module">
                                    <option value="0" ';
                                    if($form_values['enable_dates_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_dates_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ACTIVATED').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_dates_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ONLY_FOR_REGISTERED_USER').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_per_page">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="weblinks_per_page" name="weblinks_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['weblinks_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_map_link">'.$g_l10n->get('DAT_SHOW_MAP_LINK').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_map_link" name="dates_show_map_link" ';
                                if(isset($form_values['dates_show_map_link']) && $form_values['dates_show_map_link'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DAT_SHOW_MAP_LINK_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_calendar_select">'.$g_l10n->get('DAT_SHOW_CALENDAR_SELECTION').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_calendar_select" name="dates_show_calendar_select" ';
                                if($form_values['dates_show_calendar_select'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1"/>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DAT_SHOW_CALENDAR_SELECTION_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="dates_show_rooms">'.$g_l10n->get('DAT_ROOM_SELECTABLE').':</label></dt>
                            <dd>
                                <input type="checkbox" id="dates_show_rooms" name="dates_show_rooms" ';
                                if($form_values['dates_show_rooms'] == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1"/>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DAT_ROOM_SELECTABLE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label>'.$g_l10n->get('DAT_EDIT_ROOMS').':</label></dt>
                            <dd>
                                <div class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php"><img
                                    src="'. THEME_PATH. '/icons/home.png" alt="'.$g_l10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'" /></a>
                                    <a href="'. $g_root_path. '/adm_program/administration/rooms/rooms.php">'.$g_l10n->get('DAT_SWITCH_TO_ROOM_ADMINISTRATION').'</a>
                                </div>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('DAT_EDIT_ROOMS_DESC', '<img class="iconHelpLink" src="'.THEME_PATH.'/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />').'</li>
                </ul>
            </div>
        </div>';


        /**************************************************************************************/
        //Einstellungen Weblinksmodul
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="links-module">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/weblinks.png" alt="Weblinks" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('LNK_WEBLINKS').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>
                        <dl>
                            <dt><label for="enable_weblinks_module">'.$g_l10n->get('ORG_ACCESS_TO_MODULE').':</label></dt>
                            <dd>
                                <select size="1" id="enable_weblinks_module" name="enable_weblinks_module">
                                    <option value="0" ';
                                    if($form_values['enable_weblinks_module'] == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_DEACTIVATED').'</option>
                                    <option value="1" ';
                                    if($form_values['enable_weblinks_module'] == 1)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_ACTIVATED').'</option>
                                    <option value="2" ';
                                    if($form_values['enable_weblinks_module'] == 2)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_ONLY_FOR_REGISTERED_USER').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_ACCESS_TO_MODULE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_per_page">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE').':</label></dt>
                            <dd>
                                <input type="text" id="weblinks_per_page" name="weblinks_per_page"
                                     style="width: 50px;" maxlength="4" value="'. $form_values['weblinks_per_page']. '" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_target">'.$g_l10n->get('LNK_LINK_TARGET').':</label></dt>
                            <dd>
                                <select size="1" id="weblinks_target" name="weblinks_target">
                                    <option value="_self"';
                                    if($form_values['weblinks_target'] == "_self")
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('LNK_SAME_WINDOW').'</option>
                                    <option value="_blank"';
                                    if($form_values['weblinks_target'] == '_blank')
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('LNK_NEW_WINDOW').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('LNK_LINK_TARGET_DESC').'</li>
                    <li>
                        <dl>
                            <dt><label for="weblinks_redirect_seconds">'.$g_l10n->get('LNK_DISPLAY_REDIRECT').':</label></dt>
                            <dd><input type="text" id="weblinks_redirect_seconds" name="weblinks_redirect_seconds" style="width: 50px;" maxlength="4" value="'. $form_values['weblinks_redirect_seconds']. '" /> Sekunden</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">'.$g_l10n->get('LNK_DISPLAY_REDIRECT_DESC').'</li>
                </ul>
            </div>
        </div>';
		
		
		/**************************************************************************************/
        //Einstellungen Captcha
        /**************************************************************************************/
		
		echo '
        <div class="groupBox" id="captcha">
            <div class="groupBoxHeadline"><img src="'.THEME_PATH.'/icons/captcha.png" alt="'.$g_l10n->get('SYS_CAPTCHA').'" />
                '.$g_l10n->get('SYS_SETTINGS').' '.$g_l10n->get('SYS_CAPTCHA').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li class="smallFontSize">'.$g_l10n->get("ORG_CAPTCHA").'</li>
					<li>
                        <dl>
                            <dt><label for="captcha_type">'.$g_l10n->get('ORG_CAPTCHA_TYPE').':</label></dt>
                            <dd>
                                <select size="1" id="captcha_type" name="captcha_type">
                                    <option value="pic" ';
                                    if($form_values['captcha_type'] == 'pic')
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_CAPTCHA_TYPE_PIC').'</option>
                                    <option value="calc" ';
                                    if($form_values['captcha_type'] == 'calc')
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('ORG_CAPTCHA_TYPE_CALC').'</option>
                                </select>
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_TYPE_TEXT").'
                    </li>
					';
		if($g_preferences['captcha_type'] == 'pic')
		{
			echo '	<li>
                        <dl>
                            <dt><label for="captcha_font">'.$g_l10n->get("SYS_FONT").':</label></dt>
                            <dd>
								<select size="1" id="captcha_fonts" name="captcha_fonts" style="width:120px;">
								';
								$fonts = getfilenames('../../system/fonts/');
								array_push($fonts,"Theme");
								asort($fonts);
								foreach($fonts as $myfonts)
								{
									if($myfonts == $form_values["captcha_fonts"]){$select = ' selected';}
									else {$select = '';}
									echo '<option value="'.$myfonts.'"'.$select.'>'.$myfonts.'</option>
									';
								}
                             echo '</select>
							</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_FONT").'
                    </li>					
                    <li>
                        <dl>
                            <dt><label for="captcha_font_size">'.$g_l10n->get("SYS_FONT_SIZE").':</label></dt>
                            <dd>';
                                echo getMenueSettings(array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"),'captcha_text_size',$form_values["captcha_text_size"],'120','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$g_l10n->get("ORG_CAPTCHA_FONT_SIZE").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_background_color">'.$g_l10n->get("ORG_CAPTCHA_BACKGROUND_COLOR").':</label></dt>
                            <dd>
								<input type="text" id="captcha_background_color" name="captcha_background_color" style="width: 60px;" maxlength="7" value="'.$form_values["captcha_background_color"].'" />                            
							</dd>
                        </dl>
                    </li>
					<li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_BACKGROUND_COLOR_TEXT").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_width">'.$g_l10n->get("ORG_CAPTCHA_SCALING").':</label></dt>
                            <dd><input type="text" id="captcha_width" name="captcha_width" style="width: 50px;" maxlength="4" value="'.$form_values["captcha_width"].'" />
                                x
                                <input type="text" id="captcha_height" name="captcha_height" style="width: 50px;" maxlength="4" value="'.$form_values["captcha_height"].'" />
                                '.$g_l10n->get('ORG_PIXEL').'
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_SIZE").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_signs">'.$g_l10n->get("ORG_CAPTCHA_SIGNS").':</label></dt>
                            <dd>
                                <input type="text" id="captcha_signs" name="captcha_signs" maxlength="80" size="35" value="'.$form_values['captcha_signs'].'" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_SIGNS_TEXT").'
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha_signature">'.$g_l10n->get("ORG_CAPTCHA_SIGNATURE").':</label></dt>
                            <dd>
                                <input type="text" id="captcha_signature" name="captcha_signature" maxlength="60" size="35" value="'.$form_values['captcha_signature'].'" />
                            </dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                        '.$g_l10n->get("ORG_CAPTCHA_SIGNATURE_TEXT").'
                    </li>
					<li>
                        <dl>
                            <dt><label for="captcha_signature_font_size">'.$g_l10n->get("SYS_FONT_SIZE").':</label></dt>
                            <dd>';
                                echo getMenueSettings(array ("9","10","11","12","13","14","15","16","17","18","20","22","24","30"),'captcha_signature_font_size',$form_values["captcha_signature_font_size"],'120','false','false');
                             echo '</dd>
                        </dl>
                    </li>
                    <li class="smallFontSize">
                       '.$g_l10n->get("ORG_CAPTCHA_SIGNATURE_FONT_SIZE").'
                    </li>';
		}
					
		if($g_preferences['captcha_type']=='pic')
		{
			$captcha_parameter = '&type=pic';
		}
		else
		{
			$captcha_parameter = '';
		}
		
					echo '
					<li>
                        <dl>
                            <dt><label for="captcha_prewiew"><a rel="colorboxHelp" href="captcha_preview.php?inline=true'.$captcha_parameter.'">'.$g_l10n->get("ORG_CAPTCHA_PREVIEW").'</a></label></dt>
                            <dd>&nbsp;</dd>
                        </dl>
                    </li>
					<li class="smallFontSize">'.$g_l10n->get('ORG_CAPTCHA_PREVIEW_TEXT').'</li>
                </ul>
            </div>
        </div>';

        /**************************************************************************************/
        //Systeminformationen
        /**************************************************************************************/

        echo '
        <div class="groupBox" id="systeminfo">
            <div class="groupBoxHeadline"><img src="'. THEME_PATH. '/icons/info.png" alt="system infos" />
                '.$g_l10n->get('ORG_SYSTEM_INFORMATIONS').'
            </div>
            <div class="groupBoxBody">';
                require_once('systeminfo.php');
            echo'</div>
        </div>';
     echo'
    </div>
</div>

<div class="formLayout" id="organization_save_button">
    <div class="formBody">
        <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
    </div>
</div>
</form>';

require(THEME_SERVER_PATH. '/overall_footer.php');
?>