
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader_assign_users smallint not null default 0;
ALTER TABLE %PREFIX%_roles ADD COLUMN rol_leader_edit_users smallint not null default 0;

UPDATE %PREFIX%_roles SET rol_leader_assign_users = 1;