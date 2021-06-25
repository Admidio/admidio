Openwall Phpass, modernized
===========================

[![Build Status](https://secure.travis-ci.org/hautelook/phpass.png?branch=master)](http://travis-ci.org/hautelook/phpass)
[![HHVM Status](http://hhvm.h4cc.de/badge/hautelook/phpass.png)](http://hhvm.h4cc.de/package/hautelook/phpass)

This is Openwall's [Phpass](http://openwall.com/phpass/), based on the 0.3 release, but modernized slightly:

- Namespaced
- Composer support (Autoloading)
- PHP 5 style
- Unit Tested

The changes are minimal and mostly stylistic. The source code is in the public domain. We claim no ownership, but needed it for one of our projects, and wanted to make it available to other people as well.

* `1.1.0` - Modified to add `random_bytes` hook function.
* `1.0.0` - Modified to use [hash_equals](http://php.net/hash_equals) to be resistant to timing attacks. This requires `php >= 5.6.0`.
* `0.3.x` - Very close to the original version. Requires `php >= 5.3.3`.

## Customizing the Source of Randomness

In version `1.1.0`, the `get_random_bytes` function checks for the presence of a `random_bytes` function. If a `random_bytes` function is callable, then `random_bytes` will be used as the source for random bytes output. Otherwise, the original `get_random_bytes` code will be used.

## Installation ##

Add this requirement to your `composer.json` file and run `composer.phar install`:

    {
        "require": {
            "hautelook/phpass": "1.0.0"
        }
    }

## Usage ##

The following example shows how to hash a password (to then store the hash in the database), and how to check whether a provided password is correct (hashes to the same value):

``` php
<?php

namespace Your\Namespace;

use Hautelook\Phpass\PasswordHash;

require_once(__DIR__ . "/vendor/autoload.php");

$passwordHasher = new PasswordHash(8,false);

$password = $passwordHasher->HashPassword('secret');
var_dump($password);

$passwordMatch = $passwordHasher->CheckPassword('secret', "$2a$08$0RK6Yw6j9kSIXrrEOc3dwuDPQuT78HgR0S3/ghOFDEpOGpOkARoSu");
var_dump($passwordMatch);

