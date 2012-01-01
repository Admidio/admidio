<?php
/******************************************************************************
 * Zeigt eine Liste mit moeglichen Zuordnungen an
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lastname  : Der Nachname kann uebergeben und bei neuen Benutzern vorbelegt werden
 * firstname : Der Vorname kann uebergeben und bei neuen Benutzern vorbelegt werden
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

$getLastname  = admFuncVariableIsValid($_GET, 'lastname', 'string', null, true);
$getFirstname = admFuncVariableIsValid($_GET, 'firstname', 'string', null, true);

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// sollen Benutzer mit aehnlichen Namen gefunden werden ?
if($gPreferences['system_search_similar'] == 1)
{
    $sql_similar_name = 
    '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $getLastname.'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $getFirstname.'\'), 1, 4) )
     OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX(\''. $getFirstname.'\'), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX(\''. $getLastname.'\'), 1, 4) ) )';
}
else
{
    $sql_similar_name = 
    '(  (   last_name.usd_value  LIKE \''. $getLastname.'\'
        AND first_name.usd_value LIKE \''. $getFirstname.'\')
     OR (   last_name.usd_value  LIKE \''. $getFirstname.'\'
        AND first_name.usd_value LIKE \''. $getLastname.'\') )';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_login_name, last_name.usd_value as last_name, 
               first_name.usd_value as first_name, address.usd_value as address,
               zip_code.usd_value as zip_code, city.usd_value as city,
               email.usd_value as email
          FROM '. TBL_USERS. '
         RIGHT JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
         RIGHT JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 1 
           AND '.$sql_similar_name;
$result_usr   = $gDb->query($sql);
$member_found = $gDb->num_rows($result_usr);

if($member_found == 0)
{
    // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $getLastname.'&firstname='. $getFirstname);
    exit();
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$gLayout['title'] = $gL10n->get('MEM_CREATE_USER');
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<div class="formLayout" id="assign_users_form" style="width: 400px;">
    <div class="formHead">'.$gL10n->get('MEM_CREATE_USER').'</div>
    <div class="formBody">
        '.$gL10n->get('SYS_SIMILAR_USERS_FOUND', $getFirstname. ' '. $getLastname).'<br />

        <div class="groupBox">
            <div class="groupBoxHeadline">'. $gL10n->get('SYS_USERS_FOUND'). '</div>
            <div class="groupBoxBody">';
                // Alle gefundenen Benutzer mit Adresse ausgeben und einem Link zur weiteren moeglichen Verarbeitung
                $i = 0;
                while($row = $gDb->fetch_array($result_usr))
                {
                    if($i > 0)
                    {
                        echo '<hr />';
                    }
                    echo '<div style="margin-left: 20px;">
						<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'"><img 
                             src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_SHOW_PROFILE').'" /></a>
                        <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.
                            $row['first_name'].' '.$row['last_name'].'</a><br />';
                        if(strlen($row['address']) > 0)
                        {
                            echo $row['address'].'<br />';
                        }
                        if(strlen($row['zip_code']) > 0 || strlen($row['city']) > 0)
                        {
                            echo $row['zip_code'].' '.$row['city'].'<br />';
                        }
                        if(strlen($row['email']) > 0)
                        {
                            if($gPreferences['enable_mail_module'] == 1)
                            {
                                echo '<a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a><br />';
                            }
                            else
                            {
                                echo '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a><br />';
                            }
                        }

                        if(isMember($row['usr_id']) == false)
                        {
                            // gefundene User ist noch KEIN Mitglied dieser Organisation
                            $link = $g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$row['usr_id'];

                            // KEINE Logindaten vorhanden
                            echo '<br />'.$gL10n->get('MEM_NO_MEMBERSHIP', $gCurrentOrganization->getValue('org_shortname')).'<br />
                            
                            <span class="iconTextLink">
                                <a href="'.$link.'"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('MEM_ASSIGN_ROLES').'" /></a>
                                <a href="'.$link.'">'.$gL10n->get('MEM_ASSIGN_ROLES').'</a>
                            </span>';
                        }
                    echo '</div>';
                    $i++;
                }
            echo '</div>
        </div>

        <div class="groupBox">
            <div class="groupBoxHeadline">'.$gL10n->get('SYS_CREATE_NEW_USER').'</div>
            <div class="groupBoxBody">
                <div style="margin-left: 20px;">
                    '. $gL10n->get('SYS_CREATE_NOT_FOUND_USER'). '<br />
                    
                    <span class="iconTextLink">
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $getLastname.'&firstname='. $getFirstname.'&remove_url=1"><img
                        src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_NEW_USER').'" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $getLastname.'&firstname='. $getFirstname.'&remove_url=1">'.$gL10n->get('SYS_CREATE_NEW_USER').'</a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

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