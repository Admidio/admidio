# Admidio self-contained core updater

The normal settings page prepares an update and copies this directory from the selected
package before any normal Admidio core file is replaced. `index.php` and `CoreUpdater.php`
therefore use only PHP built-ins and remain usable while `src/`, `vendor/`, `system/`,
`modules/`, themes, and the normal installer are updated in place.

## Structure and access

- `lib/` holds the classes of the updater (namespace `AdmidioCoreUpdater`), loaded by
  `lib/bootstrap.php` without an autoloader. They read `adm_my_files/config.php`, connect
  with plain PDO and need nothing else. `install/update.php` and the Admidio settings page
  load the same classes, so the state of an update and the updater cookie have one
  implementation.
- Every update has a workspace `adm_my_files/core-update/<update id>/` with `state.json`
  (versioned by `schemaVersion`, written atomically, the version before kept as
  `state.json.previous`). `active.json` names the unfinished update; `update.lock` serialises
  the requests.
- Only an administrator gets in: with the updater cookie `<cookie prefix>_CORE_UPDATE`,
  which the settings page hands over when it starts an update, or with the updater's own
  login (password, security code when two-factor authentication is set up, membership in a
  role with administrator rights). Five failed logins within 15 minutes block the login for
  15 minutes; every attempt is written to `adm_my_files/core-update/login.log`. A cookie
  expires one day after its last request and is revoked when the update is over.
- `cli.php` is the command line entry point (`php install/core-update/cli.php status`).
  Shell access is the proof of authority.
- The texts are the `SYS_CORE_UPDATE_*` keys of the language files. A release carries them
  in `texts/<language>.json`; a development tree reads them from `languages/`.
- Trust: `root-key.pem` (shipped with the updater) signs `release-keys.json`, which names
  the keys of the channels and the revoked ones; the highest sequence number seen wins
  (`KeyStatement`). A channel key signs `<channel>/release.json` (`Descriptor`), which names
  the SHA-256 of the package and of `install/update-manifest.json` (`Manifest`), which names
  the SHA-256 of every file. Downloads are HTTPS only with redirects checked hop by hop
  (`HttpClient`); packages are read entry by entry with path, link, collision and size checks
  (`ZipReader`). With `$gDebug`, `$gCoreUpdateRootKeyFile` and `$gCoreUpdateServerUrl` point
  to test keys and a test server.

## Signed release contract

The regular `https://www.admidio.org/update.txt` stays small. Automatic release
installation is offered when it additionally contains:

```text
Package-URL=https://.../admidio-5.1.0.zip
Release-URL=https://.../admidio-5.1.0.release.json
```

`Release-URL.sig` is fetched automatically. The release descriptor contains only
small release metadata and hashes, for example:

```json
{
  "schema": 1,
  "version": "5.1.0",
  "packageSha256": "<sha256 of the complete ZIP>",
  "packageSize": 12345678,
  "manifestSha256": "<sha256 of install/update-manifest.json>"
}
```

Both `release.json` and the internal manifest are signed with RSA-SHA256. Their
signatures are stored as base64 text in adjacent `.sig` files. The exact JSON file
bytes are signed; there is no second canonicalization step.

The trusted project public key must be shipped as:

```text
install/core-update/public-key.pem
```

The full file inventory is not downloaded during an update check. It is contained
inside the release ZIP as:

```text
install/update-manifest.json
install/update-manifest.json.sig
```

A simplified manifest looks like:

```json
{
  "schema": 1,
  "version": "5.1.0",
  "manageRootFiles": true,
  "managedDirectories": [
    "install",
    "languages",
    "libs",
    "modules",
    "src",
    "system",
    "vendor",
    "themes/simple"
  ],
  "preservePaths": [
    "adm_my_files",
    "install/core-update"
  ],
  "files": {
    "index.php": "<sha256>",
    "src/Infrastructure/Database.php": "<sha256>"
  }
}
```

The manifest is authoritative inside `managedDirectories`. Files found there that
are not part of the authenticated target release are backed up and removed. This
also removes obsolete core files and prevents an unexpected file in a core-owned
directory from silently surviving a successful release update.

Paths intended for local data or customization are explicitly preserved. The
self-contained updater itself is preserved while it is running and was already
installed from the authenticated target package by the preparation step.

## Building an updater-compatible release

Install production Composer dependencies first:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Then build with the release private key:

```bash
composer build-release -- --private-key=/secure/path/admidio-release-private.pem
```

or directly:

```bash
php build-release.php --private-key=/secure/path/admidio-release-private.pem
```

The private key is never copied into the package. If it is encrypted, put its
passphrase in the `ADMIDIO_RELEASE_KEY_PASSPHRASE` environment variable.

The build creates:

```text
build/admidio-<version>.zip
build/admidio-<version>.release.json
build/admidio-<version>.release.json.sig
```

The ZIP includes production `vendor/` dependencies, the signed internal file
manifest, and the self-contained updater.

## Git master

The development checkbox downloads GitHub's `master.zip`. That archive is not
treated as a signed release:

- no unexpected or obsolete files are deleted;
- `adm_my_files`, local customization files, and the running updater are preserved;
- the update is refused if `composer.lock` changed while the archive has no bundled
  `vendor/autoload.php`;
- the warning is shown before the package is prepared and again in the isolated updater.

A CI-built master installation archive containing `vendor/` is preferable if master
updates are intended for more than development systems.
