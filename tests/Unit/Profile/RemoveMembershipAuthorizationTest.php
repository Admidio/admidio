<?php

namespace Admidio\Tests\Unit\Profile;

use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RemoveMembershipAuthorizationTest extends TestCase
{
    public static function membershipCases(): array
    {
        return array(
            'former membership' => array(true, true, 10, 1, '2000-01-01', '2000-12-31', true),
            'foreign organization' => array(true, true, 10, 2, '2000-01-01', '2000-12-31', false),
            'different user' => array(true, true, 20, 1, '2000-01-01', '2000-12-31', false),
            'unknown membership' => array(true, false, 0, 1, '2000-01-01', '2000-12-31', false),
            'non administrator' => array(false, true, 10, 1, '2000-01-01', '2000-12-31', false),
            'active membership' => array(true, true, 10, 1, '2000-01-01', '9999-12-31', false),
            'ends today' => array(true, true, 10, 1, '2000-01-01', 'today', false),
            'starts today' => array(true, true, 10, 1, 'today', '9999-12-31', false),
            'future membership' => array(true, true, 10, 1, '9999-01-01', '9999-12-31', true),
            'global role' => array(true, true, 10, null, '2000-01-01', '2000-12-31', true),
            'foreign future membership' => array(true, true, 10, 2, '9999-01-01', '9999-12-31', false)
        );
    }

    /** @dataProvider membershipCases */
    public function testDeletionAuthorization(bool $administrator, bool $found, int $memberUserId, ?int $orgId, string $begin, string $end, bool $allowed): void
    {
        require_once dirname(__DIR__, 2) . '/constants.php';

        $member = $this->createMock(Membership::class);
        $member->method('readDataByUuid')->willReturn($found);
        $member->method('getValue')->willReturnMap(array(
            array('mem_usr_id', '', $memberUserId),
            array('mem_rol_id', '', 5),
            array('mem_begin', 'Y-m-d', $begin === 'today' ? DATE_NOW : $begin),
            array('mem_end', 'Y-m-d', $end === 'today' ? DATE_NOW : $end)
        ));
        $member->expects($allowed ? $this->once() : $this->never())->method('delete');
        $role = $this->createMock(Role::class);
        $role->method('getValue')->with('cat_org_id')->willReturn($orgId);
        $gCurrentUser = $this->createMock(User::class);
        $gCurrentUser->method('isAdministrator')->willReturn($administrator);
        $user = $this->createMock(User::class);
        $user->method('getValue')->with('usr_id')->willReturn(10);
        $gCurrentOrgId = 1;
        $getMemberUuid = '00000000-0000-4000-8000-000000000001';

        // Execute the actual endpoint branch with entity doubles, without bootstrapping a web session.
        $source = file_get_contents(dirname(__DIR__, 3) . '/modules/profile/profile_function.php');
        $start = strpos($source, "        if (!\$gCurrentUser->isAdministrator())", strpos($source, "\$getMode === 'remove_former_membership'"));
        $stop = strpos($source, "    } elseif (\$getMode === 'reload_current_memberships')", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($stop);
        $branch = substr($source, $start, $stop - $start);
        $branch = str_replace(array(
            '$member = new Membership($gDb);',
            '$role = new Role($gDb, (int)$member->getValue(\'mem_rol_id\'));',
            'new Exception('
        ), array('', '', 'new \\RuntimeException('), $branch);

        ob_start();
        try {
            try {
                eval($branch);
                $this->assertTrue($allowed, 'Unauthorized deletion was accepted');
                $this->assertSame('done', ob_get_contents());
            } catch (RuntimeException $exception) {
                $this->assertFalse($allowed, 'Authorized deletion was rejected');
                $this->assertSame('SYS_NO_RIGHTS', $exception->getMessage());
                $this->assertSame('', ob_get_contents());
            }
        } finally {
            ob_end_clean();
        }
    }
}
