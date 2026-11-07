<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\InstallationIdentifier;
use Admidio\Infrastructure\InstallationStatistics;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InstallationIdentifierTest extends TestCase
{
    private PDO $pdo;
    private Database $db;

    protected function setUp(): void
    {
        foreach (array('TBL_PREFERENCES' => 'adm_preferences', 'TBL_COMPONENTS' => 'adm_components') as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE ' . TBL_PREFERENCES . ' (prf_org_id INTEGER, prf_name TEXT, prf_value TEXT)');
        $this->pdo->exec('CREATE TABLE ' . TBL_COMPONENTS . ' (com_id INTEGER, com_name_intern TEXT)');
        $this->pdo->exec("INSERT INTO " . TBL_COMPONENTS . " VALUES (1, 'CORE')");
        $this->db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('queryPrepared', 'startTransaction', 'endTransaction', 'rollback'))->getMock();
        $this->db->method('queryPrepared')->willReturnCallback(function (string $sql, array $params) {
            // SQLite exercises persistence and migration; row locking needs the server integration tests.
            $statement = $this->pdo->prepare(str_replace(' FOR UPDATE', '', $sql));
            $statement->execute($params);
            return $statement;
        });
        $this->db->method('startTransaction')->willReturnCallback(fn(): bool => $this->pdo->beginTransaction());
        $this->db->method('endTransaction')->willReturnCallback(fn(): bool => $this->pdo->commit());
        $this->db->method('rollback')->willReturnCallback(fn(): bool => $this->pdo->rollBack());
    }

    public function testIdentityIsIndependentOfOrganizationPreferences(): void
    {
        $previewId = Uuid::uuid4()->toString();
        $this->assertSame($previewId, InstallationIdentifier::ensure($this->db, $previewId));
        $this->pdo->exec('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id IS NOT NULL');
        $this->assertSame($previewId, InstallationIdentifier::get($this->db));
        $this->assertSame($previewId, InstallationIdentifier::ensure($this->db, Uuid::uuid4()->toString()));
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM ' . TBL_PREFERENCES)->fetchColumn());
    }

    public function testLegacyIdentityIsPreservedAndMovedOutOfOrganization(): void
    {
        $legacyId = Uuid::uuid4()->toString();
        $this->db->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . ' VALUES (1, ?, ?)', array('system_tenant_id', $legacyId));
        $this->assertSame($legacyId, InstallationIdentifier::get($this->db));
        $this->assertSame($legacyId, InstallationIdentifier::ensure($this->db, Uuid::uuid4()->toString()));
        $this->assertSame($legacyId, InstallationIdentifier::get($this->db));
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id IS NOT NULL')->fetchColumn());
    }
    public function testLastSentSqlInitializesGlobalValueAndPreservesSuccessfulSendOnRetry(): void
    {
        $update = simplexml_load_file(dirname(__DIR__, 3) . '/install/db_scripts/update_6_0.xml');
        $steps = $update->xpath('step[contains(., "system_statistics_last_sent")]');
        $this->assertCount(1, $steps);
        $sql = str_replace('%PREFIX%_preferences', TBL_PREFERENCES, (string)$steps[0]);
        $this->pdo->exec($sql);
        $this->assertSame('', InstallationStatistics::getLastSent($this->db));
        $method = new \ReflectionMethod(InstallationStatistics::class, 'recordSuccessfulSend');
        $method->invoke(new InstallationStatistics(), $this->db);
        $lastSent = InstallationStatistics::getLastSent($this->db);
        $this->assertFalse(InstallationStatistics::isDue($lastSent));
        $this->pdo->exec($sql);
        $this->assertSame($lastSent, InstallationStatistics::getLastSent($this->db));
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM ' . TBL_PREFERENCES)->fetchColumn());
    }
}
