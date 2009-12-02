<?php
/******************************************************************************
 * Neue User auflisten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster dürfen User bestätigen, ansonsten Seite verlassen
if($g_current_user->approveUsers() == false)
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Neue Mitglieder der Gruppierung selektieren
$sql    = "SELECT usr_id, usr_login_name, usr_timestamp_create, last_name.usd_value as last_name,
                  first_name.usd_value as first_name, email.usd_value as email
             FROM ". TBL_USERS. " 
             LEFT JOIN ". TBL_USER_DATA. " as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
            WHERE usr_valid = 0
              AND usr_reg_org_shortname = '$g_organization' 
            ORDER BY last_name, first_name ";
$usr_result   = $g_db->query($sql);
$member_found = $g_db->num_rows($usr_result);

if ($member_found == 0)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show("nomembers", "", "Anmeldungen");
}

// Html-Kopf ausgeben
$g_layout['title']  = "Neue Anmeldungen";
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>

<table class="tableList" cellspacing="0">
    <tr>
        <th colspan="2">Name</th>
        <th>Benutzername</th>
        <th>E-Mail</th>
        <th style="text-align: center;">Funktionen</th>
    </tr>';

    while($row = $g_db->fetch_array($usr_result))
    {
        echo '
        <tr class="tableMouseOver" id="row_user_'.$row['usr_id'].'">
            <td><a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a></td>
            <td><img class="iconInformation" src="'. THEME_PATH. '/icons/calendar_time.png"
                    alt="Registriert am '. mysqldatetime("d.m.y h:i", $row['usr_timestamp_create']). '" title="Registriert am '. mysqldatetime("d.m.y h:i", $row['usr_timestamp_create']). '" /></td>
            <td>'.$row['usr_login_name'].'</td>
            <td>';
                if($g_preferences['enable_mail_module'] == 1)
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
                    src="'. THEME_PATH. '/icons/new_registrations.png" alt="Anmeldung zuordnen" title="Anmeldung zuordnen" /></a>
                <a class="iconLink" href="javascript:deleteObject(\'new_user\', \'row_user_'.$row['usr_id'].'\','.$row['usr_id'].',\''.$row['first_name'].' '.$row['last_name'].'\')"><img 
                    src="'. THEME_PATH. '/icons/delete.png" alt="Anmeldung löschen" title="Anmeldung löschen" /></a>
            </td>
        </tr>';
    }

echo '</table>';

require(THEME_SERVER_PATH. "/overall_footer.php");

?>