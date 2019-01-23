<?php
/**
 ***********************************************************************************************
 * Birthday
 *
 * Das Plugin listet alle Benutzer auf, die an dem aktuellen Tag Geburtstag haben.
 * Auf Wunsch koennen auch Geburtstagskinder vor X Tagen angezeigt werden.
 *
 * Compatible with Admidio version 3.3
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php'))
{
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if(!isset($plg_show_names_extern) || !is_numeric($plg_show_names_extern) || $plg_show_names_extern !== 1)
{
    $plg_show_names_extern = 0;
}

if(!isset($plg_show_email_extern) || !is_numeric($plg_show_email_extern))
{
    $plg_show_email_extern = 0;
}

if(!isset($plg_show_names) || !is_numeric($plg_show_names))
{
    $plg_show_names = 1;
}

if(!isset($plg_show_age) || !is_numeric($plg_show_age))
{
    $plg_show_age = 0;
}

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = '_self';
}

if(!isset($plg_show_hinweis_keiner) || !is_numeric($plg_show_hinweis_keiner))
{
    $plg_show_hinweis_keiner = 0;
}

if(!isset($plg_show_alter_anrede) || !is_numeric($plg_show_alter_anrede))
{
    $plg_show_alter_anrede = 18;
}

if(!isset($plg_show_zeitraum) || !is_numeric($plg_show_zeitraum))
{
    $plg_show_zeitraum = 5;
}

if(!isset($plg_show_future) || !is_numeric($plg_show_future))
{
    $plg_show_future = 10;
}
// Prüfen, ob die Rollenbedingung gesetzt wurde
if(!isset($plg_rolle_sql) || $plg_rolle_sql === '')
{
    $sqlRol = 'IS NOT NULL';
}
else
{
    $sqlRol = 'IN '.$plg_rolle_sql;
}

// Prüfen, ob die Sortierbedingung gesetzt wurde
if(!isset($plg_sort_sql) || $plg_sort_sql === '')
{
    $sqlSort = 'DESC';
}
else
{
    $sqlSort = $plg_sort_sql;
}

if(!isset($plg_show_headline) || !is_numeric($plg_show_headline))
{
    $plg_show_headline = 1;
}

// ist der Benutzer ausgeloggt und soll nur die Anzahl der Geb-Kinder angezeigt werden, dann Zeitraum auf 0 Tage setzen
if($plg_show_names_extern === 0 && !$gValidLogin)
{
    $plg_show_zeitraum = 0;
    $plg_show_future   = 0;
}

// if page object is set then integrate css file of this plugin
global $page;
if(isset($page) && $page instanceof HtmlPage)
{
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/birthday/birthday.css');
}

$fieldBirthday = $gProfileFields->getProperty('BIRTHDAY', 'usf_id');

$sql = 'SELECT DISTINCT usr_id, usr_login_name,
                        last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                        birthday.bday AS birthday, birthday.bdate,
                        DATEDIFF(birthday.bdate, ?) AS days_to_bdate, -- DATE_NOW
                        YEAR(bdate) - YEAR(bday) AS age,
                        email.usd_value AS email, gender.usd_value AS gender
          FROM '.TBL_USERS.' AS users
    INNER JOIN ( (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(?), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                    FROM '.TBL_USER_DATA.' AS bd1
                   WHERE DATEDIFF(CONCAT(YEAR(?), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                 BETWEEN ? AND ? -- -$plg_show_zeitraum AND $plg_show_future
                     AND usd_usf_id = ?) -- $fieldBirthday
               UNION
                 (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(?)-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                    FROM '.TBL_USER_DATA.' AS bd2
                   WHERE DATEDIFF(CONCAT(YEAR(?)-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                 BETWEEN ? AND ? -- -$plg_show_zeitraum AND $plg_show_future
                     AND usd_usf_id = ?) -- $fieldBirthday
               UNION
                 (SELECT usd_usr_id, usd_value AS bday,
                         CONCAT(YEAR(?)+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                    FROM '.TBL_USER_DATA.' AS bd3
                   WHERE DATEDIFF(CONCAT(YEAR(?)+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                 BETWEEN ? AND ? -- -$plg_show_zeitraum AND $plg_show_future
                     AND usd_usf_id = ?) -- $fieldBirthday
               ) birthday
            ON birthday.usd_usr_id = usr_id
     LEFT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS gender
            ON gender.usd_usr_id = usr_id
           AND gender.usd_usf_id = ? -- $gProfileFields->getProperty(\'GENDER\', \'usf_id\')
     LEFT JOIN '.TBL_MEMBERS.'
            ON mem_usr_id = usr_id
           AND mem_begin <= ? -- DATE_NOW
           AND mem_end    > ? -- DATE_NOW
    INNER JOIN '.TBL_ROLES.'
            ON mem_rol_id = rol_id
           AND rol_valid  = 1
    INNER JOIN '.TBL_CATEGORIES.'
            ON rol_cat_id = cat_id
           AND cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
         WHERE usr_valid = 1
           AND mem_rol_id '.$sqlRol.'
      ORDER BY days_to_bdate '.$sqlSort.', last_name, first_name';

$queryParams = array(
    DATE_NOW,
    DATE_NOW, DATE_NOW, DATE_NOW, -$plg_show_zeitraum, $plg_show_future, $fieldBirthday,
    DATE_NOW, DATE_NOW, DATE_NOW, -$plg_show_zeitraum, $plg_show_future, $fieldBirthday,
    DATE_NOW, DATE_NOW, DATE_NOW, -$plg_show_zeitraum, $plg_show_future, $fieldBirthday,
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    $gProfileFields->getProperty('EMAIL', 'usf_id'),
    $gProfileFields->getProperty('GENDER', 'usf_id'),
    DATE_NOW,
    DATE_NOW,
    $gCurrentOrganization->getValue('org_id')
    // TODO add more params
);
$birthdayStatement = $gDb->queryPrepared($sql, $queryParams);

$numberBirthdays = $birthdayStatement->rowCount();

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
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
                // the display type of the name
                switch ($plg_show_names)
                {
                    case 1:  // first name, last name
                        $plgShowName = $row['first_name']. ' '. $row['last_name'];
                        break;
                    case 2:  // last name, first name
                        $plgShowName = $row['last_name']. ', '. $row['first_name'];
                        break;
                    case 3:  // first name
                        $plgShowName = $row['first_name'];
                        break;
                    case 4:  // Loginname
                        $plgShowName = $row['usr_login_name'];
                        break;
                    default: // first name, last name
                        $plgShowName = $row['first_name']. ' '. $row['last_name'];
                }

                // ab einem festgelegten Alter wird fuer ausgeloggte Besucher nur der Nachname mit Anrede angezeigt
                if(!$gValidLogin && $plg_show_alter_anrede <= $row['age'])
                {
                    if ($row['gender'] > 1)
                    {
                        $plgShowName = $gL10n->get('PLG_BIRTHDAY_WOMAN_VAR', array($row['last_name']));
                    }
                    else
                    {
                        $plgShowName = $gL10n->get('PLG_BIRTHDAY_MAN_VAR', array($row['last_name']));
                    }
                }

                // show name and e-mail link for registered users
                if($gValidLogin)
                {
                    $plgShowName = '<a href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES. '/profile/profile.php', array('user_id' => $row['usr_id'])) . '"
                        target="'. $plg_link_target. '" title="'.$gL10n->get('SYS_SHOW_PROFILE').'">'. $plgShowName. '</a>';

                    // E-Mail-Adresse ist hinterlegt und soll auch bei eingeloggten Benutzern verlinkt werden
                    if(strlen($row['email']) > 0 && $plg_show_email_extern < 2)
                    {
                        $plgShowName .= '
                            <a class="admidio-icon-link" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES. '/messages/messages_write.php', array('usr_id' => $row['usr_id'])) . '"><img
                            src="'. THEME_URL. '/icons/email.png" alt="'.$gL10n->get('SYS_WRITE_EMAIL').'" title="'.$gL10n->get('SYS_WRITE_EMAIL').'" /></a>';
                    }
                }
                elseif($plg_show_email_extern === 1 && strlen($row['email']) > 0)
                {
                    $plgShowName .= '
                        <a class="admidio-icon-link" href="mailto:'. $row['email']. '"><img
                        src="'. THEME_URL. '/icons/email.png" alt="'.$gL10n->get('SYS_WRITE_EMAIL').'" title="'.$gL10n->get('SYS_WRITE_EMAIL').'" /></a>';
                }

                // set css class and string for birthday today, in the future or in the past
                $birthdayDate = \DateTime::createFromFormat('Y-m-d', $row['birthday']);

                if ($row['days_to_bdate'] < 0)
                {
                    $plgCssClass = 'plgBirthdayNameHighlightAgo';
                    if ($row['days_to_bdate'] == -1)
                    {
                        $birthdayText = 'PLG_BIRTHDAY_YESTERDAY';
                        $plgDays = ' ';
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
                    if ($row['days_to_bdate'] == 1)
                    {
                        $birthdayText = 'PLG_BIRTHDAY_TOMORROW';
                        $plgDays = ' ';
                    }
                    else
                    {
                        $birthdayText = 'PLG_BIRTHDAY_FUTURE';
                        $plgDays = $row['days_to_bdate'];
                    }
                }
                else
                {
                    $plgCssClass  = 'plgBirthdayNameHighlight';
                    $birthdayText = 'PLG_BIRTHDAY_TODAY';
                    $plgDays      = $row['age'];
                }

                // don't show age of birthday person if preference is set
                if ($plg_show_age === 0 || !$gValidLogin)
                {
                    $birthdayText .= '_NO_AGE';
                }

                // now show string with the birthday person
                echo '<li><span id="'.$plgCssClass.'">'.
                    $gL10n->get($birthdayText, array($plgShowName, $plgDays, $row['age'], $birthdayDate->format($gSettingsManager->getString('system_date')))).
                '</span></li>';
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
            echo '<p>'.$gL10n->get('PLG_BIRTHDAY_MORE_USERS', array($numberBirthdays)).'</p>';
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
