
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader_rights smallint not null default 0;

UPDATE %PREFIX%_roles SET rol_leader_rights = 1;