<?php
/******************************************************************************
 * Neue User auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
$_SESSION['navigation']->addUrl($g_current_url);

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
$usr_result = mysql_query($sql, $g_adm_con);
db_error($usr_result,__FILE__,__LINE__);
$member_found = mysql_num_rows($usr_result);

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

<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
    <tr>
        <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Name</th>
        <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Benutzername</th>
        <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;E-Mail</th>
        <th class=\"tableHeader\" style=\"text-align: center;\">&nbsp;Funktionen</th>
    </tr>";

    while($row = mysql_fetch_object($usr_result))
    {
        echo "
        <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
            <td style=\"text-align: left;\">&nbsp;<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\">$row->last_name, $row->first_name</a></td>
            <td style=\"text-align: left;\">&nbsp;$row->usr_login_name</td>
            <td style=\"text-align: left;\">&nbsp;";
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
                <a href=\"$g_root_path/adm_program/administration/new_user/new_user_assign.php?new_user_id=$row->usr_id\">
                   <img src=\"$g_root_path/adm_program/images/properties.png\" border=\"0\" alt=\"Anmeldung zuordnen\" title=\"Anmeldung zuordnen\"></a>&nbsp;&nbsp;
                <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$row->usr_id&amp;mode=5\">
                   <img src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Anmeldung l&ouml;schen\" title=\"Anmeldung l&ouml;schen\"></a>
            </td>
        </tr>";
    }

echo "</table>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>