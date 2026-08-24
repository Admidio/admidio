<?php

namespace Admidio\Tests\Integration\Services;

use Admidio\Inventory\Service\ItemFieldService;
use Admidio\Inventory\Service\ItemService;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Tests\Support\AdministratorTestCase;

/**
 * Regression coverage for the headless inventory Service APIs.
 *
 * Fixtures are resolved from the production installation data and mutations go through
 * ItemFieldService/ItemService. Raw queries are used only as an independent assertion boundary.
 */
class InventoryServicePathTest extends AdministratorTestCase
{
    /**
     * @testdox Inventory field and item lifecycle is persisted by the production Services
     */
    public function testInventoryFieldAndItemLifecycleUsesProductionServices(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $suffix = bin2hex(random_bytes(5));
        $fieldName = 'Regression asset tag ' . $suffix;

        $fieldService = new ItemFieldService($db);
        $this->assertTrue($fieldService->saveData(array(
            'inf_name' => $fieldName,
            'inf_type' => 'TEXT',
            'inf_required_input' => 0,
            'inf_disabled' => 0
        )));

        $fieldRow = $db->queryPrepared(
            'SELECT inf_uuid, inf_name_intern
               FROM ' . TBL_INVENTORY_FIELDS . '
              WHERE inf_org_id = ?
                AND inf_name = ?',
            array($gCurrentOrgId, $fieldName)
        )->fetch();

        $this->assertIsArray($fieldRow);
        $this->assertNotSame('', (string)$fieldRow['inf_uuid']);
        $this->assertNotSame('', (string)$fieldRow['inf_name_intern']);

        $updatedFieldName = $fieldName . ' updated';
        (new ItemFieldService($db, (string)$fieldRow['inf_uuid']))->saveData(array(
            'inf_name' => $updatedFieldName
        ));

        $this->assertSame(
            $updatedFieldName,
            (string)$db->queryPrepared(
                'SELECT inf_name
                   FROM ' . TBL_INVENTORY_FIELDS . '
                  WHERE inf_uuid = ?',
                array($fieldRow['inf_uuid'])
            )->fetchColumn()
        );

        $categoryUuid = $db->queryPrepared(
            'SELECT cat_uuid
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND cat_org_id = ?
           ORDER BY cat_id
              LIMIT 1',
            array('IVT', $gCurrentOrgId)
        )->fetchColumn();

        $this->assertIsString($categoryUuid);
        $this->assertNotSame('', $categoryUuid);

        $itemName = 'Service item ' . $suffix;
        (new ItemService($db))->saveData(array(
            'INF-CATEGORY' => $categoryUuid,
            'INF-ITEMNAME' => $itemName,
            'INF-' . $fieldRow['inf_name_intern'] => 'asset-' . $suffix
        ));

        $itemUuid = $this->findItemUuidByName($itemName);
        $this->assertNotSame('', $itemUuid);

        $itemData = new ItemsData($db, $gCurrentOrgId);
        $itemData->readItemData($itemUuid);
        $this->assertSame('asset-' . $suffix, (string)$itemData->getValue($fieldRow['inf_name_intern'], 'database'));

        $updatedItemName = $itemName . ' updated';
        (new ItemService($db, $itemUuid))->saveData(
            array('INF-ITEMNAME' => $updatedItemName),
            true
        );

        $this->assertSame($itemUuid, $this->findItemUuidByName($updatedItemName));

        $itemService = new ItemService($db, $itemUuid);
        $itemService->retireItem();

        $itemData = new ItemsData($db, $gCurrentOrgId);
        $itemData->readItemData($itemUuid);
        $this->assertTrue($itemData->isRetired());

        (new ItemService($db, $itemUuid))->reinstateItem();

        $itemData = new ItemsData($db, $gCurrentOrgId);
        $itemData->readItemData($itemUuid);
        $this->assertFalse($itemData->isRetired());

        (new ItemService($db, $itemUuid))->delete();

        $this->assertSame(
            0,
            (int)$db->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_INVENTORY_ITEMS . '
                  WHERE ini_uuid = ?',
                array($itemUuid)
            )->fetchColumn()
        );

        $this->assertTrue((new ItemFieldService($db, (string)$fieldRow['inf_uuid']))->delete());
        $this->assertSame(
            0,
            (int)$db->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_INVENTORY_FIELDS . '
                  WHERE inf_uuid = ?',
                array($fieldRow['inf_uuid'])
            )->fetchColumn()
        );
    }

    private function findItemUuidByName(string $name): string
    {
        global $gCurrentOrgId;

        $uuid = $this->getDatabase()->queryPrepared(
            'SELECT ini_uuid
               FROM ' . TBL_INVENTORY_ITEMS . '
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . '
                 ON ind_ini_id = ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . '
                 ON inf_id = ind_inf_id
              WHERE ini_org_id = ?
                AND inf_name_intern = ?
                AND ind_value = ?',
            array($gCurrentOrgId, 'ITEMNAME', $name)
        )->fetchColumn();

        return is_string($uuid) ? $uuid : '';
    }
}
