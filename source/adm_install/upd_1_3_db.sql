create table %PRAEFIX%_folders
(
   fol_id                         int(11) unsigned               not null AUTO_INCREMENT,
   fol_org_shortname              varchar(10)                    not null,
   fol_fol_id_parent              int(11) unsigned,
   fol_type                       varchar(10)                    not null,
   fol_name                       varchar(255)                   not null,
   primary key (fol_id)
)
type = InnoDB
auto_increment = 1;
create index FOL_ORG_FK on %PRAEFIX%_folders
(
   fol_org_shortname
);
create index FOL_FOL_PARENT_FK on %PRAEFIX%_folders
(
   fol_fol_id_parent
);
create table %PRAEFIX%_folder_roles
(
   flr_fol_id                     int(11) unsigned               not null,
   flr_rol_id                     int(11) unsigned               not null
)
type = InnoDB;
create index FLR_FOL_FK on %PRAEFIX%_folder_roles
(
   flr_fol_id
);
create index FOL_ROL_FK on %PRAEFIX%_folder_roles
(
   flr_rol_id
);