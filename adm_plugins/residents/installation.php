<?php
/**
 ***********************************************************************************************
 * Installation routine for Residents plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Ramsey\Uuid\Uuid;

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/ConfigTables.php');

// only administrators may run installation
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$gNavigation->addStartUrl(CURRENT_URL);

$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'start', 'validValues' => array('start', 'install', 'uninstall')));

$headline = $gL10n->get('RE_INSTALL_HEADLINE');
$page = new HtmlPage('bl-residents-installation', $headline);
residentsEnqueueStyles($page);

$isInstalled = tableExistsRE(TBL_RE_INVOICES);

if ($getMode === 'install') {
    $creator = new ConfigTables();
    $creator->init();
    ensureResidentsMenuItem();

    // Seed a default invoice note so the setting is visible immediately after install
    $config = residentsReadConfig();
    if (!isset($config['defaults']['invoice_note']) || trim((string)$config['defaults']['invoice_note']) === '') {
        $config['defaults']['invoice_note'] = $gL10n->get('RE_DEFAULT_NOTE_TEXT');
        residentsWriteConfig($config);
    }

    $page->addHtml('<p>' . $gL10n->get('RE_INSTALL_DONE') . '</p>');
    $page->addHtml('<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php') . '"><i class="bi bi-receipt"></i> ' . $gL10n->get('RE_OPEN_RESIDENTS') . '</a> ');
    $page->addHtml('<a class="btn btn-danger" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'uninstall')) . '" onclick="return confirm(\'' . $gL10n->get('RE_UNINSTALL_CONFIRM') . '\');"><i class="bi bi-trash"></i> ' . $gL10n->get('RE_UNINSTALL_RESIDENTS') . '</a>');
} elseif ($getMode === 'uninstall') {
    $creator = new ConfigTables();
    $creator->uninstall();
    removeResidentsMenuItem();
    residentsDeleteConfig();

    // Remove any stored Residents org logos so a reinstall starts clean.
    // Logos are stored in adm_my_files/residents/org_logo_{orgId}.png
    $logoDir = ADMIDIO_PATH . FOLDER_DATA . '/residents';
    if (is_dir($logoDir)) {
        $logoFiles = glob($logoDir . '/org_logo_*.png') ?: array();
        foreach ($logoFiles as $logoFile) {
            try {
                if (class_exists('FileSystemUtils')) {
                    FileSystemUtils::deleteFileIfExists($logoFile);
                } elseif (file_exists($logoFile)) {
                    @unlink($logoFile);
                }
            } catch (Throwable $e) {
                // ignore delete errors
            }
        }
    }

    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('RE_UNINSTALL_DONE') . '</div>');
    $page->addHtml('<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'install')) . '"><i class="bi bi-arrow-right-circle"></i> ' . $gL10n->get('RE_INSTALL') . '</a>');
} else {
    $page->addHtml('<p>' . $gL10n->get('RE_INSTALL_DESC', array('<code>' . TBL_RE_INVOICES . '</code>', '<code>' . TBL_RE_INVOICE_ITEMS . '</code>', '<code>' . TBL_RE_CHARGES . '</code>')) . '</p>');

    if ($isInstalled) {
        $page->addHtml('<div class="alert alert-info">' . $gL10n->get('RE_INSTALL_ALREADY') . '</div>');
    }

    $form = new HtmlForm('installation_start_form', '', $page, array('setFocus' => false));
    if (!$isInstalled) {
        $form->addButton('btnInstall', $gL10n->get('RE_INSTALL'), array('icon' => 'fa-arrow-circle-right', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'install')), 'class' => 'btn-primary'));
        $page->addHtml($form->show(false));
    }

    if ($isInstalled) {
        $page->addHtml('<a class="btn btn-danger text-white" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'uninstall')) . '" onclick="return confirm(\'' . $gL10n->get('RE_UNINSTALL_CONFIRM') . '\');"><i class="bi bi-trash"></i> ' . $gL10n->get('RE_UNINSTALL_RESIDENTS') . '</a>');
    }
}

$page->show();
