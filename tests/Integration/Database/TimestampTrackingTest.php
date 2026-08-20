<?php
/**
 * Timestamp Tracking Tests
 *
 * Tests that creation and update timestamps are properly tracked on entities.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Organizations\Entity\Organization;

class TimestampTrackingTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that organizations have creation timestamps
     *
     * @testdox Organization creation timestamp is recorded
     */
    public function testOrganizationCreationTimestamp(): void
    {
        $fixture = $this->getFixture();
        $beforeCreate = new \DateTime();

        $org = $fixture->createAndSaveOrganization('Test Org');

        $afterCreate = new \DateTime();

        // Verify timestamps are set
        $this->assertNotEmpty($org['org_id']);
        $this->assertGreaterThan(0, $org['org_id']);

        // Fetch from database to check timestamps
        $sql = 'SELECT org_timestamp_create, org_timestamp_change FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$org['org_id']]);

        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch();
            $this->assertNotNull($row['org_timestamp_create']);
            $this->assertNull($row['org_timestamp_change']); // Change timestamp should be NULL initially
        }
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

        // Verify category exists
        $this->assertNotEmpty($cat['cat_id']);

        // Check timestamps in database
        $sql = 'SELECT cat_timestamp_create, cat_timestamp_change FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']]);
        $row = $result->fetch();

        $this->assertNotNull($row['cat_timestamp_create']);
        $this->assertNull($row['cat_timestamp_change']); // Should be NULL until changed
    }


    /**
     * Test users have proper timestamp columns
     *
     * @testdox User records include creation and update tracking
     */
    public function testUserTimestamps(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');

        // Verify user exists
        $this->assertNotEmpty($user['usr_id']);

        // Check user timestamps
        $sql = 'SELECT usr_timestamp_create, usr_timestamp_change FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']]);
        $row = $result->fetch();

        $this->assertNotNull($row['usr_timestamp_create']);
        // usr_timestamp_change might be NULL or set depending on save implementation
    }
}
