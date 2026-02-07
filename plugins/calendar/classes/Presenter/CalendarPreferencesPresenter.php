<?php

namespace Calendar\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use Calendar\classes\Calendar;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the calendar plugin
 *
 * This class is used to present the preferences for the calendar plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class CalendarPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the announcement preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the announcement preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createCalendarForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentUser;

        $pluginCalendar = Calendar::getInstance();
        $formValues = $pluginCalendar::getPluginConfig();

        $formCalendar = new FormPresenter(
            'adm_preferences_form_calendar',
            $pluginCalendar::getPluginPath() . '/templates/preferences.plugin.calendar.tpl',
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
            Language::translateIfTranslationStrId($formValues['calendar_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['calendar_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['calendar_plugin_enabled']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_events',
            Language::translateIfTranslationStrId($formValues['calendar_show_events']['name']),
            $formValues['calendar_show_events']['value'],
            array('helpTextId' => $formValues['calendar_show_events']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthdays',
            Language::translateIfTranslationStrId($formValues['calendar_show_birthdays']['name']),
            $formValues['calendar_show_birthdays']['value'],
            array('helpTextId' => $formValues['calendar_show_birthdays']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthdays_to_guests',
            Language::translateIfTranslationStrId($formValues['calendar_show_birthdays_to_guests']['name']),
            $formValues['calendar_show_birthdays_to_guests']['value'],
            array('helpTextId' => $formValues['calendar_show_birthdays_to_guests']['description'])
        );
        $formCalendar->addCheckbox(
            'calendar_show_birthday_icon',
            Language::translateIfTranslationStrId($formValues['calendar_show_birthday_icon']['name']),
            $formValues['calendar_show_birthday_icon']['value'],
            array('helpTextId' => $formValues['calendar_show_birthday_icon']['description'])
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_LASTNAME') . ', ' . $gL10n->get('SYS_FIRSTNAME'),
            '1' => $gL10n->get('SYS_FIRSTNAME'),
            '2' => $gL10n->get('SYS_LASTNAME')
        );
        $formCalendar->addSelectBox(
            'calendar_show_birthday_names',
            Language::translateIfTranslationStrId($formValues['calendar_show_birthday_names']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['calendar_show_birthday_names']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['calendar_show_birthday_names']['description'])
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
            Language::translateIfTranslationStrId($formValues['calendar_show_categories']['name']),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['calendar_show_categories']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['calendar_show_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('EVT')))
        );

        $formCalendar->addCheckbox(
            'calendar_show_categories_names',
            Language::translateIfTranslationStrId($formValues['calendar_show_categories_names']['name']),
            $formValues['calendar_show_categories_names']['value'],
            array('helpTextId' => $formValues['calendar_show_categories_names']['description'])
        );

        $selectBoxEntries = $pluginCalendar instanceof Calendar ? $pluginCalendar::getAvailableRoles() : array();

        $formCalendar->addSelectBox(
            'calendar_roles_view_plugin',
            Language::translateIfTranslationStrId($formValues['calendar_roles_view_plugin']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['calendar_roles_view_plugin']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['calendar_roles_view_plugin']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );
        $formCalendar->addSelectBox(
            'calendar_roles_sql',
            Language::translateIfTranslationStrId($formValues['calendar_roles_sql']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['calendar_roles_sql']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['calendar_roles_sql']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );

        $formCalendar->addSubmitButton(
            'adm_button_save_calendar',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formCalendar->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formCalendar);
        return $smarty->fetch($pluginCalendar::getPluginPath() . '/templates/preferences.plugin.calendar.tpl');
    }
}