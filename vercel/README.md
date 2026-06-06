# Vercel Deployment — Ghana Council e.V.

This folder contains all Vercel-specific configuration and build scripts
for deploying the GCM application to [www.ghanacouncil-nrw.de](https://www.ghanacouncil-nrw.de).

## Files

| File | Purpose |
|------|---------|
| `build.php` | Build-time script that generates `adm_my_files/config.php` from Vercel environment variables |

The root `vercel.json` controls deployment settings and references this folder.

## Required Vercel Environment Variables

Set these as **secrets** in your Vercel project dashboard (Settings → Environment Variables):

| Variable | Description |
|----------|-------------|
| `DB_HOST` | Database server hostname |
| `DB_PORT` | Database port (optional) |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |

## Deploy Steps

1. Connect the `sandydiv3r/gcm` GitHub repository to a new Vercel project.
2. Add the environment variables above.
3. Deploy — Vercel will run `composer install` and `vercel/build.php` automatically.

> The generated `adm_my_files/config.php` is not committed to git.
> It is produced fresh on every Vercel build from the secrets above.
