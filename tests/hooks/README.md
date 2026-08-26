# Hook tests

Run them with the PHP CLI, they need nothing but `vendor/`:

    php tests/hooks/hooks-behaviour.php
    php tests/hooks/entity-lifecycle.php
    php tests/hooks/entity-lifecycle-2.php
    php tests/hooks/entity-value-filter.php
    php tests/hooks/entity-transactions.php
    php tests/hooks/entity-cascade-delete.php
    php tests/hooks/entity-change-tracking-after.php
    php tests/hooks/readable-name.php
    php tests/hooks/change-notification.php
    php tests/hooks/user-valid-default.php
    php tests/hooks/form-built.php
    php tests/hooks/page-hooks.php
    php tests/hooks/component-hooks.php
    php tests/hooks/translation-hooks.php
    php tests/hooks/login-hooks.php
    php tests/hooks/email-hooks.php

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

`form-built.php` executes the real `FormPresenter`, which needs nothing but the autoloader, so the
form, the dispatch and `validate()` are all the real ones.

`translation-hooks.php` executes the real `Language` against the real `languages/*.xml` of this
checkout, so the cache, the fallback to the reference language and the placeholders are the real ones;
only the four path constants of the Admidio bootstrap are defined in the test. The text it uses to
exercise the fallback is looked up rather than named, so the check keeps working when the translation
catches up.

`email-hooks.php` is built like `login-hooks.php`. The real `Email` is constructed, but nothing is
ever handed to PHPMailer - a test that tried to deliver would need a mail server or would really send
something - so the wrapper of `sendEmail()` is checked through a stand-in, the short circuit for a demo
installation is checked on the real object, and the credentials property is checked against the real
source file. That last check tests itself first, on a fabricated line that does leak.

`login-hooks.php` is two things at once. The control flow around the hooks is checked through a
stand-in, because `ModuleLogin::checkLogin()` needs a session, a form, a database and a user; and the
property that actually matters - that no login hook is ever handed the password - is checked against
the real source file, which a stand-in could not prove. The one-line dispatch sites in `logout.php` and
`system/bootstrap/function.php` are not driven by any test.

`component-hooks.php` works the same way as `page-hooks.php`: `Component` reads the settings, the
current user and the database, so the stand-in carries the two wrappers verbatim. The wrappers are the
whole change - the switch statements below them are untouched.

`page-hooks.php` is like `user-valid-default.php`: `PagePresenter` reads the settings, the
organization, the language and the session out of the database and cannot be constructed here, so the
stand-in carries `setHtmlID()` and the head of `show()` verbatim over the real `Hooks` engine. The
constructor-then-setter order it reproduces is the whole point - it is what finding 91 was.

`user-valid-default.php` is a mixture of the two. The real `User` cannot be instantiated without a
`ProfileFields` object and a database, so its constructor and its `clear()` are carried in the test
reduced to the two lines that touch `usr_valid`; everything below them is the real `Entity`, and the
checks are about which columns end up marked as changed, which `Entity` alone decides. The last check
pins the defect of finding 86 by keeping the previous `clear()` next to the current one.

`entity-change-tracking-after.php` is different: it extracts the change-tracking block of
`Entity::setValue()` and its comparison methods verbatim from the source and runs them against a
fixture, so that the test cannot drift from the code it describes.

`readable-name.php` executes the real `Entity::readableName()` and `Entity::filterReadableName()`
against `TestRoom` (named) and `TestSession` (unnamed): the generic `entity_readable_name` filter
always runs, the specific `<hookId>_readable_name` one only for an entity that names itself.

These are plain scripts and not PHPUnit cases on purpose - the branch has no test bootstrap yet.
They are written so that moving them into one is a matter of turning each `check()` into an
assertion.
