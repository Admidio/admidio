
-- Tabelle Categories erweitern
ALTER TABLE %PREFIX%_categories ADD COLUMN `cat_default` tinyint (1) unsigned not null default 0 AFTER cat_system;
