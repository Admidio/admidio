<?php

namespace AdmidioPlugin\Calendar\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\Calendar\Calendar;

/**
 * @brief Class with methods to present the preferences for the calendar plugin
 *
 * This class is used to present the preferences for the calendar plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

final class CalendarPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.calendar.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Generates the HTML of the form from the announcement preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the announcement preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentUser;

        $plugin = PluginRegistry::requireEnabled(Calendar::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = Calendar::getConfig($plugin);

        $formCalendar = new FormPresenter(
            'adm_preferences_form_calendar',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'calendar')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formCalendar->addSelectBox(
            'calendar_plugin_enabled',
            Language::translateIfTranslationStrId($settings['calendar_plugin_enabled']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['calendar_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['calendar_plugin_enabled']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_events',
            Language::translateIfTranslationStrId($settings['calendar_show_events']['label']),
            $values['calendar_show_events'],
            array('helpTextId' => $settings['calendar_show_events']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthdays',
            Language::translateIfTranslationStrId($settings['calendar_show_birthdays']['label']),
            $values['calendar_show_birthdays'],
            array('helpTextId' => $settings['calendar_show_birthdays']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthdays_to_guests',
            Language::translateIfTranslationStrId($settings['calendar_show_birthdays_to_guests']['label']),
            $values['calendar_show_birthdays_to_guests'],
            array('helpTextId' => $settings['calendar_show_birthdays_to_guests']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthday_icon',
            Language::translateIfTranslationStrId($settings['calendar_show_birthday_icon']['label']),
            $values['calendar_show_birthday_icon'],
            array('helpTextId' => $settings['calendar_show_birthday_icon']['description'])
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_LASTNAME') . ', ' . $gL10n->get('SYS_FIRSTNAME'),
            '1' => $gL10n->get('SYS_FIRSTNAME'),
            '2' => $gL10n->get('SYS_LASTNAME')
        );
        $formCalendar->addSelectBox(
            'calendar_show_birthday_names',
            Language::translateIfTranslationStrId($settings['calendar_show_birthday_names']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['calendar_show_birthday_names'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['calendar_show_birthday_names']['description'])
        );

        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));
        $sql = 'SELECT cat.cat_id, cat.cat_name
                FROM ' . TBL_EVENTS . ' AS evt
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    WHERE cat_id IN (' . $gDb->getQmForValues($catIdParams) . ')
            ORDER BY evt.dat_timestamp_create DESC';
        $sqlData = array(
            'query' => $sql,
            'params' => $catIdParams
        );

        $formCalendar->addSelectBoxFromSql(
            'calendar_show_categories',
            Language::translateIfTranslationStrId($settings['calendar_show_categories']['label']),
            $gDb,
            $sqlData,
            array('defaultValue' => $values['calendar_show_categories'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['calendar_show_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('EVT')))
        );

        $formCalendar->addCheckbox(
            'calendar_show_categories_names',
            Language::translateIfTranslationStrId($settings['calendar_show_categories_names']['label']),
            $values['calendar_show_categories_names'],
            array('helpTextId' => $settings['calendar_show_categories_names']['description'])
        );

        $selectBoxEntries = Calendar::getAvailableRoles();

        $formCalendar->addSelectBox(
            'calendar_roles_view_plugin',
            Language::translateIfTranslationStrId($settings['calendar_roles_view_plugin']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['calendar_roles_view_plugin'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['calendar_roles_view_plugin']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );
        $formCalendar->addSelectBox(
            'calendar_roles_sql',
            Language::translateIfTranslationStrId($settings['calendar_roles_sql']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['calendar_roles_sql'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['calendar_roles_sql']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );

        $formCalendar->addSubmitButton(
            'adm_button_save_calendar',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formCalendar->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($formCalendar);
        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}