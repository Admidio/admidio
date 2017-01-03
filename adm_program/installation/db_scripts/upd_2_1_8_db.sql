/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- Leiter bei einer Mitgliedschaft korrigieren
UPDATE %PREFIX%_members SET mem_leader = 0 WHERE mem_leader IS NULL;

ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_leader` tinyint(1) unsigned not null default 0;
ALTER TABLE %PREFIX%_members MODIFY COLUMN `mem_begin` date not null;
