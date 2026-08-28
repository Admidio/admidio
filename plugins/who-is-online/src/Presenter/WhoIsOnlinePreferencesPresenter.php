<?php

namespace AdmidioPlugin\WhoIsOnline\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\WhoIsOnline\WhoIsOnline;

/**
 * The preferences panel of the who-is-online plugin.
 *
 * The panel is declared by the entry file of the plugin and built here when the administrator opens
 * it. The labels and help texts come from the manifest, so every preference is described in exactly
 * one place.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class WhoIsOnlinePreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.who-is-online.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Build the preferences form of the plugin.
     * @param PreferencesPresenter $page The preferences page the panel is shown in.
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = PluginRegistry::requireEnabled(WhoIsOnline::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = $plugin->getSettingValues();

        $form = new FormPresenter(
            'adm_preferences_form_who_is_online',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/preferences.php',
                array('mode' => 'save', 'panel' => 'who_is_online')
            ),
            null,
            array('class' => 'form-preferences')
        );

        $form->addSelectBox(
            'who_is_online_plugin_enabled',
            Language::translateIfTranslationStrId($settings['who_is_online_plugin_enabled']['label']),
            array(
                '0' => $gL10n->get('SYS_DISABLED'),
                '1' => $gL10n->get('SYS_ENABLED'),
                '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
            ),
            array(
                'defaultValue' => $values['who_is_online_plugin_enabled'],
                'showContextDependentFirstEntry' => false,
                'helpTextId' => $settings['who_is_online_plugin_enabled']['description']
            )
        );
        $form->addInput(
            'who_is_online_time_still_active',
            Language::translateIfTranslationStrId($settings['who_is_online_time_still_active']['label']),
            $values['who_is_online_time_still_active'],
            array(
                'type' => 'number',
                'minNumber' => 0,
                'step' => 1,
                'helpTextId' => $settings['who_is_online_time_still_active']['description']
            )
        );
        $form->addCheckbox(
            'who_is_online_show_visitors',
            Language::translateIfTranslationStrId($settings['who_is_online_show_visitors']['label']),
            $values['who_is_online_show_visitors'],
            array('helpTextId' => $settings['who_is_online_show_visitors']['description'])
        );
        $form->addSelectBox(
            'who_is_online_show_members_to_visitors',
            Language::translateIfTranslationStrId($settings['who_is_online_show_members_to_visitors']['label']),
            array(
                '0' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_1'),
                '1' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_2'),
                '2' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_3')
            ),
            array(
                'defaultValue' => $values['who_is_online_show_members_to_visitors'],
                'showContextDependentFirstEntry' => false,
                'helpTextId' => $settings['who_is_online_show_members_to_visitors']['description']
            )
        );
        $form->addCheckbox(
            'who_is_online_show_self',
            Language::translateIfTranslationStrId($settings['who_is_online_show_self']['label']),
            $values['who_is_online_show_self'],
            array('helpTextId' => $settings['who_is_online_show_self']['description'])
        );
        $form->addCheckbox(
            'who_is_online_show_users_side_by_side',
            Language::translateIfTranslationStrId($settings['who_is_online_show_users_side_by_side']['label']),
            $values['who_is_online_show_users_side_by_side'],
            array('helpTextId' => $settings['who_is_online_show_users_side_by_side']['description'])
        );
        $form->addSubmitButton(
            'adm_button_save_who_is_online',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $form->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($form);

        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}
