<?php

namespace Admidio\Tests\Unit;

use Admidio\Photos\ValueObject\ECard;
use Admidio\Tests\Support\AdmidioTestCase;

class ECardMessageTest extends AdmidioTestCase
{
    /** @testdox E-card messages retain text formatting and safe links without embedded files or active content */
    public function testMessageContainsOnlyTextFormatting(): void
    {
        $message = '<p style="text-align:center" onclick="alert(1)">'
            . '<strong>Hello</strong><img src="/etc/passwd" alt="secret">'
            . '<script>alert(1)</script>'
            . '<a href="javascript:alert(1)">world</a>'
            . '<a href="https://example.org/card" target="_blank">safe link</a>'
            . '<span style="color:#ff0000;background-image:url(/etc/passwd)">red</span>'
            . '</p>';

        $this->assertSame(
            '<p style="text-align:center;"><strong>Hello</strong><a>world</a>'
                . '<a href="https://example.org/card" target="_blank" rel="noreferrer noopener">safe link</a>'
                . '<span style="color:#ff0000;">red</span></p>',
            ECard::sanitizeMessage($message)
        );
    }

    /** @testdox E-card messages reject encoded and malformed image markup */
    public function testMessageRemovesOtherImageMarkup(): void
    {
        $message = '<p>Hi<img src="&#47;tmp&#47;secret.php">'
            . '<svg><image href="/tmp/secret.php"></image></svg>'
            . '<iframe src="/tmp/secret.php"></iframe></p>';

        $this->assertSame('<p>Hi</p>', ECard::sanitizeMessage($message));
    }
}
