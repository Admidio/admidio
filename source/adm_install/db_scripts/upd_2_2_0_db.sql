
-- Feldgroessen anpasssen
ALTER TABLE %PRAEFIX%_organizations MODIFY COLUMN `org_homepage` VARCHAR(60) NOT NULL;

-- Raumverwaltungstabelle hinzufuegen

DROP TABLE IF EXISTS %PRAEFIX%_rooms;
CREATE TABLE %PRAEFIX%_rooms
(
    room_id                         int(11) unsigned                not null auto_increment,
    room_name                       varchar(50)                     not null,
    room_description                varchar(255),
    room_capacity                   int(11) unsigned                not null,
    room_overhang                   int(11) unsigned,
    room_usr_id_create              int(11) unsigned,
    room_timestamp_create           datetime                        not null,
    primary key (room_id)                                                                       
)
engine = InnoDB
auto_increment = 1;

-- Attribut hinzufuegen

ALTER TABLE %PRAEFIX%_roles ADD COLUMN `rol_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN `dat_rol_id` INT(11) UNSIGNED;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN `dat_room_id` INT(11) UNSIGNED;
ALTER TABLE %PRAEFIX%_dates ADD COLUMN `dat_max_members` INT(11) UNSIGNED NOT NULL;
ALTER TABLE %PRAEFIX%_members ADD COLUMN `mem_from_rol_id` INT(11) UNSIGNED NULL;
ALTER TABLE %PRAEFIX%_members ADD INDEX (`mem_from_rol_id`) ;

-- Sichtbarkeitstabelle für Termine hinzufuegen

create table %PRAEFIX%_date_role
(
    dtr_id                          int(11) unsigned                not null auto_increment,
    dtr_dat_id                      int(11) unsigned                not null,
    dtr_rol_id                      int(11) unsigned,
    primary key (dtr_id)
)
engine = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_date_role add index DTR_DAT_FK (dtr_dat_id);
alter table %PRAEFIX%_date_role add index DTR_ROL_FK (dtr_rol_id);

-- Constraints
alter table %PRAEFIX%_date_role add constraint %PRAEFIX%_FK_DTR_DAT foreign key (dtr_dat_id)
      references %PRAEFIX%_dates (dat_id) on delete restrict on update restrict;
alter table %PRAEFIX%_date_role add constraint %PRAEFIX%_FK_DTR_ROL foreign key (dtr_rol_id)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;

-- Tabelle für maximale Zusagen an Termine

CREATE TABLE %PRAEFIX%_date_max_members (
    dmm_id                          int(11) unsigned                not null auto_increment,
    dmm_dat_id                      int(11) unsigned                not null,
    dmm_rol_id                      int(11) unsigned                not null,
    dmm_max_members                 int(11) unsigned                not null,
    PRIMARY KEY (dmm_id)
) 
ENGINE = InnoDB
auto_increment = 1;

-- Index
alter table %PRAEFIX%_date_max_members add index DMM_DAT_FK (dmm_dat_id);
alter table %PRAEFIX%_date_max_members add index DMM_ROL_FK (dmm_rol_id);

-- Constraints
alter table %PRAEFIX%_date_max_members add constraint %PRAEFIX%_FK_DMM_DAT foreign key (dmm_dat_id)
      references %PRAEFIX%_dates (dat_id) on delete restrict on update restrict;
alter table %PRAEFIX%_date_max_members add constraint %PRAEFIX%_FK_DMM_ROL foreign key (dmm_rol_id)
      references %PRAEFIX%_roles (rol_id) on delete restrict on update restrict;