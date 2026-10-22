<?php
/**
 * Request Parameter Validation Tests
 *
 * Tests the two places every module entry file relies on before it touches a request value:
 * admFuncVariableIsValid(), which types and restricts a GET or POST parameter, and the StringUtils
 * checks behind the file and folder names of the documents module.
 *
 * These are pure functions, but they are the boundary the rest of the code trusts, so they belong
 * in the regression suite rather than being taken on faith.
 */

namespace Admidio\Tests\Integration\Security;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Tests\Support\DatabaseTestCase;

class RequestValidationTest extends DatabaseTestCase
{
    /**
     * Call admFuncVariableIsValid and report whether it refused the value.
     */
    private function refuses(array $request, string $name, string $type, array $options = array()): bool
    {
        try {
            admFuncVariableIsValid($request, $name, $type, $options);

            return false;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Test that a missing parameter falls back
     *
     * @testdox A parameter that is not in the request falls back to its default
     */
    public function testMissingParameterFallsBackToItsDefault(): void
    {
        $this->assertEquals('list', admFuncVariableIsValid(array(), 'mode', 'string', array('defaultValue' => 'list')));
        $this->assertEquals(0, admFuncVariableIsValid(array(), 'id', 'numeric'));
        $this->assertEquals('', admFuncVariableIsValid(array(), 'name', 'string'));
        $this->assertFalse(admFuncVariableIsValid(array(), 'flag', 'bool'));

        // an empty value counts as not given, which is what makes an optional uuid work
        $this->assertEquals('', admFuncVariableIsValid(array('uuid' => ''), 'uuid', 'uuid'));
    }

    /**
     * Test that a mandatory parameter is enforced
     *
     * @testdox A mandatory parameter that is missing is refused
     */
    public function testMandatoryParameterThatIsMissingIsRefused(): void
    {
        $this->assertTrue($this->refuses(array(), 'uuid', 'uuid', array('requireValue' => true)));
        $this->assertTrue($this->refuses(array('uuid' => ''), 'uuid', 'uuid', array('requireValue' => true)));

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertEquals($uuid, admFuncVariableIsValid(array('uuid' => $uuid), 'uuid', 'uuid', array('requireValue' => true)));
    }

    /**
     * Test that a value outside the allowed set is refused
     *
     * @testdox A value that is not in the list of allowed values is refused
     */
    public function testValueOutsideTheAllowedSetIsRefused(): void
    {
        $allowed = array('validValues' => array('html', 'csv', 'pdf'));

        $this->assertEquals('csv', admFuncVariableIsValid(array('mode' => 'csv'), 'mode', 'string', $allowed));
        $this->assertTrue($this->refuses(array('mode' => 'exec'), 'mode', 'string', $allowed));

        // the comparison is strict, so a value that only looks equal does not pass
        $this->assertTrue($this->refuses(array('mode' => 'HTML'), 'mode', 'string', $allowed));
    }

    /**
     * Test that an allowed value is returned exactly as it was listed
     *
     * @testdox An allowed value is returned as it is listed and not html encoded
     */
    public function testAllowedValueIsReturnedAsItIsListed(): void
    {
        // The CSV import offers the quotation characters as the enclosure of a column. They are
        // read as a string, and the html encoding of that datatype would turn them into &quot; and
        // &#039;, which are not the characters the reader has to be given.
        $enclosure = array('validValues' => array('', 'AUTO', '"', '\''));

        $this->assertSame('"', admFuncVariableIsValid(array('e' => '"'), 'e', 'string', $enclosure));
        $this->assertSame('\'', admFuncVariableIsValid(array('e' => '\''), 'e', 'string', $enclosure));
        $this->assertSame('AUTO', admFuncVariableIsValid(array('e' => 'AUTO'), 'e', 'string', $enclosure));

        // anything that is not listed is still refused, markup included
        $this->assertTrue($this->refuses(array('e' => '<b>AUTO</b>'), 'e', 'string', $enclosure));

        // an int parameter is converted first and compared to the list afterwards
        $modes = array('validValues' => array(1, 2, 3));
        $this->assertSame(2, admFuncVariableIsValid(array('m' => '2'), 'm', 'int', $modes));
        $this->assertTrue($this->refuses(array('m' => '9'), 'm', 'int', $modes));
    }

    /**
     * Test that a string parameter cannot carry markup
     *
     * @testdox A string parameter is stripped of html and encoded
     */
    public function testStringParameterIsStrippedOfHtmlAndEncoded(): void
    {
        $value = admFuncVariableIsValid(array('t' => '<script>alert(1)</script>Title'), 't', 'string');
        $this->assertStringNotContainsString('<script>', $value);
        $this->assertEquals('alert(1)Title', $value);

        // what remains is encoded, so it cannot break out of an attribute either
        $quoted = admFuncVariableIsValid(array('t' => 'He said "hi" & left'), 't', 'string');
        $this->assertStringNotContainsString('"', $quoted);
        $this->assertStringContainsString('&quot;', $quoted);
        $this->assertStringContainsString('&amp;', $quoted);
    }

    /**
     * Test that a uuid parameter has to be one
     *
     * @testdox A uuid parameter refuses anything that is not a uuid
     */
    public function testUuidParameterRefusesAnythingThatIsNotAUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertEquals($uuid, admFuncVariableIsValid(array('u' => $uuid), 'u', 'uuid'));

        $this->assertTrue($this->refuses(array('u' => 'not-a-uuid'), 'u', 'uuid'));
        $this->assertTrue($this->refuses(array('u' => '1'), 'u', 'uuid'));
        $this->assertTrue($this->refuses(array('u' => "' OR 1=1 -- "), 'u', 'uuid'));
    }

    /**
     * Test that a file parameter cannot walk up the tree
     *
     * @testdox A file parameter that walks up the directory tree is refused
     */
    public function testFileParameterThatWalksUpTheTreeIsRefused(): void
    {
        $this->assertTrue($this->refuses(array('f' => '../../adm_my_files/config.php'), 'f', 'file'));
        $this->assertTrue($this->refuses(array('f' => '..\\config.php'), 'f', 'file'));
        $this->assertTrue($this->refuses(array('f' => '/etc/passwd'), 'f', 'file'));

        $this->assertEquals('minutes.pdf', admFuncVariableIsValid(array('f' => 'minutes.pdf'), 'f', 'file'));
    }

    /**
     * Test that a traversal survives no encoding
     *
     * @testdox A traversal in a file name is refused even when it is url encoded
     */
    public function testTraversalInAFileNameIsRefusedEvenWhenEncoded(): void
    {
        // the name is decoded before it is checked, so encoding the separators does not help
        $this->expectException(Exception::class);
        StringUtils::strIsValidFileName('%2e%2e%2fpasswd');
    }

    /**
     * Test that a file name may not hide itself
     *
     * @testdox A file name that starts with a dot is refused
     */
    public function testFileNameThatStartsWithADotIsRefused(): void
    {
        $this->expectException(Exception::class);
        StringUtils::strIsValidFileName('.htaccess');
    }

    /**
     * Test that executable uploads are blocked
     *
     * @testdox A file name with an executable extension is refused
     */
    public function testFileNameWithAnExecutableExtensionIsRefused(): void
    {
        foreach (array('shell.php', 'page.phtml', 'script.js', 'archive.phar', 'drawing.svg') as $name) {
            $refused = false;
            try {
                StringUtils::strIsValidFileName($name);
            } catch (Exception $e) {
                $refused = true;
            }
            $this->assertTrue($refused, $name . ' must be refused as an upload.');
        }

        // an ordinary document passes
        $this->assertTrue(StringUtils::strIsValidFileName('report.pdf'));
        $this->assertTrue(StringUtils::strIsValidFileName('picture.jpg'));
    }

    /**
     * Test that a folder name is a single segment
     *
     * @testdox A folder name that contains a path separator is refused
     */
    public function testFolderNameThatContainsAPathSeparatorIsRefused(): void
    {
        foreach (array('a/b', '..', '../up', 'a\\b', '.hidden') as $name) {
            $refused = false;
            try {
                StringUtils::strIsValidFolderName($name);
            } catch (Exception $e) {
                $refused = true;
            }
            $this->assertTrue($refused, $name . ' must be refused as a folder name.');
        }

        $this->assertTrue(StringUtils::strIsValidFolderName('Minutes 2030'));
    }

    /**
     * Test the encoder that the templates rely on
     *
     * @testdox Output encoding turns markup into text
     */
    public function testOutputEncodingTurnsMarkupIntoText(): void
    {
        $encoded = SecurityUtils::encodeHTML('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('<img', $encoded);
        $this->assertStringContainsString('&lt;img', $encoded);
        $this->assertStringNotContainsString('"', $encoded);

        // stripping is the other half and removes the tag with its attributes
        $this->assertEquals('bold', StringUtils::strStripTags('  <b>bold</b>  '));
    }

    /**
     * Test that a numeric parameter refuses text
     *
     * @testdox A numeric parameter refuses text instead of turning it into zero
     */
    public function testNumericParameterRefusesText(): void
    {
        // The value is validated before it is converted, so text no longer arrives as record 0.
        $this->assertTrue($this->refuses(array('id' => 'abc'), 'id', 'numeric'));

        // a payload is refused instead of being cut down to the number it starts with
        $this->assertTrue($this->refuses(array('id' => "1 OR 1=1"), 'id', 'int'));

        // and a boolean parameter only accepts what filter_var recognises
        $this->assertTrue($this->refuses(array('b' => 'maybe'), 'b', 'bool'));
        $this->assertTrue(admFuncVariableIsValid(array('b' => '1'), 'b', 'bool'));
        $this->assertFalse(admFuncVariableIsValid(array('b' => 'false'), 'b', 'bool'));

        // a genuine number is unaffected
        $this->assertEquals(42, admFuncVariableIsValid(array('id' => '42'), 'id', 'numeric'));
        $this->assertSame(42, admFuncVariableIsValid(array('id' => '42'), 'id', 'int'));
    }
}
