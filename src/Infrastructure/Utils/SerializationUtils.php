<?php

namespace Admidio\Infrastructure\Utils;

use ReflectionProperty;

/**
 * Restores properties from both legacy PHP object data and __serialize() data.
 */
final class SerializationUtils
{
    /**
     * @param array<string,mixed> $data Serialized object properties
     */
    public static function restoreProperties(object $object, array $data): void
    {
        foreach ($data as $key => $value) {
            $parts = explode("\0", $key);
            if (count($parts) === 3 && $parts[0] === '') {
                // PHP encodes protected properties as \0*\0name and private ones as \0Class\0name.
                $scope = $parts[1] === '*' ? $object : $parts[1];
                $name = $parts[2];
            } else {
                $scope = $object;
                $name = $key;
            }

            if (property_exists($scope, $name)) {
                (new ReflectionProperty($scope, $name))->setValue($object, $value);
            } else {
                // Preserve dynamic properties that may be present in older sessions.
                $object->{$name} = $value;
            }
        }
    }
}
