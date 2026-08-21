<?php
/**
 * Inventory Tests
 *
 * Tests the inventory module. An item is a row in adm_inventory_items that carries only the
 * organization, the category and the status; everything else is stored per field, either in
 * adm_inventory_item_data or, for the three borrow fields, in adm_inventory_item_borrow_data.
 * The field definitions in adm_inventory_fields belong to an organization.
 *
 * Items are not created through the entity but through ItemsData, which is also the object that
 * checks the rights, so the tests go through it the same way ItemService does.
 */

namespace Admidio\Tests\Integration\Inventory;

use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class InventoryTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation. Only it has the system item fields and an
     * inventory category, both of which an item needs.
     */
    private const ORG_ID = 1;

    /**
     * The item fields that a standard installation delivers.
     */
    private const SYSTEM_FIELDS = ['ITEMNAME', 'CATEGORY', 'STATUS', 'KEEPER', 'LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE'];

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Build a user for the inventory tests.
     *
     * @param string $login Login name, also used for the role name
     * @param bool $fullAdministrator Whether the user is an administrator of the whole organization
     * @return User
     */
    private function makeInventoryUser(string $login, bool $fullAdministrator): User
    {
        $fixture = $this->getFixture();
        $rights = ['rol_inventory_admin' => 1];
        if ($fullAdministrator) {
            $rights['rol_administrator'] = 1;
        }
        $role = $fixture->createAndSaveRoleWithRights('Inventory ' . $login, self::ORG_ID, $rights);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);
    }

    /**
     * The uuid of the inventory category of the installed organization.
     */
    private function inventoryCategoryUuid(): string
    {
        $sql = 'SELECT cat_uuid FROM ' . TBL_CATEGORIES . " WHERE cat_type = 'IVT' AND cat_org_id = ?";

        return (string) $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchColumn();
    }

    /**
     * Create an item with the given values and return its id.
     *
     * ItemsData has to be told that a new item is being built before createNewItem() does anything,
     * which is what readItemData('') does. ItemService uses the same order.
     *
     * @param array<string,string> $values Field values by internal field name
     */
    private function createItem(ItemsData $itemsData, array $values): int
    {
        $itemsData->readItemData('');
        $itemsData->createNewItem($this->inventoryCategoryUuid());

        foreach ($values as $field => $value) {
            $itemsData->setValue($field, $value);
        }
        $itemsData->saveItemData();

        return $itemsData->getItemId();
    }

    /**
     * The uuid of a stored item.
     */
    private function uuidOfItem(int $itemId): string
    {
        $sql = 'SELECT ini_uuid FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?';

        return (string) $this->getDatabase()->queryPrepared($sql, [$itemId])->fetchColumn();
    }

    /**
     * Test that the installation delivers the item fields
     *
     * @testdox The installation creates the system item fields for the organization
     */
    public function testInstallationCreatesTheSystemItemFields(): void
    {
        $sql = 'SELECT inf_name_intern, inf_type, inf_system, inf_sequence FROM ' . TBL_INVENTORY_FIELDS . '
                 WHERE inf_org_id = ? ORDER BY inf_sequence';
        $fields = $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchAll();

        $this->assertEquals(self::SYSTEM_FIELDS, array_column($fields, 'inf_name_intern'));

        // they are all flagged as system fields
        foreach ($fields as $field) {
            $this->assertTrue((bool) $field['inf_system'], $field['inf_name_intern']);
        }

        // the status field offers the two states an item can be in
        $sql = 'SELECT ifo_value FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
                  INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ifo_inf_id
                 WHERE inf_name_intern = ? AND inf_org_id = ? ORDER BY ifo_sequence';
        $options = $this->getDatabase()->queryPrepared($sql, ['STATUS', self::ORG_ID])->fetchAll();
        $this->assertEquals(
            ['SYS_INVENTORY_FILTER_IN_USE_ITEMS', 'SYS_INVENTORY_FILTER_RETIRED_ITEMS'],
            array_column($options, 'ifo_value')
        );
    }

    /**
     * Test that item fields belong to one organization
     *
     * @testdox An organization created afterwards has no item fields of its own
     */
    public function testAnOrganizationCreatedAfterwardsHasNoItemFields(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Inventory Org', 'invorg');

        $sql = 'SELECT COUNT(*) FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_org_id = ?';
        $this->assertEquals(0, (int) $this->getDatabase()->queryPrepared($sql, [$org['org_id']])->fetchColumn());

        $fields = $this->withOrganization($org['org_id'], function () use ($org) {
            $itemsData = new ItemsData($this->getDatabase(), $org['org_id']);

            return $itemsData->getItemFields();
        });
        $this->assertCount(0, $fields);
    }

    /**
     * Test that a new field gets a name and a position
     *
     * @testdox A new item field gets an internal name and the next free position
     */
    public function testNewItemFieldGetsAnInternalNameAndPosition(): void
    {
        $admin = $this->makeInventoryUser('invnaming', true);

        $field = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $field = new ItemField($this->getDatabase());
            $field->setValue('inf_org_id', self::ORG_ID);
            $field->setValue('inf_type', 'TEXT');
            $field->setValue('inf_name', 'Serial number');
            $field->save();

            return $field;
        });

        $this->assertEquals('SERIAL_NUMBER', $field->getValue('inf_name_intern'));
        $this->assertNotEmpty($field->getValue('inf_uuid'));

        // the position continues after the fields that already exist
        $this->assertEquals(count(self::SYSTEM_FIELDS), (int) $field->getValue('inf_sequence'));

        $sql = 'SELECT inf_name, inf_type, inf_org_id, inf_usr_id_create FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$field->getValue('inf_id')])->fetch();
        $this->assertEquals('Serial number', $row['inf_name']);
        $this->assertEquals('TEXT', $row['inf_type']);
        $this->assertEquals(self::ORG_ID, (int) $row['inf_org_id']);
        $this->assertEquals((int) $admin->getValue('usr_id'), (int) $row['inf_usr_id_create']);
    }

    /**
     * Test that internal names stay unique
     *
     * @testdox A second item field with the same name gets a numbered internal name
     */
    public function testDuplicateFieldNameGetsANumberedInternalName(): void
    {
        $admin = $this->makeInventoryUser('invdup', true);

        $names = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $names = array();
            foreach (['Colour', 'Colour', 'Keeper'] as $name) {
                $field = new ItemField($this->getDatabase());
                $field->setValue('inf_org_id', self::ORG_ID);
                $field->setValue('inf_type', 'TEXT');
                $field->setValue('inf_name', $name);
                $field->save();
                $names[] = $field->getValue('inf_name_intern');
            }

            return $names;
        });

        $this->assertEquals('COLOUR', $names[0]);
        $this->assertEquals('COLOUR_2', $names[1]);

        // the name is checked against every organization, not only the current one, so a name that
        // an existing system field already uses is numbered as well
        $this->assertEquals('KEEPER_2', $names[2]);
    }

    /**
     * Test which right is needed to store an item field
     *
     * @testdox Storing an item field needs administrator rights for the whole organization
     */
    public function testStoringAnItemFieldNeedsFullAdministratorRights(): void
    {
        $inventoryAdmin = $this->makeInventoryUser('invonly', false);

        $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($inventoryAdmin) {
            // the user administrates the inventory but not the organization
            $this->assertTrue($inventoryAdmin->isAdministratorInventory());
            $this->assertFalse($inventoryAdmin->isAdministrator());

            $field = new ItemField($this->getDatabase());
            $field->setValue('inf_org_id', self::ORG_ID);
            $field->setValue('inf_type', 'TEXT');
            $field->setValue('inf_name', 'Not allowed');

            $this->expectException(Exception::class);
            $field->save();
        });
    }

    /**
     * Test that the inventory right is enough to remove a field
     *
     * @testdox An inventory administrator may delete an item field
     */
    public function testInventoryAdministratorMayDeleteAnItemField(): void
    {
        $fullAdmin = $this->makeInventoryUser('invfull', true);
        $inventoryAdmin = $this->makeInventoryUser('invdel', false);

        $infId = $this->withCurrentUser($fullAdmin, self::ORG_ID, true, function () {
            $field = new ItemField($this->getDatabase());
            $field->setValue('inf_org_id', self::ORG_ID);
            $field->setValue('inf_type', 'TEXT');
            $field->setValue('inf_name', 'Temporary');
            $field->save();

            return (int) $field->getValue('inf_id');
        });

        $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($infId) {
            $field = new ItemField($this->getDatabase(), $infId);
            $this->assertTrue($field->delete());
        });

        $sql = 'SELECT inf_id FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_id = ?';
        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$infId])->fetch());
    }

    /**
     * Test that the delivered fields are protected
     *
     * @testdox A system item field cannot be deleted
     */
    public function testSystemItemFieldCannotBeDeleted(): void
    {
        $admin = $this->makeInventoryUser('invsystem', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $sql = 'SELECT inf_id FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_name_intern = ? AND inf_org_id = ?';
            $infId = (int) $this->getDatabase()->queryPrepared($sql, ['ITEMNAME', self::ORG_ID])->fetchColumn();

            $field = new ItemField($this->getDatabase(), $infId);
            $this->assertTrue((bool) $field->getValue('inf_system'));

            $this->expectException(Exception::class);
            $field->delete();
        });
    }

    /**
     * Test what a new item looks like
     *
     * @testdox A new item belongs to the organization and starts in use
     */
    public function testNewItemBelongsToTheOrganizationAndStartsInUse(): void
    {
        $admin = $this->makeInventoryUser('invnew', true);

        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Hammer'));

            $this->assertTrue($itemsData->isInUse());
            $this->assertFalse($itemsData->isRetired());
            $this->assertFalse($itemsData->isBorrowed());

            return $itemId;
        });

        $sql = 'SELECT ini_org_id, ini_cat_id, ini_status, ini_uuid, ini_usr_id_create FROM ' . TBL_INVENTORY_ITEMS . '
                 WHERE ini_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$itemId])->fetch();

        $this->assertEquals(self::ORG_ID, (int) $row['ini_org_id']);
        $this->assertNotEmpty($row['ini_uuid']);
        $this->assertEquals((int) $admin->getValue('usr_id'), (int) $row['ini_usr_id_create']);

        // the category of the item is stored on the item itself, not as item data
        $sql = 'SELECT cat_type FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $this->assertEquals('IVT', $this->getDatabase()->queryPrepared($sql, [$row['ini_cat_id']])->fetchColumn());
    }

    /**
     * Test where the values of an item are stored
     *
     * @testdox The values of an item are stored as one row per field
     */
    public function testItemValuesAreStoredAsOneRowPerField(): void
    {
        $admin = $this->makeInventoryUser('invvalues', true);

        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);

            return $this->createItem($itemsData, array('ITEMNAME' => 'Ladder', 'KEEPER' => '1'));
        });

        $sql = 'SELECT inf_name_intern, ind_value FROM ' . TBL_INVENTORY_ITEM_DATA . '
                  INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id
                 WHERE ind_ini_id = ? ORDER BY inf_sequence';
        $rows = $this->getDatabase()->queryPrepared($sql, [$itemId])->fetchAll();

        $this->assertEquals(['ITEMNAME', 'KEEPER'], array_column($rows, 'inf_name_intern'));
        $this->assertEquals(['Ladder', '1'], array_column($rows, 'ind_value'));

        // the name of the item is what identifies it in the changelog and the lists
        $name = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($itemId) {
            return (new Item($this->getDatabase(), null, $itemId))->readableName();
        });
        $this->assertEquals('Ladder', $name);
    }

    /**
     * Test that the values survive a round trip
     *
     * @testdox The values of an item are read back through a fresh object
     */
    public function testItemValuesAreReadBackThroughAFreshObject(): void
    {
        $admin = $this->makeInventoryUser('invroundtrip', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Drill', 'KEEPER' => '1'));

            $fresh = new ItemsData($this->getDatabase(), self::ORG_ID);
            $fresh->readItemData($this->uuidOfItem($itemId));

            $this->assertEquals($itemId, $fresh->getItemId());
            $this->assertEquals('Drill', $fresh->getValue('ITEMNAME'));
            $this->assertEquals('1', $fresh->getValue('KEEPER'));
            $this->assertTrue($fresh->isInUse());
        });
    }

    /**
     * Test that the borrow fields go to their own table
     *
     * @testdox The borrow data of an item is stored in its own table
     */
    public function testBorrowDataIsStoredInItsOwnTable(): void
    {
        $admin = $this->makeInventoryUser('invborrow', true);

        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);

            return $this->createItem($itemsData, array(
                'ITEMNAME' => 'Projector',
                'LAST_RECEIVER' => 'Alice',
                'BORROW_DATE' => '2030-03-01',
                'RETURN_DATE' => '2030-03-31'
            ));
        });

        $sql = 'SELECT inb_last_receiver, inb_borrow_date, inb_return_date FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . '
                 WHERE inb_ini_id = ?';
        $rows = $this->getDatabase()->queryPrepared($sql, [$itemId])->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['inb_last_receiver']);
        $this->assertStringStartsWith('2030-03-01', $rows[0]['inb_borrow_date']);
        $this->assertStringStartsWith('2030-03-31', $rows[0]['inb_return_date']);

        // the three borrow fields do not appear among the ordinary item data
        $sql = 'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?';
        $this->assertEquals(1, (int) $this->getDatabase()->queryPrepared($sql, [$itemId])->fetchColumn());

        // an item that has been given back is not borrowed
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($itemId) {
            $fresh = new ItemsData($this->getDatabase(), self::ORG_ID);
            $fresh->readItemData($this->uuidOfItem($itemId));
            $this->assertEquals('Alice', $fresh->getValue('LAST_RECEIVER', 'database'));
            $this->assertFalse($fresh->isBorrowed());
        });
    }

    /**
     * Test that an item is borrowed while it is out
     *
     * @testdox An item without a return date counts as borrowed
     */
    public function testItemWithoutAReturnDateCountsAsBorrowed(): void
    {
        $admin = $this->makeInventoryUser('invout', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array(
                'ITEMNAME' => 'Beamer',
                'LAST_RECEIVER' => 'Bob',
                'BORROW_DATE' => '2030-04-01'
            ));

            $fresh = new ItemsData($this->getDatabase(), self::ORG_ID);
            $fresh->readItemData($this->uuidOfItem($itemId));
            $this->assertTrue($fresh->isBorrowed());
        });
    }

    /**
     * Test that an item can be taken out of service and back in
     *
     * @testdox Retiring an item and reinstating it changes its status
     */
    public function testRetiringAndReinstatingChangesTheStatus(): void
    {
        $admin = $this->makeInventoryUser('invretire', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Old chair'));

            $itemsData->retireItem();
            $retired = new Item($this->getDatabase(), null, $itemId);
            $this->assertTrue($retired->isRetired());
            $this->assertFalse($retired->isInUse());

            $itemsData->reinstateItem();
            $reinstated = new Item($this->getDatabase(), null, $itemId);
            $this->assertFalse($reinstated->isRetired());
            $this->assertTrue($reinstated->isInUse());

            // the status is an option of the STATUS field, stored on the item itself
            $sql = 'SELECT ifo_value FROM ' . TBL_INVENTORY_FIELD_OPTIONS . ' WHERE ifo_id = ?';
            $this->assertEquals(
                'SYS_INVENTORY_FILTER_IN_USE_ITEMS',
                $this->getDatabase()->queryPrepared($sql, [$reinstated->getStatus()])->fetchColumn()
            );
        });
    }

    /**
     * Test that deleting an item cleans up
     *
     * @testdox Deleting an item removes its values as well
     */
    public function testDeletingAnItemRemovesItsValues(): void
    {
        $admin = $this->makeInventoryUser('invdelete', true);

        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array(
                'ITEMNAME' => 'Broken lamp',
                'LAST_RECEIVER' => 'Carol',
                'BORROW_DATE' => '2030-05-01'
            ));

            $itemsData->deleteItem();
            $this->assertTrue($itemsData->isDeletedItem());

            return $itemId;
        });

        $db = $this->getDatabase();
        $this->assertFalse($db->queryPrepared('SELECT ini_id FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?', [$itemId])->fetch());
        $this->assertEquals(0, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?', [$itemId])->fetchColumn());
        $this->assertEquals(0, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' WHERE inb_ini_id = ?', [$itemId])->fetchColumn());
    }

    /**
     * Test that the inventory right is required
     *
     * @testdox A user without the inventory right may neither create nor edit an item
     */
    public function testUserWithoutTheInventoryRightMayNotCreateOrEditItems(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeInventoryUser('invowner', true);
        $plain = $fixture->createAndSaveUser('invplain', 'invplain@example.local');
        $plainUser = $this->loadUserInOrganization($plain['usr_id'], self::ORG_ID);

        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);

            return $this->createItem($itemsData, array('ITEMNAME' => 'Guarded item'));
        });

        $this->withCurrentUser($plainUser, self::ORG_ID, true, function () use ($itemId) {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $this->assertFalse($itemsData->isEditable());

            // reading is allowed, writing is not
            $itemsData->readItemData($this->uuidOfItem($itemId));
            $this->assertEquals('Guarded item', $itemsData->getValue('ITEMNAME'));

            $this->expectException(Exception::class);
            $itemsData->setValue('ITEMNAME', 'Renamed by an outsider');
        });
    }
}
