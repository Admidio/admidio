<?php
/**
 * The generic list hooks - list_columns, list_data, list_rendered_data, list_row_actions - proven on
 * contacts.php/contacts_data.php, the first module to carry them (§2.7 of HOOKS_PLAN.md).
 *
 * Neither file can run here - both need a full database, session and settings stack - so this
 * reproduces the exact dispatch lines verbatim rather than executing the module: the guard in
 * contacts.php that refuses a list_columns filter which changes the column count, and the three
 * dispatch calls in contacts_data.php's row loop. What is not exercised is the surrounding SQL and
 * permission logic, which this filter layer does not touch.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;

/**
 * contacts.php's own guard, copied verbatim: list_columns may relabel a column but not add or
 * remove one, because contacts_data.php still builds every row by position.
 */
function filterContactsColumnHeading(array $columnHeading): array
{
    $filtered = Hooks::applyTypedFilters('list_columns', $columnHeading, 'contacts');
    if (count($filtered) !== count($columnHeading)) {
        throw new \UnexpectedValueException('A list_columns filter for "contacts" changed the number of columns.');
    }
    return $filtered;
}

// ------------------------------------------------------------------- list_columns may only relabel
Hooks::reset();
Hooks::addFilter('list_columns', function (array $columns) {
    $columns[1] = 'Renamed';
    return $columns;
});
$result = filterContactsColumnHeading(array('No', 'Name', 'Email'));
check('list_columns can relabel a column', $result === array('No', 'Renamed', 'Email'), implode(',', $result));

Hooks::reset();
Hooks::addFilter('list_columns', function (array $columns) {
    $columns[] = 'Extra';
    return $columns;
});
$threw = false;
try {
    filterContactsColumnHeading(array('No', 'Name', 'Email'));
} catch (\UnexpectedValueException $exception) {
    $threw = true;
}
check('list_columns cannot add a column - contacts_data.php builds rows by position', $threw);

// -------------------------------------------------------------------------------------- list_data
Hooks::reset();
Hooks::addFilter('list_data', function (array $row, string $listId) {
    $row['login_name'] = strtoupper($row['login_name']);
    return $row;
});
$row = Hooks::applyTypedFilters('list_data', array('usr_uuid' => 'u-1', 'login_name' => 'jdoe'), 'contacts');
check('list_data filters the raw row before anything is formatted from it', $row['login_name'] === 'JDOE', $row['login_name']);

// -------------------------------------------------------------------------------- list_row_actions
Hooks::reset();
Hooks::addFilter('list_row_actions', function (string $actions, string $listId, array $row) {
    return $actions . '<a href="#extra">Extra for ' . $row['usr_uuid'] . '</a>';
});
$actions = Hooks::applyTypedFilters('list_row_actions', '<a href="#edit">Edit</a>', 'contacts', array('usr_uuid' => 'u-1'));
check('list_row_actions can add an action icon to the row', str_contains($actions, 'Extra for u-1'), $actions);

// ------------------------------------------------------------------------------ list_rendered_data
Hooks::reset();
Hooks::addFilter('list_rendered_data', function (array $columnValues, string $listId, array $row) {
    $columnValues['0'] = '<b>' . $columnValues['0'] . '</b>';
    return $columnValues;
});
$columnValues = array('DT_RowId' => 'row_members_u-1', '0' => '1', '1' => 'Doe, Jane');
$columnValues = Hooks::applyTypedFilters('list_rendered_data', $columnValues, 'contacts', array('usr_uuid' => 'u-1'));
check('list_rendered_data filters the fully rendered row before it is sent', $columnValues['0'] === '<b>1</b>', $columnValues['0']);

// ------------------------------------------------------------------- without a listener, no-op
Hooks::reset();
$row = array('usr_uuid' => 'u-1', 'login_name' => 'jdoe');
check('list_data with no listener leaves the row unchanged', Hooks::applyTypedFilters('list_data', $row, 'contacts') === $row);
check(
    'the count guard passes when nobody filters list_columns',
    filterContactsColumnHeading(array('No', 'Name', 'Email')) === array('No', 'Name', 'Email')
);

echo "\n";
exit(testSummary());
