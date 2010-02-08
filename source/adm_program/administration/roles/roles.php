<?php
/******************************************************************************
 * Rollen mit Berechtigungen auflisten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
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

 require('../../system/common.php');
 require('../../system/login_valid.php');
 require('../../system/classes/table_roles.php');

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!$g_current_user->assignRoles())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

if(isset($_GET['inactive']) && $_GET['inactive'] == 1)
{
    $req_valid = 0;
}
else
{
    $req_valid = 1;
}

if(isset($_GET['invisible']) && $_GET['invisible']==1)
{
    $req_visible = 0;
}
else
{
    $req_visible = 1;
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['roles_request']);

// Html-Kopf ausgeben
$g_layout['title']  = 'Rollenverwaltung';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">Rollenverwaltung</h1>';

if($req_valid == true)
{
    $description_lnk = 'Inaktive Rollen';
    $description_lst = 'Aktive Rollen';
    $image           = 'roles_gray.png';
}
else
{
    $description_lnk = 'Aktive Rollen';
    $description_lst = 'Inaktive Rollen';
    $image           = 'roles.png';
}

if($req_visible == true)
{
    $visible_lnk    = 'Unsichtbare Rollen';
    $visible_lst    = 'Sichtbare Rollen';
    $visible_image  = 'light_off.png';
}
else
{
    $visible_lnk    = 'Sichtbare Rollen';
    $visible_lst    = 'Unsichtbare Rollen';
    $visible_image  = 'light_on.png';
}

echo '
<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
            src="'. THEME_PATH. '/icons/add.png" alt="Rolle anlegen" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">Rolle anlegen</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$req_valid.'"><img
            src="'. THEME_PATH. '/icons/'.$image.'" alt="'.$description_lnk.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$req_valid.'">'.$description_lnk.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$req_visible.'"><img
            src="'. THEME_PATH. '/icons/'.$visible_image.'" alt="'.$visible_lnk.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$req_visible.'">'.$visible_lnk.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="Kategorien pflegen" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL">Kategorien pflegen</a>
        </span>
    </li>
</ul>

<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>'.$description_lst.'</th>
            <th>Berechtigungen</th>
            <th>Einst.</th>
            <th style="text-align: center;">Funktionen</th>
        </tr>
    </thead>';
    $cat_id = '';

    // alle Rollen gruppiert nach Kategorie auflisten
    $sql    = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_valid  = '.$req_valid.'
                  AND rol_visible = '.$req_visible.'
                  AND rol_cat_id = cat_id
                  AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
                ORDER BY cat_id ASC, rol_name ASC ';
    $rol_result = $g_db->query($sql);

    // Rollenobjekt anlegen
	$role = new TableRoles($g_db);

    while($row = $g_db->fetch_array($rol_result))
    {
        // Rollenobjekt mit Daten fuellen
        $role->setArray($row);
        
        if($cat_id != $role->getValue('cat_id'))
        {
            if($cat_id > 0)
            {
                echo '</tbody>';
            }
            $image_hidden = '';
            $block_id     = 'cat_'.$role->getValue('cat_id');
            if($role->getValue('cat_hidden') == 1)
            {
                $image_hidden = '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png"
                                 alt="Nur sichtbar für eingeloggte Benutzer" title="Nur sichtbar für eingeloggte Benutzer" />';
            }
            echo '<tbody>
                <tr>
                    <td class="tableSubHeader" colspan="4">
                        <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\')"><img
                        id="img_'.$block_id.'" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="ausblenden" /></a>'.$role->getValue('cat_name').' '.$image_hidden.'
                    </td>
                </tr>
            </tbody>
            <tbody id="'.$block_id.'">';

            $cat_id = $role->getValue('cat_id');
        }
        echo "
        <tr class=\"tableMouseOver\">
            <td>&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=".$role->getValue("rol_id")."\" title=\"".$role->getValue("rol_description")."\">".$role->getValue("rol_name")."</a></td>
            <td>";
                if($role->getValue("rol_assign_roles") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/roles.png\"
                    alt=\"Rollen verwalten und zuordnen\" title=\"Rollen verwalten und zuordnen\" />";
                }
                if($role->getValue("rol_approve_users") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/new_registrations.png\"
                    alt=\"Registrierungen verwalten und zuordnen\" title=\"Registrierungen verwalten und zuordnen\" />";
                }
                if($role->getValue("rol_edit_user") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/group.png\"
                    alt=\"Profildaten aller Benutzer bearbeiten\" title=\"Profildaten aller Benutzer bearbeiten\" />";
                }
                if($role->getValue("rol_mail_to_all") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email.png\"
                    alt=\"Emails an alle Rollen schreiben\" title=\"Emails an alle Rollen schreiben\" />";
                }
                if($role->getValue("rol_profile") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/profile.png\"
                    alt=\"Eigenes Profil bearbeiten\" title=\"Eigenes Profil bearbeiten\" />";
                }
                if($role->getValue("rol_announcements") == 1 && $g_preferences['enable_announcements_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/announcements.png\"
                    alt=\"Ankündigungen anlegen und bearbeiten\" title=\"Ankündigungen anlegen und bearbeiten\" />";
                }
                if($role->getValue("rol_dates") == 1 && $g_preferences['enable_dates_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/dates.png\"
                    alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\" />";
                }
                if($role->getValue("rol_photo") == 1 && $g_preferences['enable_photo_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/photo.png\"
                    alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\" />";
                }
                if($role->getValue("rol_download") == 1 && $g_preferences['enable_download_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/download.png\"
                    alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\" />";
                }
                if($role->getValue("rol_guestbook") == 1 && $g_preferences['enable_guestbook_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/guestbook.png\"
                    alt=\"Gästebucheinträge bearbeiten und löschen\" title=\"Gästebucheinträge bearbeiten und löschen\" />";
                }
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($role->getValue("rol_guestbook_comments") == 1  && $g_preferences['enable_guestbook_module'] > 0 && $g_preferences['enable_gbook_comments4all'] == false)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/comments.png\"
                    alt=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" title=\"Kommentare zu G&auml;stebucheintr&auml;gen anlegen\" />";
                }
                if($role->getValue("rol_weblinks") == 1 && $g_preferences['enable_weblinks_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/weblinks.png\"
                    alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\" />";
                }
                /*if($role->getValue("rol_inventory") == 1 && $g_preferences['enable_inventory_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/weblinks.png\"
                    alt=\"Inventar verwalten\" title=\"Inventar verwalten\" />";
                }*/
                if($role->getValue("rol_all_lists_view") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/lists.png\"
                    alt=\"Mitgliederlisten aller Rollen einsehen\" title=\"Mitgliederlisten aller Rollen einsehen\" />";
                }
            echo "</td>
            <td>";
                if($role->getValue("rol_this_list_view") == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/list_role.png\"
                    alt=\"Nur Mitglieder dieser Rolle können die Mitgliederliste einsehen.\" title=\"Nur Mitglieder dieser Rolle können die Mitgliederliste einsehen.\" />";
                }
                if($role->getValue("rol_this_list_view") == 2)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/list_key.png\"
                    alt=\"Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen\" title=\"Angemeldete Benutzer können die Mitgliederliste der Rolle einsehen.\" />";
                }
                if($role->getValue("rol_mail_this_role") == 1 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email_role.png\"
                    alt=\"Nur Mitglieder dieser Rolle dürfen E-Mails an sie schreieben.\" title=\"Nur Mitglieder dieser Rolle dürfen E-Mails an sie schreieben.\" />";
                }
                if($role->getValue("rol_mail_this_role") == 2 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email_key.png\"
                    alt=\"Eingeloggte Benutzer dürfen E-Mails an diese Rolle schreiben.\" title=\"Eingeloggte Benutzer k&ouml;nnen E-Mails an diese Rolle schreiben.\" />";
                }
                if($role->getValue("rol_mail_this_role") == 3 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/email.png\"
                    alt=\"Alle Besucher der Webseite dürfen E-Mails an diese Rolle schreiben.\" title=\"Alle Besucher der Webseite dürfen E-Mails an diese Rolle schreiben\" />";
                }
            echo '</td>
            <td style="text-align: center;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$role->getValue("rol_id").'"><img
                src="'. THEME_PATH. '/icons/list.png" alt="Mitglieder anzeigen" title="Mitglieder anzeigen" /></a>';

                if($req_valid == true)
                {
                    echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/lists/members.php?rol_id=".$role->getValue("rol_id")."\"><img
                        src=\"". THEME_PATH. "/icons/add.png\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\" /></a>";
                }
                else
                {
                    echo "<a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/roles_function.php?rol_id=".$role->getValue("rol_id")."&amp;mode=5\"><img
                        src=\"". THEME_PATH. "/icons/roles.png\" alt=\"Rolle aktivieren\" title=\"Rolle aktivieren\" /></a>";
                }

                if($role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
                {
                    echo '<a class="iconLink"><img src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" /></a>';
                }
                else
                {
                    if($req_valid == true)
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=1"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="Rolle löschen" title="Rolle löschen" /></a>';
                    }
                    else
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=6"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="Rolle löschen" title="Rolle löschen" /></a>';
                    }
                }
                if($req_visible == true)
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=7"><img
                            src="'. THEME_PATH. '/icons/light_off.png" alt="Rolle unsichtbar machen" title="Rolle verstecken" /></a>';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=8"><img
                            src="'. THEME_PATH. '/icons/light_on.png" alt="Rolle sichtbar machen" title="Rolle zeigen" /></a>';
                }
            echo '</td>
        </tr>';
    }
echo '</tbody>
</table>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>