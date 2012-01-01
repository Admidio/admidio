<?php
/******************************************************************************
 * Neue User auflisten
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require('../../system/common.php');
require('../../system/login_valid.php');

// nur Webmaster dürfen User bestätigen, ansonsten Seite verlassen
if($gCurrentUser->approveUsers() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Neue Mitglieder der Gruppierung selektieren
$sql    = 'SELECT usr_id, usr_login_name, usr_timestamp_create, last_name.usd_value as last_name,
                  first_name.usd_value as first_name, email.usd_value as email
             FROM '. TBL_USERS. ' 
             LEFT JOIN '. TBL_USER_DATA. ' as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
            WHERE usr_valid = 0
              AND usr_reg_org_shortname = \''.$gCurrentOrganization->getValue('org_shortname').'\' 
            ORDER BY last_name, first_name ';
$usr_result   = $gDb->query($sql);
$member_found = $gDb->num_rows($usr_result);

if ($member_found == 0)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('NWU_NO_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
}

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('NWU_NEW_REGISTRATIONS');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', height: \'280px\', onComplete:function(){$("#admButtonNo").focus();}});
        }); 
    //--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>

<table class="tableList" cellspacing="0">
    <tr>
        <th colspan="2">'.$gL10n->get('SYS_NAME').'</th>
        <th>'.$gL10n->get('SYS_USERNAME').'</th>
        <th>'.$gL10n->get('SYS_EMAIL').'</th>
        <th style="text-align: center;">'.$gL10n->get('SYS_FEATURES').'</th>
    </tr>';

    while($row = $gDb->fetch_array($usr_result))
    {
        $timestampCreate = new DateTimeExtended($row['usr_timestamp_create'], 'Y-m-d H:i:s');
        $datetimeCreate  = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);
        echo '
        <tr class="tableMouseOver" id="row_user_'.$row['usr_id'].'">
            <td><a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a></td>
            <td><img class="iconInformation" src="'. THEME_PATH. '/icons/calendar_time.png"
                    alt="'.$gL10n->get('NWU_REGISTERED_ON', $datetimeCreate).'" title="'.$gL10n->get('NWU_REGISTERED_ON', $datetimeCreate).'" /></td>
            <td>'.$row['usr_login_name'].'</td>
            <td>';
                if($gPreferences['enable_mail_module'] == 1)
                {
                    echo '<a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a>';
                }
                else
                {
                    echo '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';
                }
            echo '</td>
            <td style="text-align: center;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/new_user/new_user_assign.php?new_user_id='.$row['usr_id'].'"><img 
                    src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" title="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" /></a>
                <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=nwu&amp;element_id=row_user_'.
                    $row['usr_id'].'&amp;name='.urlencode($row['first_name'].' '.$row['last_name']).'&amp;database_id='.$row['usr_id'].'"><img 
                    src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>
            </td>
        </tr>';
    }

echo '</table>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>