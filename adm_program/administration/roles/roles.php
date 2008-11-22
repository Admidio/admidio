<?php
/******************************************************************************
 * Rollen mit Berechtigungen auflisten 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * inactive: 0 - (Default) alle aktiven Rollen anzeigen
 *           1 - alle inaktiven Rollen anzeigen
 *
 *****************************************************************************/

 require("../../system/common.php");
 require("../../system/login_valid.php");

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!$g_current_user->assignRoles())
{
    $g_message->show("norights");
}

if(isset($_GET['inactive'])
&& $_GET['inactive'] == 1)
{
    $req_valid = 0;
}
else
{
    $req_valid = 1;
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['roles_request']);

// Default-Konfiguration ermitteln
$sql = "SELECT lst_id FROM ". TBL_LISTS. "
         WHERE lst_org_id  = ". $g_current_organization->getValue("org_id"). "
           AND lst_default = 1 ";
$g_db->query($sql);
$row = $g_db->fetch_array();
$default_list_id = $row[0];

// Html-Kopf ausgeben
$g_layout['title']  = "Rollenverwaltung";

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Rollenverwaltung</h1>";

if($req_valid == true)
{
    $description_lnk = "Inaktive Rollen";
    $description_lst = "Aktive Rollen";
    $image       = "roles_gray.png";
}
else
{
    $description_lnk = "Aktive Rollen";
    $description_lst = "Inaktive Rollen";
    $image       = "roles.png";
}

echo "
<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/roles_new.php\"><img
            src=\"". THEME_PATH. "/icons/add.png\" alt=\"Rolle anlegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/roles/roles_new.php\">Rolle anlegen</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/roles.php?inactive=$req_valid\"><img
            src=\"". THEME_PATH. "/icons/$image\" alt=\"$description_lnk\" /></a>
            <a href=\"$g_root_path/adm_program/administration/roles/roles.php?inactive=$req_valid\">$description_lnk</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=ROL\"><img
            src=\"". THEME_PATH. "/icons/application_double.png\" alt=\"Kategorien pflegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=ROL\">Kategorien pflegen</a>
        </span>
    </li>
</ul>

<table class=\"tableList\" cellspacing=\"0\">
    <thead>
        <tr>
            <th>$description_lst</th>
            <th>Berechtigungen</th>
            <th>Einst.</th>
            <th style=\"text-align: center;\">Funktionen</th>
        </tr>
    </thead>";
    $cat_id = "";

    // alle Rollen gruppiert nach Kategorie auflisten
    $sql    = "SELECT * FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                WHERE rol_valid  = $req_valid
                  AND rol_cat_id = cat_id
                  AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                ORDER BY cat_sequence ASC, rol_name ASC ";
    $rol_result = $g_db->query($sql);

    while($row = $g_db->fetch_object($rol_result))
    {
        if($cat_id != $row->cat_id)
        {
            if($cat_id > 0)
            {
                echo "</tbody>";
            }
            $image_hidden = "";
            $block_id     = "cat_$row->cat_id";
            if($row->cat_hidden == 1)
            {
                $image_hidden = "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/user_key.png\" 
                                 alt=\"Nur sichtbar f&uuml;r eingeloggte Benutzer\" title=\"Nur sichtbar f&uuml;r eingeloggte Benutzer\" />";
            }
            echo "<tbody>
                <tr>
                    <td class=\"tableSubHeader\" colspan=\"4\">
                        <a class=\"iconShowHide\" href=\"javascript:showHideBlock('$block_id','". THEME_PATH. "')\"><img 
                        id=\"img_$block_id\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>$row->cat_name $image_hidden
                    </td>
                </tr>
            </tbody>
            <tbody id=\"$block_id\">";

            $cat_id = $row->cat_id;
        }            
        echo "
        <tr class=\"tableMouseOver\">
            <td>&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=$row->rol_id\" title=\"$row->rol_description\">$row->rol_name</a></td>
            <td>";
                if($row->rol_assign_roles == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/roles.png\"
                    alt=\"Rollen verwalten und zuordnen\" title=\"Rollen verwalten und zuordnen\" />";
                }
                if($row->rol_approve_users == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/new_registrations.png\"
                    alt=\"Registrierungen verwalten und zuordnen\" title=\"Registrierungen verwalten und zuordnen\" />";
                }
                if($row->rol_edit_user == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/group.png\"
                    alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" title=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" />";
                }
   			    if($row->rol_mail_to_all == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email.png\"
                    alt=\"Emails an alle Rollen schreiben\" title=\"Emails an alle Rollen schreiben\" />";
                }
                if($row->rol_profile == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/profile.png\"
                    alt=\"Eigenes Profil bearbeiten\" title=\"Eigenes Profil bearbeiten\" />";
                }
                if($row->rol_announcements == 1 && $g_preferences['enable_announcements_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/announcements.png\"
                    alt=\"Ankündigungen anlegen und bearbeiten\" title=\"Ankündigungen anlegen und bearbeiten\" />";
                }
                if($row->rol_dates == 1 && $g_preferences['enable_dates_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/dates.png\"
                    alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\" />";
                }
                if($row->rol_photo == 1 && $g_preferences['enable_photo_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/photo.png\"
                    alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\" />";
                }
                if($row->rol_download == 1 && $g_preferences['enable_download_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/download.png\"
                    alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\" />";
                }
                if($row->rol_guestbook == 1 && $g_preferences['enable_guestbook_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/guestbook.png\"
                    alt=\"Gästebucheinträge bearbeiten und löschen\" title=\"Gästebucheinträge bearbeiten und löschen\" />";
                }
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($row->rol_guestbook_comments == 1  && $g_preferences['enable_guestbook_module'] > 0 && $g_preferences['enable_gbook_comments4all'] == false)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/comments.png\"
                    alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" title=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" />";
                }
                if($row->rol_weblinks == 1 && $g_preferences['enable_weblinks_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/weblinks.png\"
                    alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\" />";
                }
                if($row->rol_all_lists_view == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/lists.png\"
                    alt=\"Mitgliederlisten aller Rollen einsehen\" title=\"Mitgliederlisten aller Rollen einsehen\" />";
                }
            echo "</td>
            <td>";
                if($row->rol_this_list_view == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/list_role.png\"
                    alt=\"Nur Mitglieder dieser Rolle können die Mitgliederliste einsehen.\" title=\"Nur Mitglieder dieser Rolle können die Mitgliederliste einsehen.\" />";
                }
                if($row->rol_this_list_view == 2)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/list_key.png\"
                    alt=\"Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen\" title=\"Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen.\" />";
                }
                if($row->rol_mail_this_role == 1 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email_role.png\"
                    alt=\"Nur Mitglieder dieser Rolle dürfen E-Mails an sie schreieben.\" title=\"Nur Mitglieder dieser Rolle dürfen E-Mails an sie schreieben.\" />";
                }
                if($row->rol_mail_this_role == 2 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email_key.png\"
                    alt=\"Eingeloggte Benutzer dürfen E-Mails an diese Rolle schreiben.\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben.\" />";
                }
    			if($row->rol_mail_this_role == 3 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email.png\"
                    alt=\"Alle Besucher der Webseite dürfen E-Mails an diese Rolle schreiben.\" title=\"Alle Besucher der Webseite dürfen E-Mails an diese Rolle schreiben\" />";
                }
            echo '</td>
            <td style="text-align: center;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$default_list_id.'&amp;mode=html&amp;rol_id='.$row->rol_id.'"><img
                src="'. THEME_PATH. '/icons/list.png" alt="Mitglieder anzeigen" title="Mitglieder anzeigen" /></a>';

                if($req_valid == true)
                {
                    echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/lists/members.php?rol_id=$row->rol_id\"><img 
                        src=\"". THEME_PATH. "/icons/add.png\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\" /></a>";
                }
                else
                {
                    echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=5\"><img
                        src=\"". THEME_PATH. "/icons/roles.png\" alt=\"Rolle aktivieren\" title=\"Rolle aktivieren\" /></a>";
                }

                if($row->rol_name == "Webmaster")
                {
                    echo "<a class=\"iconLink\"><img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" /></a>";
                }
                else
                {
                    if($req_valid == true)
                    {
                        echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=1\"><img 
                            src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Rolle löschen\" title=\"Rolle löschen\" /></a>";
                    }
                    else
                    {
                        echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=6\"><img 
                            src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Rolle löschen\" title=\"Rolle löschen\" /></a>";
                    }
                }
            echo "</td>
        </tr>";
    }
echo "</tbody>
</table>";
    
require(THEME_SERVER_PATH. "/overall_footer.php");

?>