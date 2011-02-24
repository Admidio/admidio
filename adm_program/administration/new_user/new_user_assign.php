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
 * new_user_id: ID des Users, der angezeigt werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');

// nur Webmaster duerfen User zuordnen, ansonsten Seite verlassen
if($g_current_user->approveUsers() == false)
{
   $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

// Uebergabevariablen pruefen und initialisieren
$req_new_user_id = 0;

if(isset($_GET['new_user_id']) && is_numeric($_GET['new_user_id']))
{
    $req_new_user_id = $_GET['new_user_id'];
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// neuen User erst einmal als Objekt erzeugen
$new_user = new User($g_db, $req_new_user_id);

// sollen Benutzer mit aehnlichen Namen gefunden werden ?
if($g_preferences['system_search_similar'] == 1)
{
    $sql_similar_name = 
    '(  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX("'. $new_user->getValue('LAST_NAME').'"), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX("'. $new_user->getValue('FIRST_NAME').'"), 1, 4) )
     OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4) LIKE SUBSTRING(SOUNDEX("'. $new_user->getValue('FIRST_NAME').'"), 1, 4)
        AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4) LIKE SUBSTRING(SOUNDEX("'. $new_user->getValue('LAST_NAME').'"), 1, 4) ) )';
}
else
{
    $sql_similar_name = 
    '(  (   last_name.usd_value  LIKE "'. $new_user->getValue('LAST_NAME').'"
        AND first_name.usd_value LIKE "'. $new_user->getValue('FIRST_NAME').'")
     OR (   last_name.usd_value  LIKE "'. $new_user->getValue('FIRST_NAME').'"
        AND first_name.usd_value LIKE "'. $new_user->getValue('LAST_NAME').'") )';
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
    // neuer User -> Rollen zuordnen
    if($g_current_user->editUsers())
    {
        // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
        header('Location: '.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=3&user_id='.$new_user->getValue('usr_id'));
        exit();
    }
    else
    {
        // User auf aktiv setzen
        $new_user->setValue('usr_valid', 1);
        $new_user->setValue('usr_reg_org_shortname', '');
        $new_user->save();

        // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
        if($g_preferences['enable_system_mails'] == 1)
        {
            // Mail an den User schicken, um die Anmeldung zu bestaetigen
            $sysmail = new SystemMail($g_db);
            $sysmail->addRecipient($new_user->getValue('EMAIL'), $new_user->getValue('FIRST_NAME'). ' '. $new_user->getValue('LAST_NAME'));
            if($sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $new_user) == false)
            {
                $g_message->show($g_l10n->get('SYS_EMAIL_NOT_SEND', $new_user->getValue('EMAIL')));
            }
        }

        if($g_current_user->assignRoles())
        {
            // neuer User -> Rollen zuordnen
            header('Location: roles.php?new_user=1&user_id='. $new_user->getValue('usr_id'));
            exit();
        }
        else
        {
            // da der angemeldete Benutzer keine Rechte besitzt Rollen zu zuordnen, 
            // wird der neue User der Default-Rolle zugeordnet
            if($g_preferences['profile_default_role'] == 0)
            {
                $g_message->show($g_l10n->get('PRO_NO_DEFAULT_ROLE'));
            }
            $member = new TableMembers($g_db);
            $member->startMembership($g_preferences['profile_default_role'], $new_user->getValue('usr_id'));
            
            $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl(), 2000);
            $g_message->show($g_l10n->get('SYS_SAVE'));
        }
    }
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = $g_l10n->get('NWU_ASSIGN_REGISTRATION');
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<div class="formLayout" id="assign_users_form" style="width: 400px;">
    <div class="formHead">'.$g_layout['title'].'</div>
    <div class="formBody">
        '.$g_l10n->get('SYS_SIMILAR_USERS_FOUND', $new_user->getValue('FIRST_NAME'). ' '. $new_user->getValue('LAST_NAME')).'<br />

        <div class="groupBox">
            <div class="groupBoxHeadline">'.$g_l10n->get('SYS_USERS_FOUND').'</div>
            <div class="groupBoxBody">';
                // Alle gefundenen Benutzer mit Adresse ausgeben und einem Link zur weiteren moeglichen Verarbeitung
                $i = 0;
                while($row = $g_db->fetch_object($result_usr))
                {
                    if($i > 0)
                    {
                        echo '<hr />';
                    }
                    echo '<div style="margin-left: 20px;">
						<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row->usr_id.'"><img 
                             src="'.THEME_PATH.'/icons/profile.png" alt="'.$g_l10n->get('PRO_PROFILE').'" /></a>
                        <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='.$row->usr_id.'">'.
                            $row->first_name.' '.$row->last_name.'</a><br />';
                        if(strlen($row->address) > 0)
                        {
                            echo $row->address.'<br />';
                        }
                        if(strlen($row->zip_code) > 0 || strlen($row->city) > 0)
                        {
                            echo $row->zip_code.' '.$row->city.'<br />';
                        }
                        if(strlen($row->email) > 0)
                        {
                            if($g_preferences['enable_mail_module'] == 1)
                            {
                                echo '<a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?usr_id='.$row->usr_id.'">'.$row->email.'</a><br />';
                            }
                            else
                            {
                                echo '<a href="mailto:'.$row->email.'">'.$row->email.'</a><br />';
                            }
                        }

                        if(isMember($row->usr_id))
                        {
                            // gefundene User ist bereits Mitglied dieser Organisation
                            if(strlen($row->usr_login_name) > 0)
                            {
                                // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken                    
                                echo '<br />'.$g_l10n->get('NWU_USER_VALID_LOGIN');
                                if($g_preferences['enable_system_mails'] == 1)
                                {
                                    echo '<br />'.$g_l10n->get('NWU_REMINDER_SEND_LOGIN').'<br />

                                    <span class="iconTextLink">
                                        <a href="'.$g_root_path.'/adm_program/administration/new_user/new_user_function.php?new_user_id='.$req_new_user_id.'&amp;user_id='.$row->usr_id.'&amp;mode=6"><img
                                        src="'. THEME_PATH. '/icons/key.png" alt="'.$g_l10n->get('NWU_SEND_LOGIN').'" /></a>
                                        <a href="'.$g_root_path.'/adm_program/administration/new_user/new_user_function.php?new_user_id='.$req_new_user_id.'&amp;user_id='.$row->usr_id.'&amp;mode=6">'.$g_l10n->get('NWU_SEND_LOGIN').'</a>
                                    </span>';
                                }
                            }
                            else
                            {
                                // Logindaten sind NICHT vorhanden -> diese nun zuordnen
                                echo '<br />'.$g_l10n->get('NWU_USER_NO_VALID_LOGIN').'<br />

                                <span class="iconTextLink">
                                    <a href="'.$g_root_path.'/adm_program/administration/new_user/new_user_function.php?new_user_id='.$req_new_user_id.'&amp;user_id='.$row->usr_id.'&amp;mode=1"><img
                                    src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$g_l10n->get('NWU_ASSIGN_LOGIN').'" /></a>
                                    <a href="'.$g_root_path.'/adm_program/administration/new_user/new_user_function.php?new_user_id='.$req_new_user_id.'&amp;user_id='.$row->usr_id.'&amp;mode=1">'.$g_l10n->get('NWU_ASSIGN_LOGIN').'</a>
                                </span>';
                            }
                        }
                        else
                        {
                            // gefundene User ist noch KEIN Mitglied dieser Organisation
                            $link = $g_root_path.'/adm_program/administration/new_user/new_user_function.php?new_user_id='.$req_new_user_id.'&amp;user_id='.$row->usr_id.'&amp;mode=2';

                            if(strlen($row->usr_login_name) > 0)
                            {
                                // Logindaten sind bereits vorhanden
                                echo '<br />'.$g_l10n->get('NWU_NO_MEMBERSHIP', $g_organization).'<br />

                                <span class="iconTextLink">
                                    <a href="'.$link.'"><img src="'.THEME_PATH.'/icons/new_registrations.png" 
                                        alt="'.$g_l10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'" /></a>
                                    <a href="'.$link.'">'.$g_l10n->get('NWU_ASSIGN_MEMBERSHIP_AND_LOGIN').'</a>
                                </span>';
                            }               
                            else
                            {
                                // KEINE Logindaten vorhanden
                                echo '<br />'.$g_l10n->get('NWU_NO_MEMBERSHIP_NO_LOGIN', $g_organization).'<br />
                                
                                <span class="iconTextLink">
                                    <a href="'.$link.'"><img src="'. THEME_PATH. '/icons/new_registrations.png" 
                                        alt="'.$g_l10n->get('NWU_ASSIGN_MEMBERSHIP').'" /></a>
                                    <a href="'.$link.'">'.$g_l10n->get('NWU_ASSIGN_MEMBERSHIP').'</a>
                                </span>';
                            }               
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
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='.$req_new_user_id.'&amp;new_user=3"><img
                        src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_CREATE_NEW_USER').'" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='.$req_new_user_id.'&amp;new_user=3">'.$g_l10n->get('SYS_CREATE_NEW_USER').'</a>
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