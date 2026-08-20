<?php
/**
 * Timestamp Tracking Tests
 *
 * Tests that creation and update timestamps are properly tracked on entities.
 * Note that adm_organizations has no timestamp columns, so organizations cannot be covered here.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Categories\Entity\Category;

class TimestampTrackingTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that categories have creation timestamps
     *
     * @testdox Category creation timestamp is recorded
     */
    public function testCategoryCreationTimestamp(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        $cat = $fixture->createAndSaveCategory('Test Category', 'EVT', $org['org_id']);

        // Check timestamps in database
        $sql = 'SELECT cat_timestamp_create, cat_timestamp_change FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']]);
        $this->assertEquals(1, $result->rowCount());

        $row = $result->fetch();

        // Entity::save() only fills the fingerprint columns for a logged-in user, so for this
        // record the value comes from the DEFAULT CURRENT_TIMESTAMP of the column. It is written
        // by the database server, whose clock is independent of this process, so the value can
        // only be checked for being a valid timestamp and not against a local time window.
        $this->assertNotNull($row['cat_timestamp_create']);
        $this->assertNotFalse(strtotime($row['cat_timestamp_create']));

        $this->assertNull($row['cat_timestamp_change']); // Should be NULL until changed
    }

    /**
     * Test that changing a record fills the change fingerprint.
     * Entity::save() writes cat_timestamp_change and cat_usr_id_change only when a user is logged
     * in, so the test has to provide one.
     *
     * @testdox Updating a category records who changed it and when
     */
    public function testCategoryChangeTimestamp(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Before Rename', 'EVT', $org['org_id']);
        $editor = $fixture->createAndSaveUser('editor', 'editor@example.local');

        $sql = 'SELECT cat_timestamp_change, cat_usr_id_change FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $this->assertNull(
            $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']])->fetch()['cat_timestamp_change']
        );

        $previousUserId = $GLOBALS['gCurrentUserId'] ?? 0;
        $GLOBALS['gCurrentUserId'] = $editor['usr_id'];

        try {
            // change the record through the entity
            $entity = new Category($this->getDatabase());
            $entity->readDataById($cat['cat_id']);
            $entity->setValue('cat_name', 'After Rename');
            $entity->save();
        } finally {
            $GLOBALS['gCurrentUserId'] = $previousUserId;
        }

        $row = $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']])->fetch();
        $this->assertNotNull($row['cat_timestamp_change']);
        $this->assertEquals($editor['usr_id'], (int) $row['cat_usr_id_change']);

        // and the new value is the one that is stored
        $this->assertEquals('After Rename', $fixture->getCategoryById($cat['cat_id'])['cat_name']);
    }

    /**
     * Test users have a creation timestamp
     *
     * @testdox User records carry a creation timestamp
     */
    public function testUserTimestamps(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');

        // Check user timestamps
        $sql = 'SELECT usr_timestamp_create FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']]);
        $this->assertEquals(1, $result->rowCount());

        $row = $result->fetch();
        $this->assertNotNull($row['usr_timestamp_create']);
        $this->assertNotFalse(strtotime($row['usr_timestamp_create']));
    }
}
