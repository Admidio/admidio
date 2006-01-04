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
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Gruppe')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$sql = "INSERT INTO adm_role_categories (rlc_org_shortname, rlc_name)
	             VALUES ('$row->ag_shortname', 'Mannschaft')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Fotos

$sql = "SELECT * FROM adm_photo";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Foto in neue Tabelle schreiben
	$sql = "INSERT INTO adm_photos (pho_id, pho_org_shortname, pho_quantity, pho_name, pho_begin, pho_end, pho_photographers, pho_timestamp, pho_last_change)
	             VALUES ($row->ap_id, '$row->ap_ag_shortname', '$row->ap_number', '$row->ap_name', '$row->ap_begin', '$row->ap_end', '$row->ap_photographers', '$row->ap_online_since', '$row->ap_last_change')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Benutzer

$sql = "SELECT * FROM adm_user";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Foto in neue Tabelle schreiben
	$sql = "INSERT INTO adm_users (usr_id, usr_last_name, usr_first_name, usr_address, usr_zip_code, usr_city, usr_country, usr_phone, usr_mobile, usr_fax, usr_birthday,
							  usr_email, usr_homepage, usr_login_name, usr_password, usr_last_login, usr_act_login, usr_num_login,
							  usr_last_change, usr_usr_id_change, usr_valid)
	             VALUES ($row->au_id, '$row->au_name', '$row->au_vorname', '$row->au_adresse', '$row->au_plz', '$row->au_ort', '$row->au_land', '$row->au_tel1', '$row->au_mobil', '$row->au_fax', '$row->au_geburtstag', 
	             		  '$row->au_mail', '$row->au_weburl', ";
	if(strlen($row->au_login) > 0)
		$sql .= "'$row->au_login', '$row->au_password', ";
	else
		$sql .= "NULL, NULL, ";

	if(strlen($row->au_last_login) > 0)
		$sql .= "'$row->au_last_login', ";
	else
		$sql .= "NULL, ";

	if(strlen($row->au_act_login) > 0)
		$sql .= "'$row->au_act_login', ";
	else
		$sql .= "NULL, ";
	             		  
	$sql .= "$row->au_num_login, '$row->au_last_change', ";
	if($row->au_last_change_id > 0)
		$sql .= "$row->au_last_change_id, 1)";
	else
		$sql .= "NULL, 1)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// neue Benutzer

$sql = "SELECT * FROM adm_new_user";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Foto in neue Tabelle schreiben
	$sql = "INSERT INTO adm_users (usr_last_name, usr_first_name, usr_email, usr_login_name, usr_password, usr_reg_org_shortname, usr_valid)
	             VALUES ('$row->anu_name', '$row->anu_vorname', '$row->anu_mail', '$row->anu_login', '$row->anu_password', '$row->anu_ag_shortname', 0)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}


// Rollen

$sql = "SELECT * FROM adm_rolle";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	if($row->ar_gruppe == 1)
		$cat_name = "Gruppe";
	else
		$cat_name = "Allgemein";
		
	$sql = "SELECT rlc_id FROM adm_role_categories
				WHERE rlc_org_shortname = '$row->ar_ag_shortname'
				  AND rlc_name          = '$cat_name'";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
	$row_cat = mysql_fetch_object($result);	        

	// Foto in neue Tabelle schreiben
	$sql = "INSERT INTO adm_roles (rol_id, rol_org_shortname, rol_rlc_id, rol_name, rol_description, rol_moderation, rol_dates, rol_edit_user, rol_photo,
										 	 rol_download, rol_mail_logout, rol_mail_login, rol_locked, rol_start_date, rol_start_time, rol_end_date, rol_end_time, 
											 rol_weekday, rol_location, rol_max_members, rol_cost, rol_last_change, rol_usr_id_change, rol_valid)
	             VALUES ($row->ar_id, '$row->ar_ag_shortname', $row_cat->rlc_id, '$row->ar_funktion', '". mysql_escape_string($row->ar_beschreibung). "', $row->ar_r_moderation, $row->ar_r_termine, $row->ar_r_user_bearbeiten, $row->ar_r_foto,
	             			$row->ar_r_download, $row->ar_r_mail_logout, $row->ar_r_mail_login, $row->ar_r_locked, '$row->ar_datum_von', '$row->ar_zeit_von', '$row->ar_datum_bis', '$row->ar_zeit_bis', ";
	if($row->ar_wochentag > 0)
		$sql .= "$row->ar_wochentag, ";
	else
		$sql .= "NULL, ";
	if(strlen($row->ar_ort) > 0)
		$sql .= "'$row->ar_ort', ";
	else
		$sql .= "NULL, ";
	if($row->ar_max_mitglieder > 0)
		$sql .= "$row->ar_max_mitglieder, ";
	else
		$sql .= "NULL, ";
	if($row->ar_beitrag > 0)
		$sql .= "$row->ar_beitrag, ";
	else
		$sql .= "NULL, ";
	if(strlen($row->ar_last_change) > 0)
		$sql .= "'$row->ar_last_change', ";
	else
		$sql .= "NULL, ";
	if($row->ar_last_change_id > 0)
		$sql .= "$row->ar_last_change_id, $row->ar_valid)";
	else
		$sql .= "NULL,$row->ar_valid)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Mitglieder

$sql = "SELECT * FROM adm_mitglieder";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Mitglieder in neue Tabelle schreiben
	$sql = "INSERT INTO adm_members (mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_valid, mem_leader)
	             VALUES ($row->am_id, $row->am_ar_id, $row->am_au_id, '$row->am_start', '$row->am_ende', $row->am_valid, $row->am_leiter)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Sessions

$sql = "SELECT * FROM adm_session";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Sessions in neue Tabelle schreiben
	$sql = "INSERT INTO adm_sessions (ses_id, ses_usr_id, ses_org_shortname, ses_session, ses_timestamp, ses_longer_session)
	             VALUES ($row->as_id, $row->as_au_id, '$row->as_ag_shortname', '$row->as_session', '$row->as_datetime', $row->as_long_login)";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Benutzerdefinierte Felder

$sql = "SELECT * FROM adm_user_field";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Sessions in neue Tabelle schreiben
	$sql = "INSERT INTO adm_user_fields (usf_id, usf_type, usf_name, usf_description, usf_locked, usf_org_shortname)
	             VALUES ($row->auf_id, '$row->auf_type', '$row->auf_name', '$row->auf_description', $row->auf_locked, ";
	if(strlen($row->auf_ag_shortname) > 0)
		$sql .= "'$row->auf_ag_shortname' )";
	else
		$sql .= "NULL )";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Benutzerdefinierte Felder

$sql = "SELECT * FROM tmp_user_data";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Sessions in neue Tabelle schreiben
	$sql = "INSERT INTO adm_user_data (usd_id, usd_usr_id, usd_usf_id, usd_value)
	             VALUES ($row->aud_id, $row->aud_au_id, $row->aud_auf_id, '$row->aud_value')";
	$result = mysql_query($sql, $connection);
	if(!$result) showError(mysql_error());
}

// Termine

$sql = "SELECT * FROM adm_termine";
$result_org = mysql_query($sql, $connection);
if(!$result_org) showError(mysql_error());

while($row = mysql_fetch_object($result_org))
{
	// Termine in neue Tabelle schreiben
	$sql = "INSERT INTO adm_dates (dat_id, dat_org_shortname, dat_global, dat_begin, dat_end, dat_description, 
								dat_location, dat_headline, dat_usr_id, dat_timestamp, dat_last_change, dat_usr_id_change)
	             VALUES ($row->at_id, '$row->at_ag_shortname', $row->at_global, '$row->at_von', '$row->at_bis', '$row->at_beschreibung',
	             			'$row->at_ort', '$row->at_ueberschrift', $row->at_au_id, '$row->at_timestamp', ";
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
								ann_headline, ann_usr_id, ann_timestamp, ann_last_change, ann_usr_id_change)
	             VALUES ($row->aa_id, '$row->aa_ag_shortname', $row->aa_global, '$row->aa_beschreibung',
	             			'$row->aa_ueberschrift', $row->aa_au_id, '$row->aa_timestamp', ";
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