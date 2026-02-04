<?php
/**
 ***********************************************************************************************
 * Delete a charge definition (admins only)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isResidentsAdminBySettings()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$chargeId = admFuncVariableIsValid($_GET, 'id', 'int');
if ($chargeId <= 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$charge = new TableResidentsCharge($gDb, $chargeId);
if ($charge->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Organization check: charge must belong to current organization
residentsValidateOrganization($charge, 'rch_org_id');

$deleted = $charge->delete();

$params = array('tab' => 'chargers');
if ($deleted) {
    $params['charge_status'] = 'deleted';
} else {
    $params['charge_status'] = 'error';
    $params['charge_message'] = 'Failed to delete charge.';
}

admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params));
