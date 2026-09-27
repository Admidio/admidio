<?php

namespace Admidio\Tests\Integration\Documents;

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\Maintenance;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Ramsey\Uuid\Uuid;

class MaintenanceDocumentsPathTest extends DatabaseTestCase
{
    use PermissionContext;

    private function createFolder(int $orgId, string $name, string $path, ?int $parentId = null): int
    {
        $sql = 'INSERT INTO ' . TBL_FOLDERS . '
                    (fol_org_id, fol_fol_id_parent, fol_uuid, fol_type, fol_name, fol_path)
                VALUES (?, ?, ?, ?, ?, ?)';
        $this->getDatabase()->queryPrepared($sql, array(
            $orgId, $parentId, Uuid::uuid4()->toString(), 'DOCUMENTS', $name, $path
        ));

        return $this->getDatabase()->lastInsertId();
    }

    private function folderPath(int $folderId): string
    {
        $sql = 'SELECT fol_path FROM ' . TBL_FOLDERS . ' WHERE fol_id = ?';

        return $this->getDatabase()->queryPrepared($sql, array($folderId))->fetchColumn();
    }

    public function testRepairDocumentsPathsAcrossOrganizations(): void
    {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $orgA = $fixture->createAndSaveOrganization('Documents A', 'docpatha');
        $orgB = $fixture->createAndSaveOrganization('Documents B', 'docpathb');

        $rootA = $this->createFolder($orgA['org_id'], 'root-a', '/adm_my_files/a');
        $childA = $this->createFolder($orgA['org_id'], 'child-a', '/wrong', $rootA);
        $grandchildA = $this->createFolder($orgA['org_id'], 'grandchild-a', '/also-wrong', $childA);

        $rootB = $this->createFolder($orgB['org_id'], 'root-b', '/adm_my_files/b');
        $childB = $this->createFolder($orgB['org_id'], 'child-b', '/wrong', $rootB);
        $grandchildB = $this->createFolder($orgB['org_id'], 'grandchild-b', '/also-wrong', $childB);

        $maintenance = new Maintenance($this->getDatabase());
        $this->withOrganization($orgA['org_id'], function () use ($maintenance, $orgA): void {
            $maintenance->repairDocumentsFilesPath();
            $this->assertSame($orgA['org_id'], $GLOBALS['gCurrentOrgId']);
        });

        $expectedPaths = array(
            $rootA => '/adm_my_files/a',
            $childA => '/adm_my_files/a/root-a',
            $grandchildA => '/adm_my_files/a/root-a/child-a',
            $rootB => '/adm_my_files/b',
            $childB => '/adm_my_files/b/root-b',
            $grandchildB => '/adm_my_files/b/root-b/child-b',
        );

        foreach ($expectedPaths as $folderId => $expectedPath) {
            $this->assertSame($expectedPath, $this->folderPath($folderId));
        }

        $maintenance->repairDocumentsFilesPath();

        foreach ($expectedPaths as $folderId => $expectedPath) {
            $this->assertSame($expectedPath, $this->folderPath($folderId));
        }

        $this->withOrganization($orgA['org_id'], function () use ($rootB): void {
            try {
                new Folder($this->getDatabase(), $rootB);
                $this->fail('A folder of another organization must remain inaccessible.');
            } catch (Exception $exception) {
                $this->assertStringContainsString('belongs to another organization', $exception->getMessage());
            }
        });
    }
}
