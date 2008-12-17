
-- Texttabelle anpassen
ALTER TABLE %PRAEFIX%_texts MODIFY COLUMN `txt_id` int(11) unsigned not null AUTO_INCREMENT;

-- Ueberschriftgroessen anpasssen
ALTER TABLE %PRAEFIX%_announcements MODIFY COLUMN `ann_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PRAEFIX%_dates MODIFY COLUMN `dat_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN dat_country varchar(100) AFTER dat_location;

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

-- Mitgliederzuordnung anpassen
update %PRAEFIX%_members set mem_end = '9999-12-31' where mem_end is null;
ALTER TABLE %PRAEFIX%_members MODIFY COLUMN `mem_begin` DATE NOT NULL;
ALTER TABLE %PRAEFIX%_members MODIFY COLUMN `mem_end` DATE NOT NULL DEFAULT '9999-12-31';
ALTER TABLE %PRAEFIX%_members DROP COLUMN `mem_valid`;

-- Organisation aus Dates entfernen und Kategorie hinzufuegen
ALTER TABLE %PRAEFIX%_dates DROP FOREIGN KEY %PRAEFIX%_FK_DAT_ORG;
ALTER TABLE %PRAEFIX%_dates DROP INDEX DAT_ORG_FK;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN DAT_CAT_ID int(11) unsigned AFTER dat_id;
alter table %PRAEFIX%_dates add index DAT_CAT_FK (dat_cat_id);
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_CAT foreign key (dat_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;

-- Neu Mailrechteverwaltung
ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_mail_this_role tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER rol_guestbook_comments;
ALTER TABLE %PRAEFIX%_roles ADD COLUMN rol_mail_to_all tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER rol_guestbook_comments;

-- Autoincrement-Spalte für adm_user_data anlegen
ALTER TABLE %PRAEFIX%_user_data DROP FOREIGN KEY %PRAEFIX%_FK_USD_USF;
ALTER TABLE %PRAEFIX%_user_data DROP FOREIGN KEY %PRAEFIX%_FK_USD_USR ;

RENAME TABLE %PRAEFIX%_user_data TO %PRAEFIX%_user_data_old;

create table %PRAEFIX%_user_data
(
   usd_id                         int(11) unsigned               not null AUTO_INCREMENT,
   usd_usr_id                     int(11) unsigned               not null,
   usd_usf_id                     int(11) unsigned               not null,
   usd_value                      varchar(255),
   primary key (usd_id),
   unique ak_usr_usf_id (usd_usr_id, usd_usf_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_user_data add index USD_USF_FK (usd_usf_id);
alter table %PRAEFIX%_user_data add index USD_USR_FK (usd_usr_id);

-- Constraints
alter table %PRAEFIX%_user_data add constraint %PRAEFIX%_FK_USD_USF foreign key (usd_usf_id)
      references %PRAEFIX%_user_fields (usf_id) on delete restrict on update restrict;
alter table %PRAEFIX%_user_data add constraint %PRAEFIX%_FK_USD_USR foreign key (usd_usr_id)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;

INSERT INTO %PRAEFIX%_user_data (usd_usr_id, usd_usf_id, usd_value)
SELECT usd_usr_id, usd_usf_id, usd_value
  FROM %PRAEFIX%_user_data_old;
  
DROP TABLE %PRAEFIX%_user_data_old;

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
   lst_default                    tinyint(1) unsigned            not null default 0,
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
alter table %PRAEFIX%_list_columns add constraint %PRAEFIX%_FK_LSC_LST foreign key (lsc_lst_id)
      references %PRAEFIX%_lists (lst_id) on delete restrict on update restrict;

alter table %PRAEFIX%_list_columns add constraint %PRAEFIX%_FK_LSC_USF foreign key (lsc_usf_id)
      references %PRAEFIX%_user_fields (usf_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_messages                                          */
/*==============================================================*/
create table %PRAEFIX%_messages
(
 msg_id                         int(11) unsigned NOT NULL auto_increment,
 msg_usr_id_from                int(11) unsigned NOT NULL,
 msg_usr_id_to                  int(11) unsigned NOT NULL,
 msg_msg_id_previous            int(11) unsigned,
 msg_send_date                  datetime NOT NULL,
 msg_read_date                  datetime,
 msg_title                      varchar(250),
 msg_text                       text NOT NULL,
 msg_archive_flag               tinyint(1) unsigned NOT NULL default '0',
 primary key (msg_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_messages add index MSG_USR_FROM_FK (msg_usr_id_from);
alter table %PRAEFIX%_messages add index MSG_USR_TO_FK (msg_usr_id_to);
alter table %PRAEFIX%_messages add index MSG_MSG_ID_PREVIOUS_FK (msg_msg_id_previous);

-- Constraints
alter table %PRAEFIX%_messages add constraint %PRAEFIX%_FK_MSG_USR_ID_FROM foreign key (msg_usr_id_from)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_messages add constraint %PRAEFIX%_FK_MSG_USR_ID_TO foreign key (msg_usr_id_to)
      references %PRAEFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PRAEFIX%_messages add constraint %PRAEFIX%_FK_MSG_MSG_ID_PREVIOUS foreign key (msg_msg_id_previous)
      references %PRAEFIX%_messages (msg_id) on delete set null on update restrict;