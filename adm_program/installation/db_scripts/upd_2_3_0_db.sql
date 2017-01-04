/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- Tabellen erweitern
ALTER TABLE %PREFIX%_categories ADD COLUMN `cat_default` tinyint (1) unsigned not null default 0 AFTER cat_system;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_value_list` text AFTER usf_description;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_icon` varchar(255) AFTER usf_value_list;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_url` varchar(255) AFTER usf_icon;
ALTER TABLE %PREFIX%_roles DROP COLUMN `rol_inventory`;

ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_description` `room_description_old` varchar(255);
ALTER TABLE %PREFIX%_rooms ADD COLUMN `room_description` text AFTER room_name;
UPDATE %PREFIX%_rooms SET room_description = room_description_old;
ALTER TABLE %PREFIX%_rooms DROP COLUMN room_description_old;

-- Autoincrement-Spalte fuer adm_auto_login anlegen
ALTER TABLE %PREFIX%_auto_login DROP FOREIGN KEY %PREFIX%_FK_ATL_USR;
ALTER TABLE %PREFIX%_auto_login DROP FOREIGN KEY %PREFIX%_FK_ATL_ORG ;

drop table if exists %PREFIX%_auto_login_old;
RENAME TABLE %PREFIX%_auto_login TO %PREFIX%_auto_login_old;

create table %PREFIX%_auto_login
(
    atl_id                         integer       unsigned not null AUTO_INCREMENT,
    atl_session_id                 varchar(35)   not null,
    atl_org_id                     integer       unsigned not null,
    atl_usr_id                     integer       unsigned not null,
    atl_last_login                 timestamp     null default null,
    atl_ip_address                 varchar(15)   not null,
    primary key (atl_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;

alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_USR foreign key (atl_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

INSERT INTO %PREFIX%_auto_login (atl_session_id, atl_org_id, atl_usr_id, atl_last_login, atl_ip_address)
SELECT atl_session_id, atl_org_id, atl_usr_id, atl_last_login, atl_ip_address
  FROM %PREFIX%_auto_login_old;

DROP TABLE %PREFIX%_auto_login_old;

-- Datentypen von einigen Spalten aendern
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_id` `ann_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_global` `ann_global` char(1) not null default '0';
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_usr_id_create` `ann_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_timestamp_create` `ann_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_usr_id_change` `ann_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_timestamp_change` `ann_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_id` `cat_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_usr_id_create` `cat_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_timestamp_create` `cat_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_usr_id_change` `cat_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_timestamp_change` `cat_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_date_role CHANGE COLUMN  `dtr_id` `dtr_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_date_role CHANGE COLUMN `dtr_dat_id` `dtr_dat_id` integer unsigned not null;
ALTER TABLE %PREFIX%_date_role CHANGE COLUMN `dtr_rol_id` `dtr_rol_id` integer unsigned;

ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_id` `dat_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_cat_id` `dat_cat_id` integer unsigned not null;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_begin` `dat_begin` timestamp     null default null;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_end` `dat_end` timestamp     null default null;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_usr_id_create` `dat_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_timestamp_create` `dat_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_usr_id_change` `dat_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_timestamp_change` `dat_timestamp_change` timestamp null default null;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_rol_id` `dat_rol_id` integer unsigned;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_room_id` `dat_room_id` integer unsigned;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_max_members` `dat_max_members` integer not null default 0;

ALTER TABLE %PREFIX%_files CHANGE COLUMN `fil_id` `fil_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_files CHANGE COLUMN `fil_fol_id` `fil_fol_id` integer unsigned not null;
ALTER TABLE %PREFIX%_files CHANGE COLUMN `fil_usr_id` `fil_usr_id` integer unsigned;
ALTER TABLE %PREFIX%_files CHANGE COLUMN `fil_timestamp` `fil_timestamp` timestamp not null default CURRENT_TIMESTAMP;

ALTER TABLE %PREFIX%_folder_roles CHANGE COLUMN `flr_fol_id` `flr_fol_id` integer unsigned not null;
ALTER TABLE %PREFIX%_folder_roles CHANGE COLUMN `flr_rol_id` `flr_rol_id` integer unsigned not null;

ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_id` `fol_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_fol_id_parent` `fol_fol_id_parent` integer unsigned;
ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_usr_id` `fol_usr_id` integer unsigned;
ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_timestamp` `fol_timestamp` timestamp not null default CURRENT_TIMESTAMP;

ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_timestamp_change` `gbo_timestamp_change` timestamp null default null;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_id` `gbo_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_usr_id_create` `gbo_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_timestamp_create` `gbo_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_usr_id_change` `gbo_usr_id_change` integer unsigned;

ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_timestamp_change` `gbc_timestamp_change` timestamp null default null;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_id` `gbc_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_gbo_id` `gbc_gbo_id` integer unsigned not null;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_usr_id_create` `gbc_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_timestamp_create` `gbc_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_usr_id_change` `gbc_usr_id_change` integer unsigned;

ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_id` `lnk_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_cat_id` `lnk_cat_id` integer unsigned not null;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_usr_id_create` `lnk_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_timestamp_create` `lnk_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_usr_id_change` `lnk_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_timestamp_change` `lnk_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_lists CHANGE COLUMN `lst_id` `lst_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_lists CHANGE COLUMN `lst_usr_id` `lst_usr_id` integer unsigned not null;
ALTER TABLE %PREFIX%_lists CHANGE COLUMN `lst_timestamp` `lst_timestamp` timestamp not null default CURRENT_TIMESTAMP;

ALTER TABLE %PREFIX%_list_columns CHANGE COLUMN `lsc_id` `lsc_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_list_columns CHANGE COLUMN `lsc_lst_id` `lsc_lst_id` integer unsigned not null;
ALTER TABLE %PREFIX%_list_columns CHANGE COLUMN `lsc_usf_id` `lsc_usf_id` integer unsigned;

ALTER TABLE %PREFIX%_members CHANGE COLUMN `mem_id` `mem_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_members CHANGE COLUMN `mem_rol_id` `mem_rol_id` integer unsigned not null;
ALTER TABLE %PREFIX%_members CHANGE COLUMN `mem_usr_id` `mem_usr_id` integer unsigned not null;

ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_id` `pho_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_quantity` `pho_quantity` integer unsigned not null default 0;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_pho_id_parent` `pho_pho_id_parent` integer unsigned;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_usr_id_create` `pho_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_timestamp_create` `pho_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_usr_id_change` `pho_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_timestamp_change` `pho_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_preferences CHANGE COLUMN `prf_id` `prf_id` integer unsigned not null AUTO_INCREMENT;

ALTER TABLE %PREFIX%_role_dependencies CHANGE COLUMN `rld_rol_id_parent` `rld_rol_id_parent` integer unsigned not null;
ALTER TABLE %PREFIX%_role_dependencies CHANGE COLUMN `rld_rol_id_child` `rld_rol_id_child` integer unsigned not null;
ALTER TABLE %PREFIX%_role_dependencies CHANGE COLUMN `rld_usr_id` `rld_usr_id` integer unsigned;
ALTER TABLE %PREFIX%_role_dependencies CHANGE COLUMN `rld_timestamp` `rld_timestamp` timestamp not null default CURRENT_TIMESTAMP;

ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_id` `rol_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_name` `rol_name` varchar(50) not null;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_cat_id` `rol_cat_id` integer unsigned;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_mail_this_role` `rol_mail_this_role` smallint not null default 0;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_this_list_view` `rol_this_list_view` smallint not null default 0;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_max_members` `rol_max_members` integer;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_weekday` `rol_weekday` smallint;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_cost_period` `rol_cost_period` smallint;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_usr_id_create` `rol_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_timestamp_create` `rol_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_usr_id_change` `rol_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_timestamp_change` `rol_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_id` `room_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_capacity` `room_capacity` integer unsigned not null default 0;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_overhang` `room_overhang` integer unsigned;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_usr_id_create` `room_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_timestamp_create` `room_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_usr_id_change` `room_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_rooms CHANGE COLUMN `room_timestamp_change` `room_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_id` `ses_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_usr_id` `ses_usr_id` integer unsigned default null;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_begin` `ses_begin` timestamp null default null;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_timestamp` `ses_timestamp` timestamp null default null;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_blob` `ses_binary` blob;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_renew` `ses_renew` smallint not null default 0;

ALTER TABLE %PREFIX%_texts CHANGE COLUMN `txt_id` `txt_id` integer unsigned not null AUTO_INCREMENT;

ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_id` `usf_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_cat_id` `usf_cat_id` integer unsigned not null;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_usr_id_create` `usf_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_type` `usf_type` varchar(30);
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_timestamp_create` `usf_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_usr_id_change` `usf_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_timestamp_change` `usf_timestamp_change` timestamp null default null;

ALTER TABLE %PREFIX%_user_data CHANGE COLUMN `usd_id` `usd_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_user_data CHANGE COLUMN `usd_usr_id` `usd_usr_id` integer unsigned not null;
ALTER TABLE %PREFIX%_user_data CHANGE COLUMN `usd_usf_id` `usd_usf_id` integer unsigned not null;

ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_id` `usr_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_last_login` `usr_last_login` timestamp null default null;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_actual_login` `usr_actual_login` timestamp null default null;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_number_login` `usr_number_login` integer not null default 0;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_date_invalid` `usr_date_invalid` timestamp null default null;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_number_invalid` `usr_number_invalid` smallint not null default 0;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_usr_id_create` `usr_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_timestamp_create` `usr_timestamp_create` timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_usr_id_change` `usr_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_timestamp_change` `usr_timestamp_change` timestamp null default null;

-- Org_Id wird nun auch ein Index vom Typ INTEGER
ALTER TABLE %PREFIX%_categories DROP FOREIGN KEY %PREFIX%_FK_CAT_ORG;
ALTER TABLE %PREFIX%_folders DROP FOREIGN KEY %PREFIX%_FK_FOL_ORG;
ALTER TABLE %PREFIX%_guestbook DROP FOREIGN KEY %PREFIX%_FK_GBO_ORG;
ALTER TABLE %PREFIX%_lists DROP FOREIGN KEY %PREFIX%_FK_LST_ORG;
ALTER TABLE %PREFIX%_organizations DROP FOREIGN KEY %PREFIX%_FK_ORG_ORG_PARENT;
ALTER TABLE %PREFIX%_preferences DROP FOREIGN KEY %PREFIX%_FK_PRF_ORG;
ALTER TABLE %PREFIX%_sessions DROP FOREIGN KEY %PREFIX%_FK_SES_ORG;
ALTER TABLE %PREFIX%_texts DROP FOREIGN KEY %PREFIX%_FK_TXT_ORG;

ALTER TABLE %PREFIX%_auto_login CHANGE COLUMN `atl_org_id` `atl_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_org_id` `cat_org_id` integer unsigned;
ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_org_id` `fol_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_org_id` `gbo_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_lists CHANGE COLUMN `lst_org_id` `lst_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_organizations CHANGE COLUMN `org_id` `org_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_organizations CHANGE COLUMN `org_org_id_parent` `org_org_id_parent` integer unsigned;
ALTER TABLE %PREFIX%_preferences CHANGE COLUMN `prf_org_id` `prf_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_org_id` `ses_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_texts CHANGE COLUMN `txt_org_id` `txt_org_id` integer unsigned not null;

alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_ORG foreign key (atl_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_ORG foreign key (gbo_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_lists add constraint %PREFIX%_FK_LST_ORG foreign key (lst_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_organizations add constraint %PREFIX%_FK_ORG_ORG_PARENT foreign key (org_org_id_parent)
      references %PREFIX%_organizations (org_id) on delete set null on update restrict;
alter table %PREFIX%_preferences add constraint %PREFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_sessions add constraint %PREFIX%_FK_SES_ORG foreign key (ses_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_texts add constraint %PREFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

-- manipulate data
UPDATE %PREFIX%_user_fields SET usf_system = 0, usf_name = 'SYS_GENDER', usf_type = 'RADIO_BUTTON', usf_value_list = 'male.png|SYS_MALE\r\nfemale.png|SYS_FEMALE'
 WHERE usf_name_intern LIKE 'GENDER';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_LASTNAME' WHERE usf_name_intern LIKE 'LAST_NAME';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_FIRSTNAME' WHERE usf_name_intern LIKE 'FIRST_NAME';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_ADDRESS' WHERE usf_name_intern LIKE 'ADDRESS';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_POSTCODE' WHERE usf_name_intern LIKE 'POSTCODE';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_CITY' WHERE usf_name_intern LIKE 'CITY';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_COUNTRY' WHERE usf_name_intern LIKE 'COUNTRY';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_PHONE' WHERE usf_name_intern LIKE 'PHONE';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_MOBILE' WHERE usf_name_intern LIKE 'MOBILE';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_FAX' WHERE usf_name_intern LIKE 'FAX';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_BIRTHDAY' WHERE usf_name_intern LIKE 'BIRTHDAY';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_EMAIL' WHERE usf_name_intern LIKE 'EMAIL';
UPDATE %PREFIX%_user_fields SET usf_name = 'SYS_WEBSITE' WHERE usf_name_intern LIKE 'WEBSITE';

UPDATE %PREFIX%_preferences SET prf_value = 'da' WHERE prf_name like 'system_language' AND prf_value like 'dk';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'captcha_font_size';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'enable_bbcode';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'enable_ecard_text_length';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'ecard_text_length';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'ecard_text_font';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'ecard_text_size';
DELETE FROM %PREFIX%_preferences WHERE prf_name like 'ecard_text_color';
UPDATE %PREFIX%_preferences SET prf_name = 'captcha_font_size' WHERE prf_name like 'captcha_text_size';
UPDATE %PREFIX%_organizations SET org_homepage = 'http://' || org_homepage WHERE lower( substring( org_homepage, 1, 4 ) ) NOT LIKE 'http';

-- replace category name with translation id
UPDATE %PREFIX%_categories SET cat_name = 'SYS_MASTER_DATA' WHERE cat_name_intern = 'MASTER_DATA';
UPDATE %PREFIX%_categories SET cat_name = 'SYS_COMMON', cat_name_intern = 'COMMON' WHERE cat_name_intern IN ('COMMON', 'ALLGEMEIN');
UPDATE %PREFIX%_categories SET cat_name = 'INS_GROUPS', cat_name_intern = 'GROUPS' WHERE cat_name_intern IN ('GROUPS', 'GRUPPEN');
UPDATE %PREFIX%_categories SET cat_name = 'INS_COURSES', cat_name_intern = 'COURSES' WHERE cat_name_intern IN ('COURSES', 'KURSE');
UPDATE %PREFIX%_categories SET cat_name = 'INS_TEAMS', cat_name_intern = 'TEAMS' WHERE cat_name_intern IN ('TEAMS', 'MANNSCHAFTEN');
UPDATE %PREFIX%_categories SET cat_name = 'SYS_CONFIRMATION_OF_PARTICIPATION' WHERE cat_name_intern = 'CONFIRMATION_OF_PARTICIPATION';
UPDATE %PREFIX%_categories SET cat_name = 'INS_INTERN' WHERE cat_name_intern = 'INTERN';
UPDATE %PREFIX%_categories SET cat_name = 'INS_TRAINING' WHERE cat_name_intern = 'TRAINING';
UPDATE %PREFIX%_categories SET cat_name = 'INS_ADDIDIONAL_DATA', cat_name_intern = 'ADDIDIONAL_DATA' WHERE cat_name_intern IN ('ADDIDIONAL_DATA', 'ZUSÄTZLICHE_DATEN');

-- replace BB-Code with html for the new ckeditor
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(REPLACE(ann_description, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_announcements SET ann_description = REPLACE(ann_description, ']', '">');

UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(REPLACE(dat_description, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_dates SET dat_description = REPLACE(dat_description, ']', '">');

UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(REPLACE(gbo_text, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_guestbook SET gbo_text = REPLACE(gbo_text, ']', '">');

UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(REPLACE(gbc_text, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_guestbook_comments SET gbc_text = REPLACE(gbc_text, ']', '">');

UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(REPLACE(lnk_description, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_links SET lnk_description = REPLACE(lnk_description, ']', '">');

UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/b]', '</b>'), '[b]', '<b>');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/i]', '</i>'), '[i]', '<i>');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/u]', '</u>'), '[u]', '<u>');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/big]', '</span>'), '[big]', '<span style="font-size:14pt">');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/small]', '</span>'), '[small]', '<span style="font-size:9pt">');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/center]', '</p>'), '[center]', '<p style="text-align: center">');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/img]', '" />'), '[img]', '<img src="');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/url]', '</a>'), '[url=', '<a href="');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(REPLACE(room_description, '[/email]', '</a>'), '[email=', '<a href="mailto:');
UPDATE %PREFIX%_rooms SET room_description = REPLACE(room_description, ']', '">');
