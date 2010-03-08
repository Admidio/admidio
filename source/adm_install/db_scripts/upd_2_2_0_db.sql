
-- Tabelle User_Fields erweitern
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_name_intern` VARCHAR(110) AFTER usf_type;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_usr_id_create` INT(11) unsigned;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_timestamp_create` datetime;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_usr_id_change` INT(11) unsigned;
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_timestamp_change` datetime;

ALTER TABLE %PREFIX%_user_fields CHANGE COLUMN `usf_description` `usf_description_old` varchar(255);
ALTER TABLE %PREFIX%_user_fields ADD COLUMN `usf_description` text AFTER usf_name;
UPDATE %PREFIX%_user_fields SET usf_description = usf_description_old;
ALTER TABLE %PREFIX%_user_fields DROP COLUMN usf_description_old;

-- Gaestebuchtabellenspalten auf Standardbezeichnung umstellen
ALTER TABLE %PREFIX%_guestbook DROP FOREIGN KEY %PREFIX%_FK_GBO_USR;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_usr_id` `gbo_usr_id_create` int(11) unsigned;
alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_USR_CREATE foreign key (gbo_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_timestamp` `gbo_timestamp_create` datetime NOT NULL;

ALTER TABLE %PREFIX%_guestbook_comments DROP FOREIGN KEY %PREFIX%_FK_GBC_USR;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_usr_id` `gbc_usr_id_create` int(11) unsigned;
alter table %PREFIX%_guestbook_comments add constraint %PREFIX%_FK_GBC_USR_CREATE foreign key (gbc_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_guestbook_comments CHANGE COLUMN `gbc_timestamp` `gbc_timestamp_create` datetime NOT NULL;

-- Feldgroessen anpasssen
ALTER TABLE %PREFIX%_organizations MODIFY COLUMN `org_homepage` VARCHAR(60) NOT NULL;

-- Raumverwaltungstabelle hinzufuegen

DROP TABLE IF EXISTS %PREFIX%_rooms;
CREATE TABLE %PREFIX%_rooms
(
    room_id                         int(11) unsigned                not null auto_increment,
    room_name                       varchar(50)                     not null,
    room_description                varchar(255),
    room_capacity                   int(11) unsigned                not null,
    room_overhang                   int(11) unsigned,
    room_usr_id_create              int(11) unsigned,
    room_timestamp_create           datetime                        not null,
    room_usr_id_change              int(11) unsigned,
    room_timestamp_change           datetime,
    primary key (room_id)                                                                       
)
engine = InnoDB
auto_increment = 1;

-- Attribut hinzufuegen

ALTER TABLE %PREFIX%_roles ADD COLUMN `rol_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE %PREFIX%_dates ADD COLUMN `dat_rol_id` INT(11) UNSIGNED;
ALTER TABLE %PREFIX%_dates ADD COLUMN `dat_room_id` INT(11) UNSIGNED;
ALTER TABLE %PREFIX%_dates ADD COLUMN `dat_max_members` INT(11) UNSIGNED NOT NULL;
ALTER TABLE %PREFIX%_members ADD COLUMN `mem_from_rol_id` INT(11) UNSIGNED NULL;
ALTER TABLE %PREFIX%_members ADD INDEX (`mem_from_rol_id`) ;

-- Sichtbarkeitstabelle für Termine hinzufuegen

create table %PREFIX%_date_role
(
    dtr_id                          int(11) unsigned                not null auto_increment,
    dtr_dat_id                      int(11) unsigned                not null,
    dtr_rol_id                      int(11) unsigned,
    primary key (dtr_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PREFIX%_date_role add index DTR_DAT_FK (dtr_dat_id);
alter table %PREFIX%_date_role add index DTR_ROL_FK (dtr_rol_id);

-- Constraints
alter table %PREFIX%_date_role add constraint %PREFIX%_FK_DTR_DAT foreign key (dtr_dat_id)
      references %PREFIX%_dates (dat_id) on delete restrict on update restrict;
alter table %PREFIX%_date_role add constraint %PREFIX%_FK_DTR_ROL foreign key (dtr_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
