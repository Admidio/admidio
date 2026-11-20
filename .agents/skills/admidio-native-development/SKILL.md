---
name: admidio-native-development
description: Implement or review native Admidio web modules, presenters, and domain save workflows.
---

# Native development

Inspect the closest complete feature, its callers, permissions, service, entity and template before
editing. A starting slice is `modules/menu.php`, `src/UI/Presenter/MenuPresenter.php`,
`src/Menu/Service/MenuService.php`, `src/Menu/Entity/` and
`themes/simple/templates/modules/menu.*.tpl`. Compare interaction and permission semantics before
copying it. Name the actual pattern files in the delivery report.

Core features belong in native code unless a plugin is requested. Existing modules use both single
files and directories under `modules/`; preserve an existing module's layout. New modules should
follow the current single-entry-file/presenter pattern.

Keep entry points focused on HTTP orchestration: neighboring bootstrap, argument validation with
`admFuncVariableIsValid()` or the current endpoint helper, AJAX detection, authorization, dispatch,
and calls to services/presenters. Preserve neighboring JSON responses and
`handleException($exception, $isAjax)` behavior. Do not validate an empty optional UUID as `uuid`.
Use existing URL constants and `SecurityUtils::encodeUrl()` instead of untrusted concatenation.

Presenters live in `src/UI/Presenter/`, domain operations in `src/<Domain>/Service/`, row entities
in `src/<Domain>/Entity/`. Prefer a cohesive presenter unless the module family uses another design.
Read the [UI skill](../admidio-ui/SKILL.md) for forms/templates.

Save workflows retrieve the session form with
`$gCurrentSession->getFormObject($_POST['adm_csrf_token'])` and validate submitted values through it.
Repeat component and record authorization on save/delete. For actions without stored form validation,
copy the closest `SecurityUtils::validateCsrfToken()` pattern. Hidden fields confer no rights.
Preserve organization ownership, output escaping, safe redirects, download/upload visibility checks,
and safe error responses without traces, SQL or secrets.

Use `Component::isVisible()` / `isAdministrable()` and existing record rights. Do not add role-right
columns merely for component administration. A genuinely new right requires consistent schema,
migration, role UI/save, language, component and execution-path changes. Follow `SECURITY.md` for
potential vulnerabilities.

Use bootstrapped globals only in their established layer/lifecycle, without a second DI or request
context system. Registration files must not execute application work at include time. Web and CLI
bootstraps have different context requirements.
