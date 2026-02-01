<?php

namespace RandomPhoto\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use RandomPhoto\classes\RandomPhoto;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the random photo plugin
 *
 * This class is used to present the preferences for the random photo plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class RandomPhotoPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the random photo plugin preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the random photo plugin preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createRandomPhotoForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $pluginRandomPhoto = RandomPhoto::getInstance();
        $formValues = $pluginRandomPhoto::getPluginConfig();

        $formRandomPhoto = new FormPresenter(
            'adm_preferences_form_random_photo',
            $pluginRandomPhoto::getPluginPath() . '/templates/preferences.plugin.random-photo.tpl',
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
            Language::translateIfTranslationStrId($formValues['random_photo_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['random_photo_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['random_photo_plugin_enabled']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_char_per_word',
            Language::translateIfTranslationStrId($formValues['random_photo_max_char_per_word']['name']),
            $formValues['random_photo_max_char_per_word']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['random_photo_max_char_per_word']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_width',
            Language::translateIfTranslationStrId($formValues['random_photo_max_width']['name']),
            $formValues['random_photo_max_width']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['random_photo_max_width']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_max_height',
            Language::translateIfTranslationStrId($formValues['random_photo_max_height']['name']),
            $formValues['random_photo_max_height']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['random_photo_max_height']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_albums',
            Language::translateIfTranslationStrId($formValues['random_photo_albums']['name']),
            $formValues['random_photo_albums']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['random_photo_albums']['description'])
        );
        $formRandomPhoto->addInput(
            'random_photo_album_photo_number',
            Language::translateIfTranslationStrId($formValues['random_photo_album_photo_number']['name']),
            $formValues['random_photo_album_photo_number']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['random_photo_album_photo_number']['description'])
        );
        $formRandomPhoto->addCheckbox(
            'random_photo_show_album_link',
            Language::translateIfTranslationStrId($formValues['random_photo_show_album_link']['name']),
            $formValues['random_photo_show_album_link']['value'],
            array('helpTextId' => $formValues['random_photo_show_album_link']['description'])
        );
        $formRandomPhoto->addSubmitButton(
            'adm_button_save_random_photo',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formRandomPhoto->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formRandomPhoto);
        return $smarty->fetch($pluginRandomPhoto::getPluginPath() . '/templates/preferences.plugin.random-photo.tpl');
    }
}