<?php

namespace AdmidioPlugin\EventList\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\EventList\EventList;

/**
 * @brief Class with methods to present the preferences for the event list plugin
 *
 * This class is used to present the preferences for the event list plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

final class EventListPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.event-list.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Generates the HTML of the form from the event preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the event preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentUser;

        $plugin = PluginRegistry::requireEnabled(EventList::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = EventList::getConfig($plugin);
        
        $formEventList = new FormPresenter(
            'adm_preferences_form_event_list',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'event_list')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formEventList->addSelectBox(
            'event_list_plugin_enabled',
            Language::translateIfTranslationStrId($settings['event_list_plugin_enabled']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['event_list_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['event_list_plugin_enabled']['description'])
        );
        $formEventList->addInput(
            'event_list_events_count',
            Language::translateIfTranslationStrId($settings['event_list_events_count']['label']),
            $values['event_list_events_count'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $settings['event_list_events_count']['description'])
        );
        $formEventList->addCheckbox(
            'event_list_show_event_date_end',
            Language::translateIfTranslationStrId($settings['event_list_show_event_date_end']['label']),
            $values['event_list_show_event_date_end'],
            array('helpTextId' => $settings['event_list_show_event_date_end']['description'])
        );
        $formEventList->addInput(
            'event_list_show_preview_chars',
            Language::translateIfTranslationStrId($settings['event_list_show_preview_chars']['label']),
            $values['event_list_show_preview_chars'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['event_list_show_preview_chars']['description'])
        );
        $formEventList->addCheckbox(
            'event_list_show_full_description',
            Language::translateIfTranslationStrId($settings['event_list_show_full_description']['label']),
            $values['event_list_show_full_description'],
            array('helpTextId' => $settings['event_list_show_full_description']['description'])
        );
         $formEventList->addInput(
            'event_list_chars_before_linebreak',
            Language::translateIfTranslationStrId($settings['event_list_chars_before_linebreak']['label']),
            $values['event_list_chars_before_linebreak'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['event_list_chars_before_linebreak']['description'])
        );
        
        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));
        $sql = 'SELECT cat.cat_id, cat.cat_name
                FROM ' . TBL_EVENTS . ' AS evt
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    WHERE cat_id IN (' . $gDb->getQmForValues($catIdParams) . ')
            ORDER BY dat_timestamp_create DESC';
        $sqlData = array(
            'query' => $sql,
            'params' => array_merge($catIdParams)
        );

        $formEventList->addSelectBoxFromSql(
            'event_list_displayed_categories',
            Language::translateIfTranslationStrId($settings['event_list_displayed_categories']['label']),
            $gDb,
            $sqlData,
            array('defaultValue' => $values['event_list_displayed_categories'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['event_list_displayed_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('ANN')))
        );
        $formEventList->addSubmitButton(
            'adm_button_save_event_list',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formEventList->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($formEventList);
        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}