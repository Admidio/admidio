---
name: admidio-hooks
description: Implement or modify Admidio actions, filters, resolvers, or entity persistence hooks and transaction timing.
---

# Hooks and persistence

Inspect `src/Hooks/Hooks.php`, its callers, `src/Hooks/Service/EntityHookQueue.php`,
`src/Hooks/ValueObject/EntityChangeSet.php` and relevant `Entity` methods.

Use actions (`addAction`/`doAction`) for reactions whose result is ignored, filters
(`addFilter`/`applyFilters`) for transformations, typed filters (`applyTypedFilters`) for exact-type
preservation, and resolvers (`addResolver`/`resolve`) for the first non-null result. Reuse an existing
contract where it fits. Introduce new hooks deliberately with documented contracts and tests;
keep domain-specific knowledge out of generic infrastructure.

Registration takes name, callback, priority, accepted argument count and optional stable ID. Lower
priorities run first; ties retain registration order. Stable IDs allow later removal. Normal errors
are logged and rethrown; reserve `doActionCatchErrors()` for diagnostic/cleanup paths where errors
must not obscure the original outcome. Reset registrations in isolated tests and restore static state.

Persistence hooks originate in `Entity`, not modules. Opt-in is a durable public `getHookId()`;
null deliberately leaves an entity silent. Do not enable hooks during unrelated cleanup. Redact
secret/token/key/image columns through `getSensitiveHookColumns()` and test values and snapshots.
Callbacks receive `($changeSet, $entity)`; after deletion the entity is null, so use the immutable
change set. Avoid recursive mutation on the same entity/value-filter path.

Preserve pre-action rejection semantics and ordering (generic before specific), successful post-action
execution at outermost commit (specific before generic), rollback/failure reporting, coalescing,
operation IDs and cascade causes. Cover create/update/delete, rejection, rollback/failure, redaction
and transaction timing for changed contracts. Do not dispatch success immediately after an SQL
statement. Use the current `Entity::hookBulkDeletion()` pattern for deliberate bulk deletion needing
hook reporting. Installation/update deliberately disable entity hooks while core/schema may disagree.
