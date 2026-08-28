<?php

namespace AdmidioPlugin\RandomPhoto\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\RandomPhoto\RandomPhoto;

/**
 * @brief Class with methods to present the preferences for the random photo plugin
 *
 * This class is used to present the preferences for the random photo plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

final class RandomPhotoPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.random-photo.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Generates the HTML of the form from the random photo plugin preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the random photo plugin preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = PluginRegistry::requireEnabled(RandomPhoto::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = $plugin->getSettingValues();

        $formRandomPhoto = new FormPresenter(
            'adm_preferences_form_random_photo',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'random_photo')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formRandomPhoto->addSelectBox(
            'random_photo_plugin_enabled',
            Language::translateIfTranslationStrId($settings['random_photo_plugin_enabled']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['random_photo_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['random_photo_plugin_enabled']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_char_per_word',
            Language::translateIfTranslationStrId($settings['random_photo_max_char_per_word']['label']),
            $values['random_photo_max_char_per_word'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['random_photo_max_char_per_word']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_width',
            Language::translateIfTranslationStrId($settings['random_photo_max_width']['label']),
            $values['random_photo_max_width'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['random_photo_max_width']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_height',
            Language::translateIfTranslationStrId($settings['random_photo_max_height']['label']),
            $values['random_photo_max_height'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['random_photo_max_height']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_albums',
            Language::translateIfTranslationStrId($settings['random_photo_albums']['label']),
            $values['random_photo_albums'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['random_photo_albums']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_album_photo_number',
            Language::translateIfTranslationStrId($settings['random_photo_album_photo_number']['label']),
            $values['random_photo_album_photo_number'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['random_photo_album_photo_number']['description'])
        );
        $formRandomPhoto->addCheckbox(
            'random_photo_show_album_link',
            Language::translateIfTranslationStrId($settings['random_photo_show_album_link']['label']),
            $values['random_photo_show_album_link'],
            array('helpTextId' => $settings['random_photo_show_album_link']['description'])
        );
        $formRandomPhoto->addSubmitButton(
            'adm_button_save_random_photo',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formRandomPhoto->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($formRandomPhoto);
        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}