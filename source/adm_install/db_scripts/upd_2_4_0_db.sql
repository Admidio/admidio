
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader_rights smallint not null default 0;

UPDATE %PREFIX%_roles SET rol_leader_rights = 1;

ALTER TABLE %PREFIX%_members ADD COLUMN mem_usr_id_create integer unsigned;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_timestamp_create timestamp not null default CURRENT_TIMESTAMP;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_usr_id_change integer unsigned;
ALTER TABLE %PREFIX%_members ADD COLUMN mem_timestamp_change timestamp null default null;