<?php

namespace WhoIsOnline\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use WhoIsOnline\classes\WhoIsOnline;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the  who is online plugin
 *
 * This class is used to present the preferences for the who is online plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class WhoIsOnlinePreferencesPresenter
{
    /**
     * Generates the HTML of the form from the who is online preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the who is online preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createWhoIsOnlineForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $pluginWhoIsOnline = WhoIsOnline::getInstance();
        $formValues = $pluginWhoIsOnline::getPluginConfig();

        $formWhoIsOnline = new FormPresenter(
            'adm_preferences_form_who_is_online',
            $pluginWhoIsOnline::getPluginPath() . '/templates/preferences.plugin.who-is-online.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'who_is_online')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formWhoIsOnline->addSelectBox(
            'who_is_online_plugin_enabled',
            Language::translateIfTranslationStrId($formValues['who_is_online_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['who_is_online_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['who_is_online_plugin_enabled']['description'])
        );
        $formWhoIsOnline->addInput(
            'who_is_online_time_still_active',
            Language::translateIfTranslationStrId($formValues['who_is_online_time_still_active']['name']),
            $formValues['who_is_online_time_still_active']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['who_is_online_time_still_active']['description'])
        );
        $formWhoIsOnline->addCheckbox(
            'who_is_online_show_visitors',
            Language::translateIfTranslationStrId($formValues['who_is_online_show_visitors']['name']),
            $formValues['who_is_online_show_visitors']['value'],
            array('helpTextId' => $formValues['who_is_online_show_visitors']['description'])
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_1'),
            '1' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_2'),
            '2' => $gL10n->get('PLG_WHO_IS_ONLINE_PREFERENCES_SHOW_MEMBERS_TO_VISITORS_SELECTION_3')
        );
        $formWhoIsOnline->addSelectBox(
            'who_is_online_show_members_to_visitors',
            Language::translateIfTranslationStrId($formValues['who_is_online_show_members_to_visitors']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['who_is_online_show_members_to_visitors']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['who_is_online_show_members_to_visitors']['description'])
        );
        $formWhoIsOnline->addCheckbox(
            'who_is_online_show_self',
            Language::translateIfTranslationStrId($formValues['who_is_online_show_self']['name']),
            $formValues['who_is_online_show_self']['value'],
            array('helpTextId' => $formValues['who_is_online_show_self']['description'])
        );
        $formWhoIsOnline->addCheckbox(
            'who_is_online_show_users_side_by_side',
            Language::translateIfTranslationStrId($formValues['who_is_online_show_users_side_by_side']['name']),
            $formValues['who_is_online_show_users_side_by_side']['value'],
            array('helpTextId' => $formValues['who_is_online_show_users_side_by_side']['description'])
        );
        $formWhoIsOnline->addSubmitButton(
            'adm_button_save_who_is_online',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formWhoIsOnline->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formWhoIsOnline);
        return $smarty->fetch($pluginWhoIsOnline::getPluginPath() . '/templates/preferences.plugin.who-is-online.tpl');
    }
}