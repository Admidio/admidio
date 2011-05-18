
-- Tabelle Categories erweitern
ALTER TABLE %PREFIX%_categories ADD COLUMN `cat_default` tinyint (1) unsigned not null default 0 AFTER cat_system;

-- Datentypen von einigen Spalten aendern
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_id` `ann_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_usr_id_create` `ann_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_timestamp_create` `ann_timestamp_create` timestamp not null;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_usr_id_change` `ann_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_announcements CHANGE COLUMN `ann_timestamp_change` `ann_timestamp_change` timestamp;

ALTER TABLE %PREFIX%_auto_login CHANGE COLUMN `atl_usr_id` `atl_usr_id` integer unsigned not null;
ALTER TABLE %PREFIX%_auto_login CHANGE COLUMN `atl_last_login` `atl_last_login` timestamp not null;

ALTER TABLE %PREFIX%_categories CHANGE COLUMN  `cat_id` `cat_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_usr_id_create` `cat_usr_id_create` integer unsigned;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_timestamp_create` `cat_timestamp_create` timestamp not null;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_usr_id_change` `cat_usr_id_change` integer unsigned;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_timestamp_change` `cat_timestamp_change` timestamp;

-- Org_Id wird nun auch ein Index vom Typ INTEGER
ALTER TABLE %PREFIX%_auto_login DROP FOREIGN KEY %PREFIX%_FK_ATL_ORG;
ALTER TABLE %PREFIX%_categories DROP FOREIGN KEY %PREFIX%_FK_CAT_ORG;
ALTER TABLE %PREFIX%_folders DROP FOREIGN KEY %PREFIX%_FK_FOL_ORG;
ALTER TABLE %PREFIX%_guestbook DROP FOREIGN KEY %PREFIX%_FK_GBO_ORG;
ALTER TABLE %PREFIX%_lists DROP FOREIGN KEY %PREFIX%_FK_LST_ORG;
ALTER TABLE %PREFIX%_organizations DROP FOREIGN KEY %PREFIX%_FK_ORG_ORG_PARENT;
ALTER TABLE %PREFIX%_preferences DROP FOREIGN KEY %PREFIX%_FK_PRF_ORG;
ALTER TABLE %PREFIX%_sessions DROP FOREIGN KEY %PREFIX%_FK_SES_ORG;
ALTER TABLE %PREFIX%_texts DROP FOREIGN KEY %PREFIX%_FK_TXT_ORG;

ALTER TABLE %PREFIX%_auto_login CHANGE COLUMN `atl_org_id` `atl_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_categories CHANGE COLUMN `cat_org_id` `cat_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_folders CHANGE COLUMN `fol_org_id` `fol_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_guestbook CHANGE COLUMN `gbo_org_id` `gbo_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_lists CHANGE COLUMN `lst_org_id` `lst_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_organizations CHANGE COLUMN `org_id` `org_id` integer unsigned not null AUTO_INCREMENT;
ALTER TABLE %PREFIX%_organizations CHANGE COLUMN `org_org_id_parent` `org_org_id_parent` integer unsigned;
ALTER TABLE %PREFIX%_preferences CHANGE COLUMN `prf_org_id` `prf_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_sessions CHANGE COLUMN `ses_org_id` `ses_org_id` integer unsigned not null;
ALTER TABLE %PREFIX%_texts CHANGE COLUMN `txt_org_id` `txt_org_id` integer unsigned not null;

alter table %PREFIX%_auto_login add constraint %PREFIX%_FK_ATL_ORG foreign key (atl_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_categories add constraint %PREFIX%_FK_CAT_ORG foreign key (cat_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_folders add constraint %PREFIX%_FK_FOL_ORG foreign key (fol_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_guestbook add constraint %PREFIX%_FK_GBO_ORG foreign key (gbo_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_lists add constraint %PREFIX%_FK_LST_ORG foreign key (lst_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_organizations add constraint %PREFIX%_FK_ORG_ORG_PARENT foreign key (org_org_id_parent)
      references %PREFIX%_organizations (org_id) on delete set null on update restrict;
alter table %PREFIX%_preferences add constraint %PREFIX%_FK_PRF_ORG foreign key (prf_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_sessions add constraint %PREFIX%_FK_SES_ORG foreign key (ses_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;
alter table %PREFIX%_texts add constraint %PREFIX%_FK_TXT_ORG foreign key (txt_org_id)
      references %PREFIX%_organizations (org_id) on delete restrict on update restrict;