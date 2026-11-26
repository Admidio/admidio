<?php

namespace Admidio\Tests\Http;

use Admidio\Tests\Support\HttpClient;
use Admidio\Tests\Support\HttpTestCase;

class RoutesTest extends HttpTestCase
{
    public function testGuestCanReadPublicAnnouncements(): void
    {
        $page = $this->browser->request('/modules/announcements.php');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('HTTP public announcement', $page->body);
        $this->assertStringContainsString('Public HTTP route fixture', $page->body);
        $this->assertSame('', $page->header('Location'));
    }

    public function testEditorRoleAllowsAnnouncementFormWithoutAdministratorRights(): void
    {
        $this->assertSame('success', $this->login('httpeditor', 'test_editor_123')->json()['status']);
        $page = $this->browser->request('/modules/announcements.php?mode=edit');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('id="adm_announcements_edit_form"', $page->body);
        $this->assertNotSame('', $this->csrfToken($page));
        $adminPage = $this->browser->request('/modules/preferences.php');
        $this->assertSame(200, $adminPage->status);
        $this->assertStringContainsString('You do not have the required permission', $adminPage->body);
        $this->assertStringNotContainsString('id="adm_preferences"', $adminPage->body);
    }

    public function testMemberWithoutEditorRoleCannotOpenAnnouncementForm(): void
    {
        $this->assertSame('success', $this->login('httpmember', 'test_member_123')->json()['status']);
        $page = $this->browser->request('/modules/announcements.php?mode=edit');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('You do not have the required permission', $page->body);
        $this->assertStringNotContainsString('id="adm_announcements_edit_form"', $page->body);
    }

    public function testPublicLoginPageSetsSessionCookie(): void
    {
        $page = $this->browser->request('/system/login.php');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('text/html', $page->header('Content-Type'));
        $this->assertStringContainsString('HttpOnly', $page->header('Set-Cookie'));
        $this->assertStringContainsString('usr_login_name', $page->body);
        $this->assertNotSame('', $this->csrfToken($page));
    }

    public function testGuestIsRedirectedFromProtectedRoute(): void
    {
        $response = $this->browser->request('/modules/preferences.php');
        $this->assertSame(303, $response->status);
        $this->assertSame(self::$http->url . '/system/login.php', $response->header('Location'));
    }

    public function testAdministratorLoginPersistsAndLogoutRevokesAccess(): void
    {
        $login = $this->login();
        $this->assertSame(200, $login->status);
        $this->assertSame('success', $login->json()['status']);
        $page = $this->browser->request('/modules/preferences.php');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('adm_preferences', $page->body);
        $this->assertStringNotContainsString('required permission', $page->body);
        $anonymous = new HttpClient(self::$http->url);
        $this->assertSame(303, $anonymous->request('/modules/preferences.php')->status);
        $logout = $this->browser->request('/system/logout.php');
        $this->assertSame(200, $logout->status);
        $this->assertSame(303, $this->browser->request('/modules/preferences.php')->status);
    }

    public function testMemberCannotOpenAdministratorPage(): void
    {
        $this->assertSame('success', $this->login('httpmember', 'test_member_123')->json()['status']);
        $page = $this->browser->request('/modules/preferences.php');
        // Admidio currently renders application permission errors with HTTP 200.
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('You do not have the required permission', $page->body);
        $this->assertStringNotContainsString('id="adm_preferences"', $page->body);
    }

    public function testWrongPasswordDoesNotAuthenticate(): void
    {
        $this->assertSame('error', $this->login('admin', 'wrong_password')->json()['status']);
        $this->assertSame(303, $this->browser->request('/modules/preferences.php')->status);
    }

    public function testInvalidCsrfTokenDoesNotAuthenticate(): void
    {
        $this->browser->request('/system/login.php');
        $response = $this->browser->request('/system/login.php?mode=check', [
            'usr_login_name' => 'admin', 'usr_password' => 'test_admin_123', 'adm_csrf_token' => 'invalid',
        ], 'POST');
        $this->assertSame('error', $response->json()['status']);
        $this->assertSame(303, $this->browser->request('/modules/preferences.php')->status);
    }

    public function testUnknownRouteReturns404(): void
    {
        $this->assertSame(404, $this->browser->request('/modules/does-not-exist.php')->status);
    }

    public function testLoginReturnsToOriginallyRequestedProtectedPage(): void
    {
        $this->assertSame(303, $this->browser->request('/modules/preferences.php')->status);
        $login = $this->login()->json();
        $this->assertSame('success', $login['status']);
        $this->assertSame(self::$http->url . '/modules/preferences.php', $login['url']);
    }

    public function testLoginTokenCannotBeUsedInAnotherBrowserSession(): void
    {
        $token = $this->csrfToken($this->browser->request('/system/login.php'));
        $other = new HttpClient(self::$http->url);
        $other->request('/system/login.php');
        $response = $other->request('/system/login.php?mode=check', [
            'usr_login_name' => 'admin', 'usr_password' => 'test_admin_123', 'adm_csrf_token' => $token,
        ], 'POST');
        $this->assertSame('error', $response->json()['status']);
        $this->assertSame(303, $other->request('/modules/preferences.php')->status);
    }

    public function testInvalidRouteParameterIsRejected(): void
    {
        $response = $this->browser->request('/system/login.php?mode=unknown');
        $this->assertSame(200, $response->status);
        $this->assertStringNotContainsString('name="usr_password"', $response->body);
        $this->assertStringContainsString('mode', $response->body);
    }
}
