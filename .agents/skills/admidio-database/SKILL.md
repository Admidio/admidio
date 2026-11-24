---
name: admidio-database
description: Change Admidio queries, entities, database schemas, upgrade scripts, or preference definitions.
---

# Database and preferences

Inspect `src/Infrastructure/Database.php`, the closest entity/table and relevant installation/update
code. Use `queryPrepared()` with bound data, `TBL_*` constants and Admidio's transaction API.
Preserve organization filters, ownership, ordering and pagination contracts. Never interpolate
untrusted identifiers, sort expressions or IN lists. Use generic SQL supported by the abstraction;
verify engine-sensitive changes on MySQL/MariaDB and PostgreSQL where available.

Row entities normally extend `Admidio\Infrastructure\Entity\Entity`. Use its metadata and dynamic
values rather than duplicating columns as properties or introducing a repository layer.
`isNewRecord()` describes pending insertion; `wasInserted()` describes the outcome after `save()`.
Raw SQL mutation bypasses entity invariants, changelog and hooks. For new tables or changed writes,
read the relevant [changelog](../admidio-changelog/SKILL.md) and [hooks](../admidio-hooks/SKILL.md) skills.

## Schema and upgrades

- Inspect `install/db_scripts/db.sql`, `migration-paths.json`, the current version and applicable
  `update_*.xml`; do not assume the update filename from an older branch.
- Change both fresh installation and development update paths, using `%PREFIX%` as neighboring SQL
  does. Preserve upgrades from the latest release and leave released demo data unchanged.
- Keep one executable statement per XML `<step>` without delimiter tricks. Define CREATE-time
  indexes inside that statement and avoid trailing commas. Add foreign keys through separate
  ALTER TABLE statements following the closest table.
- Never reuse, renumber or change the meaning of shipped step IDs. IDs ascend in document order,
  conventionally in tens. After upstream collisions, preserve upstream IDs and renumber only
  unshipped branch steps beyond upstream's highest ID.
- Match the nearest table's ownership, lifecycle, types and bookkeeping columns; ID, UUID and
  organization columns are not universally appropriate. Update constants and entity metadata.
- Follow local XML formatting; avoid trivial comments and needless SQL-step wrapping.
- Cover fresh installation, migrations and affected behavior where practical; report untested paths.

## Preferences

Read the supported keys/types/validators in the docblock of
`src/Preferences/Service/PreferenceDefinitions.php`. Core definitions belong in its private `table()`;
installation reads `defaults()` and `Update::updateOrgPreferences()` seeds new core defaults.
New defaults alone need no SQL step; data transformations may still need migration.

Runtime module definitions use `PreferenceDefinitions::register()` before first use. Plugin manifest
settings follow their own contract. Do not call removed `SettingsManager::registerDefaults()` or add
literal defaults to `install/db_scripts/preferences.php`. Read through `$gSettingsManager` getters,
not direct SQL. Keep labels, help and layout in UI/language code.
