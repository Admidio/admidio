# Hook tests

Run them with the PHP CLI, they need nothing but `vendor/`:

    php tests/hooks/hooks-behaviour.php
    php tests/hooks/entity-lifecycle.php
    php tests/hooks/entity-lifecycle-2.php
    php tests/hooks/entity-value-filter.php
    php tests/hooks/entity-change-tracking-after.php

Each script prints one line per check and exits non-zero when a check fails.

`entity-lifecycle*.php` and `entity-value-filter.php` execute the real `Entity::save()` and
`Entity::delete()`. `FakeDatabase` is a `Database` subclass over an in-memory SQLite connection that
overrides the three methods `Entity` uses, so the rows are really written, read back and deleted.
The changelog is switched off in `bootstrap.php`, it needs settings and tables of its own and is not
what these tests are about.

`entity-change-tracking-after.php` is different: it extracts the change-tracking block of
`Entity::setValue()` and its comparison methods verbatim from the source and runs them against a
fixture, so that the test cannot drift from the code it describes.

These are plain scripts and not PHPUnit cases on purpose - the branch has no test bootstrap yet.
They are written so that moving them into one is a matter of turning each `check()` into an
assertion.
