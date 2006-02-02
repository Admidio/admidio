<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 1.2
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

$sql = "RENAME TABLE adm_user_data TO tmp_user_data";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// neue Datenbank einspielen
$filename = "db.sql";
$file     = fopen($filename, "r")
				or showError("Die Datei <b>db.sql</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
$content  = fread($file, filesize($filename));
$sql_arr  = explode(";", $content);
fclose($file);

foreach($sql_arr as $sql)
{
	if(strlen(trim($sql)) > 0)
	{
		// Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
		$sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
		$result = mysql_query($sql, $connection);
		if(!$result) showError(mysql_error());
	}
}

// Organization

$sql = "SELECT * FROM adm_gruppierung";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Id der Mutterorganisation auslesen
	$sql = "SELECT ag_id FROM adm_gruppierung 
				WHERE ag_shortname = '$row->ag_mutter'";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$row_mother = mysql_fetch_object($result);	        

	// Organisation in neue Tabelle schreiben
	$sql = "INSERT INTO adm_organizations (org_id, org_longname, org_shortname, org_org_id_parent, org_homepage)
	             VALUES ($row->ag_id, '$row->ag_longname', '$row->ag_shortname', ";
	if($row_mother->ag_id > 0)
		$sql .= $row_mother->ag_id;
	else
		$sql .= "NULL";
	
	$sql .= ", '$g_homepage') ";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	
	// Default Rollen-Kategorie eintragen
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Allgemein')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$rlc_id_common = mysql_insert_id();
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Gruppen')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$rlc_id_groups = mysql_insert_id();
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Kurse')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Mannschaften')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Fotos

$sql = "INSERT INTO adm_photos (pho_id, pho_org_shortname, pho_quantity, pho_name, pho_begin, pho_end, pho_photographers, pho_timestamp, pho_approved, pho_last_change)
				             SELECT ap_id, ap_ag_shortname, ap_number, ap_name, ap_begin, ap_end, ap_photographers, ap_online_since, 1, ap_last_change
				               FROM adm_photo ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Benutzer

$sql = "INSERT INTO adm_users (usr_id, usr_last_name, usr_first_name, usr_address, usr_zip_code, usr_city, usr_country, usr_phone, usr_mobile, usr_fax, usr_birthday,
						  				 usr_email, usr_homepage, usr_login_name, usr_password, usr_last_login, usr_actual_login, usr_number_login, usr_valid)
				            SELECT au_id, au_name, au_vorname, au_adresse, au_plz, au_ort, au_land, au_tel1, au_mobil, au_fax, au_geburtstag,
				                   au_mail, au_weburl, au_login, au_password, au_last_login, au_act_login, au_num_login, 1 
				              FROM adm_user ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// neue Benutzer

$sql = "INSERT INTO adm_users (usr_last_name, usr_first_name, usr_email, usr_login_name, usr_password, usr_reg_org_shortname, usr_valid)
                        SELECT anu_name, anu_vorname, anu_mail, anu_login, anu_password, anu_ag_shortname, 0
                          FROM adm_new_user ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Rollen

$sql = "INSERT INTO adm_roles (rol_id, rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_moderation, rol_dates, rol_edit_user, rol_photo,
										 rol_download, rol_mail_logout, rol_mail_login, rol_locked, rol_start_date, rol_start_time, rol_end_date, rol_end_time, 
										 rol_weekday, rol_location, rol_max_members, rol_cost, rol_last_change, rol_usr_id_change, rol_valid)
							  SELECT ar_id, ar_ag_shortname, $rlc_id_common, ar_funktion, ar_beschreibung, ar_r_moderation, ar_r_termine, ar_r_user_bearbeiten, ar_r_foto,
							  			ar_r_download, ar_r_mail_logout, ar_r_mail_login, ar_r_locked, ar_datum_von, ar_zeit_von, ar_datum_bis, ar_zeit_bis, 
							  			ar_wochentag, ar_ort, ar_max_mitglieder, ar_beitrag, ar_last_change, ar_last_change_id, ar_valid
							  	 FROM adm_rolle
							  	WHERE ar_gruppe = 0 ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());	

$sql = "INSERT INTO adm_roles (rol_id, rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_moderation, rol_dates, rol_edit_user, rol_photo,
										 rol_download, rol_mail_logout, rol_mail_login, rol_locked, rol_start_date, rol_start_time, rol_end_date, rol_end_time, 
										 rol_weekday, rol_location, rol_max_members, rol_cost, rol_last_change, rol_usr_id_change, rol_valid)
							  SELECT ar_id, ar_ag_shortname, $rlc_id_groups, ar_funktion, ar_beschreibung, ar_r_moderation, ar_r_termine, ar_r_user_bearbeiten, ar_r_foto,
							  			ar_r_download, ar_r_mail_logout, ar_r_mail_login, ar_r_locked, ar_datum_von, ar_zeit_von, ar_datum_bis, ar_zeit_bis, 
							  			ar_wochentag, ar_ort, ar_max_mitglieder, ar_beitrag, ar_last_change, ar_last_change_id, ar_valid
							  	 FROM adm_rolle
							  	WHERE ar_gruppe = 1 ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());	

// Mitglieder

$sql = "INSERT INTO adm_members (mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_valid, mem_leader)
								 SELECT am_id, am_ar_id, am_au_id, am_start, am_ende, am_valid, am_leiter 
								   FROM adm_mitglieder ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Benutzerdefinierte Felder
$sql = "INSERT INTO adm_user_fields (usf_id, usf_type, usf_name, usf_description, usf_locked, usf_org_shortname)
                             SELECT auf_id, auf_type, auf_name, auf_description, auf_locked, auf_ag_shortname
                               FROM adm_user_field ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Benutzerdefinierte Felder

$sql = "INSERT INTO adm_user_data (usd_id, usd_usr_id, usd_usf_id, usd_value)
                            SELECT aud_id, aud_au_id, aud_auf_id, aud_value
                              FROM tmp_user_data ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Termine

$sql = "SELECT * FROM adm_termine";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Termine in neue Tabelle schreiben
	$sql = "INSERT INTO adm_dates (dat_id, dat_org_shortname, dat_global, dat_begin, dat_end, dat_description, 
								dat_location, dat_headline, dat_timestamp, dat_usr_id, dat_last_change, dat_usr_id_change)
	             VALUES ($row->at_id, '$row->at_ag_shortname', $row->at_global, '$row->at_von', '$row->at_bis', '". strip_tags($row->at_beschreibung). "',
	             			'$row->at_ort', '". strip_tags($row->at_ueberschrift). "', '$row->at_timestamp', ";
	if($row->at_au_id > 0)
		$sql .= "$row->at_au_id, ";
	else
		$sql .= "NULL, ";
	if(strlen($row->at_last_change) > 0)
		$sql .= "'$row->at_last_change', ";
	else
		$sql .= "NULL, ";
	if($row->at_last_change_id > 0)
		$sql .= "$row->at_last_change_id)";
	else
		$sql .= "NULL)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Ankuendigungen

$sql = "SELECT * FROM adm_ankuendigungen";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Ankuendigungen in neue Tabelle schreiben
	$sql = "INSERT INTO adm_announcements (ann_id, ann_org_shortname, ann_global, ann_description, 
								ann_headline, ann_timestamp, ann_usr_id, ann_last_change, ann_usr_id_change)
	             VALUES ($row->aa_id, '$row->aa_ag_shortname', $row->aa_global, '". strip_tags($row->aa_beschreibung). "',
	             			'". strip_tags($row->aa_ueberschrift). "', '$row->aa_timestamp', ";
	if($row->aa_au_id > 0)
		$sql .= "$row->aa_au_id, ";
	else
		$sql .= "NULL, ";
	if(strlen($row->aa_last_change) > 0)
		$sql .= "'$row->aa_last_change', ";
	else
		$sql .= "NULL, ";
	if($row->aa_last_change_id > 0)
		$sql .= "$row->aa_last_change_id)";
	else
		$sql .= "NULL)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// alte Tabellen loeschen

$sql = "drop table if exists adm_ankuendigungen";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_termine";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_mitglieder";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_gruppierung";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_photo";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_rolle";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_session";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists tmp_user_data";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_user_field";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_user";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());
$sql = "drop table if exists adm_new_user";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

?>