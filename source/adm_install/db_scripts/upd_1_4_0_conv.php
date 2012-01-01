<?php
/******************************************************************************
 * Data conversion for version 1.4
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html 
 *
 *****************************************************************************/

define("TBL_ROLE_CATEGORIES",   $g_tbl_praefix. "_role_categories");

// Categorien-Tabelle kopieren
$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_id, cat_org_id, cat_type, cat_name, cat_hidden)
                        SELECT rlc_id, org_id, 'ROL', rlc_name, rlc_locked
                          FROM ". TBL_ROLE_CATEGORIES. "
                          LEFT JOIN ". TBL_ORGANIZATIONS. "
                            ON rlc_org_shortname = org_shortname ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "drop table ". TBL_ROLE_CATEGORIES;
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Alle bisherigen Rollen bekommen das Recht ihr eigenes Profil zu aendern
$sql = "UPDATE ". TBL_ROLES. " SET rol_profile = 1 ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// neue Orgafelder anlegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = mysql_query($sql, $connection);
if(!$result_orga) showError(mysql_error());

while($row_orga = mysql_fetch_object($result_orga))
{
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'registration_mode', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_registration_captcha', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_registration_admin_mail', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'logout_minutes', '30')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_thumbs_column', '5')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_thumbs_row', '5')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_thumbs_scale', '100')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_show_width', '500')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_show_height', '380')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_image_text', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_preview_scale', '100')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_mail_captcha', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_guestbook_captcha', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'flooding_protection_time', '60')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_gbook_comments4all', '0')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_photo_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_guestbook_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_weblinks_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_download_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_announcements_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_dates_module', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_system_mails', '1')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // Jetzt noch die alte OrgaEinstellung send_mail_extern in enable_mail_module umwandeln
    $sql = "SELECT prf_value FROM ". TBL_PREFERENCES. "
            WHERE prf_name = 'send_email_extern' AND prf_org_id = $row_orga->org_id";
    $result_mail_ext = mysql_query($sql, $connection);
    if(!$result_mail_ext) showError(mysql_error());
    $mail_ext = mysql_fetch_object($result_mail_ext);

    if ($mail_ext->prf_value == 0)
    {
        $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                VALUES ($row_orga->org_id, 'enable_mail_module', '1')";
        $result = mysql_query($sql, $connection);
        if(!$result) showError(mysql_error());
    }
    else
    {
        $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                VALUES ($row_orga->org_id, 'enable_mail_module', '0')";
        $result = mysql_query($sql, $connection);
        if(!$result) showError(mysql_error());
    }

    $sql = "DELETE FROM ". TBL_PREFERENCES. "
            WHERE prf_name = 'send_email_extern' AND prf_org_id = $row_orga->org_id";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    // Alle Links bekommen erst einmal die neue Kategorie "Allgemein"

    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                      VALUES ($row_orga->org_id, 'LNK', 'Allgemein', 0) ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $cat_id = mysql_insert_id($connection);

    $sql = "UPDATE ". TBL_LINKS. " SET lnk_cat_id = $cat_id
             WHERE lnk_org_id = $row_orga->org_id ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

// Referenz zw. Rollen und Kategorie wiederherstellen
$sql = "alter table ". TBL_ROLES. " add constraint ". $g_tbl_praefix. "_FK_ROL_CAT foreign key (rol_cat_id)
        references ". TBL_CATEGORIES. " (cat_id) on delete restrict on update restrict ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Referenz zw. Rollen und Kategorie wiederherstellen
$sql = "alter table ". TBL_LINKS. " add constraint ". $g_tbl_praefix. "_FK_LNK_CAT foreign key (lnk_cat_id)
        references ". TBL_CATEGORIES. " (cat_id) on delete restrict on update restrict ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

//In der CommentsTabelle des Gaestebuchs werden nun die usrIDs in Namen uebersetzt
$sql = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS;
$result_comments = mysql_query($sql, $connection);
if(!$result_comments) showError(mysql_error());

while($row_comment = mysql_fetch_object($result_comments))
{
    $sql = "SELECT usr_last_name, usr_first_name FROM ". TBL_USERS. " WHERE usr_id =  $row_comment->gbc_usr_id";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $row = mysql_fetch_object($result);

    $sql = "UPDATE ". TBL_GUESTBOOK_COMMENTS. " SET gbc_name =  '$row->usr_first_name $row->usr_last_name' WHERE gbc_id = $row_comment->gbc_id";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

?>