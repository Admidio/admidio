# Hook tests

Run them with the PHP CLI, they need nothing but `vendor/`:

    php tests/hooks/hooks-behaviour.php
    php tests/hooks/entity-lifecycle.php
    php tests/hooks/entity-lifecycle-2.php
    php tests/hooks/entity-value-filter.php
    php tests/hooks/entity-transactions.php
    php tests/hooks/entity-cascade-delete.php
    php tests/hooks/entity-change-tracking-after.php
    php tests/hooks/change-notification.php

Each script prints one line per check and exits non-zero when a check fails.

`entity-lifecycle*.php`, `entity-value-filter.php` and `entity-transactions.php` execute the real
`Entity::save()` and `Entity::delete()`. `FakeDatabase` is a `Database` subclass over an in-memory
SQLite connection that
overrides the four methods `Entity` uses plus the transaction handling, so the rows are really
written, read back, committed, rolled back and deleted. `BufferedStatement` exists because the SQLite
driver of PDO answers `rowCount()` with 0 for a SELECT, which is the number `Entity::readData()`
decides on.
The changelog is switched off in `bootstrap.php`, it needs settings and tables of its own and is not
what these tests are about.

`change-notification.php` runs the real `ChangeNotification` listener against the real `Entity`
lifecycle. The three tables of a person are created with the columns of `install/db_scripts/db.sql` and
the entity subclasses carry the hook ID, the sensitive columns and the ignored columns of the real
`User`, `UserData` and `Membership`; `$gProfileFields`, `$gSettingsManager` and `$gL10n` are stubs at the
top of the file, because the mail is not what is being tested - what the listener hears and what it makes
of it is.

`entity-change-tracking-after.php` is different: it extracts the change-tracking block of
`Entity::setValue()` and its comparison methods verbatim from the source and runs them against a
fixture, so that the test cannot drift from the code it describes.

These are plain scripts and not PHPUnit cases on purpose - the branch has no test bootstrap yet.
They are written so that moving them into one is a matter of turning each `check()` into an
assertion.
