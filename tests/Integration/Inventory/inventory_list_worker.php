<?php

use Admidio\Infrastructure\Database;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions;
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
$keeper = new User($gDb, $gProfileFields, $user['usr_id']);
$keeper->saveChangesWithoutRights();
$keeper->setValue('FIRST_NAME', 'Review');
$keeper->setValue('LAST_NAME', 'Keeperlabel');
$keeper->save();
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
if ($request['displayValues'] ?? false) {
    $borrowerData = $fixture->createAndSaveUser('inventoryborrower', 'borrower@example.local');
    $fixture->assignUserToRole($borrowerData['usr_id'], $role['rol_id']);
    $borrower = new User($gDb, $gProfileFields, $borrowerData['usr_id']);
    $borrower->saveChangesWithoutRights();
    $borrower->setValue('FIRST_NAME', 'Review');
    $borrower->setValue('LAST_NAME', 'Borrowerlabel');
    $borrower->save();
    $category = $fixture->createAndSaveCategory('Audio Equipment ÄÖÜ & Zubehör', 'IVT', $gCurrentOrgId);
    $audioCategoryUuid = $category['cat_uuid'];
    $field = new ItemField($gDb);
    $field->setValue('inf_org_id', $gCurrentOrgId);
    $field->setValue('inf_name', 'Audit transport');
    $field->setValue('inf_type', 'DROPDOWN');
    $field->save();
    $transportField = $field->getValue('inf_name_intern');
    $option = new SelectOptions($gDb, (int)$field->getValue('inf_id'));
    $option->setValue('ifo_inf_id', $field->getValue('inf_id'));
    $option->setValue('ifo_value', 'Transportlabel');
    $option->save();
    $gSettingsManager->set('system_date', 'd.m.Y');
}
$items = new ItemsData($gDb, $gCurrentOrgId);
foreach (['Camera' => '10', 'Camera spare' => '2', 'Tripod' => '20'] as $name => $quantity) {
    $items->readItemData('');
    $items->createNewItem(isset($audioCategoryUuid) && $name !== 'Camera spare' ? $audioCategoryUuid : $categoryUuid);
    $items->setValue('ITEMNAME', $name);
    $items->setValue('KEEPER', (string) $gCurrentUserId);
    $numericValue = ($request['scientificNumber'] ?? false) ? $quantity . 'e0' : $quantity;
    if ($request['sameNumber'] ?? false) {
        $numericValue = '10';
    }
    if ($name === 'Camera' && ($request['missingNumber'] ?? false)) {
        $numericValue = '';
    }
    $items->setValue($quantityField, $name === 'Tripod' && ($request['invalidNumber'] ?? false) ? 'not-a-number' : $numericValue);
    $items->setValue($decimalField, ['Camera' => '1.000002', 'Camera spare' => '1.000001', 'Tripod' => '1.000003'][$name]);
    $description = $name === 'Tripod' ? str_repeat('x', 2000) . ' longneedle' : 'optics';
    if ($name === 'Camera') {
        $description .= ",kit camera-kit ÄÖÜ O'Reilly 50% Model_A";
    }
    $items->setValue($descriptionField, $description);
    $items->setValue('LAST_RECEIVER', $name === 'Camera' ? (string) $gCurrentUserId : $name);
    $items->setValue('BORROW_DATE', '2020-01-01');
    if (isset($transportField) && $name === 'Camera') {
        $items->setValue($transportField, (string)$option->getValue('ifo_id'));
        $items->setValue('LAST_RECEIVER', (string)$borrowerData['usr_id']);
    }
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
$fieldColumns = [];
foreach ($items->getItemFields() as $itemField) {
    $fieldColumns[$itemField->getValue('inf_name_intern')] = $column;
    if ($itemField->getValue('inf_name_intern') === ($request['orderField'] ?? 'ITEMNAME')) {
        $_GET['order'] = [['column' => $column, 'dir' => $request['dir'] ?? 'asc']];
    }
    ++$column;
}
if (isset($request['orderFields'])) {
    $_GET['order'] = [];
    foreach ($request['orderFields'] as [$name, $direction]) {
        $_GET['order'][] = ['column' => $fieldColumns[$name], 'dir' => $direction];
    }
}
if ($request['defaultOrder'] ?? false) {
    unset($_GET['order']);
}

if (DB_TYPE === Database::PDO_ENGINE_MYSQL) {
    $gDb->queryPrepared('SET NAMES ' . (($request['charset'] ?? '') === 'utf8' ? 'utf8' : 'utf8mb4'));
}
(new InventoryPresenter())->createList();
if ($request['guest'] ?? false) {
    $gValidLogin = false;
    $gCurrentUser = new User($gDb, $gProfileFields);
    $gCurrentUserId = 0;
    $gSettingsManager->set('inventory_anonymize_user_names_for_guests', '1');
}
if ($request['disableBorrowing'] ?? false) {
    $gSettingsManager->set('inventory_items_disable_borrowing', '1');
}

// The copied endpoint is unchanged. Its common.php symlink resolves to the integration
// bootstrap already loaded above, so require_once retains this isolated test context.
$gLogger->resetQueryCount();
ob_start();
require $argv[2];
$result = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
$result['testQueryCount'] = $gLogger->getQueryCount();
if ($request['compareExports'] ?? false) {
    $_GET['items_filter_string'] = $request['search'] ?? '';
    foreach (['csv', 'xlsx', 'ods', 'pdf', 'print'] as $mode) {
        $data = (new InventoryPresenter(false))->prepareData($mode);
        $names = array_map(static fn(array $row): string => $row['data'][0], $data['rows']);
        sort($names);
        $result['testExportNames'][$mode] = $names;
    }
}
if ($request['checkPages'] ?? false) {
    foreach ([0, 1, 2, 0] as $start) {
        $_GET['start'] = $start;
        $_GET['length'] = 1;
        ob_start();
        require $argv[2];
        $page = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        $result['testPages'][] = $page['data'];
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR);

$gDb->rollback();
