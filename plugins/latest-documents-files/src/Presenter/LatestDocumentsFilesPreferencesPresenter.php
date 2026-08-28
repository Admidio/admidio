<?php

namespace AdmidioPlugin\LatestDocumentsFiles\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\LatestDocumentsFiles\LatestDocumentsFiles;

/**
 * @brief Class with methods to present the preferences for the latest documents & files plugin
 *
 * This class is used to present the preferences for the latest documents & files plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

final class LatestDocumentsFilesPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.latest-documents-files.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Generates the HTML of the form from the latest documents & files preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the latest documents & files preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = PluginRegistry::requireEnabled(LatestDocumentsFiles::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = $plugin->getSettingValues();
        
        $formLatestDocumentsFiles = new FormPresenter(
            'adm_preferences_form_latest_documents_files',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'latest_documents_files')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formLatestDocumentsFiles->addSelectBox(
            'latest_documents_files_plugin_enabled',
            Language::translateIfTranslationStrId($settings['latest_documents_files_plugin_enabled']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['latest_documents_files_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['latest_documents_files_plugin_enabled']['description'])
        );
        $formLatestDocumentsFiles->addInput(
            'latest_documents_files_files_count',
            Language::translateIfTranslationStrId($settings['latest_documents_files_files_count']['label']),
            $values['latest_documents_files_files_count'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $settings['latest_documents_files_files_count']['description'])
        );
        $formLatestDocumentsFiles->addCheckbox(
            'latest_documents_files_show_upload_timestamp',
            Language::translateIfTranslationStrId($settings['latest_documents_files_show_upload_timestamp']['label']),
            $values['latest_documents_files_show_upload_timestamp'],
            array('helpTextId' => $settings['latest_documents_files_show_upload_timestamp']['description'])
        );
        $formLatestDocumentsFiles->addInput(
            'latest_documents_files_max_chars_filename',
            Language::translateIfTranslationStrId($settings['latest_documents_files_max_chars_filename']['label']),
            $values['latest_documents_files_max_chars_filename'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['latest_documents_files_max_chars_filename']['description'])
        );
        $formLatestDocumentsFiles->addSubmitButton(
            'adm_button_save_latest_documents_files',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formLatestDocumentsFiles->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($formLatestDocumentsFiles);
        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}