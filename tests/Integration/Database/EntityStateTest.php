<?php
/**
 * Entity State Tests
 *
 * Tests the state that Entity keeps about the record it represents: whether it is still new,
 * whether the last save has created it, and which columns an insert has to contain.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Announcements\Entity\Announcement;
use Admidio\Categories\Entity\Category;
use Admidio\Organizations\Entity\Organization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class EntityStateTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator of the installed organization.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * An unsaved category of the installed organization.
     */
    private function newCategory(string $name): Category
    {
        $category = new Category($this->getDatabase());
        $category->setValue('cat_name', $name);
        $category->setValue('cat_type', 'ANN');
        $category->setValue('cat_org_id', self::ORG_ID);

        return $category;
    }

    /**
     * Test that a stored record no longer claims to be new
     *
     * @testdox A record that was saved is no longer a new record
     */
    public function testASavedRecordIsNoLongerANewRecord(): void
    {
        $category = $this->newCategory('State Category');

        $this->assertTrue($category->isNewRecord());
        $this->assertFalse($category->wasInserted());

        $this->assertTrue($category->save());

        $this->assertFalse($category->isNewRecord());
        $this->assertTrue($category->wasInserted());
        $this->assertGreaterThan(0, (int) $category->getValue('cat_id'));
    }

    /**
     * Test that the created answer survives a following change
     *
     * @testdox wasInserted stays true when the same object is saved again
     */
    public function testWasInsertedStaysTrueOverAFollowingSave(): void
    {
        $category = $this->newCategory('Second Save Category');
        $category->save();
        $catId = (int) $category->getValue('cat_id');

        $category->setValue('cat_name', 'Second Save Category renamed');
        $category->save();

        // the record was created by this object, and the second save updated the same row
        $this->assertTrue($category->wasInserted());
        $this->assertFalse($category->isNewRecord());
        $this->assertEquals($catId, (int) $category->getValue('cat_id'));

        $sql = 'SELECT COUNT(*) FROM ' . TBL_CATEGORIES . ' WHERE cat_name = ?';
        $this->assertEquals(
            1,
            (int) $this->getDatabase()->queryPrepared($sql, ['Second Save Category renamed'])->fetchColumn()
        );
    }

    /**
     * Test that a record read from the database was not created here
     *
     * @testdox A record that was read from the database reports that it was not inserted
     */
    public function testAReadRecordReportsThatItWasNotInserted(): void
    {
        $category = $this->newCategory('Read Back Category');
        $category->save();

        $reread = new Category($this->getDatabase(), (int) $category->getValue('cat_id'));

        $this->assertFalse($reread->isNewRecord());
        $this->assertFalse($reread->wasInserted());
    }

    /**
     * Test that a saved announcement knows it was created and sees its category
     *
     * @testdox A saved announcement knows it was created and has its joined category
     */
    public function testASavedAnnouncementKnowsItWasCreated(): void
    {
        $fixture = $this->getFixture();
        $category = $fixture->createAndSaveCategory('Announcement State', 'ANN', self::ORG_ID);
        $administrator = $this->administrator();

        $announcement = $this->withCurrentUser($administrator, self::ORG_ID, true, function () use ($category) {
            $announcement = new Announcement($this->getDatabase());
            $announcement->setValue('ann_cat_id', $category['cat_id']);
            $announcement->setValue('ann_headline', 'State headline');
            $announcement->setValue('ann_description', '<p>State body</p>');
            $announcement->save();

            return $announcement;
        });

        // sendNotification() picks the created or the changed text by this answer, and save() reads
        // the record back for the joined category columns, which must not clear it
        $this->assertTrue($announcement->wasInserted());
        $this->assertFalse($announcement->isNewRecord());

        // the joined category is filled, so the rights can be judged without reading it again
        $this->assertEquals($category['cat_id'], (int) $announcement->getValue('cat_id'));
        $this->assertEquals(self::ORG_ID, (int) $announcement->getValue('cat_org_id'));

        // a later change of the same object is still a creation as far as this request is concerned
        $this->withCurrentUser($administrator, self::ORG_ID, true, function () use ($announcement) {
            $announcement->setValue('ann_headline', 'State headline changed');
            $announcement->save();
        });
        $this->assertTrue($announcement->wasInserted());
        $this->assertFalse($announcement->isNewRecord());
    }

    /**
     * Test that an insert contains the columns the database requires
     *
     * @testdox An insert contains the NOT NULL columns that the caller did not set
     */
    public function testAnInsertContainsTheRequiredColumnsTheCallerDidNotSet(): void
    {
        // org_homepage and org_email_administrator are NOT NULL without a default. An insert that
        // leaves them out is accepted by MySQL only because the connection runs with SQL_MODE
        // 'ANSI' and therefore without STRICT_TRANS_TABLES, while PostgreSQL rejects the row.
        $organization = new Organization($this->getDatabase());
        $organization->setValue('org_shortname', 'reqcol');
        $organization->setValue('org_longname', 'Required columns organization');

        $this->assertTrue($organization->save());
        $orgId = (int) $organization->getValue('org_id');
        $this->assertGreaterThan(0, $orgId);

        $sql = 'SELECT org_homepage, org_email_administrator FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$orgId])->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('', $row['org_homepage']);
        $this->assertSame('', $row['org_email_administrator']);
    }

    /**
     * Test that a value the caller sets is not overwritten by the empty default
     *
     * @testdox A required column that the caller sets keeps its value
     */
    public function testARequiredColumnThatTheCallerSetsKeepsItsValue(): void
    {
        $organization = new Organization($this->getDatabase());
        $organization->setValue('org_shortname', 'reqcol2');
        $organization->setValue('org_longname', 'Required columns organization 2');
        $organization->setValue('org_homepage', 'https://example.local');
        $organization->setValue('org_email_administrator', 'reqcol2@example.local');
        $organization->save();

        $sql = 'SELECT org_homepage, org_email_administrator FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [(int) $organization->getValue('org_id')])->fetch();
        $this->assertEquals('https://example.local', $row['org_homepage']);
        $this->assertEquals('reqcol2@example.local', $row['org_email_administrator']);
    }
}
