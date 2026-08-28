<?php

namespace AdmidioPlugin\HelloWorld;

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
     * The greeting, as the administrator configured it. The preferences of a plugin are ordinary
     * Admidio preferences from the moment the plugin is loaded, so they are read like any other.
     * @return string
     */
    public static function getText(): string
    {
        global $gSettingsManager, $gCurrentUser, $gValidLogin, $gL10n;

        // The language files of a plugin are added to the search path when it is loaded, so its own
        // keys are read with $gL10n->get() like every other string.
        $name = $gValidLogin
            ? (string)$gCurrentUser->getValue('FIRST_NAME')
            : $gL10n->get('PLG_HELLO_WORLD_VISITOR');

        return $gSettingsManager->getString('hello_world_greeting') . ', ' . $name . '!';
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
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('hello_world_decorate_headline')) {
            return $headline;
        }

        return $headline . ' - ' . self::getText();
    }
}
