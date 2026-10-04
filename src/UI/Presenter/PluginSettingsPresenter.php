<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief The preferences panel of a plugin that declared settings but no panel of its own.
 *
 * A plugin describes its settings in the manifest with a type, a label and a description, and the
 * loader registers them as ordinary Admidio preferences. Everything a form needs is therefore
 * already declared, and this class turns that declaration into the form. A plugin only has to write
 * a presenter when it wants more than its manifest can express - readable names for the values of
 * an enum, a role selection, a live preview - and the panel it registers itself takes precedence.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginSettingsPresenter
{
    /**
     * The template that arranges the generated controls.
     */
    public const TEMPLATE = 'preferences/preferences.plugin.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Build the settings form of a plugin from the settings its manifest declares.
     * @param Plugin $plugin The plugin whose settings are edited.
     * @param string $panel ID of the preferences panel the form is shown in and saved to.
     * @param PreferencesPresenter $page The preferences page the panel belongs to.
     * @return string The HTML of the form.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(Plugin $plugin, string $panel, PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $values = $plugin->getSettingValues();

        $form = new FormPresenter(
            'adm_preferences_form_' . $panel,
            self::TEMPLATE,
            SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_MODULES . '/preferences.php',
                array('mode' => 'save', 'panel' => $panel)
            ),
            null,
            array('class' => 'form-preferences')
        );

        foreach ($plugin->settings as $name => $definition) {
            /*
             * A preference the plugin owns is not necessarily one this form edits. The position of
             * an overview widget belongs to the overview preferences, and a plugin may keep state
             * of its own in a preference that nobody is meant to type into. Both are declared
             * without a label, which is also what makes them impossible to put in a form.
             */
            if ($definition['label'] === '') {
                continue;
            }

            self::addControl($form, $name, $definition, $values[$name] ?? $definition['default']);
        }

        $form->addSubmitButton(
            'adm_button_save_' . $panel,
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $page->getSmartyTemplate();
        $form->addToSmarty($smarty);
        $gCurrentSession->addFormObject($form);

        return $smarty->fetch(self::TEMPLATE);
    }

    /**
     * Add the control that edits one declared setting.
     *
     * A setting whose type the manifest cannot describe well enough for a form - a list - is shown
     * as a comma-separated text field, which is how it is stored.
     * @param FormPresenter $form
     * @param string $name Name of the preference.
     * @param array<string,mixed> $definition The declaration from the manifest.
     * @param mixed $value The value the preference currently has.
     * @return void
     * @throws Exception
     */
    private static function addControl(FormPresenter $form, string $name, array $definition, mixed $value): void
    {
        $label = Language::translateIfTranslationStrId((string)($definition['label'] ?? $name));
        $options = array();
        if (($definition['description'] ?? '') !== '') {
            $options['helpTextId'] = $definition['description'];
        }

        switch ($definition['type']) {
            case 'bool':
            case 'boolean':
                $form->addCheckbox($name, $label, (bool)$value, $options);
                break;

            case 'enum':
                /*
                 * A manifest that names its values gets those names in the select box; one that
                 * only lists them shows the values themselves, because there is nothing better.
                 */
                $labels = $definition['valueLabels'] ?? array();
                $entries = array();
                foreach ($definition['values'] as $entry) {
                    $entry = (string)$entry;
                    $entries[$entry] = isset($labels[$entry])
                        ? Language::translateIfTranslationStrId($labels[$entry]) : $entry;
                }
                $options['defaultValue'] = (string)$value;
                $options['showContextDependentFirstEntry'] = false;
                $form->addSelectBox($name, $label, $entries, $options);
                break;

            case 'int':
            case 'integer':
                $options['type'] = 'number';
                // A bound the manifest does not declare stays null, which addInput() drops again.
                $options['minNumber'] = $definition['min'] ?? null;
                $options['maxNumber'] = $definition['max'] ?? null;
                $options['step'] = $definition['step'] ?? null;
                $form->addInput($name, $label, (string)$value, $options);
                break;

            default:
                $form->addInput($name, $label, is_array($value) ? implode(',', $value) : (string)$value, $options);
                break;
        }
    }
}
