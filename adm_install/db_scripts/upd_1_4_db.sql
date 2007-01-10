
-- Kategorientabelle konvertieren
ALTER TABLE %PRAEFIX%_role_categories DROP FOREIGN KEY %PRAEFIX%_FK_RLC_ORG;
ALTER TABLE %PRAEFIX%_roles DROP FOREIGN KEY %PRAEFIX%_FK_ROL_RLC;

create table %PRAEFIX%_categories
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

alter table %PRAEFIX%_categories add index CAT_ORG_FK (cat_org_id);

ALTER TABLE %PRAEFIX%_roles DROP index ROL_RLC_FK;
ALTER TABLE %PRAEFIX%_roles CHANGE COLUMN `rol_rlc_id` `rol_cat_id` INTEGER(11) UNSIGNED NOT NULL DEFAULT 0;
alter table %PRAEFIX%_roles add index ROL_CAT_FK (rol_cat_id);
ALTER TABLE %PRAEFIX%_roles ADD COLUMN `rol_profile` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_photo`;

ALTER TABLE %PRAEFIX%_links ADD COLUMN `lnk_cat_id` INTEGER(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `lnk_org_id`;
alter table %PRAEFIX%_links add index LNK_CAT_FK (lnk_cat_id);

alter table %PRAEFIX%_categories add constraint %PRAEFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;

-- Texttabelle
create table %PRAEFIX%_texts
(
   txt_id                         int(11) unsigned               not null,
   txt_org_id                     tinyint(4)                     not null,
   txt_name                       varchar(30)                    not null,
   txt_text                       text,
   primary key (txt_id)
)
type = InnoDB
auto_increment = 1;

alter table %PRAEFIX%_texts add index TXT_ORG_FK (txt_org_id);

alter table %PRAEFIX%_texts add constraint %PRAEFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;

-- Guestbook-Comments erweitern
ALTER TABLE %PRAEFIX%_guestbook_comments
 ADD COLUMN gbc_name VARCHAR(50) NOT NULL DEFAULT '' AFTER `gbc_usr_id`,
 ADD COLUMN gbc_email VARCHAR(50) AFTER `gbc_text`,
 ADD COLUMN gbc_ip_address VARCHAR(15) NOT NULL DEFAULT '0' AFTER `gbc_email`,
 ADD COLUMN gbc_last_change DATETIME AFTER `gbc_ip_address`,
 ADD COLUMN gbc_usr_id_change INTEGER(11) UNSIGNED AFTER `gbc_last_change`;

alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_USR_CHANGE foreign key (gbc_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

-- Sessiontabelle bearbeiten
ALTER TABLE %PRAEFIX%_sessions DROP COLUMN ses_longer_session;
ALTER TABLE %PRAEFIX%_sessions ADD COLUMN ses_blob blob AFTER ses_ip_address;