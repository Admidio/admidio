/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

-- fehlende Foreign-Keys zur Tabelle dates hinzufuegen
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_ROL foreign key (dat_rol_id)
      references %PREFIX%_roles (rol_id) on delete restrict on update restrict;
alter table %PREFIX%_dates add constraint %PREFIX%_FK_DAT_ROOM foreign key (dat_room_id)
      references %PREFIX%_rooms (room_id) on delete set null on update restrict;

ALTER TABLE %PREFIX%_links CHANGE COLUMN `lnk_counter` `lnk_counter` integer not null default '0';
