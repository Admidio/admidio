
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

ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_usr_id` `dat_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_timestamp` `dat_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_dates CHANGE COLUMN `dat_last_change` `dat_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_usr_id` `ann_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_timestamp` `ann_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_announcements CHANGE COLUMN `ann_last_change` `ann_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_usr_id` `lnk_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_timestamp` `lnk_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_links CHANGE COLUMN `lnk_last_change` `lnk_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_usr_id` `pho_usr_id_create` int(11) unsigned;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_timestamp` `pho_timestamp_create` datetime;
ALTER TABLE %PRAEFIX%_photos CHANGE COLUMN `pho_last_change` `pho_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_guestbook CHANGE COLUMN `gbo_last_change` `gbo_timestamp_change` datetime;
ALTER TABLE %PRAEFIX%_guestbook_comments CHANGE COLUMN `gbc_last_change` `gbc_timestamp_change` datetime;

-- Organisation aus Dates entfernen und Kategorie hinzufuegen
ALTER TABLE %PRAEFIX%_dates DROP FOREIGN KEY %PRAEFIX%_FK_DAT_ORG;
ALTER TABLE %PRAEFIX%_dates DROP INDEX DAT_ORG_FK;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN DAT_CAT_ID int(11) unsigned AFTER dat_id;
alter table %PRAEFIX%_dates add index DAT_CAT_FK (dat_cat_id);
alter table %PRAEFIX%_dates add constraint %PRAEFIX%_FK_DAT_CAT foreign key (dat_cat_id)
      references %PRAEFIX%_categories (cat_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
create table %PRAEFIX%_lists
(
   lst_id                         int(11) unsigned               not null AUTO_INCREMENT,
   lst_org_id                     tinyint(4)                     not null,
   lst_usr_id                     int(11) unsigned               not null,
   lst_name                       varchar(255)                   not null,
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
/* Table: adm_list_fields                                       */
/*==============================================================*/
create table %PRAEFIX%_list_fields
(
   lsf_lst_id                     int(11) unsigned               not null,
   lsf_column                     smallint                       not null,
   lsf_usf_id                     int(11) unsigned,
   lsf_special_field              varchar(255),
   lsf_sort                       varchar(5)                     default '0',
   lsf_filter                     varchar(255)
)
type = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_list_fields add index LSF_LST_FK (lsf_lst_id);
alter table %PRAEFIX%_list_fields add index LSF_USF_FK (lsf_usf_id);

-- Constraints
alter table %PRAEFIX%_list_fields add constraint FK_LSF_LST foreign key (lsf_lst_id)
      references adm_lists (lst_id) on delete restrict on update restrict;

alter table %PRAEFIX%_list_fields add constraint FK_LSF_USF foreign key (lsf_usf_id)
      references adm_user_fields (usf_id) on delete restrict on update restrict;