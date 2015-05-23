/******************************************************************************
 * SQL script with database structure
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ******************************************************************************/


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


/*==============================================================*/
/* Table: adm_announcements                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_announcements
(
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
    atl_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    atl_session_id                 VARCHAR(35)   NOT NULL,
    atl_org_id                     INTEGER       UNSIGNED NOT NULL,
    atl_usr_id                     INTEGER       UNSIGNED NOT NULL,
    atl_last_login                 TIMESTAMP     NULL DEFAULT NULL,
    atl_ip_address                 VARCHAR(39)   NOT NULL,
    PRIMARY KEY (atl_id)
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
    com_id                        INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    com_type                      VARCHAR(10)   NOT NULL,
    com_name                      VARCHAR(255)  NOT NULL,
    com_name_intern               VARCHAR(255)  NOT NULL,
    com_version                   VARCHAR(10)   NOT NULL,
    com_beta                      SMALLINT,
    com_update_step               INTEGER       NOT NULL DEFAULT 0,
    com_timestamp_installed       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (com_id)
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

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_files
(
    fil_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    fil_fol_id                     INTEGER       UNSIGNED NOT NULL,
    fil_name                       VARCHAR(255)  NOT NULL,
    fil_description                TEXT,
    fil_locked                     BOOLEAN       NOT NULL DEFAULT '0',
    fil_counter                    INTEGER,
    fil_usr_id                     INTEGER       UNSIGNED,
    fil_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fil_id)
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
    flr_fol_id                     INTEGER       UNSIGNED NOT NULL,
    flr_rol_id                     INTEGER       UNSIGNED NOT NULL,
    PRIMARY KEY (flr_fol_id, flr_rol_id)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
CREATE TABLE %PREFIX%_folders
(
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
    lst_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    lst_org_id                     INTEGER       UNSIGNED NOT NULL,
    lst_usr_id                     INTEGER       UNSIGNED NOT NULL,
    lst_name                       VARCHAR(255),
    lst_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lst_global                     BOOLEAN       NOT NULL DEFAULT '0',
    lst_default                    BOOLEAN       NOT NULL DEFAULT '0',
    PRIMARY KEY (lst_id)
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
    lsc_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    lsc_lst_id                     INTEGER       UNSIGNED NOT NULL,
    lsc_number                     SMALLINT      NOT NULL,
    lsc_usf_id                     INTEGER       UNSIGNED,
    lsc_special_field              VARCHAR(255),
    lsc_sort                       VARCHAR(5),
    lsc_filter                     VARCHAR(255),
    PRIMARY KEY (lsc_id)
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
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX IDX_MEM_ROL_USR_ID ON %PREFIX%_members (mem_rol_id, mem_usr_id);


/*==============================================================*/
/* Table: adm_messages                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages
(
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

/*==============================================================*/
/* Table: adm_messages_content                                  */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages_content
(
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

/*==============================================================*/
/* Table: adm_organizations                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_organizations
(
    org_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    org_longname                   VARCHAR(60)   NOT NULL,
    org_shortname                  VARCHAR(10)   NOT NULL,
    org_org_id_parent              INTEGER       UNSIGNED,
    org_homepage                   VARCHAR(60)   NOT NULL,
    PRIMARY KEY (org_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX ak_shortname ON %PREFIX%_organizations (org_shortname);


/*==============================================================*/
/* Table: adm_photos                                            */
/*==============================================================*/
CREATE TABLE %PREFIX%_photos
(
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
    prf_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    prf_org_id                     INTEGER       UNSIGNED NOT NULL,
    prf_name                       VARCHAR(50)   NOT NULL,
    prf_value                      VARCHAR(255),
    PRIMARY KEY (prf_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_PRF_ORG_ID_NAME ON %PREFIX%_preferences (prf_org_id, prf_name);


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
    rld_rol_id_parent              INTEGER       UNSIGNED NOT NULL,
    rld_rol_id_child               INTEGER       UNSIGNED NOT NULL,
    rld_comment                    TEXT,
    rld_usr_id                     INTEGER       UNSIGNED,
    rld_timestamp                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rld_rol_id_parent, rld_rol_id_child)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_roles                                             */
/*==============================================================*/
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


/*==============================================================*/
/* Table: adm_rooms                                             */
/*==============================================================*/

CREATE TABLE %PREFIX%_rooms
(
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
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX IDX_SESSION_ID ON %PREFIX%_sessions (ses_session_id);


/*==============================================================*/
/* Table: adm_texts                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_texts
(
    txt_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    txt_org_id                     INTEGER       UNSIGNED NOT NULL,
    txt_name                       VARCHAR(30)   NOT NULL,
    txt_text                       TEXT,
    PRIMARY KEY (txt_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_user_fields                                       */
/*==============================================================*/
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


/*==============================================================*/
/* Table: adm_user_data                                         */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_data
(
    usd_id                         INTEGER       UNSIGNED NOT NULL AUTO_INCREMENT,
    usd_usr_id                     INTEGER       UNSIGNED NOT NULL,
    usd_usf_id                     INTEGER       UNSIGNED NOT NULL,
    usd_value                      VARCHAR(4000),
    PRIMARY KEY (usd_id)
)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX IDX_USD_USR_USF_ID ON %PREFIX%_user_data (usd_usr_id, usd_usf_id);

/*==============================================================*/
/* Table: adm_user_log                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_log (
  usl_id                INTEGER                  NOT NULL AUTO_INCREMENT ,
  usl_usr_id            INTEGER         UNSIGNED NOT NULL ,
  usl_usf_id            INTEGER         UNSIGNED NOT NULL ,
  usl_value_old         VARCHAR(4000)            NULL ,
  usl_value_new         VARCHAR(4000)            NULL ,
  usl_usr_id_create     INTEGER         UNSIGNED NULL ,
  usl_timestamp_create  TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  usl_comment           VARCHAR(255)             NULL ,
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

/*==============================================================*/
/* Constraints                                                  */
/*==============================================================*/
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
    REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_registrations ADD CONSTRAINT %PREFIX%_FK_REG_USR FOREIGN KEY (reg_usr_id)
    REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

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
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_log ADD CONSTRAINT %PREFIX%_FK_USER_LOG_2 FOREIGN KEY (usl_usr_id_create )
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_user_log ADD CONSTRAINT %PREFIX%_FK_USER_LOG_3 FOREIGN KEY (usl_usf_id )
    REFERENCES %PREFIX%_user_fields (usf_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_users ADD CONSTRAINT %PREFIX%_FK_USR_USR_CREATE FOREIGN KEY (usr_usr_id_create)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE %PREFIX%_users ADD CONSTRAINT %PREFIX%_FK_USR_USR_CHANGE FOREIGN KEY (usr_usr_id_change)
      REFERENCES %PREFIX%_users (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT;
