<?php

namespace Admidio\Tests\Integration\Inventory;

use Admidio\Tests\Support\DatabaseTestCase;
use Symfony\Component\Process\Process;

/** Exercise the real DataTables endpoint against each configured database engine. */
class InventoryListSqlTest extends DatabaseTestCase
{
    public static function listRequests(): array
    {
        return [
            'plain list' => [[], ['Camera', 'Camera spare', 'Tripod']],
            'strict grouping' => [['strict' => true], ['Camera', 'Camera spare', 'Tripod']],
            'search with strict SQL mode' => [['strict' => true, 'search' => 'camera'], ['Camera', 'Camera spare']],
            'UTF-8 connection' => [['charset' => 'utf8'], ['Camera', 'Camera spare', 'Tripod']],
            'case insensitive search' => [['search' => 'CAMERA'], ['Camera', 'Camera spare']],
            'multiple search words' => [['search' => 'camera spare'], ['Camera spare']],
            'words in different fields' => [['search' => 'camera optics'], ['Camera', 'Camera spare']],
            'search after long field contents' => [['search' => 'longneedle'], ['Tripod']],
            'no matches' => [['search' => 'missing'], []],
            'numeric ordering' => [['orderField' => 'AUDIT_QUANTITY'], ['Camera spare', 'Camera', 'Tripod']],
            'numeric ordering with search' => [['search' => 'camera', 'orderField' => 'AUDIT_QUANTITY'], ['Camera spare', 'Camera']],
            'receiver ordering with search' => [['search' => 'camera', 'orderField' => 'LAST_RECEIVER', 'dir' => 'desc'], ['Camera spare', 'Camera']],
            'whitespace search and receiver ordering' => [['search' => '   ', 'orderField' => 'LAST_RECEIVER'], ['Camera', 'Camera spare', 'Tripod']],
            'category and keeper filters with search' => [['filterCategory' => true, 'filterKeeper' => true, 'search' => 'camera'], ['Camera', 'Camera spare']],
            'receiver filter' => [['filterReceiver' => 'Camera spare'], ['Camera spare'], 1],
            'pagination with search' => [['search' => 'camera', 'start' => 1, 'length' => 1], ['Camera spare'], 3, 2],
        ];
    }

    /** @dataProvider listRequests */
    public function testInventoryListQueries(array $request, array $expectedNames, int $expectedTotal = 3, ?int $expectedFiltered = null): void
    {
        $root = tempnam(sys_get_temp_dir(), 'admidio-inventory-list-');
        unlink($root);
        mkdir($root . '/modules/inventory', 0777, true);
        mkdir($root . '/system');
        $endpoint = $root . '/modules/inventory/inventory_data.php';
        $common = $root . '/system/common.php';

        try {
            copy(ADMIDIO_PATH . '/modules/inventory/inventory_data.php', $endpoint);
            symlink(dirname(__DIR__, 2) . '/bootstrap-admidio.php', $common);
            $process = new Process([PHP_BINARY, __DIR__ . '/inventory_list_worker.php', json_encode($request, JSON_THROW_ON_ERROR), $endpoint]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');
            $this->assertSame($expectedTotal, $result['recordsTotal']);
            $this->assertSame($expectedFiltered ?? count($expectedNames), $result['recordsFiltered']);
            $names = array_map(static fn(array $row): string => html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5), $result['data']);
            $this->assertSame($expectedNames, $names);
        } finally {
            unlink($endpoint);
            unlink($common);
            rmdir($root . '/modules/inventory');
            rmdir($root . '/modules');
            rmdir($root . '/system');
            rmdir($root);
        }
    }
}
