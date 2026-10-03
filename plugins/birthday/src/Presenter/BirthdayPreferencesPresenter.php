<?php

namespace AdmidioPlugin\Birthday\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\Birthday\Birthday;

/**
 * @brief Class with methods to present the preferences for the birthday plugin
 *
 * The panel is declared by the entry file of the plugin and built here when the administrator opens
 * it. The labels and help texts come from the manifest, so every preference is described in exactly
 * one place.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class BirthdayPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.birthday.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Generates the HTML of the form from the birthday preferences and will return the complete HTML.
     * @param PreferencesPresenter $page The preferences page the panel is shown in.
     * @return string Returns the complete HTML of the form from the birthday preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = PluginRegistry::requireEnabled(Birthday::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = Birthday::getConfig($plugin);

        $form = new FormPresenter(
            'adm_preferences_form_birthday',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/preferences.php',
                array('mode' => 'save', 'panel' => 'birthday')
            ),
            null,
            array('class' => 'form-preferences')
        );
        $form->addSelectBox(
            'birthday_plugin_enabled',
            Language::translateIfTranslationStrId($settings['birthday_plugin_enabled']['label']),
            array(
                '0' => $gL10n->get('SYS_DISABLED'),
                '1' => $gL10n->get('SYS_ENABLED'),
                '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
            ),
            array('defaultValue' => $values['birthday_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_plugin_enabled']['description'])
        );
        $form->addCheckbox(
            'birthday_show_names_extern',
            Language::translateIfTranslationStrId($settings['birthday_show_names_extern']['label']),
            $values['birthday_show_names_extern'],
            array('helpTextId' => $settings['birthday_show_names_extern']['description'])
        );
        $form->addSelectBox(
            'birthday_show_names',
            Language::translateIfTranslationStrId($settings['birthday_show_names']['label']),
            array(
                '0' => $gL10n->get('SYS_FIRSTNAME') . ' ' . $gL10n->get('SYS_LASTNAME'),
                '1' => $gL10n->get('SYS_LASTNAME') . ', ' . $gL10n->get('SYS_FIRSTNAME'),
                '2' => $gL10n->get('SYS_FIRSTNAME'),
                '3' => $gL10n->get('SYS_USERNAME')
            ),
            array('defaultValue' => $values['birthday_show_names'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_show_names']['description'])
        );
        $form->addCheckbox(
            'birthday_show_age',
            Language::translateIfTranslationStrId($settings['birthday_show_age']['label']),
            $values['birthday_show_age'],
            array('helpTextId' => $settings['birthday_show_age']['description'])
        );
        $form->addInput(
            'birthday_show_age_salutation',
            Language::translateIfTranslationStrId($settings['birthday_show_age_salutation']['label']),
            $values['birthday_show_age_salutation'],
            array('type' => 'number', 'minNumber' => -1, 'step' => 1, 'helpTextId' => $settings['birthday_show_age_salutation']['description'])
        );
        $form->addCheckbox(
            'birthday_show_notice_none',
            Language::translateIfTranslationStrId($settings['birthday_show_notice_none']['label']),
            $values['birthday_show_notice_none'],
            array('helpTextId' => $settings['birthday_show_notice_none']['description'])
        );
        $form->addInput(
            'birthday_show_past',
            Language::translateIfTranslationStrId($settings['birthday_show_past']['label']),
            $values['birthday_show_past'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['birthday_show_past']['description'])
        );
        $form->addInput(
            'birthday_show_future',
            Language::translateIfTranslationStrId($settings['birthday_show_future']['label']),
            $values['birthday_show_future'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['birthday_show_future']['description'])
        );
        $form->addInput(
            'birthday_show_display_limit',
            Language::translateIfTranslationStrId($settings['birthday_show_display_limit']['label']),
            $values['birthday_show_display_limit'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['birthday_show_display_limit']['description'])
        );
        $form->addSelectBox(
            'birthday_show_email_extern',
            Language::translateIfTranslationStrId($settings['birthday_show_email_extern']['label']),
            array(
                '0' => $gL10n->get('SYS_NAME') . ' (' . $gL10n->get('SYS_VISITORS') . ')',
                '1' => $gL10n->get('SYS_NAME') . ' + ' . $gL10n->get('SYS_EMAIL'),
                '2' => $gL10n->get('SYS_NAME') . ' (' . $gL10n->get('SYS_VISITORS') . ' + ' . $gL10n->get('SYS_USERS') . ')'
            ),
            array('defaultValue' => $values['birthday_show_email_extern'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_show_email_extern']['description'])
        );

        $roles = Birthday::getAvailableRoles();

        $form->addSelectBox(
            'birthday_roles_view_plugin',
            Language::translateIfTranslationStrId($settings['birthday_roles_view_plugin']['label']),
            $roles,
            array('defaultValue' => $values['birthday_roles_view_plugin'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_roles_view_plugin']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($roles))
        );
        $form->addSelectBox(
            'birthday_roles_sql',
            Language::translateIfTranslationStrId($settings['birthday_roles_sql']['label']),
            $roles,
            array('defaultValue' => $values['birthday_roles_sql'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_roles_sql']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($roles))
        );
        $form->addSelectBox(
            'birthday_sort_sql',
            Language::translateIfTranslationStrId($settings['birthday_sort_sql']['label']),
            array('ASC' => 'ASC', 'DESC' => 'DESC'),
            array('defaultValue' => $values['birthday_sort_sql'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['birthday_sort_sql']['description'])
        );
        $form->addSubmitButton(
            'adm_button_save_birthday',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $form->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($form);

        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}
