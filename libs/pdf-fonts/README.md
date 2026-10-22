# PDF core font metrics

The native PDF engine uses JSON font metrics from `tecnickcom/tc-lib-pdf-font`. Composer
installs the importer but does not include generated font assets. These bundled
Core 14 metrics let web, CLI and release-archive exports run without downloading
or generating fonts at runtime, or requiring a writable vendor directory.

Source: https://github.com/tecnickcom/tc-font-mirror/tree/2.4.0/core
License: `core/LICENSE` (Adobe AFM redistribution notice).
The JSON files retain the original copyright and trademark notices.

Generated with `tecnickcom/tc-lib-pdf-font` 4.3.3. To regenerate, download the
`.afm` files from the pinned source and import each through the project autoloader:

```php
new \Com\Tecnick\Pdf\Font\Import($afmPath, $outputDirectory, '', $encoding);
```

Use `cp1252` for Courier, Helvetica and Times variants, `symbol` for Symbol,
and an empty encoding for ZapfDingbats. The output directory must exist and
end with a directory separator; the importer requires that the output files
do not exist yet. Preserve the `_notice` and `_copyright` metadata in each
JSON file when regenerating. The fonts are the same standard PDF families used
by the previous integration; they do not provide full Unicode glyph coverage.
