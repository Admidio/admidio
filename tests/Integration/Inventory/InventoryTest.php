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

use Admidio\Events\Entity\Event;
use Admidio\Events\Service\EventService;
use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Service\ItemService;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\Inventory\Service\ReservationService;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\UI\Presenter\EventFormPresenter;
use Admidio\UI\Presenter\InventoryItemPresenter;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\Users\Entity\User;
use DateTimeImmutable;

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
            $rights['rol_events'] = 1;
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

    /** Create a minimal inventory item assigned to another organization. */
    private function createForeignOrganizationItem(int $organizationId): int
    {
        $categoryId = (int)$this->getDatabase()->queryPrepared(
            "SELECT cat_id FROM " . TBL_CATEGORIES . " WHERE cat_type = 'IVT' AND cat_org_id = ?",
            [self::ORG_ID]
        )->fetchColumn();
        $statusId = (int)$this->getDatabase()->queryPrepared(
            "SELECT ifo_id FROM " . TBL_INVENTORY_FIELD_OPTIONS . "\n"
            . " INNER JOIN " . TBL_INVENTORY_FIELDS . " ON inf_id = ifo_inf_id\n"
            . " WHERE inf_org_id = ? AND inf_name_intern = 'STATUS' ORDER BY ifo_sequence",
            [self::ORG_ID]
        )->fetchColumn();

        $item = new Item($this->getDatabase());
        $item->setValue('ini_org_id', $organizationId);
        $item->setValue('ini_cat_id', $categoryId);
        $item->setValue('ini_status', $statusId);
        $item->save();

        return (int)$item->getValue('ini_id');
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

        // the name is checked within the organization, and this organization already has the
        // delivered system field KEEPER, so the new one is numbered
        $this->assertEquals('KEEPER_2', $names[2]);
    }

    /**
     * Test which right is needed to store an item field
     *
     * @testdox Storing an item field needs the inventory administrator right
     */
    public function testStoringAnItemFieldNeedsTheInventoryAdministratorRight(): void
    {
        $inventoryAdmin = $this->makeInventoryUser('invonly', false);

        $infId = $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($inventoryAdmin) {
            // the user administrates the inventory but not the organization
            $this->assertTrue($inventoryAdmin->isAdministratorInventory());
            $this->assertFalse($inventoryAdmin->isAdministrator());

            $field = new ItemField($this->getDatabase());
            $field->setValue('inf_org_id', self::ORG_ID);
            $field->setValue('inf_type', 'TEXT');
            $field->setValue('inf_name', 'Allowed for the inventory administrator');
            $this->assertTrue($field->save());

            return (int) $field->getValue('inf_id');
        });

        // save() and delete() ask for the same right, so the same user may remove it again
        $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($infId) {
            $field = new ItemField($this->getDatabase(), $infId);
            $this->assertTrue($field->delete());
        });
    }

    /**
     * Test that a user without the inventory right may not store an item field
     *
     * @testdox A user without the inventory right cannot store an item field
     */
    public function testStoringAnItemFieldWithoutTheInventoryRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRoleWithRights('Inventory outsiders', self::ORG_ID);
        $user = $fixture->createAndSaveUser('invnone', 'invnone@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $member = $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);

        $this->withCurrentUser($member, self::ORG_ID, true, function () use ($member) {
            $this->assertFalse($member->isAdministratorInventory());

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
     * The list and profile queries must agree on the meaning of the default filter: retired
     * items are hidden. This protects the profile view from accidentally showing only retired
     * items when it asks for items managed by a user.
     *
     * @testdox Inventory list queries exclude retired items by default
     */
    public function testItemListQueriesExcludeRetiredItemsByDefault(): void
    {
        $admin = $this->makeInventoryUser('invlistfilter', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($admin) {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $activeItemId = $this->createItem($itemsData, array('ITEMNAME' => 'Available chair', 'KEEPER' => (string) $admin->getValue('usr_id')));

            $retiredItemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $retiredItemId = $this->createItem($retiredItemsData, array('ITEMNAME' => 'Retired chair', 'KEEPER' => (string) $admin->getValue('usr_id')));
            $retiredItemsData->retireItem();

            $listedItems = new ItemsData($this->getDatabase(), self::ORG_ID);
            $listedItems->showRetiredItems(false);
            $listedItems->readItems();
            $listedItemIds = array_map(static fn (array $item): int => (int) $item['ini_id'], $listedItems->getItems());

            $profileItems = new ItemsData($this->getDatabase(), self::ORG_ID);
            $profileItems->showRetiredItems(false);
            $profileItems->readItemsByUser((int) $admin->getValue('usr_id'));
            $profileItemIds = array_map(static fn (array $item): int => (int) $item['ini_id'], $profileItems->getItems());

            $this->assertContains($activeItemId, $listedItemIds);
            $this->assertNotContains($retiredItemId, $listedItemIds);
            $this->assertContains($activeItemId, $profileItemIds);
            $this->assertNotContains($retiredItemId, $profileItemIds);
        });
    }

    /**
     * Profile pages must use the same inventory access levels as the inventory module.
     *
     * @testdox Inventory module access is denied to ordinary members at restricted levels
     */
    public function testRestrictedInventoryModuleAccessIsDeniedToOrdinaryMembers(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeInventoryUser('invaccessadmin', true);
        $memberData = $fixture->createAndSaveUser('invaccessmember', 'invaccessmember@example.local');
        $member = $this->loadUserInOrganization($memberData['usr_id'], self::ORG_ID);

        $this->withCurrentUser($member, self::ORG_ID, true, function () {
            $settings = $GLOBALS['gSettingsManager'];
            foreach (array(3, 4, 5) as $level) {
                $settings->set('inventory_module_enabled', (string) $level);
                $this->assertFalse(InventoryAccessService::canViewModule(), 'access level ' . $level);
            }
        });

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $settings = $GLOBALS['gSettingsManager'];
            foreach (array(3, 4, 5) as $level) {
                $settings->set('inventory_module_enabled', (string) $level);
                $this->assertTrue(InventoryAccessService::canViewModule(), 'access level ' . $level);
            }
        });
    }

    /**
     * Test that list preloading preserves the single-item data representation.
     *
     * @testdox A preloaded inventory list returns the same item, category, status and borrow values
     */
    public function testPreloadedItemDataMatchesSingleItemLoading(): void
    {
        $admin = $this->makeInventoryUser('invpreload', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $firstItemId = $this->createItem($itemsData, array(
                'ITEMNAME' => 'Preloaded projector',
                'LAST_RECEIVER' => 'Alice',
                'BORROW_DATE' => '2030-05-01'
            ));
            $secondItemId = $this->createItem($itemsData, array('ITEMNAME' => 'Preloaded ladder'));
            $uuids = array($this->uuidOfItem($firstItemId), $this->uuidOfItem($secondItemId));

            $singleItem = new ItemsData($this->getDatabase(), self::ORG_ID);
            $singleItem->readItemData($uuids[0]);
            $expected = array(
                'ITEMNAME' => $singleItem->getValue('ITEMNAME', 'database'),
                'CATEGORY' => $singleItem->getValue('CATEGORY', 'database'),
                'STATUS' => $singleItem->getValue('STATUS', 'database'),
                'LAST_RECEIVER' => $singleItem->getValue('LAST_RECEIVER', 'database'),
                'BORROW_DATE' => $singleItem->getValue('BORROW_DATE', 'database'),
                'isBorrowed' => $singleItem->isBorrowed(),
                'isRetired' => $singleItem->isRetired()
            );

            $preloadedItems = new ItemsData($this->getDatabase(), self::ORG_ID);
            $preloadedItems->preloadItemData($uuids);
            $preloadedItems->readItemData($uuids[0]);

            $this->assertSame($expected['ITEMNAME'], $preloadedItems->getValue('ITEMNAME', 'database'));
            $this->assertSame($expected['CATEGORY'], $preloadedItems->getValue('CATEGORY', 'database'));
            $this->assertSame($expected['STATUS'], $preloadedItems->getValue('STATUS', 'database'));
            $this->assertSame($expected['LAST_RECEIVER'], $preloadedItems->getValue('LAST_RECEIVER', 'database'));
            $this->assertSame($expected['BORROW_DATE'], $preloadedItems->getValue('BORROW_DATE', 'database'));
            $this->assertSame($expected['isBorrowed'], $preloadedItems->isBorrowed());
            $this->assertSame($expected['isRetired'], $preloadedItems->isRetired());

            $preloadedItems->readItemData($uuids[1]);
            $this->assertSame('Preloaded ladder', $preloadedItems->getValue('ITEMNAME', 'database'));
        });
    }

    /**
     * ItemService and ItemsData must apply the same keeper permission. The service is used by
     * write endpoints while ItemsData also controls the actions shown in the list.
     *
     * @testdox Keeper edit permission is consistent for inventory services and data objects
     */
    public function testKeeperEditPermissionIsConsistentAcrossInventoryLayers(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeInventoryUser('invkeeperadmin', true);
        $keeperData = $fixture->createAndSaveUser('invkeeper', 'invkeeper@example.local');
        $keeper = $this->loadUserInOrganization($keeperData['usr_id'], self::ORG_ID);

        $itemUuid = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($keeper) {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Keeper managed item', 'KEEPER' => (string) $keeper->getValue('usr_id')));

            return $this->uuidOfItem($itemId);
        });

        $this->withCurrentUser($keeper, self::ORG_ID, true, function () use ($itemUuid) {
            $settings = $GLOBALS['gSettingsManager'];
            $settings->set('inventory_allow_keeper_edit', '1');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemsData->readItemData($itemUuid);
            $itemService = new ItemService($this->getDatabase(), $itemUuid);
            $this->assertTrue($itemsData->isEditable());
            $this->assertTrue($itemService->isEditable());

            $settings->set('inventory_allow_keeper_edit', '0');
            $this->assertFalse($itemsData->isEditable());
            $this->assertFalse($itemService->isEditable());
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

    /**
     * @testdox Reservation management is unavailable while reservations are disabled
     */
    public function testReservationManagementRequiresEnabledSetting(): void
    {
        $admin = $this->makeInventoryUser('invreservationvisibility', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '0');
            $this->assertFalse(InventoryAccessService::canManageReservations());
            $this->assertFalse(InventoryAccessService::canRequestReservation());

            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $this->assertTrue(InventoryAccessService::canManageReservations());
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'roles');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requester_roles', '');
            $this->assertTrue(InventoryAccessService::canRequestReservation());
        });
    }

    /**
     * @testdox Reservation requesters receive an action column without inventory bulk selection
     */
    public function testReservationRequesterInventoryListContainsActionColumnWithoutSelection(): void
    {
        $memberData = $this->getFixture()->createAndSaveUser('invreservationrequester', 'invreservationrequester@example.local');
        $member = $this->loadUserInOrganization($memberData['usr_id'], self::ORG_ID);

        $this->withCurrentUser($member, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');

            $this->assertTrue(InventoryAccessService::canRequestReservation());
            $tableDefinition = (new InventoryPresenter(false))->prepareTableDefinition();

            $this->assertStringNotContainsString('<input type="checkbox"', implode('', $tableDefinition['headers']));
            $this->assertSame('<span style="display:block; min-width:40px;">&nbsp;</span>', end($tableDefinition['headers']));
        });
    }

    /**
     * @testdox Reservation notification roles accept multiple roles from the current organization
     */
    public function testReservationNotificationRolesAcceptMultipleRoles(): void
    {
        $admin = $this->makeInventoryUser('invreservationnotificationroles', true);
        $fixture = $this->getFixture();
        $firstRole = $fixture->createAndSaveRoleWithRights('Reservation notifications one', self::ORG_ID, array('rol_all_lists_view' => 1));
        $secondRole = $fixture->createAndSaveRoleWithRights('Reservation notifications two', self::ORG_ID, array('rol_all_lists_view' => 1));

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($firstRole, $secondRole) {
            $this->assertSame(
                $firstRole['rol_uuid'] . ',' . $secondRole['rol_uuid'],
                PreferenceDefinitions::normalize(
                    'inventory_reservation_notification_roles',
                    $firstRole['rol_uuid'] . ',' . $secondRole['rol_uuid']
                )
            );
            $this->assertSame(
                'requested,approved,rejected,cancelled,borrowed,returned',
                PreferenceDefinitions::normalize(
                    'inventory_reservation_manager_statuses',
                    'requested,approved,rejected,cancelled,borrowed,returned'
                )
            );
        });
    }

    /**
     * @testdox Disabled reservations cannot be managed through the service
     */
    public function testDisabledReservationsCannotBeManagedThroughTheService(): void
    {
        $admin = $this->makeInventoryUser('invreservationdisabledservice', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), array('ITEMNAME' => 'Disabled reservation projector'));
            $reservation = (new ReservationService($this->getDatabase()))->request(
                $itemId,
                new DateTimeImmutable('2031-01-01 10:00:00'),
                new DateTimeImmutable('2031-01-01 12:00:00')
            );

            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '0');
            $this->assertFalse(InventoryAccessService::canManageReservationItem($itemId));
            $this->expectException(Exception::class);
            (new ReservationService($this->getDatabase()))->approve($reservation);
        });
    }

    /**
     * @testdox Reservation entities reject invalid periods before saving
     */
    public function testReservationEntityRejectsInvalidPeriod(): void
    {
        $reservation = new Reservation($this->getDatabase());
        $reservation->setValue('ivr_ini_id', 1);
        $reservation->setValue('ivr_begin', '2031-01-01 12:00:00');
        $reservation->setValue('ivr_end', '2031-01-01 10:00:00');

        $this->expectException(Exception::class);
        $reservation->save();
    }

    /**
     * @testdox Reservation services reject inventory items from another organization
     */
    public function testReservationServicesRejectItemsFromAnotherOrganization(): void
    {
        $admin = $this->makeInventoryUser('invreservationforeignitem', true);
        $organization = $this->getFixture()->createAndSaveOrganization('Foreign Inventory Organization', 'FRGITEM01');

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($organization) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $foreignItemId = $this->createForeignOrganizationItem((int)$organization['org_id']);
            $service = new ReservationService($this->getDatabase());

            try {
                $service->isAvailable(
                    $foreignItemId,
                    new DateTimeImmutable('2030-04-01 10:00:00'),
                    new DateTimeImmutable('2030-04-01 12:00:00')
                );
                $this->fail('Availability must not be readable for an item from another organization.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }

            try {
                $service->request(
                    $foreignItemId,
                    new DateTimeImmutable('2030-04-01 10:00:00'),
                    new DateTimeImmutable('2030-04-01 12:00:00')
                );
                $this->fail('A reservation request must not accept an item from another organization.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }
        });
    }

    /**
     * @testdox Event synchronization rejects inventory items from another organization
     */
    public function testEventReservationSynchronizationRejectsItemsFromAnotherOrganization(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationforeignitem', true);
        $organization = $this->getFixture()->createAndSaveOrganization('Foreign Event Inventory Organization', 'FRGEVT01');
        $category = $this->getFixture()->createAndSaveCategory('Foreign inventory event', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($organization, $category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $foreignItemId = $this->createForeignOrganizationItem((int)$organization['org_id']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with foreign inventory item');
            $event->setValue('dat_begin', '2030-04-02 10:00:00');
            $event->setValue('dat_end', '2030-04-02 12:00:00');
            $event->save();

            try {
                (new ReservationService($this->getDatabase()))->syncEventReservations(
                    (int)$event->getValue('dat_id'),
                    [$foreignItemId],
                    new DateTimeImmutable('2030-04-02 10:00:00'),
                    new DateTimeImmutable('2030-04-02 12:00:00')
                );
                $this->fail('Event synchronization must not accept an item from another organization.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }
        });
    }

    /**
     * @testdox Reservation requests reject events from another organization
     */
    public function testReservationRequestRejectsEventFromAnotherOrganization(): void
    {
        $admin = $this->makeInventoryUser('invreservationforeignevent', true);
        $organization = $this->getFixture()->createAndSaveOrganization('Foreign Reservation Event Organization', 'FRGRES01');
        $category = $this->getFixture()->createAndSaveCategory('Foreign reservation event', 'EVT', (int)$organization['org_id']);
        $foreignRole = $this->getFixture()->createAndSaveRoleWithRights(
            'Foreign reservation event administrators',
            (int)$organization['org_id'],
            array('rol_administrator' => 1, 'rol_events' => 1)
        );
        $foreignUserData = $this->getFixture()->createAndSaveUser('foreignreservationevent', 'foreignreservationevent@example.local');
        $this->getFixture()->assignUserToRole($foreignUserData['usr_id'], $foreignRole['rol_id']);
        $foreignAdmin = $this->loadUserInOrganization($foreignUserData['usr_id'], (int)$organization['org_id']);

        $foreignEventId = $this->withCurrentUser($foreignAdmin, (int)$organization['org_id'], true, function () use ($category) {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Foreign reservation event');
            $event->setValue('dat_begin', '2030-04-03 10:00:00');
            $event->setValue('dat_end', '2030-04-03 12:00:00');
            $event->save();

            return (int)$event->getValue('dat_id');
        });

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($foreignEventId) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), array('ITEMNAME' => 'Local reservation item'));

            try {
                (new ReservationService($this->getDatabase()))->request(
                    $itemId,
                    new DateTimeImmutable('2030-04-03 10:00:00'),
                    new DateTimeImmutable('2030-04-03 12:00:00'),
                    '',
                    '',
                    $foreignEventId
                );
                $this->fail('A reservation request must not accept an event from another organization.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }
        });
    }

    /**
     * @testdox Item keepers may manage reservations when the organization permits it
     */
    public function testKeeperMayManageReservationsWhenEnabled(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeInventoryUser('invreservationkeeperadmin', true);
        $keeperData = $fixture->createAndSaveUser('invreservationkeeper', 'invreservationkeeper@example.local');
        $keeper = $this->loadUserInOrganization($keeperData['usr_id'], self::ORG_ID);

        $itemIds = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($keeper) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemIds = array(
                'own' => $this->createItem($itemsData, array(
                    'ITEMNAME' => 'Reservation keeper item',
                    'KEEPER' => (string)$keeper->getValue('usr_id')
                )),
                'foreign' => $this->createItem($itemsData, array(
                    'ITEMNAME' => 'Reservation administrator item'
                ))
            );
            $reservation = (new ReservationService($this->getDatabase()))->request(
                $itemIds['foreign'],
                new DateTimeImmutable('2030-05-01 10:00:00'),
                new DateTimeImmutable('2030-05-01 12:00:00')
            );
            $itemIds['foreignReservation'] = (int)$reservation->getValue('ivr_id');

            return $itemIds;
        });

        $this->withCurrentUser($keeper, self::ORG_ID, true, function () use ($itemIds) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_allow_keeper_edit', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_keepers_manage', '1');
            $this->assertTrue(InventoryAccessService::canManageReservations());
            $this->assertTrue(InventoryAccessService::canManageReservationItem($itemIds['own']));
            $this->assertFalse(InventoryAccessService::canManageReservationItem($itemIds['foreign']));

            $service = new ReservationService($this->getDatabase());
            $foreignReservation = new Reservation($this->getDatabase(), $itemIds['foreignReservation']);
            $actions = array(
                'approve' => static fn () => $service->approve($foreignReservation),
                'reject' => static fn () => $service->changeStatus($foreignReservation, Reservation::STATUS_REJECTED),
                'cancel' => static fn () => $service->changeStatus($foreignReservation, Reservation::STATUS_CANCELLED),
                'start borrowing' => static fn () => $service->startBorrowing($foreignReservation),
                'finish borrowing' => static fn () => $service->finishBorrowing($foreignReservation),
                'withdraw' => static fn () => $service->withdraw($foreignReservation)
            );
            foreach ($actions as $actionName => $action) {
                try {
                    $action();
                    $this->fail('A keeper must not ' . $actionName . ' a reservation for another keeper\'s item.');
                } catch (Exception $exception) {
                    $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
                }
            }

            $GLOBALS['gSettingsManager']->set('inventory_reservation_keepers_manage', '0');
            $this->assertFalse(InventoryAccessService::canManageReservations());

            $GLOBALS['gSettingsManager']->set('inventory_reservation_keepers_manage', '1');
            $GLOBALS['gSettingsManager']->set('inventory_allow_keeper_edit', '0');
            $this->assertFalse(InventoryAccessService::canManageReservations());
            $this->assertFalse(InventoryAccessService::canManageReservationItem($itemIds['own']));
        });
    }

    /**
     * @testdox Automatic reservations immediately block an overlapping period
     */
    public function testAutomaticReservationBlocksOverlappingPeriod(): void
    {
        $admin = $this->makeInventoryUser('invreservationauto', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Reservation projector'));
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-06-01 10:00:00'),
                new DateTimeImmutable('2030-06-01 12:00:00')
            );

            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));
            $this->assertFalse($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-06-01 11:00:00'),
                new DateTimeImmutable('2030-06-01 13:00:00')
            ));
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-06-01 12:00:00'),
                new DateTimeImmutable('2030-06-01 13:00:00')
            ));
        });
    }

    /**
     * @testdox Manual reservations block their period only after approval
     */
    public function testManualReservationBlocksPeriodAfterApproval(): void
    {
        $admin = $this->makeInventoryUser('invreservationmanual', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Manual reservation projector'));
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-01 10:00:00'),
                new DateTimeImmutable('2030-07-01 12:00:00')
            );

            $this->assertSame(Reservation::STATUS_REQUESTED, $reservation->getValue('ivr_status'));
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-07-01 11:00:00'),
                new DateTimeImmutable('2030-07-01 13:00:00')
            ));

            $service->approve($reservation);
            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));
            $this->assertFalse($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-07-01 11:00:00'),
                new DateTimeImmutable('2030-07-01 13:00:00')
            ));
        });
    }

    /**
     * @testdox A manual request is rejected when its period is already confirmed
     */
    public function testManualReservationRequestRejectsConfirmedOverlappingPeriod(): void
    {
        $admin = $this->makeInventoryUser('invreservationmanualconflict', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Confirmed manual reservation projector'));
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 10:00:00'),
                new DateTimeImmutable('2030-07-15 12:00:00')
            );
            $service->approve($reservation);

            $this->expectException(Exception::class);
            $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 11:00:00'),
                new DateTimeImmutable('2030-07-15 13:00:00')
            );
        });
    }

    /**
     * @testdox Reservations allow only the defined lifecycle transitions
     */
    public function testReservationLifecycleAllowsValidTransitions(): void
    {
        $admin = $this->makeInventoryUser('invreservationtransitions', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), array('ITEMNAME' => 'Lifecycle projector'));
            $service = new ReservationService($this->getDatabase());
            $request = static fn (string $begin) => $service->request(
                $itemId,
                new DateTimeImmutable($begin),
                new DateTimeImmutable($begin . ' +1 hour')
            );

            $approved = $request('2030-11-01 10:00:00');
            $service->approve($approved);
            $this->assertSame(Reservation::STATUS_APPROVED, $approved->getValue('ivr_status'));

            $rejected = $request('2030-11-02 10:00:00');
            $service->changeStatus($rejected, Reservation::STATUS_REJECTED);
            $this->assertSame(Reservation::STATUS_REJECTED, $rejected->getValue('ivr_status'));

            $cancelledRequest = $request('2030-11-03 10:00:00');
            $service->withdraw($cancelledRequest);
            $this->assertSame(Reservation::STATUS_CANCELLED, $cancelledRequest->getValue('ivr_status'));

            $borrowed = $request('2030-11-04 10:00:00');
            $service->approve($borrowed);
            $service->startBorrowing($borrowed);
            $this->assertSame(Reservation::STATUS_BORROWED, $borrowed->getValue('ivr_status'));
            $service->finishBorrowing($borrowed);
            $this->assertSame(Reservation::STATUS_RETURNED, $borrowed->getValue('ivr_status'));

            $cancelledApproved = $request('2030-11-05 10:00:00');
            $service->approve($cancelledApproved);
            $service->changeStatus($cancelledApproved, Reservation::STATUS_CANCELLED);
            $this->assertSame(Reservation::STATUS_CANCELLED, $cancelledApproved->getValue('ivr_status'));
        });
    }

    /**
     * @testdox Completed reservations cannot be changed through invalid lifecycle transitions
     */
    public function testReservationLifecycleRejectsInvalidTransitions(): void
    {
        $admin = $this->makeInventoryUser('invreservationinvalidtransitions', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), array('ITEMNAME' => 'Invalid lifecycle projector'));
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-12-01 10:00:00'),
                new DateTimeImmutable('2030-12-01 12:00:00')
            );

            foreach (array(
                static fn () => $service->startBorrowing($reservation),
                static fn () => $service->finishBorrowing($reservation)
            ) as $transition) {
                try {
                    $transition();
                    $this->fail('A requested reservation must reject invalid lifecycle transitions.');
                } catch (Exception $exception) {
                    $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
                }
            }

            $service->approve($reservation);
            try {
                $service->changeStatus($reservation, Reservation::STATUS_REJECTED);
                $this->fail('An approved reservation must not be rejected.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }

            $service->startBorrowing($reservation);
            try {
                $service->changeStatus($reservation, Reservation::STATUS_CANCELLED);
                $this->fail('A handed-over reservation must not be cancelled.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }

            $service->finishBorrowing($reservation);
            foreach (array(
                static fn () => $service->approve($reservation),
                static fn () => $service->changeStatus($reservation, Reservation::STATUS_CANCELLED)
            ) as $transition) {
                try {
                    $transition();
                    $this->fail('A returned reservation must not be changed again.');
                } catch (Exception $exception) {
                    $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
                }
            }
        });
    }

    /**
     * @testdox Event reservations follow the configured manual approval workflow
     */
    public function testEventReservationIsRequestedWithManualApproval(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationmanual', true);
        $category = $this->getFixture()->createAndSaveCategory('Reservation Events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Event reservation projector'));
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with equipment request');
            $event->setValue('dat_begin', '2030-08-01 10:00:00');
            $event->setValue('dat_end', '2030-08-01 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int)$event->getValue('dat_id'),
                array($itemId),
                new DateTimeImmutable('2030-08-01 10:00:00'),
                new DateTimeImmutable('2030-08-01 12:00:00')
            );

            $status = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                array((int)$event->getValue('dat_id'))
            )->fetchColumn();
            $this->assertSame(Reservation::STATUS_REQUESTED, $status);
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-08-01 11:00:00'),
                new DateTimeImmutable('2030-08-01 13:00:00')
            ));
        });
    }

    /**
     * @testdox A rejected event reservation can be requested again
     */
    public function testRejectedEventReservationCanBeRequestedAgain(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationagain', true);
        $category = $this->getFixture()->createAndSaveCategory('Repeated reservation events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Repeated event reservation projector'));
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with repeated equipment request');
            $event->setValue('dat_begin', '2030-08-15 10:00:00');
            $event->setValue('dat_end', '2030-08-15 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int)$event->getValue('dat_id'),
                array($itemId),
                new DateTimeImmutable('2030-08-15 10:00:00'),
                new DateTimeImmutable('2030-08-15 12:00:00')
            );

            $reservationId = (int)$this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                array((int)$event->getValue('dat_id'))
            )->fetchColumn();
            $service->changeStatus(new Reservation($this->getDatabase(), $reservationId), Reservation::STATUS_REJECTED);

            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            $_POST = array('adm_csrf_token' => $session->getCsrfToken());
            try {
                $response = (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string)$event->getValue('dat_uuid'),
                    $itemId
                );
                $this->assertSame('success', $response['status']);
                $this->assertSame(Reservation::STATUS_REQUESTED, $response['reservation_status']);

                try {
                    (new EventService($this->getDatabase()))->requestReservationAgain(
                        (string)$event->getValue('dat_uuid'),
                        $itemId
                    );
                    $this->fail('Only the latest rejected or cancelled reservation may be requested again.');
                } catch (Exception $exception) {
                    $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
                }
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
                $_POST = $previousPost;
            }

            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? ORDER BY ivr_id',
                array((int)$event->getValue('dat_id'))
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame(array(Reservation::STATUS_REJECTED, Reservation::STATUS_REQUESTED), $statuses);

            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '0');
            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            $_POST = array('adm_csrf_token' => $session->getCsrfToken());
            try {
                (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string)$event->getValue('dat_uuid'),
                    $itemId
                );
                $this->fail('Event reservation requests must respect the event reservation setting.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
                $_POST = $previousPost;
            }
        });
    }

    /**
     * @testdox The event reservation status data contains only the current retry action and honors DataTables paging and sorting
     */
    public function testEventReservationStatusDataHonorsLatestReservationAndDataTablesParameters(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationstatus', true);
        $category = $this->getFixture()->createAndSaveCategory('Reservation status events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $rejectedItemId = $this->createItem($itemsData, array('ITEMNAME' => 'A rejected event item'));
            $requestedItemId = $this->createItem($itemsData, array('ITEMNAME' => 'Z requested event item'));
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event reservation status');
            $event->setValue('dat_begin', '2030-08-20 10:00:00');
            $event->setValue('dat_end', '2030-08-20 12:00:00');
            $event->save();

            $reservationService = new ReservationService($this->getDatabase());
            $reservationService->syncEventReservations(
                (int)$event->getValue('dat_id'),
                array($rejectedItemId, $requestedItemId),
                new DateTimeImmutable('2030-08-20 10:00:00'),
                new DateTimeImmutable('2030-08-20 12:00:00')
            );
            $rejectedReservationId = (int)$this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_ini_id = ?',
                array((int)$event->getValue('dat_id'), $rejectedItemId)
            )->fetchColumn();
            $reservationService->changeStatus(new Reservation($this->getDatabase(), $rejectedReservationId), Reservation::STATUS_REJECTED);

            $presenter = new EventFormPresenter();
            $data = $presenter->getReservationStatusData(
                (string)$event->getValue('dat_uuid'),
                'this',
                7,
                0,
                1,
                '',
                1,
                'asc'
            );

            $this->assertSame(7, $data['draw']);
            $this->assertSame(2, $data['recordsTotal']);
            $this->assertSame(2, $data['recordsFiltered']);
            $this->assertCount(1, $data['data']);
            $this->assertStringContainsString('A rejected event item', $data['data'][0][1]);
            $this->assertStringContainsString('event-inventory-reservation-request-again', $data['data'][0][3]);

            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            $_POST = array('adm_csrf_token' => $session->getCsrfToken());
            try {
                $response = (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string)$event->getValue('dat_uuid'),
                    $rejectedItemId
                );
                $this->assertSame('success', $response['status']);
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
                $_POST = $previousPost;
            }

            $dataAfterRequestAgain = $presenter->getReservationStatusData(
                (string)$event->getValue('dat_uuid'),
                'this',
                8,
                0,
                25,
                '',
                -1,
                'desc'
            );
            $this->assertSame(3, $dataAfterRequestAgain['recordsTotal']);
            foreach ($dataAfterRequestAgain['data'] as $row) {
                $this->assertSame('', $row[3]);
            }

            $filteredData = $presenter->getReservationStatusData(
                (string)$event->getValue('dat_uuid'),
                'this',
                9,
                0,
                25,
                'requested',
                -1,
                'desc'
            );

            $this->assertSame(3, $filteredData['recordsTotal']);
            $this->assertSame(2, $filteredData['recordsFiltered']);
            $this->assertStringContainsString('A rejected event item', implode(' ', array_column($filteredData['data'], 1)));
            $this->assertStringContainsString('Z requested event item', implode(' ', array_column($filteredData['data'], 1)));
        });
    }

    /**
     * @testdox Event reservation status data requires the right to edit the event
     */
    public function testEventReservationStatusDataRequiresEventEditPermission(): void
    {
        $eventAdmin = $this->makeInventoryUser('inveventreservationstatusadmin', true);
        $inventoryAdmin = $this->makeInventoryUser('inveventreservationstatusinventory', false);
        $category = $this->getFixture()->createAndSaveCategory('Reservation status access events', 'EVT', self::ORG_ID);

        $eventUuid = $this->withCurrentUser($eventAdmin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Protected event reservation status');
            $event->setValue('dat_begin', '2030-08-21 10:00:00');
            $event->setValue('dat_end', '2030-08-21 12:00:00');
            $event->save();

            return (string)$event->getValue('dat_uuid');
        });

        $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($eventUuid) {
            try {
                (new EventFormPresenter())->getReservationStatusData($eventUuid, 'this', 1, 0, 25, '', -1, 'desc');
                $this->fail('An inventory administrator must not read reservation data for an event they cannot edit.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            }
        });
    }

    /**
     * @testdox Moving an event keeps its approval and rejects conflicting periods
     */
    public function testMovingApprovedEventReservationRejectsConflictsWithoutChangingExistingReservation(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationmove', true);
        $category = $this->getFixture()->createAndSaveCategory('Moving reservation events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, array('ITEMNAME' => 'Moving event reservation projector'));
            $service = new ReservationService($this->getDatabase());

            $firstEvent = new Event($this->getDatabase());
            $firstEvent->setValue('dat_cat_id', $category['cat_id']);
            $firstEvent->setValue('dat_headline', 'First reservation event');
            $firstEvent->setValue('dat_begin', '2030-09-01 10:00:00');
            $firstEvent->setValue('dat_end', '2030-09-01 12:00:00');
            $firstEvent->save();
            $service->syncEventReservations(
                (int)$firstEvent->getValue('dat_id'),
                array($itemId),
                new DateTimeImmutable('2030-09-01 10:00:00'),
                new DateTimeImmutable('2030-09-01 12:00:00')
            );

            $secondEvent = new Event($this->getDatabase());
            $secondEvent->setValue('dat_cat_id', $category['cat_id']);
            $secondEvent->setValue('dat_headline', 'Second reservation event');
            $secondEvent->setValue('dat_begin', '2030-09-01 13:00:00');
            $secondEvent->setValue('dat_end', '2030-09-01 15:00:00');
            $secondEvent->save();
            $service->syncEventReservations(
                (int)$secondEvent->getValue('dat_id'),
                array($itemId),
                new DateTimeImmutable('2030-09-01 13:00:00'),
                new DateTimeImmutable('2030-09-01 15:00:00')
            );

            $secondReservation = $this->getDatabase()->queryPrepared(
                'SELECT ivr_id, ivr_status, ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                array((int)$secondEvent->getValue('dat_id'))
            )->fetch();
            $this->assertSame(Reservation::STATUS_APPROVED, $secondReservation['ivr_status']);

            try {
                $service->syncEventReservations(
                    (int)$secondEvent->getValue('dat_id'),
                    array($itemId),
                    new DateTimeImmutable('2030-09-01 11:00:00'),
                    new DateTimeImmutable('2030-09-01 14:00:00')
                );
                $this->fail('Moving an event reservation into another approved reservation must fail.');
            } catch (\Admidio\Infrastructure\Exception $exception) {
                $this->assertSame('SYS_INVENTORY_RESERVATION_NOT_AVAILABLE', $exception->getTranslationId());
            }

            $unchangedReservation = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status, ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_id = ?',
                array((int)$secondReservation['ivr_id'])
            )->fetch();
            $this->assertSame(Reservation::STATUS_APPROVED, $unchangedReservation['ivr_status']);
            $this->assertSame('2030-09-01 13:00:00', $unchangedReservation['ivr_begin']);
            $this->assertSame('2030-09-01 15:00:00', $unchangedReservation['ivr_end']);
        });
    }

    /**
     * @testdox Removing an event item cancels open reservations but retains a handed-over item
     */
    public function testRemovingEventItemsCancelsOnlyReservationsThatAreNotBorrowed(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationremove', true);
        $category = $this->getFixture()->createAndSaveCategory('Removing reservation events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $borrowedItemId = $this->createItem($itemsData, array('ITEMNAME' => 'Handed over event projector'));
            $openItemId = $this->createItem($itemsData, array('ITEMNAME' => 'Open event microphone'));
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event removing equipment');
            $event->setValue('dat_begin', '2030-10-01 10:00:00');
            $event->setValue('dat_end', '2030-10-01 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int)$event->getValue('dat_id'),
                array($borrowedItemId, $openItemId),
                new DateTimeImmutable('2030-10-01 10:00:00'),
                new DateTimeImmutable('2030-10-01 12:00:00')
            );

            $borrowedReservation = new Reservation($this->getDatabase(), (int)$this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_ini_id = ?',
                array((int)$event->getValue('dat_id'), $borrowedItemId)
            )->fetchColumn());
            $service->startBorrowing($borrowedReservation);

            $service->syncEventReservations(
                (int)$event->getValue('dat_id'),
                array(),
                new DateTimeImmutable('2030-10-01 10:00:00'),
                new DateTimeImmutable('2030-10-01 12:00:00')
            );

            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_ini_id, ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                array((int)$event->getValue('dat_id'))
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            $this->assertSame(Reservation::STATUS_BORROWED, $statuses[$borrowedItemId]);
            $this->assertSame(Reservation::STATUS_CANCELLED, $statuses[$openItemId]);
        });
    }
}
