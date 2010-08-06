
-- Kategorientabelle konvertieren
ALTER TABLE %PREFIX%_role_categories DROP FOREIGN KEY %PREFIX%_FK_RLC_ORG;
ALTER TABLE %PREFIX%_roles DROP FOREIGN KEY %PREFIX%_FK_ROL_RLC;

create table %PREFIX%_categories
(
   cat_id                         int (11) unsigned              not null AUTO_INCREMENT,
   cat_org_id                     tinyint(4)                     not null,
   cat_type                       varchar(10)                    not null,
   cat_name                       varchar(30)                    not null,
   cat_hidden                     tinyint(1) unsigned            not null default 0,
   primary key (cat_id)
)
type = InnoDB
auto_increment = 1;

alter table %PREFIX%_categories add index CAT_ORG_FK (cat_org_id);

ALTER TABLE %PREFIX%_roles DROP index ROL_RLC_FK;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_rlc_id` `rol_cat_id` INTEGER(11) UNSIGNED NOT NULL DEFAULT 0;
alter table %PREFIX%_roles add index ROL_CAT_FK (rol_cat_id);
ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_profile` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_photo`;

ALTER TABLE %PREFIX%_links ADD COLUMN `lnk_cat_id` INTEGER(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `lnk_org_id`;
alter table %PREFIX%_links add index LNK_CAT_FK (lnk_cat_id);

alter table %PREFIX%_links add index LNK_USR_CHANGE_FK (lnk_usr_id_change);

alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
      
alter table %PREFIX%_links add constraint %PREFIX%_FK_LNK_USR_CHANGE foreign key (lnk_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;      

-- Texttabelle
create table %PREFIX%_texts
(
   txt_id                         int(11) unsigned               not null,
   txt_org_id                     tinyint(4)                     not null,
   txt_name                       varchar(30)                    not null,
   txt_text                       text,
   primary key (txt_id)
)
type = InnoDB
auto_increment = 1;

alter table %PREFIX%_texts add index TXT_ORG_FK (txt_org_id);

alter table %PREFIX%_texts add constraint %PREFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

-- Guestbook-Comments erweitern
ALTER TABLE %PREFIX%_guestbook_comments
 ADD COLUMN gbc_name VARCHAR(50) NOT NULL DEFAULT '' AFTER `gbc_usr_id`,
 ADD COLUMN gbc_email VARCHAR(50) AFTER `gbc_text`,
 ADD COLUMN gbc_ip_address VARCHAR(15) NOT NULL DEFAULT '0' AFTER `gbc_email`,
 ADD COLUMN gbc_last_change DATETIME AFTER `gbc_ip_address`,
 ADD COLUMN gbc_usr_id_change INTEGER(11) UNSIGNED AFTER `gbc_last_change`;
 
ALTER TABLE %PREFIX%_guestbook_comments MODIFY COLUMN `gbc_usr_id` INTEGER UNSIGNED;

alter table %PREFIX%_guestbook_comments add index GBC_USR_CHANGE_FK (gbc_usr_id_change);
alter table %PREFIX%_guestbook_comments add constraint %PREFIX%_FK_GBC_USR_CHANGE foreign key (gbc_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

-- Sessiontabelle bearbeiten
ALTER TABLE %PREFIX%_sessions DROP COLUMN ses_longer_session;
ALTER TABLE %PREFIX%_sessions ADD  COLUMN ses_blob blob AFTER ses_ip_address;

-- Memberstabelle pflegen
UPDATE %PREFIX%_members SET mem_end = NULL WHERE mem_end = '0000-00-00';

-- Rollentabelle pflegen
UPDATE %PREFIX%_roles SET rol_start_date = NULL WHERE rol_start_date = '0000-00-00';
UPDATE %PREFIX%_roles SET rol_end_date   = NULL WHERE rol_end_date   = '0000-00-00';
UPDATE %PREFIX%_roles SET rol_start_time = NULL WHERE rol_start_time = '00:00:00';
UPDATE %PREFIX%_roles SET rol_end_time   = NULL WHERE rol_end_time   = '00:00:00';

-- Usertabelle pflegen
UPDATE %PREFIX%_users SET usr_birthday     = NULL WHERE usr_birthday     = '0000-00-00';
UPDATE %PREFIX%_users SET usr_last_login   = NULL WHERE usr_last_login   = '0000-00-00 00:00:00';
UPDATE %PREFIX%_users SET usr_actual_login = NULL WHERE usr_actual_login = '0000-00-00 00:00:00';
UPDATE %PREFIX%_users SET usr_date_invalid = NULL WHERE usr_date_invalid = '0000-00-00 00:00:00';
UPDATE %PREFIX%_users SET usr_last_change  = NULL WHERE usr_last_change  = '0000-00-00 00:00:00';