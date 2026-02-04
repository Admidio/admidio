<?php
/**
 ***********************************************************************************************
 * Residents main page: renders tabs and delegates content to sub files.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/../../system/login_valid.php');

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$config = residentsReadConfig();
$isAdmin = isResidentsAdmin();
$canSeeChargers = isResidentsAdminBySettings();
$canSeePreferences = isResidentsAdmin();

$tab = admFuncVariableIsValid($_GET, 'tab', 'string', array('defaultValue' => 'invoices', 'validValues' => array('invoices', 'payments', 'chargers', 'preferences', 'devices')));
$getId = admFuncVariableIsValid($_GET, 'id', 'int');

$gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('RES_TITLE'), 'bi-receipt');
$page = new HtmlPage('residents');
$page->setTitle($gL10n->get('RES_TITLE'));
$tabHeadlines = array(
    'invoices' => $gL10n->get('RE_TAB_INVOICES'),
    'payments' => $gL10n->get('RE_TAB_PAYMENTS'),
    'chargers' => $gL10n->get('RE_TAB_CHARGERS'),
    'preferences' => $gL10n->get('RE_TAB_PREFERENCES'),
    'devices' => $gL10n->get('RE_TAB_DEVICES')
);
$page->setHeadline($tabHeadlines[$tab] ?? $gL10n->get('RES_TITLE'));
residentsEnqueueStyles($page);

// Render tabs
$baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
$tabs = '<ul class="nav nav-tabs">'
    . '<li class="nav-item"><a class="nav-link'.($tab==='invoices'?' active':'').'" href="'.SecurityUtils::encodeUrl($baseUrl, array('tab'=>'invoices')).'">'.$gL10n->get('RE_TAB_INVOICES').'</a></li>'
    . '<li class="nav-item"><a class="nav-link'.($tab==='payments'?' active':'').'" href="'.SecurityUtils::encodeUrl($baseUrl, array('tab'=>'payments')).'">'.$gL10n->get('RE_TAB_PAYMENTS').'</a></li>'
    . ($canSeeChargers ? '<li class="nav-item"><a class="nav-link'.($tab==='chargers'?' active':'').'" href="'.SecurityUtils::encodeUrl($baseUrl, array('tab'=>'chargers')).'">'.$gL10n->get('RE_TAB_CHARGERS').'</a></li>' : '')
    . ($canSeeChargers ? '<li class="nav-item"><a class="nav-link'.($tab==='devices'?' active':'').'" href="'.SecurityUtils::encodeUrl($baseUrl, array('tab'=>'devices')).'">'.$gL10n->get('RE_TAB_DEVICES').'</a></li>' : '')
    . ($canSeePreferences ? '<li class="nav-item"><a class="nav-link'.($tab==='preferences'?' active':'').'" href="'.SecurityUtils::encodeUrl($baseUrl, array('tab'=>'preferences')).'">'.$gL10n->get('RE_TAB_PREFERENCES').'</a></li>' : '')
    . '</ul><br />';
$page->addHtml($tabs);
$page->addHtml('<style>.admidio-content-header {margin-bottom: 0px;}</style>');

// Show success message after preferences save redirect
$prefStatus = admFuncVariableIsValid($_GET, 'pref_status', 'string');
if ($prefStatus === 'saved') {
    $page->addHtml('<div class="alert alert-success">'.$gL10n->get('RE_SAVED').'</div>');
}

// Delegate to sub files
switch ($tab) {
    case 'payments':
        require __DIR__ . '/payments/list.php';
        break;
    case 'chargers':
        if ($canSeeChargers) {
            require __DIR__ . '/charges/list.php';
        } else {
            $page->addHtml('<div class="alert alert-warning">'.$gL10n->get('SYS_NO_RIGHTS').'</div>');
        }
        break;
    case 'preferences':
        if ($canSeePreferences) {
            require __DIR__ . '/preferences/index.php';
        } else {
            $page->addHtml('<div class="alert alert-warning">'.$gL10n->get('SYS_NO_RIGHTS').'</div>');
        }
        break;
    case 'devices':
        if ($canSeeChargers) {
            require __DIR__ . '/devices/list.php';
        } else {
            $page->addHtml('<div class="alert alert-warning">'.$gL10n->get('SYS_NO_RIGHTS').'</div>');
        }
        break;
    case 'invoices':
    default:
        require __DIR__ . '/invoices/list.php';
        break;
}

// Add bottom spacing before footer
$page->addHtml('<div style="height: 50px;"></div>');

// Show page after sub content appended
$page->show();
