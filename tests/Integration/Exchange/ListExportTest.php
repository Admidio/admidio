<?php
/**
 * List Export Tests
 *
 * Tests the data behind every member list and the files it can be written to. ListData collects the
 * rows, either from a list configuration, from a plain SQL statement or from an array, and
 * createExportFile() turns them into a spreadsheet through PhpSpreadsheet.
 *
 * The export goes through createExportFile() and not through export(), which sends HTTP headers and
 * ends the request. The files are written to the temporary folder and removed again here.
 */

namespace Admidio\Tests\Integration\Exchange;

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\ValueObject\ListData;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ListExportTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    /**
     * Files the tests wrote, removed again in tearDown.
     *
     * @var array<int,string>
     */
    private array $writtenFiles = array();

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->writtenFiles = array();

        parent::tearDown();
    }

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator that the installation created.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    /**
     * Create a role with two named members.
     *
     * @return array{rol_uuid: string, names: array<int,string>}
     */
    private function roleWithMembers(): array
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Export Role', self::ORG_ID);
        $names = array('Alpha', 'Beta');

        foreach ($names as $index => $name) {
            $user = $fixture->createAndSaveUser('exp' . $index, 'e' . $index . '@example.local');
            $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
            $this->asAdministrator(function () use ($user, $name) {
                $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $user['usr_id']);
                $entity->saveChangesWithoutRights();
                $entity->setValue('LAST_NAME', $name);
                $entity->setValue('FIRST_NAME', 'Test');
                $entity->save();
            });
        }

        return array('rol_uuid' => $role['rol_uuid'], 'names' => $names);
    }

    /**
     * A list configuration with the two name columns.
     */
    private function nameList(): ListConfiguration
    {
        $list = new ListConfiguration($this->getDatabase());
        $list->setValue('lst_name', 'Export list');
        $list->addColumn((int) $GLOBALS['gProfileFields']->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $list->addColumn((int) $GLOBALS['gProfileFields']->getProperty('FIRST_NAME', 'usf_id'));
        $list->save();

        return $list;
    }

    /**
     * Remember an exported file so that tearDown removes it.
     */
    private function remember(array $export): array
    {
        $this->writtenFiles[] = $export['path'];

        return $export;
    }

    /**
     * Test that rows can be supplied directly
     *
     * @testdox Rows given as an array are returned with their headline
     */
    public function testRowsGivenAsAnArrayAreReturnedWithTheirHeadline(): void
    {
        $listData = new ListData();
        $listData->setColumnHeadlines(array('Surname', 'First name'));
        $listData->setDataByArray(array(
            array('Alpha', 'Test'),
            array('Beta', 'Test')
        ));

        // the headline counts as a row of the data
        $this->assertEquals(3, $listData->rowCount());
        $this->assertEquals(
            array(array('Surname', 'First name'), array('Alpha', 'Test'), array('Beta', 'Test')),
            $listData->getData()
        );
    }

    /**
     * Test that rows can come from a statement
     *
     * @testdox Rows can be collected from a plain SQL statement
     */
    public function testRowsCanBeCollectedFromASqlStatement(): void
    {
        $fixture = $this->getFixture();
        $first = $fixture->createAndSaveUser('sqla', 'sa@example.local');
        $second = $fixture->createAndSaveUser('sqlb', 'sb@example.local');

        $listData = new ListData();
        $listData->setDataBySql(
            'SELECT usr_login_name FROM ' . TBL_USERS . ' WHERE usr_id IN (?, ?) ORDER BY usr_login_name',
            array($first['usr_id'], $second['usr_id'])
        );

        $this->assertEquals(2, $listData->rowCount());
        $this->assertEquals(array('sqla', 'sqlb'), array_column($listData->getData(), 'usr_login_name'));
    }

    /**
     * Test that rows can come from a list configuration
     *
     * @testdox Rows can be collected through a list configuration
     */
    public function testRowsCanBeCollectedThroughAListConfiguration(): void
    {
        $role = $this->roleWithMembers();

        $data = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return array($listData->rowCount(), $listData->getData());
        });

        $this->assertEquals(2, $data[0]);

        // the columns are named after the profile fields, not after their position
        $this->assertEquals($role['names'], array_column($data[1], 'last_name'));
        $this->assertEquals(array('Test', 'Test'), array_column($data[1], 'first_name'));
    }

    /**
     * Test that the html format links to the profiles
     *
     * @testdox The html format turns the names into links to the profile
     */
    public function testHtmlFormatTurnsTheNamesIntoLinks(): void
    {
        $role = $this->roleWithMembers();

        $data = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $listData->getData('html');
        });

        $this->assertStringContainsString('<a href=', $data[0]['last_name']);
        $this->assertStringContainsString('profile.php', $data[0]['last_name']);
        $this->assertStringContainsString('Alpha', $data[0]['last_name']);
    }

    /**
     * Test the csv file
     *
     * @testdox A list is written to a csv file with its values
     */
    public function testListIsWrittenToACsvFile(): void
    {
        $role = $this->roleWithMembers();

        $export = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $this->remember($listData->createExportFile('members', 'csv'));
        });

        $this->assertEquals('members.csv', $export['filename']);
        $this->assertEquals('text/csv', $export['contentType']);
        $this->assertFileExists($export['path']);

        $content = file_get_contents($export['path']);
        $this->assertStringContainsString('Alpha', $content);
        $this->assertStringContainsString('Beta', $content);
        $this->assertStringContainsString('Test', $content);
    }

    /**
     * Test the xlsx file
     *
     * @testdox A list is written to an xlsx file
     */
    public function testListIsWrittenToAnXlsxFile(): void
    {
        $role = $this->roleWithMembers();

        $export = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $this->remember($listData->createExportFile('members', 'xlsx'));
        });

        $this->assertEquals('members.xlsx', $export['filename']);
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $export['contentType']
        );
        $this->assertFileExists($export['path']);

        // an xlsx file is a zip archive, which is what the reader on the other side expects
        $this->assertGreaterThan(0, filesize($export['path']));
        $this->assertEquals('PK', substr(file_get_contents($export['path']), 0, 2));
    }

    /**
     * Test the ods file
     *
     * @testdox A list is written to an ods file
     */
    public function testListIsWrittenToAnOdsFile(): void
    {
        $role = $this->roleWithMembers();

        $export = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $this->remember($listData->createExportFile('members', 'ods'));
        });

        $this->assertEquals('members.ods', $export['filename']);
        $this->assertEquals('application/vnd.oasis.opendocument.spreadsheet', $export['contentType']);
        $this->assertFileExists($export['path']);
        $this->assertEquals('PK', substr(file_get_contents($export['path']), 0, 2));
    }

    /**
     * Test that an unknown format falls back
     *
     * @testdox An unknown export format is written as csv
     */
    public function testUnknownExportFormatIsWrittenAsCsv(): void
    {
        $role = $this->roleWithMembers();

        $export = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $this->remember($listData->createExportFile('members', 'something-else'));
        });

        $this->assertEquals('members.csv', $export['filename']);
        $this->assertEquals('text/csv', $export['contentType']);
    }

    /**
     * Test that an empty export is refused
     *
     * @testdox Exporting a list without rows is refused
     */
    public function testExportingAListWithoutRowsIsRefused(): void
    {
        $listData = new ListData();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The export file will contain no data.');
        $listData->createExportFile('empty', 'csv');
    }

    /**
     * Test that a list of nobody stays empty
     *
     * @testdox A list configuration with no matching members produces no rows
     */
    public function testListConfigurationWithNoMatchingMembersProducesNoRows(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Empty Role', self::ORG_ID);

        $count = $this->asAdministrator(function () use ($role) {
            $listData = new ListData();
            $listData->setDataByConfiguration($this->nameList(), array('showRolesMembers' => array($role['rol_uuid'])));

            return $listData->rowCount();
        });

        $this->assertEquals(0, $count);
    }
}
