/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*==============================================================*/
/* Table: adm_components                                        */
/*==============================================================*/

create table %PREFIX%_components
(
    com_id                        integer       unsigned not null AUTO_INCREMENT,
    com_type                      varchar(10)   not null,
    com_name                      varchar(255)  not null,
    com_name_intern               varchar(255)  not null,
    com_version                   varchar(10)   not null,
    com_beta                      smallint      not null,
    com_update_step               integer       not null default 0,
    com_timestamp_installed       timestamp     not null default CURRENT_TIMESTAMP,
    primary key (com_id)
)
engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;


insert into %PREFIX%_components (com_type, com_name, com_name_intern, com_version, com_beta)
                         values ('SYSTEM', 'Admidio Core', 'CORE', '3.0.0', 1);

update %PREFIX%_announcements SET ann_description = '<p>' || ann_description || '</p>' WHERE ann_description not like '<p>%';
update %PREFIX%_guestbook SET gbo_text = '<p>' || gbo_text || '</p>' WHERE gbo_text not like '<p>%';
update %PREFIX%_guestbook_comments SET gbc_text = '<p>' || gbc_text || '</p>' WHERE gbc_text not like '<p>%';
