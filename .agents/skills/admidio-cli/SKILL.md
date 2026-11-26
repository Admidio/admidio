---
name: admidio-cli
description: Add or change Admidio CLI commands, registration, acting-user permissions, or bootstrap boundaries.
---

# Native CLI

Inspect root `admidio`, `src/Infrastructure/Cli/CliApplication.php`, `CliTaskRegistry.php` and
`CoreTasks.php`. Use this framework, not a second registry or Symfony Console. Installed work uses
`system/bootstrap/cli.php`; database-free installation/documentation paths use
`system/bootstrap/cli-installation.php` where the application routes them. Preserve that boundary.

Core commands use `registerCore()`; modules/plugins use `register()` from their own `cli.php`.
Registration files declare metadata/callbacks only: no execution, data access, normal output or actor
requirement at include time. The application establishes actors, plugin runtime and authorization
before invoking only the selected callback.

Extension commands use lowercase `module:task` names in their own namespace, associate the correct
installed component, require an actor and never replace commands or call `registerCore()`. Use
`ACCESS_ADMINISTRABLE` for writes, `ACCESS_VISIBLE` only for reads, and supported additional-right
declarations. Enforce record/organization rights equivalent to web behavior.

Keep domain behavior in services/entities. Use `CliApplication` helpers for rows, formats, JSON and
JSON-API, warnings, dry runs and queued session reloads. Return integer exit codes. Do not make
lightweight commands depend on a database, organization, actor or plugins unless their contract
requires it.

Tests belong in `tests/Cli/`; read the [testing skill](../admidio-testing/SKILL.md). Use in-process
coverage for registry/parser/authorization and `tests/Support/CliSubprocess` for real process or
bootstrap behavior. Separate connections cannot see the parent test's uncommitted fixtures.
