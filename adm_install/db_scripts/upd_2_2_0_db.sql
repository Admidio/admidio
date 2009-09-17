
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

DROP TABLE IF EXISTS %PRAEFIX%_date_role;
CREATE TABLE %PRAEFIX%_date_role
(
    id                              int(11) unsigned                not null auto_increment,
    dat_id                          int(11) unsigned                not null,
    rol_id                          int(11) unsigned                not null,
    primary key (id)
)
engine = InnoDB
auto_increment = 1;

-- Tabelle für maximale Zusagen an Termine

CREATE TABLE %PRAEFIX%_date_max_members (
    id              INT NOT NULL AUTO_INCREMENT ,
    dat_id          INT NOT NULL ,
    rol_id          INT NOT NULL ,
    max_members     INT NOT NULL ,
    PRIMARY KEY ( id )
) 
ENGINE = InnoDB
auto_increment = 1;

