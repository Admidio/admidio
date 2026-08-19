<?php
/**
 * Base test case for all Admidio tests
 */

namespace Admidio\Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class AdmidioTestCase extends TestCase
{
    /**
     * Called before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Override in subclasses as needed
    }

    /**
     * Called after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        // Override in subclasses as needed
    }

    /**
     * Assert that a value is a valid UUID
     *
     * @param mixed $value The value to check
     * @param string $message Optional message
     */
    protected function assertValidUuid($value, string $message = ''): void
    {
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, (string) $value, $message ?: 'Value is not a valid UUID');
    }

    /**
     * Assert that a value is a valid timestamp
     *
     * @param mixed $value The value to check
     * @param string $message Optional message
     */
    protected function assertValidTimestamp($value, string $message = ''): void
    {
        if (is_string($value)) {
            $value = strtotime($value);
        }
        $this->assertIsInt($value, $message ?: 'Value is not a valid timestamp');
        $this->assertGreaterThan(0, $value, $message ?: 'Timestamp must be positive');
    }

    /**
     * Assert array has specific keys
     *
     * @param array $keys The keys to check
     * @param array $array The array to check
     * @param string $message Optional message
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array missing key: $key");
        }
    }

    /**
     * Get test database configuration
     */
    protected static function getTestDatabaseConfig(): array
    {
        return \TestEnvironment::getTestDatabaseConfig();
    }
}
