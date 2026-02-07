<?php

namespace EventList\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use EventList\classes\EventList;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the event list plugin
 *
 * This class is used to present the preferences for the event list plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class EventListPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the event preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the event preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createEventListForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentUser;

        $pluginEventList = EventList::getInstance();
        $formValues = $pluginEventList::getPluginConfig();
        
        $formEventList = new FormPresenter(
            'adm_preferences_form_event_list',
            $pluginEventList::getPluginPath() . '/templates/preferences.plugin.event-list.tpl',
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
            Language::translateIfTranslationStrId($formValues['event_list_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['event_list_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['event_list_plugin_enabled']['description'])
        );
        $formEventList->addInput(
            'event_list_events_count',
            Language::translateIfTranslationStrId($formValues['event_list_events_count']['name']),
            $formValues['event_list_events_count']['value'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $formValues['event_list_events_count']['description'])
        );
        $formEventList->addCheckbox(
            'event_list_show_event_date_end',
            Language::translateIfTranslationStrId($formValues['event_list_show_event_date_end']['name']),
            $formValues['event_list_show_event_date_end']['value'],
            array('helpTextId' => $formValues['event_list_show_event_date_end']['description'])
        );
        $formEventList->addInput(
            'event_list_show_preview_chars',
            Language::translateIfTranslationStrId($formValues['event_list_show_preview_chars']['name']),
            $formValues['event_list_show_preview_chars']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['event_list_show_preview_chars']['description'])
        );
        $formEventList->addCheckbox(
            'event_list_show_full_description',
            Language::translateIfTranslationStrId($formValues['event_list_show_full_description']['name']),
            $formValues['event_list_show_full_description']['value'],
            array('helpTextId' => $formValues['event_list_show_full_description']['description'])
        );
         $formEventList->addInput(
            'event_list_chars_before_linebreak',
            Language::translateIfTranslationStrId($formValues['event_list_chars_before_linebreak']['name']),
            $formValues['event_list_chars_before_linebreak']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['event_list_chars_before_linebreak']['description'])
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
            Language::translateIfTranslationStrId($formValues['event_list_displayed_categories']['name']),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['event_list_displayed_categories']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['event_list_displayed_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('ANN')))
        );
        $formEventList->addSubmitButton(
            'adm_button_save_event_list',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formEventList->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formEventList);
        return $smarty->fetch($pluginEventList::getPluginPath() . '/templates/preferences.plugin.event-list.tpl');
    }
}