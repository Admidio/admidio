# Hello World — the example Admidio plugin

The smallest plugin that still uses every convention. Copy this directory, rename it, and you have a
working plugin.

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
  it is uninstalled.

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
