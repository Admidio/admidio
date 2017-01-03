/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- Texttabelle anpassen
ALTER TABLE %PREFIX%_texts MODIFY COLUMN `txt_id` int(11) unsigned NOT NULL AUTO_INCREMENT;

-- Ueberschriftgroessen anpasssen
ALTER TABLE %PREFIX%_announcements MODIFY COLUMN `ann_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PREFIX%_dates MODIFY COLUMN `dat_headline` VARCHAR(100) NOT NULL;
ALTER TABLE %PREFIX%_dates ADD COLUMN dat_country varchar(100) AFTER dat_location;

-- Loginnamen auf 35 Zeichen erweitern
ALTER TABLE %PREFIX%_users MODIFY COLUMN `usr_login_name` VARCHAR(35);

-- Alle Zeitstempel und User-Ids von Anlegen und Aenderungen anpassen
ALTER TABLE %PREFIX%_users ADD COLUMN usr_usr_id_create int(11) unsigned AFTER usr_number_invalid;
ALTER TABLE %PREFIX%_users ADD INDEX USR_USR_CREATE_FK (usr_usr_id_create);
ALTER TABLE %PREFIX%_users ADD CONSTRAINT %PREFIX%_FK_USR_USR_create foreign key (usr_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_users ADD COLUMN usr_timestamp_create datetime AFTER usr_usr_id_create;
ALTER TABLE %PREFIX%_users CHANGE COLUMN `usr_last_change` `usr_timestamp_change` datetime;

ALTER TABLE %PREFIX%_roles ADD COLUMN rol_cost_period smallint(3) unsigned AFTER rol_cost;
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_usr_id_create int(11) unsigned AFTER rol_cost_period;
ALTER TABLE %PREFIX%_roles ADD INDEX ROL_USR_CREATE_FK (rol_usr_id_create);
ALTER TABLE %PREFIX%_roles ADD CONSTRAINT %PREFIX%_FK_ROL_USR_CREATE foreign key (rol_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_timestamp_create datetime AFTER rol_usr_id_create;
ALTER TABLE %PREFIX%_roles CHANGE COLUMN `rol_last_change` `rol_timestamp_change` datetime;

ALTER TABLE %PREFIX%_dates DROP FOREIGN KEY %PREFIX%_FK_DAT_USR;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_usr_id` `dat_usr_id_create` int(11) unsigned;
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_USR_CREATE foreign key (dat_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_timestamp` `dat_timestamp_create` datetime;
ALTER TABLE %PREFIX%_dates CHANGE COLUMN `dat_last_change` `dat_timestamp_change` datetime;

ALTER TABLE %PREFIX%_announcements DROP FOREIGN KEY %PREFIX%_FK_ANN_USR;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_usr_id` `ann_usr_id_create` int(11) unsigned;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_timestamp` `ann_timestamp_create` datetime;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_last_change` `ann_timestamp_change` datetime;
ALTER TABLE %PREFIX%_announcements ADD CONSTRAINT %PREFIX%_FK_ANN_USR_CREATE foreign key (ann_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PREFIX%_links DROP FOREIGN KEY %PREFIX%_FK_LNK_USR;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_usr_id` `lnk_usr_id_create` int(11) unsigned;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_timestamp` `lnk_timestamp_create` datetime;
ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_last_change` `lnk_timestamp_change` datetime;
ALTER TABLE %PREFIX%_links ADD CONSTRAINT %PREFIX%_FK_LNK_USR_CREATE foreign key (lnk_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PREFIX%_photos DROP FOREIGN KEY %PREFIX%_FK_PHO_USR;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_usr_id` `pho_usr_id_create` int(11) unsigned;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_timestamp` `pho_timestamp_create` datetime;
ALTER TABLE %PREFIX%_photos CHANGE COLUMN `pho_last_change` `pho_timestamp_change` datetime;
ALTER TABLE %PREFIX%_photos ADD CONSTRAINT %PREFIX%_FK_PHO_USR_CREATE foreign key (pho_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) on delete set null on update restrict;

ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_last_change` `gbo_timestamp_change` datetime;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_last_change` `gbc_timestamp_change` datetime;

-- Systemprofilfelder anpassen
UPDATE %PREFIX%_user_fields SET usf_system = 0
 WHERE usf_name IN ('Telefon','Handy','Fax');

-- Mitgliederzuordnung anpassen
update %PREFIX%_members set mem_end = '9999-12-31' where mem_end IS NULL;
ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_begin` DATE NOT NULL;
ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_end` DATE NOT NULL DEFAULT '9999-12-31';
ALTER TABLE %PREFIX%_members DROP COLUMN `mem_valid`;

-- Organisation aus Dates entfernen und Kategorie hinzufuegen
ALTER TABLE %PREFIX%_dates DROP FOREIGN KEY %PREFIX%_FK_DAT_ORG;
ALTER TABLE %PREFIX%_dates DROP INDEX DAT_ORG_FK;
ALTER TABLE %PREFIX%_dates ADD COLUMN DAT_CAT_ID int(11) unsigned AFTER dat_id;
ALTER TABLE %PREFIX%_dates ADD INDEX DAT_CAT_FK (dat_cat_id);
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_CAT foreign key (dat_cat_id)
      REFERENCES %PREFIX%_categories (cat_id) on delete restrict on update restrict;

-- Neu Mailrechteverwaltung
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_mail_this_role tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER rol_guestbook_comments;
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_mail_to_all tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER rol_guestbook_comments;

-- Neues Recht fuer Inventarmodul
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_inventory tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER rol_guestbook_comments;


-- Autoincrement-Spalte fuer adm_user_data anlegen
ALTER TABLE %PREFIX%_user_data DROP FOREIGN KEY %PREFIX%_FK_USD_USF;
ALTER TABLE %PREFIX%_user_data DROP FOREIGN KEY %PREFIX%_FK_USD_USR ;

DROP TABLE if exists %PREFIX%_user_data_old;
RENAME TABLE %PREFIX%_user_data TO %PREFIX%_user_data_old;

CREATE TABLE %PREFIX%_user_data
(
    usd_id                         int(11) unsigned               NOT NULL AUTO_INCREMENT,
    usd_usr_id                     int(11) unsigned               NOT NULL,
    usd_usf_id                     int(11) unsigned               NOT NULL,
    usd_value                      varchar(255),
    primary key (usd_id),
    unique ak_usr_usf_id (usd_usr_id, usd_usf_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
ALTER TABLE %PREFIX%_user_data ADD INDEX USD_USF_FK (usd_usf_id);
ALTER TABLE %PREFIX%_user_data ADD INDEX USD_USR_FK (usd_usr_id);

-- Constraints
ALTER TABLE %PREFIX%_user_data ADD CONSTRAINT %PREFIX%_FK_USD_USF foreign key (usd_usf_id)
      REFERENCES %PREFIX%_user_fields (usf_id) on delete restrict on update restrict;
ALTER TABLE %PREFIX%_user_data ADD CONSTRAINT %PREFIX%_FK_USD_USR foreign key (usd_usr_id)
      REFERENCES %PREFIX%_users (usr_id) on delete restrict on update restrict;

INSERT INTO %PREFIX%_user_data (usd_usr_id, usd_usf_id, usd_value)
SELECT usd_usr_id, usd_usf_id, usd_value
  FROM %PREFIX%_user_data_old;

DROP TABLE %PREFIX%_user_data_old;


-- neue Spalten in den Tabellen des Downloadmoduls anlegen
ALTER TABLE %PREFIX%_folders ADD COLUMN fol_description text AFTER fol_name;
ALTER TABLE %PREFIX%_files   ADD COLUMN fil_description text AFTER fil_name;


/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_lists
(
    lst_id                         int(11) unsigned               NOT NULL AUTO_INCREMENT,
    lst_org_id                     tinyint(4)                     NOT NULL,
    lst_usr_id                     int(11) unsigned               NOT NULL,
    lst_name                       varchar(255),
    lst_timestamp                  datetime                       NOT NULL,
    lst_global                     tinyint(1) unsigned            NOT NULL default 0,
    lst_default                    tinyint(1) unsigned            NOT NULL default 0,
    primary key (lst_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
ALTER TABLE %PREFIX%_lists ADD INDEX LST_USR_FK (lst_usr_id);
ALTER TABLE %PREFIX%_lists ADD INDEX LST_ORG_FK (lst_org_id);

-- Constraints
ALTER TABLE %PREFIX%_lists ADD CONSTRAINT %PREFIX%_FK_LST_USR foreign key (lst_usr_id)
      REFERENCES %PREFIX%_users (usr_id) on delete restrict on update restrict;
ALTER TABLE %PREFIX%_lists ADD CONSTRAINT %PREFIX%_FK_LST_ORG foreign key (lst_org_id)
      REFERENCES %PREFIX%_organizations (org_id) on delete restrict on update restrict;

/*==============================================================*/
/* Table: adm_list_columns                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_list_columns
(
    lsc_id                         int(11) unsigned               NOT NULL AUTO_INCREMENT,
    lsc_lst_id                     int(11) unsigned               NOT NULL,
    lsc_number                     smallint                       NOT NULL,
    lsc_usf_id                     int(11) unsigned,
    lsc_special_field              varchar(255),
    lsc_sort                       varchar(5),
    lsc_filter                     varchar(255),
    primary key (lsc_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
ALTER TABLE %PREFIX%_list_columns ADD INDEX LSC_LST_FK (lsc_lst_id);
ALTER TABLE %PREFIX%_list_columns ADD INDEX LSC_USF_FK (lsc_usf_id);

-- Constraints
ALTER TABLE %PREFIX%_list_columns ADD CONSTRAINT %PREFIX%_FK_LSC_LST foreign key (lsc_lst_id)
      REFERENCES %PREFIX%_lists (lst_id) on delete restrict on update restrict;

ALTER TABLE %PREFIX%_list_columns ADD CONSTRAINT %PREFIX%_FK_LSC_USF foreign key (lsc_usf_id)
      REFERENCES %PREFIX%_user_fields (usf_id) on delete restrict on update restrict;
