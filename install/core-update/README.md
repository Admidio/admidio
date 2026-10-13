# Admidio core updater

The updater replaces the files of an Admidio installation with those of a new release and then
hands over to `install/update.php` for the database. It uses only PHP itself, so it keeps
working while `src/`, `vendor/`, `system/`, `modules/` and the themes are replaced.

## Files

```text
index.php          the pages and requests of the browser
cli.php            the command line: status, apply, rollback
release-key.pem    the public key that signs update.txt (shipped with every release)
texts/             the texts of the updater per language (written by the release builder)
lib/bootstrap.php  loads the files below
lib/common.php     configuration, texts, state and lock of an update, cookie, versions, pages
lib/login.php      the updater's own login (fallback during maintenance mode)
lib/download.php   HTTPS, signature of update.txt, release information, manifest, ZIP reading
lib/phase-a.php    phase A and the checks P1-P9
lib/phase-b.php    phase B: extraction, plan, installation, verification, rollback
lib/cli.php        the command line: apply and rollback from one decision to the next (loaded by cli.php only)
```

Everything in `lib/` is plain functions in the namespace `AdmidioCoreUpdater`. It reads
`adm_my_files/config.php`, connects with plain PDO and needs nothing else.
`install/update.php` loads `lib/common.php`, so the state of an update and the updater cookie
have one implementation.

## State and access

- Every update has a workspace `adm_my_files/core-update/<update id>/` with `state.json`
  (versioned by `schemaVersion`, written atomically, the version before kept as
  `state.json.previous`). `active.json` names the unfinished update; `update.lock` serialises
  the requests.
- Only an administrator gets in. As long as no Admidio file has been changed, that is the
  Admidio login: `index.php` includes `system/common.php` of the installed release, and the
  settings page simply links to `install/core-update/`. From maintenance mode on Admidio cannot
  be used: the browser that started the installation carries the updater cookie
  `<cookie prefix>_CORE_UPDATE`, and the updater's own login (password, security code when
  two-factor authentication is set up, membership in a role with administrator rights) is the
  fallback for another device or a lost cookie. Five failed logins within 15 minutes block it
  for 15 minutes; every attempt is written to `adm_my_files/core-update/login.log`.
- The texts are the `SYS_CORE_UPDATE_*` keys of the language files. A release carries them in
  `texts/<language>.json`; a development tree reads them from `languages/`.

## Release information

The releases are announced in `https://www.admidio.org/update.txt`, the file Admidio already
reads for its update check, with a few more lines:

```text
Version=5.1.0
Package-URL=https://.../admidio-5.1.0.zip
Package-SHA256=<sha256 of the ZIP>
Package-Size=12345678
Minimum-Source-Version=5.0.0
Minimum-PHP-Version=8.2
Beta-Version=5.2.0
Beta-Release=1
Beta-Package-URL=...
Beta-Package-SHA256=...
Beta-Package-Size=...
Beta-Minimum-Source-Version=...
Beta-Minimum-PHP-Version=...
```

`update.txt.sig` next to it is the base64 RSA-SHA256 signature over the exact bytes of
`update.txt`, made with the release key; its public part is `release-key.pem`. That one
signature is the whole chain of trust: it names the SHA-256 of the package, and the package
contains `install/update-manifest.json` with `version`, `managedDirectories`,
`managedRootFiles`, `preservePaths`, `migrationPaths` (optional), `requirements` (`php`,
`extensions`), `totalSize` and `files` (path => SHA-256). The package may come from any mirror.

Every installation can install the stable release. With `$gDebug` the updater also offers the
beta channel and the developer channels: master, a branch, a tag or a pull request (forks
included) as the GitHub source archive `https://github.com/Admidio/admidio/archive/refs/...`,
whose address the updater builds itself from the name entered. A source archive is unsigned: it
is downloaded during the check, the administrator confirms its commit (from the ZIP comment), and
exactly that file is installed. The updater makes its manifest from what a release of it would
contain (`archive.exclude` of its `composer.json`, without `vendor/`), removes nothing, never
touches `vendor/` (a changed `composer.lock` asks for `composer install --no-dev` by hand) and
removes the manifest of the installed release, so the next update keeps every file it replaces.
An archive without the updater is installed by the installed one, which stays; such a version
cannot take over the database update, which the administrator then runs with
`install/update.php`.

Downloads are HTTPS only, redirects are checked hop by hop, and packages are read entry by entry
with path, link, collision and size checks.

## Phase A

Phase A runs in the installed updater: the channel choice (only with `$gDebug`, otherwise the
check starts at once), `update.txt` and its signature, the version rule and the checks P1-P10,
then the confirmation, the download in range requests of a few seconds each, the verification
of package and manifest, and the copy of the updater of the new release over this one. It ends
in state `prepared`; phase B continues there.

A failed check or a network error can be fixed and tried again. A failed verification ends the
update and removes its workspace.

## Phase B

Phase B runs in the updater of the new release. It unpacks the package into the workspace and
checks every file against the manifest (`extracting`), then compares the installation with the
release (`planning`): a file that is already right gets no operation, files in the managed
directories that the release does not have are removed, root files only if the installed manifest
lists them. A file that is replaced or removed and differs from the original file of the installed
release, or that the release does not know (every such file when the installed release has no
manifest), is marked to be kept.

The administrator sees the plan, the files that are removed or kept and the checks D1-D3 (`review`),
chooses a backup of the database by the updater or confirms an own backup, and starts the
installation. Then the browser gets the updater cookie and maintenance mode goes on. The database is
backed up table by table with mysqldump-php from the package (PostgreSQL with `pg_dump`, if PHP may
run it) into `adm_my_files/core-update/dumps/` (`dumping`); if that fails, maintenance mode goes off
again and nothing is changed. With a password the ZIP of the backup is encrypted with AES-256: the
password comes from `--dump-password-env` of the command line, else from `$gCoreUpdateDumpPassword`
in `config.php`, else from the write-only preference `system_update_dump_password` of the first
organization. It is read again whenever it is needed, also for a restore, and never written to the
state or the log; if the ZIP extension cannot encrypt, the review warns (D4) and the backup stays
unencrypted. `system/bootstrap/` is installed first, so that even a release without
maintenance mode shows the maintenance page; every replaced or removed file is copied to `backup/`
in the workspace before the operation, and `journal.jsonl` records each operation before and after
it (`installing`). The kept files go from that copy into a ZIP in `adm_my_files/core-update/kept/`,
which is never removed automatically (`archiving`). Every installed file is hashed again and OPcache
is reset (`verifying`). At `files_installed` the page of the updater has `install/update.php` of the
new release update the database (`mode=core_update`, a POST with the updater cookie and the CSRF
token of the update, answered with JSON only), while its sidebar shows the step. A lock keeps a
second request from starting another migration next to it; if a proxy cuts off the long request,
the page asks the updater until the migration no longer runs. The result page removes the
workspace and empties the Admidio session of the browser, so that Admidio builds its objects with
the new classes while the administrator stays logged in.

Every request does at most 8 seconds or 200 files of work. Up to `review` nothing is changed and
cancelling removes the workspace. From `installing` on an error stops in `rollback_required`: the
update continues where it stopped, or the journal is undone (`rolling_back`), which is possible
until the database update starts. If the database update fails (`database_failed`), the page names
the error and the backup and offers to retry the database update, or to restore the previous version
as a whole: the database from the backup of the updater (`restoring_database`; MySQL/MariaDB with
PDO table by table, PostgreSQL with `psql`), the tables the new version created are removed, then the
files from the journal. Without such a backup the administrator restores the database first and
confirms it. Paths that the database update of a release moves or removes itself (6.0 moves
`adm_plugins`) are listed in `install/db_scripts/migration-paths.json` of the release and become
`migrationPaths` of its manifest; the updater copies them before `install/update.php` runs and puts
them back with the previous version.

Every update writes `update.log` in its workspace (states, errors with their English detail,
decisions); when the update is over it is copied to `adm_my_files/logs/core-update_<date>_<from>_to_<to>.log`.

## Command line

```bash
php admidio update:apply --as=admin [--yes] [--dry-run]       # or: php install/core-update/cli.php apply
php admidio update:rollback --as=admin                        # or: php install/core-update/cli.php rollback
php install/core-update/cli.php status
```

`update:apply` and `update:rollback` of the Admidio command line run `cli.php` in a process of
their own, because that process has `src/` and `vendor/` loaded, which the update replaces.
`cli.php` can be run directly; shell access is the proof of authority, so it has no login. While an
update is unfinished, Admidio itself may not start, and `cli.php` is the way to continue or undo
it. It answers in English and warns when it runs as another user than the owner of the files.

`apply` calls the same functions as the page, so it goes through the same states and keeps the
same `state.json`: an update started in the browser can be continued on the command line and the
other way round. It asks before the download and before the installation, as the page does;
`--yes` answers both, and without a terminal or with `--no-interaction` it stops before it changes
anything. Where the page loads a new page, the command line starts a new process: after phase A
`cli.php apply --resume`, which runs the updater of the new release, and after the files
`cli.php database`, which loads `system/bootstrap/cli.php` of the new release (no plugins, no
session) and has `CoreUpdateHandover::updateDatabase()` do what `install/update.php` does for the
page. If PHP may not start processes, `cli.php` prints the command to run next.

| Option | Effect |
|---|---|
| `--channel=`, `--ref=` | the channel and its branch, tag or pull request, as on the page (`$gDebug` for all but stable) |
| `--backup=dump\|own` | the updater backs up the database (the default where it can), or an own backup is confirmed |
| `--on-changed=archive\|cancel` | changed and unknown files are kept in the archive and replaced (default), or the update is cancelled at the review |
| `--dry-run` | the update is checked and planned, then cancelled; the updater of the new release stays, as after a cancel on the page |
| `--skip-database` | the update ends after the files; maintenance mode stays on until `apply --resume` or `install/update.php` updates the database |
| `--resume` | continue the unfinished update, also to retry a failed database update |
| `--dump-password-env=VAR` | the password of the backup is in the environment variable `VAR` (also for `rollback`), never on the command line itself |

`rollback` cancels an update that has not changed anything, restores the previous files until the
database update has started, and after a failed database update restores the previous version
with the backup of the updater, or with `--database-restored` once the administrator has restored
the database from an own backup. The notes about plugins that the database update of 6.0 writes
reach the command line only through the Admidio log, because `install/update.php` gets them from a
cookie.

## Development

With `$gDebug = true` two settings in `config.php` point the updater to a test server:

```php
$gCoreUpdateServerUrl = 'http://host.docker.internal:8090';      // serves update.txt(.sig)
$gCoreUpdatePublicKeyFile = 'adm_my_files/core-update-test/release-key.pem';
$gCoreUpdateArchiveUrl = 'http://host.docker.internal:8090/archive/'; // instead of GitHub
```

Plain HTTP is only accepted below `$gCoreUpdateServerUrl`. The settings are ignored without
`$gDebug`.

Because phase A replaces `install/core-update/` with the updater of the release it installs, a
test release must be built from the current working tree, or it overwrites newer code there.
Phase B replaces every file: test it on a separate installation, never on a development tree.

## Building a release

`build-release.php` in the root of the repository builds a release from a clean export of a commit
(`git archive`), never from the working tree, which therefore has to be clean:

```bash
php build-release.php --key=/secure/path/release-private.pem --ref=v5.1.0 \
    --update-txt=https://www.admidio.org/update.txt
```

It runs `composer install --no-dev` in the export and takes what `archive.exclude` of the exported
`composer.json` leaves in - the one packaging rule, which the developer channels apply as well. It
adds `install/core-update/texts/<language>.json` (the `SYS_CORE_UPDATE_*` keys of every language
file) and `install/update-manifest.json` (managed directories and root files derived from the files,
the official plugins included, `migrationPaths` from `install/db_scripts/migration-paths.json`,
requirements from `composer.json`). In `build/` it writes:

```text
admidio-<version>.zip               the package; the same commit always gives the same bytes
admidio-core-updater-<version>.zip  install/core-update/ alone, extracted into the Admidio
                                    directory of an installation without the updater
update.txt, update.txt.sig          the given update.txt with the lines of this release (stable, or
                                    the Beta- lines of a beta), signed
```

The package URL in `update.txt` is the asset of the GitHub release of the tag, or `--package-url`.
The builder refuses a dirty working tree (`--allow-dirty` builds the commit anyway), a tag that
does not name the version of the constants (`v5.0.17`, `v5.0-Beta.1`), and a release whose
`install/core-update/release-key.pem` is not the public part of the signing key. Before it ends it
checks the result as the updater will: `update.txt` verifies and names the package, and every file
matches the manifest. `php build-release.php sign --key=... --update-txt=update.txt` signs an
`update.txt` that was edited by hand.

The workflow `.github/workflows/release.yml` runs the builder for every `v*` tag in the protected
environment `release` and creates a draft GitHub release with the three files. Publishing the
release and copying `update.txt` and `update.txt.sig` to admidio.org are the final, deliberate
steps.

### The release key

There is one key, an RSA key of 4096 bits:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -aes-256-cbc -out release-private.pem
openssl pkey -in release-private.pem -pubout -out install/core-update/release-key.pem
```

The public part is committed as `install/core-update/release-key.pem`. The private part is kept
offline by the maintainers and is the secret `ADMIDIO_RELEASE_KEY` (with
`ADMIDIO_RELEASE_KEY_PASSPHRASE`) of the GitHub environment `release`, which needs the approval of
a reviewer and is restricted to `v*` tags; the builder also reads it from `--key` or
`ADMIDIO_RELEASE_KEY_FILE`. It never enters a package.

To change the key, commit the new public key and build the next release with the old private key
and `--key-rotation`: installations verify that release with the key they have and get the new one
with it. An installation that skips that release cannot verify later ones and has to install one
release by hand. If the private key leaks, publish a new key and announce a manual update.

For a local test server, `--test-release` puts the public part of a test key into the package.
