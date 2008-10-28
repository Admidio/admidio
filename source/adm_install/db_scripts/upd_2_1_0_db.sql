
-- Texttabelle anpassen
ALTER TABLE %PRAEFIX%_texts MODIFY COLUMN `txt_id` int(11) unsigned not null AUTO_INCREMENT;

-- Ueberschriftgroessen anpasssen
ALTER TABLE %PRAEFIX%_announcements MODIFY COLUMN `ann_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PRAEFIX%_dates MODIFY COLUMN `dat_headline` VARCHAR(100) NOT NULL;

-- Loginnamen auf 35 Zeichen erweitern
ALTER TABLE %PRAEFIX%_users MODIFY COLUMN `usr_login_name` VARCHAR(35);

-- Alle Zeitstempel und User-Ids von Anlegen und Aenderungen anpassen
ALTER TABLE %PRAEFIX%_users ADD COLUMN usr_usr_id_create int(11) unsigned AFTER usr_number_invalid;
alter table %PRAEFIX%_users add index USR_USR_CREATE_FK (usr_usr_id_create);
alter table %PRAEFIX%_users add constraint %PRAEFIX%_FK_USR_USR_create foreign key (usr_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PRAEFIX%_users ADD COLUMN usr_timestamp_create datetime AFTER usr_usr_id_create;
ALTER TABLE %PRAEFIX%_users CHANGE COLUMN `usr_last_change` `usr_timestamp_change` datetime;

ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_usr_id_create int(11) unsigned AFTER rol_cost;
alter table %PRAEFIX%_roles add index ROL_USR_CREATE_FK (rol_usr_id_create);
alter table %PRAEFIX%_roles add constraint %PRAEFIX%_FK_ROL_USR_CREATE foreign key (rol_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_timestamp_create datetime AFTER rol_usr_id_create;
ALTER TABLE %PRAEFIX%_roles CHANGE COLUMN `rol_last_change` `rol_timestamp_change` datetime;

ALTER TABLE %PRAEFIX%_dates DROP FOREIGN KEY %PRAEFIX%_FK_DAT_USR;
ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_usr_id` `dat_usr_id_create` int(11) unsigned;
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_USR_CREATE foreign key (dat_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_timestamp` `dat_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_last_change` `dat_timestamp_change` datetime;

ALTER TABLE %PRAEFIX%_announcements DROP FOREIGN KEY %PRAEFIX%_FK_ANN_USR;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_usr_id` `ann_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_timestamp` `ann_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_last_change` `ann_timestamp_change` datetime;
alter table %PRAEFIX%_announcements add constraint %PRAEFIX%_FK_ANN_USR_CREATE foreign key (ann_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PRAEFIX%_links DROP FOREIGN KEY %PRAEFIX%_FK_LNK_USR;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_usr_id` `lnk_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_timestamp` `lnk_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_last_change` `lnk_timestamp_change` datetime;
alter table %PRAEFIX%_links add constraint %PRAEFIX%_FK_LNK_USR_CREATE foreign key (lnk_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PRAEFIX%_photos DROP FOREIGN KEY %PRAEFIX%_FK_PHO_USR;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_usr_id` `pho_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_timestamp` `pho_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_last_change` `pho_timestamp_change` datetime;
alter table %PRAEFIX%_photos add constraint %PRAEFIX%_FK_PHO_USR_CREATE foreign key (pho_usr_id_create)
      references %PRAEFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PRAEFIX%_guestbook CHANGE COLUMN `gbo_last_change` `gbo_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_guestbook_comments CHANGE COLUMN `gbc_last_change` `gbc_timestamp_change` datetime;

-- Organisation aus Dates entfernen und Kategorie hinzufuegen
ALTER TABLE %PRAEFIX%_dates DROP FOREIGN KEY %PRAEFIX%_FK_DAT_ORG;
ALTER TABLE %PRAEFIX%_dates DROP INDEX DAT_ORG_FK;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN DAT_CAT_ID int(11) unsigned AFTER dat_id;
alter table %PRAEFIX%_dates add index DAT_CAT_FK (dat_cat_id);
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_CAT foreign key (dat_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;

-- Neu Mailrechteverwaltung
ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_mail_to_all int(11) unsigned AFTER rol_guestbook_comments DFAULT 0;
ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_mail_this_role int(11) unsigned AFTER rol_mail_to_all DFAULT 0;
ALTER TABLE %PRAEFIX%_roles DROP COLUMN rol_mail_logout;
ALTER TABLE %PRAEFIX%_roles DROP COLUMN rol_mail_login;

/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
create table %PRAEFIX%_lists
(
   lst_id                         int(11) unsigned               not null AUTO_INCREMENT,
   lst_org_id                     tinyint(4)                     not null,
   lst_usr_id                     int(11) unsigned               not null,
   lst_name                       varchar(255),
   lst_timestamp                  datetime                       not null,
   lst_global                     tinyint(1) unsigned            not null default 0,
   primary key (lst_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_lists add index LST_USR_FK (lst_usr_id);
alter table %PRAEFIX%_lists add index LST_ORG_FK (lst_org_id);

-- Constraints
alter table %PRAEFIX%_lists add constraint %PRAEFIX%_FK_LST_USR foreign key (lst_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_lists add constraint %PRAEFIX%_FK_LST_ORG foreign key (lst_org_id)
      references %PRAEFIX%_organizations (org_id) on delete restrict on update restrict;
      
/*==============================================================*/
/* Table: adm_list_columns                                       */
/*==============================================================*/
create table %PRAEFIX%_list_columns
(
   lsc_id                         int(11) unsigned               not null AUTO_INCREMENT,
   lsc_lst_id                     int(11) unsigned               not null,
   lsc_number                     smallint                       not null,
   lsc_usf_id                     int(11) unsigned,
   lsc_special_field              varchar(255),
   lsc_sort                       varchar(5),
   lsc_filter                     varchar(255),
   primary key (lsc_id)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_list_columns add index LSC_LST_FK (lsc_lst_id);
alter table %PRAEFIX%_list_columns add index LSC_USF_FK (lsc_usf_id);

-- Constraints
alter table %PRAEFIX%_list_columns add constraint FK_LSC_LST foreign key (lsc_lst_id)
      references adm_lists (lst_id) on delete restrict on update restrict;

alter table %PRAEFIX%_list_columns add constraint FK_LSC_USF foreign key (lsc_usf_id)
      references adm_user_fields (usf_id) on delete restrict on update restrict;