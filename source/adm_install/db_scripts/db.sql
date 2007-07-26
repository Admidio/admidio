/******************************************************************************
 * Skript fuer die MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 ******************************************************************************/

-- Hier ist die Reihenfolge wegen den Constraints wichtig !!!

drop table if exists %PRAEFIX%_photos;

drop table if exists %PRAEFIX%_links;

drop table if exists %PRAEFIX%_guestbook_comments;

drop table if exists %PRAEFIX%_guestbook;

drop table if exists %PRAEFIX%_folder_roles;

drop table if exists %PRAEFIX%_files;

drop table if exists %PRAEFIX%_folders;

drop table if exists %PRAEFIX%_dates;

drop table if exists %PRAEFIX%_announcements;

drop table if exists %PRAEFIX%_members;

drop table if exists %PRAEFIX%_role_dependencies;

drop table if exists %PRAEFIX%_roles;

drop table if exists %PRAEFIX%_sessions;

drop table if exists %PRAEFIX%_user_data;

drop table if exists %PRAEFIX%_user_fields;

drop table if exists %PRAEFIX%_users;

drop table if exists %PRAEFIX%_categories;

drop table if exists %PRAEFIX%_preferences;

drop table if exists %PRAEFIX%_texts;

drop table if exists %PRAEFIX%_organizations;

/*==============================================================*/
/* Table: adm_organizations                                     */
/*==============================================================*/
create table %PRAEFIX%_organizations
(
   org_id                         tinyint(4)                     not null AUTO_INCREMENT,
   org_longname                   varchar(60)                    not null,
   org_shortname                  varchar(10)                    not null,
   org_org_id_parent              tinyint(4),
   org_homepage                   varchar(30)                    not null,
   primary key (org_id),
   unique ak_shortname (org_shortname)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_organizations add index ORG_ORG_PARENT_FK (org_org_id_parent);

-- Constraints
alter table %PRAEFIX%_organizations add constraint %PRAEFIX%_FK_ORG_ORG_PARENT foreign key (org_org_id_parent)
      references %PRAEFIX%_organizations (org_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_texts                                             */
/*==============================================================*/
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

-- Index
alter table %PRAEFIX%_texts add index TXT_ORG_FK (txt_org_id);

-- Constraints
alter table %PRAEFIX%_texts add constraint %PRAEFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_preferences                                       */
/*==============================================================*/
create table %PRAEFIX%_preferences
(
   prf_id                         int(11) unsigned               not null AUTO_INCREMENT,
   prf_org_id                     tinyint(4)                     not null,
   prf_name                       varchar(30)                    not null,
   prf_value                      varchar(255),
   primary key (prf_id)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_preferences add index PRF_ORG_FK (prf_org_id);

-- Constraints
alter table %PRAEFIX%_preferences add constraint %PRAEFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_categories                                        */
/*==============================================================*/
create table %PRAEFIX%_categories
(
   cat_id                         int (11) unsigned              not null AUTO_INCREMENT,
   cat_org_id                     tinyint(4),
   cat_type                       varchar(10)                    not null,
   cat_name                       varchar(30)                    not null,
   cat_hidden                     tinyint(1) unsigned            not null default 0,
   cat_system                     tinyint(1) unsigned            not null default 0,
   cat_sequence						 smallint                       not null,
   primary key (cat_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_categories add index CAT_ORG_FK (cat_org_id);

-- Constraints
alter table %PRAEFIX%_categories add constraint %PRAEFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_users                                             */
/*==============================================================*/
create table %PRAEFIX%_users
(
   usr_id                         int(11) unsigned               not null AUTO_INCREMENT,
   usr_login_name                 varchar(20),
   usr_password                   varchar(35),
   usr_photo                      blob,
   usr_text                       text,
   usr_last_login                 datetime,
   usr_actual_login               datetime,
   usr_number_login               smallint(5) unsigned           not null default 0,
   usr_date_invalid               datetime,
   usr_number_invalid             tinyint(3) unsigned            not null default 0,
   usr_last_change                datetime,
   usr_usr_id_change              int(11) unsigned,
   usr_valid                      tinyint(1) unsigned            not null default 0,
   usr_reg_org_shortname          varchar(10),
   primary key (usr_id),
   unique ak_usr_login_name (usr_login_name)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_users add index USR_USR_CHANGE_FK (usr_usr_id_change);
alter table %PRAEFIX%_users add index USR_ORG_REG_FK (usr_reg_org_shortname);

-- Constraints
alter table %PRAEFIX%_users add constraint %PRAEFIX%_FK_USR_USR_CHANGE foreign key (usr_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_users add constraint %PRAEFIX%_FK_USR_ORG_REG foreign key (usr_reg_org_shortname)
      references %PRAEFIX%_organizations (org_shortname) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_user_fields                                       */
/*==============================================================*/
create table %PRAEFIX%_user_fields
(
   usf_id                         int(11) unsigned               not null AUTO_INCREMENT,
   usf_cat_id                     int(11) unsigned               not null,
   usf_type                       varchar(10)                    not null,
   usf_name                       varchar(100)                   not null,
   usf_description                varchar(255),
   usf_system                     tinyint(1) unsigned            not null default 0,
   usf_disabled                   tinyint(1) unsigned            not null default 0,
   usf_hidden                     tinyint(1) unsigned            not null default 0,
   usf_mandatory                  tinyint(1) unsigned            not null default 0,
   usf_sequence						 smallint                       not null,
   primary key (usf_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_user_fields add index USF_CAT_FK (usf_cat_id);

-- Constraints
alter table %PRAEFIX%_user_fields add constraint FK_USF_CAT foreign key (usf_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_user_data                                         */
/*==============================================================*/
create table adm_user_data
(
   usd_usr_id                     int(11) unsigned               not null,
   usd_usf_id                     int(11) unsigned               not null,
   usd_value                      varchar(255),
   primary key (usd_usr_id, usd_usf_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_user_data add index USD_USF_FK (usd_usf_id);
alter table %PRAEFIX%_user_data add index USD_USR_FK (usd_usr_id);

-- Constraints
alter table %PRAEFIX%_user_data add constraint %PRAEFIX%_FK_USD_USF foreign key (usd_usf_id)
      references %PRAEFIX%_user_fields (usf_id) on delete restrict on update restrict;
alter table %PRAEFIX%_user_data add constraint %PRAEFIX%_FK_USD_USR foreign key (usd_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_sessions                                          */
/*==============================================================*/
create table %PRAEFIX%_sessions
(
   ses_id                         int(11) unsigned               not null AUTO_INCREMENT,
   ses_usr_id                     int(11) unsigned               default NULL,
   ses_org_id                     tinyint(4)                     not null,
   ses_session                    varchar(35)                    not null,
   ses_begin                      datetime                       not null,
   ses_timestamp                  datetime                       not null,
   ses_ip_address                 varchar(15)                    not null,
   ses_blob                       blob,
   ses_renew                      tinyint(1) unsigned            not null default 0,
   primary key (ses_id),
   key ak_session (ses_session)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_sessions add index SES_USR_FK (ses_usr_id);
alter table %PRAEFIX%_sessions add index SES_ORG_FK (ses_org_id);

-- Constraints
alter table %PRAEFIX%_sessions add constraint %PRAEFIX%_FK_SES_ORG foreign key (ses_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_sessions add constraint %PRAEFIX%_FK_SES_USR foreign key (ses_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_roles                                             */
/*==============================================================*/
create table %PRAEFIX%_roles
(
   rol_id                         int(11) unsigned               not null AUTO_INCREMENT,
   rol_cat_id                     int(11) unsigned               not null,
   rol_name                       varchar(30)                    not null,
   rol_description                varchar(255),
   rol_assign_roles               tinyint(1) unsigned            not null default 0,
   rol_approve_users              tinyint(1) unsigned            not null default 0,
   rol_announcements              tinyint(1) unsigned            not null default 0,
   rol_dates                      tinyint(1) unsigned            not null default 0,
   rol_download                   tinyint(1) unsigned            not null default 0,
   rol_edit_user                  tinyint(1) unsigned            not null default 0,
   rol_guestbook                  tinyint(1) unsigned            not null default 0,
   rol_guestbook_comments         tinyint(1) unsigned            not null default 0,
   rol_mail_logout                tinyint(1) unsigned            not null default 0,
   rol_mail_login                 tinyint(1) unsigned            not null default 0,
   rol_photo                      tinyint(1) unsigned            not null default 0,
   rol_profile                    tinyint(1) unsigned            not null default 0,
   rol_weblinks                   tinyint(1) unsigned            not null default 0,
   rol_locked                     tinyint(1) unsigned            not null default 0,
   rol_start_date                 date,
   rol_start_time                 time,
   rol_end_date                   date,
   rol_end_time                   time,
   rol_weekday                    tinyint(1),
   rol_location                   varchar(30),
   rol_max_members                smallint(3) unsigned,
   rol_cost                       float unsigned,
   rol_last_change                datetime,
   rol_usr_id_change              int(7) unsigned,
   rol_valid                      tinyint(1) unsigned            not null default 1,
   rol_system                     tinyint(1) unsigned            not null default 0,
   primary key (rol_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_roles add index ROL_CAT_FK (rol_cat_id);
alter table %PRAEFIX%_roles add index ROL_USR_FK (rol_usr_id_change);

-- Constraints
alter table %PRAEFIX%_roles add constraint %PRAEFIX%_FK_ROL_CAT foreign key (rol_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PRAEFIX%_roles add constraint %PRAEFIX%_FK_ROL_USR foreign key (rol_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
/*==============================================================*/
/* Table: adm_role_dependencies                                 */
/*==============================================================*/
create table %PRAEFIX%_role_dependencies
(
   rld_rol_id_parent              int(11) unsigned               not null,
   rld_rol_id_child               int(11) unsigned               not null,
   rld_comment                    text,
   rld_usr_id                     int(11) unsigned,
   rld_timestamp                  datetime                       not null,
   primary key (rld_rol_id_parent, rld_rol_id_child)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_role_dependencies add index RLD_USR_FK (rld_usr_id);
alter table %PRAEFIX%_role_dependencies add index RLD_ROL_PARENT_FK (rld_rol_id_parent);
alter table %PRAEFIX%_role_dependencies add index RLD_ROL_CHILD_FK (rld_rol_id_child);

-- Constraints
alter table %PRAEFIX%_role_dependencies add constraint %PRAEFIX%_FK_RLD_ROL_CHILD foreign key (rld_rol_id_child)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_role_dependencies add constraint %PRAEFIX%_FK_RLD_ROL_PARENT foreign key (rld_rol_id_parent)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_role_dependencies add constraint %PRAEFIX%_FK_RLD_USR foreign key (rld_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
/*==============================================================*/
/* Table: adm_members                                           */
/*==============================================================*/
create table %PRAEFIX%_members
(
   mem_id                         int(11)                        not null AUTO_INCREMENT,
   mem_rol_id                     int(11) unsigned               not null,
   mem_usr_id                     int(11) unsigned               not null,
   mem_begin                      date                           not null,
   mem_end                        date,
   mem_valid                      tinyint(1) unsigned            not null default 1,
   mem_leader                     tinyint(1) unsigned            not null default 0,
   primary key (mem_id),
   unique ak_rol_usr_id (mem_rol_id, mem_usr_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_members add index MEM_ROL_FK (mem_rol_id);
alter table %PRAEFIX%_members add index MEM_USR_FK (mem_usr_id);

-- Constraints
alter table %PRAEFIX%_members add constraint %PRAEFIX%_FK_MEM_ROL foreign key (mem_rol_id)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PRAEFIX%_members add constraint %PRAEFIX%_FK_MEM_USR foreign key (mem_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
      
/*==============================================================*/
/* Table: adm_announcements                                     */
/*==============================================================*/
create table %PRAEFIX%_announcements
(
   ann_id                         int(11) unsigned               not null AUTO_INCREMENT,
   ann_org_shortname              varchar(10)                    not null,
   ann_global                     tinyint(1) unsigned            not null default 0,
   ann_headline                   varchar(50)                    not null,
   ann_description                text,
   ann_usr_id                     int(11) unsigned,
   ann_timestamp                  datetime                       not null,
   ann_last_change                datetime,
   ann_usr_id_change              int(11) unsigned,
   primary key (ann_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_announcements add index ANN_ORG_FK (ann_org_shortname);
alter table %PRAEFIX%_announcements add index ANN_USR_FK(ann_usr_id);
alter table %PRAEFIX%_announcements add index ANN_USR_CHANGE_FK (ann_usr_id_change);

-- Constraints
alter table %PRAEFIX%_announcements add constraint %PRAEFIX%_FK_ANN_ORG foreign key (ann_org_shortname)
      references %PRAEFIX%_organizations (org_shortname) on delete restrict on update restrict;
alter table %PRAEFIX%_announcements add constraint %PRAEFIX%_FK_ANN_USR foreign key (ann_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_announcements add constraint %PRAEFIX%_FK_ANN_USR_CHANGE foreign key (ann_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
/*==============================================================*/
/* Table: adm_dates                                             */
/*==============================================================*/
create table %PRAEFIX%_dates
(
   dat_id                         int(11) unsigned               not null AUTO_INCREMENT,
   dat_org_shortname              varchar(10)                    not null,
   dat_global                     tinyint(1) unsigned            not null default 0,
   dat_begin                      datetime                       not null,
   dat_end                        datetime,
   dat_description                text,
   dat_location                   varchar(100),
   dat_headline                   varchar(50)                    not null,
   dat_usr_id                     int(11) unsigned,
   dat_timestamp                  datetime                       not null,
   dat_last_change                datetime,
   dat_usr_id_change              int(11) unsigned,
   primary key (dat_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_dates add index DAT_ORG_FK (dat_org_shortname);
alter table %PRAEFIX%_dates add index DAT_USR_FK (dat_usr_id);
alter table %PRAEFIX%_dates add index DAT_USR_CHANGE_FK (dat_usr_id_change);

-- Constraints
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_ORG foreign key (dat_org_shortname)
      references %PRAEFIX%_organizations (org_shortname) on delete restrict on update restrict;
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_USR foreign key (dat_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_USR_CHANGE foreign key (dat_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
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

      
/*==============================================================*/
/* Table: adm_guestbook                                         */
/*==============================================================*/
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

-- Index
alter table %PRAEFIX%_guestbook add index GBO_ORG_FK (gbo_org_id);
alter table %PRAEFIX%_guestbook add index GBO_USR_FK (gbo_usr_id);
alter table %PRAEFIX%_guestbook add index GBO_USR_CHANGE_FK (gbo_usr_id_change);

-- Constraints
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_ORG foreign key (gbo_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_USR foreign key (gbo_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_guestbook add constraint %PRAEFIX%_FK_GBO_USR_CHANGE foreign key (gbo_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_guestbook_comments                                */
/*==============================================================*/
create table %PRAEFIX%_guestbook_comments
(
   gbc_id                         int(11) unsigned               not null AUTO_INCREMENT,
   gbc_gbo_id                     int(11) unsigned               not null,
   gbc_usr_id                     int(11) unsigned,
   gbc_name                       varchar(60)                    not null,
   gbc_text                       text                           not null,
   gbc_email                      varchar(50),
   gbc_timestamp                  datetime                       not null,
   gbc_ip_address                 varchar(15)                    not null,
   gbc_last_change                datetime,
   gbc_usr_id_change              int(11) unsigned,
   primary key (gbc_id)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_guestbook_comments add index GBC_GBO_FK (gbc_gbo_id);
alter table %PRAEFIX%_guestbook_comments add index GBC_USR_FK (gbc_usr_id);
alter table %PRAEFIX%_guestbook_comments add index GBC_USR_CHANGE_FK (gbc_usr_id_change);

-- Constraints
alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_GBO foreign key (gbc_gbo_id)
      references %PRAEFIX%_guestbook (gbo_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_USR foreign key (gbc_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_guestbook_comments add constraint %PRAEFIX%_FK_GBC_USR_CHANGE foreign key (gbc_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

/*==============================================================*/
/* Table: adm_links                                             */
/*==============================================================*/
create table %PRAEFIX%_links
(
   lnk_id                         int(11) unsigned               not null AUTO_INCREMENT,
   lnk_org_id                     tinyint(4)                     not null,
   lnk_cat_id                     int(11) unsigned               not null,
   lnk_name                       varchar(255)                   not null,
   lnk_description                text,
   lnk_url                        varchar(255)                   not null,
   lnk_usr_id                     int(11) unsigned,
   lnk_timestamp                  datetime                       not null,
   lnk_usr_id_change              int(11) unsigned,
   lnk_last_change                datetime,
   primary key (lnk_id)
)
type = InnoDB;

-- Index
alter table %PRAEFIX%_links add index LNK_ORG_FK (lnk_org_id);
alter table %PRAEFIX%_links add index LNK_USR_FK (lnk_usr_id);
alter table %PRAEFIX%_links add index LNK_CAT_FK (lnk_cat_id);
alter table %PRAEFIX%_links add index LNK_USR_CHANGE_FK (lnk_usr_id_change);

-- Constraints
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_ORG foreign key (lnk_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_USR foreign key (lnk_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_CAT foreign key (lnk_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_USR_CHANGE foreign key (lnk_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
      
/*==============================================================*/
/* Table: adm_photos                                            */
/*==============================================================*/
create table %PRAEFIX%_photos
(
   pho_id                         int(11) unsigned               not null AUTO_INCREMENT,
   pho_org_shortname              varchar(10)                    not null,
   pho_quantity                   int(11) unsigned               not null default 0,
   pho_name                       varchar(50)                    not null,
   pho_begin                      date                           not null default '0000-00-00',
   pho_end                        date                           not null default '0000-00-00',
   pho_photographers              varchar(100),
   pho_usr_id                     int(11) unsigned,
   pho_timestamp                  datetime                       not null,
   pho_locked                     tinyint(1) unsigned            not null default 0,
   pho_pho_id_parent              int(11) unsigned,
   pho_last_change                datetime,
   pho_usr_id_change              int(11) unsigned,
   primary key (pho_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_photos add index PHO_ORG_FK (pho_org_shortname);
alter table %PRAEFIX%_photos add index PHO_USR_FK (pho_usr_id);
alter table %PRAEFIX%_photos add index PHO_USR_CHANGE_FK (pho_usr_id_change);
alter table %PRAEFIX%_photos add index FK_PHO_PHO_PARENT_FK (pho_pho_id_parent);

-- Constraints
alter table %PRAEFIX%_photos add constraint %PRAEFIX%_FK_PHO_PHO_PARENT foreign key (pho_pho_id_parent)
      references %PRAEFIX%_photos (pho_id) on delete set null on update restrict;
alter table %PRAEFIX%_photos add constraint %PRAEFIX%_FK_PHO_ORG foreign key (pho_org_shortname)
      references %PRAEFIX%_organizations (org_shortname) on delete restrict on update restrict;
alter table %PRAEFIX%_photos add constraint %PRAEFIX%_FK_PHO_USR foreign key (pho_usr_id)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
alter table %PRAEFIX%_photos add constraint %PRAEFIX%_FK_PHO_USR_CHANGE foreign key (pho_usr_id_change)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
