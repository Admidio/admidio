
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader_rights smallint not null default 0;

UPDATE %PREFIX%_roles SET rol_leader_rights = 1;

ALTER TABLE %PREFIX%_members ADD COLUMN mem_usr_id_create integer unsigned;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_timestamp_create timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_usr_id_change integer unsigned;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_timestamp_change timestamp null default null;

alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_USR_CREATE foreign key (mem_usr_id_create)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
alter table %PREFIX%_members add constraint %PREFIX%_FK_MEM_USR_CHANGE foreign key (mem_usr_id_change)
      references %PREFIX%_users (usr_id) on delete set null on update restrict;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_timestamp_change timestamp null default null;

-- -----------------------------------------------------
-- Table %PREFIX%_user_log
-- -----------------------------------------------------
CREATE TABLE %PREFIX%_user_log (
  usl_id                INTEGER                  NOT NULL AUTO_INCREMENT ,
  usl_usr_id            INTEGER         unsigned NOT NULL ,
  usl_usf_id            INTEGER         unsigned NOT NULL ,
  usl_value_old         VARCHAR(255)             NULL ,
  usl_value_new         VARCHAR(255)             NULL ,
  usl_usr_id_create     INTEGER         unsigned NOT NULL ,
  usl_timestamp_create  TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  usl_comment           VARCHAR(255) NULL ,
  PRIMARY KEY (usl_id) 
)
ENGINE = InnoDB
auto_increment = 1
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;

alter table %PREFIX%_user_log add
  CONSTRAINT %PREFIX%_FK_USER_LOG_1
    FOREIGN KEY (usl_usr_id )
    REFERENCES %PREFIX%_users (usr_id )
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;


alter table %PREFIX%_user_log add
  CONSTRAINT %PREFIX%_FK_USER_LOG_2
    FOREIGN KEY (usl_usr_id_create )
    REFERENCES %PREFIX%_users (usr_id )
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

alter table %PREFIX%_user_log add
  CONSTRAINT %PREFIX%_FK_USER_LOG_3
    FOREIGN KEY (usl_usf_id )
    REFERENCES %PREFIX%_user_fields (usf_id )
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;