<?php
/**
 * String utility unit tests
 *
 * These tests exercise production code without database, filesystem or network access.
 */

namespace Admidio\Tests\Unit;

use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Tests\Support\AdmidioTestCase;

class StringUtilsTest extends AdmidioTestCase
{
    /**
     * @testdox Contains can compare case-sensitively and case-insensitively
     */
    public function testContainsHonorsCaseSensitivity(): void
    {
        $this->assertTrue(StringUtils::strContains('Admidio Core', 'Admidio'));
        $this->assertFalse(StringUtils::strContains('Admidio Core', 'admidio'));
        $this->assertTrue(StringUtils::strContains('Admidio Core', 'admidio', false));
    }

    /**
     * @testdox Starts-with can compare case-insensitively
     */
    public function testStartsWithCanIgnoreCase(): void
    {
        $this->assertTrue(StringUtils::strStartsWith('Admidio Core', 'admidio', false));
        $this->assertFalse(StringUtils::strStartsWith('Admidio Core', 'core', false));
    }

    /**
     * @testdox Ends-with can compare case-insensitively
     */
    public function testEndsWithCanIgnoreCase(): void
    {
        $this->assertTrue(StringUtils::strEndsWith('README.PHP', '.php', false));
        $this->assertFalse(StringUtils::strEndsWith('README.PHP', '.xml', false));
    }

    /**
     * @testdox Multiple replacements use the production helper
     */
    public function testMultipleReplacements(): void
    {
        $this->assertSame(
            'Admidio regression suite',
            StringUtils::strMultiReplace('PRODUCT test suite', array(
                'PRODUCT' => 'Admidio',
                'test' => 'regression'
            ))
        );
    }

    /**
     * @testdox HTML tags and surrounding whitespace are stripped
     */
    public function testStripTags(): void
    {
        $this->assertSame('Hello world', StringUtils::strStripTags('  <strong>Hello</strong> world  '));
        $this->assertSame(
            array('one', 'two'),
            StringUtils::strStripTags(array(' <b>one</b> ', '<i>two</i>'))
        );
    }

    /**
     * @testdox Character validation checks real email syntax
     */
    public function testEmailCharacterValidation(): void
    {
        $this->assertTrue(StringUtils::strValidCharacters('member@example.org', 'email'));
        $this->assertFalse(StringUtils::strValidCharacters('not an email', 'email'));
        $this->assertFalse(StringUtils::strValidCharacters('', 'email'));
    }
}
