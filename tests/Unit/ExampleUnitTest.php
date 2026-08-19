<?php
/**
 * Example Unit Test
 * Demonstrates PHPUnit structure for Admidio regression tests
 *
 * @testdox Example unit tests show basic test structure
 */

namespace Admidio\Tests\Unit;

use Admidio\Tests\Support\AdmidioTestCase;

class ExampleUnitTest extends AdmidioTestCase
{
    /**
     * Example: Test simple assertion
     *
     * @testdox Simple assertion test
     */
    public function testSimpleAssertion(): void
    {
        $value = 42;

        $this->assertEquals(42, $value);
        $this->assertIsInt($value);
    }

    /**
     * Example: Test string utilities
     *
     * @testdox String utility functions work correctly
     */
    public function testStringUtility(): void
    {
        $input = 'Hello World';
        $expected = 'hello world';

        $result = strtolower($input);

        $this->assertEquals($expected, $result);
    }

    /**
     * Example: Test array operations
     *
     * @testdox Array operations maintain data integrity
     */
    public function testArrayOperations(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertArrayHasKeys(['a', 'b', 'c'], $array);
        $this->assertCount(3, $array);
        $this->assertEquals(1, $array['a']);
    }

    /**
     * Example: Test exception handling
     *
     * @testdox Exceptions are thrown correctly
     */
    public function testExceptionHandling(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        throw new \RuntimeException('Something went wrong');
    }

    /**
     * Example: Test with data provider
     *
     * @dataProvider provideBooleanValues
     * @testdox Boolean assertions work with data provider
     */
    public function testWithDataProvider(bool $value, bool $expected): void
    {
        $this->assertEquals($expected, $value);
    }

    /**
     * Data provider for testWithDataProvider
     */
    public function provideBooleanValues(): array
    {
        return [
            'true value' => [true, true],
            'false value' => [false, false],
        ];
    }

    /**
     * Example: Test UUID validation
     *
     * @testdox UUID validation helper works
     */
    public function testUuidValidation(): void
    {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->assertValidUuid($validUuid);
    }

    /**
     * Example: Test UUID validation fails for invalid UUID
     *
     * @testdox Invalid UUID fails validation
     */
    public function testInvalidUuidFails(): void
    {
        $invalidUuid = 'not-a-uuid';

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        $this->assertValidUuid($invalidUuid);
    }
}
