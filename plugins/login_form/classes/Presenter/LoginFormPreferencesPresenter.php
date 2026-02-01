<?php

namespace LoginForm\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use LoginForm\classes\LoginForm;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the login form plugin
 *
 * This class is used to present the preferences for the login form plugin.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class LoginFormPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the login form preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the login form preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createLoginFormForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $pluginLoginForm = LoginForm::getInstance();
        $formValues = $pluginLoginForm::getPluginConfig();

        $formLoginForm = new FormPresenter(
            'adm_preferences_form_login_form',
            $pluginLoginForm::getPluginPath() . '/templates/preferences.plugin.login-form.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'login_form')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formLoginForm->addSelectBox(
            'login_form_plugin_enabled',
            Language::translateIfTranslationStrId($formValues['login_form_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['login_form_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['login_form_plugin_enabled']['description'])
        );
        $formLoginForm->addCheckbox(
            'login_form_show_register_link',
            Language::translateIfTranslationStrId($formValues['login_form_show_register_link']['name']),
            $formValues['login_form_show_register_link']['value'],
            array('helpTextId' => $formValues['login_form_show_register_link']['description'])
        );
        $formLoginForm->addCheckbox(
            'login_form_show_email_link',
            Language::translateIfTranslationStrId($formValues['login_form_show_email_link']['name']),
            $formValues['login_form_show_email_link']['value'],
            array('helpTextId' => $formValues['login_form_show_email_link']['description'])
        );
        $formLoginForm->addCheckbox(
            'login_form_show_logout_link',
            Language::translateIfTranslationStrId($formValues['login_form_show_logout_link']['name']),
            $formValues['login_form_show_logout_link']['value'],
            array('helpTextId' => $formValues['login_form_show_logout_link']['description'])
        );
        $formLoginForm->addCheckbox(
            'login_form_enable_ranks',
            Language::translateIfTranslationStrId($formValues['login_form_enable_ranks']['name']),
            $formValues['login_form_enable_ranks']['value'],
            array('helpTextId' => $formValues['login_form_enable_ranks']['description'])
        );
        // these inputs will never be displayed, but they are used to save the values later
        // register an input filed for the ranks array and add the values from the plugin config
        $formLoginForm->addInput(
            'login_form_ranks_keys',
            '',
            implode(',', array_keys($formValues['login_form_ranks']['value'])),
            array('type' => 'text', 'property' => FormPresenter::FIELD_HIDDEN)
        );
        // register an input filed for the ranks keys array and add the values from the plugin config
        $formLoginForm->addInput(
            'login_form_ranks',
            '',
            implode(',', $formValues['login_form_ranks']['value']),
            array('type' => 'text', 'property' => FormPresenter::FIELD_HIDDEN)
        );
        $content = '';
        // create a table with the ranks
        $content .= '<table class="table table-striped table-bordered">';
        $content .= '<thead><tr><th style="width: 20%">' . $gL10n->get('PLG_LOGIN_FORM_NUMBER_OF_LOGINS') . '</th><th>' . $gL10n->get('PLG_LOGIN_FORM_MEMBERRANK') . '</th></tr></thead>';
        $content .= '<tbody>';
        $id = 0;
        foreach ($formValues['login_form_ranks']['value'] as $numLogins => $rankName) {
            $content .= '<tr id ="login_form_ranks_row_' . $id . '">';
            $content .= '<td><input id="login_form_ranks_key_' . $id . '" name="login_form_ranks_key_' . $id . '" class="form-control focus-ring" type="number" min="0" value="' .$numLogins . '"></td>';
            $content .= '<td><input id="login_form_ranks_value_' . $id . '" name="login_form_ranks_value_' . $id . '" class="form-control focus-ring" type="text" value="' .$rankName . '"></td>';
            $content .= '</tr>';
            $id++;
        }
        $content .= '</tbody>';
        $content .= '<tfoot>
                    <tr id="table_row_button">
                        <td colspan="2">
                            <a class="icon-text-link" href="javascript:void(0);" onclick="javascript:addLoginFormRanksRow();">
                                <i class="bi bi-plus-circle-fill"></i> ' . $gL10n->get('SYS_ADD_ENTRY') . '
                            </a>
                        </td>
                    </tr>';
        $content .= '</tfoot></table>';
        $formLoginForm->addCustomContent(
            'login_form_ranks_table',
            Language::translateIfTranslationStrId($formValues['login_form_ranks']['name']),
            $content,
            array('helpTextId' => $formValues['login_form_ranks']['description'])
        );

        $formLoginForm->addSubmitButton(
            'adm_button_save_login_form',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formLoginForm->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formLoginForm);
        return $smarty->fetch($pluginLoginForm::getPluginPath() . '/templates/preferences.plugin.login-form.tpl');
    }
}