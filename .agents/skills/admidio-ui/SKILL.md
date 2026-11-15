---
name: admidio-ui-guidelines
description: Apply Admidio UI guidelines when creating or changing controls, forms, templates, translations, or action areas in the Admidio repository.
---

# Admidio UI Guidelines

Choose an action's appearance according to its role in the current context. Use existing Admidio components and Bootstrap classes. When changing an existing interface, check nearby patterns.

Prefer Bootstrap component and utility classes for layout, spacing, sizing, colors, and responsive behavior. Reuse existing Admidio classes where needed. Avoid custom CSS, including new CSS classes, `<style>` blocks, or inline `style` attributes, when existing Bootstrap and Admidio classes can achieve the desired result. Custom CSS is allowed when needed, but keep it minimal.

| Role | Style |
| --- | --- |
| Primary action of a form or section | `btn-primary` |
| Important secondary action | `btn-secondary` |
| Supporting, less prominent action | `btn-outline-primary` |
| Icon-only action | `admidio-icon-link` |

For manually written buttons and button-like links, combine the relevant `btn-*` variant with `btn`, for example `class="btn btn-primary"`. If an Admidio helper already adds `btn`, pass only the appropriate variant class. Use the existing `admidio-icon-link` class for icon-only actions instead of a textless `btn`.

Give icon-only actions an accessible, translated name, such as an appropriate `aria-label`; a tooltip or `title` alone does not replace that name. Use the Admidio translation system for all visible text and accessible labels. Use a link for navigation and a button for actions that do not navigate, where the existing component allows it.

Before adding a translation string, check whether a suitable key already exists. New translation keys must always start with `SYS_`. When the English text is short, name the key directly after that text where possible, using uppercase letters and underscores; for example, `Save` → `SYS_SAVE`. Do not add a context prefix to the key: use `SYS_SAVE` for `Save` on an event page, not `SYS_EVENT_SAVE`.

## Presenters and form templates

Inspect `src/UI/Presenter/FormPresenter.php` and a complete nearby presenter/template pair such as
`MenuPresenter` and `themes/simple/templates/modules/menu.*.tpl`. Define controls/metadata in the
presenter and register stateful forms with `$gCurrentSession->addFormObject($form)`.

The Smarty template renders the actual form wrapper with supplied attributes, each control's
`sys-template-parts/form.*.tpl`, and `adm_csrf_token`. Never dump `{$elements}` or assume added
controls render automatically. Preserve template value types; do not pass null where strings are
expected. Use `adm_` snake_case page IDs and existing `withHtmlIDAndHeadline()` / `setHtmlID()` patterns.

## Translation files

Search `languages/en.xml` for reusable keys first. Insert new core `SYS_` keys beside their neighbors
in local alphabetical order. Plugin-local keys follow their plugin's conventions. English is the
source; other translations normally come through Transifex, so edit them when explicitly requested.
`de.xml` addresses readers informally, `de-DE.xml` formally; adapt reader-addressing text accordingly.
Validate edited language XML. Internal CLI/developer diagnostics may remain English where the
subsystem follows that convention.
