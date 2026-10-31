<?php

namespace Admidio\Tests\Support;

use DOMDocument;
use DOMXPath;

abstract class HttpTestCase extends AdmidioTestCase
{
    protected static ?HttpTestEnvironment $http = null;
    protected HttpClient $browser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$http = new HttpTestEnvironment();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->browser = new HttpClient(self::$http->url);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->hasFailed()) {
                self::$http->writeDiagnostics(static::class . '-' . $this->getName(), 'test');
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function tearDownAfterClass(): void
    {
        try {
            self::$http?->close();
        } finally {
            self::$http = null;
            parent::tearDownAfterClass();
        }
    }

    protected function login(string $user = 'admin', string $password = 'test_admin_123'): HttpResponse
    {
        $page = $this->browser->request('/system/login.php');
        $this->assertSame(200, $page->status);
        return $this->browser->request('/system/login.php?mode=check', [
            'usr_login_name' => $user, 'usr_password' => $password,
            'adm_csrf_token' => $this->csrfToken($page),
        ], 'POST');
    }

    protected function csrfToken(HttpResponse $page): string
    {
        $tokens = $this->xpath($page)->query('//input[@name="adm_csrf_token"]/@value');
        $this->assertGreaterThan(0, $tokens->length, 'Expected a real CSRF form token.');
        return $tokens->item(0)->nodeValue;
    }

    protected function xpath(HttpResponse $page): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($page->body);
            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
