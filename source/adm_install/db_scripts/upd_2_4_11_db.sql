/*==============================================================*/
/* Table: adm_components                                     */
/*==============================================================*/

create table %PREFIX%_components
(
	com_id                        integer       unsigned not null AUTO_INCREMENT,
    com_type                      varchar(10)   not null,
    com_name                      varchar(255)  not null,
    com_name_intern               varchar(255)  not null,
    com_version                   varchar(10)   not null,
    com_beta                      boolean       not null default 0,
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