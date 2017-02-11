<?php
/**
 ***********************************************************************************************
 * Birthday
 *
 * Version 2.0.0
 *
 * Das Plugin listet alle Benutzer auf, die an dem aktuellen Tag Geburtstag haben.
 * Auf Wunsch koennen auch Geburtstagskinder vor X Tagen angezeigt werden.
 *
 * Compatible with Admidio version 3.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'birthday.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos + 1, $plugin_file_pos - $plugin_folder_pos - 2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// integrate language file of plugin
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!isset($plg_show_names_extern) || !is_numeric($plg_show_names_extern))
{
    $plg_show_names_extern = 1;
}

if(!isset($plg_show_email_extern) || !is_numeric($plg_show_email_extern))
{
    $plg_show_email_extern = 0;
}

if(!isset($plg_show_names) || !is_numeric($plg_show_names))
{
    $plg_show_names = 1;
}

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = '_self';
}

if(!isset($plg_show_hinweis_keiner) || !is_numeric($plg_show_names_extern))
{
    $plg_show_names_extern = 0;
}

if(!isset($plg_show_alter_anrede) || !is_numeric($plg_show_names_extern))
{
    $plg_show_names_extern = 18;
}

if(!isset($plg_show_zeitraum) || !is_numeric($plg_show_names_extern))
{
    $plg_show_zeitraum = 5;
}

if(!isset($plg_show_future) || !is_numeric($plg_show_names_extern))
{
    $plg_show_future = 10;
}
// Prüfen, ob die Rollenbedingung gesetzt wurde
if(!isset($plg_rolle_sql) || $plg_rolle_sql === '')
{
    $rol_sql = 'IS NOT NULL';
}
else
{
    $rol_sql = 'IN '.$plg_rolle_sql;
}

// Prüfen, ob die Sotierbedingung gesetzt wurde
if(!isset($plg_sort_sql) || $plg_sort_sql === '')
{
    $sort_sql = 'DESC';
}
else
{
    $sort_sql = $plg_sort_sql;
}

// ist der Benutzer ausgeloggt und soll nur die Anzahl der Geb-Kinder angezeigt werden, dann Zeitraum auf 0 Tage setzen
if($plg_show_names_extern === 0 && !$gValidLogin)
{
    $plg_show_zeitraum = 0;
    $plg_show_future = 0;
}

// if page object is set then integrate css file of this plugin
global $page;
if(isset($page) && $page instanceof \HtmlPage)
{
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/birthday/birthday.css');
}

$sql = 'SELECT DISTINCT usr_id, usr_login_name,
                        last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                        birthday.bday AS birthday, birthday.bdate,
                        DATEDIFF(birthday.bdate, \''.DATETIME_NOW.'\') AS days_to_bdate,
                        YEAR(bdate) - YEAR(bday) AS age,
                        email.usd_value AS email, gender.usd_value AS gender
          FROM '.TBL_USERS.' users
    INNER JOIN ( (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(\''.DATETIME_NOW.'\'), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')) AS bdate
                    FROM '.TBL_USER_DATA.' bd1
                   WHERE DATEDIFF(CONCAT(YEAR(\''.DATETIME_NOW.'\'), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')), \''.DATETIME_NOW.'\')
                         BETWEEN -'.$plg_show_zeitraum.' AND '.$plg_show_future.'
                     AND usd_usf_id = '.$gProfileFields->getProperty('BIRTHDAY', 'usf_id').')
               UNION
                 (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(\''.DATETIME_NOW.'\')-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')) AS bdate
                    FROM '.TBL_USER_DATA.' bd2
                   WHERE DATEDIFF(CONCAT(YEAR(\''.DATETIME_NOW.'\')-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')), \''.DATETIME_NOW.'\')
                         BETWEEN -'.$plg_show_zeitraum.' AND '.$plg_show_future.'
                     AND usd_usf_id = '.$gProfileFields->getProperty('BIRTHDAY', 'usf_id').')
               UNION
                 (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(\''.DATETIME_NOW.'\')+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')) AS bdate
                    FROM '.TBL_USER_DATA.' bd3
                   WHERE DATEDIFF(CONCAT(YEAR(\''.DATETIME_NOW.'\')+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')), \''.DATETIME_NOW.'\')
                         BETWEEN -'.$plg_show_zeitraum.' AND '.$plg_show_future.'
                     AND usd_usf_id = '.$gProfileFields->getProperty('BIRTHDAY', 'usf_id').')
               ) birthday
            ON birthday.usd_usr_id = usr_id
     LEFT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
     LEFT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '.$gProfileFields->getProperty('EMAIL', 'usf_id').'
     LEFT JOIN '.TBL_USER_DATA.' AS gender
            ON gender.usd_usr_id = usr_id
           AND gender.usd_usf_id = '.$gProfileFields->getProperty('GENDER', 'usf_id').'
     LEFT JOIN '.TBL_MEMBERS.'
            ON mem_usr_id = usr_id
           AND mem_begin <= \''.DATE_NOW.'\'
           AND mem_end    > \''.DATE_NOW.'\'
    INNER JOIN '.TBL_ROLES.'
            ON mem_rol_id = rol_id
           AND rol_valid  = 1
    INNER JOIN '.TBL_CATEGORIES.'
            ON rol_cat_id = cat_id
           AND cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
         WHERE usr_valid = 1
           AND mem_rol_id '.$rol_sql.'
      ORDER BY days_to_bdate '.$sort_sql.', last_name, first_name ';
$birthdayStatement = $gDb->query($sql);

$numberBirthdays = $birthdayStatement->rowCount();

echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';
if($plg_show_headline)
{
    echo '<h3>'.$gL10n->get('PLG_BIRTHDAY_HEADLINE').'</h3>';
}

if($numberBirthdays > 0)
{
    if($plg_show_names_extern === 1 || $gValidLogin)
    {

        echo '<ul id="plgBirthdayNameList">';
            while($row = $birthdayStatement->fetch())
            {
                // Anzeigeart des Namens beruecksichtigen
                switch ($plg_show_names)
                {
                    case 1:  // Vorname, Nachname
                        $plg_show_name = $row['first_name']. ' '. $row['last_name'];
                        break;
                    case 2:  // Nachname, Vorname
                        $plg_show_name = $row['last_name']. ', '. $row['first_name'];
                        break;
                    case 3:  // Vorname
                        $plg_show_name = $row['first_name'];
                        break;
                    case 4:  // Loginname
                        $plg_show_name = $row['usr_login_name'];
                        break;
                    default: // Vorname, Nachname
                        $plg_show_name = $row['first_name']. ' '. $row['last_name'];
                }

                // ab einem festgelegten Alter wird fuer ausgeloggte Besucher nur der Nachname mit Anrede angezeigt
                if(!$gValidLogin && $plg_show_alter_anrede <= $row['age'])
                {
                    if ($row['gender'] > 1)
                    {
                        $plg_show_name = $gL10n->get('PLG_BIRTHDAY_WOMAN_VAR', $row['last_name']);
                    }
                    else
                    {
                        $plg_show_name = $gL10n->get('PLG_BIRTHDAY_MAN_VAR', $row['last_name']);
                    }
                }

                // Namen mit Alter und Mail-Link anzeigen
                if($gValidLogin)
                {
                    $plg_show_name = '<a href="'. ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php?user_id='. $row['usr_id']. '"
                        target="'. $plg_link_target. '" title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'. $plg_show_name. '</a>';

                    // E-Mail-Adresse ist hinterlegt und soll auch bei eingeloggten Benutzern verlinkt werden
                    if(strlen($row['email']) > 0 && $plg_show_email_extern < 2)
                    {
                        $plg_show_name = $plg_show_name.'
                            <a class="admidio-icon-link" href="'. ADMIDIO_URL. FOLDER_MODULES. '/messages/messages_write.php?usr_id='. $row['usr_id']. '"><img
                            src="'. THEME_URL. '/icons/email.png" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" title="'.$gL10n->get('MAI_SEND_EMAIL').'" /></a>';
                    }
                }
                elseif($plg_show_email_extern === 1 && strlen($row['email']) > 0)
                {
                    $plg_show_name = $plg_show_name.'
                        <a class="admidio-icon-link" href="mailto:'. $row['email']. '"><img
                        src="'. THEME_URL. '/icons/email.png" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" title="'.$gL10n->get('MAI_SEND_EMAIL').'" /></a>';
                }

                // Soll das Alter auch für nicht angemeldete Benutzer angezeigt werden?
                if($plg_show_names_extern < 2 || $gValidLogin)
                {
                    // Geburtstagskinder am aktuellen Tag bekommen anderen Text
                    if((int) $row['days_to_bdate'] === 0)
                    {
                        // Die Anzeige der Geburtstage folgt nicht mehr als Liste, sondern mittels div-Tag
                        echo '<li><span id="plgBirthdayNameHighlight">'.$gL10n->get('PLG_BIRTHDAY_TODAY', $plg_show_name, $row['age']).'</span></li>';
                    }
                    else
                    {
                        $birthayDate  = DateTime::createFromFormat('Y-m-d', $row['birthday']);
                        $plgDays      = ' ';
                        $plgCssClass  = '';
                        $birthdayText = '';

                        if ($row['days_to_bdate'] < 0)
                        {
                            $plgCssClass = 'plgBirthdayNameHighlightAgo';
                            if($row['days_to_bdate'] == -1)
                            {
                                $birthdayText = 'PLG_BIRTHDAY_YESTERDAY';
                            }
                            else
                            {
                                $birthdayText = 'PLG_BIRTHDAY_PAST';
                                $plgDays = -$row['days_to_bdate'];
                            }
                        }
                        elseif ($row['days_to_bdate'] > 0)
                        {
                            $plgCssClass = 'plgBirthdayNameHighlightFuture';
                            if($row['days_to_bdate'] == 1)
                            {
                                $birthdayText = 'PLG_BIRTHDAY_TOMORROW';
                            }
                            else
                            {
                                $birthdayText = 'PLG_BIRTHDAY_FUTURE';
                                $plgDays = $row['days_to_bdate'];
                            }
                        }
                        // Die Anzeige der Geburtstage folgt nicht mehr als Liste, sondern mittels div-Tag
                        echo '<li><span id="'.$plgCssClass.'">'.
                            $gL10n->get($birthdayText, $plg_show_name, $plgDays, $row['age'], $birthayDate->format($gPreferences['system_date'])).
                        '</span></li>';
                    }
                }
            }
        echo '</ul>';
    }
    else
    {
        if($numberBirthdays === 1)
        {
            echo '<p>'.$gL10n->get('PLG_BIRTHDAY_ONE_USER').'</p>';
        }
        else
        {
            echo '<p>'.$gL10n->get('PLG_BIRTHDAY_MORE_USERS', $numberBirthdays).'</p>';
        }
    }
}
else
{
    // Bei entsprechend gesetzter Konfiguration wird auch im Fall, dass keiner Geburtstag hat, eine Meldung ausgegeben.
    if(!$plg_show_hinweis_keiner)
    {
        echo '<p>'.$gL10n->get('PLG_BIRTHDAY_NO_USER').'</p>';
    }
}

echo '</div>';
