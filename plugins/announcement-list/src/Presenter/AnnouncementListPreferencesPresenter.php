<?php

namespace AdmidioPlugin\AnnouncementList\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\AnnouncementList\AnnouncementList;

/**
 * @brief Class with methods to present the preferences for the announcement list plugin
 * 
 * This class is used to present the preferences for the announcement list plugin.
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

final class AnnouncementListPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.announcement-list.tpl';

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

        $plugin = PluginRegistry::requireEnabled(AnnouncementList::PLUGIN_ID);
        $settings = $plugin->settings;
        $values = AnnouncementList::getConfig($plugin);
        
        $formAnnouncementList = new FormPresenter(
            'adm_preferences_form_announcement_list',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'announcement_list')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formAnnouncementList->addSelectBox(
            'announcement_list_plugin_enabled',
            Language::translateIfTranslationStrId($settings['announcement_list_plugin_enabled']['label']),
            $selectBoxEntries,
            array('defaultValue' => $values['announcement_list_plugin_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['announcement_list_plugin_enabled']['description'])
        );
        $formAnnouncementList->addInput(
            'announcement_list_announcements_count',
            Language::translateIfTranslationStrId($settings['announcement_list_announcements_count']['label']),
            $values['announcement_list_announcements_count'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $settings['announcement_list_announcements_count']['description'])
        );
         $formAnnouncementList->addInput(
            'announcement_list_show_preview_chars',
            Language::translateIfTranslationStrId($settings['announcement_list_show_preview_chars']['label']),
            $values['announcement_list_show_preview_chars'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['announcement_list_show_preview_chars']['description'])
        );
        $formAnnouncementList->addCheckbox(
            'announcement_list_show_full_description',
            Language::translateIfTranslationStrId($settings['announcement_list_show_full_description']['label']),
            $values['announcement_list_show_full_description'],
            array('helpTextId' => $settings['announcement_list_show_full_description']['description'])
        );
         $formAnnouncementList->addInput(
            'announcement_list_chars_before_linebreak',
            Language::translateIfTranslationStrId($settings['announcement_list_chars_before_linebreak']['label']),
            $values['announcement_list_chars_before_linebreak'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $settings['announcement_list_chars_before_linebreak']['description'])
        );
        
        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('ANN'));
        $sql = 'SELECT cat.cat_id, cat.cat_name
                FROM ' . TBL_ANNOUNCEMENTS . ' AS ann
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    WHERE cat_id IN (' . $gDb->getQmForValues($catIdParams) . ')
            ORDER BY ann_timestamp_create DESC';
        $sqlData = array(
            'query' => $sql,
            'params' => $catIdParams
        );

        $formAnnouncementList->addSelectBoxFromSql(
            'announcement_list_displayed_categories',
            Language::translateIfTranslationStrId($settings['announcement_list_displayed_categories']['label']),
            $gDb,
            $sqlData,
            array('defaultValue' => $values['announcement_list_displayed_categories'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $settings['announcement_list_displayed_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('ANN')))
        );
        $formAnnouncementList->addSubmitButton(
            'adm_button_save_announcement_list',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formAnnouncementList->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($formAnnouncementList);
        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}