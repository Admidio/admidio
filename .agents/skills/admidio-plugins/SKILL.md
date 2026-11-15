---
name: admidio-plugins
description: Implement or modify Admidio plugin manifests, discovery, settings, extension contributions, or lifecycle fixtures.
---

# Plugins

Inspect a comparable bundled plugin's `plugin.json` and `plugin.php` and relevant classes under
`src/Infrastructure/Plugins/`. Core features remain native unless a plugin is requested.

Follow the current manifest schema for identity/version/requirements, plugin-local PSR-4 autoload,
preferences and settings. Copy a verified manifest without assuming every possible field is mandatory.
Mappings stay inside the plugin directory and cannot claim other namespaces. Languages/templates
live in the plugin's own directories and use its naming conventions.

`plugin.php` registers contributions once, returns nothing and guards against direct execution;
it is not an HTTP entry point. `PluginRegistry` governs discovery and installed/enabled/built-in state.
Do not independently scan folders or instantiate unavailable plugin classes in web, CLI or tests.
Use existing loader/installer/package/store/pages abstractions where appropriate.

Contribute through hooks, including `PluginWidget::register()` for overview widgets and
`PluginPanel::HOOK` for preference panels. Read manifest settings through `$plugin->getSettingValues()`;
do not add them to core definitions. Read the [hooks skill](../admidio-hooks/SKILL.md) for contracts
and [CLI skill](../admidio-cli/SKILL.md) for plugin-local `cli.php`.

Keep dummy plugins in `tests/fixtures/plugins/`, never in production `plugins/`. Exercise lifecycle
and permissions through the registry using existing fixture patterns.
