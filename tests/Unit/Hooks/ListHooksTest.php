<?php
/**
 * The generic list hooks - list_columns, list_data, list_rendered_data, list_row_actions - proven on
 * contacts.php/contacts_data.php, the first module to carry them (SS 2.7 of HOOKS_PLAN.md).
 *
 * Neither file can run here - both need a full database, session and settings stack - so this
 * reproduces the exact dispatch lines verbatim rather than executing the module: the guard in
 * contacts.php that refuses a list_columns filter which changes the column count, and the three
 * dispatch calls in contacts_data.php's row loop. What is not exercised is the surrounding SQL and
 * permission logic, which this filter layer does not touch.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use UnexpectedValueException;

class ListHooksTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    /**
     * contacts.php's own guard, copied verbatim: list_columns may relabel a column but not add or
     * remove one, because contacts_data.php still builds every row by position.
     */
    private function filterContactsColumnHeading(array $columnHeading): array
    {
        $filtered = Hooks::applyTypedFilters('list_columns', $columnHeading, 'contacts');
        if (count($filtered) !== count($columnHeading)) {
            throw new UnexpectedValueException('A list_columns filter for "contacts" changed the number of columns.');
        }
        return $filtered;
    }

    public function testListColumnsCanRelabelAColumn(): void
    {
        Hooks::addFilter('list_columns', function (array $columns) {
            $columns[1] = 'Renamed';
            return $columns;
        });
        $result = $this->filterContactsColumnHeading(array('No', 'Name', 'Email'));

        $this->assertSame(array('No', 'Renamed', 'Email'), $result);
    }

    public function testListColumnsCannotAddAColumn(): void
    {
        Hooks::addFilter('list_columns', function (array $columns) {
            $columns[] = 'Extra';
            return $columns;
        });

        $this->expectException(UnexpectedValueException::class);
        $this->filterContactsColumnHeading(array('No', 'Name', 'Email'));
    }

    public function testListDataFiltersTheRawRowBeforeAnythingIsFormatted(): void
    {
        Hooks::addFilter('list_data', function (array $row, string $listId) {
            $row['login_name'] = strtoupper($row['login_name']);
            return $row;
        });
        $row = Hooks::applyTypedFilters('list_data', array('usr_uuid' => 'u-1', 'login_name' => 'jdoe'), 'contacts');

        $this->assertSame('JDOE', $row['login_name']);
    }

    public function testListRowActionsCanAddAnActionIcon(): void
    {
        Hooks::addFilter('list_row_actions', function (string $actions, string $listId, array $row) {
            return $actions . '<a href="#extra">Extra for ' . $row['usr_uuid'] . '</a>';
        });
        $actions = Hooks::applyTypedFilters('list_row_actions', '<a href="#edit">Edit</a>', 'contacts', array('usr_uuid' => 'u-1'));

        $this->assertStringContainsString('Extra for u-1', $actions);
    }

    public function testListRenderedDataFiltersTheFullyRenderedRowBeforeItIsSent(): void
    {
        Hooks::addFilter('list_rendered_data', function (array $columnValues, string $listId, array $row) {
            $columnValues['0'] = '<b>' . $columnValues['0'] . '</b>';
            return $columnValues;
        });
        $columnValues = array('DT_RowId' => 'row_members_u-1', '0' => '1', '1' => 'Doe, Jane');
        $columnValues = Hooks::applyTypedFilters('list_rendered_data', $columnValues, 'contacts', array('usr_uuid' => 'u-1'));

        $this->assertSame('<b>1</b>', $columnValues['0']);
    }

    public function testWithoutAListenerNothingChanges(): void
    {
        $row = array('usr_uuid' => 'u-1', 'login_name' => 'jdoe');
        $this->assertSame($row, Hooks::applyTypedFilters('list_data', $row, 'contacts'));
        $this->assertSame(
            array('No', 'Name', 'Email'),
            $this->filterContactsColumnHeading(array('No', 'Name', 'Email')),
            'the count guard passes when nobody filters list_columns'
        );
    }
}
