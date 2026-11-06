<?php
/**
 * The logout hooks, checked against the real source file. The real logout needs a session, a
 * database and a user, and what matters about the hooks is the order in which logout.php does things:
 * a listener that takes over the logout has to be asked before any part of the session is ended.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Tests\Support\AdmidioTestCase;

class LogoutHooksTest extends AdmidioTestCase
{
    private function logoutSource(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 3) . '/system/logout.php');
    }

    public function testTheLogoutTargetIsAskedBeforeAnythingOfTheSessionIsEnded(): void
    {
        $source = $this->logoutSource();
        $resolve = strpos($source, "Hooks::resolve('logout_target'");
        $this->assertNotFalse($resolve, 'logout.php asks the logout_target resolver');

        $laterSteps = array(
            "Hooks::resolve('logout_response'" => 'a listener finishes the logout, e.g. the single sign-on clients are told about it',
            "Hooks::doAction('logout'" => 'the logout action is dispatched',
            '$gCurrentSession->logout()' => 'the session is ended'
        );
        foreach ($laterSteps as $needle => $step) {
            $position = strpos($source, $needle);
            $this->assertNotFalse($position, 'logout.php still has the step where ' . $step);
            $this->assertLessThan($position, $resolve, 'the resolver is asked before ' . $step);
        }
    }

    public function testAnAnswerOfTheResolverEndsTheRequestWithARedirect(): void
    {
        $source = $this->logoutSource();
        $resolve = strpos($source, "Hooks::resolve('logout_target'");
        $redirect = strpos($source, 'admRedirect($logoutTarget)');

        $this->assertNotFalse($redirect, 'logout.php redirects to the answer of the resolver');
        $this->assertGreaterThan($resolve, $redirect);
        $this->assertLessThan(strpos($source, "Hooks::doAction('logout'"), $redirect, 'and does so before the logout begins');
    }

    public function testThePageAfterTheLogoutIsFiltered(): void
    {
        $this->assertStringContainsString("Hooks::applyTypedFilters('logout_homepage'", $this->logoutSource());
    }

    public function testTheLogoutResponseIsAskedForAfterTheSessionIsEnded(): void
    {
        $source = $this->logoutSource();
        $response = strpos($source, "Hooks::resolve('logout_response'");

        $this->assertNotFalse($response, 'logout.php asks the logout_response resolver');
        $this->assertGreaterThan(strpos($source, '$gCurrentSession->logout()'), $response, 'after the session is ended');
        $this->assertGreaterThan(strpos($source, "Hooks::applyTypedFilters('logout_homepage'"), $response, 'with the filtered logout homepage');
        $this->assertStringNotContainsString('SAMLService', $source, 'logout.php does not know the single sign-on module');
    }
}
