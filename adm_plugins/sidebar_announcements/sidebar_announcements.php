<?php
/**
 ***********************************************************************************************
 * Sidebar Announcements
 *
 * Plugin that lists the latest announcements in a slim interface and
 * can thus be ideally used in a sidebar
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(__DIR__, 2);
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'int');
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to', 'date');

// set default values if no value has been stored in the config.php
if (!isset($plg_announcements_count) || !is_numeric($plg_announcements_count)) {
    $plg_announcements_count = 2;
}

if (!isset($plg_show_preview) || !is_numeric($plg_show_preview)) {
    $plg_show_preview = 70;
}

if (!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription)) {
    $plgShowFullDescription = 0;
}

if (isset($plg_link_target)) {
    $plg_link_target = strip_tags($plg_link_target);
} else {
    $plg_link_target = '_self';
}

if (!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word)) {
    $plg_max_char_per_word = 0;
}

if (!isset($plg_categories)) {
    $plg_categories = array();
}

if (!isset($plg_show_headline) || !is_numeric($plg_show_headline)) {
    $plg_show_headline = 1;
}

if (!isset($plg_headline) || $plg_headline === '') {
    $plg_headline = $gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_HEADLINE');
} elseif (Language::isTranslationStringId($plg_headline)) {
    // if text is a translation-id then translate it
    $plg_headline = $gL10n->get($plg_headline);
}

if ($gSettingsManager->getInt('enable_announcements_module') > 0) {
    // create announcements object
    $plgAnnouncements = new ModuleAnnouncements();
    $plgAnnouncements->setParameter('cat_id', $getCatId);
    $plgAnnouncements->setDateRange($getDateFrom, $getDateTo);
    $plgAnnouncements->setCategoriesNames($plg_categories);

    echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';

    if ($plg_show_headline === 1) {
        echo '<h3>'.$plg_headline.'</h3>';
    }

    if ($gSettingsManager->getInt('enable_announcements_module') === 1
    || ($gSettingsManager->getInt('enable_announcements_module') === 2 && $gValidLogin)) {
        if ($plgAnnouncements->getDataSetCount() > 0) {
            // get announcements data
            $plgGetAnnouncements = $plgAnnouncements->getDataSet(0, $plg_announcements_count);
            $plgAnnouncement = new TableAnnouncement($gDb);
            echo '<ul class="list-group list-group-flush">';

            foreach ($plgGetAnnouncements['recordset'] as $plgRow) {
                $plgAnnouncement->clear();
                $plgAnnouncement->setArray($plgRow);

                echo '<li class="list-group-item">
                    <h5><a href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES. '/announcements/announcements.php',
                        array('ann_uuid' => $plgAnnouncement->getValue('ann_uuid'), 'headline' => $plg_headline)
                    ). '" target="'. $plg_link_target. '">';

                if ($plg_max_char_per_word > 0) {
                    $plgNewHeadline = '';

                    // Interrupt words if they are too long
                    $plgWords = explode(' ', $plgAnnouncement->getValue('ann_headline'));

                    foreach ($plgWords as $plgValue) {
                        if (strlen($plgValue) > $plg_max_char_per_word) {
                            $plgNewHeadline .= ' '. substr($plgValue, 0, $plg_max_char_per_word). '-<br />'.
                                            substr($plgValue, $plg_max_char_per_word);
                        } else {
                            $plgNewHeadline .= ' '. $plgValue;
                        }
                    }
                    echo $plgNewHeadline.'</a></h5>';
                } else {
                    echo $plgAnnouncement->getValue('ann_headline').'</a></h5>';
                }

                // show preview text
                if ($plgShowFullDescription === 1) {
                    echo '<div>'.$plgAnnouncement->getValue('ann_description').'</div>';
                } elseif ($plg_show_preview > 0) {
                    // remove all html tags except some format tags
                    $textPrev = strip_tags($plgAnnouncement->getValue('ann_description'));

                    // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                    $textPrev = substr($textPrev, 0, $plg_show_preview + 15);
                    $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).'
                        <a class="admidio-icon-link" target="'. $plg_link_target. '"
                            href="'. SecurityUtils::encodeUrl(
                        ADMIDIO_URL. FOLDER_MODULES. '/announcements/announcements.php',
                        array('ann_uuid' => $plgAnnouncement->getValue('ann_uuid'), 'headline' => $plg_headline)
                    ). '"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';

                    echo '<div>'.$textPrev.'</div>';
                }

                echo '
                <div><em>('. $plgAnnouncement->getValue('ann_timestamp_create', $gSettingsManager->getString('system_date')). ')</em></div>
                </li>';
            }

            echo '<li class="list-group-item">
                <a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('headline' => $plg_headline)).'" target="'.$plg_link_target.'">'.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_ALL_ENTRIES').'</a>
            </li></ul>';
        } else {
            echo $gL10n->get('SYS_NO_ENTRIES');
        }
    } else {
        echo $gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_NO_ENTRIES_VISITORS');
    }
    echo '</div>';
}
