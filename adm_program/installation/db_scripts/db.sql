/**
 ***********************************************************************************************
 * SQL script with database structure
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */


drop table if exists %PREFIX%_announcements cascade;
drop table if exists %PREFIX%_auto_login cascade;
drop table if exists %PREFIX%_components cascade;
drop table if exists %PREFIX%_date_role cascade;
drop table if exists %PREFIX%_dates cascade;
drop table if exists %PREFIX%_files cascade;
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
drop table if exists %PREFIX%_roles_rights cascade;
drop table if exists %PREFIX%_roles_rights_data cascade;
drop table if exists %PREFIX%_list_columns cascade;
drop table if exists %PREFIX%_lists cascade;
drop table if exists %PREFIX%_rooms cascade;
drop table if exists %PREFIX%_sessions cascade;
drop table if exists %PREFIX%_texts cascade;
drop table if exists %PREFIX%_user_relations cascade;
drop table if exists %PREFIX%_user_relation_types cascade;
drop table if exists %PREFIX%_user_log cascade;
drop table if exists %PREFIX%_user_data cascade;
drop table if exists %PREFIX%_user_fields cascade;
drop table if exists %PREFIX%_categories cascade;
drop table if exists %PREFIX%_users cascade;
drop table if exists %PREFIX%_organizations cascade;
drop table if exists %PREFIX%_ids cascade;


/*==============================================================*/
/* Table: adm_announcements                                     */
/*==============================================================*/
create table %PREFIX%_announcements
(
    ann_id                         integer       unsigned not null AUTO_INCREMENT,
    ann_cat_id                     integer       unsigned not null,
    ann_global                     boolean       not null default '0',
    ann_headline                   varchar(100)  not null,
    ann_description                text,
    ann_usr_id_create              integer       unsigned,
    ann_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    ann_usr_id_change              integer       unsigned,
    ann_timestamp_change           timestamp     null default null,
    primary key (ann_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_auto_login                                        */
/*==============================================================*/
create table %PREFIX%_auto_login
(
    atl_id                         integer       unsigned not null AUTO_INCREMENT,
    atl_auto_login_id              varchar(255)  not null,
    atl_session_id                 varchar(255)  not null,
    atl_org_id                     integer       unsigned not null,
    atl_usr_id                     integer       unsigned not null,
    atl_last_login                 timestamp     null default null,
    atl_ip_address                 varchar(39)   not null,
    atl_number_invalid             smallint      not null default 0,
    primary key (atl_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_categories                                        */
/*==============================================================*/
create table %PREFIX%_categories
(
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
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_date_role                                         */
/*==============================================================*/

create table %PREFIX%_date_role
(
    dtr_id                          integer       unsigned not null AUTO_INCREMENT,
    dtr_dat_id                      integer       unsigned not null,
    dtr_rol_id                      integer       unsigned,
    primary key (dtr_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_dates                                             */
/*==============================================================*/
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

/*==============================================================*/
/* Table: adm_files                                             */
/*==============================================================*/
create table %PREFIX%_files
(
    fil_id                         integer       unsigned not null AUTO_INCREMENT,
    fil_fol_id                     integer       unsigned not null,
    fil_name                       varchar(255)  not null,
    fil_description                text,
    fil_locked                     boolean       not null default '0',
    fil_counter                    integer,
    fil_usr_id                     integer       unsigned,
    fil_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    primary key (fil_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_folders                                           */
/*==============================================================*/
create table %PREFIX%_folders
(
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
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_guestbook                                         */
/*==============================================================*/
create table %PREFIX%_guestbook
(
    gbo_id                         integer       unsigned not null AUTO_INCREMENT,
    gbo_org_id                     integer       unsigned not null,
    gbo_name                       varchar(60)   not null,
    gbo_text                       text          not null,
    gbo_email                      varchar(254),
    gbo_homepage                   varchar(50),
    gbo_ip_address                 varchar(39)   not null,
    gbo_locked                     boolean       not null default '0',
    gbo_usr_id_create              integer       unsigned,
    gbo_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    gbo_usr_id_change              integer       unsigned,
    gbo_timestamp_change           timestamp     null default null,
    primary key (gbo_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_guestbook_comments                                */
/*==============================================================*/
create table %PREFIX%_guestbook_comments
(
    gbc_id                         integer       unsigned not null AUTO_INCREMENT,
    gbc_gbo_id                     integer       unsigned not null,
    gbc_name                       varchar(60)   not null,
    gbc_text                       text          not null,
    gbc_email                      varchar(254),
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
    inv_usr_id_lent                integer       unsigned,
    inv_lent_until                 timestamp     null default null,
    inv_number_lent                integer       not null default 0,
    inv_usr_id_create              integer       unsigned,
    inv_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    inv_usr_id_change              integer       unsigned,
    inv_timestamp_change           timestamp     null default null,
    inv_valid                      boolean       not null default '0',
    primary key (inv_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_links                                             */
/*==============================================================*/
create table %PREFIX%_links
(
    lnk_id                         integer       unsigned not null AUTO_INCREMENT,
    lnk_cat_id                     integer       unsigned not null,
    lnk_name                       varchar(255)  not null,
    lnk_description                text,
    lnk_url                        varchar(2000) not null,
    lnk_counter                    integer       not null default 0,
    lnk_usr_id_create              integer       unsigned,
    lnk_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    lnk_usr_id_change              integer       unsigned,
    lnk_timestamp_change           timestamp     null default null,
    primary key (lnk_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_lists                                             */
/*==============================================================*/
create table %PREFIX%_lists
(
    lst_id                         integer       unsigned not null AUTO_INCREMENT,
    lst_org_id                     integer       unsigned not null,
    lst_usr_id                     integer       unsigned not null,
    lst_name                       varchar(255),
    lst_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    lst_global                     boolean       not null default '0',
    primary key (lst_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_list_columns                                      */
/*==============================================================*/
create table %PREFIX%_list_columns
(
    lsc_id                         integer       unsigned not null AUTO_INCREMENT,
    lsc_lst_id                     integer       unsigned not null,
    lsc_number                     smallint      not null,
    lsc_usf_id                     integer       unsigned,
    lsc_special_field              varchar(255),
    lsc_sort                       varchar(5),
    lsc_filter                     varchar(255),
    primary key (lsc_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_members                                           */
/*==============================================================*/
create table %PREFIX%_members
(
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
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create index IDX_%PREFIX%_MEM_ROL_USR_ID on %PREFIX%_members (mem_rol_id, mem_usr_id);


/*==============================================================*/
/* Table: adm_messages                                          */
/*==============================================================*/
create table %PREFIX%_messages
(
    msg_id                        integer         unsigned not null AUTO_INCREMENT,
    msg_type                      varchar(10)     not null,
    msg_subject                   varchar(256)    not null,
    msg_usr_id_sender             integer         unsigned not null,
    msg_usr_id_receiver           varchar(256)    not null,
    msg_timestamp                 timestamp       not null default CURRENT_TIMESTAMP,
    msg_read                      smallint        not null default 0,
    primary key (msg_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_messages_content                                  */
/*==============================================================*/
create table %PREFIX%_messages_content
(
    msc_id                        integer         unsigned not null AUTO_INCREMENT,
    msc_msg_id                    integer         unsigned not null,
    msc_part_id                   integer         unsigned not null,
    msc_usr_id                    integer         unsigned,
    msc_message                   text            not null,
    msc_timestamp                 timestamp       not null default CURRENT_TIMESTAMP,
    primary key (msc_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;

create index IDX_%PREFIX%_MSC_PART_ID on %PREFIX%_messages_content (msc_part_id);

/*==============================================================*/
/* Table: adm_organizations                                     */
/*==============================================================*/
create table %PREFIX%_organizations
(
    org_id                         integer       unsigned not null AUTO_INCREMENT,
    org_longname                   varchar(60)   not null,
    org_shortname                  varchar(10)   not null,
    org_org_id_parent              integer       unsigned,
    org_homepage                   varchar(60)   not null,
    primary key (org_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index ak_%PREFIX%_shortname on %PREFIX%_organizations (org_shortname);


/*==============================================================*/
/* Table: adm_photos                                            */
/*==============================================================*/
create table %PREFIX%_photos
(
    pho_id                         integer       unsigned not null AUTO_INCREMENT,
    pho_org_id                     integer       unsigned not null,
    pho_quantity                   integer       unsigned not null default 0,
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
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_preferences                                       */
/*==============================================================*/
create table %PREFIX%_preferences
(
    prf_id                         integer       unsigned not null AUTO_INCREMENT,
    prf_org_id                     integer       unsigned not null,
    prf_name                       varchar(50)   not null,
    prf_value                      varchar(255),
    primary key (prf_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_PRF_ORG_ID_NAME on %PREFIX%_preferences (prf_org_id, prf_name);


/*==============================================================*/
/* Table: adm_registrations                                     */
/*==============================================================*/

create table %PREFIX%_registrations
(
    reg_id                        integer       unsigned not null AUTO_INCREMENT,
    reg_org_id                    integer       unsigned not null,
    reg_usr_id                    integer       unsigned not null,
    reg_timestamp                 timestamp     not null default CURRENT_TIMESTAMP,
    primary key (reg_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_role_dependencies                                 */
/*==============================================================*/
create table %PREFIX%_role_dependencies
(
    rld_rol_id_parent              integer       unsigned not null,
    rld_rol_id_child               integer       unsigned not null,
    rld_comment                    text,
    rld_usr_id                     integer       unsigned,
    rld_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    primary key (rld_rol_id_parent, rld_rol_id_child)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_roles                                             */
/*==============================================================*/
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
    rol_administrator              boolean       not null default '0',
    primary key (rol_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_roles_rights                                      */
/*==============================================================*/
create table %PREFIX%_roles_rights
(
    ror_id                         integer       unsigned not null AUTO_INCREMENT,
    ror_name_intern                varchar(50)   not null,
    ror_table                      varchar(50)   not null,
    primary key (ror_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_roles_rights_data                                 */
/*==============================================================*/
create table %PREFIX%_roles_rights_data
(
    rrd_id                         integer       unsigned not null AUTO_INCREMENT,
    rrd_ror_id                     integer       unsigned not null,
    rrd_rol_id                     integer       unsigned not null,
    rrd_object_id                  integer       unsigned not null,
    rrd_usr_id_create              integer       unsigned,
    rrd_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    primary key (rrd_id)
)
engine = InnoDB
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_RRD_ROR_ROL_OBJECT_ID on %PREFIX%_roles_rights_data (rrd_ror_id, rrd_rol_id, rrd_object_id);


/*==============================================================*/
/* Table: adm_rooms                                             */
/*==============================================================*/

create table %PREFIX%_rooms
(
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
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_sessions                                          */
/*==============================================================*/
create table %PREFIX%_sessions
(
    ses_id                         integer       unsigned not null AUTO_INCREMENT,
    ses_usr_id                     integer       unsigned default NULL,
    ses_org_id                     integer       unsigned not null,
    ses_session_id                 varchar(255)  not null,
    ses_begin                      timestamp     null default null,
    ses_timestamp                  timestamp     not null default CURRENT_TIMESTAMP,
    ses_ip_address                 varchar(39)   not null,
    ses_binary                     blob,
    ses_renew                      smallint      not null default 0,
    primary key (ses_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create index IDX_%PREFIX%_SESSION_ID on %PREFIX%_sessions (ses_session_id);


/*==============================================================*/
/* Table: adm_texts                                             */
/*==============================================================*/
create table %PREFIX%_texts
(
    txt_id                         integer       unsigned not null AUTO_INCREMENT,
    txt_org_id                     integer       unsigned not null,
    txt_name                       varchar(30)   not null,
    txt_text                       text,
    primary key (txt_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


/*==============================================================*/
/* Table: adm_user_fields                                       */
/*==============================================================*/
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


/*==============================================================*/
/* Table: adm_user_data                                         */
/*==============================================================*/
create table %PREFIX%_user_data
(
    usd_id                         integer       unsigned not null AUTO_INCREMENT,
    usd_usr_id                     integer       unsigned not null,
    usd_usf_id                     integer       unsigned not null,
    usd_value                      varchar(4000),
    primary key (usd_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index IDX_%PREFIX%_USD_USR_USF_ID on %PREFIX%_user_data (usd_usr_id, usd_usf_id);

/*==============================================================*/
/* Table: adm_user_log                                             */
/*==============================================================*/
create table %PREFIX%_user_log
(
    usl_id                         integer       not null AUTO_INCREMENT,
    usl_usr_id                     integer       unsigned not null,
    usl_usf_id                     integer       unsigned not null,
    usl_value_old                  varchar(4000) null,
    usl_value_new                  varchar(4000) null,
    usl_usr_id_create              integer       unsigned null,
    usl_timestamp_create           timestamp     not null default CURRENT_TIMESTAMP,
    usl_comment                    varchar(255)  null,
    primary key (usl_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: adm_users                                             */
/*==============================================================*/
create table %PREFIX%_users
(
    usr_id                         integer       unsigned not null AUTO_INCREMENT,
    usr_login_name                 varchar(35),
    usr_password                   varchar(255),
    usr_new_password               varchar(255),
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

/*==============================================================*/
/* Table: adm_user_relation_types                               */
/*==============================================================*/
create table %PREFIX%_user_relation_types
(
    urt_id integer unsigned not null AUTO_INCREMENT,
    urt_name varchar(100) not null,
    urt_name_male varchar(100) not null,
    urt_name_female varchar(100) not null,
    urt_id_inverse integer unsigned default null,
    urt_usr_id_create integer unsigned default null,
    urt_timestamp_create timestamp not null default CURRENT_TIMESTAMP,
    urt_usr_id_change integer unsigned default null,
    urt_timestamp_change timestamp null default null,
    primary key (urt_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index %PREFIX%_IDX_URE_URT_NAME on %PREFIX%_user_relation_types (urt_name);

/*==============================================================*/
/* Table: adm_user_relation_types                               */
/*==============================================================*/

create table %PREFIX%_user_relations
(
    ure_id integer unsigned not null AUTO_INCREMENT,
    ure_urt_id integer unsigned not null,
    ure_usr_id1 integer unsigned not null,
    ure_usr_id2 integer unsigned not null,
    ure_usr_id_create integer unsigned default null,
    ure_timestamp_create timestamp not null default CURRENT_TIMESTAMP,
    ure_usr_id_change integer unsigned default null,
    ure_timestamp_change timestamp null default null,
    primary key (ure_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

create unique index %PREFIX%_IDX_URE_URT_USR on %PREFIX%_user_relations (ure_urt_id,ure_usr_id1,ure_usr_id2);

/*==============================================================*/
/* Constraints                                                  */
/*==============================================================*/
alter table %PREFIX%_announcements add constraint %PREFIX%_FK_ANN_CAT foreign key (ann_cat_id)
      references %PREFIX%_categories (cat_id) on delete restrict on update restrict
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
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_ORG foreign key (pho_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_USR_CREATE foreign key (pho_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_photos add constraint %PREFIX%_FK_PHO_USR_CHANGE foreign key (pho_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_preferences add constraint %PREFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;

alter table %PREFIX%_registrations add CONSTRAINT %PREFIX%_FK_REG_ORG FOREIGN KEY (reg_org_id)
    REFERENCES %PREFIX%_organizations (org_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
alter table %PREFIX%_registrations add CONSTRAINT %PREFIX%_FK_REG_USR FOREIGN KEY (reg_usr_id)
    REFERENCES %PREFIX%_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT;

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

alter table %PREFIX%_roles_rights_data add constraint %PREFIX%_FK_RRD_ROR foreign key (rrd_ror_id)
      references %PREFIX%_roles_rights (ror_id) on delete restrict on update restrict;
alter table %PREFIX%_roles_rights_data add constraint %PREFIX%_FK_RRD_ROL foreign key (rrd_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_roles_rights_data add constraint %PREFIX%_FK_RRD_USR_CREATE foreign key (rrd_usr_id_create)
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
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
alter table %PREFIX%_user_log add CONSTRAINT %PREFIX%_FK_USER_LOG_2 FOREIGN KEY (usl_usr_id_create )
    REFERENCES %PREFIX%_users (usr_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;
alter table %PREFIX%_user_log add CONSTRAINT %PREFIX%_FK_USER_LOG_3 FOREIGN KEY (usl_usf_id )
    REFERENCES %PREFIX%_user_fields (usf_id ) ON DELETE RESTRICT ON UPDATE RESTRICT;

alter table %PREFIX%_users add constraint %PREFIX%_FK_USR_USR_CREATE foreign key (usr_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_users add constraint %PREFIX%_FK_USR_USR_CHANGE foreign key (usr_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_user_relation_types add constraint %PREFIX%_FK_URT_ID_INVERSE foreign key (urt_id_inverse)
      references %PREFIX%_user_relation_types (urt_id) on delete cascade on update restrict;
alter table %PREFIX%_user_relation_types add constraint %PREFIX%_FK_URT_USR_CHANGE foreign key (urt_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_user_relation_types add constraint %PREFIX%_FK_URT_USR_CREATE foreign key (urt_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;

alter table %PREFIX%_user_relations add constraint %PREFIX%_FK_URE_URT foreign key (ure_urt_id)
      references %PREFIX%_user_relation_types (urt_id) on delete cascade on update restrict;
alter table %PREFIX%_user_relations add constraint %PREFIX%_FK_URE_USR1 foreign key (ure_usr_id1)
      references %PREFIX%_users (usr_id) on delete cascade on update restrict;
alter table %PREFIX%_user_relations add constraint %PREFIX%_FK_URE_USR2 foreign key (ure_usr_id2)
      references %PREFIX%_users (usr_id) on delete cascade on update restrict;
alter table %PREFIX%_user_relations add constraint %PREFIX%_FK_URE_USR_CHANGE foreign key (ure_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_user_relations add constraint %PREFIX%_FK_URE_USR_CREATE foreign key (ure_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
