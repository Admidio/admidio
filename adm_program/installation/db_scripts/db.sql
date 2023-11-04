/**
 ***********************************************************************************************
 * SQL script with database structure
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*==============================================================*/
/* Table Cleanup                                                */
/*==============================================================*/
DROP TABLE IF EXISTS %PREFIX%_announcements        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_auto_login           CASCADE;
DROP TABLE IF EXISTS %PREFIX%_category_report      CASCADE;
DROP TABLE IF EXISTS %PREFIX%_components           CASCADE;
DROP TABLE IF EXISTS %PREFIX%_dates                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_files                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_folders              CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook_comments   CASCADE;
DROP TABLE IF EXISTS %PREFIX%_guestbook            CASCADE;
DROP TABLE IF EXISTS %PREFIX%_links                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_members              CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_attachments CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_content     CASCADE;
DROP TABLE IF EXISTS %PREFIX%_messages_recipients  CASCADE;
DROP TABLE IF EXISTS %PREFIX%_photos               CASCADE;
DROP TABLE IF EXISTS %PREFIX%_preferences          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_registrations        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_role_dependencies    CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles_rights         CASCADE;
DROP TABLE IF EXISTS %PREFIX%_roles_rights_data    CASCADE;
DROP TABLE IF EXISTS %PREFIX%_list_columns         CASCADE;
DROP TABLE IF EXISTS %PREFIX%_lists                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_rooms                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_sessions             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_texts                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_relations       CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_relation_types  CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_log             CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_data            CASCADE;
DROP TABLE IF EXISTS %PREFIX%_user_fields          CASCADE;
DROP TABLE IF EXISTS %PREFIX%_categories           CASCADE;
DROP TABLE IF EXISTS %PREFIX%_users                CASCADE;
DROP TABLE IF EXISTS %PREFIX%_organizations        CASCADE;
DROP TABLE IF EXISTS %PREFIX%_ids                  CASCADE;
DROP TABLE IF EXISTS %PREFIX%_menu                 CASCADE;


/*==============================================================*/
/* Table: adm_announcements                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_announcements
(
    ann_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    ann_cat_id                  integer unsigned    NOT NULL,
    ann_uuid                    varchar(36)         NOT NULL,
    ann_headline                varchar(100)        NOT NULL,
    ann_description             text,
    ann_usr_id_create           integer unsigned,
    ann_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    ann_usr_id_change           integer unsigned,
    ann_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (ann_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_ann_uuid ON %PREFIX%_announcements (ann_uuid);

/*==============================================================*/
/* Table: adm_auto_login                                        */
/*==============================================================*/
CREATE TABLE %PREFIX%_auto_login
(
    atl_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    atl_auto_login_id           varchar(255)        NOT NULL,
    atl_session_id              varchar(255)        NOT NULL,
    atl_org_id                  integer unsigned    NOT NULL,
    atl_usr_id                  integer unsigned    NOT NULL,
    atl_last_login              timestamp           NULL        DEFAULT NULL,
    atl_number_invalid          smallint            NOT NULL    DEFAULT 0,
    PRIMARY KEY (atl_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_categories                                        */
/*==============================================================*/
CREATE TABLE %PREFIX%_categories
(
    cat_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    cat_org_id                  integer unsigned,
    cat_uuid                    varchar(36)         NOT NULL,
    cat_type                    varchar(10)         NOT NULL,
    cat_name_intern             varchar(110)        NOT NULL,
    cat_name                    varchar(100)        NOT NULL,
    cat_system                  boolean             NOT NULL    DEFAULT false,
    cat_default                 boolean             NOT NULL    DEFAULT false,
    cat_sequence                smallint            NOT NULL,
    cat_usr_id_create           integer unsigned,
    cat_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    cat_usr_id_change           integer unsigned,
    cat_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (cat_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_cat_uuid ON %PREFIX%_categories (cat_uuid);

/*==============================================================*/
/* Table: adm_category_report                                   */
/*==============================================================*/
CREATE TABLE %PREFIX%_category_report
(
    crt_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    crt_org_id                  integer unsigned,
    crt_name                    varchar(100)        NOT NULL,
    crt_col_fields              text,
    crt_selection_role          varchar(100),
    crt_selection_cat           varchar(100),
    crt_number_col              boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (crt_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_components                                        */
/*==============================================================*/
CREATE TABLE %PREFIX%_components
(
    com_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    com_type                    varchar(10)         NOT NULL,
    com_name                    varchar(255)        NOT NULL,
    com_name_intern             varchar(255)        NOT NULL,
    com_version                 varchar(10)         NOT NULL,
    com_beta                    smallint            NOT NULL    DEFAULT 0,
    com_update_step             integer             NOT NULL    DEFAULT 0,
    com_update_completed        boolean             NOT NULL    DEFAULT true,
    com_timestamp_installed     timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (com_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_dates                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_dates
(
    dat_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    dat_cat_id                  integer unsigned    NOT NULL,
    dat_rol_id                  integer unsigned,
    dat_room_id                 integer unsigned,
    dat_uuid                    varchar(36)         NOT NULL,
    dat_begin                   timestamp           NULL        DEFAULT NULL,
    dat_end                     timestamp           NULL        DEFAULT NULL,
    dat_all_day                 boolean             NOT NULL    DEFAULT false,
    dat_headline                varchar(100)        NOT NULL,
    dat_description             text,
    dat_highlight               boolean             NOT NULL    DEFAULT false,
    dat_location                varchar(100),
    dat_country                 varchar(100),
    dat_deadline                timestamp           NULL        DEFAULT NULL,
    dat_max_members             integer             NOT NULL    DEFAULT 0,
    dat_usr_id_create           integer unsigned,
    dat_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    dat_usr_id_change           integer unsigned,
    dat_timestamp_change        timestamp           NULL        DEFAULT NULL,
    dat_allow_comments          boolean             NOT NULL    DEFAULT false,
    dat_additional_guests       boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (dat_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_dat_uuid ON %PREFIX%_dates (dat_uuid);

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_files
(
    fil_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    fil_fol_id                  integer unsigned    NOT NULL,
    fil_uuid                    varchar(36)         NOT NULL,
    fil_name                    varchar(255)        NOT NULL,
    fil_description             text,
    fil_locked                  boolean             NOT NULL    DEFAULT false,
    fil_counter                 integer,
    fil_usr_id                  integer unsigned,
    fil_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fil_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_fil_uuid ON %PREFIX%_files (fil_uuid);

/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
CREATE TABLE %PREFIX%_folders
(
    fol_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    fol_org_id                  integer unsigned    NOT NULL,
    fol_fol_id_parent           integer unsigned,
    fol_uuid                    varchar(36)         NOT NULL,
    fol_type                    varchar(10)         NOT NULL,
    fol_name                    varchar(255)        NOT NULL,
    fol_description             text,
    fol_path                    varchar(255)        NOT NULL,
    fol_locked                  boolean             NOT NULL    DEFAULT false,
    fol_public                  boolean             NOT NULL    DEFAULT false,
    fol_usr_id                  integer unsigned,
    fol_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fol_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_fol_uuid ON %PREFIX%_folders (fol_uuid);

/*==============================================================*/
/* Table: adm_guestbook                                         */
/*==============================================================*/
CREATE TABLE %PREFIX%_guestbook
(
    gbo_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    gbo_org_id                  integer unsigned    NOT NULL,
    gbo_uuid                    varchar(36)         NOT NULL,
    gbo_name                    varchar(60)         NOT NULL,
    gbo_text                    text                NOT NULL,
    gbo_email                   varchar(254),
    gbo_homepage                varchar(50),
    gbo_ip_address              varchar(39)         NOT NULL,
    gbo_locked                  boolean             NOT NULL    DEFAULT false,
    gbo_usr_id_create           integer unsigned,
    gbo_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    gbo_usr_id_change           integer unsigned,
    gbo_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (gbo_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_gbo_uuid ON %PREFIX%_guestbook (gbo_uuid);

/*==============================================================*/
/* Table: adm_guestbook_comments                                */
/*==============================================================*/
CREATE TABLE %PREFIX%_guestbook_comments
(
    gbc_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    gbc_gbo_id                  integer unsigned    NOT NULL,
    gbc_uuid                    varchar(36)         NOT NULL,
    gbc_name                    varchar(60)         NOT NULL,
    gbc_text                    text                NOT NULL,
    gbc_email                   varchar(254),
    gbc_ip_address              varchar(39)         NOT NULL,
    gbc_locked                  boolean             NOT NULL    DEFAULT false,
    gbc_usr_id_create           integer unsigned,
    gbc_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    gbc_usr_id_change           integer unsigned,
    gbc_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (gbc_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_gbc_uuid ON %PREFIX%_guestbook_comments (gbc_uuid);

/*==============================================================*/
/* Table: adm_ids                                               */
/*==============================================================*/
CREATE TABLE %PREFIX%_ids
(
    ids_usr_id                  integer unsigned    NOT NULL,
    ids_reference_id            integer unsigned    NOT NULL
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_links                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_links
(
    lnk_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    lnk_cat_id                  integer unsigned    NOT NULL,
    lnk_uuid                    varchar(36)         NOT NULL,
    lnk_name                    varchar(255)        NOT NULL,
    lnk_description             text,
    lnk_url                     varchar(2000)       NOT NULL,
    lnk_counter                 integer             NOT NULL    DEFAULT 0,
    lnk_usr_id_create           integer unsigned,
    lnk_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    lnk_usr_id_change           integer unsigned,
    lnk_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (lnk_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_lnk_uuid ON %PREFIX%_links (lnk_uuid);

/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_lists
(
    lst_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    lst_org_id                  integer unsigned    NOT NULL,
    lst_usr_id                  integer unsigned    NOT NULL,
    lst_uuid                    varchar(36)         NOT NULL,
    lst_name                    varchar(255),
    lst_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    lst_global                  boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (lst_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_lst_uuid ON %PREFIX%_lists (lst_uuid);

/*==============================================================*/
/* Table: adm_list_columns                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_list_columns
(
    lsc_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    lsc_lst_id                  integer unsigned    NOT NULL,
    lsc_number                  smallint            NOT NULL,
    lsc_usf_id                  integer unsigned,
    lsc_special_field           varchar(255),
    lsc_sort                    varchar(5),
    lsc_filter                  varchar(255),
    PRIMARY KEY (lsc_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_members                                           */
/*==============================================================*/
CREATE TABLE %PREFIX%_members
(
    mem_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    mem_rol_id                  integer unsigned    NOT NULL,
    mem_usr_id                  integer unsigned    NOT NULL,
    mem_uuid                    varchar(36)         NOT NULL,
    mem_begin                   date                NOT NULL,
    mem_end                     date                NOT NULL    DEFAULT '9999-12-31',
    mem_leader                  boolean             NOT NULL    DEFAULT false,
    mem_usr_id_create           integer unsigned,
    mem_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    mem_usr_id_change           integer unsigned,
    mem_timestamp_change        timestamp           NULL        DEFAULT NULL,
    mem_approved                integer unsigned    NULL        DEFAULT NULL,
    mem_comment                 varchar(4000),
    mem_count_guests            integer unsigned    NOT NULL    DEFAULT 0,
    PRIMARY KEY (mem_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX %PREFIX%_idx_mem_rol_usr_id ON %PREFIX%_members (mem_rol_id, mem_usr_id);
CREATE UNIQUE INDEX %PREFIX%_idx_mem_uuid ON %PREFIX%_members (mem_uuid);

/*==============================================================*/
/* Table: adm_menu                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_menu
(
    men_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    men_men_id_parent           integer unsigned,
    men_com_id                  integer unsigned,
    men_uuid                    varchar(36)         NOT NULL,
    men_name_intern             varchar(255),
    men_name                    varchar(255),
    men_description             varchar(4000),
    men_node                    boolean             NOT NULL    DEFAULT false,
    men_order                   integer unsigned,
    men_standard                boolean             NOT NULL    DEFAULT false,
    men_url                     varchar(2000),
    men_icon                    varchar(100),
    PRIMARY KEY (men_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX %PREFIX%_idx_men_men_id_parent ON %PREFIX%_menu (men_men_id_parent);
CREATE UNIQUE INDEX %PREFIX%_idx_men_uuid ON %PREFIX%_menu (men_uuid);

/*==============================================================*/
/* Table: adm_messages                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages
(
    msg_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    msg_uuid                    varchar(36)         NOT NULL,
    msg_type                    varchar(10)         NOT NULL,
    msg_subject                 varchar(256)        NOT NULL,
    msg_usr_id_sender           integer unsigned    NOT NULL,
    msg_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    msg_read                    smallint            NOT NULL    DEFAULT 0,
    PRIMARY KEY (msg_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_msg_uuid ON %PREFIX%_messages (msg_uuid);

/*==============================================================*/
/* Table: adm_messages_attachments                              */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages_attachments
(
    msa_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    msa_msg_id                  integer unsigned    NOT NULL,
    msa_file_name               varchar(256)        NOT NULL,
    msa_original_file_name      varchar(256)        NOT NULL,
    PRIMARY KEY (msa_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_messages_content                                  */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages_content
(
    msc_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    msc_msg_id                  integer unsigned    NOT NULL,
    msc_usr_id                  integer unsigned,
    msc_message                 text                NOT NULL,
    msc_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (msc_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_messages_recipients                                */
/*==============================================================*/
CREATE TABLE %PREFIX%_messages_recipients
(
    msr_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    msr_msg_id                  integer unsigned    NOT NULL,
    msr_rol_id                  integer unsigned,
    msr_usr_id                  integer unsigned,
    msr_role_mode               smallint            NOT NULL    DEFAULT 0,
    PRIMARY KEY (msr_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_organizations                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_organizations
(
    org_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    org_uuid                    varchar(36)         NOT NULL,
    org_shortname               varchar(10)         NOT NULL,
    org_longname                varchar(60)         NOT NULL,
    org_org_id_parent           integer unsigned,
    org_homepage                varchar(60)         NOT NULL,
    PRIMARY KEY (org_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_org_shortname ON %PREFIX%_organizations (org_shortname);
CREATE UNIQUE INDEX %PREFIX%_idx_org_uuid ON %PREFIX%_organizations (org_uuid);

/*==============================================================*/
/* Table: adm_photos                                            */
/*==============================================================*/
CREATE TABLE %PREFIX%_photos
(
    pho_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    pho_org_id                  integer unsigned    NOT NULL,
    pho_pho_id_parent           integer unsigned,
    pho_uuid                    varchar(36)         NOT NULL,
    pho_quantity                integer unsigned    NOT NULL    DEFAULT 0,
    pho_name                    varchar(50)         NOT NULL,
    pho_begin                   date                NOT NULL,
    pho_end                     date                NOT NULL,
    pho_description             varchar(4000),
    pho_photographers           varchar(100),
    pho_locked                  boolean             NOT NULL    DEFAULT false,
    pho_usr_id_create           integer unsigned,
    pho_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    pho_usr_id_change           integer unsigned,
    pho_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (pho_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_pho_uuid ON %PREFIX%_photos (pho_uuid);

/*==============================================================*/
/* Table: adm_preferences                                       */
/*==============================================================*/
CREATE TABLE %PREFIX%_preferences
(
    prf_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    prf_org_id                  integer unsigned    NOT NULL,
    prf_name                    varchar(50)         NOT NULL,
    prf_value                   varchar(255),
    PRIMARY KEY (prf_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_prf_org_id_name ON %PREFIX%_preferences (prf_org_id, prf_name);

/*==============================================================*/
/* Table: adm_registrations                                     */
/*==============================================================*/
CREATE TABLE %PREFIX%_registrations
(
    reg_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    reg_org_id                  integer unsigned    NOT NULL,
    reg_usr_id                  integer unsigned    NOT NULL,
    reg_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reg_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_role_dependencies                                 */
/*==============================================================*/
CREATE TABLE %PREFIX%_role_dependencies
(
    rld_rol_id_parent           integer unsigned    NOT NULL,
    rld_rol_id_child            integer unsigned    NOT NULL,
    rld_comment                 text,
    rld_usr_id                  integer unsigned,
    rld_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rld_rol_id_parent, rld_rol_id_child)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_roles                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_roles
(
    rol_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    rol_cat_id                  integer unsigned    NOT NULL,
    rol_lst_id                  integer unsigned,
    rol_uuid                    varchar(36)         NOT NULL,
    rol_name                    varchar(100)        NOT NULL,
    rol_description             varchar(4000),
    rol_assign_roles            boolean             NOT NULL    DEFAULT false,
    rol_approve_users           boolean             NOT NULL    DEFAULT false,
    rol_announcements           boolean             NOT NULL    DEFAULT false,
    rol_dates                   boolean             NOT NULL    DEFAULT false,
    rol_documents_files         boolean             NOT NULL    DEFAULT false,
    rol_edit_user               boolean             NOT NULL    DEFAULT false,
    rol_guestbook               boolean             NOT NULL    DEFAULT false,
    rol_guestbook_comments      boolean             NOT NULL    DEFAULT false,
    rol_mail_to_all             boolean             NOT NULL    DEFAULT false,
    rol_mail_this_role          smallint            NOT NULL    DEFAULT 0,
    rol_photo                   boolean             NOT NULL    DEFAULT false,
    rol_profile                 boolean             NOT NULL    DEFAULT false,
    rol_weblinks                boolean             NOT NULL    DEFAULT false,
    rol_all_lists_view          boolean             NOT NULL    DEFAULT false,
    rol_default_registration    boolean             NOT NULL    DEFAULT false,
    rol_leader_rights           smallint            NOT NULL    DEFAULT 0,
    rol_view_memberships        smallint            NOT NULL    DEFAULT 0,
    rol_view_members_profiles   smallint            NOT NULL    DEFAULT 0,
    rol_start_date              date,
    rol_start_time              time,
    rol_end_date                date,
    rol_end_time                time,
    rol_weekday                 smallint,
    rol_location                varchar(100),
    rol_max_members             integer,
    rol_cost                    float,
    rol_cost_period             smallint,
    rol_usr_id_create           integer unsigned,
    rol_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    rol_usr_id_change           integer unsigned,
    rol_timestamp_change        timestamp           NULL        DEFAULT NULL,
    rol_valid                   boolean             NOT NULL    DEFAULT true,
    rol_system                  boolean             NOT NULL    DEFAULT false,
    rol_administrator           boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (rol_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_rol_uuid ON %PREFIX%_roles (rol_uuid);

/*==============================================================*/
/* Table: adm_roles_rights                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_roles_rights
(
    ror_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    ror_name_intern             varchar(50)         NOT NULL,
    ror_table                   varchar(50)         NOT NULL,
    ror_ror_id_parent           integer unsigned,
    PRIMARY KEY (ror_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_roles_rights_data                                 */
/*==============================================================*/
CREATE TABLE %PREFIX%_roles_rights_data
(
    rrd_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    rrd_ror_id                  integer unsigned    NOT NULL,
    rrd_rol_id                  integer unsigned    NOT NULL,
    rrd_object_id               integer unsigned    NOT NULL,
    rrd_usr_id_create           integer unsigned,
    rrd_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rrd_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_rrd_ror_rol_object_id ON %PREFIX%_roles_rights_data (rrd_ror_id, rrd_rol_id, rrd_object_id);

/*==============================================================*/
/* Table: adm_rooms                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_rooms
(
    room_id                     integer unsigned    NOT NULL    AUTO_INCREMENT,
    room_uuid                   varchar(36)         NOT NULL,
    room_name                   varchar(50)         NOT NULL,
    room_description            text,
    room_capacity               integer             NOT NULL,
    room_overhang               integer,
    room_usr_id_create          integer unsigned,
    room_timestamp_create       timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    room_usr_id_change          integer unsigned,
    room_timestamp_change       timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (room_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_room_uuid ON %PREFIX%_rooms (room_uuid);

/*==============================================================*/
/* Table: adm_sessions                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_sessions
(
    ses_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    ses_usr_id                  integer unsigned    NULL        DEFAULT NULL,
    ses_org_id                  integer unsigned    NOT NULL,
    ses_session_id              varchar(255)        NOT NULL,
    ses_begin                   timestamp           NULL        DEFAULT NULL,
    ses_timestamp               timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    ses_ip_address              varchar(39)         NOT NULL,
    ses_binary                  blob,
    ses_reload                  boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (ses_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE INDEX %PREFIX%_idx_session_id ON %PREFIX%_sessions (ses_session_id);

/*==============================================================*/
/* Table: adm_texts                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_texts
(
    txt_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    txt_org_id                  integer unsigned    NOT NULL,
    txt_name                    varchar(30)         NOT NULL,
    txt_text                    text,
    PRIMARY KEY (txt_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_user_fields                                       */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_fields
(
    usf_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    usf_cat_id                  integer unsigned    NOT NULL,
    usf_uuid                    varchar(36)         NOT NULL,
    usf_type                    varchar(30)         NOT NULL,
    usf_name_intern             varchar(110)        NOT NULL,
    usf_name                    varchar(100)        NOT NULL,
    usf_description             text,
    usf_description_inline      boolean             NOT NULL    DEFAULT false,
    usf_value_list              text,
    usf_default_value           varchar(100),
    usf_regex                   varchar(100),
    usf_icon                    varchar(100),
    usf_url                     varchar(2000),
    usf_system                  boolean             NOT NULL    DEFAULT false,
    usf_disabled                boolean             NOT NULL    DEFAULT false,
    usf_hidden                  boolean             NOT NULL    DEFAULT false,
    usf_registration            boolean             NOT NULL    DEFAULT false,
    usf_required_input          smallint            NOT NULL    DEFAULT 0,
    usf_sequence                smallint            NOT NULL,
    usf_usr_id_create           integer unsigned,
    usf_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    usf_usr_id_change           integer unsigned,
    usf_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (usf_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_usf_name_intern ON %PREFIX%_user_fields (usf_name_intern);
CREATE UNIQUE INDEX %PREFIX%_idx_usf_uuid ON %PREFIX%_user_fields (usf_uuid);

/*==============================================================*/
/* Table: adm_user_data                                         */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_data
(
    usd_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    usd_usr_id                  integer unsigned    NOT NULL,
    usd_usf_id                  integer unsigned    NOT NULL,
    usd_value                   varchar(4000),
    PRIMARY KEY (usd_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_usd_usr_usf_id ON %PREFIX%_user_data (usd_usr_id, usd_usf_id);

/*==============================================================*/
/* Table: adm_user_log                                          */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_log
(
    usl_id                      integer             NOT NULL    AUTO_INCREMENT,
    usl_usr_id                  integer unsigned    NOT NULL,
    usl_usf_id                  integer unsigned    NOT NULL,
    usl_value_old               varchar(4000)       NULL,
    usl_value_new               varchar(4000)       NULL,
    usl_usr_id_create           integer unsigned    NULL,
    usl_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    usl_comment                 varchar(255)        NULL,
    PRIMARY KEY (usl_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_users                                             */
/*==============================================================*/
CREATE TABLE %PREFIX%_users
(
    usr_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    usr_uuid                    varchar(36)         NOT NULL,
    usr_login_name              varchar(254),
    usr_password                varchar(255),
    usr_photo                   blob,
    usr_text                    text,
    usr_pw_reset_id             varchar(50),
    usr_pw_reset_timestamp      timestamp           NULL        DEFAULT NULL,
    usr_last_login              timestamp           NULL        DEFAULT NULL,
    usr_actual_login            timestamp           NULL        DEFAULT NULL,
    usr_number_login            integer             NOT NULL    DEFAULT 0,
    usr_date_invalid            timestamp           NULL        DEFAULT NULL,
    usr_number_invalid          smallint            NOT NULL    DEFAULT 0,
    usr_usr_id_create           integer unsigned,
    usr_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    usr_usr_id_change           integer unsigned,
    usr_timestamp_change        timestamp           NULL        DEFAULT NULL,
    usr_valid                   boolean             NOT NULL    DEFAULT false,
    PRIMARY KEY (usr_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_usr_login_name ON %PREFIX%_users (usr_login_name);
CREATE UNIQUE INDEX %PREFIX%_idx_usr_uuid ON %PREFIX%_users (usr_uuid);

/*==============================================================*/
/* Table: adm_user_relation_types                               */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_relation_types
(
    urt_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    urt_uuid                    varchar(36)         NOT NULL,
    urt_name                    varchar(100)        NOT NULL,
    urt_name_male               varchar(100)        NOT NULL,
    urt_name_female             varchar(100)        NOT NULL,
    urt_edit_user               boolean             NOT NULL    DEFAULT false,
    urt_id_inverse              integer unsigned    NULL        DEFAULT NULL,
    urt_usr_id_create           integer unsigned    NULL        DEFAULT NULL,
    urt_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    urt_usr_id_change           integer unsigned    NULL        DEFAULT NULL,
    urt_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (urt_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_ure_urt_name ON %PREFIX%_user_relation_types (urt_name);
CREATE UNIQUE INDEX %PREFIX%_idx_urt_uuid ON %PREFIX%_user_relation_types (urt_uuid);

/*==============================================================*/
/* Table: adm_user_relations                                    */
/*==============================================================*/
CREATE TABLE %PREFIX%_user_relations
(
    ure_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    ure_urt_id                  integer unsigned    NOT NULL,
    ure_usr_id1                 integer unsigned    NOT NULL,
    ure_usr_id2                 integer unsigned    NOT NULL,
    ure_usr_id_create           integer unsigned    NULL        DEFAULT NULL,
    ure_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    ure_usr_id_change           integer unsigned    NULL        DEFAULT NULL,
    ure_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (ure_id)
)
ENGINE = InnoDB
DEFAULT character SET = utf8
COLLATE = utf8_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_ure_urt_usr ON %PREFIX%_user_relations (ure_urt_id, ure_usr_id1, ure_usr_id2);


/*==============================================================*/
/* Foreign Key Constraints                                      */
/*==============================================================*/
ALTER TABLE %PREFIX%_announcements
    ADD CONSTRAINT %PREFIX%_fk_ann_cat         FOREIGN KEY (ann_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ann_usr_create  FOREIGN KEY (ann_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ann_usr_change  FOREIGN KEY (ann_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_auto_login
    ADD CONSTRAINT %PREFIX%_fk_atl_usr         FOREIGN KEY (atl_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_atl_org         FOREIGN KEY (atl_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_categories
    ADD CONSTRAINT %PREFIX%_fk_cat_org         FOREIGN KEY (cat_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_cat_usr_create  FOREIGN KEY (cat_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_cat_usr_change  FOREIGN KEY (cat_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_category_report
    ADD CONSTRAINT %PREFIX%_fk_crt_org         FOREIGN KEY (crt_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_dates
    ADD CONSTRAINT %PREFIX%_fk_dat_cat         FOREIGN KEY (dat_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_dat_rol         FOREIGN KEY (dat_rol_id)         REFERENCES %PREFIX%_roles (rol_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_dat_room        FOREIGN KEY (dat_room_id)        REFERENCES %PREFIX%_rooms (room_id)              ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_dat_usr_create  FOREIGN KEY (dat_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_dat_usr_change  FOREIGN KEY (dat_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_files
    ADD CONSTRAINT %PREFIX%_fk_fil_fol         FOREIGN KEY (fil_fol_id)         REFERENCES %PREFIX%_folders (fol_id)             ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_fil_usr         FOREIGN KEY (fil_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_folders
    ADD CONSTRAINT %PREFIX%_fk_fol_org         FOREIGN KEY (fol_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_fol_fol_parent  FOREIGN KEY (fol_fol_id_parent)  REFERENCES %PREFIX%_folders (fol_id)             ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_fol_usr         FOREIGN KEY (fol_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_guestbook
    ADD CONSTRAINT %PREFIX%_fk_gbo_org         FOREIGN KEY (gbo_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_gbo_usr_create  FOREIGN KEY (gbo_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_gbo_usr_change  FOREIGN KEY (gbo_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_guestbook_comments
    ADD CONSTRAINT %PREFIX%_fk_gbc_gbo         FOREIGN KEY (gbc_gbo_id)         REFERENCES %PREFIX%_guestbook (gbo_id)           ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_gbc_usr_create  FOREIGN KEY (gbc_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_gbc_usr_change  FOREIGN KEY (gbc_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_ids
    ADD CONSTRAINT %PREFIX%_fk_ids_usr_id      FOREIGN KEY (ids_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_links
    ADD CONSTRAINT %PREFIX%_fk_lnk_cat         FOREIGN KEY (lnk_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_lnk_usr_create  FOREIGN KEY (lnk_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_lnk_usr_change  FOREIGN KEY (lnk_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_lists
    ADD CONSTRAINT %PREFIX%_fk_lst_usr         FOREIGN KEY (lst_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_lst_org         FOREIGN KEY (lst_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_list_columns
    ADD CONSTRAINT %PREFIX%_fk_lsc_lst         FOREIGN KEY (lsc_lst_id)         REFERENCES %PREFIX%_lists (lst_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_lsc_usf         FOREIGN KEY (lsc_usf_id)         REFERENCES %PREFIX%_user_fields (usf_id)         ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_members
    ADD CONSTRAINT %PREFIX%_fk_mem_rol         FOREIGN KEY (mem_rol_id)         REFERENCES %PREFIX%_roles (rol_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mem_usr         FOREIGN KEY (mem_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mem_usr_create  FOREIGN KEY (mem_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mem_usr_change  FOREIGN KEY (mem_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_menu
    ADD CONSTRAINT %PREFIX%_fk_men_men_parent  FOREIGN KEY (men_men_id_parent)  REFERENCES %PREFIX%_menu (men_id)                ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_men_com_id      FOREIGN KEY (men_com_id)         REFERENCES %PREFIX%_components (com_id)          ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages
    ADD CONSTRAINT %PREFIX%_fk_msg_usr_sender  FOREIGN KEY (msg_usr_id_sender)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages_attachments
    ADD CONSTRAINT %PREFIX%_fk_msa_msg_id      FOREIGN KEY (msa_msg_id)         REFERENCES %PREFIX%_messages (msg_id)            ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages_content
    ADD CONSTRAINT %PREFIX%_fk_msc_msg_id      FOREIGN KEY (msc_msg_id)         REFERENCES %PREFIX%_messages (msg_id)            ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_msc_usr_id      FOREIGN KEY (msc_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_messages_recipients
    ADD CONSTRAINT %PREFIX%_fk_msr_msg_id      FOREIGN KEY (msr_msg_id)         REFERENCES %PREFIX%_messages (msg_id)            ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_msr_rol_id      FOREIGN KEY (msr_rol_id)         REFERENCES %PREFIX%_roles (rol_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_msr_usr_id      FOREIGN KEY (msr_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_organizations
    ADD CONSTRAINT %PREFIX%_fk_org_org_parent  FOREIGN KEY (org_org_id_parent)  REFERENCES %PREFIX%_organizations (org_id)       ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_photos
    ADD CONSTRAINT %PREFIX%_fk_pho_pho_parent  FOREIGN KEY (pho_pho_id_parent)  REFERENCES %PREFIX%_photos (pho_id)              ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_pho_org         FOREIGN KEY (pho_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_pho_usr_create  FOREIGN KEY (pho_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_pho_usr_change  FOREIGN KEY (pho_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_preferences
    ADD CONSTRAINT %PREFIX%_fk_prf_org         FOREIGN KEY (prf_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_registrations
    ADD CONSTRAINT %PREFIX%_fk_reg_org         FOREIGN KEY (reg_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_reg_usr         FOREIGN KEY (reg_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_role_dependencies
    ADD CONSTRAINT %PREFIX%_fk_rld_rol_child   FOREIGN KEY (rld_rol_id_child)   REFERENCES %PREFIX%_roles (rol_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rld_rol_parent  FOREIGN KEY (rld_rol_id_parent)  REFERENCES %PREFIX%_roles (rol_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rld_usr         FOREIGN KEY (rld_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_roles
    ADD CONSTRAINT %PREFIX%_fk_rol_cat         FOREIGN KEY (rol_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rol_lst_id      FOREIGN KEY (rol_lst_id)         REFERENCES %PREFIX%_lists (lst_id)               ON DELETE SET NULL ON UPDATE SET NULL,
    ADD CONSTRAINT %PREFIX%_fk_rol_usr_create  FOREIGN KEY (rol_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rol_usr_change  FOREIGN KEY (rol_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_roles_rights
    ADD CONSTRAINT %PREFIX%_fk_ror_ror_parent  FOREIGN KEY (ror_ror_id_parent)  REFERENCES %PREFIX%_roles_rights (ror_id)        ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_roles_rights_data
    ADD CONSTRAINT %PREFIX%_fk_rrd_ror         FOREIGN KEY (rrd_ror_id)         REFERENCES %PREFIX%_roles_rights (ror_id)        ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rrd_rol         FOREIGN KEY (rrd_rol_id)         REFERENCES %PREFIX%_roles (rol_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_rrd_usr_create  FOREIGN KEY (rrd_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_rooms
    ADD CONSTRAINT %PREFIX%_fk_room_usr_create FOREIGN KEY (room_usr_id_create) REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_room_usr_change FOREIGN KEY (room_usr_id_change) REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_sessions
    ADD CONSTRAINT %PREFIX%_fk_ses_org         FOREIGN KEY (ses_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ses_usr         FOREIGN KEY (ses_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_texts
    ADD CONSTRAINT %PREFIX%_fk_txt_org         FOREIGN KEY (txt_org_id)         REFERENCES %PREFIX%_organizations (org_id)       ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_fields
    ADD CONSTRAINT %PREFIX%_fk_usf_cat         FOREIGN KEY (usf_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_usf_usr_create  FOREIGN KEY (usf_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_usf_usr_change  FOREIGN KEY (usf_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_data
    ADD CONSTRAINT %PREFIX%_fk_usd_usf         FOREIGN KEY (usd_usf_id)         REFERENCES %PREFIX%_user_fields (usf_id)         ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_usd_usr         FOREIGN KEY (usd_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_log
    ADD CONSTRAINT %PREFIX%_fk_user_log_1      FOREIGN KEY (usl_usr_id)         REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_user_log_2      FOREIGN KEY (usl_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_user_log_3      FOREIGN KEY (usl_usf_id)         REFERENCES %PREFIX%_user_fields (usf_id)         ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_users
    ADD CONSTRAINT %PREFIX%_fk_usr_usr_create  FOREIGN KEY (usr_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_usr_usr_change  FOREIGN KEY (usr_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_relation_types
    ADD CONSTRAINT %PREFIX%_fk_urt_id_inverse  FOREIGN KEY (urt_id_inverse)     REFERENCES %PREFIX%_user_relation_types (urt_id) ON DELETE CASCADE  ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_urt_usr_change  FOREIGN KEY (urt_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_urt_usr_create  FOREIGN KEY (urt_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE %PREFIX%_user_relations
    ADD CONSTRAINT %PREFIX%_fk_ure_urt         FOREIGN KEY (ure_urt_id)         REFERENCES %PREFIX%_user_relation_types (urt_id) ON DELETE CASCADE  ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ure_usr1        FOREIGN KEY (ure_usr_id1)        REFERENCES %PREFIX%_users (usr_id)               ON DELETE CASCADE  ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ure_usr2        FOREIGN KEY (ure_usr_id2)        REFERENCES %PREFIX%_users (usr_id)               ON DELETE CASCADE  ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ure_usr_change  FOREIGN KEY (ure_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ure_usr_create  FOREIGN KEY (ure_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;
