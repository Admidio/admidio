<?php
/**
 * Menu Entry Tests
 *
 * Tests the entries of the main menu. Unlike almost every other module the menu is not scoped to an
 * organization: adm_menu has no org column, so one menu is shared by the whole installation. The
 * entries form a two level tree, where a node (men_node) groups the entries below it and men_order
 * is counted per node.
 */

namespace Admidio\Tests\Integration\Menu;

use Admidio\Infrastructure\Exception;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;

class MenuEntryTest extends DatabaseTestCase
{
    /**
     * The node "extensions" of a standard installation. It is created without any entries below it,
     * so a test can fill it without having to care about the entries of the other nodes.
     */
    private const EMPTY_NODE_NAME = 'extensions';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Read the id of a menu entry by its internal name.
     */
    private function idOf(string $nameIntern): int
    {
        $sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = ?';

        return (int) $this->getDatabase()->queryPrepared($sql, [$nameIntern])->fetchColumn();
    }

    /**
     * Create an entry below the given node.
     */
    private function createEntry(string $name, int $parentId, string $url = ''): MenuEntry
    {
        $entry = new MenuEntry($this->getDatabase());
        $entry->setValue('men_men_id_parent', $parentId);
        $entry->setValue('men_name', $name);
        if ($url !== '') {
            $entry->setValue('men_url', $url);
        }
        $entry->save();

        return $entry;
    }

    /**
     * Read the entries below a node as internal name => order.
     *
     * @return array<string,int>
     */
    private function orderOfNode(int $parentId): array
    {
        $sql = 'SELECT men_name_intern, men_order FROM ' . TBL_MENU . '
                 WHERE men_men_id_parent = ? ORDER BY men_order';
        $rows = $this->getDatabase()->queryPrepared($sql, [$parentId])->fetchAll();

        return array_combine(array_column($rows, 'men_name_intern'), array_map('intval', array_column($rows, 'men_order')));
    }

    /**
     * Test that the installation creates a menu
     *
     * @testdox The installation creates a menu of nodes with entries below them
     */
    public function testInstallationCreatesTheStandardMenu(): void
    {
        $db = $this->getDatabase();

        $nodes = $db->queryPrepared('SELECT men_name_intern, men_order FROM ' . TBL_MENU . '
                                      WHERE men_men_id_parent IS NULL ORDER BY men_order')->fetchAll();
        $this->assertEquals(['modules', 'administration', 'extensions'], array_column($nodes, 'men_name_intern'));

        // a node is flagged as such and belongs to no module
        $row = $db->queryPrepared('SELECT men_node, men_standard, men_com_id, men_url FROM ' . TBL_MENU . '
                                    WHERE men_name_intern = ?', ['modules'])->fetch();
        $this->assertTrue((bool) $row['men_node']);
        $this->assertTrue((bool) $row['men_standard']);
        $this->assertNull($row['men_url']);

        // the entries below a node are not nodes themselves and point at a module
        $entry = $db->queryPrepared('SELECT men_node, men_standard, men_com_id, men_url FROM ' . TBL_MENU . '
                                      WHERE men_name_intern = ?', ['announcements'])->fetch();
        $this->assertFalse((bool) $entry['men_node']);
        $this->assertTrue((bool) $entry['men_standard']);
        $this->assertNotNull($entry['men_com_id']);
        $this->assertStringContainsString('announcements.php', $entry['men_url']);

        // the node "extensions" is delivered empty
        $this->assertCount(0, $this->orderOfNode($this->idOf(self::EMPTY_NODE_NAME)));
    }

    /**
     * Test that the internal name is derived from the name
     *
     * @testdox A new entry gets an internal name derived from its name
     */
    public function testNewEntryGetsAnInternalNameDerivedFromItsName(): void
    {
        $entry = $this->createEntry('My own page', $this->idOf(self::EMPTY_NODE_NAME), '/modules/own.php');

        $this->assertEquals('MY_OWN_PAGE', $entry->getValue('men_name_intern'));
        $this->assertNotEmpty($entry->getValue('men_uuid'));

        $sql = 'SELECT men_name, men_name_intern, men_url FROM ' . TBL_MENU . ' WHERE men_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$entry->getValue('men_id')])->fetch();
        $this->assertEquals('My own page', $row['men_name']);
        $this->assertEquals('MY_OWN_PAGE', $row['men_name_intern']);
        $this->assertEquals('/modules/own.php', $row['men_url']);
    }

    /**
     * Test that internal names stay unique
     *
     * @testdox A second entry with the same name gets a numbered internal name
     */
    public function testDuplicateNameGetsANumberedInternalName(): void
    {
        $node = $this->idOf(self::EMPTY_NODE_NAME);

        $first = $this->createEntry('Same name', $node);
        $second = $this->createEntry('Same name', $node);
        $third = $this->createEntry('Same name', $node);

        $this->assertEquals('SAME_NAME', $first->getValue('men_name_intern'));
        $this->assertEquals('SAME_NAME_2', $second->getValue('men_name_intern'));
        $this->assertEquals('SAME_NAME_3', $third->getValue('men_name_intern'));
    }

    /**
     * Test that a new entry goes to the end
     *
     * @testdox A new entry is appended at the end of its node
     */
    public function testNewEntryIsAppendedAtTheEndOfItsNode(): void
    {
        $node = $this->idOf(self::EMPTY_NODE_NAME);

        $this->createEntry('First', $node);
        $this->createEntry('Second', $node);
        $this->createEntry('Third', $node);

        $this->assertEquals(['FIRST' => 1, 'SECOND' => 2, 'THIRD' => 3], $this->orderOfNode($node));
    }

    /**
     * Test that the order is counted per node
     *
     * @testdox The order of an entry is counted within its own node
     */
    public function testOrderIsCountedPerNode(): void
    {
        $emptyNode = $this->idOf(self::EMPTY_NODE_NAME);
        $administration = $this->idOf('administration');
        $countInAdministration = count($this->orderOfNode($administration));

        $inEmpty = $this->createEntry('In extensions', $emptyNode);
        $inAdministration = $this->createEntry('In administration', $administration);

        // the empty node starts counting at one, the filled one continues after its last entry
        $this->assertEquals(1, (int) $inEmpty->getValue('men_order'));
        $this->assertEquals($countInAdministration + 1, (int) $inAdministration->getValue('men_order'));
    }

    /**
     * Test that the icon name is validated
     *
     * @testdox An icon name that is not a Bootstrap icon is refused
     */
    public function testInvalidIconNameIsRefused(): void
    {
        $entry = new MenuEntry($this->getDatabase());

        // a Bootstrap icon name is lower case letters, digits and hyphens
        $this->assertTrue($entry->setValue('men_icon', 'star-fill'));

        $this->expectException(Exception::class);
        $entry->setValue('men_icon', 'Star Fill');
    }

    /**
     * Test that an entry can be moved within its node
     *
     * @testdox Moving an entry up swaps it with the entry above it
     */
    public function testMovingAnEntryUpSwapsItWithTheOneAbove(): void
    {
        $node = $this->idOf(self::EMPTY_NODE_NAME);
        $this->createEntry('First', $node);
        $second = $this->createEntry('Second', $node);
        $this->createEntry('Third', $node);

        // the entry has to be read from the database again: moveSequence saves the object, and
        // MenuEntry::save() renumbers and renames a record it still considers new
        $entry = new MenuEntry($this->getDatabase(), (int) $second->getValue('men_id'));
        $entry->moveSequence(MenuEntry::MOVE_UP);

        $this->assertEquals(['SECOND' => 1, 'FIRST' => 2, 'THIRD' => 3], $this->orderOfNode($node));

        // and back again
        $entry = new MenuEntry($this->getDatabase(), (int) $second->getValue('men_id'));
        $entry->moveSequence(MenuEntry::MOVE_DOWN);

        $this->assertEquals(['FIRST' => 1, 'SECOND' => 2, 'THIRD' => 3], $this->orderOfNode($node));
    }

    /**
     * Test that the first entry cannot be moved up
     *
     * @testdox The first entry of a node stays where it is when it is moved up
     */
    public function testFirstEntryCannotBeMovedUp(): void
    {
        $node = $this->idOf(self::EMPTY_NODE_NAME);
        $first = $this->createEntry('First', $node);
        $this->createEntry('Second', $node);

        $entry = new MenuEntry($this->getDatabase(), (int) $first->getValue('men_id'));
        $entry->moveSequence(MenuEntry::MOVE_UP);

        $this->assertEquals(['FIRST' => 1, 'SECOND' => 2], $this->orderOfNode($node));
    }

    /**
     * Test that a name which is a translation id is translated
     *
     * @testdox A menu name that is a translation id is translated on read
     */
    public function testTranslationIdIsTranslatedOnRead(): void
    {
        $entry = new MenuEntry($this->getDatabase());
        $entry->readDataByColumns(array('men_name_intern' => 'overview'));

        // the standard entries store the id of the language string, not the text
        $this->assertEquals('SYS_OVERVIEW', $entry->getValue('men_name', 'database'));
        $this->assertEquals('Overview', $entry->getValue('men_name'));

        // a name that is no translation id is returned unchanged
        $own = $this->createEntry('Our own page', $this->idOf(self::EMPTY_NODE_NAME));
        $this->assertEquals('Our own page', $own->getValue('men_name'));
    }

    /**
     * Test that an entry can be deleted
     *
     * @testdox A menu entry can be deleted
     */
    public function testEntryCanBeDeleted(): void
    {
        $node = $this->idOf(self::EMPTY_NODE_NAME);
        $entry = $this->createEntry('To be removed', $node);
        $menId = (int) $entry->getValue('men_id');

        $reread = new MenuEntry($this->getDatabase(), $menId);
        $this->assertTrue($reread->delete());

        $sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_id = ?';
        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$menId])->fetch());
    }
}
