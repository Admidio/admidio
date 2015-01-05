
-- Leiter bei einer Mitgliedschaft korrigieren
UPDATE %PREFIX%_members SET mem_leader = 0 WHERE mem_leader IS NULL;

ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_leader` tinyint(1) unsigned not null default 0;
ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_begin` date not null;