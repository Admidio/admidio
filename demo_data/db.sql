/******************************************************************************
 * SQL script with database structure
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ******************************************************************************/


<<<<<<< HEAD
drop table if exists %PREFIX%_announcements cascade;
drop table if exists %PREFIX%_auto_login cascade;
drop table if exists %PREFIX%_components cascade;
drop table if exists %PREFIX%_date_role cascade;
drop table if exists %PREFIX%_dates cascade;
drop table if exists %PREFIX%_files cascade;
drop table if exists %PREFIX%_folder_roles cascade;
drop table if exists %PREFIX%_folders cascade;
drop table if exists %PREFIX%_guestbook_comments cascade;
drop table if exists %PREFIX%_guestbook cascade;
drop table if exists %PREFIX%_invent_fields cascade;
drop table if exists %PREFIX%_invent_data cascade;
drop table if exists %PREFIX%_invent cascade;
drop table if exists %PREFIX%_links cascade;
drop table if exists %PREFIX%_members cascade;
drop table if exists %PREFIX%_messages cascade;
drop table if exists %PREFIX%_messages_content cascade;
drop table if exists %PREFIX%_photos cascade;
drop table if exists %PREFIX%_preferences cascade;
drop table if exists %PREFIX%_registrations cascade;
drop table if exists %PREFIX%_role_dependencies cascade;
drop table if exists %PREFIX%_roles cascade;
drop table if exists %PREFIX%_list_columns cascade;
drop table if exists %PREFIX%_lists cascade;
drop table if exists %PREFIX%_rooms cascade;
drop table if exists %PREFIX%_sessions cascade;
drop table if exists %PREFIX%_texts cascade;
drop table if exists %PREFIX%_user_log cascade;
drop table if exists %PREFIX%_user_data cascade;
drop table if exists %PREFIX%_user_fields cascade;
drop table if exists %PREFIX%_categories cascade;
drop table if exists %PREFIX%_users cascade;
drop table if exists %PREFIX%_organizations cascade;
drop table if exists %PREFIX%_ids cascade;
=======
DROP TABLE IF EXISTS %PREFIX%_announcements CASCADE;
DROP TABLE IF EXISTS %PREFIX%_auto_login CASCADE;
DROP TABLE IF EXISTS %PREFIX%_components CASCADE;
DROP TABLE IF EXISTS %PREFIX%_date_role CASCADE;
DROP TABLE IF EXISTS %PREFIX%_dates CASCADE;
DROP TABLE IF EXISTS %PREFIX%_files CASCADE;
DROP TABLE IF EXISTS %PREFIX%_folder_roles CASCADE;
DROP TABLE IF EXISTS %PREFIX%_folders CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook_comments CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook CASCADE;
DROP TABLE IF EXISTS %PREFIX%_invent_fields CASCADE;
DROP TABLE IF EXISTS %PREFIX%_invent_data CASCADE;
DROP TABLE IF EXISTS %PREFIX%_invent CASCADE;
DROP TABLE IF EXISTS %PREFIX%_links CASCADE;
DROP TABLE IF EXISTS %PREFIX%_members CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_content CASCADE;
DROP TABLE IF EXISTS %PREFIX%_photos CASCADE;
DROP TABLE IF EXISTS %PREFIX%_preferences CASCADE;
DROP TABLE IF EXISTS %PREFIX%_registrations CASCADE;
DROP TABLE IF EXISTS %PREFIX%_role_dependencies CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles CASCADE;
DROP TABLE IF EXISTS %PREFIX%_list_columns CASCADE;
DROP TABLE IF EXISTS %PREFIX%_lists CASCADE;
DROP TABLE IF EXISTS %PREFIX%_rooms CASCADE;
DROP TABLE IF EXISTS %PREFIX%_sessions CASCADE;
DROP TABLE IF EXISTS %PREFIX%_texts CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_log CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_data CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_fields CASCADE;
DROP TABLE IF EXISTS %PREFIX%_categories CASCADE;
DROP TABLE IF EXISTS %PREFIX%_users CASCADE;
DROP TABLE IF EXISTS %PREFIX%_organizations CASCADE;
DROP TABLE IF EXISTS %PREFIX%_ids CASCADE;
>>>>>>> origin/master


/*==============================================================*/
/* Table: adm_announcements                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_announcements
(
<<<<<<< HEAD
    ann_id                         integer       unsigned not null AUTO_INCREMENT,
    ann_org_shortname              varchar(10)   not null,
    ann_global                     boolean       not null default '0',
    ann_headline                   varchar(100)  not null,
    ann_description                text,
    ann_usr_id_create              integer       unsigned,
    ann_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    ann_usr_id_change              integer       unsigned,
    ann_timestamp_change           timestamp     null default null,
    primary key (ann_id)
=======
    ann_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    ann_org_shortname              VARCHAR(10)   NOT NULL,
    ann_global                     BOOLEAN       NOT NULL DEFAULT '0',
    ann_headline                   VARCHAR(100)  NOT NULL,
    ann_description                TEXT,
    ann_usr_id_create              INTEGER       UNSIGNED,
    ann_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ann_usr_id_change              INTEGER       UNSIGNED,
    ann_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (ann_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_auto_login                                        */
/*==============================================================*/
CREATE TABLE %PREFIX%_auto_login
(
<<<<<<< HEAD
    atl_id                         integer       unsigned not null AUTO_INCREMENT,
    atl_session_id                 varchar(35)   not null,
    atl_org_id                     integer       unsigned not null,
    atl_usr_id                     integer       unsigned not null,
    atl_last_login                 timestamp        null default null,
    atl_ip_address                 varchar(39)   not null,
    primary key (atl_id)
=======
    atl_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    atl_session_id                 VARCHAR(35)   NOT NULL,
    atl_org_id                     INTEGER       UNSIGNED NOT NULL,
    atl_usr_id                     INTEGER       UNSIGNED NOT NULL,
    atl_last_login                 TIMESTAMP     NULL DEFAULT NULL,
    atl_ip_address                 VARCHAR(39)   NOT NULL,
    PRIMARY KEY (atl_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_categories                                        */
/*==============================================================*/
CREATE TABLE %PREFIX%_categories
(
    cat_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    cat_org_id                     INTEGER       UNSIGNED,
    cat_type                       VARCHAR(10)   NOT NULL,
    cat_name_intern                VARCHAR(110)  NOT NULL,
    cat_name                       VARCHAR(100)  NOT NULL,
    cat_hidden                     BOOLEAN       NOT NULL DEFAULT '0',
    cat_system                     BOOLEAN       NOT NULL DEFAULT '0',
    cat_default                    BOOLEAN       NOT NULL DEFAULT '0',
    cat_sequence                   SMALLINT      NOT NULL,
    cat_usr_id_create              INTEGER       UNSIGNED,
    cat_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cat_usr_id_change              INTEGER       UNSIGNED,
    cat_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (cat_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_components                                         */
/*==============================================================*/

CREATE TABLE %PREFIX%_components
(
<<<<<<< HEAD
    cat_id                         integer       unsigned not null AUTO_INCREMENT,
    cat_org_id                     integer       unsigned,
    cat_type                       varchar(10)   not null,
    cat_name_intern                varchar(110)  not null,
    cat_name                       varchar(100)  not null,
    cat_hidden                     boolean       not null default '0',
    cat_system                     boolean       not null default '0',
    cat_default                    boolean       not null default '0',
    cat_sequence                   smallint      not null,
    cat_usr_id_create              integer       unsigned,
    cat_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    cat_usr_id_change              integer       unsigned,
    cat_timestamp_change           timestamp     null default null,
    primary key (cat_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_components                                         */
/*==============================================================*/

create table %PREFIX%_components
(
    com_id                        integer       unsigned not null AUTO_INCREMENT,
    com_type                      varchar(10)   not null,
    com_name                      varchar(255)  not null,
    com_name_intern               varchar(255)  not null,
    com_version                   varchar(10)   not null,
    com_beta                      smallint      not null default 0,
    com_update_step               integer       not null default 0,
    com_timestamp_installed       timestamp     not null default CURRENT_TIMESTAMP,
    primary key (com_id)
=======
    com_id                        INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    com_type                      VARCHAR(10)   NOT NULL,
    com_name                      VARCHAR(255)  NOT NULL,
    com_name_intern               VARCHAR(255)  NOT NULL,
    com_version                   VARCHAR(10)   NOT NULL,
    com_beta                      SMALLINT,
    com_update_step               INTEGER       NOT NULL DEFAULT 0,
    com_timestamp_installed       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (com_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_date_role                                         */
/*==============================================================*/

CREATE TABLE %PREFIX%_date_role
(
    dtr_id                          INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    dtr_dat_id                      INTEGER       UNSIGNED NOT NULL,
    dtr_rol_id                      INTEGER       UNSIGNED,
    PRIMARY KEY (dtr_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_dates                                             */
/*==============================================================*/
<<<<<<< HEAD
create table %PREFIX%_dates
(
    dat_id                         integer       unsigned not null AUTO_INCREMENT,
    dat_cat_id                     integer       unsigned not null,
    dat_rol_id                     integer       unsigned,
    dat_room_id                    integer       unsigned,
    dat_global                     boolean       not null default '0',
    dat_begin                      timestamp     null default null,
    dat_end                        timestamp     null default null,
    dat_all_day                    boolean       not null default '0',
    dat_highlight                  boolean       not null default '0',
    dat_description                text,
    dat_location                   varchar(100),
    dat_country                    varchar(100),
    dat_headline                   varchar(100)  not null,
    dat_max_members                integer       not null default 0,
    dat_usr_id_create              integer       unsigned,
    dat_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    dat_usr_id_change              integer       unsigned,
    dat_timestamp_change           timestamp     null default null,
    primary key (dat_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;
=======
CREATE TABLE %PREFIX%_dates
(
    dat_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    dat_cat_id                     INTEGER       UNSIGNED NOT NULL,
    dat_rol_id                     INTEGER       UNSIGNED,
    dat_room_id                    INTEGER       UNSIGNED,
    dat_global                     BOOLEAN       NOT NULL DEFAULT '0',
    dat_begin                      TIMESTAMP     NULL DEFAULT NULL,
    dat_end                        TIMESTAMP     NULL DEFAULT NULL,
    dat_all_day                    BOOLEAN       NOT NULL DEFAULT '0',
    dat_highlight                  BOOLEAN       NOT NULL DEFAULT '0',
    dat_description                TEXT,
    dat_location                   VARCHAR(100),
    dat_country                    VARCHAR(100),
    dat_headline                   VARCHAR(100)  NOT NULL,
    dat_max_members                INTEGER       NOT NULL DEFAULT 0,
    dat_usr_id_create              INTEGER       UNSIGNED,
    dat_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dat_usr_id_change              INTEGER       UNSIGNED,
    dat_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (dat_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_files
(
<<<<<<< HEAD
    fil_id                         integer       unsigned not null AUTO_INCREMENT,
    fil_fol_id                     integer       unsigned not null,
    fil_name                       varchar(255)  not null,
    fil_description                text,
    fil_locked                     boolean       not null default '0',
    fil_counter                    integer,
    fil_usr_id                     integer       unsigned,
    fil_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    primary key (fil_id)
=======
    fil_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    fil_fol_id                     INTEGER       UNSIGNED NOT NULL,
    fil_name                       VARCHAR(255)  NOT NULL,
    fil_description                TEXT,
    fil_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    fil_counter                    INTEGER,
    fil_usr_id                     INTEGER       UNSIGNED,
    fil_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fil_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_folder_roles                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_folder_roles
(
<<<<<<< HEAD
    flr_fol_id                     integer       unsigned not null,
    flr_rol_id                     integer       unsigned not null,
    primary key (flr_fol_id, flr_rol_id)
=======
    flr_fol_id                     INTEGER       UNSIGNED NOT NULL,
    flr_rol_id                     INTEGER       UNSIGNED NOT NULL,
    PRIMARY KEY (flr_fol_id, flr_rol_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
CREATE TABLE %PREFIX%_folders
(
<<<<<<< HEAD
    fol_id                         integer       unsigned not null AUTO_INCREMENT,
    fol_org_id                     integer       unsigned not null,
    fol_fol_id_parent              integer       unsigned,
    fol_type                       varchar(10)   not null,
    fol_name                       varchar(255)  not null,
    fol_description                text,
    fol_path                       varchar(255)  not null,
    fol_locked                     boolean       not null default '0',
    fol_public                     boolean       not null default '0',
    fol_usr_id                     integer       unsigned,
    fol_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    primary key (fol_id)
=======
    fol_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    fol_org_id                     INTEGER       UNSIGNED NOT NULL,
    fol_fol_id_parent              INTEGER       UNSIGNED,
    fol_type                       VARCHAR(10)   NOT NULL,
    fol_name                       VARCHAR(255)  NOT NULL,
    fol_description                TEXT,
    fol_path                       VARCHAR(255)  NOT NULL,
    fol_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    fol_public                     BOOLEAN       NOT NULL DEFAULT '0',
    fol_usr_id                     INTEGER       UNSIGNED,
    fol_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fol_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_guestbook                                         */
/*==============================================================*/
CREATE TABLE %PREFIX%_guestbook
(
<<<<<<< HEAD
    gbo_id                         integer       unsigned not null AUTO_INCREMENT,
    gbo_org_id                     integer       unsigned not null,
    gbo_name                       varchar(60)   not null,
    gbo_text                       text          not null,
    gbo_email                      varchar(50),
    gbo_homepage                   varchar(50),
    gbo_ip_address                 varchar(39)   not null,
    gbo_locked                     boolean       not null default '0',
    gbo_usr_id_create              integer       unsigned,
    gbo_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    gbo_usr_id_change              integer       unsigned,
    gbo_timestamp_change           timestamp     null default null,
    primary key (gbo_id)
=======
    gbo_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    gbo_org_id                     INTEGER       UNSIGNED NOT NULL,
    gbo_name                       VARCHAR(60)   NOT NULL,
    gbo_text                       TEXT          NOT NULL,
    gbo_email                      VARCHAR(50),
    gbo_homepage                   VARCHAR(50),
    gbo_ip_address                 VARCHAR(39)   NOT NULL,
    gbo_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    gbo_usr_id_create              INTEGER       UNSIGNED,
    gbo_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gbo_usr_id_change              INTEGER       UNSIGNED,
    gbo_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (gbo_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_guestbook_comments                                */
/*==============================================================*/
CREATE TABLE %PREFIX%_guestbook_comments
(
<<<<<<< HEAD
    gbc_id                         integer       unsigned not null AUTO_INCREMENT,
    gbc_gbo_id                     integer       unsigned not null,
    gbc_name                       varchar(60)   not null,
    gbc_text                       text          not null,
    gbc_email                      varchar(50),
    gbc_ip_address                 varchar(39)   not null,
    gbc_locked                     boolean       not null default '0',
    gbc_usr_id_create              integer       unsigned,
    gbc_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    gbc_usr_id_change              integer       unsigned,
    gbc_timestamp_change           timestamp     null default null,
    primary key (gbc_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_ids                                               */
/*==============================================================*/
create table %PREFIX%_ids
(
   ids_usr_id                     integer       unsigned not null,
   ids_reference_id               integer       unsigned not null
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_invent_fields                                     */
/*==============================================================*/
create table %PREFIX%_invent_fields
(
    inf_id                         integer       unsigned not null AUTO_INCREMENT,
    inf_cat_id                     integer       unsigned not null,
    inf_type                       varchar(30)   not null,
    inf_name_intern                varchar(110)  not null,
    inf_name                       varchar(100)  not null,
    inf_description                text,
    inf_value_list                 text,
    inf_system                     boolean       not null default '0',
    inf_disabled                   boolean       not null default '0',
    inf_hidden                     boolean       not null default '0',
    inf_mandatory                  boolean       not null default '0',
    inf_sequence                   smallint      not null,
    inf_usr_id_create              integer       unsigned,
    inf_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    inf_usr_id_change              integer       unsigned,
    inf_timestamp_change           timestamp     null default null,
    primary key (inf_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_INF_NAME_INTERN on %PREFIX%_invent_fields (inf_name_intern);


/*==============================================================*/
/* Table: adm_invent_data                                       */
/*==============================================================*/
create table %PREFIX%_invent_data
(
    ind_id                         integer       unsigned not null AUTO_INCREMENT,
    ind_itm_id                     integer       unsigned not null,
    ind_inf_id                     integer       unsigned not null,
    ind_value                      varchar(255),
    primary key (ind_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_IND_ITM_INF_ID on %PREFIX%_invent_data (ind_itm_id, ind_inf_id);


/*==============================================================*/
/* Table: adm_invent                                            */
/*==============================================================*/
create table %PREFIX%_invent
(
    inv_id                         integer       unsigned not null AUTO_INCREMENT,
    inv_photo                      blob,
    inv_text                       text,
    inv_for_loan                   boolean       not null default '0',
    inv_last_lent                  timestamp     null default null,
    inv_usr_id_lent                integer         unsigned,
    inv_lent_until                 timestamp     null default null,
    inv_number_lent                integer       not null default 0,
    inv_usr_id_create              integer       unsigned,
    inv_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    inv_usr_id_change              integer       unsigned,
    inv_timestamp_change           timestamp     null default null,
    inv_valid                      boolean       not null default '0',
    primary key (inv_id)
=======
    gbc_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    gbc_gbo_id                     INTEGER       UNSIGNED NOT NULL,
    gbc_name                       VARCHAR(60)   NOT NULL,
    gbc_text                       TEXT          NOT NULL,
    gbc_email                      VARCHAR(50),
    gbc_ip_address                 VARCHAR(39)   NOT NULL,
    gbc_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    gbc_usr_id_create              INTEGER       UNSIGNED,
    gbc_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gbc_usr_id_change              INTEGER       UNSIGNED,
    gbc_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (gbc_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_ids                                               */
/*==============================================================*/
CREATE TABLE %PREFIX%_ids
(
   ids_usr_id                     INTEGER       UNSIGNED NOT NULL,
   ids_reference_id               INTEGER       UNSIGNED NOT NULL
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_invent_fields                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_invent_fields
(
    inf_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    inf_cat_id                     INTEGER       UNSIGNED NOT NULL,
    inf_type                       VARCHAR(30)   NOT NULL,
    inf_name_intern                VARCHAR(110)  NOT NULL,
    inf_name                       VARCHAR(100)  NOT NULL,
    inf_description                TEXT,
    inf_value_list                 TEXT,
    inf_system                     BOOLEAN       NOT NULL DEFAULT '0',
    inf_disabled                   BOOLEAN       NOT NULL DEFAULT '0',
    inf_hidden                     BOOLEAN       NOT NULL DEFAULT '0',
    inf_mandatory                  BOOLEAN       NOT NULL DEFAULT '0',
    inf_sequence                   SMALLINT      NOT NULL,
    inf_usr_id_create              INTEGER       UNSIGNED,
    inf_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inf_usr_id_change              INTEGER       UNSIGNED,
    inf_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (inf_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_INF_NAME_INTERN ON %PREFIX%_invent_fields (inf_name_intern);


/*==============================================================*/
/* Table: adm_invent_data                                       */
/*==============================================================*/
CREATE TABLE %PREFIX%_invent_data
(
    ind_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    ind_itm_id                     INTEGER       UNSIGNED NOT NULL,
    ind_inf_id                     INTEGER       UNSIGNED NOT NULL,
    ind_value                      VARCHAR(255),
    PRIMARY KEY (ind_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_IND_ITM_INF_ID ON %PREFIX%_invent_data (ind_itm_id, ind_inf_id);


/*==============================================================*/
/* Table: adm_invent                                            */
/*==============================================================*/
CREATE TABLE %PREFIX%_invent
(
    inv_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    inv_photo                      BLOB,
    inv_text                       TEXT,
    inv_for_loan                   BOOLEAN       NOT NULL DEFAULT '0',
    inv_last_lent                  TIMESTAMP     NULL DEFAULT NULL,
    inv_usr_id_lent                INTEGER       UNSIGNED,
    inv_lent_until                 TIMESTAMP     NULL DEFAULT NULL,
    inv_number_lent                INTEGER       NOT NULL DEFAULT 0,
    inv_usr_id_create              INTEGER       UNSIGNED,
    inv_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inv_usr_id_change              INTEGER       UNSIGNED,
    inv_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    inv_valid                      BOOLEAN       NOT NULL DEFAULT '0',
    PRIMARY KEY (inv_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_links                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_links
(
<<<<<<< HEAD
    lnk_id                         integer       unsigned not null AUTO_INCREMENT,
    lnk_cat_id                     integer       unsigned not null,
    lnk_name                       varchar(255)  not null,
    lnk_description                text,
    lnk_url                        varchar(2000)  not null,
    lnk_counter                    integer       not null default 0,
    lnk_usr_id_create              integer       unsigned,
    lnk_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    lnk_usr_id_change              integer       unsigned,
    lnk_timestamp_change           timestamp     null default null,
    primary key (lnk_id)
=======
    lnk_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    lnk_cat_id                     INTEGER       UNSIGNED NOT NULL,
    lnk_name                       VARCHAR(255)  NOT NULL,
    lnk_description                TEXT,
    lnk_url                        VARCHAR(2000) NOT NULL,
    lnk_counter                    INTEGER       NOT NULL DEFAULT 0,
    lnk_usr_id_create              INTEGER       UNSIGNED,
    lnk_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lnk_usr_id_change              INTEGER       UNSIGNED,
    lnk_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (lnk_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_lists
(
<<<<<<< HEAD
    lst_id                         integer       unsigned not null AUTO_INCREMENT,
    lst_org_id                     integer       unsigned not null,
    lst_usr_id                     integer       unsigned not null,
    lst_name                       varchar(255),
    lst_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    lst_global                     boolean       not null default '0',
    lst_default                    boolean       not null default '0',
    primary key (lst_id)
=======
    lst_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    lst_org_id                     INTEGER       UNSIGNED NOT NULL,
    lst_usr_id                     INTEGER       UNSIGNED NOT NULL,
    lst_name                       VARCHAR(255),
    lst_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lst_global                     BOOLEAN       NOT NULL DEFAULT '0',
    lst_default                    BOOLEAN       NOT NULL DEFAULT '0',
    PRIMARY KEY (lst_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_list_columns                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_list_columns
(
<<<<<<< HEAD
    lsc_id                         integer       unsigned not null AUTO_INCREMENT,
    lsc_lst_id                     integer       unsigned not null,
    lsc_number                     smallint      not null,
    lsc_usf_id                     integer       unsigned,
    lsc_special_field              varchar(255),
    lsc_sort                       varchar(5),
    lsc_filter                     varchar(255),
    primary key (lsc_id)
=======
    lsc_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    lsc_lst_id                     INTEGER       UNSIGNED NOT NULL,
    lsc_number                     SMALLINT      NOT NULL,
    lsc_usf_id                     INTEGER       UNSIGNED,
    lsc_special_field              VARCHAR(255),
    lsc_sort                       VARCHAR(5),
    lsc_filter                     VARCHAR(255),
    PRIMARY KEY (lsc_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_members                                           */
/*==============================================================*/
CREATE TABLE %PREFIX%_members
(
<<<<<<< HEAD
    mem_id                         integer       unsigned not null AUTO_INCREMENT,
    mem_rol_id                     integer       unsigned not null,
    mem_usr_id                     integer       unsigned not null,
    mem_begin                      date          not null,
    mem_end                        date          not null default '9999-12-31',
    mem_leader                     boolean       not null default '0',
    mem_usr_id_create              integer       unsigned,
    mem_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    mem_usr_id_change              integer       unsigned,
    mem_timestamp_change           timestamp     null default null,
    mem_approved                   integer       unsigned default null,
    mem_comment                    varchar(4000),
    mem_count_guests               integer       unsigned not null default '0',
    primary key (mem_id)
=======
    mem_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    mem_rol_id                     INTEGER       UNSIGNED NOT NULL,
    mem_usr_id                     INTEGER       UNSIGNED NOT NULL,
    mem_begin                      DATE          NOT NULL,
    mem_end                        DATE          NOT NULL DEFAULT '9999-12-31',
    mem_leader                     BOOLEAN       NOT NULL DEFAULT '0',
    mem_usr_id_create              INTEGER       UNSIGNED,
    mem_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mem_usr_id_change              INTEGER       UNSIGNED,
    mem_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    mem_approved                   INTEGER       UNSIGNED DEFAULT NULL,
    mem_comment                    VARCHAR(4000),
    mem_count_guests               INTEGER       UNSIGNED NOT NULL DEFAULT '0',
    PRIMARY KEY (mem_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX IDX_MEM_ROL_USR_ID ON %PREFIX%_members (mem_rol_id, mem_usr_id);

<<<<<<< HEAD
create index IDX_%PREFIX%_MEM_ROL_USR_ID on %PREFIX%_members (mem_rol_id, mem_usr_id);

=======
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_messages                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages
(
<<<<<<< HEAD
    msg_id                        integer         unsigned NOT NULL AUTO_INCREMENT,
    msg_type                      varchar(10)     NOT NULL,
    msg_subject                   varchar(256)    NOT NULL,
    msg_usr_id_sender             integer         unsigned NOT NULL,
    msg_usr_id_receiver           varchar(256)    NOT NULL,
    msg_timestamp                 timestamp       NOT NULL default CURRENT_TIMESTAMP,
    msg_read                      smallint        NOT NULL DEFAULT 0,
    primary key (msg_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;
=======
    msg_id                        INTEGER         UNSIGNED NOT NULL AUTO_INCREMENT,
    msg_type                      VARCHAR(10)     NOT NULL,
    msg_subject                   VARCHAR(256)    NOT NULL,
    msg_usr_id_sender             INTEGER         UNSIGNED NOT NULL,
    msg_usr_id_receiver           VARCHAR(256)    NOT NULL,
    msg_timestamp                 TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    msg_read                      SMALLINT        NOT NULL DEFAULT 0,
    PRIMARY KEY (msg_id)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_messages_content                                  */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages_content
(
<<<<<<< HEAD
    msc_id                        integer         unsigned NOT NULL AUTO_INCREMENT,
    msc_msg_id                    integer         unsigned NOT NULL,
    msc_part_id                   integer         unsigned NOT NULL,
    msc_usr_id                    integer         unsigned,
    msc_message                   text            NOT NULL,
    msc_timestamp                 timestamp       NOT NULL default CURRENT_TIMESTAMP,
    primary key (msc_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;

create index IDX_%PREFIX%_MSC_PART_ID on %PREFIX%_messages_content (msc_part_id);
=======
    msc_id                        INTEGER         UNSIGNED NOT NULL AUTO_INCREMENT,
    msc_msg_id                    INTEGER         UNSIGNED NOT NULL,
    msc_part_id                   INTEGER         UNSIGNED NOT NULL,
    msc_usr_id                    INTEGER         UNSIGNED,
    msc_message                   TEXT            NOT NULL,
    msc_timestamp                 TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (msc_id)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX IDX_MSC_PART_ID ON %PREFIX%_messages_content (msc_part_id);
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_organizations                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_organizations
(
<<<<<<< HEAD
    org_id                         integer       unsigned not null AUTO_INCREMENT,
    org_longname                   varchar(60)   not null,
    org_shortname                  varchar(10)   not null,
    org_org_id_parent              integer       unsigned,
    org_homepage                   varchar(60)   not null,
    primary key (org_id)
=======
    org_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    org_longname                   VARCHAR(60)   NOT NULL,
    org_shortname                  VARCHAR(10)   NOT NULL,
    org_org_id_parent              INTEGER       UNSIGNED,
    org_homepage                   VARCHAR(60)   NOT NULL,
    PRIMARY KEY (org_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX ak_shortname ON %PREFIX%_organizations (org_shortname);

<<<<<<< HEAD
create unique index ak_%PREFIX%_shortname on %PREFIX%_organizations (org_shortname);

=======
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_photos                                            */
/*==============================================================*/
CREATE TABLE %PREFIX%_photos
(
<<<<<<< HEAD
    pho_id                         integer       unsigned not null AUTO_INCREMENT,
    pho_org_shortname              varchar(10)   not null,
    pho_quantity                   integer        unsigned not null default 0,
    pho_name                       varchar(50)   not null,
    pho_begin                      date          not null,
    pho_end                        date          not null,
    pho_photographers              varchar(100),
    pho_locked                     boolean       not null default '0',
    pho_pho_id_parent              integer       unsigned,
    pho_usr_id_create              integer       unsigned,
    pho_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    pho_usr_id_change              integer       unsigned,
    pho_timestamp_change           timestamp     null default null,
    primary key (pho_id)
=======
    pho_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    pho_org_shortname              VARCHAR(10)   NOT NULL,
    pho_quantity                   INTEGER       UNSIGNED NOT NULL DEFAULT 0,
    pho_name                       VARCHAR(50)   NOT NULL,
    pho_begin                      DATE          NOT NULL,
    pho_end                        DATE          NOT NULL,
    pho_photographers              VARCHAR(100),
    pho_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    pho_pho_id_parent              INTEGER       UNSIGNED,
    pho_usr_id_create              INTEGER       UNSIGNED,
    pho_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pho_usr_id_change              INTEGER       UNSIGNED,
    pho_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (pho_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_preferences                                       */
/*==============================================================*/
CREATE TABLE %PREFIX%_preferences
(
<<<<<<< HEAD
    prf_id                         integer       unsigned not null AUTO_INCREMENT,
    prf_org_id                     integer       unsigned not null,
    prf_name                       varchar(50)   not null,
    prf_value                      varchar(255),
    primary key (prf_id)
=======
    prf_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    prf_org_id                     INTEGER       UNSIGNED NOT NULL,
    prf_name                       VARCHAR(50)   NOT NULL,
    prf_value                      VARCHAR(255),
    PRIMARY KEY (prf_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

<<<<<<< HEAD
create unique index IDX_%PREFIX%_PRF_ORG_ID_NAME on %PREFIX%_preferences (prf_org_id, prf_name);
=======
CREATE UNIQUE INDEX IDX_PRF_ORG_ID_NAME ON %PREFIX%_preferences (prf_org_id, prf_name);
>>>>>>> origin/master


/*==============================================================*/
/* Table: adm_registrations                                     */
/*==============================================================*/

CREATE TABLE %PREFIX%_registrations
(
    reg_id                        INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    reg_org_id                    INTEGER       UNSIGNED NOT NULL,
    reg_usr_id                    INTEGER       UNSIGNED NOT NULL,
    reg_timestamp                 TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reg_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_role_dependencies                                 */
/*==============================================================*/
CREATE TABLE %PREFIX%_role_dependencies
(
<<<<<<< HEAD
    rld_rol_id_parent              integer       unsigned not null,
    rld_rol_id_child               integer       unsigned not null,
    rld_comment                    text,
    rld_usr_id                     integer       unsigned,
    rld_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    primary key (rld_rol_id_parent, rld_rol_id_child)
=======
    rld_rol_id_parent              INTEGER       UNSIGNED NOT NULL,
    rld_rol_id_child               INTEGER       UNSIGNED NOT NULL,
    rld_comment                    TEXT,
    rld_usr_id                     INTEGER       UNSIGNED,
    rld_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rld_rol_id_parent, rld_rol_id_child)
>>>>>>> origin/master
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_roles                                             */
/*==============================================================*/
<<<<<<< HEAD
create table %PREFIX%_roles
(
    rol_id                         integer       unsigned not null AUTO_INCREMENT,
    rol_cat_id                     integer       unsigned not null,
    rol_lst_id                     integer       unsigned,
    rol_name                       varchar(50)   not null,
    rol_description                varchar(4000),
    rol_assign_roles               boolean       not null default '0',
    rol_approve_users              boolean       not null default '0',
    rol_announcements              boolean       not null default '0',
    rol_dates                      boolean       not null default '0',
    rol_download                   boolean       not null default '0',
    rol_edit_user                  boolean       not null default '0',
    rol_guestbook                  boolean       not null default '0',
    rol_guestbook_comments         boolean       not null default '0',
    rol_inventory                  boolean       not null default '0',
    rol_mail_to_all                boolean       not null default '0',
    rol_mail_this_role             smallint      not null default 0,
    rol_photo                      boolean       not null default '0',
    rol_profile                    boolean       not null default '0',
    rol_weblinks                   boolean       not null default '0',
    rol_this_list_view             smallint      not null default 0,
    rol_all_lists_view             boolean       not null default '0',
    rol_default_registration       boolean       not null default '0',
    rol_leader_rights              smallint      not null default 0,
    rol_start_date                 date,
    rol_start_time                 time,
    rol_end_date                   date,
    rol_end_time                   time,
    rol_weekday                    smallint,
    rol_location                   varchar(30),
    rol_max_members                integer,
    rol_cost                       float         unsigned,
    rol_cost_period                smallint,
    rol_usr_id_create              integer       unsigned,
    rol_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    rol_usr_id_change              integer       unsigned,
    rol_timestamp_change           timestamp     null default null,
    rol_valid                      boolean       not null default '1',
    rol_system                     boolean       not null default '0',
    rol_visible                    boolean       not null default '1',
    rol_webmaster                  boolean       not null default '0',
    primary key (rol_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;
=======
CREATE TABLE %PREFIX%_roles
(
    rol_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    rol_cat_id                     INTEGER       UNSIGNED NOT NULL,
    rol_lst_id                     INTEGER       UNSIGNED,
    rol_name                       VARCHAR(50)   NOT NULL,
    rol_description                VARCHAR(4000),
    rol_assign_roles               BOOLEAN       NOT NULL DEFAULT '0',
    rol_approve_users              BOOLEAN       NOT NULL DEFAULT '0',
    rol_announcements              BOOLEAN       NOT NULL DEFAULT '0',
    rol_dates                      BOOLEAN       NOT NULL DEFAULT '0',
    rol_download                   BOOLEAN       NOT NULL DEFAULT '0',
    rol_edit_user                  BOOLEAN       NOT NULL DEFAULT '0',
    rol_guestbook                  BOOLEAN       NOT NULL DEFAULT '0',
    rol_guestbook_comments         BOOLEAN       NOT NULL DEFAULT '0',
    rol_inventory                  BOOLEAN       NOT NULL DEFAULT '0',
    rol_mail_to_all                BOOLEAN       NOT NULL DEFAULT '0',
    rol_mail_this_role             SMALLINT      NOT NULL DEFAULT 0,
    rol_photo                      BOOLEAN       NOT NULL DEFAULT '0',
    rol_profile                    BOOLEAN       NOT NULL DEFAULT '0',
    rol_weblinks                   BOOLEAN       NOT NULL DEFAULT '0',
    rol_this_list_view             SMALLINT      NOT NULL DEFAULT 0,
    rol_all_lists_view             BOOLEAN       NOT NULL DEFAULT '0',
    rol_default_registration       BOOLEAN       NOT NULL DEFAULT '0',
    rol_leader_rights              SMALLINT      NOT NULL DEFAULT 0,
    rol_start_date                 DATE,
    rol_start_time                 TIME,
    rol_end_date                   DATE,
    rol_end_time                   TIME,
    rol_weekday                    SMALLINT,
    rol_location                   VARCHAR(30),
    rol_max_members                INTEGER,
    rol_cost                       FLOAT         UNSIGNED,
    rol_cost_period                SMALLINT,
    rol_usr_id_create              INTEGER       UNSIGNED,
    rol_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rol_usr_id_change              INTEGER       UNSIGNED,
    rol_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    rol_valid                      BOOLEAN       NOT NULL DEFAULT '1',
    rol_system                     BOOLEAN       NOT NULL DEFAULT '0',
    rol_visible                    BOOLEAN       NOT NULL DEFAULT '1',
    rol_webmaster                  BOOLEAN       NOT NULL DEFAULT '0',
    PRIMARY KEY (rol_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;
>>>>>>> origin/master


/*==============================================================*/
/* Table: adm_rooms                                             */
/*==============================================================*/

CREATE TABLE %PREFIX%_rooms
(
<<<<<<< HEAD
    room_id                       integer       unsigned not null AUTO_INCREMENT,
    room_name                     varchar(50)   not null,
    room_description              text,
    room_capacity                 integer       not null,
    room_overhang                 integer,
    room_usr_id_create            integer       unsigned,
    room_timestamp_create         timestamp     not null default CURRENT_TIMESTAMP,
    room_usr_id_change            integer       unsigned,
    room_timestamp_change         timestamp     null default null,
    primary key (room_id)
=======
    room_id                       INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    room_name                     VARCHAR(50)   NOT NULL,
    room_description              TEXT,
    room_capacity                 INTEGER       NOT NULL,
    room_overhang                 INTEGER,
    room_usr_id_create            INTEGER       UNSIGNED,
    room_timestamp_create         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    room_usr_id_change            INTEGER       UNSIGNED,
    room_timestamp_change         TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (room_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_sessions                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_sessions
(
<<<<<<< HEAD
    ses_id                         integer       unsigned not null AUTO_INCREMENT,
    ses_usr_id                     integer       unsigned default NULL,
    ses_org_id                     integer       unsigned not null,
    ses_session_id                 varchar(255)  not null,
    ses_device_id                  varchar(255),
    ses_begin                      timestamp        null default null,
    ses_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    ses_ip_address                 varchar(39)   not null,
    ses_binary                     blob,
    ses_renew                      smallint      not null default 0,
    primary key (ses_id)
=======
    ses_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    ses_usr_id                     INTEGER       UNSIGNED DEFAULT NULL,
    ses_org_id                     INTEGER       UNSIGNED NOT NULL,
    ses_session_id                 VARCHAR(255)  NOT NULL,
    ses_device_id                  VARCHAR(255),
    ses_begin                      TIMESTAMP     NULL DEFAULT NULL,
    ses_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ses_ip_address                 VARCHAR(39)   NOT NULL,
    ses_binary                     BLOB,
    ses_renew                      SMALLINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (ses_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

<<<<<<< HEAD
create index IDX_%PREFIX%_SESSION_ID on %PREFIX%_sessions (ses_session_id);
=======
CREATE INDEX IDX_SESSION_ID ON %PREFIX%_sessions (ses_session_id);
>>>>>>> origin/master


/*==============================================================*/
/* Table: adm_texts                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_texts
(
<<<<<<< HEAD
    txt_id                         integer       unsigned not null AUTO_INCREMENT,
    txt_org_id                     integer       unsigned not null,
    txt_name                       varchar(30)   not null,
    txt_text                       text,
    primary key (txt_id)
=======
    txt_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    txt_org_id                     INTEGER       UNSIGNED NOT NULL,
    txt_name                       VARCHAR(30)   NOT NULL,
    txt_text                       TEXT,
    PRIMARY KEY (txt_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_user_fields                                       */
/*==============================================================*/
<<<<<<< HEAD
create table %PREFIX%_user_fields
(
    usf_id                         integer       unsigned not null AUTO_INCREMENT,
    usf_cat_id                     integer       unsigned not null,
    usf_type                       varchar(30)   not null,
    usf_name_intern                varchar(110)  not null,
    usf_name                       varchar(100)  not null,
    usf_description                text,
    usf_value_list                 text,
    usf_icon                       varchar(2000),
    usf_url                        varchar(2000),
    usf_system                     boolean       not null default '0',
    usf_disabled                   boolean       not null default '0',
    usf_hidden                     boolean       not null default '0',
    usf_mandatory                  boolean       not null default '0',
    usf_sequence                   smallint      not null,
    usf_usr_id_create              integer       unsigned,
    usf_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    usf_usr_id_change              integer       unsigned,
    usf_timestamp_change           timestamp     null default null,
    primary key (usf_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_USF_NAME_INTERN on %PREFIX%_user_fields (usf_name_intern);
=======
CREATE TABLE %PREFIX%_user_fields
(
    usf_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    usf_cat_id                     INTEGER       UNSIGNED NOT NULL,
    usf_type                       VARCHAR(30)   NOT NULL,
    usf_name_intern                VARCHAR(110)  NOT NULL,
    usf_name                       VARCHAR(100)  NOT NULL,
    usf_description                TEXT,
    usf_value_list                 TEXT,
    usf_icon                       VARCHAR(2000),
    usf_url                        VARCHAR(2000),
    usf_system                     BOOLEAN       NOT NULL DEFAULT '0',
    usf_disabled                   BOOLEAN       NOT NULL DEFAULT '0',
    usf_hidden                     BOOLEAN       NOT NULL DEFAULT '0',
    usf_mandatory                  BOOLEAN       NOT NULL DEFAULT '0',
    usf_sequence                   SMALLINT      NOT NULL,
    usf_usr_id_create              INTEGER       UNSIGNED,
    usf_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usf_usr_id_change              INTEGER       UNSIGNED,
    usf_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (usf_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_USF_NAME_INTERN ON %PREFIX%_user_fields (usf_name_intern);
>>>>>>> origin/master


/*==============================================================*/
/* Table: adm_user_data                                         */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_data
(
<<<<<<< HEAD
    usd_id                         integer       unsigned not null AUTO_INCREMENT,
    usd_usr_id                     integer       unsigned not null,
    usd_usf_id                     integer       unsigned not null,
    usd_value                      varchar(4000),
    primary key (usd_id)
=======
    usd_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    usd_usr_id                     INTEGER       UNSIGNED NOT NULL,
    usd_usf_id                     INTEGER       UNSIGNED NOT NULL,
    usd_value                      VARCHAR(4000),
    PRIMARY KEY (usd_id)
>>>>>>> origin/master
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

<<<<<<< HEAD
create unique index IDX_%PREFIX%_USD_USR_USF_ID on %PREFIX%_user_data (usd_usr_id, usd_usf_id);
=======
CREATE UNIQUE INDEX IDX_USD_USR_USF_ID ON %PREFIX%_user_data (usd_usr_id, usd_usf_id);
>>>>>>> origin/master

/*==============================================================*/
/* Table: adm_user_log                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_log (
  usl_id                INTEGER                  NOT NULL AUTO_INCREMENT ,
<<<<<<< HEAD
  usl_usr_id            INTEGER         unsigned NOT NULL ,
  usl_usf_id            INTEGER         unsigned NOT NULL ,
  usl_value_old         VARCHAR(4000)             NULL ,
  usl_value_new         VARCHAR(4000)             NULL ,
  usl_usr_id_create     INTEGER         unsigned NULL ,
  usl_timestamp_create  TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  usl_comment           VARCHAR(255) NULL ,
=======
  usl_usr_id            INTEGER         UNSIGNED NOT NULL ,
  usl_usf_id            INTEGER         UNSIGNED NOT NULL ,
  usl_value_old         VARCHAR(4000)            NULL ,
  usl_value_new         VARCHAR(4000)            NULL ,
  usl_usr_id_create     INTEGER         UNSIGNED NULL ,
  usl_timestamp_create  TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  usl_comment           VARCHAR(255)             NULL ,
>>>>>>> origin/master
  PRIMARY KEY (usl_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_users                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_users
(
<<<<<<< HEAD
    usr_id                         integer       unsigned not null AUTO_INCREMENT,
    usr_login_name                 varchar(35),
    usr_password                   varchar(35),
    usr_new_password               varchar(35),
    usr_photo                      blob,
    usr_text                       text,
    usr_activation_code            varchar(10),
    usr_last_login                 timestamp     null default null,
    usr_actual_login               timestamp     null default null,
    usr_number_login               integer       not null default 0,
    usr_date_invalid               timestamp     null default null,
    usr_number_invalid             smallint      not null default 0,
    usr_usr_id_create              integer       unsigned,
    usr_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    usr_usr_id_change              integer       unsigned,
    usr_timestamp_change           timestamp     null default null,
    usr_valid                      boolean       not null default '0',
    primary key (usr_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_USR_LOGIN_NAME on %PREFIX%_users (usr_login_name);
=======
    usr_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    usr_login_name                 VARCHAR(35),
    usr_password                   VARCHAR(35),
    usr_new_password               VARCHAR(35),
    usr_photo                      BLOB,
    usr_text                       TEXT,
    usr_activation_code            VARCHAR(10),
    usr_last_login                 TIMESTAMP     NULL DEFAULT NULL,
    usr_actual_login               TIMESTAMP     NULL DEFAULT NULL,
    usr_number_login               INTEGER       NOT NULL DEFAULT 0,
    usr_date_invalid               TIMESTAMP     NULL DEFAULT NULL,
    usr_number_invalid             SMALLINT      NOT NULL DEFAULT 0,
    usr_usr_id_create              INTEGER       UNSIGNED,
    usr_timestamp_create           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usr_usr_id_change              INTEGER       UNSIGNED,
    usr_timestamp_change           TIMESTAMP     NULL DEFAULT NULL,
    usr_valid                      BOOLEAN       NOT NULL DEFAULT '0',
    PRIMARY KEY (usr_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_USR_LOGIN_NAME ON %PREFIX%_users (usr_login_name);
>>>>>>> origin/master

/*==============================================================*/
/* Constraints                                                  */
/*==============================================================*/
<<<<<<< HEAD
alter table %PREFIX%_announcements add constraint %PREFIX%_FK_ANN_ORG foreign key (ann_org_shortname)
      references %PREFIX%_organizations (org_shortname) on delete restrict on update restrict;
alter table %PREFIX%_announcements add constraint %PREFIX%_FK_ANN_USR_CREATE foreign key (ann_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_announcements add constraint %PREFIX%_FK_ANN_USR_CHANGE foreign key (ann_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_USR foreign key (atl_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_ORG foreign key (atl_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_USR_CREATE foreign key (cat_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_USR_CHANGE foreign key (cat_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_date_role add constraint %PREFIX%_FK_DTR_DAT foreign key (dtr_dat_id)
      references %PREFIX%_dates (dat_id) on delete restrict on update restrict;
alter table %PREFIX%_date_role add constraint %PREFIX%_FK_DTR_ROL foreign key (dtr_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;

alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_CAT foreign key (dat_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_ROL foreign key (dat_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_ROOM foreign key (dat_room_id)
      references %PREFIX%_rooms (room_id) on delete set null on update restrict;
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_USR_CREATE foreign key (dat_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_USR_CHANGE foreign key (dat_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_files add constraint %PREFIX%_FK_FIL_FOL foreign key (fil_fol_id)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PREFIX%_files add constraint %PREFIX%_FK_FIL_USR foreign key (fil_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_folder_roles add constraint %PREFIX%_FK_FLR_FOL foreign key (flr_fol_id)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PREFIX%_folder_roles add constraint %PREFIX%_FK_FLR_ROL foreign key (flr_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;

alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_FOL_PARENT foreign key (fol_fol_id_parent)
      references %PREFIX%_folders (fol_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_USR foreign key (fol_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_ORG foreign key (gbo_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_USR_CREATE foreign key (gbo_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_USR_CHANGE foreign key (gbo_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_guestbook_comments add constraint %PREFIX%_FK_GBC_GBO foreign key (gbc_gbo_id)
      references %PREFIX%_guestbook (gbo_id) on delete restrict on update restrict;
alter table %PREFIX%_guestbook_comments add constraint %PREFIX%_FK_GBC_USR_CREATE foreign key (gbc_usr_id_create)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PREFIX%_guestbook_comments add constraint %PREFIX%_FK_GBC_USR_CHANGE foreign key (gbc_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_ids add constraint %PREFIX%_FK_IDS_USR_ID foreign key (ids_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

alter table %PREFIX%_links add constraint %PREFIX%_FK_LNK_CAT foreign key (lnk_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PREFIX%_links add constraint %PREFIX%_FK_LNK_USR_CREATE foreign key (lnk_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_links add constraint %PREFIX%_FK_LNK_USR_CHANGE foreign key (lnk_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_lists add constraint %PREFIX%_FK_LST_USR foreign key (lst_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PREFIX%_lists add constraint %PREFIX%_FK_LST_ORG foreign key (lst_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

alter table %PREFIX%_list_columns add constraint %PREFIX%_FK_LSC_LST foreign key (lsc_lst_id)
      references %PREFIX%_lists (lst_id) on delete restrict on update restrict;
alter table %PREFIX%_list_columns add constraint %PREFIX%_FK_LSC_USF foreign key (lsc_usf_id)
      references %PREFIX%_user_fields (usf_id) on delete restrict on update restrict;

alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_ROL foreign key (mem_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_USR foreign key (mem_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;
alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_USR_CREATE foreign key (mem_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_USR_CHANGE foreign key (mem_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_messages add constraint %PREFIX%_FK_MSG_USR_SENDER foreign key (msg_usr_id_sender)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

alter table %PREFIX%_messages_content add constraint %PREFIX%_FK_MSC_MSG_ID foreign key (msc_msg_id)
      references %PREFIX%_messages (msg_id) on delete restrict on update restrict;
alter table %PREFIX%_messages_content add constraint %PREFIX%_FK_MSC_USR_ID foreign key (msc_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_organizations add constraint %PREFIX%_FK_ORG_ORG_PARENT foreign key (org_org_id_parent)
      references %PREFIX%_organizations (org_id) on delete set null on update restrict;

alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_PHO_PARENT foreign key (pho_pho_id_parent)
      references %PREFIX%_photos (pho_id) on delete set null on update restrict;
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_ORG foreign key (pho_org_shortname)
      references %PREFIX%_organizations (org_shortname) on delete restrict on update restrict;
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_USR_CREATE foreign key (pho_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_USR_CHANGE foreign key (pho_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_preferences add constraint %PREFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

alter table %PREFIX%_registrations add CONSTRAINT %PREFIX%_FK_REG_ORG FOREIGN KEY (reg_org_id)
=======
ALTER TABLE %PREFIX%_announcements ADD CONSTRAINT %PREFIX%_FK_ANN_ORG FOREIGN KEY (ann_org_shortname)
      REFERENCES %PREFIX%_organizations (org_shortname) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_announcements ADD CONSTRAINT %PREFIX%_FK_ANN_USR_CREATE FOREIGN KEY (ann_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_announcements ADD CONSTRAINT %PREFIX%_FK_ANN_USR_CHANGE FOREIGN KEY (ann_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_auto_login ADD CONSTRAINT %PREFIX%_FK_ATL_USR FOREIGN KEY (atl_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_auto_login ADD CONSTRAINT %PREFIX%_FK_ATL_ORG FOREIGN KEY (atl_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_categories ADD CONSTRAINT %PREFIX%_FK_CAT_ORG FOREIGN KEY (cat_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_categories ADD CONSTRAINT %PREFIX%_FK_CAT_USR_CREATE FOREIGN KEY (cat_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_categories ADD CONSTRAINT %PREFIX%_FK_CAT_USR_CHANGE FOREIGN KEY (cat_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_date_role ADD CONSTRAINT %PREFIX%_FK_DTR_DAT FOREIGN KEY (dtr_dat_id)
      REFERENCES %PREFIX%_dates (dat_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_date_role ADD CONSTRAINT %PREFIX%_FK_DTR_ROL FOREIGN KEY (dtr_rol_id)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_CAT FOREIGN KEY (dat_cat_id)
      REFERENCES %PREFIX%_categories (cat_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_ROL FOREIGN KEY (dat_rol_id)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_ROOM FOREIGN KEY (dat_room_id)
      REFERENCES %PREFIX%_rooms (room_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_USR_CREATE FOREIGN KEY (dat_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_dates ADD CONSTRAINT %PREFIX%_FK_DAT_USR_CHANGE FOREIGN KEY (dat_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_files ADD CONSTRAINT %PREFIX%_FK_FIL_FOL FOREIGN KEY (fil_fol_id)
      REFERENCES %PREFIX%_folders (fol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_files ADD CONSTRAINT %PREFIX%_FK_FIL_USR FOREIGN KEY (fil_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_folder_roles ADD CONSTRAINT %PREFIX%_FK_FLR_FOL FOREIGN KEY (flr_fol_id)
      REFERENCES %PREFIX%_folders (fol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_folder_roles ADD CONSTRAINT %PREFIX%_FK_FLR_ROL FOREIGN KEY (flr_rol_id)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_folders ADD CONSTRAINT %PREFIX%_FK_FOL_ORG FOREIGN KEY (fol_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_folders ADD CONSTRAINT %PREFIX%_FK_FOL_FOL_PARENT FOREIGN KEY (fol_fol_id_parent)
      REFERENCES %PREFIX%_folders (fol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_folders ADD CONSTRAINT %PREFIX%_FK_FOL_USR FOREIGN KEY (fol_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_guestbook ADD CONSTRAINT %PREFIX%_FK_GBO_ORG FOREIGN KEY (gbo_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_guestbook ADD CONSTRAINT %PREFIX%_FK_GBO_USR_CREATE FOREIGN KEY (gbo_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_guestbook ADD CONSTRAINT %PREFIX%_FK_GBO_USR_CHANGE FOREIGN KEY (gbo_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_guestbook_comments ADD CONSTRAINT %PREFIX%_FK_GBC_GBO FOREIGN KEY (gbc_gbo_id)
      REFERENCES %PREFIX%_guestbook (gbo_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_guestbook_comments ADD CONSTRAINT %PREFIX%_FK_GBC_USR_CREATE FOREIGN KEY (gbc_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_guestbook_comments ADD CONSTRAINT %PREFIX%_FK_GBC_USR_CHANGE FOREIGN KEY (gbc_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_ids ADD CONSTRAINT %PREFIX%_FK_IDS_USR_ID FOREIGN KEY (ids_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_links ADD CONSTRAINT %PREFIX%_FK_LNK_CAT FOREIGN KEY (lnk_cat_id)
      REFERENCES %PREFIX%_categories (cat_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_links ADD CONSTRAINT %PREFIX%_FK_LNK_USR_CREATE FOREIGN KEY (lnk_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_links ADD CONSTRAINT %PREFIX%_FK_LNK_USR_CHANGE FOREIGN KEY (lnk_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_lists ADD CONSTRAINT %PREFIX%_FK_LST_USR FOREIGN KEY (lst_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_lists ADD CONSTRAINT %PREFIX%_FK_LST_ORG FOREIGN KEY (lst_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_list_columns ADD CONSTRAINT %PREFIX%_FK_LSC_LST FOREIGN KEY (lsc_lst_id)
      REFERENCES %PREFIX%_lists (lst_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_list_columns ADD CONSTRAINT %PREFIX%_FK_LSC_USF FOREIGN KEY (lsc_usf_id)
      REFERENCES %PREFIX%_user_fields (usf_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_members ADD CONSTRAINT %PREFIX%_FK_MEM_ROL FOREIGN KEY (mem_rol_id)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_members ADD CONSTRAINT %PREFIX%_FK_MEM_USR FOREIGN KEY (mem_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_members ADD CONSTRAINT %PREFIX%_FK_MEM_USR_CREATE FOREIGN KEY (mem_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_members ADD CONSTRAINT %PREFIX%_FK_MEM_USR_CHANGE FOREIGN KEY (mem_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages ADD CONSTRAINT %PREFIX%_FK_MSG_USR_SENDER FOREIGN KEY (msg_usr_id_sender)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages_content ADD CONSTRAINT %PREFIX%_FK_MSC_MSG_ID FOREIGN KEY (msc_msg_id)
      REFERENCES %PREFIX%_messages (msg_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_messages_content ADD CONSTRAINT %PREFIX%_FK_MSC_USR_ID FOREIGN KEY (msc_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_organizations ADD CONSTRAINT %PREFIX%_FK_ORG_ORG_PARENT FOREIGN KEY (org_org_id_parent)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_photos ADD CONSTRAINT %PREFIX%_FK_PHO_PHO_PARENT FOREIGN KEY (pho_pho_id_parent)
      REFERENCES %PREFIX%_photos (pho_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_photos ADD CONSTRAINT %PREFIX%_FK_PHO_ORG FOREIGN KEY (pho_org_shortname)
      REFERENCES %PREFIX%_organizations (org_shortname) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_photos ADD CONSTRAINT %PREFIX%_FK_PHO_USR_CREATE FOREIGN KEY (pho_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_photos ADD CONSTRAINT %PREFIX%_FK_PHO_USR_CHANGE FOREIGN KEY (pho_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_preferences ADD CONSTRAINT %PREFIX%_FK_PRF_ORG FOREIGN KEY (prf_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_registrations ADD CONSTRAINT %PREFIX%_FK_REG_ORG FOREIGN KEY (reg_org_id)
>>>>>>> origin/master
    REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_registrations ADD CONSTRAINT %PREFIX%_FK_REG_USR FOREIGN KEY (reg_usr_id)
    REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

<<<<<<< HEAD
alter table %PREFIX%_role_dependencies add constraint %PREFIX%_FK_RLD_ROL_CHILD foreign key (rld_rol_id_child)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_role_dependencies add constraint %PREFIX%_FK_RLD_ROL_PARENT foreign key (rld_rol_id_parent)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_role_dependencies add constraint %PREFIX%_FK_RLD_USR foreign key (rld_usr_id)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_roles add constraint %PREFIX%_FK_ROL_CAT foreign key (rol_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PREFIX%_roles add constraint %PREFIX%_FK_ROL_LST_ID foreign key (rol_lst_id)
      references %PREFIX%_lists (lst_id) on delete set null on update set null;
alter table %PREFIX%_roles add constraint %PREFIX%_FK_ROL_USR_CREATE foreign key (rol_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_roles add constraint %PREFIX%_FK_ROL_USR_CHANGE foreign key (rol_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_rooms add constraint %PREFIX%_FK_ROOM_USR_CREATE foreign key (room_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_rooms add constraint %PREFIX%_FK_ROOM_USR_CHANGE foreign key (room_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_sessions add constraint %PREFIX%_FK_SES_ORG foreign key (ses_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_sessions add constraint %PREFIX%_FK_SES_USR foreign key (ses_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

alter table %PREFIX%_texts add constraint %PREFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

alter table %PREFIX%_user_fields add constraint %PREFIX%_FK_USF_CAT foreign key (usf_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict;
alter table %PREFIX%_user_fields add constraint %PREFIX%_FK_USF_USR_CREATE foreign key (usf_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_user_fields add constraint %PREFIX%_FK_USF_USR_CHANGE foreign key (usf_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_user_data add constraint %PREFIX%_FK_USD_USF foreign key (usd_usf_id)
      references %PREFIX%_user_fields (usf_id) on delete restrict on update restrict;
alter table %PREFIX%_user_data add constraint %PREFIX%_FK_USD_USR foreign key (usd_usr_id)
      references %PREFIX%_users (usr_id) on delete restrict on update restrict;

alter table %PREFIX%_user_log add CONSTRAINT %PREFIX%_FK_USER_LOG_1 FOREIGN KEY (usl_usr_id )
=======
ALTER TABLE %PREFIX%_role_dependencies ADD CONSTRAINT %PREFIX%_FK_RLD_ROL_CHILD FOREIGN KEY (rld_rol_id_child)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_role_dependencies ADD CONSTRAINT %PREFIX%_FK_RLD_ROL_PARENT FOREIGN KEY (rld_rol_id_parent)
      REFERENCES %PREFIX%_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_role_dependencies ADD CONSTRAINT %PREFIX%_FK_RLD_USR FOREIGN KEY (rld_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_roles ADD CONSTRAINT %PREFIX%_FK_ROL_CAT FOREIGN KEY (rol_cat_id)
      REFERENCES %PREFIX%_categories (cat_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_roles ADD CONSTRAINT %PREFIX%_FK_ROL_LST_ID FOREIGN KEY (rol_lst_id)
      REFERENCES %PREFIX%_lists (lst_id) ON DELETE SET NULL ON update set NULL;
ALTER TABLE %PREFIX%_roles ADD CONSTRAINT %PREFIX%_FK_ROL_USR_CREATE FOREIGN KEY (rol_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_roles ADD CONSTRAINT %PREFIX%_FK_ROL_USR_CHANGE FOREIGN KEY (rol_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_rooms ADD CONSTRAINT %PREFIX%_FK_ROOM_USR_CREATE FOREIGN KEY (room_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_rooms ADD CONSTRAINT %PREFIX%_FK_ROOM_USR_CHANGE FOREIGN KEY (room_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_sessions ADD CONSTRAINT %PREFIX%_FK_SES_ORG FOREIGN KEY (ses_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_sessions ADD CONSTRAINT %PREFIX%_FK_SES_USR FOREIGN KEY (ses_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_texts ADD CONSTRAINT %PREFIX%_FK_TXT_ORG FOREIGN KEY (txt_org_id)
      REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_fields ADD CONSTRAINT %PREFIX%_FK_USF_CAT FOREIGN KEY (usf_cat_id)
      REFERENCES %PREFIX%_categories (cat_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_fields ADD CONSTRAINT %PREFIX%_FK_USF_USR_CREATE FOREIGN KEY (usf_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_fields ADD CONSTRAINT %PREFIX%_FK_USF_USR_CHANGE FOREIGN KEY (usf_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_data ADD CONSTRAINT %PREFIX%_FK_USD_USF FOREIGN KEY (usd_usf_id)
      REFERENCES %PREFIX%_user_fields (usf_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_data ADD CONSTRAINT %PREFIX%_FK_USD_USR FOREIGN KEY (usd_usr_id)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_log ADD CONSTRAINT %PREFIX%_FK_USER_LOG_1 FOREIGN KEY (usl_usr_id )
>>>>>>> origin/master
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_log ADD CONSTRAINT %PREFIX%_FK_USER_LOG_2 FOREIGN KEY (usl_usr_id_create )
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_log ADD CONSTRAINT %PREFIX%_FK_USER_LOG_3 FOREIGN KEY (usl_usf_id )
    REFERENCES %PREFIX%_user_fields (usf_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;

<<<<<<< HEAD
alter table %PREFIX%_users add constraint %PREFIX%_FK_USR_USR_CREATE foreign key (usr_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_users add constraint %PREFIX%_FK_USR_USR_CHANGE foreign key (usr_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
=======
ALTER TABLE %PREFIX%_users ADD CONSTRAINT %PREFIX%_FK_USR_USR_CREATE FOREIGN KEY (usr_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_users ADD CONSTRAINT %PREFIX%_FK_USR_USR_CHANGE FOREIGN KEY (usr_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
>>>>>>> origin/master
