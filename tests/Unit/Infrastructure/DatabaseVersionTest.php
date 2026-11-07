<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Database;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class DatabaseVersionTest extends TestCase
{
    public static function databaseVersions(): array
    {
        return array(
            'mysql' => array('mysql', '8.0.40', 'mysql', '8.0.40'),
            'mariadb' => array('mysql', '10.11.6-MariaDB', 'mariadb', '10.11.6'),
            'postgres' => array('pgsql', 'PostgreSQL 16.4 on x86_64, compiled by gcc', 'pgsql', '16.4')
        );
    }

    /** @dataProvider databaseVersions */
    public function testFirstVersionAccessLoadsAndCachesDatabaseInformation(
        string $engine,
        string $serverVersion,
        string $expectedType,
        string $expectedVersion
    ): void {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())->method('fetchColumn')->willReturn($serverVersion);

        $database = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('queryPrepared'))
            ->getMock();
        (new ReflectionProperty(Database::class, 'engine'))->setValue($database, $engine);
        $database->expects($this->once())->method('queryPrepared')
            ->with('SELECT version()')->willReturn($statement);

        $this->assertSame($expectedVersion, $database->getVersion());
        $this->assertSame($expectedVersion, $database->getVersion());
        $this->assertSame($expectedType, (new ReflectionProperty(Database::class, 'type'))->getValue($database));
    }
}
