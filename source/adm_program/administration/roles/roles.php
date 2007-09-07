<?php
/******************************************************************************
 * Rollen mit Berechtigungen auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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

// Html-Kopf ausgeben
$g_layout['title']  = "Rollenverwaltung";
$g_layout['header'] = "<script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/show_hide_block.js\"></script>
                       <style type=\"text/css\">
                           .iconLink li {
                               padding: 15px;
                               list-style-type: none;
                               display: inline;
                           }

                           .iconLink img {
                               vertical-align:   top;
                               border-width:     0px;
                           }
                       </style>";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Rollenverwaltung</h1>";

if($req_valid == true)
{
    $description_lnk = "Inaktive Rollen";
    $description_lst = "Aktive Rollen";
    $image       = "wand_gray.png";
}
else
{
    $description_lnk = "Aktive Rollen";
    $description_lst = "Inaktive Rollen";
    $image       = "wand.png";
}

echo "
<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/roles_new.php\"><img
            src=\"$g_root_path/adm_program/images/add.png\" alt=\"Rolle anlegen\"></a>
            <a href=\"$g_root_path/adm_program/administration/roles/roles_new.php\">Rolle anlegen</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/roles.php?inactive=$req_valid\"><img
            src=\"$g_root_path/adm_program/images/$image\" alt=\"$description_lnk\"></a>
            <a href=\"$g_root_path/adm_program/administration/roles/roles.php?inactive=$req_valid\">$description_lnk</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=ROL\"><img
            src=\"$g_root_path/adm_program/images/application_double.png\" alt=\"Kategorien pflegen\"></a>
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=ROL\">Kategorien pflegen</a>
        </span>
    </li>
</ul>

<table class=\"tableList\" cellspacing=\"0\">
    <thead>
        <tr>
            <th>$description_lst</th>
            <th>Berechtigungen</th>
            <th style=\"text-align: center;\"><img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\" title=\"Rolle nur f&uuml;r Moderatoren sichtbar\"></th>
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
                $image_hidden = "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/user_key.png\" 
                                 alt=\"Nur sichtbar f&uuml;r eingeloggte Benutzer\" title=\"Nur sichtbar f&uuml;r eingeloggte Benutzer\">";
            }
            echo "<tbody>
                <tr>
                    <td class=\"tableSubHeader\" colspan=\"4\">
                        <a class=\"iconShowHide\" href=\"javascript:showHideBlock('$block_id','$g_root_path')\"><img 
                        name=\"img_$block_id\" src=\"$g_root_path/adm_program/images/triangle_open.gif\" alt=\"ausblenden\"></a>$row->cat_name $image_hidden
                    </td>
                </tr>
            </tbody>
            <tbody id=\"$block_id\">";

            $cat_id = $row->cat_id;
        }            
        echo "
        <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
            <td>&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=$row->rol_id\" title=\"$row->rol_description\">$row->rol_name</a></td>
            <td>";
                if($row->rol_assign_roles == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/wand.png\"
                    alt=\"Rollen verwalten und zuordnen\" title=\"Rollen verwalten und zuordnen\">";
                }
                if($row->rol_approve_users == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/properties.png\"
                    alt=\"Registrierungen verwalten und zuordnen\" title=\"Registrierungen verwalten und zuordnen\">";
                }
                if($row->rol_edit_user == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/group.png\"
                    alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" title=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\">";
                }
                if($row->rol_profile == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/user.png\"
                    alt=\"Eigenes Profil bearbeiten\" title=\"Eigenes Profil bearbeiten\">";
                }
                if($row->rol_announcements == 1 && $g_preferences['enable_announcements_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/note.png\"
                    alt=\"Ank&uuml;ndigungen anlegen und bearbeiten\" title=\"Ank&uuml;ndigungen anlegen und bearbeiten\">";
                }
                if($row->rol_dates == 1 && $g_preferences['enable_dates_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/date.png\"
                    alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\">";
                }
                if($row->rol_photo == 1 && $g_preferences['enable_photo_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/photo.png\"
                    alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\">";
                }
                if($row->rol_download == 1 && $g_preferences['enable_download_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/folder_down.png\"
                    alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\">";
                }
                if($row->rol_guestbook == 1 && $g_preferences['enable_guestbook_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/comment.png\"
                    alt=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\" title=\"G&auml;stebucheintr&auml;ge bearbeiten und l&ouml;schen\">";
                }
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($row->rol_guestbook_comments == 1  && $g_preferences['enable_guestbook_module'] > 0 && $g_preferences['enable_gbook_comments4all'] == false)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/comments.png\"
                    alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" title=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\">";
                }
                if($row->rol_mail_logout == 1 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/email.png\"
                    alt=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Besucher (ausgeloggt) k&ouml;nnen E-Mails an diese Rolle schreiben\">";
                }
                if($row->rol_mail_login == 1 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/email_key.png\"
                    alt=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben\">";
                }
                if($row->rol_weblinks == 1 && $g_preferences['enable_weblinks_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/globe.png\"
                    alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\">";
                }
            echo "</td>
            <td style=\"text-align: center;\">";
                if($row->rol_locked == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/lock.png\"
                    alt=\"Rolle nur f&uuml;r Moderatoren sichtbar\" title=\"Rolle nur f&uuml;r Moderatoren sichtbar\">";
                }
            echo "</td>
            <td style=\"text-align: center;\">
                <span class=\"iconLink\">
                    <a href=\"$g_root_path/adm_program/modules/lists/lists_show.php?type=address&amp;mode=html&amp;rol_id=$row->rol_id\"><img
                    src=\"$g_root_path/adm_program/images/application_view_columns.png\" alt=\"Mitglieder anzeigen\" title=\"Mitglieder anzeigen\"></a>
                </span>";

                if($req_valid == true)
                {
                    echo "
                    <span class=\"iconLink\">
                        <a href=\"$g_root_path/adm_program/modules/lists/members.php?rol_id=$row->rol_id\"><img 
                        src=\"$g_root_path/adm_program/images/add.png\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\"></a>
                    </span>";
                }
                else
                {
                    echo "
                    <span class=\"iconLink\">
                        <a href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=5\"><img
                        src=\"$g_root_path/adm_program/images/wand.png\" alt=\"Rolle aktivieren\" title=\"Rolle aktivieren\"></a>
                    </span>";
                }

                if($row->rol_name == "Webmaster")
                {
                    echo "
                    <span class=\"iconLink\">
                        <img src=\"$g_root_path/adm_program/images/dummy.png\" alt=\"dummy\">
                    </span>";
                }
                else
                {
                    if($req_valid == true)
                    {
                        echo "
                        <span class=\"iconLink\">
                            <a href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=1\"><img 
                            src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Rolle l&ouml;schen\" title=\"Rolle l&ouml;schen\"></a>
                        </span>";
                    }
                    else
                    {
                        echo "
                        <span class=\"iconLink\">
                            <a href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=$row->rol_id&amp;mode=6\"><img 
                            src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Rolle l&ouml;schen\" title=\"Rolle l&ouml;schen\"></a>
                        </span>";
                    }
                }
            echo "</td>
        </tr>";
    }
echo "</tbody>
</table>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>