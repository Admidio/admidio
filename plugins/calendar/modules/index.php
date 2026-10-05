<?php
/**
 ***********************************************************************************************
 * The month fragment of the calendar plugin.
 *
 * The month buttons of the calendar replace its content without reloading the page, so this page
 * answers with the calendar of one month and nothing else.
 *
 * The Admidio bootstrap has already checked that this plugin is installed and enabled for the
 * current organization; a page of a disabled plugin is refused before it runs.
 *
 * Parameters:
 *
 * date_id : Month and year that should be shown, as mmyyyy. Without it the month the visitor last
 *           looked at is shown, or the current one.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Calendar\Calendar;

try {
    require_once(__DIR__ . '/../../../system/common.php');

    $getDateId = admFuncVariableIsValid($_GET, 'date_id', 'string');

    $plugin = PluginRegistry::requireEnabled(Calendar::PLUGIN_ID);

    // The same preference decides here as on the overview: a visitor who may not see the widget may
    // not ask for one of its months either.
    if (!PluginWidget::isVisible('calendar_plugin_enabled')) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $page = new PagePresenter('adm_plugin_calendar');
    $page->setInlineMode();

    header('Content-Type: text/html; charset=utf-8');
    echo Calendar::renderWidget($page, $plugin, $getDateId);
} catch (Throwable $e) {
    handleException($e);
}
