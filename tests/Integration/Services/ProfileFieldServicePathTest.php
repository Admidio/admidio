<?php

namespace Admidio\Tests\Integration\Services;

use Admidio\ProfileFields\Service\ProfileFieldService;
use Admidio\Tests\Support\DatabaseTestCase;

/**
 * Regression coverage for ProfileFieldService::saveData().
 */
class ProfileFieldServicePathTest extends DatabaseTestCase
{
    /**
     * @testdox Profile fields created and updated through ProfileFieldService are really persisted
     */
    public function testProfileFieldLifecycleUsesProductionService(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $categoryId = $db->queryPrepared(
            'SELECT cat_id
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND cat_org_id = ?
           ORDER BY cat_id
              LIMIT 1',
            array('USF', $gCurrentOrgId)
        )->fetchColumn();

        $this->assertGreaterThan(0, (int)$categoryId);

        $suffix = bin2hex(random_bytes(5));
        $name = 'Regression profile field ' . $suffix;

        (new ProfileFieldService($db))->saveData(array(
            'usf_cat_id' => (int)$categoryId,
            'usf_type' => 'TEXT',
            'usf_name' => $name,
            'usf_description' => 'Created through ProfileFieldService',
            'usf_required_input' => 0,
            'usf_registration' => 0,
            'usf_hidden' => 0,
            'usf_disabled' => 0
        ));

        $field = $db->queryPrepared(
            'SELECT usf_uuid, usf_name_intern, usf_description
               FROM ' . TBL_USER_FIELDS . '
              WHERE usf_cat_id = ?
                AND usf_name = ?',
            array((int)$categoryId, $name)
        )->fetch();

        $this->assertIsArray($field);
        $this->assertNotSame('', (string)$field['usf_uuid']);
        $this->assertNotSame('', (string)$field['usf_name_intern']);
        $this->assertSame('Created through ProfileFieldService', (string)$field['usf_description']);

        $updatedName = $name . ' updated';
        (new ProfileFieldService($db, (string)$field['usf_uuid']))->saveData(array(
            'usf_cat_id' => (int)$categoryId,
            'usf_name' => $updatedName,
            'usf_description' => 'Updated through ProfileFieldService'
        ));

        $updated = $db->queryPrepared(
            'SELECT usf_name, usf_name_intern, usf_description
               FROM ' . TBL_USER_FIELDS . '
              WHERE usf_uuid = ?',
            array($field['usf_uuid'])
        )->fetch();

        $this->assertIsArray($updated);
        $this->assertSame($updatedName, (string)$updated['usf_name']);
        $this->assertSame((string)$field['usf_name_intern'], (string)$updated['usf_name_intern']);
        $this->assertSame('Updated through ProfileFieldService', (string)$updated['usf_description']);

        (new ProfileFieldService($db, (string)$field['usf_uuid']))->delete();

        $this->assertSame(
            0,
            (int)$db->queryPrepared(
                'SELECT COUNT(*)
                   FROM ' . TBL_USER_FIELDS . '
                  WHERE usf_uuid = ?',
                array($field['usf_uuid'])
            )->fetchColumn()
        );
    }

    /**
     * @testdox ProfileFieldService rejects duplicate field names in one category
     */
    public function testDuplicateProfileFieldNameIsRejectedByService(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $categoryId = (int)$db->queryPrepared(
            'SELECT cat_id
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
                AND cat_org_id = ?
           ORDER BY cat_id
              LIMIT 1',
            array('USF', $gCurrentOrgId)
        )->fetchColumn();

        $name = 'Duplicate service field ' . bin2hex(random_bytes(5));
        $values = array(
            'usf_cat_id' => $categoryId,
            'usf_type' => 'TEXT',
            'usf_name' => $name
        );

        (new ProfileFieldService($db))->saveData($values);

        $this->expectException(\Admidio\Infrastructure\Exception::class);
        (new ProfileFieldService($db))->saveData($values);
    }
}
