# Hello World — the example Admidio plugin

Every convention in one place, so that each of them can be copied on its own. Copy this directory,
rename it, and you have a working plugin.

This is **not** the smallest useful plugin. That one is two files — a `plugin.json` naming the plugin
and a `plugin.php` registering a hook — with no class, no settings, no page and no language file.
Everything below the manifest and the entry file is here because it has to be shown somewhere, not
because a plugin needs it.

```text
plugins/hello-world/
├── plugin.json                        the manifest — the only required metadata
├── plugin.php                         the entry file — included once when the plugin is loaded
├── modules/index.php                  a page, an ordinary Admidio module file
├── src/Greeting.php                   a class, reached through the "autoload" mapping
├── languages/en.xml                   the language keys of the plugin
└── templates/plugin.hello-world.tpl   a Smarty template of the plugin
```

Only `plugin.json` and `plugin.php` are required. A plugin that just registers a hook needs nothing
else.

## The manifest

The directory name is the plugin ID and the only identity the plugin has. `name` and `description`
may be language keys, which is what this plugin uses. Everything else is optional:

* `requires` — version constraints for `admidio` and `php`, plus `extensions` and `plugins`. A
  constraint is a space separated list of terms that all have to match, e.g. `>=5.1 <6.0`; a term
  without an operator means `>=`.
* `autoload` — namespace prefix to directory. The prefix must not start with `Admidio\`, which
  belongs to the core, and the directory has to stay inside the plugin.
* `settings` — the preferences the plugin owns. They become ordinary Admidio preferences when the
  plugin is loaded, get a row in every organization when it is installed and are removed again when
  it is uninstalled. Each one declares a `type` (`string`, `boolean`, `integer`, `enum` or `array`),
  a `default`, and a `label` and `description` that may be language keys. That is enough for Admidio
  to build the settings form by itself, so a plugin only writes a preferences presenter when it
  wants more than the manifest can express.

  A setting **without** a `label` is not put into that form. A preference the plugin owns is not
  necessarily one this form edits — the position of an overview widget is edited in the overview
  preferences, and a plugin may keep state of its own that nobody is meant to type into.

  An `enum` lists its permitted values, and may instead name them so that the generated form has
  something readable to show:

  ```json
  "values": ["ASC", "DESC"]
  "values": {"first_name": "PLG_HELLO_WORLD_ADDRESS_FIRST_NAME", "full_name": "…"}
  ```

  An `integer` may bound what the generated form offers with `min`, `max` and `step`. The value is
  stored as an integer either way; a bound only restricts the input field.

  ```json
  "hello_world_repeat": { "type": "integer", "default": 3, "min": 0, "max": 20, "step": 1 }
  ```

* `preferences` — where the settings of the plugin appear in the Admidio preferences. `section`
  names one of the tabs and `sequence` places the panel inside it:

  ```json
  "preferences": { "section": "overview_extensions", "sequence": 20 }
  ```

  The tabs are `system`, `login_security`, `user_management`, `communication`, `content_management`,
  `overview_extensions` and `extensions`. A plugin is not restricted to the last two: a module that
  used to be part of the Admidio core keeps its own tab once it becomes a plugin. A plugin that
  names no section, or one that no longer exists, lands in `extensions`.

## The entry file

`plugin.php` registers what the plugin contributes and returns nothing. There is no plugin class, no
interface and no base class. It runs inside the fully initialized Admidio request, so it can call
any public Admidio API — normally it registers hooks:

```php
Hooks::addFilter('page_headline', array(Greeting::class, 'decorateHeadline'), 20, 2, 'hello-world');
```

## The page

`modules/index.php` is a normal Admidio module file: `require_once` of `system/common.php`,
`admFuncVariableIsValid()`, `PagePresenter`, `FormPresenter`, services, `Entity`, `handleException()`.

It is always reachable at `plugins/hello-world/modules/index.php`. When the administrator switches on
the preference `plugin_module_pages`, Admidio additionally publishes it at
`modules/hello-world/index.php`. Never hardcode either form — ask `Plugin::getUrl('index.php')` which
one is live.

Only the files directly below `modules/` are entry points. Everything else in the plugin — the entry
file, the classes below `src/` — is refused if it is requested directly.

## Trying it out

1. Install the plugin in *Administration → Extensions*.
2. Open its page.
3. Switch on *Show the greeting in every headline* to see the hook change what the core produces.
4. Disable the plugin and open its page again: it is refused.
