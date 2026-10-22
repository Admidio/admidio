<?php

namespace Admidio\Tests\Unit\Plugins\Support;

use RuntimeException;

/**
 * Answers the preferences the plugin classes read. The real SettingsManager needs a database.
 *
 * Like the real one, this double refuses a name it has no row for: registering a preference does
 * not create it, and a double that answers anyway hides exactly that mistake.
 */
final class PluginSettingsDouble
{
    /**
     * @param array<string,string> $values The preferences this organization actually has a row for.
     */
    public function __construct(private array $values = array())
    {
    }

    public function has(string $name, bool $update = false): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name, bool $update = false): string
    {
        if (!array_key_exists($name, $this->values)) {
            throw new RuntimeException('Settings name "' . $name . '" does not exist!');
        }

        return $this->values[$name];
    }

    public function getBool(string $name, bool $update = false): bool
    {
        return $this->get($name) === '1';
    }

    public function getInt(string $name, bool $update = false): int
    {
        return (int)$this->get($name);
    }

    public function getString(string $name, bool $update = false): string
    {
        return $this->get($name);
    }

    public function set(string $name, $value, bool $update = true): bool
    {
        $this->values[$name] = (string)$value;

        return true;
    }
}
