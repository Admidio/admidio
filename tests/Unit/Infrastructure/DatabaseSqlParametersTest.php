<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DatabaseSqlParametersTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('TABLE_PREFIX')) {
            define('TABLE_PREFIX', 'adm_test');
        }
    }

    public function testGeneratedUuidsAreSqlStringLiteralsAndUniquePerOccurrence(): void
    {
        $sql = Database::prepareSqlAdmidioParameters('SELECT %UUID% AS first_uuid, %UUID% AS second_uuid');
        $result = (new PDO('sqlite::memory:'))->query($sql)->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue(Uuid::isValid($result['first_uuid']));
        $this->assertTrue(Uuid::isValid($result['second_uuid']));
        $this->assertNotSame($result['first_uuid'], $result['second_uuid']);
        $this->assertSame('SELECT * FROM ' . TABLE_PREFIX . '_menu',
            Database::prepareSqlAdmidioParameters('SELECT * FROM %PREFIX%_menu'));
    }

    public static function updateSteps(): array
    {
        return array(
            array('4_1', '860'), array('4_3', '180'),
            array('5_0', '370'), array('5_0', '1610'),
            array('5_1', '30'), array('5_1', '600')
        );
    }

    /** @dataProvider updateSteps */
    public function testUpdateScriptsProduceExactlyOneQuotedUuid(string $version, string $stepId): void
    {
        $script = simplexml_load_file(dirname(__DIR__, 3) . '/install/db_scripts/update_' . $version . '.xml');
        $step = $script->xpath('/update/step[@id="' . $stepId . '"]');
        $this->assertCount(1, $step);
        $source = (string) $step[0];
        $this->assertStringContainsString('%UUID%', $source);
        $sql = Database::prepareSqlAdmidioParameters($source);
        $this->assertMatchesRegularExpression("/(?<!')'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'(?!')/", $sql);
        $this->assertStringNotContainsString('%UUID%', $sql);
        $this->assertStringNotContainsString('%PREFIX%', $sql);
    }
}
