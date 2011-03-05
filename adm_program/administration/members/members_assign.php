<?php
/******************************************************************************
 * Zeigt eine Liste mit moeglichen Zuordnungen an
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lastname  : Der Nachname kann uebergeben und bei neuen Benutzern vorbelegt werden
 * firstname : Der Vorname kann uebergeben und bei neuen Benutzern vorbelegt werden
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// sollen Benutzer mit aehnlichen Namen gefunden werden ?
if($g_preferences['system_search_similar'] == 1)
{
    $sql_similar_name = 
    '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX("'. $_GET['lastname'].'"), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX("'. $_GET['firstname'].'"), 1, 4) )
     OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX("'. $_GET['firstname'].'"), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX("'. $_GET['lastname'].'"), 1, 4) ) )';
}
else
{
    $sql_similar_name = 
    '(  (   last_name.usd_value  LIKE "'. $_GET['lastname'].'"
        AND first_name.usd_value LIKE "'. $_GET['firstname'].'")
     OR (   last_name.usd_value  LIKE "'. $_GET['firstname'].'"
        AND first_name.usd_value LIKE "'. $_GET['lastname'].'") )';
}

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = 'SELECT usr_id, usr_login_name, last_name.usd_value as last_name, 
               first_name.usd_value as first_name, address.usd_value as address,
               zip_code.usd_value as zip_code, city.usd_value as city,
               email.usd_value as email
          FROM '. TBL_USERS. '
         RIGHT JOIN '. TBL_USER_DATA. ' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id'). '
         RIGHT JOIN '. TBL_USER_DATA. ' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = '. $g_current_user->getProperty('ADDRESS', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = '. $g_current_user->getProperty('POSTCODE', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = '. $g_current_user->getProperty('CITY', 'usf_id'). '
          LEFT JOIN '. TBL_USER_DATA. ' as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $g_current_user->getProperty('EMAIL', 'usf_id'). '
         WHERE usr_valid = 1 
           AND '.$sql_similar_name;
$result_usr   = $g_db->query($sql);
$member_found = $g_db->num_rows($result_usr);

if($member_found == 0)
{
    // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $_GET['lastname'].'&firstname='. $_GET['firstname']);
    exit();
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = $g_l10n->get('MEM_CREATE_USER');
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<div class="formLayout" id="assign_users_form" style="width: 400px;">
    <div class="formHead">'.$g_l10n->get('MEM_CREATE_USER').'</div>
    <div class="formBody">
        '.$g_l10n->get('SYS_SIMILAR_USERS_FOUND', $_GET['firstname']. ' '. $_GET['lastname']).'<br />

        <div class="groupBox">
            <div class="groupBoxHeadline">'. $g_l10n->get('SYS_USERS_FOUND'). '</div>
            <div class="groupBoxBody">';
                // Alle gefundenen Benutzer mit Adresse ausgeben und einem Link zur weiteren moeglichen Verarbeitung
                $i = 0;
                while($row = $g_db->fetch_array($result_usr))
                {
                    if($i > 0)
                    {
                        echo '<hr />';
                    }
                    echo '<div style="margin-left: 20px;">
						<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'"><img 
                             src="'.THEME_PATH.'/icons/profile.png" alt="'.$g_l10n->get('SYS_SHOW_PROFILE').'" /></a>
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
                            if($g_preferences['enable_mail_module'] == 1)
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
                            $link = $g_root_path.'/adm_program/modules/profile/roles.php?user_id='.$row['usr_id'];

                            // KEINE Logindaten vorhanden
                            echo '<br />'.$g_l10n->get('MEM_NO_MEMBERSHIP', $g_organization).'<br />
                            
                            <span class="iconTextLink">
                                <a href="'.$link.'"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$g_l10n->get('MEM_ASSIGN_ROLES').'" /></a>
                                <a href="'.$link.'">'.$g_l10n->get('MEM_ASSIGN_ROLES').'</a>
                            </span>';
                        }
                    echo '</div>';
                    $i++;
                }
            echo '</div>
        </div>

        <div class="groupBox">
            <div class="groupBoxHeadline">'.$g_l10n->get('SYS_CREATE_NEW_USER').'</div>
            <div class="groupBoxBody">
                <div style="margin-left: 20px;">
                    '. $g_l10n->get('SYS_CREATE_NOT_FOUND_USER'). '<br />
                    
                    <span class="iconTextLink">
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $_GET['lastname'].'&firstname='. $_GET['firstname'].'"><img
                        src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_CREATE_NEW_USER').'" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=1&lastname='. $_GET['lastname'].'&firstname='. $_GET['firstname'].'">'.$g_l10n->get('SYS_CREATE_NEW_USER').'</a>
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
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>