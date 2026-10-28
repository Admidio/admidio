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
use Admidio\Events\Service\EventSaveService;
use Admidio\Events\Service\EventService;
use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Service\ItemService;
use Admidio\Inventory\Service\InventoryAccessService;
use Admidio\Inventory\Service\ReservationService;
use Admidio\Inventory\Service\ReservationNotificationService;
use Admidio\Inventory\Entity\Reservation;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\UI\Presenter\EventFormPresenter;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InventoryItemPresenter;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\UI\Presenter\InventoryBorrowPresenter;
use Admidio\Users\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Process\Process;

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
        $categoryId = (int) $this->getDatabase()->queryPrepared(
            "SELECT cat_id FROM " . TBL_CATEGORIES . " WHERE cat_type = 'IVT' AND cat_org_id = ?",
            [self::ORG_ID],
        )->fetchColumn();
        $statusId = (int) $this->getDatabase()->queryPrepared(
            "SELECT ifo_id FROM " . TBL_INVENTORY_FIELD_OPTIONS . "\n"
            . " INNER JOIN " . TBL_INVENTORY_FIELDS . " ON inf_id = ifo_inf_id\n"
            . " WHERE inf_org_id = ? AND inf_name_intern = 'STATUS' ORDER BY ifo_sequence",
            [self::ORG_ID],
        )->fetchColumn();

        $item = new Item($this->getDatabase());
        $item->setValue('ini_org_id', $organizationId);
        $item->setValue('ini_cat_id', $categoryId);
        $item->setValue('ini_status', $statusId);
        $item->save();

        return (int) $item->getValue('ini_id');
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

    public function testParallelServiceCreationsCommitBothItems(): void
    {
        $database = $this->getDatabase();
        $names = ['Parallel camera ' . bin2hex(random_bytes(8)), 'Parallel tripod ' . bin2hex(random_bytes(8))];
        $startFile = tempnam(sys_get_temp_dir(), 'admidio-item-creation-');
        $processes = [];
        unlink($startFile);
        // The workers use their own connections and transactions, like real requests.
        $database->endTransaction();
        try {
            foreach ($names as $name) {
                $process = new Process([PHP_BINARY, __DIR__ . '/item_creation_worker.php', 'parallel', $name, $startFile]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            touch($startFile);
            foreach ($processes as $index => $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('', $result['error']);
                $this->assertFalse($result['transactionOpen']);
                $this->assertGreaterThan(0, $result['itemId']);
                $this->assertSame($names[$index], $database->queryPrepared(
                    'SELECT ind_value FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ? AND ind_value = ?',
                    [$result['itemId'], $names[$index]]
                )->fetchColumn());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            $ids = $database->queryPrepared('SELECT ind_ini_id FROM ' . TBL_INVENTORY_ITEM_DATA
                . ' WHERE ind_value IN (?, ?)', $names)->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?', [$id]);
                $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' WHERE inb_ini_id = ?', [$id]);
                $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?', [$id]);
            }
            $database->queryPrepared('DELETE FROM ' . TBL_LOG_CHANGES
                . " WHERE log_table IN ('inventory_items', 'inventory_item_data') AND log_record_name IN (?, ?)", $names);
            if (file_exists($startFile)) {
                unlink($startFile);
            }
            $database->startTransaction();
        }
    }

    public function testFailedNewItemDoesNotLeaveAnIncompleteRecord(): void
    {
        foreach (['required-field', 'return-date'] as $failure) {
            $process = new Process([PHP_BINARY, __DIR__ . '/item_creation_worker.php', $failure]);
            $process->setTimeout(30);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertNotSame('', $result['error'], $failure);
            if ($failure === 'return-date') {
                $this->assertSame('SYS_INVENTORY_RETURN_DATE_MUST_NOT_BE_FUTURE', $result['errorId']);
            } else {
                $this->assertStringContainsString('empty', strtolower($result['error']));
            }
            $this->assertSame($result['before'], $result['after'], $failure);
            $this->assertFalse($result['transactionOpen'], $failure);
        }
    }

    public function testInterleavedItemCreationPreservesBothItems(): void
    {
        $admin = $this->makeInventoryUser('invinterleaved', true);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $first = new ItemsData($this->getDatabase(), self::ORG_ID);
            $first->readItemData('');
            $first->createNewItem($this->inventoryCategoryUuid());
            $firstId = $first->getItemId();

            // Pause the first creation before its field values are saved, as can
            // happen while a second request creates another item.
            $second = new ItemsData($this->getDatabase(), self::ORG_ID);
            $second->readItemData('');
            $second->createNewItem($this->inventoryCategoryUuid());
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?', [$firstId]
            )->fetchColumn());

            $first->setValue('ITEMNAME', 'First interleaved item');
            $first->saveItemData();
            $second->setValue('ITEMNAME', 'Second interleaved item');
            $second->saveItemData();
            $loaded = new ItemsData($this->getDatabase(), self::ORG_ID);
            foreach ([$first, $second] as $created) {
                $loaded->readItemData($this->uuidOfItem($created->getItemId()));
                $this->assertSame($created->getValue('ITEMNAME'), $loaded->getValue('ITEMNAME'));
            }
        });
    }

    public function testPresenterLoadsItemsOnlyWhenPreparingRows(): void
    {
        $admin = $this->makeInventoryUser('invlazyrows', true);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $this->createItem($items, ['ITEMNAME' => 'Lazy camera']);
            $this->createItem($items, ['ITEMNAME' => 'Lazy tripod']);

            $presenter = new class(false) extends InventoryPresenter {
                public function loadedItemCount(): int
                {
                    return count($this->itemsData->getItems());
                }
            };

            $this->assertSame(0, $presenter->loadedItemCount());
            $this->assertNotEmpty($presenter->prepareTableDefinition('csv')['export_headers']);
            $this->assertSame(0, $presenter->loadedItemCount());
            $this->assertCount(2, $presenter->prepareData('csv')['rows']);
            $this->assertSame(2, $presenter->loadedItemCount());
        });
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
                'BORROW_DATE' => '2020-03-01',
                'RETURN_DATE' => '2020-03-31',
            ));
        });

        $sql = 'SELECT inb_last_receiver, inb_borrow_date, inb_return_date FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . '
                 WHERE inb_ini_id = ?';
        $rows = $this->getDatabase()->queryPrepared($sql, [$itemId])->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['inb_last_receiver']);
        $this->assertStringStartsWith('2020-03-01', $rows[0]['inb_borrow_date']);
        $this->assertStringStartsWith('2020-03-31', $rows[0]['inb_return_date']);

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
     * @testdox An actual return date cannot be in the future
     */
    public function testFutureReturnDateIsRejected(): void
    {
        $admin = $this->makeInventoryUser('invfuturedate', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);

            try {
                $this->createItem($itemsData, [
                    'ITEMNAME' => 'Projector with future return date',
                    'LAST_RECEIVER' => 'Alice',
                    'BORROW_DATE' => '2020-03-01',
                    'RETURN_DATE' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d'),
                ]);
                $this->fail('A future return date must be rejected.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVENTORY_RETURN_DATE_MUST_NOT_BE_FUTURE', $exception->getTranslationId());
            }
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
     * @testdox Open reservations block retiring and deleting an item
     */
    public function testDeletingAnItemWithReservationsPreservesItsData(): void
    {
        $admin = $this->makeInventoryUser('invdeletereserveditem', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, [
                'ITEMNAME' => 'Reserved lamp',
                'LAST_RECEIVER' => 'Carol',
                'BORROW_DATE' => '2030-05-01',
            ]);
            $reservationService = new ReservationService($this->getDatabase());
            $reservation = $reservationService->request(
                $itemId,
                new DateTimeImmutable('2030-06-01 10:00:00'),
                new DateTimeImmutable('2030-06-01 12:00:00'),
            );

            try {
                $itemsData->deleteItem();
                $this->fail('An item with an open reservation must not be deleted.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_ITEM_HAS_OPEN_RESERVATIONS', $exception->getTranslationId());
            }
            try {
                $itemsData->retireItem();
                $this->fail('An item with an open reservation must not be retired.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_ITEM_HAS_OPEN_RESERVATIONS', $exception->getTranslationId());
            }

            $this->assertFalse($itemsData->isDeletedItem());
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?',
                [$itemId],
            )->fetchColumn());
            $this->assertGreaterThan(0, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?',
                [$itemId],
            )->fetchColumn());
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' WHERE inb_ini_id = ?',
                [$itemId],
            )->fetchColumn());

            $reservationService->changeStatus($reservation, Reservation::STATUS_CANCELLED);
            $itemsData->deleteItem();

            $this->assertTrue($itemsData->isDeletedItem());
            $this->assertSame(0, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchColumn());
        });
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
     * @testdox Guest reservation requests require separate first and last name fields
     */
    public function testGuestReservationRequestFormContainsSeparateNameFields(): void
    {
        $guestData = $this->getFixture()->createAndSaveUser('invreservationguest', 'invreservationguest@example.local');
        $guest = $this->loadUserInOrganization($guestData['usr_id'], self::ORG_ID);

        $this->withCurrentUser($guest, self::ORG_ID, false, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'guests');
            $GLOBALS['gSettingsManager']->set('captcha_enabled', '1');
            $previousSession = $GLOBALS['gCurrentSession'];
            $session = new class {
                public ?FormPresenter $form = null;

                public function getCsrfToken(): string
                {
                    return 'test-csrf-token';
                }

                public function addFormObject(FormPresenter $form): bool
                {
                    $this->form = $form;
                    return true;
                }
            };
            $GLOBALS['gCurrentSession'] = $session;
            try {
                (new InventoryItemPresenter())->createReservationRequestForm();
                $form = $session->form;

                $this->assertNotNull($form);
                $this->assertTrue($form->hasElement('guest_first_name'));
                $this->assertTrue($form->hasElement('guest_last_name'));
                $this->assertTrue($form->hasElement('guest_email'));
                $this->assertTrue($form->hasElement('adm_captcha_code'));
                $this->assertFalse($form->hasElement('guest_name'));
                $guestElementIds = array_values(array_filter(
                    array_keys($form->getElements()),
                    static fn(string $id): bool => str_starts_with($id, 'guest_'),
                ));
                $this->assertSame(['guest_last_name', 'guest_first_name', 'guest_email'], $guestElementIds);
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
            }
        });
    }

    /**
     * @testdox Reservation notification roles accept multiple roles from the current organization
     */
    public function testReservationNotificationRolesAcceptMultipleRoles(): void
    {
        $admin = $this->makeInventoryUser('invreservationnotificationroles', true);
        $fixture = $this->getFixture();
        $firstRole = $fixture->createAndSaveRoleWithRights('Reservation notifications one', self::ORG_ID, ['rol_all_lists_view' => 1]);
        $secondRole = $fixture->createAndSaveRoleWithRights('Reservation notifications two', self::ORG_ID, ['rol_all_lists_view' => 1]);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($firstRole, $secondRole) {
            $this->assertSame(
                $firstRole['rol_uuid'] . ',' . $secondRole['rol_uuid'],
                PreferenceDefinitions::normalize(
                    'inventory_reservation_notification_roles',
                    $firstRole['rol_uuid'] . ',' . $secondRole['rol_uuid'],
                ),
            );
            $this->assertSame(
                'requested,approved,rejected,cancelled,borrowed,returned',
                PreferenceDefinitions::normalize(
                    'inventory_reservation_manager_statuses',
                    'requested,approved,rejected,cancelled,borrowed,returned',
                ),
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
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Disabled reservation projector']);
            $reservation = (new ReservationService($this->getDatabase()))->request(
                $itemId,
                new DateTimeImmutable('2031-01-01 10:00:00'),
                new DateTimeImmutable('2031-01-01 12:00:00'),
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
            $foreignItemId = $this->createForeignOrganizationItem((int) $organization['org_id']);
            $service = new ReservationService($this->getDatabase());

            $this->assertFalse(InventoryAccessService::canManageReservationItem($foreignItemId));

            try {
                $service->isAvailable(
                    $foreignItemId,
                    new DateTimeImmutable('2030-04-01 10:00:00'),
                    new DateTimeImmutable('2030-04-01 12:00:00'),
                );
                $this->fail('Availability must not be readable for an item from another organization.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }

            try {
                $service->request(
                    $foreignItemId,
                    new DateTimeImmutable('2030-04-01 10:00:00'),
                    new DateTimeImmutable('2030-04-01 12:00:00'),
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
            $foreignItemId = $this->createForeignOrganizationItem((int) $organization['org_id']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with foreign inventory item');
            $event->setValue('dat_begin', '2030-04-02 10:00:00');
            $event->setValue('dat_end', '2030-04-02 12:00:00');
            $event->save();

            try {
                (new ReservationService($this->getDatabase()))->syncEventReservations(
                    (int) $event->getValue('dat_id'),
                    [$foreignItemId],
                    new DateTimeImmutable('2030-04-02 10:00:00'),
                    new DateTimeImmutable('2030-04-02 12:00:00'),
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
        $category = $this->getFixture()->createAndSaveCategory('Foreign reservation event', 'EVT', (int) $organization['org_id']);
        $foreignRole = $this->getFixture()->createAndSaveRoleWithRights(
            'Foreign reservation event administrators',
            (int) $organization['org_id'],
            ['rol_administrator' => 1, 'rol_events' => 1],
        );
        $foreignUserData = $this->getFixture()->createAndSaveUser('foreignreservationevent', 'foreignreservationevent@example.local');
        $this->getFixture()->assignUserToRole($foreignUserData['usr_id'], $foreignRole['rol_id']);
        $foreignAdmin = $this->loadUserInOrganization($foreignUserData['usr_id'], (int) $organization['org_id']);

        $foreignEventId = $this->withCurrentUser($foreignAdmin, (int) $organization['org_id'], true, function () use ($category) {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Foreign reservation event');
            $event->setValue('dat_begin', '2030-04-03 10:00:00');
            $event->setValue('dat_end', '2030-04-03 12:00:00');
            $event->save();

            return (int) $event->getValue('dat_id');
        });

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($foreignEventId) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Local reservation item']);

            try {
                (new ReservationService($this->getDatabase()))->request(
                    $itemId,
                    new DateTimeImmutable('2030-04-03 10:00:00'),
                    new DateTimeImmutable('2030-04-03 12:00:00'),
                    '',
                    '',
                    $foreignEventId,
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
            $itemIds = [
                'own' => $this->createItem($itemsData, [
                    'ITEMNAME' => 'Reservation keeper item',
                    'KEEPER' => (string) $keeper->getValue('usr_id'),
                ]),
                'foreign' => $this->createItem($itemsData, [
                    'ITEMNAME' => 'Reservation administrator item',
                ]),
            ];
            $reservation = (new ReservationService($this->getDatabase()))->request(
                $itemIds['foreign'],
                new DateTimeImmutable('2030-05-01 10:00:00'),
                new DateTimeImmutable('2030-05-01 12:00:00'),
            );
            $itemIds['foreignReservation'] = (int) $reservation->getValue('ivr_id');

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
            $actions = [
                'approve' => static fn() => $service->approve($foreignReservation),
                'reject' => static fn() => $service->changeStatus($foreignReservation, Reservation::STATUS_REJECTED),
                'cancel' => static fn() => $service->changeStatus($foreignReservation, Reservation::STATUS_CANCELLED),
                'start borrowing' => static fn() => $service->startBorrowing($foreignReservation),
                'finish borrowing' => static fn() => $service->finishBorrowing($foreignReservation),
                'return item' => static fn() => $service->finishBorrowingForItem($itemIds['foreign']),
                'withdraw' => static fn() => $service->withdraw($foreignReservation),
            ];
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
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Reservation projector']);
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-06-01 10:00:00'),
                new DateTimeImmutable('2030-06-01 12:00:00'),
            );

            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));
            $this->assertFalse($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-06-01 11:00:00'),
                new DateTimeImmutable('2030-06-01 13:00:00'),
            ));
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-06-01 12:00:00'),
                new DateTimeImmutable('2030-06-01 13:00:00'),
            ));
        });
    }

    /**
     * @testdox Concurrent automatic reservations create only one confirmed reservation
     */
    public function testConcurrentAutomaticReservationsCreateOnlyOneConfirmedReservation(): void
    {
        $admin = $this->loadUserInOrganization(1, self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($admin) {
            $database = $this->getDatabase();
            $settings = $GLOBALS['gSettingsManager'];
            $previousApproval = $settings->getString('inventory_reservation_approval');
            $previousEnabled = $settings->getString('inventory_reservations_enabled');
            $previousRequesters = $settings->getString('inventory_reservation_requesters');
            $itemId = 0;
            $startFile = tempnam(sys_get_temp_dir(), 'admidio-reservation-start-');
            $resultFiles = [
                tempnam(sys_get_temp_dir(), 'admidio-reservation-result-'),
                tempnam(sys_get_temp_dir(), 'admidio-reservation-result-'),
            ];
            $processes = [];

            try {
                unlink($startFile);
                $settings->set('inventory_reservations_enabled', '1');
                $settings->set('inventory_reservation_requesters', 'members');
                $settings->set('inventory_reservation_approval', 'automatic');
                $itemId = $this->createForeignOrganizationItem(self::ORG_ID);

                // Child processes need to see the item and the temporary settings.
                $database->endTransaction();

                foreach ($resultFiles as $resultFile) {
                    $process = new Process([
                        PHP_BINARY,
                        __DIR__ . '/reservation_request_worker.php',
                        (string) $itemId,
                        (string) $admin->getValue('usr_id'),
                        $startFile,
                        $resultFile,
                    ]);
                    $process->start();
                    $processes[] = $process;
                }

                touch($startFile);
                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                }

                $results = array_map(static fn(string $file): string => trim((string) file_get_contents($file)), $resultFiles);
                sort($results);
                $this->assertSame(['SYS_ITEM_NOT_AVAILABLE', 'approved'], $results);
                $this->assertSame(1, (int) $database->queryPrepared(
                    'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status = ?',
                    [$itemId, Reservation::STATUS_APPROVED],
                )->fetchColumn());
            } finally {
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
                if ($itemId > 0) {
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' WHERE inb_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?', [$itemId]);
                }
                $settings->set('inventory_reservation_approval', $previousApproval);
                $settings->set('inventory_reservations_enabled', $previousEnabled);
                $settings->set('inventory_reservation_requesters', $previousRequesters);
                foreach (array_merge([$startFile], $resultFiles) as $file) {
                    if (is_string($file) && file_exists($file)) {
                        unlink($file);
                    }
                }
                $database->startTransaction();
            }
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
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Manual reservation projector']);
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-01 10:00:00'),
                new DateTimeImmutable('2030-07-01 12:00:00'),
            );

            $this->assertSame(Reservation::STATUS_REQUESTED, $reservation->getValue('ivr_status'));
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-07-01 11:00:00'),
                new DateTimeImmutable('2030-07-01 13:00:00'),
            ));

            $service->approve($reservation);
            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));
            $this->assertFalse($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-07-01 11:00:00'),
                new DateTimeImmutable('2030-07-01 13:00:00'),
            ));
        });
    }

    /**
     * @testdox Reservations for a currently borrowed item may be requested and approved
     */
    public function testManualReservationCanBeRequestedWhileItemIsBorrowed(): void
    {
        $admin = $this->makeInventoryUser('invreservationborrowedmanual', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, [
                'ITEMNAME' => 'Borrowed manual reservation projector',
                'LAST_RECEIVER' => 'Alice',
                'BORROW_DATE' => '2030-07-01',
            ]);
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 10:00:00'),
                new DateTimeImmutable('2030-07-15 12:00:00'),
            );

            $this->assertSame(Reservation::STATUS_REQUESTED, $reservation->getValue('ivr_status'));
            $service->approve($reservation);
            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));

            try {
                $service->startBorrowing($reservation);
                $this->fail('A borrowed item must not be handed over for another reservation.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_ITEM_NOT_AVAILABLE', $exception->getTranslationId());
            }

            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' SET inb_return_date = ? WHERE inb_ini_id = ?',
                ['2020-07-15', $itemId],
            );
            $service->assertCanStartBorrowing($reservation);
            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' SET inb_return_date = NULL WHERE inb_ini_id = ?',
                [$itemId],
            );
            $service->startBorrowing($reservation, false);
            $this->assertSame(Reservation::STATUS_BORROWED, $reservation->getValue('ivr_status'));
        });
    }

    /**
     * @testdox Automatic reservations can be created for an item that is currently borrowed
     */
    public function testAutomaticReservationAllowsBorrowedItem(): void
    {
        $admin = $this->makeInventoryUser('invreservationborrowedauto', true);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'members');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, [
                'ITEMNAME' => 'Borrowed automatic reservation projector',
                'LAST_RECEIVER' => 'Alice',
                'BORROW_DATE' => '2030-07-01',
            ]);

            $reservation = (new ReservationService($this->getDatabase()))->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 10:00:00'),
                new DateTimeImmutable('2030-07-15 12:00:00'),
            );
            $this->assertSame(Reservation::STATUS_APPROVED, $reservation->getValue('ivr_status'));
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
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Confirmed manual reservation projector']);
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 10:00:00'),
                new DateTimeImmutable('2030-07-15 12:00:00'),
            );
            $service->approve($reservation);

            $this->expectException(Exception::class);
            $service->request(
                $itemId,
                new DateTimeImmutable('2030-07-15 11:00:00'),
                new DateTimeImmutable('2030-07-15 13:00:00'),
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
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Lifecycle projector']);
            $service = new ReservationService($this->getDatabase());
            $request = static fn(string $begin) => $service->request(
                $itemId,
                new DateTimeImmutable($begin),
                new DateTimeImmutable($begin . ' +1 hour'),
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
            (new ItemService($this->getDatabase(), $this->uuidOfItem($itemId)))->saveData([
                'INF-LAST_RECEIVER' => 'Alice', 'INF-BORROW_DATE' => '2020-01-01', 'INF-RETURN_DATE' => date('Y-m-d'),
            ]);
            $service->finishBorrowingForItem($itemId);
            $this->assertSame(Reservation::STATUS_RETURNED, (new Reservation(
                $this->getDatabase(),
                (int) $borrowed->getValue('ivr_id'),
            ))->getValue('ivr_status'));

            $cancelledApproved = $request('2030-11-05 10:00:00');
            $service->approve($cancelledApproved);
            $service->changeStatus($cancelledApproved, Reservation::STATUS_CANCELLED);
            $this->assertSame(Reservation::STATUS_CANCELLED, $cancelledApproved->getValue('ivr_status'));
        });
    }

    /** @testdox Keepers can open the borrowing workspace and see only their own items */
    public function testKeeperBorrowingWorkspaceRestrictsBothListsToOwnedItems(): void
    {
        $admin = $this->makeInventoryUser('invworkspaceadmin', true);
        $keeperData = $this->getFixture()->createAndSaveUser('invworkspacekeeper', 'keeper@example.local');
        $keeper = $this->loadUserInOrganization($keeperData['usr_id'], self::ORG_ID);
        $itemUuids = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($keeperData, $admin) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $service = new ReservationService($this->getDatabase());
            $itemUuids = [];
            foreach (['owned ready', 'foreign ready', 'owned borrowed', 'foreign borrowed'] as $name) {
                $itemId = $this->createItem($items, [
                    'ITEMNAME' => $name,
                    'KEEPER' => (string) (str_starts_with($name, 'owned') ? $keeperData['usr_id'] : $admin->getValue('usr_id')),
                ]);
                $itemUuids[$name] = $this->uuidOfItem($itemId);
                $reservation = $service->request(
                    $itemId,
                    new DateTimeImmutable('+1 day'),
                    new DateTimeImmutable('+2 days'),
                );
                $service->approve($reservation);
                if (str_ends_with($name, 'borrowed')) {
                    $service->startBorrowing($reservation);
                    $items->setValue('LAST_RECEIVER', (string) $keeperData['usr_id']);
                    $items->setValue('BORROW_DATE', date('Y-m-d'));
                    $items->saveItemData();
                }
            }

            return $itemUuids;
        });

        $this->withCurrentUser($keeper, self::ORG_ID, true, function () use ($itemUuids) {
            $GLOBALS['gSettingsManager']->set('inventory_module_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_allow_keeper_edit', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_keepers_manage', '1');
            $previousSession = $GLOBALS['gCurrentSession'];
            $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
            try {
                $presenter = new InventoryBorrowPresenter();
                $presenter->createList();
                $smarty = $presenter->getSmartyTemplate();
                $this->assertSame([$itemUuids['owned ready']], array_column($smarty->getTemplateVars('readyReservations'), 'ini_uuid'));
                $this->assertSame([$itemUuids['owned borrowed']], array_column($smarty->getTemplateVars('activeBorrowings'), 'ini_uuid'));
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
            }
        });
    }

    public static function regularReturnCases(): array
    {
        return [
            'administrator without reservations' => [false, false, false],
            'administrator with disabled reservation' => [false, true, false],
            'keeper without reservations' => [true, false, false],
            'keeper with disabled reservation' => [true, true, false],
            'keeper without reservation management' => [true, true, true],
            'administrator returning disabled reservation by UUID' => [false, true, false, true],
            'keeper returning disabled reservation by UUID' => [true, true, false, true],
            'keeper returning reservation by UUID without management' => [true, true, true, true],
            'regular return with cleared date' => [false, true, true, false, true],
            'UUID return with cleared date' => [false, true, true, true, true],
            'keeper regular return with cleared date and disabled reservations' => [true, true, false, false, true],
            'keeper UUID return with cleared date and disabled reservations' => [true, true, false, true, true],
        ];
    }

    /**
     * @dataProvider regularReturnCases
     * @testdox Regular returns use item permissions independently of reservation settings
     */
    public function testRegularReturnDoesNotRequireReservationManagement(bool $asKeeper, bool $hasReservation, bool $reservationsEnabled, bool $withReservationUuid = false, bool $clearReturnDate = false): void
    {
        $admin = $this->makeInventoryUser('invregularreturnadmin', true);
        $keeperData = $this->getFixture()->createAndSaveUser('invregularreturnkeeper', 'invregularreturnkeeper@example.local');
        $keeper = $this->loadUserInOrganization($keeperData['usr_id'], self::ORG_ID);
        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($keeperData, $hasReservation) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($items, ['ITEMNAME' => 'Regular return item', 'KEEPER' => (string) $keeperData['usr_id']]);
            if ($hasReservation) {
                $service = new ReservationService($this->getDatabase());
                $reservation = $service->request($itemId, new DateTimeImmutable('2020-01-01 10:00:00'), new DateTimeImmutable('2020-01-01 12:00:00'));
                $service->startBorrowing($reservation);
            }
            $items->setValue('LAST_RECEIVER', 'Alice');
            $items->setValue('BORROW_DATE', '2020-01-01');
            $items->saveItemData();
            return $itemId;
        });

        $this->withCurrentUser($asKeeper ? $keeper : $admin, self::ORG_ID, true, function () use ($itemId, $hasReservation, $reservationsEnabled, $withReservationUuid, $clearReturnDate) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', $reservationsEnabled ? '1' : '0');
            $GLOBALS['gSettingsManager']->set('inventory_allow_keeper_edit', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_keepers_manage', '0');
            $GLOBALS['gSettingsManager']->set('inventory_allowed_keeper_edit_fields', 'LAST_RECEIVER,BORROW_DATE,RETURN_DATE');
            $uuid = $this->uuidOfItem($itemId);
            $reservationService = new ReservationService($this->getDatabase());
            if ($withReservationUuid) {
                $reservationId = (int) $this->getDatabase()->queryPrepared(
                    'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                    [$itemId],
                )->fetchColumn();
                $reservation = new Reservation($this->getDatabase(), $reservationId);
                $previousSession = $GLOBALS['gCurrentSession'];
                $session = new class {
                    public ?FormPresenter $form = null;

                    public function getCsrfToken(): string
                    {
                        return 'return-test-token';
                    }

                    public function addFormObject(FormPresenter $form): bool
                    {
                        $this->form = $form;
                        return true;
                    }
                };
                try {
                    $GLOBALS['gCurrentSession'] = $session;
                    (new InventoryItemPresenter())->createEditBorrowForm($uuid, (string) $reservation->getValue('ivr_uuid'), true);
                    $this->assertNotNull($session->form);
                } finally {
                    $GLOBALS['gCurrentSession'] = $previousSession;
                }
            }
            (new ItemService($this->getDatabase(), $uuid))->saveData(['INF-RETURN_DATE' => $clearReturnDate ? '' : date('Y-m-d')]);
            if ($withReservationUuid) {
                $reservationService->finishBorrowing($reservation);
            } else {
                $reservationService->finishBorrowingForItem($itemId);
            }
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $items->readItemData($uuid);
            $this->assertSame($clearReturnDate, $items->isBorrowed());
            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $expectedStatus = $clearReturnDate ? Reservation::STATUS_BORROWED : Reservation::STATUS_RETURNED;
            $this->assertSame($hasReservation ? [$expectedStatus] : [], $statuses);
        });
    }

    /** @testdox A handed-over reservation blocks its period until it is returned */
    public function testBorrowedReservationPreventsOverlappingRequestsAndEventReservations(): void
    {
        $admin = $this->makeInventoryUser('invborrowedoverlap', true);
        $category = $this->getFixture()->createAndSaveCategory('Borrowed overlap events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Borrowed overlap item']);
            $service = new ReservationService($this->getDatabase());
            $begin = new DateTimeImmutable('2030-11-05 10:00:00');
            $end = new DateTimeImmutable('2030-11-05 12:00:00');
            $reservation = $service->request($itemId, $begin, $end);
            $service->startBorrowing($reservation);
            $this->assertFalse($service->isAvailable($itemId, $begin, $end));
            $this->assertTrue($service->isAvailable($itemId, $end, $end->modify('+1 hour')));

            try {
                $service->request($itemId, $begin, $end);
                $this->fail('A handed-over reservation must still block overlapping requests.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_ITEM_NOT_AVAILABLE', $exception->getTranslationId());
            }

            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Overlapping borrowed item');
            $event->setValue('dat_begin', $begin->format('Y-m-d H:i:s'));
            $event->setValue('dat_end', $end->format('Y-m-d H:i:s'));
            $event->save();
            try {
                $service->syncEventReservations((int) $event->getValue('dat_id'), [$itemId], $begin, $end);
                $this->fail('A handed-over reservation must still block overlapping event reservations.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_ITEM_NOT_AVAILABLE_FOR_PERIOD', $exception->getTranslationId());
            }
            $service->finishBorrowing($reservation);
            $this->assertSame(Reservation::STATUS_BORROWED, $reservation->getValue('ivr_status'), 'A reservation without a saved physical return must stay borrowed.');
            (new ItemService($this->getDatabase(), $this->uuidOfItem($itemId)))->saveData([
                'INF-LAST_RECEIVER' => 'Alice', 'INF-BORROW_DATE' => '2020-01-01', 'INF-RETURN_DATE' => date('Y-m-d'),
            ]);
            $service->finishBorrowing($reservation);
            $this->assertTrue($service->isAvailable($itemId, $begin, $end));
        });
    }

    public static function notificationStatuses(): array
    {
        $cases = [];
        foreach (['requested', 'approved', 'rejected', 'cancelled', 'borrowed', 'returned'] as $status) {
            foreach ([false, true] as $html) {
                $cases[$status . ($html ? ' HTML' : ' plain')] = [$status, $html];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider notificationStatuses
     * @testdox Reservation email subjects and bodies use translated statuses
     */
    public function testReservationNotificationUsesTranslatedStatus(string $status, bool $html): void
    {
        $admin = $this->makeInventoryUser('invnotificationstatus', true);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($status, $html) {
            $GLOBALS['gSettingsManager']->set('mail_html_registered_users', $html ? '1' : '0');
            $GLOBALS['gSettingsManager']->set('mail_sender_mode', '2');
            $GLOBALS['gSettingsManager']->set('mail_sender_email', 'inventory@example.test');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Notification item']);
            $reservation = new Reservation($this->getDatabase());
            $reservation->setValue('ivr_ini_id', $itemId);
            $reservation->setValue('ivr_guest_name', 'Guest');
            $reservation->setValue('ivr_status', $status);
            $reservation->setValue('ivr_begin', '2030-11-05 10:00:00');
            $reservation->setValue('ivr_end', '2030-11-05 12:00:00');
            $service = new ReservationNotificationService($this->getDatabase());
            $email = (new \ReflectionMethod($service, 'createEmail'))->invoke($service, $reservation);
            $text = (new \ReflectionProperty($email, 'emText'))->getValue($email);
            $statusId = $status === 'cancelled' ? 'SYS_CANCELLED' : 'SYS_STATUS_' . strtoupper($status);
            $label = $GLOBALS['gL10n']->get($statusId);
            $this->assertStringContainsString($label, $email->Subject);
            $this->assertStringContainsString($label, $text);
            $this->assertStringNotContainsString('#SYS_', $email->Subject . $text);
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
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Invalid lifecycle projector']);
            $service = new ReservationService($this->getDatabase());
            $reservation = $service->request(
                $itemId,
                new DateTimeImmutable('2030-12-01 10:00:00'),
                new DateTimeImmutable('2030-12-01 12:00:00'),
            );

            foreach ([
                static fn() => $service->startBorrowing($reservation),
                static fn() => $service->finishBorrowing($reservation),
            ] as $transition) {
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

            (new ItemService($this->getDatabase(), $this->uuidOfItem($itemId)))->saveData([
                'INF-LAST_RECEIVER' => 'Alice', 'INF-BORROW_DATE' => '2020-01-01', 'INF-RETURN_DATE' => date('Y-m-d'),
            ]);
            $service->finishBorrowing($reservation);
            foreach ([
                static fn() => $service->approve($reservation),
                static fn() => $service->changeStatus($reservation, Reservation::STATUS_CANCELLED),
            ] as $transition) {
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
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Event reservation projector']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with equipment request');
            $event->setValue('dat_begin', '2030-08-01 10:00:00');
            $event->setValue('dat_end', '2030-08-01 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-08-01 10:00:00'),
                new DateTimeImmutable('2030-08-01 12:00:00'),
            );

            $status = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [(int) $event->getValue('dat_id')],
            )->fetchColumn();
            $this->assertSame(Reservation::STATUS_REQUESTED, $status);
            $this->assertTrue($service->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-08-01 11:00:00'),
                new DateTimeImmutable('2030-08-01 13:00:00'),
            ));
        });
    }

    /**
     * @testdox Events may request a currently borrowed item with manual approval
     */
    public function testEventReservationCanBeRequestedForBorrowedItemWithManualApproval(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationborrowedmanual', true);
        $category = $this->getFixture()->createAndSaveCategory('Borrowed reservation events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, [
                'ITEMNAME' => 'Borrowed event reservation projector',
                'LAST_RECEIVER' => 'Alice',
                'BORROW_DATE' => '2030-08-01',
            ]);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with borrowed equipment request');
            $event->setValue('dat_begin', '2030-08-15 10:00:00');
            $event->setValue('dat_end', '2030-08-15 12:00:00');
            $event->save();

            (new ReservationService($this->getDatabase()))->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-08-15 10:00:00'),
                new DateTimeImmutable('2030-08-15 12:00:00'),
            );

            $this->assertSame(Reservation::STATUS_REQUESTED, $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [(int) $event->getValue('dat_id')],
            )->fetchColumn());
        });
    }

    public static function revokedReservationPolicies(): array
    {
        $cases = [];
        foreach (['requester', 'events', 'reservations'] as $policy) {
            foreach (['new', 'this', 'series'] as $scope) {
                $cases[$policy . ' revoked for ' . $scope] = [$policy, $scope];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider revokedReservationPolicies
     * @testdox Stored event forms cannot request items after reservation permissions are revoked
     */
    public function testEventFormRechecksCurrentReservationPermission(string $policy, string $scope): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeInventoryUser('invrevokedadmin', true);
        $role = $fixture->createAndSaveRoleWithRights('Revoked reservation event editors', self::ORG_ID, ['rol_events' => 1]);
        $editorData = $fixture->createAndSaveUser('invrevokededitor', 'invrevokededitor@example.local');
        $fixture->assignUserToRole($editorData['usr_id'], $role['rol_id']);
        $editor = $this->loadUserInOrganization($editorData['usr_id'], self::ORG_ID);
        $category = $fixture->createAndSaveCategory('Revoked reservation events', 'EVT', self::ORG_ID);
        $itemId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            return $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Restricted event item']);
        });

        $this->withCurrentUser($editor, self::ORG_ID, true, function () use ($category, $itemId, $policy, $scope) {
            $settings = $GLOBALS['gSettingsManager'];
            $settings->set('inventory_reservations_enabled', '1');
            $settings->set('inventory_reservations_events_enabled', '1');
            $settings->set('inventory_reservation_requesters', 'members');
            $settings->set('inventory_reservation_approval', 'automatic');
            $GLOBALS['gTimezone'] = 'UTC';
            $values = [
                'dat_headline' => 'Event before revoked request', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
            ];
            if ($scope === 'series') {
                $values += [
                    'event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1,
                    'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 2,
                ];
            }
            $service = new EventSaveService($this->getDatabase());
            $eventUuid = $scope === 'new' ? '' : (string) $service->saveData('', $values)->getValue('dat_uuid');
            $this->assertTrue(InventoryAccessService::canRequestReservation());
            $form = new FormPresenter('revoked_event_request', 'modules/events.edit.tpl');
            $form->addSelectBox('event_inventory_items', 'Items', [$itemId => 'Restricted event item'], ['multiselect' => true]);

            if ($policy === 'requester') {
                $settings->set('inventory_reservation_requesters', 'roles');
                $settings->set('inventory_reservation_requester_roles', '');
                $this->assertFalse(InventoryAccessService::canRequestReservation());
            } else {
                $settings->set('inventory_reservations_' . ($policy === 'events' ? 'events_enabled' : 'enabled'), '0');
            }
            $validated = $form->validate([
                'adm_csrf_token' => $form->getCsrfToken(), 'event_inventory_items' => [$itemId],
            ]);
            $values['dat_headline'] = 'Forbidden reservation change';
            try {
                $service->saveData($eventUuid, array_merge($values, $validated), '', false, $scope === 'series' ? 'series' : 'this');
                $this->fail('A stored form must not bypass current reservation permissions.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            }
            $this->assertSame(0, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchColumn());
            $this->assertSame(0, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_EVENTS . ' WHERE dat_headline = ?',
                ['Forbidden reservation change'],
            )->fetchColumn());
        });
    }

    /** @testdox Event synchronization may update existing items but cannot add items without request rights */
    public function testEventSynchronizationRequiresPermissionForAdditionalItems(): void
    {
        $admin = $this->makeInventoryUser('invsyncpermission', true);
        $category = $this->getFixture()->createAndSaveCategory('Synchronization permission events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $settings = $GLOBALS['gSettingsManager'];
            $settings->set('inventory_reservations_enabled', '1');
            $settings->set('inventory_reservations_events_enabled', '1');
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $existingItemId = $this->createItem($items, ['ITEMNAME' => 'Existing reservation item']);
            $additionalItemId = $this->createItem($items, ['ITEMNAME' => 'Additional reservation item']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Existing synchronization event');
            $event->setValue('dat_begin', '2030-12-01 10:00:00');
            $event->setValue('dat_end', '2030-12-01 12:00:00');
            $event->save();
            $eventId = (int) $event->getValue('dat_id');
            $service = new ReservationService($this->getDatabase());
            $begin = new DateTimeImmutable('2030-12-01 10:00:00');
            $end = new DateTimeImmutable('2030-12-01 12:00:00');
            $service->syncEventReservations($eventId, [$existingItemId], $begin, $end);
            $settings->set('inventory_reservations_events_enabled', '0');
            $service->syncEventReservations($eventId, [$existingItemId], $begin->modify('+1 day'), $end->modify('+1 day'));
            try {
                $service->syncEventReservations($eventId, [$existingItemId, $additionalItemId], $begin, $end);
                $this->fail('Synchronization must not add new items when event reservations are disabled.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            }
            $periods = $this->getDatabase()->queryPrepared(
                'SELECT ivr_ini_id, ivr_begin FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [$eventId],
            )->fetchAll();
            $this->assertCount(1, $periods);
            $this->assertSame($existingItemId, (int) $periods[0]['ivr_ini_id']);
            $this->assertSame('2030-12-02 10:00:00', $periods[0]['ivr_begin']);
        });
    }

    /**
     * @testdox Event editors synchronize existing item reservations without request permission
     */
    public function testEventEditorSynchronizesExistingReservationsWithoutRequestPermission(): void
    {
        $fixture = $this->getFixture();
        $inventoryAdmin = $this->makeInventoryUser('inveventsyncinventoryadmin', true);
        $eventRole = $fixture->createAndSaveRoleWithRights('Reservation event editors', self::ORG_ID, ['rol_events' => 1]);
        $eventEditorData = $fixture->createAndSaveUser('inveventsynceditor', 'inveventsynceditor@example.local');
        $fixture->assignUserToRole($eventEditorData['usr_id'], $eventRole['rol_id']);
        $eventEditor = $this->loadUserInOrganization($eventEditorData['usr_id'], self::ORG_ID);
        $category = $fixture->createAndSaveCategory('Reservation synchronization events', 'EVT', self::ORG_ID);

        [$eventId, $itemId] = $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Synchronized event projector']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Reservation synchronization event');
            $event->setValue('dat_begin', '2030-12-01 10:00:00');
            $event->setValue('dat_end', '2030-12-01 12:00:00');
            $event->save();
            (new ReservationService($this->getDatabase()))->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-12-01 10:00:00'),
                new DateTimeImmutable('2030-12-01 12:00:00'),
            );

            return [(int) $event->getValue('dat_id'), $itemId];
        });

        $this->withCurrentUser($eventEditor, self::ORG_ID, true, function () use ($category, $eventId, $itemId) {
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'roles');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requester_roles', '');
            $eventUuid = $this->getDatabase()->queryPrepared('SELECT dat_uuid FROM ' . TBL_EVENTS . ' WHERE dat_id = ?', [$eventId])->fetchColumn();
            (new EventSaveService($this->getDatabase()))->saveData((string) $eventUuid, [
                'dat_headline' => 'Reservation synchronization event',
                'dat_location' => '',
                'dat_all_day' => '0',
                'event_from' => '2030-12-02',
                'event_from_time' => '14:00',
                'event_to' => '2030-12-02',
                'event_to_time' => '16:00',
                'cat_uuid' => $category['cat_uuid'],
                'dat_max_members' => 0,
                'event_participation_possible' => 0,
                'event_deadline' => '',
                'event_deadline_time' => '',
            ]);

            $period = $this->getDatabase()->queryPrepared(
                'SELECT ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_ini_id = ?',
                [$eventId, $itemId],
            )->fetch();
            $this->assertSame('2030-12-02 14:00:00', $period['ivr_begin']);
            $this->assertSame('2030-12-02 16:00:00', $period['ivr_end']);
        });
    }

    /**
     * @testdox Event editors synchronize reservations of an event series without request permission
     */
    public function testEventEditorSynchronizesExistingSeriesReservationsWithoutRequestPermission(): void
    {
        $fixture = $this->getFixture();
        $inventoryAdmin = $this->makeInventoryUser('inveventseriessyncinventoryadmin', true);
        $eventRole = $fixture->createAndSaveRoleWithRights('Reservation series event editors', self::ORG_ID, ['rol_events' => 1]);
        $eventEditorData = $fixture->createAndSaveUser('inveventseriessynceditor', 'inveventseriessynceditor@example.local');
        $fixture->assignUserToRole($eventEditorData['usr_id'], $eventRole['rol_id']);
        $eventEditor = $this->loadUserInOrganization($eventEditorData['usr_id'], self::ORG_ID);
        $category = $fixture->createAndSaveCategory('Reservation series synchronization events', 'EVT', self::ORG_ID);

        $eventUuid = $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Synchronized series projector']);
            $event = (new EventSaveService($this->getDatabase()))->saveData('', [
                'dat_headline' => 'Reservation synchronization series',
                'dat_location' => '',
                'dat_all_day' => '0',
                'event_from' => '2030-12-01',
                'event_from_time' => '10:00',
                'event_to' => '2030-12-01',
                'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'],
                'dat_max_members' => 0,
                'event_participation_possible' => 0,
                'event_deadline' => '',
                'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
                'event_recurrence_frequency' => 'daily',
                'event_recurrence_interval' => 1,
                'event_recurrence_end_type' => 'count',
                'event_recurrence_count' => 2,
            ]);

            return (string) $event->getValue('dat_uuid');
        });

        $this->withCurrentUser($eventEditor, self::ORG_ID, true, function () use ($category, $eventUuid) {
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requesters', 'roles');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_requester_roles', '');
            (new EventSaveService($this->getDatabase()))->saveData($eventUuid, [
                'dat_headline' => 'Reservation synchronization series',
                'dat_location' => '',
                'dat_all_day' => '0',
                'event_from' => '2030-12-01',
                'event_from_time' => '14:00',
                'event_to' => '2030-12-01',
                'event_to_time' => '16:00',
                'cat_uuid' => $category['cat_uuid'],
                'dat_max_members' => 0,
                'event_participation_possible' => 0,
                'event_deadline' => '',
                'event_deadline_time' => '',
                'event_recurrence_frequency' => 'daily',
                'event_recurrence_interval' => 1,
                'event_recurrence_end_type' => 'count',
                'event_recurrence_count' => 2,
            ], '', false, 'series');

            $periods = $this->getDatabase()->queryPrepared(
                'SELECT ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS
                . ' INNER JOIN ' . TBL_EVENTS . ' ON dat_id = ivr_dat_id'
                . ' WHERE dat_headline = ? AND ivr_status = ? ORDER BY ivr_begin',
                ['Reservation synchronization series', Reservation::STATUS_REQUESTED],
            )->fetchAll();
            $this->assertSame([
                ['ivr_begin' => '2030-12-01 14:00:00', 'ivr_end' => '2030-12-01 16:00:00'],
                ['ivr_begin' => '2030-12-02 14:00:00', 'ivr_end' => '2030-12-02 16:00:00'],
            ], $periods);
        });
    }

    public static function eventReservationScopes(): array
    {
        return ['single event' => ['this'], 'event series' => ['series']];
    }

    public static function zeroDurationEventCases(): array
    {
        return [
            'new event' => [false],
            'existing event after removing all items' => [true],
        ];
    }

    /**
     * @dataProvider zeroDurationEventCases
     * @testdox Events without items allow zero duration while actual reservations still require a period
     */
    public function testZeroDurationEventsWithoutItems(bool $removeItems): void
    {
        $admin = $this->makeInventoryUser('invzeroduration', true);
        $category = $this->getFixture()->createAndSaveCategory('Zero duration events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $removeItems) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Zero duration item']);
            $values = [
                'dat_headline' => 'Zero duration event', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '10:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [''],
            ];
            $service = new EventSaveService($this->getDatabase());
            $eventUuid = '';
            if ($removeItems) {
                $initialValues = array_replace($values, ['event_to_time' => '12:00', 'event_inventory_items' => [$itemId]]);
                $eventUuid = (string) $service->saveData('', $initialValues)->getValue('dat_uuid');
            }
            $event = $service->saveData($eventUuid, $values);
            $this->assertGreaterThan(0, (int) $event->getValue('dat_id'));
            $periods = $this->getDatabase()->queryPrepared(
                'SELECT dat_begin, dat_end FROM ' . TBL_EVENTS . ' WHERE dat_headline = ?',
                [$values['dat_headline']],
            )->fetchAll();
            $this->assertCount(1, $periods);
            foreach ($periods as $period) {
                $this->assertSame($period['dat_begin'], $period['dat_end']);
            }
            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame($removeItems ? array_fill(0, count($periods), Reservation::STATUS_CANCELLED) : [], $statuses);

            // Empty selections must not weaken the period requirement for actual reservations.
            try {
                (new ReservationService($this->getDatabase()))->syncEventReservations(
                    (int) $event->getValue('dat_id'),
                    [$itemId],
                    new DateTimeImmutable('2030-12-01 10:00:00'),
                    new DateTimeImmutable('2030-12-01 10:00:00'),
                );
                $this->fail('An item reservation must require a positive duration.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_DATE_END_BEFORE_BEGIN', $exception->getTranslationId());
            }
        });
    }

    /**
     * @dataProvider eventReservationScopes
     * @testdox Removing every item row explicitly cancels event reservations
     */
    public function testRemovingAllEventItemRowsCancelsReservations(string $scope): void
    {
        $admin = $this->makeInventoryUser('invremoveallitems', true);
        $category = $this->getFixture()->createAndSaveCategory('Remove all item events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $scope) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Removed event item']);
            $values = [
                'dat_headline' => 'Remove every item', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
            ];
            if ($scope === 'series') {
                $values += [
                    'event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1,
                    'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 2,
                ];
            }
            $service = new EventSaveService($this->getDatabase());
            $event = $service->saveData('', $values);
            $previousSession = $GLOBALS['gCurrentSession'];
            try {
                $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
                $form = new FormPresenter('remove_all_items', 'modules/events.edit.tpl');
                $form->addSelectBox('event_inventory_items', 'Items', [$itemId => 'Removed event item'], ['multiselect' => true]);
                $form->addButton('event_inventory_reservation_add', 'Add');
                $smarty = new \Smarty\Smarty();
                $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
                $smarty->setCompileDir(sys_get_temp_dir());
                $smarty->assign([
                    'l10n' => $GLOBALS['gL10n'], 'elements' => $form->getElements(),
                    'eventInventoryReservationRows' => [], 'eventInventoryReservationItems' => [],
                ]);
                $document = new \DOMDocument();
                $document->loadHTML($smarty->fetch('modules/events.edit.inventory-reservations.tpl'));
                $emptySelection = (new \DOMXPath($document))->query('//input[@type="hidden" and @name="event_inventory_items[]"]');
                $this->assertCount(1, $emptySelection);
                $validated = $form->validate([
                    'adm_csrf_token' => $form->getCsrfToken(),
                    'event_inventory_items' => [$emptySelection->item(0)->getAttribute('value')],
                ]);
                unset($values['event_inventory_items']);
                $service->saveData((string) $event->getValue('dat_uuid'), array_merge($values, $validated), '', false, $scope);
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
            }
            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame(array_fill(0, $scope === 'series' ? 2 : 1, Reservation::STATUS_CANCELLED), $statuses);
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
            $GLOBALS['gSettingsManager']->set('system_date', 'd/m/Y');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Repeated event reservation projector']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event with repeated equipment request');
            $event->setValue('dat_begin', '2030-11-05 10:00:00');
            $event->setValue('dat_end', '2030-11-05 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-11-05 10:00:00'),
                new DateTimeImmutable('2030-11-05 12:00:00'),
            );

            $reservationId = (int) $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [(int) $event->getValue('dat_id')],
            )->fetchColumn();
            $service->changeStatus(new Reservation($this->getDatabase(), $reservationId), Reservation::STATUS_REJECTED);

            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            $_POST = ['adm_csrf_token' => $session->getCsrfToken()];
            try {
                $response = (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string) $event->getValue('dat_uuid'),
                    $itemId,
                );
                $this->assertSame('success', $response['status']);
                $this->assertSame(Reservation::STATUS_REQUESTED, $response['reservation_status']);

                try {
                    (new EventService($this->getDatabase()))->requestReservationAgain(
                        (string) $event->getValue('dat_uuid'),
                        $itemId,
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
                [(int) $event->getValue('dat_id')],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame([Reservation::STATUS_REJECTED, Reservation::STATUS_REQUESTED], $statuses);
            $newReservationPeriod = $this->getDatabase()->queryPrepared(
                'SELECT ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . '
                  WHERE ivr_dat_id = ? ORDER BY ivr_id DESC LIMIT 1',
                [(int) $event->getValue('dat_id')],
            )->fetch();
            $this->assertSame('2030-11-05 10:00:00', $newReservationPeriod['ivr_begin']);
            $this->assertSame('2030-11-05 12:00:00', $newReservationPeriod['ivr_end']);

            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '0');
            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            $_POST = ['adm_csrf_token' => $session->getCsrfToken()];
            try {
                (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string) $event->getValue('dat_uuid'),
                    $itemId,
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
            $rejectedItemId = $this->createItem($itemsData, ['ITEMNAME' => 'A rejected event item']);
            $requestedItemId = $this->createItem($itemsData, ['ITEMNAME' => 'Z requested event item']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event reservation status');
            $event->setValue('dat_begin', '2030-08-20 10:00:00');
            $event->setValue('dat_end', '2030-08-20 12:00:00');
            $event->save();

            $reservationService = new ReservationService($this->getDatabase());
            $reservationService->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$rejectedItemId, $requestedItemId],
                new DateTimeImmutable('2030-08-20 10:00:00'),
                new DateTimeImmutable('2030-08-20 12:00:00'),
            );
            $rejectedReservationId = (int) $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_ini_id = ?',
                [(int) $event->getValue('dat_id'), $rejectedItemId],
            )->fetchColumn();
            $reservationService->changeStatus(new Reservation($this->getDatabase(), $rejectedReservationId), Reservation::STATUS_REJECTED);

            $previousSession = $GLOBALS['gCurrentSession'];
            $previousPost = $_POST;
            $session = new Session($this->getDatabase(), COOKIE_PREFIX);
            $GLOBALS['gCurrentSession'] = $session;
            try {
                $presenter = new EventFormPresenter();
                $data = $presenter->getReservationStatusData(
                    (string) $event->getValue('dat_uuid'),
                    'this',
                    7,
                    0,
                    1,
                    '',
                    1,
                    'asc',
                );

                $this->assertSame(7, $data['draw']);
                $this->assertSame(2, $data['recordsTotal']);
                $this->assertSame(2, $data['recordsFiltered']);
                $this->assertCount(1, $data['data']);
                $this->assertStringContainsString('A rejected event item', $data['data'][0][1]);
                $this->assertStringContainsString('event-inventory-reservation-request-again', $data['data'][0][3]);

                $_POST = ['adm_csrf_token' => $session->getCsrfToken()];
                $response = (new EventService($this->getDatabase()))->requestReservationAgain(
                    (string) $event->getValue('dat_uuid'),
                    $rejectedItemId,
                );
                $this->assertSame('success', $response['status']);
                $dataAfterRequestAgain = $presenter->getReservationStatusData(
                    (string) $event->getValue('dat_uuid'),
                    'this',
                    8,
                    0,
                    25,
                    '',
                    -1,
                    'desc',
                );
                $this->assertSame(3, $dataAfterRequestAgain['recordsTotal']);
                foreach ($dataAfterRequestAgain['data'] as $row) {
                    $this->assertSame('', $row[3]);
                }

                $filteredData = $presenter->getReservationStatusData(
                    (string) $event->getValue('dat_uuid'),
                    'this',
                    9,
                    0,
                    25,
                    'REQUESTED',
                    -1,
                    'desc',
                );

                $this->assertSame(3, $filteredData['recordsTotal']);
                $this->assertSame(2, $filteredData['recordsFiltered']);
                $this->assertStringContainsString('A rejected event item', implode(' ', array_column($filteredData['data'], 1)));
                $this->assertStringContainsString('Z requested event item', implode(' ', array_column($filteredData['data'], 1)));
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
                $_POST = $previousPost;
            }
        });
    }

    public static function historicalReservationPeriods(): array
    {
        return [
            'timed event' => [false, '2030-12-03'],
            'multi-day event' => [false, '2030-12-04'],
            'all-day event changed to timed event' => [true, '2030-12-03'],
        ];
    }

    /**
     * @dataProvider historicalReservationPeriods
     * @testdox Reservation history displays and sorts each entry by its own period after an event moves
     */
    public function testEventReservationHistoryRetainsOriginalPeriods(bool $allDay, string $originalEnd): void
    {
        $admin = $this->makeInventoryUser('invhistoryperiods', true);
        $category = $this->getFixture()->createAndSaveCategory('Reservation history periods', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $allDay, $originalEnd) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');
            $GLOBALS['gSettingsManager']->set('system_date', 'd.m.Y');
            $GLOBALS['gSettingsManager']->set('system_time', 'H:i');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'History projector']);
            $values = [
                'dat_headline' => 'Reservation period history', 'dat_location' => '', 'dat_all_day' => $allDay ? '1' : '0',
                'event_from' => '2030-12-03', 'event_from_time' => '10:00',
                'event_to' => $originalEnd, 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
            ];
            $service = new EventSaveService($this->getDatabase());
            $event = $service->saveData('', $values);
            // Move backwards so sorting by the current event time cannot accidentally pass.
            $values['event_from'] = '2030-12-01';
            $values['dat_all_day'] = '0';
            $values['event_to'] = '2030-12-01';
            $values['event_from_time'] = '15:00';
            $values['event_to_time'] = '16:00';
            $service->saveData((string) $event->getValue('dat_uuid'), $values);

            $previousSession = $GLOBALS['gCurrentSession'];
            $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
            try {
                $presenter = new EventFormPresenter();
                $data = $presenter->getReservationStatusData((string) $event->getValue('dat_uuid'), 'this', 1, 0, 25, '', 0, 'desc');
                $this->assertCount(2, $data['data']);
                $this->assertStringContainsString('03.12.2030', $data['data'][0][0]);
                if ($allDay) {
                    $this->assertStringNotContainsString('00:00', $data['data'][0][0]);
                    $this->assertStringNotContainsString('23:59', $data['data'][0][0]);
                } else {
                    $this->assertStringContainsString('10:00', $data['data'][0][0]);
                    $this->assertStringContainsString('12:00', $data['data'][0][0]);
                }
                if ($originalEnd !== '2030-12-03') {
                    $this->assertStringContainsString('04.12.2030', $data['data'][0][0]);
                }
                $this->assertStringContainsString($GLOBALS['gL10n']->get('SYS_CANCELLED'), $data['data'][0][2]);
                $this->assertStringContainsString('01.12.2030', $data['data'][1][0]);
                $this->assertStringContainsString('15:00 - 16:00', $data['data'][1][0]);
                $this->assertStringContainsString($GLOBALS['gL10n']->get('SYS_STATUS_REQUESTED'), $data['data'][1][2]);
                $ascending = $presenter->getReservationStatusData((string) $event->getValue('dat_uuid'), 'this', 2, 0, 25, '', 0, 'asc');
                $this->assertSame($data['data'][1][0], $ascending['data'][0][0]);
                $this->assertSame($data['data'][0][0], $ascending['data'][1][0]);
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
            }
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

            return (string) $event->getValue('dat_uuid');
        });

        $this->withCurrentUser($inventoryAdmin, self::ORG_ID, true, function () use ($eventUuid) {
            $previousSession = $GLOBALS['gCurrentSession'];
            $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
            try {
                (new EventFormPresenter())->getReservationStatusData($eventUuid, 'this', 1, 0, 25, '', -1, 'desc');
                $this->fail('An inventory administrator must not read reservation data for an event they cannot edit.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            } finally {
                $GLOBALS['gCurrentSession'] = $previousSession;
            }
        });
    }

    public static function synchronizedReservationPeriods(): array
    {
        $cases = [];
        foreach (['manual', 'automatic'] as $approval) {
            foreach ([Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED, Reservation::STATUS_BORROWED] as $status) {
                foreach ([
                    'unchanged' => ['2030-09-01 10:00:00', '2030-09-01 12:00:00'],
                    'begin changed' => ['2030-09-01 10:30:00', '2030-09-01 12:00:00'],
                    'end changed' => ['2030-09-01 10:00:00', '2030-09-01 12:30:00'],
                    'date changed' => ['2030-09-02 10:00:00', '2030-09-02 12:00:00'],
                ] as $change => [$begin, $end]) {
                    $cases[$approval . ', ' . $status . ', ' . $change] = [$approval, $status, $begin, $end, $change !== 'unchanged'];
                }
            }
        }

        return $cases;
    }

    /**
     * @dataProvider synchronizedReservationPeriods
     * @testdox Moving an approved event reservation creates one new request and retains its approval history
     */
    public function testEventReservationTimeChangesRequireNewApproval(string $approval, string $status, string $begin, string $end, bool $changed): void
    {
        $admin = $this->makeInventoryUser('inveventreapproval', true);
        $category = $this->getFixture()->createAndSaveCategory('Reapproval events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $approval, $status, $begin, $end, $changed) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'manual');
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Reapproval projector']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event requiring renewed approval');
            $event->setValue('dat_begin', '2030-09-01 10:00:00');
            $event->setValue('dat_end', '2030-09-01 12:00:00');
            $event->save();
            $eventId = (int) $event->getValue('dat_id');
            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations($eventId, [$itemId], new DateTimeImmutable('2030-09-01 10:00:00'), new DateTimeImmutable('2030-09-01 12:00:00'));
            $original = new Reservation($this->getDatabase(), (int) $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [$eventId],
            )->fetchColumn());
            if ($status !== Reservation::STATUS_REQUESTED) {
                $service->approve($original);
            }
            if ($status === Reservation::STATUS_BORROWED) {
                $service->startBorrowing($original);
            }
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', $approval);
            $service->syncEventReservations($eventId, [$itemId], new DateTimeImmutable($begin), new DateTimeImmutable($end));
            $rows = $this->getDatabase()->queryPrepared(
                'SELECT ivr_uuid, ivr_status, ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? ORDER BY ivr_id',
                [$eventId],
            )->fetchAll();
            $requiresApproval = $changed && $status === Reservation::STATUS_APPROVED;
            $this->assertCount($requiresApproval ? 2 : 1, $rows);
            if ($requiresApproval) {
                $this->assertSame(Reservation::STATUS_CANCELLED, $rows[0]['ivr_status']);
                $this->assertSame('2030-09-01 10:00:00', $rows[0]['ivr_begin']);
                $this->assertSame('2030-09-01 12:00:00', $rows[0]['ivr_end']);
                $this->assertNotSame($rows[0]['ivr_uuid'], $rows[1]['ivr_uuid']);
            }
            $latest = $rows[array_key_last($rows)];
            $this->assertSame($requiresApproval ? Reservation::STATUS_REQUESTED : $status, $latest['ivr_status']);
            $this->assertSame($begin, $latest['ivr_begin']);
            $this->assertSame($end, $latest['ivr_end']);
            // Saving the same time again must neither duplicate nor approve the new request.
            $service->syncEventReservations($eventId, [$itemId], new DateTimeImmutable($begin), new DateTimeImmutable($end));
            $this->assertSame(count($rows), (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [$eventId],
            )->fetchColumn());
            $this->assertSame($latest['ivr_status'], $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_uuid = ?',
                [$latest['ivr_uuid']],
            )->fetchColumn());
        });
    }

    public static function cancelledEventReservationCases(): array
    {
        $cases = [];
        foreach (['this', 'series'] as $scope) {
            foreach ([Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED, Reservation::STATUS_BORROWED] as $status) {
                foreach ([false, true] as $enabled) {
                    $cases[$scope . ', ' . $status . ', enabled=' . (int) $enabled] = [$scope, $status, $enabled];
                }
            }
        }
        return $cases;
    }

    /**
     * @dataProvider cancelledEventReservationCases
     * @testdox Recurrence cancellation releases requests and approvals while preserving physical loans and past occurrences
     */
    public function testCancellingRecurringEventsReleasesReservations(string $scope, string $status, bool $enabled): void
    {
        $admin = $this->makeInventoryUser('invcancelrecurrence', true);
        $category = $this->getFixture()->createAndSaveCategory('Cancelled recurrence items', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $scope, $status, $enabled): void {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', $status === Reservation::STATUS_REQUESTED ? 'manual' : 'automatic');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Cancelled recurrence projector']);
            $event = (new EventSaveService($this->getDatabase()))->saveData('', [
                'dat_headline' => 'Cancelled recurrence', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
                'event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1,
                'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 3,
            ]);
            $rows = $this->getDatabase()->queryPrepared(
                'SELECT dat_id, dat_uuid, ivr_id FROM ' . TBL_EVENTS . '
                 INNER JOIN ' . TBL_INVENTORY_RESERVATIONS . ' ON ivr_dat_id = dat_id
                 WHERE dat_evr_id = ? ORDER BY dat_begin',
                [$event->getValue('dat_evr_id')],
            )->fetchAll();
            $reservationService = new ReservationService($this->getDatabase());
            if ($status === Reservation::STATUS_BORROWED) {
                foreach ($rows as $row) {
                    $reservationService->startBorrowing(new Reservation($this->getDatabase(), (int) $row['ivr_id']));
                }
            }
            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_EVENTS . ' SET dat_recurrence_status = ? WHERE dat_id = ?',
                ['modified', $rows[1]['dat_id']],
            );
            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_EVENTS . ' SET dat_begin = ?, dat_end = ? WHERE dat_id = ?',
                ['2020-12-01 10:00:00', '2020-12-01 12:00:00', $rows[2]['dat_id']],
            );
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', $enabled ? '1' : '0');
            (new EventService($this->getDatabase()))->deleteData($rows[1]['dat_uuid'], $scope);
            foreach ($rows as $index => $row) {
                $cancelled = $index === 1 || ($scope === 'series' && $index === 0);
                $reservation = new Reservation($this->getDatabase(), (int) $row['ivr_id']);
                $expected = $cancelled && $status !== Reservation::STATUS_BORROWED ? Reservation::STATUS_CANCELLED : $status;
                $this->assertSame($expected, $reservation->getValue('ivr_status'));
                if ($cancelled && $status === Reservation::STATUS_REQUESTED && $enabled) {
                    try {
                        $reservationService->approve($reservation);
                        $this->fail('Requests for cancelled occurrences must no longer be approvable.');
                    } catch (Exception $exception) {
                        $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
                    }
                }
            }
        });
    }

    public static function disabledEventReservationCases(): array
    {
        return [
            'single pending request' => ['this', Reservation::STATUS_REQUESTED, false],
            'single approval' => ['this', Reservation::STATUS_APPROVED, false],
            'single physical loan' => ['this', Reservation::STATUS_BORROWED, false],
            'series pending requests' => ['series', Reservation::STATUS_REQUESTED, false],
            'series approvals' => ['series', Reservation::STATUS_APPROVED, false],
            'series physical loans' => ['series', Reservation::STATUS_BORROWED, false],
            'single zero-duration event' => ['this', Reservation::STATUS_APPROVED, true],
        ];
    }

    /**
     * @dataProvider disabledEventReservationCases
     * @testdox Editing events with reservations disabled cancels their requests and approvals without returning borrowed items
     */
    public function testMovingEventsWithReservationsDisabled(string $scope, string $status, bool $zeroDuration): void
    {
        $admin = $this->makeInventoryUser('invdisabledmove', true);
        $category = $this->getFixture()->createAndSaveCategory('Disabled reservation events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $scope, $status, $zeroDuration): void {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', $status === Reservation::STATUS_REQUESTED ? 'manual' : 'automatic');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Disabled reservation projector']);
            $values = [
                'dat_headline' => 'Disabled reservation event', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
            ];
            if ($scope === 'series') {
                $values += ['event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1, 'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 2];
            }
            $eventService = new EventSaveService($this->getDatabase());
            $event = $eventService->saveData('', $values);
            $rows = $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            if ($status === Reservation::STATUS_BORROWED) {
                foreach ($rows as $reservationId) {
                    (new ReservationService($this->getDatabase()))->startBorrowing(new Reservation($this->getDatabase(), (int) $reservationId));
                }
            }
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '0');
            unset($values['event_inventory_items']);
            $values['event_from_time'] = '14:00';
            $values['event_to_time'] = $zeroDuration ? '14:00' : '16:00';
            $eventService->saveData((string) $event->getValue('dat_uuid'), $values, '', false, $scope);
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? ORDER BY ivr_id',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $expected = $status === Reservation::STATUS_BORROWED ? $status : Reservation::STATUS_CANCELLED;
            $this->assertSame(array_fill(0, count($rows), $expected), $statuses);
            $this->assertSame($expected === Reservation::STATUS_CANCELLED, (new ReservationService($this->getDatabase()))->isAvailable(
                $itemId,
                new DateTimeImmutable('2030-12-01 10:00'),
                new DateTimeImmutable('2030-12-01 12:00'),
            ));
        });
    }

    public static function globalCalendarCases(): array
    {
        return [
            'disabled, single event' => [false, false, 'this'],
            'enabled without items, single event' => [true, false, 'this'],
            'enabled with items, single event' => [true, true, 'this'],
            'disabled, series' => [false, false, 'series'],
            'enabled without items, series' => [true, false, 'series'],
            'enabled with items, series' => [true, true, 'series'],
        ];
    }

    /**
     * @dataProvider globalCalendarCases
     * @testdox Parent organizations can save, edit and delete events in global calendars
     */
    public function testGlobalCalendarEventsRemainEditable(bool $enabled, bool $withItems, string $scope): void
    {
        $admin = $this->makeInventoryUser('invglobalcalendar', true);
        $category = $this->getFixture()->createAndSaveCategory('Global inventory calendar', 'EVT', self::ORG_ID);
        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_CATEGORIES . ' SET cat_org_id = NULL WHERE cat_id = ?', [$category['cat_id']]);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $enabled, $withItems, $scope): void {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', $enabled ? '1' : '0');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Global calendar projector']);
            $values = [
                'dat_headline' => 'Global calendar event', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
            ];
            if ($withItems) {
                $values['event_inventory_items'] = [$itemId];
            }
            if ($scope === 'series') {
                $values += ['event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1, 'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 2];
            }
            $service = new EventSaveService($this->getDatabase());
            $event = $service->saveData('', $values);
            $uuid = (string) $event->getValue('dat_uuid');
            $this->assertTrue((new Event($this->getDatabase(), (int) $event->getValue('dat_id')))->isEditable());
            $values['event_from_time'] = '14:00';
            $values['event_to_time'] = '16:00';
            $service->saveData($uuid, $values, '', false, $scope);
            (new EventService($this->getDatabase()))->deleteData($uuid, $scope);
            $this->assertSame(0, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status IN (?, ?)',
                [$itemId, Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED],
            )->fetchColumn());
        });
    }

    public static function retireItemEditCases(): array
    {
        $cases = [];
        foreach (['form', 'entity', 'action'] as $path) {
            foreach (['none', Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED, Reservation::STATUS_BORROWED, Reservation::STATUS_CANCELLED, Reservation::STATUS_RETURNED] as $status) {
                $cases[$path . ', ' . $status] = [$path, $status];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider retireItemEditCases
     * @testdox Every retirement path protects open reservations while completed history permits retirement
     */
    public function testEveryRetirementPathProtectsReservations(string $path, string $status): void
    {
        $admin = $this->makeInventoryUser('invretirementpaths', true);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($path, $status): void {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $items = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($items, ['ITEMNAME' => 'Retirement guard item']);
            $uuid = $this->uuidOfItem($itemId);
            if ($status !== 'none') {
                $reservation = new Reservation($this->getDatabase());
                $reservation->setValue('ivr_ini_id', $itemId);
                $reservation->setValue('ivr_begin', '2030-12-01 10:00:00');
                $reservation->setValue('ivr_end', '2030-12-01 12:00:00');
                $reservation->setValue('ivr_status', $status);
                $reservation->save();
            }
            $retiredId = (int) $this->getDatabase()->queryPrepared(
                'SELECT ifo_id FROM ' . TBL_INVENTORY_FIELD_OPTIONS . ' WHERE ifo_inf_id = ? AND ifo_value = ?',
                [$items->getProperty('STATUS', 'inf_id'), 'SYS_INVENTORY_FILTER_RETIRED_ITEMS'],
            )->fetchColumn();
            $blocked = in_array($status, [Reservation::STATUS_REQUESTED, Reservation::STATUS_APPROVED, Reservation::STATUS_BORROWED], true);
            try {
                if ($path === 'form') {
                    (new ItemService($this->getDatabase(), $uuid))->saveData(['INF-ITEMNAME' => 'Edited retired item', 'INF-STATUS' => $retiredId]);
                } elseif ($path === 'entity') {
                    $item = new Item($this->getDatabase(), null, $itemId);
                    $item->setValue('ini_status', $retiredId);
                    $item->save();
                } else {
                    $items->retireItem();
                }
                $this->assertFalse($blocked, 'Open reservations must block retirement through every entry point.');
            } catch (Exception $exception) {
                $this->assertTrue($blocked);
                $this->assertSame('SYS_ITEM_HAS_OPEN_RESERVATIONS', $exception->getTranslationId());
            }
            $fresh = new ItemsData($this->getDatabase(), self::ORG_ID);
            $fresh->readItemData($uuid);
            $this->assertSame(!$blocked, (new Item($this->getDatabase(), null, $itemId))->isRetired());
            if ($blocked) {
                $this->assertSame('Retirement guard item', $fresh->getValue('ITEMNAME'));
                // Unrelated edits must remain possible while reservations are open.
                (new ItemService($this->getDatabase(), $uuid))->saveData(['INF-ITEMNAME' => 'Allowed item edit']);
                $fresh->readItemData($uuid);
                $this->assertSame('Allowed item edit', $fresh->getValue('ITEMNAME'));
            }
        });
    }

    /** @testdox Concurrent equipment removal must preserve a reservation handed over after the initial read */
    public function testConcurrentRemovalPreservesHandedOverReservation(): void
    {
        $category = $this->getFixture()->createAndSaveCategory('Concurrent equipment calendar', 'EVT', self::ORG_ID);
        $role = $this->getFixture()->createAndSaveRoleWithRights('Concurrent equipment permissions', self::ORG_ID, ['rol_inventory_admin' => 1, 'rol_events' => 1]);
        $this->getFixture()->assignUserToRole(1, $role['rol_id']);
        $admin = $this->loadUserInOrganization(1, self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($admin, $category, $role): void {
            $database = $this->getDatabase();
            $settings = $GLOBALS['gSettingsManager'];
            $previousEnabled = $settings->getString('inventory_reservations_enabled');
            $readyFile = tempnam(sys_get_temp_dir(), 'admidio-sync-ready-');
            $continueFile = tempnam(sys_get_temp_dir(), 'admidio-sync-continue-');
            unlink($readyFile);
            unlink($continueFile);
            $process = null;
            $event = null;
            $itemId = 0;
            try {
                $settings->set('inventory_reservations_enabled', '1');
                $itemId = $this->createForeignOrganizationItem(self::ORG_ID);
                $event = new Event($database);
                $event->setValue('dat_cat_id', $category['cat_id']);
                $event->setValue('dat_headline', 'Concurrent handover event');
                $event->setValue('dat_begin', '2031-01-10 10:00');
                $event->setValue('dat_end', '2031-01-10 12:00');
                $event->save();
                $reservation = new Reservation($database);
                $reservation->setValue('ivr_ini_id', $itemId);
                $reservation->setValue('ivr_dat_id', $event->getValue('dat_id'));
                $reservation->setValue('ivr_begin', '2031-01-10 10:00:00');
                $reservation->setValue('ivr_end', '2031-01-10 12:00:00');
                $reservation->setValue('ivr_status', Reservation::STATUS_APPROVED);
                $reservation->save();
                $database->endTransaction();
                $process = new Process([PHP_BINARY, __DIR__ . '/reservation_sync_worker.php', (string) $event->getValue('dat_id'), (string) $admin->getValue('usr_id'), $readyFile, $continueFile]);
                $process->start();
                $deadline = microtime(true) + 10;
                while (!file_exists($readyFile) && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($readyFile, $process->getErrorOutput());
                $database->startTransaction();
                $service = new ReservationService($database);
                $service->startBorrowing($reservation);
                (new ItemService($database, $this->uuidOfItem($itemId)))->saveData(['INF-LAST_RECEIVER' => 'Alice', 'INF-BORROW_DATE' => '2020-01-01']);
                $database->endTransaction();
                touch($continueFile);
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(Reservation::STATUS_BORROWED, (new Reservation($database, (int) $reservation->getValue('ivr_id')))->getValue('ivr_status'));
                $this->assertFalse($service->isAvailable($itemId, new DateTimeImmutable('2031-01-10 10:00'), new DateTimeImmutable('2031-01-10 12:00')));
            } finally {
                if ($process !== null && $process->isRunning()) {
                    $process->stop();
                }
                if ($database->isInTransaction()) {
                    $database->rollback();
                }
                if ($itemId > 0) {
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_BORROW_DATA . ' WHERE inb_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEM_DATA . ' WHERE ind_ini_id = ?', [$itemId]);
                    $database->queryPrepared('DELETE FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_id = ?', [$itemId]);
                }
                if ($event !== null) {
                    $database->queryPrepared('DELETE FROM ' . TBL_EVENTS . ' WHERE dat_id = ?', [$event->getValue('dat_id')]);
                }
                $database->queryPrepared('DELETE FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?', [$category['cat_id']]);
                $this->getFixture()->deleteRole($role['rol_id']);
                $settings->set('inventory_reservations_enabled', $previousEnabled);
                foreach ([$readyFile, $continueFile] as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                $database->startTransaction();
            }
        });
    }

    /** @testdox Global calendars remain protected against edits and reservation changes by child organizations */
    public function testChildOrganizationCannotChangeGlobalEventReservations(): void
    {
        $admin = $this->makeInventoryUser('invglobalchild', true);
        $parent = $this->getFixture()->createAndSaveOrganization('Global calendar parent', 'GLBPARENT');
        $category = $this->getFixture()->createAndSaveCategory('Protected global calendar', 'EVT', self::ORG_ID);
        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_CATEGORIES . ' SET cat_org_id = NULL WHERE cat_id = ?', [$category['cat_id']]);
        $eventId = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category): int {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Protected global event');
            $event->setValue('dat_begin', '2030-12-01 10:00');
            $event->setValue('dat_end', '2030-12-01 12:00');
            $event->save();
            return (int) $event->getValue('dat_id');
        });
        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_ORGANIZATIONS . ' SET org_org_id_parent = ? WHERE org_id = ?', [$parent['org_id'], self::ORG_ID]);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($eventId): void {
            $event = new Event($this->getDatabase(), $eventId);
            $this->assertFalse($event->isEditable());
            $service = new ReservationService($this->getDatabase());
            foreach ([false, true] as $enabled) {
                $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', $enabled ? '1' : '0');
                foreach ([
                    static fn() => $service->syncEventReservations($eventId, [], new DateTimeImmutable('2030-12-01 10:00'), new DateTimeImmutable('2030-12-01 12:00')),
                    static fn() => $service->cancelEventReservations($eventId),
                ] as $change) {
                    try {
                        $change();
                        $this->fail('Child organizations must not mutate global event reservations.');
                    } catch (Exception $exception) {
                        $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
                    }
                }
            }
            try {
                (new EventService($this->getDatabase()))->deleteData((string) $event->getValue('dat_uuid'));
                $this->fail('Child organizations must not delete global events.');
            } catch (Exception $exception) {
                $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
            }
        });
    }

    public static function eventReapprovalScopes(): array
    {
        return [
            'single event, manual approval' => ['this', 'manual'],
            'single event, automatic approval' => ['this', 'automatic'],
            'event series, manual approval' => ['series', 'manual'],
            'event series, automatic approval' => ['series', 'automatic'],
            'moved series, manual approval' => ['moved-series', 'manual'],
            'moved series, automatic approval' => ['moved-series', 'automatic'],
            'overlapping series, automatic approval' => ['overlapping-series', 'automatic'],
            'shortened series, automatic approval' => ['shortened-series', 'automatic'],
            'changed interval, automatic approval' => ['interval-series', 'automatic'],
        ];
    }

    /**
     * @dataProvider eventReapprovalScopes
     * @testdox Event and recurrence changes retain only approvals for unchanged periods
     */
    public function testEventSaveRequestsEquipmentApprovalAgain(string $scope, string $approval): void
    {
        $moveSeries = in_array($scope, ['moved-series', 'overlapping-series'], true);
        $overlap = $scope === 'overlapping-series';
        $shorten = $scope === 'shortened-series';
        $changeInterval = $scope === 'interval-series';
        $scope = $scope === 'this' ? 'this' : 'series';
        $admin = $this->makeInventoryUser('invsaveeventreapproval', true);
        $category = $this->getFixture()->createAndSaveCategory('Saved reapproval events', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category, $scope, $approval, $moveSeries, $overlap, $shorten, $changeInterval) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', $approval);
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Rescheduled event projector']);
            $values = [
                'dat_headline' => 'Rescheduled event approval', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
            ];
            if ($scope === 'series') {
                $values += ['event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1, 'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 2];
            }
            $service = new EventSaveService($this->getDatabase());
            $event = $service->saveData('', $values);
            $reservationIds = $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ?',
                [$itemId],
            )->fetchAll(\PDO::FETCH_COLUMN);
            $reservationService = new ReservationService($this->getDatabase());
            foreach ($reservationIds as $id) {
                $reservation = new Reservation($this->getDatabase(), (int) $id);
                if ($reservation->getValue('ivr_status') === Reservation::STATUS_REQUESTED) {
                    $reservationService->approve($reservation);
                }
            }
            // Only changing other event details must not invalidate approvals.
            $values['dat_location'] = 'Updated venue';
            $service->saveData((string) $event->getValue('dat_uuid'), $values, '', false, $scope);
            $this->assertSame(count($reservationIds), (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status = ?',
                [$itemId, Reservation::STATUS_APPROVED],
            )->fetchColumn());
            $unchangedTime = $overlap || $shorten || $changeInterval;
            $values['event_from_time'] = $unchangedTime ? '10:00' : '14:00';
            $values['event_to_time'] = $unchangedTime ? '12:00' : '16:00';
            if ($moveSeries) {
                $values['event_from'] = $overlap ? '2030-12-02' : '2030-12-04';
                $values['event_to'] = $values['event_from'];
            }
            if ($shorten) {
                $values['event_recurrence_count'] = 1;
            }
            if ($changeInterval) {
                $values['event_recurrence_interval'] = 2;
            }
            $service->saveData((string) $event->getValue('dat_uuid'), $values, '', false, $scope);
            $expectedApproved = $shorten || $changeInterval ? 1 : 0;
            $expectedCancelled = count($reservationIds) - $expectedApproved;
            $expectedRequested = $shorten ? 0 : $expectedCancelled;
            $this->assertSame($expectedApproved, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status = ?',
                [$itemId, Reservation::STATUS_APPROVED],
            )->fetchColumn());
            $this->assertSame($expectedRequested, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status = ?',
                [$itemId, Reservation::STATUS_REQUESTED],
            )->fetchColumn());
            $this->assertSame($expectedCancelled, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_ini_id = ? AND ivr_status = ?',
                [$itemId, Reservation::STATUS_CANCELLED],
            )->fetchColumn());
        });
    }

    /** @testdox Series time changes preserve modified and cancelled occurrences */
    public function testSeriesTimeChangesPreserveManualExceptions(): void
    {
        $admin = $this->makeInventoryUser('invseriesexceptions', true);
        $category = $this->getFixture()->createAndSaveCategory('Series exceptions', 'EVT', self::ORG_ID);
        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category): void {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservations_events_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');
            $GLOBALS['gTimezone'] = 'UTC';
            $itemId = $this->createItem(new ItemsData($this->getDatabase(), self::ORG_ID), ['ITEMNAME' => 'Series exception projector']);
            $values = [
                'dat_headline' => 'Series exceptions', 'dat_location' => '', 'dat_all_day' => '0',
                'event_from' => '2030-12-01', 'event_from_time' => '10:00',
                'event_to' => '2030-12-01', 'event_to_time' => '12:00',
                'cat_uuid' => $category['cat_uuid'], 'dat_max_members' => 0,
                'event_participation_possible' => 0, 'event_deadline' => '', 'event_deadline_time' => '',
                'event_inventory_items' => [$itemId],
                'event_recurrence_frequency' => 'daily', 'event_recurrence_interval' => 1,
                'event_recurrence_end_type' => 'count', 'event_recurrence_count' => 3,
            ];
            $service = new EventSaveService($this->getDatabase());
            $event = $service->saveData('', $values);
            $exceptions = $this->getDatabase()->queryPrepared(
                'SELECT dat_id FROM ' . TBL_EVENTS . ' WHERE dat_evr_id = ? AND dat_recurrence_status = ? ORDER BY dat_begin',
                [$event->getValue('dat_evr_id'), 'generated'],
            )->fetchAll(\PDO::FETCH_COLUMN);
            foreach (['modified', 'cancelled'] as $index => $status) {
                $this->getDatabase()->queryPrepared(
                    'UPDATE ' . TBL_EVENTS . ' SET dat_recurrence_status = ? WHERE dat_id = ?',
                    [$status, $exceptions[$index]],
                );
            }
            (new ReservationService($this->getDatabase()))->syncEventReservations(
                (int) $exceptions[1],
                [],
                new DateTimeImmutable('2030-12-03 10:00'),
                new DateTimeImmutable('2030-12-03 12:00'),
            );
            $values['event_from_time'] = '14:00';
            $values['event_to_time'] = '16:00';
            $service->saveData((string) $event->getValue('dat_uuid'), $values, '', false, 'series');
            $this->assertSame(3, (int) $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_EVENTS . ' WHERE dat_evr_id = ?',
                [$event->getValue('dat_evr_id')],
            )->fetchColumn(), 'Manual exceptions must not be recreated at the new series time.');
            $this->assertSame('2030-12-02 10:00:00', $this->getDatabase()->queryPrepared(
                'SELECT ivr_begin FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_status = ?',
                [$exceptions[0], Reservation::STATUS_APPROVED],
            )->fetchColumn());
        });
    }

    /**
     * @testdox A conflicting event move preserves its existing approval and period
     */
    public function testMovingApprovedEventReservationRejectsConflictsWithoutChangingExistingReservation(): void
    {
        $admin = $this->makeInventoryUser('inveventreservationmove', true);
        $category = $this->getFixture()->createAndSaveCategory('Moving reservation events', 'EVT', self::ORG_ID);

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($category) {
            $GLOBALS['gSettingsManager']->set('inventory_reservations_enabled', '1');
            $GLOBALS['gSettingsManager']->set('inventory_reservation_approval', 'automatic');

            $itemsData = new ItemsData($this->getDatabase(), self::ORG_ID);
            $itemId = $this->createItem($itemsData, ['ITEMNAME' => 'Moving event reservation projector']);
            $service = new ReservationService($this->getDatabase());

            $firstEvent = new Event($this->getDatabase());
            $firstEvent->setValue('dat_cat_id', $category['cat_id']);
            $firstEvent->setValue('dat_headline', 'First reservation event');
            $firstEvent->setValue('dat_begin', '2030-09-01 10:00:00');
            $firstEvent->setValue('dat_end', '2030-09-01 12:00:00');
            $firstEvent->save();
            $service->syncEventReservations(
                (int) $firstEvent->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-09-01 10:00:00'),
                new DateTimeImmutable('2030-09-01 12:00:00'),
            );

            $secondEvent = new Event($this->getDatabase());
            $secondEvent->setValue('dat_cat_id', $category['cat_id']);
            $secondEvent->setValue('dat_headline', 'Second reservation event');
            $secondEvent->setValue('dat_begin', '2030-09-01 13:00:00');
            $secondEvent->setValue('dat_end', '2030-09-01 15:00:00');
            $secondEvent->save();
            $service->syncEventReservations(
                (int) $secondEvent->getValue('dat_id'),
                [$itemId],
                new DateTimeImmutable('2030-09-01 13:00:00'),
                new DateTimeImmutable('2030-09-01 15:00:00'),
            );

            $secondReservation = $this->getDatabase()->queryPrepared(
                'SELECT ivr_id, ivr_status, ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [(int) $secondEvent->getValue('dat_id')],
            )->fetch();
            $this->assertSame(Reservation::STATUS_APPROVED, $secondReservation['ivr_status']);

            try {
                $service->syncEventReservations(
                    (int) $secondEvent->getValue('dat_id'),
                    [$itemId],
                    new DateTimeImmutable('2030-09-01 11:00:00'),
                    new DateTimeImmutable('2030-09-01 14:00:00'),
                );
                $this->fail('Moving an event reservation into another approved reservation must fail.');
            } catch (\Admidio\Infrastructure\Exception $exception) {
                $this->assertSame('SYS_ITEM_NOT_AVAILABLE_FOR_PERIOD', $exception->getTranslationId());
                $this->assertStringContainsString('Moving event reservation projector', $exception->getMessage());
            }

            $unchangedReservation = $this->getDatabase()->queryPrepared(
                'SELECT ivr_status, ivr_begin, ivr_end FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_id = ?',
                [(int) $secondReservation['ivr_id']],
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
            $borrowedItemId = $this->createItem($itemsData, ['ITEMNAME' => 'Handed over event projector']);
            $openItemId = $this->createItem($itemsData, ['ITEMNAME' => 'Open event microphone']);
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Event removing equipment');
            $event->setValue('dat_begin', '2030-10-01 10:00:00');
            $event->setValue('dat_end', '2030-10-01 12:00:00');
            $event->save();

            $service = new ReservationService($this->getDatabase());
            $service->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [$borrowedItemId, $openItemId],
                new DateTimeImmutable('2030-10-01 10:00:00'),
                new DateTimeImmutable('2030-10-01 12:00:00'),
            );

            $borrowedReservation = new Reservation($this->getDatabase(), (int) $this->getDatabase()->queryPrepared(
                'SELECT ivr_id FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ? AND ivr_ini_id = ?',
                [(int) $event->getValue('dat_id'), $borrowedItemId],
            )->fetchColumn());
            $service->startBorrowing($borrowedReservation);

            $service->syncEventReservations(
                (int) $event->getValue('dat_id'),
                [],
                new DateTimeImmutable('2030-10-01 10:00:00'),
                new DateTimeImmutable('2030-10-01 12:00:00'),
            );

            $statuses = $this->getDatabase()->queryPrepared(
                'SELECT ivr_ini_id, ivr_status FROM ' . TBL_INVENTORY_RESERVATIONS . ' WHERE ivr_dat_id = ?',
                [(int) $event->getValue('dat_id')],
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            $this->assertSame(Reservation::STATUS_BORROWED, $statuses[$borrowedItemId]);
            $this->assertSame(Reservation::STATUS_CANCELLED, $statuses[$openItemId]);
        });
    }
}
