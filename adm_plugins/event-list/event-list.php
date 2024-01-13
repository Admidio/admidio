<?php
/**
 ***********************************************************************************************
 * Event list
 *
 * Plugin that lists the latest events in a slim interface and
 * can thus be ideally used in an overview page.
 *
 * @copyright The Admidio Team
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

// set default values if there is no value has been stored in the config.php
if (!isset($plg_max_number_events_shown) || !is_numeric($plg_max_number_events_shown)) {
    $plg_max_number_events_shown = 2;
}

if (!isset($plg_show_date_end) || !is_numeric($plg_show_date_end)) {
    $plg_show_date_end = 1;
}

if (!isset($plg_events_show_preview) || !is_numeric($plg_events_show_preview)) {
    $plg_events_show_preview = 70;
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

// Check if the link URL is set or empty, if empty use the default path to the Admidio module.
if (!isset($plg_link_url) || $plg_link_url === '') {
    $plg_link_url = ADMIDIO_URL . FOLDER_MODULES . '/events/events.php';
}

if ($gSettingsManager->getInt('events_module_enabled') > 0) {
    try {
        // read events for output
        $plgEvents = new ModuleEvents();
        $plgEvents->setDateRange();
        $plgEvents->setCalendarNames($plg_kal_cat);
        $plgEventsResult = $plgEvents->getDataSet(0, $plg_max_number_events_shown);
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $plgEvent = new TableEvent($gDb);

    echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
    if ($plg_show_headline) {
        echo '<h3>'.$gL10n->get('PLG_EVENT_LIST_HEADLINE').'</h3>';
    }

    if ($gSettingsManager->getInt('events_module_enabled') === 1
    || ($gSettingsManager->getInt('events_module_enabled') === 2 && $gValidLogin)) {
        if ($plgEventsResult['numResults'] > 0) {
            echo '<ul class="list-group list-group-flush">';

            foreach ($plgEventsResult['recordset'] as $plgRow) {
                $plgEvent->clear();
                $plgEvent->setArray($plgRow);
                $plgHtmlEndDate = '';

                echo '<li class="list-group-item">
                    <h5>'.$plgEvent->getDateTimePeriod($plg_show_date_end);

                // create a link to date module
                echo '<br /><a href="'. SecurityUtils::encodeUrl($plg_link_url,
                        array('view_mode' => 'html', 'view' => 'detail', 'dat_uuid' => $plgEvent->getValue('dat_uuid'))
                    ). '" target="'. $plg_link_target. '">';

                if ($plg_max_char_per_word > 0) {
                    $plgNewHeadline = '';

                    // Pause words if they are too long
                    $plgWords = explode(' ', $plgEvent->getValue('dat_headline'));

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
                    echo $plgEvent->getValue('dat_headline'). '</a></h5>';
                }

                // show preview text
                if ($plgShowFullDescription === 1) {
                    echo '<div>'.$plgEvent->getValue('dat_description').'</div>';
                } elseif ($plg_events_show_preview > 0) {
                    // remove all html tags except some format tags
                    $textPrev = strip_tags($plgEvent->getValue('dat_description'));

                    // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
                    $textPrev = substr($textPrev, 0, $plg_events_show_preview + 15);
                    $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).'
                        <a class="admidio-icon-link" target="'. $plg_link_target. '"
                            href="'.SecurityUtils::encodeUrl(
                        $plg_link_url,
                        array('view' => 'detail', 'dat_uuid' => $plgEvent->getValue('dat_uuid'))
                    ). '"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';

                    echo '<div>'.$textPrev.'</div>';
                }

                echo '</li>';
            }

            // forward to $plg_link_url without any additional parameters
            echo '<li class="list-group-item">
                <a href="'. $plg_link_url. '" target="'. $plg_link_target. '">'.$gL10n->get('PLG_EVENT_LIST_ALL_EVENTS').'</a>
            </li></ul>';
        } else {
            echo $gL10n->get('SYS_NO_ENTRIES');
        }
    } else {
        echo $gL10n->get('PLG_EVENT_LIST_NO_ENTRIES_VISITORS');
    }

    echo '</div>';
}
