<?php

namespace AnnouncementList\classes\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Infrastructure\Language;

use AnnouncementList\classes\AnnouncementList;
use Smarty\Smarty;

/**
 * @brief Class with methods to present the preferences for the announcement list plugin
 * 
 * This class is used to present the preferences for the announcement list plugin.
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

class AnnouncementListPreferencesPresenter
{
    /**
     * Generates the HTML of the form from the announcement preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the announcement preferences.
     * @throws Exception|\Smarty\Exception
     */
    public static function createAnnouncementListForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession, $gDb, $gCurrentUser;

        $pluginAnnouncementList = AnnouncementList::getInstance();
        $formValues = $pluginAnnouncementList::getPluginConfig();
        
        $formAnnouncementList = new FormPresenter(
            'adm_preferences_form_announcement_list',
            $pluginAnnouncementList::getPluginPath() . '/templates/preferences.plugin.announcement-list.tpl',
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
            Language::translateIfTranslationStrId($formValues['announcement_list_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['announcement_list_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['announcement_list_plugin_enabled']['description'])
        );
        $formAnnouncementList->addInput(
            'announcement_list_announcements_count',
            Language::translateIfTranslationStrId($formValues['announcement_list_announcements_count']['name']),
            $formValues['announcement_list_announcements_count']['value'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 20, 'step' => 1, 'helpTextId' => $formValues['announcement_list_announcements_count']['description'])
        );
         $formAnnouncementList->addInput(
            'announcement_list_show_preview_chars',
            Language::translateIfTranslationStrId($formValues['announcement_list_show_preview_chars']['name']),
            $formValues['announcement_list_show_preview_chars']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['announcement_list_show_preview_chars']['description'])
        );
        $formAnnouncementList->addCheckbox(
            'announcement_list_show_full_description',
            Language::translateIfTranslationStrId($formValues['announcement_list_show_full_description']['name']),
            $formValues['announcement_list_show_full_description']['value'],
            array('helpTextId' => $formValues['announcement_list_show_full_description']['description'])
        );
         $formAnnouncementList->addInput(
            'announcement_list_chars_before_linebreak',
            Language::translateIfTranslationStrId($formValues['announcement_list_chars_before_linebreak']['name']),
            $formValues['announcement_list_chars_before_linebreak']['value'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => $formValues['announcement_list_chars_before_linebreak']['description'])
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
            Language::translateIfTranslationStrId($formValues['announcement_list_displayed_categories']['name']),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['announcement_list_displayed_categories']['value'], 'showContextDependentFirstEntry' => false, 'helpTextId' => $formValues['announcement_list_displayed_categories']['description'], 'multiselect' => true, 'maximumSelectionNumber' => count($gCurrentUser->getAllVisibleCategories('ANN')))
        );
        $formAnnouncementList->addSubmitButton(
            'adm_button_save_announcement_list',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $formAnnouncementList->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formAnnouncementList);
        return $smarty->fetch($pluginAnnouncementList::getPluginPath() . '/templates/preferences.plugin.announcement-list.tpl');
    }
}