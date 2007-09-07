<?php
/******************************************************************************
 * Neue User auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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
    $g_message->show("norights");
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
$sql    = "SELECT usr_id, usr_login_name, last_name.usd_value as last_name,
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
    $g_message->setForwardUrl("home");
    $g_message->show("nomembers", "", "Anmeldungen");
}

// Html-Kopf ausgeben
$g_layout['title'] = "Neue Anmeldungen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Neue Anmeldungen</h1>

<table class=\"tableList\" cellspacing=\"0\">
    <tr>
        <th>Name</th>
        <th>Benutzername</th>
        <th>E-Mail</th>
        <th style=\"text-align: center;\">Funktionen</th>
    </tr>";

    while($row = $g_db->fetch_object($usr_result))
    {
        echo "
        <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
            <td><a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\">$row->last_name, $row->first_name</a></td>
            <td>$row->usr_login_name</td>
            <td>";
                if($g_preferences['enable_mail_module'] == 1)
                {
                    echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id\">$row->email</a>";
                }
                else
                {
                    echo "<a href=\"mailto:$row->email\">$row->email</a>";
                }
            echo "</td>
            <td style=\"text-align: center;\">
                <span class=\"iconLink\">
                    <a href=\"$g_root_path/adm_program/administration/new_user/new_user_assign.php?new_user_id=$row->usr_id\"><img 
                    src=\"$g_root_path/adm_program/images/properties.png\" alt=\"Anmeldung zuordnen\" title=\"Anmeldung zuordnen\" /></a>
                </span>
                <span class=\"iconLink\">
                    <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$row->usr_id&amp;mode=5\"><img 
                    src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Anmeldung l&ouml;schen\" title=\"Anmeldung l&ouml;schen\" /></a>
                </span>
            </td>
        </tr>";
    }

echo "</table>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>