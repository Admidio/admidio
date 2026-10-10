# Admidio core updater

The updater replaces the files of an Admidio installation with those of a new release and then
hands over to `install/update.php` for the database. It uses only PHP itself, so it keeps
working while `src/`, `vendor/`, `system/`, `modules/` and the themes are replaced.

## Files

```text
index.php          the pages and requests of the browser
cli.php            php install/core-update/cli.php status
CoreUpdater.php    phase B: extraction, file installation, rollback (being reworked)
release-key.pem    the public key that signs update.txt (shipped with every release)
lib/bootstrap.php  loads the files below
lib/common.php     configuration, texts, state and lock of an update, cookie, versions, pages
lib/login.php      the updater's own login (fallback during maintenance mode)
lib/download.php   HTTPS, signature of update.txt, release information, manifest, ZIP reading
lib/phase-a.php    phase A and the checks P1-P9
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
`managedRootFiles`, `preservePaths`, `requirements` (`php`, `extensions`), `totalSize` and
`files` (path => SHA-256). The package may come from any mirror.

Every installation can install the stable release. The beta channel is only offered with
`$gDebug`. Downloads are HTTPS only, redirects are checked hop by hop, and packages are read
entry by entry with path, link, collision and size checks.

## Phase A

Phase A runs in the installed updater: the channel choice (only with `$gDebug`, otherwise the
check starts at once), `update.txt` and its signature, the version rule and the checks P1-P10,
then the confirmation, the download in range requests of a few seconds each, the verification
of package and manifest, and the copy of the updater of the new release over this one. It ends
in state `prepared`; phase B continues there.

A failed check or a network error can be fixed and tried again. A failed verification ends the
update and removes its workspace.

## Development

With `$gDebug = true` two settings in `config.php` point the updater to a test server:

```php
$gCoreUpdateServerUrl = 'http://host.docker.internal:8090';      // serves update.txt(.sig)
$gCoreUpdatePublicKeyFile = 'adm_my_files/core-update-test/release-key.pem';
```

Plain HTTP is only accepted below `$gCoreUpdateServerUrl`. Both settings are ignored without
`$gDebug`.

Because phase A replaces `install/core-update/` with the updater of the release it installs, a
test release must be built from the current working tree, or it overwrites newer code there.

## Building a release

> `build-release.php` is the builder of the first implementation. It is replaced in step 8 of
> the concept and does not produce the formats above yet.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php build-release.php --private-key=/secure/path/admidio-release-private.pem
```
