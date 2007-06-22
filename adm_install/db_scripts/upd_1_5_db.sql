
-- Rollentabelle anpassen
ALTER TABLE %PRAEFIX%_roles CHANGE COLUMN `rol_moderation` `rol_assign_roles` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE %PRAEFIX%_roles ADD COLUMN `rol_approve_users` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_assign_roles`;
ALTER TABLE %PRAEFIX%_roles ADD COLUMN `rol_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `rol_valid`;

-- Tabelle user_fields erweitern
ALTER TABLE %PRAEFIX%_user_fields DROP FOREIGN KEY %PRAEFIX%_FK_USF_ORG;
ALTER TABLE %PRAEFIX%_user_fields DROP index USF_ORG_FK;

ALTER TABLE %PRAEFIX%_user_fields ADD COLUMN `usf_cat_id` int(11) unsigned AFTER `usf_id`;
alter table %PRAEFIX%_user_fields add index USF_CAT_FK (usf_cat_id);
alter table %PRAEFIX%_user_fields add constraint FK_USF_CAT foreign key (usf_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;
ALTER TABLE %PRAEFIX%_user_fields ADD COLUMN `usf_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_description`;
ALTER TABLE %PRAEFIX%_user_fields CHANGE COLUMN `usf_locked` `usf_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE %PRAEFIX%_user_fields ADD COLUMN `usf_disabled` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_system`;
ALTER TABLE %PRAEFIX%_user_fields ADD COLUMN `usf_mandatory` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `usf_hidden`;
ALTER TABLE %PRAEFIX%_user_fields ADD COLUMN `usf_sequence` smallint NOT NULL AFTER `usf_mandatory`;

-- User-Tabelle ergaenzen
ALTER TABLE %PRAEFIX%_users ADD COLUMN `usr_text` text AFTER `usr_photo`;

-- org_shortname aus Rollentabelle entfernen
ALTER TABLE %PRAEFIX%_roles DROP FOREIGN KEY %PRAEFIX%_FK_ROL_ORG;
ALTER TABLE %PRAEFIX%_roles DROP INDEX ROL_ORG_FK;
ALTER TABLE %PRAEFIX%_roles DROP COLUMN rol_org_shortname;

-- Kategorie-Tabelle anpassen
ALTER TABLE %PRAEFIX%_categories CHANGE COLUMN `cat_org_id` `cat_org_id` tinyint(4);
ALTER TABLE %PRAEFIX%_categories ADD COLUMN `cat_system` tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER `cat_hidden`;
ALTER TABLE %PRAEFIX%_categories ADD COLUMN `cat_sequence` smallint NOT NULL AFTER `cat_system`;

-- usd_id entfernen, da sie ueberfluessig ist
ALTER TABLE %PRAEFIX%_user_data DROP INDEX ak_usr_usf_id;
ALTER TABLE %PRAEFIX%_user_data DROP COLUMN usd_id, DROP PRIMARY KEY;
ALTER TABLE %PRAEFIX%_user_data ADD PRIMARY KEY(usd_usr_id, usd_usf_id);

-- Tabellen fuer den Downloadbereich erstellen

/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
create table %PRAEFIX%_folders
(
   fol_id                         int(11) unsigned               not null AUTO_INCREMENT,
   fol_org_id                     tinyint(4)                     not null,
   fol_fol_id_parent              int(11) unsigned,
   fol_type                       varchar(10)                    not null,
   fol_name                       varchar(255)                   not null,
   fol_path                       text                           not null,
   fol_locked                     tinyint (1) unsigned           not null default 0,
   fol_timestamp                  datetime                       not null,
   fol_usr_id                     int(11) unsigned,
   primary key (fol_id)
)
type = InnoDB;      

-- Index
alter table %PRAEFIX%_folders add index FOL_ORG_FK (fol_org_id);
alter table %PRAEFIX%_folders add index FOL_FOL_PARENT_FK (fol_fol_id_parent);
alter table %PRAEFIX%_folders add index FOL_USR_FK (fol_usr_id);

-- Constraints
alter table %PRAEFIX%_folders add constraint %PRAEFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_folders add constraint %PRAEFIX%_FK_FOL_FOL_PARENT foreign key (fol_fol_id_parent)
      references %PRAEFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_folders add constraint %PRAEFIX%_FK_FOL_USR foreign key (fol_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
create table %PRAEFIX%_files
(
   fil_id                         int(11) unsigned               not null,
   fil_fol_id                     int(11) unsigned               not null,
   fil_name                       varchar(255)                   not null,
   fil_locked                     tinyint(1) unsigned            not null default 0,
   fil_counter                    int,
   fil_timestamp                  datetime                       not null,
   fil_usr_id                     int(11) unsigned,
   primary key (fil_id)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_files add index FIL_FOL_FK (fil_fol_id);
alter table %PRAEFIX%_files add index FIL_USR_FK (fil_usr_id);

-- Constraints
alter table %PRAEFIX%_files add constraint %PRAEFIX%_FK_FIL_FOL foreign key (fil_fol_id)
      references %PRAEFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_files add constraint %PRAEFIX%_FK_FIL_USR foreign key (fil_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
/*==============================================================*/
/* Table: adm_folder_roles                                      */
/*==============================================================*/
create table %PRAEFIX%_folder_roles
(
   flr_fol_id                     int(11) unsigned               not null,
   flr_rol_id                     int(11) unsigned               not null,
   primary key (flr_fol_id, flr_rol_id)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_folder_roles add index FLR_FOL_FK (flr_fol_id);
alter table %PRAEFIX%_folder_roles add index FLR_ROL_FK (flr_rol_id);

-- Constraints
alter table %PRAEFIX%_folder_roles add constraint %PRAEFIX%_FK_FLR_FOL foreign key (flr_fol_id)
      references %PRAEFIX%_folders (fol_id) on delete restrict on update restrict;

alter table %PRAEFIX%_folder_roles add constraint %PRAEFIX%_FK_FLR_ROL foreign key (flr_rol_id)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;
