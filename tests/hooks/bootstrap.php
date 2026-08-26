<?php
/**
 * Enough of the Admidio runtime to execute the real Entity lifecycle against SQLite.
 */
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/BufferedStatement.php';
require __DIR__ . '/FakeDatabase.php';

define('DB_TYPE', 'mysql');            // the PostgreSQL boolean conversion must stay out of the way
define('TABLE_PREFIX', 'adm');
define('DATETIME_NOW', date('Y-m-d H:i:s'));
$GLOBALS['gCurrentUserId'] = 0;        // no creator/editor columns are stamped
$GLOBALS['gLogger'] = new \Psr\Log\NullLogger();   // Admidio logs unguarded in a few places

use Admidio\Infrastructure\Entity\Entity;

// the changelog needs settings and tables of its own and is not what these tests are about
Entity::setLoggingEnabled(false);

$GLOBALS['adm_test_failures'] = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    if (!$ok) {
        $GLOBALS['adm_test_failures']++;
    }
    printf("%-6s %s%s\n", $ok ? '  ok' : 'FAIL', $name, $detail !== '' ? '  -> ' . $detail : '');
}

function testSummary(): int
{
    $failures = $GLOBALS['adm_test_failures'];
    echo $failures === 0 ? "\nall checks passed\n" : "\n$failures checks failed\n";
    return $failures === 0 ? 0 : 1;
}

/**
 * A table with the columns that Admidio entities normally have.
 */
function columnDefinition(string $prefix): array
{
    return array(
        $prefix . '_id' => array('type' => 'integer', 'null' => false, 'key' => true, 'serial' => true, 'default' => null),
        $prefix . '_uuid' => array('type' => 'varchar(36)', 'null' => false, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_name' => array('type' => 'varchar(255)', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_secret' => array('type' => 'varchar(255)', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_usr_id_create' => array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_timestamp_create' => array('type' => 'timestamp', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_usr_id_change' => array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        $prefix . '_timestamp_change' => array('type' => 'timestamp', 'null' => true, 'key' => false, 'serial' => false, 'default' => null)
    );
}
