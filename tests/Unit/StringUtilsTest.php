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
        // the whole ending has to be compared, not just its first character
        $this->assertFalse(StringUtils::strEndsWith('README.PHP', '.pdf', false));
        // an ending longer than the string must not read before the start of the string
        $this->assertFalse(StringUtils::strEndsWith('.php', 'README.php', false));
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

    /**
     * @testdox Invalid filename character pattern returns valid character class
     */
    public function testInvalidFilenameCharsPattern(): void
    {
        $pattern = StringUtils::getInvalidFilenameCharsPattern();
        $this->assertSame('[' . StringUtils::INVALID_FILENAME_CHARS . ']', $pattern);
        $this->assertMatchesRegularExpression('#' . $pattern . '#', 'bad/file');
        $this->assertMatchesRegularExpression('#' . $pattern . '#', 'bad?file');
        $this->assertMatchesRegularExpression('#' . $pattern . '#', 'bad*file');
        $this->assertMatchesRegularExpression('#' . $pattern . '#', 'bad:file');
        $this->assertDoesNotMatchRegularExpression('#' . $pattern . '#', 'valid-file.pdf');
    }

    /**
     * @testdox Filename sanitization cleans invalid characters, dots, and hyphens
     */
    public function testFilenameSanitization(): void
    {
        $this->assertSame('normal.pdf', StringUtils::sanitizeFilename('normal.pdf'));
        $this->assertSame('test-file-name.txt', StringUtils::sanitizeFilename('test?file*name.txt'));
        $this->assertSame('bad-char-slash-back.doc', StringUtils::sanitizeFilename("bad:char/slash\\back.doc"));
        $this->assertSame('hidden_file.png', StringUtils::sanitizeFilename('.hidden_file.png'));
        $this->assertSame('multiple.dots.test.jpg', StringUtils::sanitizeFilename('multiple...dots...test.jpg'));
        $this->assertSame('dashes.txt', StringUtils::sanitizeFilename('---dashes---.txt'));
        $this->assertSame('file.txt', StringUtils::sanitizeFilename('???***.txt'));
        $this->assertSame('file', StringUtils::sanitizeFilename(''));
    }

    /**
     * @testdox Filename validation correctly validates filenames and foldernames
     */
    public function testFilenameValidation(): void
    {
        $this->assertTrue(StringUtils::strValidCharacters('document.pdf', 'file'));
        $this->assertTrue(StringUtils::strValidCharacters('my [1] document.pdf', 'file'));
        $this->assertFalse(StringUtils::strValidCharacters('document/bad.pdf', 'file'));
        $this->assertFalse(StringUtils::strValidCharacters('document?bad.pdf', 'file'));
        $this->assertTrue(StringUtils::strValidCharacters('My Folder', 'folder'));
        $this->assertFalse(StringUtils::strValidCharacters('My/Folder', 'folder'));
    }
}

