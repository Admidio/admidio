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
            'default ordering without sort joins' => [['defaultOrder' => true], ['Camera', 'Camera spare', 'Tripod']],
            'category ordering with a secondary field' => [['orderFields' => [['CATEGORY', 'asc'], ['ITEMNAME', 'desc']]], ['Tripod', 'Camera spare', 'Camera']],
            'numeric ordering with ties and a secondary field' => [['sameNumber' => true, 'orderFields' => [['AUDIT_QUANTITY', 'asc'], ['ITEMNAME', 'desc']]], ['Tripod', 'Camera spare', 'Camera']],
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
            'scientific numeric notation' => [['orderField' => 'AUDIT_QUANTITY', 'scientificNumber' => true], ['Camera spare', 'Camera', 'Tripod']],
            'decimal precision' => [['orderField' => 'AUDIT_DECIMAL'], ['Camera spare', 'Camera', 'Tripod']],
            'invalid numeric value after changing field type' => [['orderField' => 'AUDIT_QUANTITY', 'invalidNumber' => true], ['Camera spare', 'Camera', 'Tripod']],
            'invalid numeric value with descending order' => [['orderField' => 'AUDIT_QUANTITY', 'invalidNumber' => true, 'dir' => 'desc'], ['Camera', 'Camera spare', 'Tripod']],
            'missing numeric value sorts last' => [['orderField' => 'AUDIT_QUANTITY', 'missingNumber' => true], ['Camera spare', 'Tripod', 'Camera']],
            'missing numeric value sorts last descending' => [['orderField' => 'AUDIT_QUANTITY', 'missingNumber' => true, 'dir' => 'desc'], ['Tripod', 'Camera spare', 'Camera']],
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
        $result = $this->requestList($request);
        $this->assertSame($expectedTotal, $result['recordsTotal']);
        $this->assertSame($expectedFiltered ?? count($expectedNames), $result['recordsFiltered']);
        $names = array_map(static fn(array $row): string => html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5), $result['data']);
        $this->assertSame($expectedNames, $names);
    }

    public function testKeeperFilterReusesLoadedFieldDefinition(): void
    {
        $plain = $this->requestList([]);
        $filtered = $this->requestList(['filterKeeper' => true]);
        $this->assertSame($plain['recordsTotal'], $filtered['recordsTotal']);
        $this->assertSame($plain['testQueryCount'], $filtered['testQueryCount']);
    }

    public function testWhitespaceSearchDoesNotRunAnotherCountQuery(): void
    {
        $plain = $this->requestList([]);
        $whitespace = $this->requestList(['search' => '   ']);
        $this->assertSame($plain['recordsFiltered'], $whitespace['recordsFiltered']);
        $this->assertSame($plain['testQueryCount'], $whitespace['testQueryCount']);
    }

    public static function sharedSearches(): array
    {
        return [
            'multiple words in different fields' => ['camera optics', ['Camera', 'Camera spare']],
            'multiple words in one field' => ['camera spare', ['Camera spare']],
            'mixed whitespace' => ["  CAMERA\toptics\n", ['Camera', 'Camera spare']],
            'whitespace only' => ['   ', ['Camera', 'Camera spare', 'Tripod']],
            'literal comma' => ['optics,kit', ['Camera']],
            'comma is not an alternative operator' => ['camera,tripod', []],
            'hyphen in a word' => ['camera-kit', ['Camera']],
            'minus is not an exclusion operator' => ['camera -spare', []],
            'case insensitive umlauts' => ['äöü', ['Camera']],
            'apostrophe' => ["O'Reilly", ['Camera']],
            'SQL pattern characters' => ['50% Model_A', ['Camera']],
            'long field contents' => ['longneedle', ['Tripod']],
            'no matches' => ['missing', []],
        ];
    }

    /** @dataProvider sharedSearches */
    public function testListExportAndPrintUseTheSameSearch(string $search, array $expectedNames): void
    {
        $result = $this->requestList(['search' => $search, 'compareExports' => true]);
        $names = array_map(static fn(array $row): string => html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5), $result['data']);
        sort($names);
        $this->assertSame($expectedNames, $names);
        foreach ($result['testExportNames'] as $mode => $exportNames) {
            $this->assertSame($names, $exportNames, $mode);
        }
    }

    private function requestList(array $request): array
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
            return $result;
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
