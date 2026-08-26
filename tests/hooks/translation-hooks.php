<?php
/**
 * The translation hooks. The real `Language` is executed against the real `languages/*.xml` of this
 * checkout, so the cache, the fallback to the reference language and the placeholders are the real
 * ones. Only the four path constants of the Admidio bootstrap are defined here.
 */
require __DIR__ . '/bootstrap.php';

define('ADMIDIO_PATH', realpath(__DIR__ . '/../..'));
define('FOLDER_DATA', '/adm_my_files');
define('FOLDER_LANGUAGES', '/languages');
define('FOLDER_PLUGINS', '/plugins');

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Language;

function aLanguage(string $language = 'de'): Language
{
    Hooks::reset();

    return new Language($language);
}

// ------------------------------------------------------------------------ nothing changes by itself

$l10n = aLanguage();
check('a text of the language file is returned as it is', $l10n->get('SYS_SAVE') === 'Speichern', $l10n->get('SYS_SAVE'));
check('a text nobody has is marked as undefined', $l10n->get('ZZZ_NOT_THERE') === '#ZZZ_NOT_THERE#', $l10n->get('ZZZ_NOT_THERE'));

// ---------------------------------------------------------------------------- the missing resolver

$l10n = aLanguage();
$asked = array();
Hooks::addResolver('translation_missing', function (string $textId, string $language) use (&$asked) {
    $asked[] = $textId . '/' . $language;
    return ($textId === 'ZZZ_PLUGIN_TEXT') ? 'Von einem Plugin' : null;
});

check('a resolver can supply a text that no file has', $l10n->get('ZZZ_PLUGIN_TEXT') === 'Von einem Plugin', $l10n->get('ZZZ_PLUGIN_TEXT'));
check('and it is told which text and which language', $asked === array('ZZZ_PLUGIN_TEXT/de'), implode(',', $asked));

$l10n->get('ZZZ_PLUGIN_TEXT');
$l10n->get('ZZZ_PLUGIN_TEXT');
check('the answer is cached, so the resolver is asked once', count($asked) === 1, (string)count($asked));

check('a resolver that has no answer changes nothing', $l10n->get('ZZZ_STILL_NOT_THERE') === '#ZZZ_STILL_NOT_THERE#');

// the cache lives in the session, so a plugin whose texts changed clears it the way Admidio does
$restored = unserialize(serialize($l10n));
check('and it survives the session', $restored->get('ZZZ_PLUGIN_TEXT') === 'Von einem Plugin', $restored->get('ZZZ_PLUGIN_TEXT'));

// ------------------------------------------------------------------- placeholders after the resolver

$l10n = aLanguage();
Hooks::addResolver('translation_missing', function () {
    return 'Angelegt von #VAR1# am #VAR2#';
});
check(
    'a resolved text still gets its placeholders',
    $l10n->get('ZZZ_WITH_PARAMS', array('John Doe', '2026-08-26')) === 'Angelegt von John Doe am 2026-08-26',
    $l10n->get('ZZZ_WITH_PARAMS', array('John Doe', '2026-08-26'))
);

// -------------------------------------------------------------------------------- the unresolved hook

$l10n = aLanguage();
$unresolved = array();
Hooks::addAction('translation_unresolved', function (string $textId, string $language) use (&$unresolved) {
    $unresolved[] = $textId . '/' . $language;
});
$l10n->get('ZZZ_NOBODY_HAS_THIS');
check('translation_unresolved reports what nobody could answer', $unresolved === array('ZZZ_NOBODY_HAS_THIS/de'), implode(',', $unresolved));

// a diagnostic must not be able to break the page
$l10n = aLanguage();
Hooks::addAction('translation_unresolved', function () {
    throw new \RuntimeException('the devhelper is broken');
});
check('and a diagnostic that throws does not take the page with it', $l10n->get('ZZZ_NOBODY_HAS_THIS') === '#ZZZ_NOBODY_HAS_THIS#');

// ------------------------------------------------------------------------------- the fallback hook

$l10n = aLanguage('de');
$fallbacks = array();
Hooks::addAction('translation_fallback_used', function (string $textId, string $language, string $reference) use (&$fallbacks) {
    $fallbacks[] = $textId . ' ' . $language . '->' . $reference;
});
check('a text that the language has does not report a fallback', $l10n->get('SYS_SAVE') === 'Speichern' && $fallbacks === array(), implode(',', $fallbacks));

// a text that German does not have but English does. It is looked up rather than named, so that the
// check keeps working when the translation catches up.
$onlyInReference = null;
$germanIds = array();
foreach (simplexml_load_file(ADMIDIO_PATH . '/languages/de.xml')->string as $string) {
    $germanIds[(string)$string['name']] = true;
}
foreach (simplexml_load_file(ADMIDIO_PATH . '/languages/en.xml')->string as $string) {
    if (!array_key_exists((string)$string['name'], $germanIds)) {
        $onlyInReference = (string)$string['name'];
        break;
    }
}

if ($onlyInReference === null) {
    check('every text is translated into German, so the fallback cannot be exercised', true, 'nothing to check');
} else {
    $l10n = aLanguage('de');
    $fallbacks = array();
    Hooks::addAction('translation_fallback_used', function (string $textId, string $language, string $reference) use (&$fallbacks) {
        $fallbacks[] = $textId . ' ' . $language . '->' . $reference;
    });
    $text = $l10n->get($onlyInReference);

    check(
        'a text that only the reference language has reports the fallback',
        $fallbacks === array($onlyInReference . ' de->en'),
        implode(',', $fallbacks) . ' for ' . $onlyInReference
    );
    check('and the English text is what is shown', $text !== '' && $text !== '#' . $onlyInReference . '#', $text);

    $l10n->get($onlyInReference);
    check('the cache answers the second time, so it is reported once per session', count($fallbacks) === 1, (string)count($fallbacks));
}

// an English installation must not report a fallback for every single text
$l10n = aLanguage('en');
$fallbacks = array();
Hooks::addAction('translation_fallback_used', function () use (&$fallbacks) {
    $fallbacks[] = 'reported';
});
$l10n->get('SYS_SAVE');
check('and neither does the reference language itself', $fallbacks === array(), implode(',', $fallbacks));

// ------------------------------------------------------------------------------------- the filter

$l10n = aLanguage();
Hooks::addFilter('translation_text', function (string $text, string $textId) {
    return ($textId === 'SYS_SAVE') ? 'Sichern' : $text;
});
check('translation_text changes a text', $l10n->get('SYS_SAVE') === 'Sichern', $l10n->get('SYS_SAVE'));
check('and leaves the others alone', $l10n->get('SYS_CANCEL') !== 'Sichern');

// it runs after the cache, so a second call is filtered as well
check('it runs on every call and not only on the first', $l10n->get('SYS_SAVE') === 'Sichern', $l10n->get('SYS_SAVE'));

// and it runs before the placeholders, so a filter sees the template
$l10n = aLanguage();
Hooks::addFilter('translation_text', function (string $text) {
    return str_replace('#VAR1#', '<b>#VAR1#</b>', $text);
});
$created = $l10n->get('SYS_CREATED_BY_AND_AT', array('John Doe', '2026-08-26'));
check('a filter sees the placeholders, not the filled-in values', str_contains($created, '<b>John Doe</b>'), $created);

// a text is a string
$l10n = aLanguage();
Hooks::addFilter('translation_text', function () {
    return array('not a text');
});
$refused = false;
try {
    $l10n->get('SYS_SAVE');
} catch (\Throwable $exception) {
    $refused = str_contains($exception->getMessage(), 'returned array instead of string');
}
check('a filter that does not answer with a string is refused', $refused);

// ------------------------------------------------------------- the cache is not filtered, the text is

$l10n = aLanguage();
$l10n->get('SYS_SAVE');                       // fills the cache with the unfiltered text
Hooks::addFilter('translation_text', function (string $text) {
    return strtoupper($text);
});
check('a filter registered after the cache was filled still applies', $l10n->get('SYS_SAVE') === 'SPEICHERN', $l10n->get('SYS_SAVE'));

Hooks::reset();
check('and removing it gives the text back', $l10n->get('SYS_SAVE') === 'Speichern', $l10n->get('SYS_SAVE'));

exit(testSummary());
