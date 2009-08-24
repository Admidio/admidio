
-- Feldgroessen anpasssen
ALTER TABLE %PRAEFIX%_organizations MODIFY COLUMN `org_homepage` VARCHAR(60) NOT NULL;

--Raumverwaltungstabelle hinzufügen
drop table if exists %PRAEFIX%_rooms;
create table %PRAEFIX%_rooms
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
