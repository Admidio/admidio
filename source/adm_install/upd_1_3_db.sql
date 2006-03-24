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
alter table adm_folder_roles add constraint %PRAEFIX%_FK_FOL_ROL foreign key (flr_rol_id)
      references adm_roles (rol_id) on delete restrict on update restrict;
alter table adm_folder_roles add constraint %PRAEFIX%_FK_FLR_FOL foreign key (flr_fol_id)
      references adm_folders (fol_id) on delete restrict on update restrict;
alter table adm_folders add constraint %PRAEFIX%_FK_FOL_FOL_PARENT foreign key (fol_fol_id_parent)
      references adm_folders (fol_id) on delete restrict on update restrict;
alter table adm_folders add constraint %PRAEFIX%_FK_FOL_ORG foreign key (fol_org_shortname)
      references adm_organizations (org_shortname) on delete restrict on update restrict;