/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- Rollentabelle anpassen
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_moderation` `rol_assign_roles` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_approve_users` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_assign_roles`;
ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_valid`;
ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_all_lists_view` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_locked`;
ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_this_list_view` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_locked`;
UPDATE %PREFIX%_roles SET `rol_this_list_view` = 1 WHERE `rol_locked` = 0;
ALTER TABLE %PREFIX%_roles DROP COLUMN `rol_locked`;


-- Tabelle user_fields erweitern
ALTER TABLE %PREFIX%_user_fields DROP FOREIGN KEY %PREFIX%_FK_USF_ORG;
ALTER TABLE %PREFIX%_user_fields DROP index USF_ORG_FK;

ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_cat_id` int(11) unsigned AFTER `usf_id`;
ALTER TABLE %PREFIX%_user_fields ADD index USF_CAT_FK (usf_cat_id);
ALTER TABLE %PREFIX%_user_fields ADD constraint FK_USF_CAT foreign key (usf_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_description`;
ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_locked` `usf_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_disabled` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_system`;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_mandatory` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_hidden`;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_sequence` smallint NOT NULL AFTER `usf_mandatory`;

-- User-Tabelle ergaenzen
ALTER TABLE %PREFIX%_users ADD COLUMN `usr_text` text AFTER `usr_photo`;
ALTER TABLE %PREFIX%_users ADD COLUMN `usr_activation_code` VARCHAR(10) AFTER `usr_text`;
ALTER TABLE %PREFIX%_users ADD COLUMN `usr_new_password` VARCHAR(35) AFTER `usr_password`;
ALTER TABLE %PREFIX%_users MODIFY COLUMN `usr_login_name` VARCHAR(35) DEFAULT NULL;

-- Dates-Tabelle ergaenzen
ALTER TABLE %PREFIX%_dates ADD COLUMN `dat_all_day` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `dat_end`;
ALTER TABLE %PREFIX%_dates MODIFY COLUMN `dat_begin` DATETIME NOT NULL;
ALTER TABLE %PREFIX%_dates MODIFY COLUMN `dat_end` DATETIME NOT NULL;

-- Photos-Tabelle ergaenzen
ALTER TABLE %PREFIX%_photos MODIFY COLUMN `pho_begin` DATE NOT NULL;
ALTER TABLE %PREFIX%_photos MODIFY COLUMN `pho_end` DATE NOT NULL;

-- Announcement-Tabelle korrigieren
ALTER TABLE %PREFIX%_announcements DROP FOREIGN KEY %PREFIX%_FK_ANN_USR;
ALTER TABLE %PREFIX%_announcements ADD constraint %PREFIX%_FK_ANN_USR foreign key (ann_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

-- Session-Tabelle ergaenzen
DELETE FROM %PREFIX%_sessions;
ALTER TABLE %PREFIX%_sessions ADD COLUMN `ses_begin` datetime NOT NULL AFTER `ses_session`;
ALTER TABLE %PREFIX%_sessions ADD COLUMN `ses_renew` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `ses_blob`;
ALTER TABLE %PREFIX%_sessions MODIFY COLUMN `ses_usr_id` INTEGER UNSIGNED DEFAULT NULL;
ALTER TABLE %PREFIX%_sessions MODIFY COLUMN `ses_ip_address` VARCHAR(15) NOT NULL;

-- org_shortname in org_id in Sessiontabelle umwandeln
ALTER TABLE %PREFIX%_sessions DROP FOREIGN KEY %PREFIX%_FK_SES_ORG;
ALTER TABLE %PREFIX%_sessions DROP INDEX SES_ORG_FK;
ALTER TABLE %PREFIX%_sessions DROP COLUMN ses_org_shortname;

ALTER TABLE %PREFIX%_sessions ADD COLUMN `ses_org_id` tinyint(4) NOT NULL AFTER `ses_id`;
ALTER TABLE %PREFIX%_sessions ADD index SES_ORG_FK (ses_org_id);
ALTER TABLE %PREFIX%_sessions ADD constraint %PREFIX%_FK_SES_ORG foreign key (ses_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_session` `ses_session_id` VARCHAR(35) NOT NULL;
ALTER TABLE %PREFIX%_sessions DROP INDEX ak_session;
ALTER TABLE %PREFIX%_sessions ADD INDEX ak_session (ses_session_id);

-- org_shortname aus Rollentabelle entfernen
ALTER TABLE %PREFIX%_roles DROP FOREIGN KEY %PREFIX%_FK_ROL_ORG;
ALTER TABLE %PREFIX%_roles DROP INDEX ROL_ORG_FK;
ALTER TABLE %PREFIX%_roles DROP COLUMN rol_org_shortname;

-- Links-Tabelle korrigieren
ALTER TABLE %PREFIX%_links DROP FOREIGN KEY %PREFIX%_FK_LNK_ORG;
ALTER TABLE %PREFIX%_links DROP index LNK_ORG_FK;
ALTER TABLE %PREFIX%_links DROP COLUMN lnk_org_id;
ALTER TABLE %PREFIX%_links DROP FOREIGN KEY %PREFIX%_FK_LNK_USR;
ALTER TABLE %PREFIX%_links ADD constraint %PREFIX%_FK_LNK_USR foreign key (lnk_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

-- Kategorie-Tabelle anpassen
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_org_id` `cat_org_id` tinyint(4);
ALTER TABLE %PREFIX%_categories ADD COLUMN `cat_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `cat_hidden`;
ALTER TABLE %PREFIX%_categories ADD COLUMN `cat_sequence` smallint NOT NULL AFTER `cat_system`;

-- usd_id entfernen, da sie ueberfluessig ist
ALTER TABLE %PREFIX%_user_data DROP INDEX ak_usr_usf_id;
ALTER TABLE %PREFIX%_user_data DROP COLUMN usd_id, DROP PRIMARY KEY;
ALTER TABLE %PREFIX%_user_data ADD PRIMARY KEY(usd_usr_id, usd_usf_id);

-- Tabelle Einstellungen anpassen
ALTER TABLE %PREFIX%_preferences ADD UNIQUE ak_org_id_name (prf_org_id, prf_name);

-- Tabellen fuer den Downloadbereich erstellen

/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
create table %PREFIX%_folders
(
    fol_id                         int(11) unsigned               not null AUTO_INCREMENT,
    fol_org_id                     tinyint(4)                     not null,
    fol_fol_id_parent              int(11) unsigned,
    fol_type                       varchar(10)                    not null,
    fol_name                       varchar(255)                   not null,
    fol_path                       varchar(255)                   not null,
    fol_locked                     tinyint (1) unsigned           not null default 0,
    fol_public                     tinyint (1) unsigned           not null default 0,
    fol_timestamp                  datetime                       not null,
    fol_usr_id                     int(11) unsigned,
    primary key (fol_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PREFIX%_folders add index FOL_ORG_FK (fol_org_id);
alter table %PREFIX%_folders add index FOL_FOL_PARENT_FK (fol_fol_id_parent);
alter table %PREFIX%_folders add index FOL_USR_FK (fol_usr_id);

-- Constraints
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_FOL_PARENT foreign key (fol_fol_id_parent)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_USR foreign key (fol_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
create table %PREFIX%_files
(
    fil_id                         int(11) unsigned               not null AUTO_INCREMENT,
    fil_fol_id                     int(11) unsigned               not null,
    fil_name                       varchar(255)                   not null,
    fil_locked                     tinyint(1) unsigned            not null default 0,
    fil_counter                    int,
    fil_timestamp                  datetime                       not null,
    fil_usr_id                     int(11) unsigned,
    primary key (fil_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PREFIX%_files add index FIL_FOL_FK (fil_fol_id);
alter table %PREFIX%_files add index FIL_USR_FK (fil_usr_id);

-- Constraints
alter table %PREFIX%_files add constraint %PREFIX%_FK_FIL_FOL foreign key (fil_fol_id)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PREFIX%_files add constraint %PREFIX%_FK_FIL_USR foreign key (fil_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_folder_roles                                      */
/*==============================================================*/
create table %PREFIX%_folder_roles
(
    flr_fol_id                     int(11) unsigned               not null,
    flr_rol_id                     int(11) unsigned               not null,
    primary key (flr_fol_id, flr_rol_id)
)
engine = InnoDB;

-- Index
alter table %PREFIX%_folder_roles add index FLR_FOL_FK (flr_fol_id);
alter table %PREFIX%_folder_roles add index FLR_ROL_FK (flr_rol_id);

-- Constraints
alter table %PREFIX%_folder_roles add constraint %PREFIX%_FK_FLR_FOL foreign key (flr_fol_id)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;

alter table %PREFIX%_folder_roles add constraint %PREFIX%_FK_FLR_ROL foreign key (flr_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_auto_login                                        */
/*==============================================================*/
create table %PREFIX%_auto_login
(
    atl_session_id                 varchar(35)                    not null,
    atl_org_id                     tinyint(4)                     not null,
    atl_usr_id                     int(11) unsigned               not null,
    atl_last_login                 datetime                       not null,
    atl_ip_address                 varchar(15)                    not null,
    primary key (atl_session_id)
)
engine = InnoDB;

-- Index
alter table %PREFIX%_auto_login add index ATL_USR_FK (atl_usr_id);
alter table %PREFIX%_auto_login add index ATL_ORG_FK (atl_org_id);

-- Constraints
alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_USR foreign key (atl_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_ORG foreign key (atl_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
