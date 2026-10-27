<?php

use Admidio\Infrastructure\Database;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\Users\Entity\User;

session_start();
require dirname(__DIR__, 2) . '/bootstrap-admidio.php';

$request = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$gCurrentOrgId = 1;
$gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
$gCurrentUser = new User($gDb, $gProfileFields);
$gCurrentUser->readDataById(1);
$gCurrentUserId = 1;
$gCurrentOrganization = new Organization($gDb, $gCurrentOrgId);
$gSettingsManager = $gCurrentOrganization->getSettingsManager();
$gValidLogin = true;
$gCurrentSession = new Session($gDb, COOKIE_PREFIX);

// All fixtures and settings are rolled back when this worker's connection closes, including
// when the endpoint catches an SQL error and exits before returning to this file.
$gDb->startTransaction();
$fixture = new AdmidioTestFixture($gDb);
$role = $fixture->createAndSaveRoleWithRights('Inventory SQL audit', $gCurrentOrgId, ['rol_administrator' => 1, 'rol_inventory_admin' => 1]);
$user = $fixture->createAndSaveUser('inventorysqlaudit', 'inventorysqlaudit@example.local');
$fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
$gCurrentUser = new User($gDb, $gProfileFields);
$gCurrentUser->readDataById($user['usr_id']);
$gCurrentUserId = $user['usr_id'];
$gSettingsManager->set('inventory_module_enabled', '1');
$gSettingsManager->set('inventory_item_picture_enabled', '0');
$gSettingsManager->set('inventory_items_disable_borrowing', '0');
if (DB_TYPE === Database::PDO_ENGINE_MYSQL && ($request['strict'] ?? false)) {
    $gDb->queryPrepared("SET SESSION sql_mode = 'ANSI,ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES'");
}

$field = new ItemField($gDb);
$field->setValue('inf_org_id', $gCurrentOrgId);
$field->setValue('inf_name', 'Audit quantity');
$field->setValue('inf_type', ($request['invalidNumber'] ?? false) ? 'TEXT' : 'NUMBER');
$field->save();
$quantityField = $field->getValue('inf_name_intern');
$quantityFieldId = (int) $field->getValue('inf_id');

$field = new ItemField($gDb);
$field->setValue('inf_org_id', $gCurrentOrgId);
$field->setValue('inf_name', 'Audit description');
$field->setValue('inf_type', 'TEXT_BIG');
$field->save();
$descriptionField = $field->getValue('inf_name_intern');

$field = new ItemField($gDb);
$field->setValue('inf_org_id', $gCurrentOrgId);
$field->setValue('inf_name', 'Audit decimal');
$field->setValue('inf_type', 'DECIMAL');
$field->save();
$decimalField = $field->getValue('inf_name_intern');

$categoryUuid = $gDb->queryPrepared("SELECT cat_uuid FROM " . TBL_CATEGORIES . " WHERE cat_type = 'IVT' AND cat_org_id = ?", [$gCurrentOrgId])->fetchColumn();
$items = new ItemsData($gDb, $gCurrentOrgId);
foreach (['Camera' => '10', 'Camera spare' => '2', 'Tripod' => '20'] as $name => $quantity) {
    $items->readItemData('');
    $items->createNewItem($categoryUuid);
    $items->setValue('ITEMNAME', $name);
    $items->setValue('KEEPER', (string) $gCurrentUserId);
    $numericValue = ($request['scientificNumber'] ?? false) ? $quantity . 'e0' : $quantity;
    if ($name === 'Camera' && ($request['missingNumber'] ?? false)) {
        $numericValue = '';
    }
    $items->setValue($quantityField, $name === 'Tripod' && ($request['invalidNumber'] ?? false) ? 'not-a-number' : $numericValue);
    $items->setValue($decimalField, ['Camera' => '1.000002', 'Camera spare' => '1.000001', 'Tripod' => '1.000003'][$name]);
    $items->setValue($descriptionField, $name === 'Tripod' ? str_repeat('x', 2000) . ' longneedle' : 'optics');
    $items->setValue('LAST_RECEIVER', $name === 'Camera' ? (string) $gCurrentUserId : $name);
    $items->setValue('BORROW_DATE', '2020-01-01');
    $items->saveItemData();
}
if ($request['invalidNumber'] ?? false) {
    $field = new ItemField($gDb, $quantityFieldId);
    $field->setValue('inf_type', 'NUMBER');
    $field->save();
}

$_GET = [
    'draw' => 1,
    'start' => $request['start'] ?? 0,
    'length' => $request['length'] ?? 10,
    'search' => ['value' => $request['search'] ?? ''],
    'items_filter_status' => 0,
    'items_filter_category' => ($request['filterCategory'] ?? false) ? $categoryUuid : '',
    'items_filter_keeper' => ($request['filterKeeper'] ?? false) ? $gCurrentUserId : 0,
    'items_filter_last_receiver' => $request['filterReceiver'] ?? '',
];
$column = 1; // Inventory administrators have a selection checkbox before the item fields.
foreach ($items->getItemFields() as $itemField) {
    if ($itemField->getValue('inf_name_intern') === ($request['orderField'] ?? 'ITEMNAME')) {
        $_GET['order'] = [['column' => $column, 'dir' => $request['dir'] ?? 'asc']];
    }
    ++$column;
}

if (DB_TYPE === Database::PDO_ENGINE_MYSQL) {
    $gDb->queryPrepared('SET NAMES ' . (($request['charset'] ?? '') === 'utf8' ? 'utf8' : 'utf8mb4'));
}
(new InventoryPresenter())->createList();

// The copied endpoint is unchanged. Its common.php symlink resolves to the integration
// bootstrap already loaded above, so require_once retains this isolated test context.
require $argv[2];

$gDb->rollback();
