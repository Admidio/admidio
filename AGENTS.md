# AGENTS.md

## Purpose and scope

This file gives repository-wide instructions to coding agents working on Admidio. It applies to the
whole tree unless a more specific `AGENTS.md` exists in a subdirectory. Direct maintainer
instructions for a task take precedence over this file.

The checked-out branch is the final authority. Read the implementation, tests, schema, templates,
and update scripts that exist in that branch before changing them. When this document names an API
that is not present in the branch, do not invent a substitute with the same name. Follow the
closest branch-local pattern, or make the missing infrastructure an explicit part of the proposed
change.

Admidio is not a generic PHP framework project. Do not introduce repositories, DTOs, controllers,
routers, middleware, service containers, ORMs, or other framework patterns merely because they are
common elsewhere. Use such a pattern only when the current Admidio code uses it for the same kind
of work.

## First principle: inspect, then adapt

Before writing code:

1. Identify the closest existing Admidio implementation for every affected layer.
2. Read that implementation end to end, including its entity, service, presenter, module entry
   point, template, language keys, permissions, schema, update path, and tests where applicable.
3. Search for all callers before changing a public method, global, hook, preference, CLI command,
   table column, or template variable.
4. Copy and adapt the verified Admidio pattern rather than designing from memory.
5. Keep the change limited to the requested behavior. Do not bundle unrelated conversions,
   cleanup, modernization, renaming, or reformatting.
6. Add or update tests at the level where the regression can actually be observed.
7. Review the complete diff for security, portability, line-ending churn, generated files, and
   accidental local data before presenting or committing it.

When explaining an implementation, name the source files used as pattern references. Never present
an uninspected API, placeholder, or "replace this later" implementation as finished code.

## Repository map

| Path | Responsibility |
|---|---|
| `modules/` | Native web entry points and module-specific workflows |
| `src/<Domain>/Entity/` | Database-backed domain entities |
| `src/<Domain>/Service/` | Domain operations and save workflows |
| `src/<Domain>/ValueObjects/` | Value objects where the domain already uses them |
| `src/UI/Presenter/` | Page and form presenters |
| `src/Hooks/` | The action/filter/resolver extension point and its value objects |
| `themes/simple/templates/` | Smarty page, module, and form templates |
| `src/Infrastructure/` | Shared database, entity, CLI, plugin, security, and other infrastructure |
| `system/` | Bootstrap code, constants, shared functions, and legacy integration code |
| `install/db_scripts/` | Fresh-install schema, preferences, and versioned update XML |
| `languages/` | Source and translated language XML |
| `plugins/` | Bundled Admidio plugins; not a substitute for native core modules |
| `tests/Unit/` | Fast tests without database behavior |
| `tests/Integration/` | Real-database entity and service regression tests |
| `tests/Cli/` | In-process and subprocess CLI tests |
| `tests/Support/` | Shared fixture, permission, database, and CLI test infrastructure |
| `admidio` | Extensionless native CLI entry point |

Treat `adm_my_files/`, local configuration, `.env.test`, test output, caches, IDE metadata, and
runtime uploads as local data. Follow `.gitignore`; never commit credentials, private keys,
installation configuration, or user files.

## Native feature architecture

A user-facing core feature belongs in native Admidio code, not in a standalone plugin, unless the
task explicitly requests a plugin.

Use the nearest complete module as the model. A representative vertical slice is:

- `modules/menu.php`
- `src/UI/Presenter/MenuPresenter.php`
- `src/Menu/Service/MenuService.php`
- the corresponding `src/Menu/Entity/` classes
- `themes/simple/templates/modules/menu.*.tpl`

Do not assume this is the closest pattern for every feature; compare it with modules that have the
same interaction and permission model.

Two module layouts coexist. The newer one is a single entry file, `modules/events.php` or
`modules/announcements.php`, with the whole view in a presenter under `src/UI/Presenter/`. The
older one is a directory, `modules/inventory/` or `modules/changelog/`, with several entry files.
Follow the layout the module you are extending already uses; for a new module copy the
single-file one.

### Module entry points

Keep module entry files focused on HTTP orchestration:

- load the normal Admidio bootstrap used by neighboring module files;
- validate every request parameter with `admFuncVariableIsValid()` or the exact current helper for
  that endpoint;
- determine AJAX mode before error handling where the neighboring module does so;
- enforce component and record-level permissions before reading or changing protected data;
- dispatch the requested mode or action;
- call entities, services, and presenters rather than implementing their responsibilities inline;
- return JSON for save, delete, or AJAX actions when the matching module does;
- catch failures and call `handleException($exception, $isAjax)` in the established style.

Do not validate an empty optional UUID as type `uuid`. Guard the validation until a value is
present, or copy a verified optional-UUID pattern from a current module.

Use `CURRENT_URL`, `ADMIDIO_URL`, `FOLDER_MODULES`, and
`Admidio\Infrastructure\Utils\SecurityUtils::encodeUrl()` in the same way as neighboring code.
Do not concatenate untrusted query values into URLs.

### Presenters and templates

Build pages and forms in presenters under `src/UI/Presenter/`. Extend the same presenter base class
as the closest module. Prefer one cohesive module presenter with methods such as `createList()`,
`createEditForm()`, `createDeleteConfirmation()`, or `createSequence()` over one class per screen,
unless the current module family clearly uses another design.

Use `Admidio\UI\Presenter\FormPresenter` for forms. The presenter defines controls and metadata; the
Smarty template emits the actual HTML. Register every stateful form with:

```php
$gCurrentSession->addFormObject($form);
```

A form template must:

- render the actual `<form ...>` wrapper with the attributes supplied by `FormPresenter`;
- include the appropriate `sys-template-parts/form.*.tpl` for every element;
- include `adm_csrf_token`;
- use the same button, icon, menu, and layout conventions as the nearest existing template.

Never dump `{$elements}` directly. Never assume that adding controls to `FormPresenter` renders
them automatically. Avoid passing `null` to template variables whose current template contract
expects a string.

Page HTML IDs follow the `adm_` snake_case scheme, for example `adm_sso_keys_configuration`. Use
`PagePresenter::withHtmlIDAndHeadline()` or `setHtmlID()` as the closest page does, and do not
introduce a new `admidio-` prefixed ID.

### Services and save workflows

Put nontrivial POST save logic in a domain service under `src/<Domain>/Service/` when that is the
current module pattern. Retrieve and validate the stored form object through the existing session
contract, for example:

```php
$form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
$formValues = $form->validate($_POST);
```

Perform authorization again in the save/delete path; do not trust a hidden field, disabled
control, prior page view, or client-side validation. For a direct delete or AJAX action that does
not validate a stored `FormPresenter`, use
`Admidio\Infrastructure\Utils\SecurityUtils::validateCsrfToken()` in the exact pattern of the
closest current endpoint. Keep multi-record changes in the database transaction style used by the
closest service.

### Entities

A class representing a database row normally belongs under `src/<Domain>/Entity/` and extends
`Admidio\Infrastructure\Entity\Entity`.

Use the base entity's column metadata and dynamic value storage. Do not declare a PHP property for
every database column when `Entity` already supplies `readDataById()`, `readDataByUuid()`,
`setValue()`, `getValue()`, `setArray()`, `save()`, and `delete()`.

`isNewRecord()` answers whether the record still has to be inserted, and becomes false once it has
been. `wasInserted()` answers whether this object's `save()` created the record and stays true
afterwards, which is what a "created versus changed" decision after `save()` needs.

Do not add a custom repository layer around an entity unless a directly comparable Admidio domain
already requires one. Use entity writes when changelog entries, entity hooks, normalization, or
domain invariants are expected; direct SQL updates and deletes bypass those behaviors.

## Bootstrap and application context

Admidio deliberately uses bootstrapped globals such as `$gDb`, `$gCurrentUser`, `$gCurrentOrgId`,
`$gCurrentSession`, and `$gSettingsManager`. Use them only in the same layer and lifecycle phase as
the closest current code. Do not introduce a second dependency-injection or request-context system
for a local change.

Web and CLI bootstraps are not interchangeable:

- normal web modules use the web bootstrap used by neighboring module files;
- normal installed CLI work uses `system/bootstrap/cli.php`;
- installation, documentation, and other deliberately database-free CLI paths use
  `system/bootstrap/cli-installation.php` where the current CLI routes them.

Do not make lightweight installation or maintenance commands depend on an installed database,
current organization, active plugins, or an acting user unless their command contract explicitly
requires that context.

Avoid application work at include time. Registration files may declare callbacks and metadata, but
the selected operation should run only after bootstrap, parsing, actor setup, and authorization.

## Security and permissions

Every entry point and state-changing path must preserve all of these properties:

- validate request arguments and option values;
- use prepared statements for data values;
- use Admidio form validation and CSRF handling;
- escape output through existing presenter and Smarty mechanisms;
- authorize both the component and the individual record/action;
- scope organization-owned records to the current organization;
- never rely on hidden fields or submitted IDs as proof of permission;
- do not disclose passwords, tokens, keys, private configuration, or sensitive hook values;
- do not expose uploaded files directly or add a download endpoint without an explicit visibility
  and permission check;
- preserve safe redirect and URL encoding behavior;
- avoid leaking stack traces, SQL, filesystem paths, or secrets through web or machine-readable
  responses.

Use `Admidio\Components\Entity\Component::isVisible()` and `Component::isAdministrable()` where the
component model applies. Reuse existing user and record-level rights methods. On branches with the
typed `component_visible` and `component_administrable` filters, extensions should use those
contracts rather than mutate core permission state. Do not add a new role-right column merely to
avoid using the current administrator/component model. When a new right is genuinely required,
implement it consistently in schema, installation and update paths, role editing and saving,
language strings, component checks, tests, and all execution paths.

For a potential vulnerability, follow `SECURITY.md`; do not publish exploit details in an ordinary
issue.

## Database, schema, and preferences

### Query and entity rules

Use Admidio's database abstraction and generic SQL:

- call `$gDb->queryPrepared()` for queries and bind all data values;
- use table constants such as `TBL_USERS`, adding a corresponding `TBL_*` constant in the current
  constants location when introducing a table;
- use the transaction API of `Admidio\Infrastructure\Database` rather than raw PDO transaction
  calls;
- preserve organization filters and ownership columns;
- copy ordering, pagination, and result-shape patterns from the nearest query;
- never interpolate user-controlled input into SQL, identifiers, sort expressions, or `IN` lists.

`src/Infrastructure/Database.php` converts selected generic SQL constructs for supported engines.
Do not add MySQL-only or PostgreSQL-only SQL unless the exact call site already branches by engine
for a documented reason. Test schema and query changes on both MariaDB/MySQL and PostgreSQL when
the change can behave differently.

### Installation and update scripts

For a schema change, inspect both fresh-install and upgrade paths:

- add the fresh-install definition to `install/db_scripts/db.sql`;
- add the matching change to the current applicable `install/db_scripts/update_*.xml`;
- use `%PREFIX%` in install/update SQL as neighboring steps do;
- keep one executable SQL statement in each XML `<step>`, and do not hide several behind a
  delimiter;
- if an index is needed during `CREATE TABLE`, put it inside the `CREATE TABLE` statement, because
  the step executor permits only one statement;
- avoid a trailing comma in `CREATE TABLE`;
- write no trivial comments in `update_5_x.xml`, and do not line-wrap a single-statement step;
- update the relevant `TBL_*` constant and entity metadata;
- add regression coverage for both a fresh installation and the affected behavior when practical.

Update step ids rise in tens and are never reused or renumbered once a version has shipped them.
An installation records the last id it ran, so changing what an id means silently skips work on
every installation that already passed it. When a branch and upstream have both appended steps,
keep upstream's ids untouched and renumber only the branch's own steps, continuing past upstream's
highest id. Ids must stay ascending in document order; gaps are fine.

Normal domain tables often have an ID, UUID, organization relation, and create/change user and
timestamp columns, but this is not a universal template. Copy the closest table with the same
ownership and lifecycle semantics. Match its types; common examples are `integer unsigned` IDs,
`varchar(36)` UUIDs, `boolean` flags, `timestamp`, `text` for longer texts, `varchar(255)`
filenames, and `varchar(2000)` URLs. The bookkeeping columns take this form:

```sql
xxx_usr_id_create           integer unsigned,
xxx_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
xxx_usr_id_change           integer unsigned,
xxx_timestamp_change        timestamp           NULL        DEFAULT NULL,
```

Foreign keys are added as a separate `ALTER TABLE`, which may carry several constraints, in both
`db.sql` and the update XML:

```sql
ALTER TABLE %PREFIX%_announcements
    ADD CONSTRAINT %PREFIX%_fk_ann_cat         FOREIGN KEY (ann_cat_id)         REFERENCES %PREFIX%_categories (cat_id)          ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ann_usr_create  FOREIGN KEY (ann_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_ann_usr_change  FOREIGN KEY (ann_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)               ON DELETE SET NULL ON UPDATE RESTRICT;
```

### Preferences

`src/Preferences/Service/PreferenceDefinitions.php` is the canonical preference registry on
branches that contain it. Its class docblock documents every key a definition may carry; read it
rather than guessing.

- A new core preference is declared in `PreferenceDefinitions::table()`. It needs no update step:
  `install/db_scripts/preferences.php` only calls `PreferenceDefinitions::defaults()`, and
  `Admidio\InstallationUpdate\Service\Update::updateOrgPreferences()` inserts everything new into
  every organization.
- A definition carries `default` or `defaultProvider`, a `type` of `string` (the default), `bool`,
  `int`, `enum` or `reference`, and optionally `values`, `minimum`, `maximum`, `maxLength`,
  `required`, `sensitive`, `internal` or `validator`. Omitting `maximum` and passing `null` mean
  the same thing.
- A preference owned by a module or plugin is registered at runtime with
  `PreferenceDefinitions::register($name, $definition)` before first use.
- `SettingsManager::registerDefaults()` no longer exists. Do not call it, and do not add a
  preference to a literal array in `install/db_scripts/preferences.php`.
- Read a preference through `$gSettingsManager->get()`, `getBool()`, `getInt()`, `getFloat()` or
  `getString()`. Do not read the preferences table directly.
- Keep labels, help text, and form layout in the UI and language layer, not in the definition.

## Changelog and entity persistence hooks

### Changelog

Use the existing `ChangelogService` and entity conventions. Do not invent a parallel audit table.

`ChangelogService::displayHistoryButton()` already decides whether the supplied table or tables
have changelog support. Do not wrap it in a redundant preference check unless another current
caller demonstrably needs one.

The application changelog is produced by `Entity::save()` and `Entity::delete()`. A service that
updates or deletes dependent rows with raw SQL silently skips that audit path. Load and mutate
those rows through their entity when each logical change must be audited. Keep module-specific
display formatting in the module or entity helper that owns the data, for example
`ItemsData::formatChangelogValue()`, and never as module state in the generic changelog
infrastructure.

#### A new table is logged by default: decide, then follow through

`Entity::save()` and `Entity::delete()` log every table that is not opted out, so a new table
starts producing changelog entries the moment its entity is used, with no registration required.
`Entity::readableName()` already falls back through the `_name`, `_title`, `_headline` and `_text`
columns before it falls back to the numeric id, and `ChangelogService::getTableLabel()` falls back
to the raw table name for a table with no registered label, so an unregistered table is still
logged in a usable, if unpolished, way as long as it has one of those name-like columns. When you
add a table, do all three steps:

**a) Decide whether the table has audit value.** Ask who writes the rows. Configuration that an
administrator edits and decisions a user makes belong in the log. Rows the application writes and
expires on its own — tokens, sessions, queues, caches, transient protocol or transaction state —
do not, and they drown the useful entries.

**b) Not desired: add it to `ChangelogService::$noLogTables`,** with the table name without the
`adm_` prefix. Nothing else is needed; `LogChanges::save()` then discards the entry. Say in a
comment why the table is exempt.

**c) Desired: register it everywhere for a fully polished display.** An unregistered table is not
silently skipped: `ChangelogService::isTableLogged()` treats a table with no label as *unknown* and
falls back to the `changelog_table_others` preference (off by default, like most specific tables),
so it is logged once an administrator turns that catch-all on, under the raw table name and with
raw column names where no translation is registered. Registration replaces that raw fallback with
a translated label, a link to the record, and a dedicated on/off preference of its own, so add
`changelog_table_<table>` to `PreferenceDefinitions` in the same change, or the table stops being
logged the moment it gains a label. In `ChangelogService`:

| Method | What to add |
|---|---|
| `getTableLabelArray()` | the table's own translated label |
| `getAreaArray()` | the area whose section and `enabledBy` gate the table |
| `getObjectForTable()` | a `case` returning the entity |
| `createLink()` | the link target, or a comment stating that no page exists |
| `getFieldTranslations()` | one entry per column that is actually logged |
| `getRelatedTable()` | only when the entries name a related object |

and on the entity itself:

| Method | What to provide |
|---|---|
| `readableName()` | a name for the record; the default falls back to the id |
| `getIgnoredLogColumns()` | foreign keys shown as record or relation, and internal fields |
| `adjustLogEntry()` | for relation tables, whose entry belongs to one of the related records |

and one preference:

| File | What to add |
|---|---|
| `PreferenceDefinitions` | `changelog_table_<table>`, `bool`, default `0` unless the data is core |

A relation table logs against the record a reader would look for, not against itself.
`Membership` points its entries at the user and names the role as the related object;
`OIDCConsent` names the consenting user and shows the OIDC client as the related object. Both are
worth reading before writing a new one. A foreign key that the entry already shows as its record
or its related object is not logged a second time as a changed value, and neither are internal
checksums or caches: both belong in `getIgnoredLogColumns()`, and a column that is ignored gets no
entry in `getFieldTranslations()`. Add every new language string to `languages/en.xml`
and `languages/de.xml`, and cover the result with a test: `SsoConsentChangelogTest` pins the name,
the related object, the excluded columns and the exempt tables.

### Hook types

`Admidio\Hooks\Hooks` is the extension point that modules and plugins use. Pick the type that
matches the contract:

| Type | Registration and dispatch | Contract |
|---|---|---|
| Action | `addAction()` / `doAction()` | Observe or react; the return value is ignored |
| Filter | `addFilter()` / `applyFilters()` | Transform the first value through the callback chain |
| Typed filter | `addFilter()` / `applyTypedFilters()` | Transform while preserving the required exact type |
| Resolver | `addResolver()` / `resolve()` | Return the first non-`null` resolution |

Registration is `addAction($name, $callback, $priority, $acceptedArgs, $id)`. Lower numeric
priorities run first and equal priorities keep registration order. Supply a stable `$id` when the
callback may later be removed with `removeAction()` or `removeFilter()`.

Reuse an existing hook name instead of inventing one. The names in use are the `entity_*`, `page_*`,
`list_*`, `login_*`, `translation_*` and `component_*` families, plus `form_built`, `logout`,
`email_recipients`, `user_created`, `user_changes_cumulated`, `user_registration_accepted` and the
`category_report_*` pair.

Normal hook callback failures are logged and rethrown. Use `doActionCatchErrors()` only for
diagnostic, failure-reporting, shutdown, or cleanup hooks where a callback failure must not hide
the original result. Call `Hooks::reset()` in isolated tests or long-running test contexts that
would otherwise retain static registrations.

### Entity persistence hooks

The entity persistence hooks come from `Entity` itself, never from a module. An entity opts in by
returning a stable public identifier from `getHookId()`; it then dispatches `<id>_creating`,
`<id>_created` and `<id>_create_failed` next to the generic `entity_*` ones, and the update and
delete equivalents. An entity that returns `null`, the default, stays silent, which is deliberate
for sessions, auto logins, the changelog itself and the OAuth tokens. Do not silently enable hooks
for an entity while making an unrelated change.

When opting an entity in:

- choose a durable, documented hook ID rather than deriving one from the class name, so the class
  can be renamed without breaking the plugins that listen to it;
- list secret, token, key, and image columns in `getSensitiveHookColumns()`. The change set still
  reports that they changed, but replaces both values with the redaction marker and leaves them
  out of its snapshot;
- add tests for create, update, delete, rollback/failure, redaction, and transaction timing;
- use `Admidio\Hooks\ValueObject\EntityChangeSet` rather than re-reading a deleted entity;
- keep callbacks free of recursive `setValue()` calls on the same entity/value filter path.

A callback receives `($changeSet, $entity)`. Pre-actions run before persistence and may reject the
operation by throwing; the object is left exactly as it was found, so the caller can handle the
rejection and save again. Generic pre-actions run before entity-specific ones.

Successful post-actions do not fire when the statement runs. `EntityHookQueue` hands them to
`Database::registerAfterCommit()`, so they are dispatched when the outermost transaction commits,
run entity-specific before generic, and are discarded or reported as failures on rollback. The
queue also reduces several saves of one record within a transaction to the one change an outside
observer can see. For the `deleted` and `delete_failed` stages the entity argument is `null`,
because the object has been cleared; the immutable change-set snapshot is the reliable record of
what was deleted. Preserve the queue's operation IDs, coalescing, cascade cause, and redaction
semantics when changing entity persistence.

For deliberate raw bulk deletion, use the current `Entity::hookBulkDeletion()` pattern when hook
reporting is required; do not synthesize partial change sets independently.

The installation and the update call `Entity::setHooksEnabled(false)`, because the schema and the
core are not in a state a callback could work with while they run.

## Native CLI

The extensionless root `admidio` script is the entry point, and
`Admidio\Infrastructure\Cli\CliApplication` with `CliTaskRegistry` is the framework. Do not add
Symfony Console or a second command registry.

Core commands are registered in `CoreTasks` with `CliTaskRegistry::registerCore()`. Module and
plugin commands use `CliTaskRegistry::register()` from their own `cli.php`, which the application
discovers as `modules/<module>/cli.php` and loads on every invocation.

A `cli.php` must only register command metadata and a callback. It must not perform the command,
query or mutate data, emit normal output, or require an actor merely because the file was
included. The application registers first, then establishes any required acting user, boots plugin
runtime code where available, checks permissions, and invokes only the selected callback.

Extension command rules are enforced by `CliTaskRegistry`:

- use lowercase `module:task` names in the extension's own namespace;
- do not claim a core or another extension's namespace;
- do not call `registerCore()` from extension context;
- do not replace a command that is already registered;
- associate the command with the correct installed component;
- require an actor for extension commands;
- use `CliTaskRegistry::ACCESS_ADMINISTRABLE` for anything that changes data;
- use `ACCESS_VISIBLE` only for read-only work, which still has to make the same record-level
  checks as the web path;
- use only the registry's supported additional-right declarations.

Keep domain behavior in the existing entities and services; the callback handles CLI input and
output adaptation only. Return an integer exit code, and use the `CliApplication` helpers for rows,
output formats, JSON and JSON-API results, warnings, dry-run behavior, and queued session reloads
instead of printing ad hoc protocol output.

Add CLI tests under `tests/Cli/`. Use in-process tests for registry, parser, authorization, and
callback behavior. Use `tests/Support/CliSubprocess` only when the real `./admidio` process or the
bootstrap boundary matters; a subprocess has its own database connection and sees committed state,
not data inside the parent test's rollback transaction.

## Plugins

The native plugin system exists only on the `Plugin_System` branch and the `master-merged`
integration branch. If `src/Infrastructure/Plugins/PluginRegistry.php` is absent from the
checked-out branch, none of this applies. Do not build an informal approximation of it during
unrelated work; adding the infrastructure is an explicit change of its own.

- A plugin is a directory under `plugins/` with a `plugin.json` manifest declaring `name`,
  `description`, `version`, `author`, `icon`, `requires`, `autoload` (PSR-4 into the plugin's own
  namespace), `preferences` and `settings`. Its language keys live in its own `languages/`
  directory and its templates in its own `templates/` directory.
- `plugin.php` is the entry file. It is included once when the plugin is loaded, registers what
  the plugin contributes, and returns nothing. It is never an entry point of its own and guards
  against being called directly.
- `PluginRegistry` is the authority for discovery and for installed, enabled, and built-in state.
  Do not scan `plugins/` or instantiate a plugin's classes independently in web, CLI, or tests.
  The supporting classes are `Plugin`, `PluginLoader`, `PluginInstaller`, `PluginPackage`,
  `PluginStore`, `PluginPages`, `PluginPanel` and `PluginWidget`.
- A plugin contributes through the hook layer: `PluginWidget::register()` for an overview widget
  and the `PluginPanel::HOOK` filter for a preferences panel.
- A namespace mapping must stay inside the plugin directory and must not claim a core or another
  plugin's namespace.
- Settings declared in the manifest are read with `$plugin->getSettingValues()`. Do not add a
  plugin's settings to the core preference definitions.
- Optional plugin CLI commands belong in the plugin's own `cli.php`, not in its registration call.
- Keep plugin fixtures under `tests/fixtures/plugins/`. Never install a dummy plugin into
  `plugins/`.

## Language and visible text

Do not hard-code user-visible strings when the same kind of UI uses language keys.

- Search `languages/en.xml` for an existing generic key before adding one.
- Use established generic keys such as `SYS_SAVE`, `SYS_CANCEL`, `SYS_EDIT`, `SYS_DELETE`,
  `SYS_NAME`, `SYS_DESCRIPTION`, `SYS_WEBSITE`, `SYS_NO_RIGHTS`, and `SYS_INVALID_PAGE_VIEW` where
  they fit.
- Follow the prefix and naming pattern of the owning domain.
- `languages/en.xml` is the source of every new string. Insert it next to its neighbours in the
  same prefix block, keeping the local alphabetical order; do not append it at the end.
- Other translations normally come through Transifex; edit them only when the task explicitly
  requires it.
- German exists twice: `de.xml` uses informal address ("Möchtest du ...") and `de-DE.xml` formal
  address ("Möchten Sie ..."). They are otherwise identical, so a string that does not address the
  reader is the same in both. Do not copy reader-addressing text between them without adapting the
  register.
- Validate every edited language XML file.

Internal CLI diagnostics and developer-facing exception details may be English where the current
CLI and infrastructure code does so. Web-visible labels, help, buttons, and messages follow the
language system.

## Style and change hygiene

The project targets PHP `^8.2` in `composer.json`. Match the neighboring source and the configured
style rather than applying personal preferences.

- Use four spaces and no tabs in PHP, HTML, CSS, JavaScript, JSON, XML, and SQL.
- Use the project's long `array(...)` syntax where the current fixer or configuration requires it.
- Preserve namespace, import, docblock, brace, and type-declaration conventions of the file.
- Do not add `declare(strict_types=1)` to isolated files unless the surrounding subsystem uses it.
- Keep comments about intent or non-obvious constraints, not a narration of the code.
- Avoid speculative abstractions. Extract shared code only after confirming real repeated behavior
  and a natural Admidio owner for it.
- Do not run a broad formatter across unrelated files.
- Do not modify `vendor/`, generated output, minified third-party assets, or dependency locks
  unless dependency work is explicitly in scope.
- Keep public APIs and legacy behavior backward compatible unless the change explicitly documents
  a break.

### Line endings

`.gitattributes` declares `* text=auto` and `core.autocrlf` is true, so the working tree is CRLF
throughout while the repository stores LF. Git normalizes on commit.

A scripted edit that writes LF therefore still commits correctly, but leaves the working file
inconsistent with the rest of the tree. `sed -i` strips the CR; `perl -i` keeps it. Restore CRLF
after any tool that rewrites a whole file, and check `git diff --check` and the diff size before
presenting the change. A search string with the wrong newline silently fails to match, so match on
content rather than on line endings where possible.

## Tests and validation

Use the smallest test that proves the behavior, then run the relevant broader suite. A real
database is available; prefer running the suite over reasoning about entity or schema behavior.

### Test placement

- Pure, database-independent behavior: `tests/Unit/`.
- Entity, service, permissions, changelog, schema, and real query behavior:
  `tests/Integration/<Domain>/`.
- CLI registry, parsing, authorization, bootstrap, and subprocess behavior: `tests/Cli/`.
- Shared builders and context helpers: `tests/Support/`, but only when genuinely reusable.
- Non-production plugin data: `tests/fixtures/plugins/`.

Extend the existing base classes rather than building a new harness. `DatabaseTestCase` wraps each
test in a transaction that is rolled back in `tearDown`, and `AdministratorTestCase` adds a real
administrator context. Use `AdmidioTestFixture` to create records through real entities and
`PermissionContext` to set `$gCurrentUser`, `$gCurrentOrgId`, and settings consistently. Do not
replace integration behavior with mocks when the regression depends on Admidio's database and
entity semantics.

A test that has to observe committed state cannot see it through that rolled-back transaction; use
`tests/Support/CliSubprocess`, which runs the real `./admidio` process with its own connection.
`Entity::$loggingEnabled` is static, so a changelog test must save and restore its prior state.

### Safe local setup

Read `tests/README.md` before running database tests. The setup **drops every table in the
configured test database**. The database name must contain `test`, and `TEST_FILES_PATH` must point
to the dedicated test tree accepted by the safety checks. Never point the regression suite at a
development or production database or data directory.

The documented local flow is:

```bash
cp .env.test.example .env.test
docker-compose -f docker-compose.test.yml up -d
php tests/bin/setup-test-env.php
```

`docker-compose.test.yml` provides MariaDB, PostgreSQL, and Mailpit. The engine is part of the
environment, not a command line option:

```bash
TEST_DATABASE_ENGINE=postgres composer test:integration
```

MySQL and MariaDB need the `pdo_mysql` extension, PostgreSQL needs `pdo_pgsql`. Check
`extension_loaded('pdo_pgsql')` before claiming a change was exercised on PostgreSQL; if the
extension is present but not enabled in `php.ini`, `php -d extension=pdo_pgsql vendor/bin/phpunit`
works as a stopgap.

### Commands

```bash
composer validate --strict --no-check-publish
composer test:unit          # no database needed
composer test:integration
composer test:cli
composer test:all
php vendor/bin/phpunit <path>   # a single file
```

For touched PHP files run `php -l` on each file. For a repository-wide syntax check matching CI:

```bash
find src system modules install tests -name '*.php' -print0 \
    | xargs -0 -n1 -P4 php -l
```

Validate edited XML and JSON with an appropriate parser. Run `git diff --check`.
`.github/workflows/regression-tests.yml` is the CI contract; read the workflow itself before
claiming parity with CI.

Report validation precisely:

- list the commands that actually ran and their outcomes;
- distinguish syntax and static checks from runtime tests;
- name unavailable dependencies or services;
- never describe an unexecuted database, browser, mail, or cross-engine test as passing.

## Git and delivery

Preserve the user's working tree. Before editing, inspect `git status`, the current branch and
commit, and existing unrelated changes. Never discard, overwrite, stage, or include unrelated work.

Do not create a commit unless the maintainer explicitly asks for one. Always show the complete
patch and the proposed commit message first and get an explicit go-ahead; approval for one change
does not carry over to the next, even in the same session.

Commit one coherent issue at a time. If several issues are ready at once, commit them separately
even when they touch the same file. Use a short imperative subject in the repository style:

```text
Prefix: describe the change
```

Add at most a sentence or two of context. Describe the previous situation in a few words at most
and do not justify every individual change. Never add a `Co-Authored-By` trailer or any similar
trailer; end the message with its last content line.

When a `git format-patch` series is requested:

- record the exact baseline commit;
- make dependency order explicit;
- keep each commit independently reviewable and buildable where practical;
- generate standard `git format-patch` output rather than handcrafted pseudo-patches;
- replay the delivered files with `git am` on a clean worktree from that baseline;
- run `git diff --check`, syntax validation, and the relevant tests on the replayed tree;
- provide exact limitations and checksums;
- do not include unrelated module conversions, fixtures, or formatting.

## Agent responses and proposals

When delivering code or a review:

- identify the concrete Admidio pattern files that were inspected;
- provide changes only unless the maintainer asks for complete files;
- make each proposed patch complete for its issue, including imports, constants, templates,
  language keys, install and update schema, and tests. Never ask for approval on an isolated hunk;
  a lone namespace import cannot be judged without the code that uses it;
- distinguish source-derived facts from assumptions;
- state exactly which file or API remains unresolved when the checkout does not answer a question;
- do not use pseudocode or placeholder implementations unless a conceptual sketch was explicitly
  requested;
- do not claim an API or a validation result that was not inspected or executed;
- be exact in wording, but do not over-explain the motivation.

## Forbidden anti-patterns

Do not:

- create standalone plugin code when the task is a core module, or put new code under `plugins/`
  for core work;
- invent repositories where Admidio uses entities and services directly;
- dump Smarty `{$elements}` directly, or rely on `FormPresenter` to render controls by itself;
- implement CSRF handling manually when Admidio already provides it;
- validate an empty optional UUID as a UUID;
- put several SQL statements in one update step;
- renumber or reuse an update step id that a released version already published;
- hard-code engine-specific SQL where Admidio's generic conversion should apply;
- wrap `ChangelogService::displayHistoryButton()` in an extra changelog-setting check;
- add a role-right column when the existing administrator or component model is what was asked for;
- call `SettingsManager::registerDefaults()`, which no longer exists, or add a preference to a
  literal array in `install/db_scripts/preferences.php`;
- invent a hook name, or put module-specific knowledge into the hook layer;
- pass `null` to a template variable that expects a string;
- propose "replace this with the actual Admidio API" placeholders unless a conceptual sketch was
  explicitly requested;
- describe a change as tested when the suite was not run, or as tested on PostgreSQL when
  `pdo_pgsql` is not loaded.

## Completion checklist

Before presenting a change, verify the applicable items:

- [ ] The closest current Admidio pattern was inspected and named.
- [ ] Responsibilities are in the correct module, presenter, service, entity, template, CLI, or
      plugin layer.
- [ ] All input is validated and all state changes have CSRF protection where applicable.
- [ ] Component, record, organization, and acting-user permissions are enforced server-side.
- [ ] Queries are prepared and portable through Admidio's database abstraction.
- [ ] Entity writes preserve changelog and hook behavior, or any deliberate bypass is justified and
      tested.
- [ ] Forms use `FormPresenter`, session registration, and explicit template-part rendering.
- [ ] Visible text uses existing or properly added language keys.
- [ ] Fresh-install and upgrade schema paths agree, and no shipped update step id was reused.
- [ ] Hook callbacks use the correct action/filter/resolver contract and redact sensitive values.
- [ ] CLI registration performs no work and command callbacks enforce the web-equivalent rights.
- [ ] Tests cover the regression at the appropriate level and isolate static and global state.
- [ ] Touched PHP, XML, JSON, SQL, and templates were validated as far as the environment permits.
- [ ] The complete diff contains no secrets, local files, unrelated edits, or line-ending churn.
- [ ] The validation report says exactly what ran and what did not.
