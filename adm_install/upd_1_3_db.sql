ALTER TABLE %PRAEFIX%_roles ADD rol_announcements TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER rol_moderation;
ALTER TABLE %PRAEFIX%_roles ADD rol_guestbook_comments TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER rol_edit_user;
ALTER TABLE %PRAEFIX%_roles ADD rol_guestbook     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER rol_edit_user;
ALTER TABLE %PRAEFIX%_roles ADD rol_weblinks      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER rol_photo;
ALTER TABLE %PRAEFIX%_users ADD usr_photo blob AFTER usr_password;

create table %PRAEFIX%_folders
(
   fol_id                         int(11) unsigned               not null AUTO_INCREMENT,
   fol_org_id                     tinyint(4)                     not null,
   fol_fol_id_parent              int(11) unsigned,
   fol_type                       varchar(10)                    not null,
   fol_name                       varchar(255)                   not null,
   primary key (fol_id)
)
type = InnoDB
auto_increment = 1;
alter table %PRAEFIX%_folders add index FOL_ORG_FK (fol_org_id);
alter table %PRAEFIX%_folders add index FOL_FOL_PARENT_FK (fol_fol_id_parent);

create table %PRAEFIX%_folder_roles
(
   flr_fol_id                     int(11) unsigned               not null,
   flr_rol_id                     int(11) unsigned               not null,
   primary key (flr_fol_id, flr_rol_id)
)
type = InnoDB;
alter table %PRAEFIX%_folder_roles add index FLR_FOL_FK (flr_fol_id);
alter table %PRAEFIX%_folder_roles add index FOL_ROL_FK (flr_rol_id);

create table %PRAEFIX%_guestbook
(
   gbo_id                         int(11) unsigned               not null AUTO_INCREMENT,
   gbo_org_id                     tinyint(4)                     not null,
   gbo_usr_id                     int(11) unsigned,
   gbo_name                       varchar(60)                    not null,
   gbo_text                       text                           not null,
   gbo_email                      varchar(50),
   gbo_homepage                   varchar(50),
   gbo_timestamp                  datetime                       not null,
   gbo_ip_address                 varchar(15)                    not null,
   gbo_last_change                datetime,
   gbo_usr_id_change              int(11) unsigned,
   primary key (gbo_id)
)
type = InnoDB;
alter table %PRAEFIX%_guestbook add index GBO_ORG_FK (gbo_org_id);
alter table %PRAEFIX%_guestbook add index GBO_USR_FK (gbo_usr_id);
alter table %PRAEFIX%_guestbook add index GBO_USR_CHANGE_FK (gbo_usr_id_change);

create table %PRAEFIX%_guestbook_comments
(
   gbc_id                         int(11) unsigned               not null AUTO_INCREMENT,
   gbc_gbo_id                     int(11) unsigned               not null,
   gbc_usr_id                     int(11) unsigned               not null,
   gbc_text                       text                           not null,
   gbc_timestamp                  datetime                       not null,
   primary key (gbc_id)
)
type = InnoDB;
alter table %PRAEFIX%_guestbook_comments add index GBC_GBO_FK (gbc_gbo_id);
alter table %PRAEFIX%_guestbook_comments add index GBC_USR_FK (gbc_usr_id);

create table %PRAEFIX%_links
(
   lnk_id                         int(11) unsigned               not null AUTO_INCREMENT,
   lnk_org_id                     tinyint(4)                     not null,
   lnk_name                       varchar(255)                   not null,
   lnk_description                text,
   lnk_url                        varchar(255)                   not null,
   lnk_usr_id                     int(11) unsigned,
   lnk_timestamp                  datetime                       not null,
   primary key (lnk_id)
)
type = InnoDB;
alter table %PRAEFIX%_links add index LNK_ORG_FK (lnk_org_id);
alter table %PRAEFIX%_links add index LNK_USR_FK (lnk_usr_id);

create table %PRAEFIX%_preferences
(
   prf_id                         int(11) unsigned               not null AUTO_INCREMENT,
   prf_org_id                     tinyint(4)                     not null,
   prf_name                       varchar(30)                    not null,
   prf_value                      varchar(255),
   primary key (prf_id)
)
type = InnoDB;
alter table %PRAEFIX%_preferences add index PRF_ORG_FK (prf_org_id);

alter table %PRAEFIX%_folder_roles add constraint %PRAEFIX%_FK_FOL_ROL foreign key (flr_rol_id)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_folder_roles add constraint %PRAEFIX%_FK_FLR_FOL foreign key (flr_fol_id)
      references %PRAEFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_folders add constraint %PRAEFIX%_FK_FOL_FOL_PARENT foreign key (fol_fol_id_parent)
      references %PRAEFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_folders add constraint %PRAEFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_ORG foreign key (gbo_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_USR foreign key (gbo_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_USR_CHANGE foreign key (gbo_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_GBO foreign key (gbc_gbo_id)
      references %PRAEFIX%_guestbook (gbo_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_USR foreign key (gbc_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_ORG foreign key (lnk_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_USR foreign key (lnk_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_preferences add constraint %PRAEFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;