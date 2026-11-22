<?php

namespace Admidio\Tests\Integration\Changelog;

use Admidio\Changelog\Entity\LogChanges;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ChangelogVisibilityTest extends AdministratorTestCase
{
    use PermissionContext;

    private const PRIVATE_FIELD = 'CHANGELOG_PRIVATE_ID';
    private const PUBLIC_FIELD = 'CHANGELOG_PUBLIC_NOTE';
    private const SECRET = 'TH-SECRET-9912';

    private ProfileFields $previousProfileFields;
    private array $team;
    private array $hiddenRole;
    private array $logIds = array();
    private User $leader;
    private User $member;
    private ProfileFields $profileFields;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousProfileFields = $GLOBALS['gProfileFields'];
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $orgId = (int)$GLOBALS['gCurrentOrgId'];
        $role = $fixture->createAndSaveRoleWithRights('Changelog edit team', $orgId, array(
            'rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_EDIT,
            'rol_profile' => 1,
            'rol_view_memberships' => Role::VIEW_LEADERS
        ));
        $this->team = $role;
        $hrRole = $fixture->createAndSaveRoleWithRights('Private HR access', $orgId, array(
            'rol_view_memberships' => Role::VIEW_NOBODY
        ));
        $this->hiddenRole = $hrRole;
        $publicCategory = $fixture->createAndSaveCategory('Changelog public data', 'USF');
        $privateCategory = $fixture->createAndSaveCategory('Private HR', 'USF');
        (new RolesRights($db, 'category_view', $privateCategory['cat_id']))->addRoles(array($hrRole['rol_id']));

        foreach (array(
            array('Changelog private ID', $privateCategory['cat_id']),
            array('Changelog public note', $publicCategory['cat_id'])
        ) as [$name, $categoryId]) {
            $field = new ProfileField($db);
            $field->setValue('usf_name', $name);
            $field->setValue('usf_type', 'TEXT');
            $field->setValue('usf_cat_id', $categoryId);
            $field->save();
        }

        $leader = $fixture->createAndSaveUser('changelog-leader', 'changelog-leader@example.local');
        $member = $fixture->createAndSaveUser('changelog-member', 'changelog-member@example.local');
        $fixture->assignUserToRolePeriod($leader['usr_id'], $role['rol_id'], DATE_NOW, DATE_MAX, true);
        $fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

        $fixture->assignUserToRole($member['usr_id'], $hrRole['rol_id']);
        $this->profileFields = new ProfileFields($db, $orgId);
        $GLOBALS['gProfileFields'] = $this->profileFields;
        $this->leader = new User($db, $this->profileFields, $leader['usr_id']);
        $this->member = new User($db, $this->profileFields, $member['usr_id']);
        foreach (array($this->leader, $this->member) as $user) {
            $user->setValue(self::PRIVATE_FIELD, self::SECRET);
            $user->setValue(self::PUBLIC_FIELD, 'Original note');
            $user->save();
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS['gProfileFields'] = $this->previousProfileFields;
        parent::tearDown();
    }

    private function addEntry(string $table, string $field, string $related = '', string $value = self::SECRET): int
    {
        $entry = new LogChanges($this->getDatabase());
        $entry->setLogModification($table, 1, $this->member->getValue('usr_uuid'), 'Member',
            $field, 'Field', 'Old confidential value', $value);
        $entry->setLogRelated($related, 'Role');
        $this->assertTrue($entry->save());
        $id = (int) $entry->getValue('log_id');
        $this->logIds[] = $id;
        return $id;
    }

    public function testLeaderHistoryFiltersFieldsAndRolesBeforeSearchAndGrouping(): void
    {
        $publicId = $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PUBLIC_FIELD, 'usf_id'));
        $privateId = $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PRIVATE_FIELD, 'usf_id'));
        $teamId = $this->addEntry('members', 'mem_begin', $this->team['rol_uuid']);
        $numericTeamId = $this->addEntry('members', 'mem_end', (string) $this->team['rol_id']);
        $this->addEntry('members', 'mem_begin', $this->hiddenRole['rol_uuid']);
        $this->addEntry('members', 'mem_end', (string) $this->hiddenRole['rol_id']);
        $this->addEntry('members', 'mem_begin', 'deleted-role');
        $this->addEntry('user_data', '999999999');

        $this->withCurrentUser($this->leader, (int) $GLOBALS['gCurrentOrgId'], true, function () use ($publicId, $privateId, $teamId, $numericTeamId) {
            $this->assertTrue($this->leader->hasRightEditProfile($this->member));
            $this->assertFalse($this->leader->allowedViewProfileField($this->member, self::PRIVATE_FIELD));
            $this->assertTrue($this->leader->hasRightViewRole($this->team['rol_id']));
            $this->assertFalse($this->leader->hasRightViewRole($this->hiddenRole['rol_id']));
            $this->assertContains('user_data', ChangelogService::getReadableTables($this->leader, array('user_data'), $this->member));
            [$condition, $params] = ChangelogService::getVisibilityRestriction($this->leader, $this->member);
            $where = ' WHERE log_id IN (' . \Admidio\Infrastructure\Database::getQmForValues($this->logIds) . ')' . $condition;
            $params = array_merge($this->logIds, $params);
            $db = $this->getDatabase();
            $ids = $db->queryPrepared('SELECT log_id FROM ' . TBL_LOG_CHANGES . $where . ' ORDER BY log_id', $params)->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertSame(array($publicId, $teamId, $numericTeamId), array_map('intval', $ids));
            $groups = $db->queryPrepared('SELECT log_table, COUNT(*) AS entries FROM ' . TBL_LOG_CHANGES . $where . ' GROUP BY log_table', $params)->fetchAll();
            $this->assertSame(3, array_sum(array_column($groups, 'entries')));
            $searchCount = $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_LOG_CHANGES . $where . ' AND log_id = ?', array_merge($params, array($privateId)))->fetchColumn();
            $this->assertSame(0, (int) $searchCount);
        });
    }

    public function testAdministratorRetainsAccessToExistingFieldsAndRoles(): void
    {
        $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PRIVATE_FIELD, 'usf_id'));
        $this->addEntry('members', 'mem_begin', $this->hiddenRole['rol_uuid']);
        foreach (array(null, $this->member) as $subject) {
            [$condition, $params] = ChangelogService::getVisibilityRestriction($GLOBALS['gCurrentUser'], $subject);
            $count = $this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_LOG_CHANGES . ' WHERE log_id IN ('
                . \Admidio\Infrastructure\Database::getQmForValues($this->logIds) . ')' . $condition,
                array_merge($this->logIds, $params)
            )->fetchColumn();
            $this->assertSame(2, (int) $count);
        }
    }

    public function testSearchCannotRevealRestrictedValuesThroughCountsOrPaging(): void
    {
        $public = $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PUBLIC_FIELD, 'usf_id'), '', 'Public search note');
        $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PRIVATE_FIELD, 'usf_id'), '', 'DE89370400440532013000');
        $this->addEntry('members', 'mem_begin', $this->hiddenRole['rol_uuid'], 'Hidden role secret');
        $this->withCurrentUser($this->leader, (int) $GLOBALS['gCurrentOrgId'], true, function () use ($public) {
            [$visibility, $params] = ChangelogService::getVisibilityRestriction($this->leader, $this->member);
            $restriction = ChangelogService::getUserTableRestriction($this->leader, $this->member);
            $tables = ChangelogService::getReadableTables($this->leader, array('user_data', 'members'), $this->member);
            $db = $this->getDatabase();
            $base = 'SELECT log_id, log_change_uuid, log_value_old, log_value_new FROM ' . TBL_LOG_CHANGES
                . ' WHERE log_id IN (' . \Admidio\Infrastructure\Database::getQmForValues($this->logIds) . ')'
                . ' AND log_table IN (' . \Admidio\Infrastructure\Database::getQmForValues($tables) . ')'
                . ' AND log_record_uuid = ?' . $visibility;
            $params = array_merge($this->logIds, $tables, array($restriction), $params);
            foreach (array('DE89370400', 'DE8937040X', 'Hidden role secret', 'Public search note') as $term) {
                [$search, $searchParams] = ChangelogService::getSearchCondition($term, array('log_value_old', 'log_value_new'));
                $sql = 'SELECT * FROM (' . $base . ') AS entries' . $search;
                $bound = array_merge($params, $searchParams);
                $expected = $term === 'Public search note' ? 1 : 0;
                $this->assertSame($expected, (int) $db->queryPrepared('SELECT COUNT(*) FROM (' . $sql . ') AS matches', $bound)->fetchColumn());
                $groupCount = $db->queryPrepared('SELECT COUNT(*) FROM (SELECT log_change_uuid FROM (' . $sql . ') AS visible_entries GROUP BY log_change_uuid) AS changes', $bound)->fetchColumn();
                $this->assertSame($expected, (int) $groupCount);
                $ids = $db->queryPrepared($sql . ' ORDER BY log_id LIMIT 1', $bound)->fetchAll(\PDO::FETCH_COLUMN);
                $this->assertSame($expected ? array($public) : array(), array_map('intval', $ids));
                $this->assertSame(array(), $db->queryPrepared($sql . ' ORDER BY log_id LIMIT 1 OFFSET 5', $bound)->fetchAll());
            }
        });
    }

    public function testSearchTreatsWildcardsAndEscapeCharactersLiterally(): void
    {
        $id = $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PUBLIC_FIELD, 'usf_id'), '', 'Rate 50% code A_B warning!');
        foreach (array('50%', 'A_B', 'warning!', 'rate', '50_', 'A%B', 'missing') as $term) {
            [$search, $params] = ChangelogService::getSearchCondition($term, array('log_value_new'));
            $sql = 'SELECT * FROM (SELECT log_value_new FROM ' . TBL_LOG_CHANGES . ' WHERE log_id = ?) AS entries' . $search;
            $count = $this->getDatabase()->queryPrepared('SELECT COUNT(*) FROM (' . $sql . ') AS matches', array_merge(array($id), $params))->fetchColumn();
            $this->assertSame(in_array($term, array('50_', 'A%B', 'missing'), true) ? 0 : 1, (int) $count, $term);
        }
    }

    public function testMemberWithoutTableFilterCannotProbeAnotherUsersHistory(): void
    {
        $this->addEntry('user_data', (string) $this->profileFields->getProperty(self::PRIVATE_FIELD, 'usf_id'), '', 'DE89370400440532013000');
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $record = $fixture->createAndSaveUser('changelog-reader', 'changelog-reader@example.local');
        $reader = new User($this->getDatabase(), $this->profileFields, $record['usr_id']);
        $this->withCurrentUser($reader, (int) $GLOBALS['gCurrentOrgId'], true, function () use ($reader) {
            $this->assertFalse($reader->isAdministratorUsers());
            $this->assertFalse($reader->hasRightEditProfile($this->member));
            $tables = ChangelogService::getReadableTables($reader);
            $uuid = ChangelogService::getUserTableRestriction($reader);
            $this->assertSame($reader->getValue('usr_uuid'), $uuid);
            [$visibility, $visibilityParams] = ChangelogService::getVisibilityRestriction($reader);
            foreach (array('DE89370400', 'DE8937040X') as $term) {
                if (count($tables) === 0) {
                    // The endpoint returns SYS_NO_RIGHTS before running any search.
                    $this->assertSame(array(), $tables);
                    continue;
                }
                $base = 'SELECT log_value_new FROM ' . TBL_LOG_CHANGES
                    . ' WHERE log_table IN (' . \Admidio\Infrastructure\Database::getQmForValues($tables) . ')'
                    . ' AND log_record_uuid = ?' . $visibility;
                [$search, $params] = ChangelogService::getSearchCondition($term, array('log_value_new'));
                $count = $this->getDatabase()->queryPrepared(
                    'SELECT COUNT(*) FROM (' . $base . ') AS entries' . $search,
                    array_merge($tables, array($uuid), $visibilityParams, $params)
                )->fetchColumn();
                $this->assertSame(0, (int) $count);
            }
        });
    }

}
