
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader smallint not null default 0;

UPDATE %PREFIX%_roles SET rol_leader = 1;