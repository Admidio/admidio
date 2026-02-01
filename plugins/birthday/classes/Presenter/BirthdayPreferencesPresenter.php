<?php

namespace Birthday\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use Birthday\classes\Birthday;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the birthday plugin
 *
 * This class is used to present the preferences for the birthday plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class BirthdayPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the announcement preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the announcement preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createBirthdayForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $pluginBirthday = Birthday::getInstance();
        $formValues = $pluginBirthday::getPluginConfig();

        $formBirthday = new FormPresenter(
            'adm_preferences_form_birthday',
            $pluginBirthday::getPluginPath() . '/templates/preferences.plugin.birthday.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'birthday')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formBirthday->addSelectBox(
            'birthday_plugin_enabled',
            Language::translateIfTranslationStrId($formValues['birthday_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_plugin_enabled']['description'])
        );
        $formBirthday->addCheckbox(
            'birthday_show_names_extern',
            Language::translateIfTranslationStrId($formValues['birthday_show_names_extern']['name']),
            $formValues['birthday_show_names_extern']['value'],
            array('helpTextId' => $formValues['birthday_show_names_extern']['description'])
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_FIRSTNAME') . ' ' . $gL10n->get('SYS_LASTNAME'),
            '1' => $gL10n->get('SYS_LASTNAME') . ', ' . $gL10n->get('SYS_FIRSTNAME'),
            '2' => $gL10n->get('SYS_FIRSTNAME'),
            '3' => $gL10n->get('SYS_USERNAME')
        );
        $formBirthday->addSelectBox(
            'birthday_show_names',
            Language::translateIfTranslationStrId($formValues['birthday_show_names']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_show_names']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_show_names']['description'])
        );
        $formBirthday->addCheckbox(
            'birthday_show_age',
            Language::translateIfTranslationStrId($formValues['birthday_show_age']['name']),
            $formValues['birthday_show_age']['value'],
            array('helpTextId' => $formValues['birthday_show_age']['description'])
        );
        $formBirthday->addInput(
            'birthday_show_age_salutation',
            Language::translateIfTranslationStrId($formValues['birthday_show_age_salutation']['name']),
            $formValues['birthday_show_age_salutation']['value'],
            array('type' => 'number', 'minNumber' => -1, 'step' => 1, 'helpTextId' => $formValues['birthday_show_age_salutation']['description'])
        );
        $formBirthday->addCheckbox(
            'birthday_show_notice_none',
            Language::translateIfTranslationStrId($formValues['birthday_show_notice_none']['name']),
            $formValues['birthday_show_notice_none']['value'],
            array('helpTextId' => $formValues['birthday_show_notice_none']['description'])
        );
         $formBirthday->addInput(
            'birthday_show_past',
            Language::translateIfTranslationStrId($formValues['birthday_show_past']['name']),
            $formValues['birthday_show_past']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['birthday_show_past']['description'])
        );
         $formBirthday->addInput(
            'birthday_show_future',
            Language::translateIfTranslationStrId($formValues['birthday_show_future']['name']),
            $formValues['birthday_show_future']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['birthday_show_future']['description'])
        );
         $formBirthday->addInput(
            'birthday_show_display_limit',
            Language::translateIfTranslationStrId($formValues['birthday_show_display_limit']['name']),
            $formValues['birthday_show_display_limit']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['birthday_show_display_limit']['description'])
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_NAME') . ' (' . $gL10n->get('SYS_VISITORS') . ')',
            '1' => $gL10n->get('SYS_NAME') . ' + ' . $gL10n->get('SYS_EMAIL'),
            '2' => $gL10n->get('SYS_NAME') . ' (' . $gL10n->get('SYS_VISITORS') . ' + ' . $gL10n->get('SYS_USERS') . ')'
        );
        $formBirthday->addSelectBox(
            'birthday_show_email_extern',
            Language::translateIfTranslationStrId($formValues['birthday_show_email_extern']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_show_email_extern']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_show_email_extern']['description'])
        );

        $selectBoxEntries = $pluginBirthday instanceof Birthday ? $pluginBirthday::getAvailableRoles() : array();

        $formBirthday->addSelectBox(
            'birthday_roles_view_plugin',
            Language::translateIfTranslationStrId($formValues['birthday_roles_view_plugin']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_roles_view_plugin']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_roles_view_plugin']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );
        $formBirthday->addSelectBox(
            'birthday_roles_sql',
            Language::translateIfTranslationStrId($formValues['birthday_roles_sql']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_roles_sql']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_roles_sql']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );

        $selectBoxEntries = array(
            'ASC' => 'ASC',
            'DESC' => 'DESC'
        );
        $formBirthday->addSelectBox(
            'birthday_sort_sql',
            Language::translateIfTranslationStrId($formValues['birthday_sort_sql']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['birthday_sort_sql']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['birthday_sort_sql']['description'])
        );
        $formBirthday->addSubmitButton(
            'adm_button_save_birthday',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formBirthday->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formBirthday);
        return $smarty->fetch($pluginBirthday::getPluginPath() . '/templates/preferences.plugin.birthday.tpl');
    }
}