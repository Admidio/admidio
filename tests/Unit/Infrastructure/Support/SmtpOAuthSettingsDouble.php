<?php

namespace Admidio\Tests\Unit\Infrastructure\Support;

use RuntimeException;

/**
 * Answers the preferences of the mail settings. The real SettingsManager needs a database.
 *
 * It keeps the values the current request has cached apart from what the database holds, because
 * a refresh token can be replaced by another session while this one still caches the old one. Like
 * the real one, it refuses a name it has no row for.
 */
final class SmtpOAuthSettingsDouble
{
    /** @var array<int,array{0:string,1:string}> Every set() in order, as name and value. */
    public array $writes = array();

    /**
     * @param array<string,string> $values The preferences as the current request has cached them.
     * @param array<string,string> $database Rows whose value in the database differs from the cache.
     */
    public function __construct(public array $values = array(), public array $database = array())
    {
    }

    public function has(string $name, bool $update = false): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name, bool $update = false): string
    {
        if ($update && array_key_exists($name, $this->database)) {
            $this->values[$name] = $this->database[$name];
        }
        if (!array_key_exists($name, $this->values)) {
            throw new RuntimeException('Settings name "' . $name . '" does not exist!');
        }

        return $this->values[$name];
    }

    public function getString(string $name, bool $update = false): string
    {
        return $this->get($name, $update);
    }

    public function getBool(string $name, bool $update = false): bool
    {
        return $this->get($name, $update) === '1';
    }

    public function getInt(string $name, bool $update = false): int
    {
        return (int)$this->get($name, $update);
    }

    public function getAll(bool $update = false): array
    {
        return $this->values;
    }

    public function set(string $name, $value, bool $update = true): bool
    {
        $this->writes[] = array($name, (string)$value);
        $this->values[$name] = (string)$value;
        $this->database[$name] = (string)$value;

        return true;
    }

    public function setMulti(array $settings, bool $update = true): void
    {
        foreach ($settings as $name => $value) {
            $this->values[$name] = (string)$value;
            $this->database[$name] = (string)$value;
        }
    }

    public function resetAll(): void
    {
    }
}
