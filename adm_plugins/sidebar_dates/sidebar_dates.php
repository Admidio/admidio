<?php
/**
 ***********************************************************************************************
 * Sidebar Dates
 *
 * Plugin that lists the latest events in a slim interface and
 * can thus be ideally used in a sidebar
 *
 * Compatible with Admidio version 4.1
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if (!isset($plg_dates_count) || !is_numeric($plg_dates_count)) {
    $plg_dates_count = 2;
}

if (!isset($plg_show_date_end) || !is_numeric($plg_show_date_end)) {
    $plg_show_date_end = 1;
}

if (!isset($plg_dates_show_preview) || !is_numeric($plg_dates_show_preview)) {
    $plg_dates_show_preview = 70;
}

if (!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription)) {
    $plgShowFullDescription = 1;
}

if (!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word)) {
    $plg_max_char_per_word = 0;
}

if (!isset($plg_kal_cat) || (isset($plg_kal_cat[0]) && $plg_kal_cat[0] === 'all')) {
    $plg_kal_cat = array();
}

if (isset($plg_link_target)) {
    $plg_link_target = strip_tags($plg_link_target);
} else {
    $plg_link_target = '_self';
}

if (!isset($plg_show_headline) || !is_numeric($plg_show_headline)) {
    $plg_show_headline = 1;
}

// Prüfen ob the Link-URL gesetzt wurde oder leer ist
// wenn leer, dann Standardpfad zum Admidio-Modul
if (!isset($plg_link_url) || $plg_link_url === '') {
    $plg_link_url = ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php';
}

if ($gSettingsManager->getInt('enable_dates_module') > 0) {
    // create Object
    $plgDates = new ModuleDates();

    // read events for output
    $plgDates->setDateRange();
    $plgDates->setCalendarNames($plg_kal_cat);
    $plgDatesResult = $plgDates->getDataSet(0, $plg_dates_count);

    $plgDate = new TableDate($gDb);

    echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
    if ($plg_show_headline) {
        echo '<h3>'.$gL10n->get('PLG_DATES_HEADLINE').'</h3>';
    }

    if ($gSettingsManager->getInt('enable_dates_module') === 1
    || ($gSettingsManager->getInt('enable_dates_module') === 2 && $gValidLogin)) {
        if ($plgDatesResult['numResults'] > 0) {
            echo '<ul class="list-group list-group-flush">';

            foreach ($plgDatesResult['recordset'] as $plgRow) {
                $plgDate->clear();
                $plgDate->setArray($plgRow);
                $plgHtmlEndDate = '';

                echo '<li class="list-group-item">
                    <h5>'.$plgDate->getDateTimePeriod($plg_show_date_end);

                // create a link to date module
                echo '<br /><a href="'. SecurityUtils::encodeUrl($plg_link_url,
                        array('view_mode' => 'html', 'view' => 'detail', 'dat_uuid' => $plgDate->getValue('dat_uuid'))
                    ). '" target="'. $plg_link_target. '">';

                if ($plg_max_char_per_word > 0) {
                    $plgNewHeadline = '';

                    // Pause words if they are too long
                    $plgWords = explode(' ', $plgDate->getValue('dat_headline'));

                    foreach ($plgWords as $plgValue) {
                        if (strlen($plgValue) > $plg_max_char_per_word) {
                            $plgNewHeadline .= ' '. substr($plgValue, 0, $plg_max_char_per_word). '-<br />'.
                                                substr($plgValue, $plg_max_char_per_word);
                        } else {
                            $plgNewHeadline .= ' '. $plgValue;
                        }
                    }
                    echo $plgNewHeadline. '</a></h5>';
                } else {
                    echo $plgDate->getValue('dat_headline'). '</a></h5>';
                }

                // show preview text
                if ($plgShowFullDescription === 1) {
                    echo '<div>'.$plgDate->getValue('dat_description').'</div>';
                } elseif ($plg_dates_show_preview > 0) {
                    // remove all html tags except some format tags
                    $textPrev = strip_tags($plgDate->getValue('dat_description'));

                    // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                    $textPrev = substr($textPrev, 0, $plg_dates_show_preview + 15);
                    $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).'
                        <a class="admidio-icon-link" target="'. $plg_link_target. '"
                            href="'.SecurityUtils::encodeUrl(
                        $plg_link_url,
                        array('view' => 'detail', 'dat_uuid' => $plgDate->getValue('dat_uuid'))
                    ). '"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';

                    echo '<div>'.$textPrev.'</div>';
                }

                echo '</li>';
            }

            // forward to $plg_link_url without any additional parameters
            echo '<li class="list-group-item">
                <a href="'. $plg_link_url. '" target="'. $plg_link_target. '">'.$gL10n->get('PLG_DATES_ALL_EVENTS').'</a>
            </li></ul>';
        } else {
            echo $gL10n->get('SYS_NO_ENTRIES');
        }
    } else {
        echo $gL10n->get('PLG_DATES_NO_ENTRIES_VISITORS');
    }

    echo '</div>';
}
