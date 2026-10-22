<?php

namespace AdmidioPlugin\HelloWorld;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\UI\Presenter\PagePresenter;

/**
 * The one piece of logic the example plugin has.
 *
 * A plugin owns its own namespace, declared in plugin.json as
 * **"autoload": { "AdmidioPlugin\\HelloWorld\\": "src/" }**. The Admidio core namespace is
 * reserved, and every mapped directory has to stay inside the plugin, so a plugin can never shadow a
 * core class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Greeting
{
    /**
     * The directory of the plugin, which is its ID and its only identity.
     */
    public const PLUGIN_ID = 'hello-world';

    /**
     * The greeting, as the administrator configured it.
     *
     * The settings are read through the plugin and not through $gSettingsManager, which is what a
     * plugin should do: registering a setting does not create its row, so a version that adds one
     * has no stored value for it until the administrator updates the plugin. getSettingValues()
     * answers the default the manifest declares until then, in the type the manifest declares,
     * while $gSettingsManager->getString() would throw for a name it has no row for.
     * @return string
     */
    public static function getText(): string
    {
        global $gCurrentUser, $gValidLogin, $gL10n;

        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return '';
        }
        $settings = $plugin->getSettingValues();

        // The language files of a plugin are added to the search path when it is loaded, so its own
        // keys are read with $gL10n->get() like every other string.
        if (!$gValidLogin) {
            $name = $gL10n->get('PLG_HELLO_WORLD_VISITOR');
        } else {
            $name = match ($settings['hello_world_address'] ?? '') {
                'full_name' => $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'),
                'login_name' => $gCurrentUser->getValue('usr_login_name'),
                default => $gCurrentUser->getValue('FIRST_NAME')
            };
        }

        return (string)($settings['hello_world_greeting'] ?? '') . ', ' . (string)$name . '!';
    }

    /**
     * Callback of the **page_headline** filter. A filter receives the value and returns the value the
     * next callback sees, so a callback that does not want to change anything returns it unchanged.
     * @param string $headline The headline built so far.
     * @param PagePresenter $page The page the headline belongs to.
     * @return string
     */
    public static function decorateHeadline(string $headline, PagePresenter $page): string
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null || !($plugin->getSettingValues()['hello_world_decorate_headline'] ?? false)) {
            return $headline;
        }

        return $headline . ' - ' . self::getText();
    }
}
