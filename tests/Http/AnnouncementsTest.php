<?php

namespace Admidio\Tests\Http;

use Admidio\Tests\Support\HttpClient;
use Admidio\Tests\Support\HttpTestCase;

class AnnouncementsTest extends HttpTestCase
{
    public function testCreateAndDeleteEnforcesPermissionsAndCsrf(): void
    {
        $this->assertSame('success', $this->login('httpeditor', 'test_editor_123')->json()['status']);
        $form = $this->browser->request('/modules/announcements.php?mode=edit');
        $this->assertSame(200, $form->status);
        $categories = $this->xpath($form)->query('//select[@name="ann_cat_id"]//option[string-length(@value) = 36]/@value');
        $this->assertGreaterThan(0, $categories->length);
        $values = [
            'ann_headline' => 'HTTP created announcement',
            'ann_description' => 'Created through the real HTTP form',
            'ann_cat_id' => $categories->item(0)->nodeValue,
            'adm_csrf_token' => $this->csrfToken($form),
        ];
        $url = '/modules/announcements.php?mode=save';
        $rejected = $this->browser->request($url, array_replace($values, ['adm_csrf_token' => 'invalid']), 'POST');
        $this->assertSame(200, $rejected->status);
        $this->assertSame('error', $rejected->json()['status']);
        $this->assertStringNotContainsString($values['ann_headline'], $this->browser->request('/modules/announcements.php')->body);

        $saved = $this->browser->request($url, $values, 'POST');
        $this->assertSame(200, $saved->status);
        $this->assertSame('success', $saved->json()['status'], $saved->body);
        $page = $this->browser->request('/modules/announcements.php');
        $cards = $this->xpath($page)->query('//div[starts-with(@id,"adm_announcement_")][contains(.,"HTTP created announcement")]');
        $this->assertSame(1, $cards->length);
        $uuid = substr($cards->item(0)->getAttribute('id'), strlen('adm_announcement_'));
        $this->assertValidUuid($uuid);
        $actions = $this->xpath($page)->query('//div[@id="adm_announcement_' . $uuid . '"]//a[@data-href]/@data-href');
        $this->assertSame(1, $actions->length);
        // Direct actions use the session token from the real confirmation action, not a form token.
        $this->assertSame(1, preg_match("/^callUrlHideElement\('[^']+', '[^']+', '([^']+)'\)$/", $actions->item(0)->nodeValue, $tokens));
        $delete = '/modules/announcements.php?mode=delete&announcement_uuid=' . $uuid;
        try {
            $this->assertStringContainsString($values['ann_description'], $page->body);
            $editor = $this->browser;
            $this->browser = new HttpClient(self::$http->url);
            try {
                $this->assertSame('success', $this->login('httpmember', 'test_member_123')->json()['status']);
                $forged = $this->browser->request($url . '&announcement_uuid=' . $uuid,
                    array_replace($values, ['ann_headline' => 'Unauthorized change']), 'POST');
                $this->assertSame(200, $forged->status);
                $this->assertSame('error', $forged->json()['status']);
                $this->assertStringContainsString('You do not have the required permission', $forged->json()['message']);
            } finally {
                $this->browser = $editor;
            }
            $page = $this->browser->request('/modules/announcements.php');
            $this->assertStringContainsString($values['ann_headline'], $page->body);
            $this->assertStringNotContainsString('Unauthorized change', $page->body);
            $rejected = $this->browser->request($delete, ['adm_csrf_token' => 'invalid'], 'POST');
            $this->assertSame('error', $rejected->json()['status']);
            $this->assertStringContainsString($values['ann_headline'], $this->browser->request('/modules/announcements.php')->body);
        } finally {
            $deleted = $this->browser->request($delete, ['adm_csrf_token' => $tokens[1]], 'POST');
            $this->assertSame(200, $deleted->status);
            $this->assertSame('success', $deleted->json()['status']);
        }
        $this->assertStringNotContainsString($values['ann_headline'], $this->browser->request('/modules/announcements.php')->body);
    }
}
