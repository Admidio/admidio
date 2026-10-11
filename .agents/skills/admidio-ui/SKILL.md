---
name: admidio-ui-guidelines
description: Apply Admidio UI guidelines when creating or changing controls, forms, or action areas in the Admidio repository.
---

# Admidio UI Guidelines

Choose an action's appearance according to its role in the current context. Use existing Admidio components and Bootstrap classes. When changing an existing interface, check nearby patterns.

| Role | Style |
| --- | --- |
| Primary action of a form or section | `btn-primary` |
| Important secondary action | `btn-secondary` |
| Supporting, less prominent action | `btn-outline-primary` |
| Icon-only action | `admidio-icon-link` |

For manually written buttons and button-like links, combine the relevant `btn-*` variant with `btn`, for example `class="btn btn-primary"`. If an Admidio helper already adds `btn`, pass only the appropriate variant class. Use the existing `admidio-icon-link` class for icon-only actions instead of a textless `btn`.

Give icon-only actions an accessible, translated name, such as an appropriate `aria-label`; a tooltip or `title` alone does not replace that name. Use the Admidio translation system for all visible text and accessible labels. Use a link for navigation and a button for actions that do not navigate, where the existing component allows it.

Before adding a translation string, check whether a suitable key already exists. New translation keys must always start with `SYS_`. When the English text is short, name the key directly after that text where possible, using uppercase letters and underscores; for example, `Save` → `SYS_SAVE`. Do not add a context prefix to the key: use `SYS_SAVE` for `Save` on an event page, not `SYS_EVENT_SAVE`.
