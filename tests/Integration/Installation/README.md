# Language-independent installation defaults

`DefaultEntryTranslationsTest.php` exercises the production installer, the 5.1
update step in `UpdateStepsCode`, the entities, CLI name resolution and system-mail rendering.
Run it through PHPUnit with a dedicated test database, as described in `tests/README.md`.
The same tests run on the configured MariaDB, MySQL or PostgreSQL backend.

The text source is `languages/en.xml`; other `languages/*.xml` files supply the
translations. Database defaults contain the existing IDs, not copies of the texts.
The tests verify that all affected installation IDs exist in the English file.

Coverage includes language changes, an additional organization, unchanged form
submissions (including editor paragraphs and mail line breaks), custom text,
case-sensitive migration matching, personal lists, and repeat execution.

The migration matches known defaults against the available language files and the
normalization originally used when saving them. It does not infer translations of
arbitrary custom data. Personal lists, changed text and unrecognized historical
wording remain unchanged. A custom value exactly identical to a known default in
the same default context cannot be distinguished from that default.

For UI review, switch between English and German and check role names/descriptions,
list selectors and exports, room selection in events, profile-field descriptions,
and system-mail settings. Save these forms unchanged and confirm the stored IDs
remain intact; then customize a value and confirm it stays literal after switching
language. Check mail text and placeholder substitution without sending real mail.

`SystemMailTemplateModeTest.php` also renders the system-mail preferences form and
submits it through the production form validator and preferences service. It covers
all six templates, switching back and forth after reloading, translation-ID resets,
language-dependent defaults, explicit custom text equal to a default, empty custom
versions, ignored preview submissions, and migration of existing custom templates.
The active value remains in `txt_text`; `txt_custom_text` retains the custom version
while the system default is selected. Run the database updater for existing installs
to add this column (steps 1290 and 1300).
