<?php

namespace Admidio\Tests\Integration\Services;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Categories\Service\CategoryService;
use Admidio\Infrastructure\Service\RegistrationService;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Menu\Service\MenuService;
use Admidio\Roles\Service\RolesService;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\UI\Presenter\GroupsRolesPresenter;

/**
 * Regression coverage for database-backed Service read paths that are used by web and CLI callers.
 */
class ReadModelServicePathTest extends AdministratorTestCase
{
    /**
     * @testdox CategoryService returns real visible categories from the current organization
     */
    public function testCategoryServiceReadsProductionCategories(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $service = new CategoryService($db, 'ANN');
        $categories = $service->getVisibleCategories();

        $this->assertNotEmpty($categories);

        foreach ($categories as $category) {
            $this->assertSame(
                (int)$gCurrentOrgId,
                (int)$db->queryPrepared(
                    'SELECT cat_org_id
                       FROM ' . TBL_CATEGORIES . '
                      WHERE cat_uuid = ?',
                    array($category['cat_uuid'])
                )->fetchColumn()
            );
        }
    }

    /**
     * @testdox AnnouncementsService sees an Announcement entity persisted in the real database
     */
    public function testAnnouncementsServiceReadsPersistedAnnouncement(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $category = $db->queryPrepared(
            'SELECT cat_id, cat_uuid
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND cat_org_id = ?
           ORDER BY cat_id
              LIMIT 1',
            array('ANN', $gCurrentOrgId)
        )->fetch();

        $this->assertIsArray($category);

        $headline = 'Service announcement ' . bin2hex(random_bytes(5));
        $announcement = new Announcement($db);
        $announcement->setValue('ann_cat_id', (int)$category['cat_id']);
        $announcement->setValue('ann_headline', $headline);
        $announcement->setValue('ann_description', 'Persisted through the production Announcement entity');
        $this->assertTrue($announcement->save());

        $uuid = (string)$announcement->getValue('ann_uuid');
        $service = new AnnouncementsService($db, (string)$category['cat_uuid'], $uuid);

        $this->assertSame(1, $service->count());
        $rows = $service->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame($uuid, (string)$rows[0]['ann_uuid']);
        $this->assertSame($headline, (string)$rows[0]['ann_headline']);
    }

    /**
     * @testdox MenuService builds its hierarchy from persisted MenuEntry entities
     */
    public function testMenuServiceReadsPersistedHierarchy(): void
    {
        $db = $this->getDatabase();
        $suffix = bin2hex(random_bytes(5));

        $parent = new MenuEntry($db);
        $parent->setValue('men_name', 'Regression menu ' . $suffix);
        $parent->setValue('men_node', 1);
        $parent->setValue('men_url', '');
        $this->assertTrue($parent->save());

        $child = new MenuEntry($db);
        $child->setValue('men_name', 'Regression child ' . $suffix);
        $child->setValue('men_men_id_parent', (int)$parent->getValue('men_id'));
        $child->setValue('men_node', 0);
        $child->setValue('men_url', '/regression/' . $suffix);
        $this->assertTrue($child->save());

        $data = (new MenuService($db))->getData();
        $matchingParent = null;

        foreach ($data as $row) {
            if ((int)$row['men_id'] === (int)$parent->getValue('men_id')) {
                $matchingParent = $row;
                break;
            }
        }

        $this->assertIsArray($matchingParent);
        $this->assertCount(1, $matchingParent['entries']);
        $this->assertSame(
            (string)$child->getValue('men_uuid'),
            (string)$matchingParent['entries'][0]['men_uuid']
        );
    }

    /**
     * @testdox RolesService returns real active roles and RegistrationService uses the installed registration table
     */
    public function testCoreServiceReadModelsUseInstalledDatabase(): void
    {
        $db = $this->getDatabase();

        $roles = (new RolesService($db))->findAll(GroupsRolesPresenter::ROLE_TYPE_ACTIVE);
        $this->assertNotEmpty($roles);
        $this->assertArrayHasKey('rol_uuid', $roles[0]);
        $this->assertArrayHasKey('num_members', $roles[0]);

        $registrations = (new RegistrationService($db))->findAll();
        $this->assertIsArray($registrations);

        $expectedCount = (int)$db->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_REGISTRATIONS . '
         INNER JOIN ' . TBL_USERS . '
                 ON usr_id = reg_usr_id
              WHERE usr_valid = false
                AND reg_org_id = ?',
            array($GLOBALS['gCurrentOrgId'])
        )->fetchColumn();

        $this->assertCount($expectedCount, $registrations);
    }
}
