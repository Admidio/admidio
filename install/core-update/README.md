# Admidio self-contained core updater

The normal settings page prepares an update and copies this directory from the selected
package before any normal Admidio core file is replaced. `index.php` and `CoreUpdater.php`
therefore use only PHP built-ins and remain usable while `src/`, `vendor/`, `system/`,
`modules/`, themes, and the normal installer are updated in place.

## Structure and access

- `lib/` holds the classes of the updater (namespace `AdmidioCoreUpdater`), loaded by
  `lib/bootstrap.php` without an autoloader. They read `adm_my_files/config.php`, connect
  with plain PDO and need nothing else. `install/update.php` loads the same classes, so the
  state of an update and the updater cookie have one implementation.
- Every update has a workspace `adm_my_files/core-update/<update id>/` with `state.json`
  (versioned by `schemaVersion`, written atomically, the version before kept as
  `state.json.previous`). `active.json` names the unfinished update; `update.lock` serialises
  the requests.
- Only an administrator gets in. As long as no Admidio file has been changed (states up to
  `ready`), that is the Admidio login: `index.php` includes `system/common.php` of the
  installed release, whatever 5.x release it is, and the settings page simply links to
  `install/core-update/`. From maintenance mode on Admidio cannot be used: the browser that
  started the installation carries the updater cookie `<cookie prefix>_CORE_UPDATE`, and the
  updater's own login (password, security code when two-factor authentication is set up,
  membership in a role with administrator rights) is the fallback for another device or a
  lost cookie. Five failed logins within 15 minutes block it for 15 minutes; every attempt is
  written to `adm_my_files/core-update/login.log`. A cookie expires one day after its last
  request and is revoked when the update is over.
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

## Release information and phase A

Every channel publishes, below the channel base URL (`Config::CHANNEL_BASE_URL`, final URLs
still open):

```text
release-keys.json(.sig)          signed by the root key
<channel>/release.json(.sig)     signed by a key of the channel
```

`release.json` names the release:

```json
{
  "schema": 1,
  "channel": "stable",
  "version": "5.1.0",
  "versionText": "5.1.0",
  "packageUrl": ["https://.../admidio-5.1.0.zip", "https://mirror/.../admidio-5.1.0.zip"],
  "packageSha256": "<sha256 of the ZIP>",
  "packageSize": 12345678,
  "manifestSha256": "<sha256 of install/update-manifest.json>",
  "minimumSourceVersion": "5.0.0",
  "minimumPhpVersion": "8.2",
  "releasedAt": "2026-10-08T12:00:00Z"
}
```

The package contains `install/update-manifest.json(.sig)` with `version`, `managedDirectories`,
`managedRootFiles`, `preservePaths`, `requirements` (`php`, `extensions`), `totalSize` and
`files` (path => SHA-256). Signatures are base64 RSA-SHA256 over the exact file bytes.

Phase A (`PhaseA`) runs in the installed updater: channel choice, key statement, descriptor,
version rule and the checks P1-P10 (`Preflight`), then the confirmation, the download in range
requests of a few seconds each, the verification of package and manifest, and the copy of the
updater of the new release over this one. It ends in state `prepared`; phase B continues there.

Because phase A replaces `install/core-update/` with the updater of the release it installs, a
test release must be built from the current working tree, or it overwrites newer code there.

## Building an updater-compatible release

> This describes the release builder of the first implementation; it is replaced in step 8 of
> the concept and does not produce the formats above yet.

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
