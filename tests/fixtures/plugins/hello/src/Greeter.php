<?php

namespace AdmidioPlugin\Hello;

final class Greeter
{
    public static function decorate(string $greeting, string $name): string
    {
        return $greeting . ' ' . $name . '!';
    }
}
