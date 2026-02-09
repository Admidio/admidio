<?php

namespace LatestDocumentsFiles\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use LatestDocumentsFiles\classes\LatestDocumentsFiles;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the latest documents & files plugin
 *
 * This class is used to present the preferences for the latest documents & files plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class LatestDocumentsFilesPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the latest documents & files preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the latest documents & files preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createLatestDocumentsFilesForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $pluginLatestDocumentsFiles = LatestDocumentsFiles::getInstance();
        $formValues = $pluginLatestDocumentsFiles::getPluginConfig();
        
        $formLatestDocumentsFiles = new FormPresenter(
            'adm_preferences_form_latest_documents_files',
            $pluginLatestDocumentsFiles::getPluginPath() . '/templates/preferences.plugin.latest-documents-files.tpl',
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
            Language::translateIfTranslationStrId($formValues['latest_documents_files_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['latest_documents_files_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['latest_documents_files_plugin_enabled']['description'])
        );
        $formLatestDocumentsFiles->addInput(
            'latest_documents_files_files_count',
            Language::translateIfTranslationStrId($formValues['latest_documents_files_files_count']['name']),
            $formValues['latest_documents_files_files_count']['value'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $formValues['latest_documents_files_files_count']['description'])
        );
        $formLatestDocumentsFiles->addCheckbox(
            'latest_documents_files_show_upload_timestamp',
            Language::translateIfTranslationStrId($formValues['latest_documents_files_show_upload_timestamp']['name']),
            $formValues['latest_documents_files_show_upload_timestamp']['value'],
            array('helpTextId' => $formValues['latest_documents_files_show_upload_timestamp']['description'])
        );
        $formLatestDocumentsFiles->addInput(
            'latest_documents_files_max_chars_filename',
            Language::translateIfTranslationStrId($formValues['latest_documents_files_max_chars_filename']['name']),
            $formValues['latest_documents_files_max_chars_filename']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['latest_documents_files_max_chars_filename']['description'])
        );
        $formLatestDocumentsFiles->addSubmitButton(
            'adm_button_save_latest_documents_files',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formLatestDocumentsFiles->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formLatestDocumentsFiles);
        return $smarty->fetch($pluginLatestDocumentsFiles::getPluginPath() . '/templates/preferences.plugin.latest-documents-files.tpl');
    }
}