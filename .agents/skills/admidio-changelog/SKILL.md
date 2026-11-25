---
name: admidio-changelog
description: Integrate tables and entities with the Admidio changelog or repair audit behavior in save and delete workflows.
---

# Changelog integration

Inspect `src/Changelog/Service/ChangelogService.php`, `src/Changelog/Entity/LogChanges.php`,
`src/Infrastructure/Entity/Entity.php` and a comparable entity. Do not invent a parallel audit table.
`displayHistoryButton()` already checks table support; avoid redundant preference checks.

Entity save/delete is the audit path; direct SQL mutation bypasses it. Mutate dependent rows through
entities when each logical change must be audited. Keep domain formatting in its module/entity,
not in generic changelog infrastructure.

Decide the audit value of every new table. Human-edited configuration and user decisions usually
belong in the log; expiring tokens, sessions, queues and protocol state usually do not. Exempt tables
belong in `ChangelogService::$noLogTables` without the installation prefix, with a reason. An
unregistered table is not automatically exempt: preferences and unknown-table fallback matter.

For a fully integrated logged table, check these `ChangelogService` contracts:

- `getTableLabelArray()` and `getAreaArray()`: translated label and enabling gates;
- `getObjectForTable()`: entity construction;
- `createLink()`: record target, or document why no page exists;
- `getFieldTranslations()`: columns actually logged;
- `getRelatedTable()`: when a related object is displayed.

Add `changelog_table_<table>` to `PreferenceDefinitions`, normally bool default `0`, following nearby
core-table defaults. Registering a table label changes preference routing, so include the preference.

Check entity `readableName()`, `getIgnoredLogColumns()` and relation-table `adjustLogEntry()`.
Point relation entries at the record a reader would look for, exposing the other as related.
Do not log those identifying foreign keys again as changed fields; exclude internal caches/checksums
and do not translate ignored columns. Read `Membership`, `OIDCConsent` and
`tests/Integration/Sso/SsoConsentChangelogTest.php` for comparable patterns. Cover record names,
relations, excluded columns and opt-outs, restoring static `Entity::$loggingEnabled` after tests.
Follow the UI skill for language edits rather than requiring unrelated locale changes.
