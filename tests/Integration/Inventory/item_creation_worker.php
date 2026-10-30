<?php

use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Service\ItemService;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Users\Entity\User;

require dirname(__DIR__, 2) . '/bootstrap-admidio.php';

// Use a separate connection without the test case's outer transaction so the
// service must start and roll back its own creation transaction.
$gCurrentOrgId = 1;
$gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
$gCurrentUser = new User($gDb, $gProfileFields);
$gCurrentUser->readDataById(1);
$gCurrentUserId = 1;
$fixture = new AdmidioTestFixture($gDb);
$role = $fixture->createAndSaveRoleWithRights('Creation worker ' . bin2hex(random_bytes(8)), $gCurrentOrgId, ['rol_inventory_admin' => 1]);
$fixture->assignUserToRole($gCurrentUserId, $role['rol_id']);
$gCurrentUser = new User($gDb, $gProfileFields);
$gCurrentUser->readDataById($gCurrentUserId);
$gCurrentOrganization = new Organization($gDb, $gCurrentOrgId);
$gSettingsManager = $gCurrentOrganization->getSettingsManager();
$gValidLogin = true;
$category = $gDb->queryPrepared('SELECT cat_uuid FROM ' . TBL_CATEGORIES
    . " WHERE cat_type = 'IVT' AND cat_org_id = ?", [$gCurrentOrgId])->fetchColumn();
$before = (int) $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS)->fetchColumn();
$values = ['INF-CATEGORY' => $category, 'INF-ITEMNAME' => ''];
if (($argv[1] ?? '') === 'parallel') {
    $values['INF-ITEMNAME'] = $argv[2];
    for ($wait = 0; !file_exists($argv[3]) && $wait < 200; ++$wait) {
        usleep(10000);
    }
}
if (($argv[1] ?? '') === 'return-date') {
    $values['INF-ITEMNAME'] = 'Failed creation';
    $values['INF-RETURN_DATE'] = date('Y-m-d', strtotime('+1 day'));
}
$error = '';
$errorId = null;
try {
    (new ItemService($gDb, '', 0, 1, true))->saveData($values);
} catch (Exception $exception) {
    $error = $exception->getMessage();
    $errorId = $exception->getTranslationId();
}
echo json_encode([
    'error' => $error,
    'errorId' => $errorId,
    'before' => $before,
    'after' => (int) $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS)->fetchColumn(),
    'transactionOpen' => $gDb->isInTransaction(),
    'itemId' => ($argv[1] ?? '') === 'parallel' ? (int) $gDb->queryPrepared(
        'SELECT ind_ini_id FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_value = ?', [$values['INF-ITEMNAME']]
    )->fetchColumn() : 0,
], JSON_THROW_ON_ERROR);
$fixture->deleteRole($role['rol_id']);
