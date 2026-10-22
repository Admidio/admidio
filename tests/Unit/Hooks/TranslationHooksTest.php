<?php
/**
 * The translation hooks. The real `Language` is executed against the real `languages/*.xml` of this
 * checkout, so the cache, the fallback to the reference language and the placeholders are the real
 * ones. Only the four path constants of the Admidio bootstrap are defined here.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Language;
use Admidio\Tests\Support\AdmidioTestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class TranslationHooksTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $admidioRoot = realpath(__DIR__ . '/../../..');
        if (!defined('ADMIDIO_PATH')) {
            define('ADMIDIO_PATH', $admidioRoot);
        }
        if (!defined('FOLDER_DATA')) {
            // Derived the same way tests/bootstrap-admidio.php derives it - not a literal
            // '/adm_my_files' - so that whichever bootstrap runs first in composer test:all, the
            // Integration/CLI suites still see the TEST_FILES_PATH-checked test tree and not the
            // real checkout's data folder.
            require_once dirname(__DIR__, 2) . '/env.php';
            admidioTestLoadEnvironment($admidioRoot . '/.env.test');
            define('FOLDER_DATA', admidioTestDataFolder($admidioRoot));
        }
        if (!defined('FOLDER_LANGUAGES')) {
            define('FOLDER_LANGUAGES', '/languages');
        }
        if (!defined('FOLDER_PLUGINS')) {
            define('FOLDER_PLUGINS', '/plugins');
        }

        // Language::get() logs its lookup time through getExecutionTime() and $gLogger unconditionally.
        require_once ADMIDIO_PATH . '/system/bootstrap/function.php';
        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    private function aLanguage(string $language = 'de'): Language
    {
        Hooks::reset();

        return new Language($language);
    }

    public function testATextOfTheLanguageFileIsReturnedAsItIsAndAnUnknownOneIsMarkedUndefined(): void
    {
        $l10n = $this->aLanguage();
        $this->assertSame('Speichern', $l10n->get('SYS_SAVE'), 'a text of the language file is returned as it is');
        $this->assertSame('#ZZZ_NOT_THERE#', $l10n->get('ZZZ_NOT_THERE'), 'a text nobody has is marked as undefined');
    }

    public function testTheMissingResolverSuppliesATextNoFileHas(): void
    {
        $l10n = $this->aLanguage();
        $asked = array();
        Hooks::addResolver('translation_missing', function (string $textId, string $language) use (&$asked) {
            $asked[] = $textId . '/' . $language;
            return ($textId === 'ZZZ_PLUGIN_TEXT') ? 'Von einem Plugin' : null;
        });

        $this->assertSame('Von einem Plugin', $l10n->get('ZZZ_PLUGIN_TEXT'));
        $this->assertSame(array('ZZZ_PLUGIN_TEXT/de'), $asked, 'it is told which text and which language');

        $l10n->get('ZZZ_PLUGIN_TEXT');
        $l10n->get('ZZZ_PLUGIN_TEXT');
        $this->assertCount(1, $asked, 'the answer is cached, so the resolver is asked once');

        $this->assertSame('#ZZZ_STILL_NOT_THERE#', $l10n->get('ZZZ_STILL_NOT_THERE'), 'a resolver that has no answer changes nothing');

        // the cache lives in the session, so a plugin whose texts changed clears it the way Admidio does
        $restored = unserialize(serialize($l10n));
        $this->assertSame('Von einem Plugin', $restored->get('ZZZ_PLUGIN_TEXT'), 'and it survives the session');
    }

    public function testAResolvedTextStillGetsItsPlaceholders(): void
    {
        $l10n = $this->aLanguage();
        Hooks::addResolver('translation_missing', function () {
            return 'Angelegt von #VAR1# am #VAR2#';
        });

        $this->assertSame(
            'Angelegt von John Doe am 2026-08-26',
            $l10n->get('ZZZ_WITH_PARAMS', array('John Doe', '2026-08-26'))
        );
    }

    public function testTranslationUnresolvedReportsWhatNobodyCouldAnswer(): void
    {
        $l10n = $this->aLanguage();
        $unresolved = array();
        Hooks::addAction('translation_unresolved', function (string $textId, string $language) use (&$unresolved) {
            $unresolved[] = $textId . '/' . $language;
        });
        $l10n->get('ZZZ_NOBODY_HAS_THIS');

        $this->assertSame(array('ZZZ_NOBODY_HAS_THIS/de'), $unresolved);
    }

    public function testADiagnosticThatThrowsDoesNotTakeThePageWithIt(): void
    {
        $l10n = $this->aLanguage();
        Hooks::addAction('translation_unresolved', function () {
            throw new RuntimeException('the devhelper is broken');
        });

        $this->assertSame('#ZZZ_NOBODY_HAS_THIS#', $l10n->get('ZZZ_NOBODY_HAS_THIS'));
    }

    public function testATextTheLanguageHasDoesNotReportAFallback(): void
    {
        $l10n = $this->aLanguage('de');
        $fallbacks = array();
        Hooks::addAction('translation_fallback_used', function (string $textId, string $language, string $reference) use (&$fallbacks) {
            $fallbacks[] = $textId . ' ' . $language . '->' . $reference;
        });

        $this->assertSame('Speichern', $l10n->get('SYS_SAVE'));
        $this->assertSame(array(), $fallbacks);
    }

    public function testATextThatOnlyTheReferenceLanguageHasReportsTheFallback(): void
    {
        // A text that German does not have but English does. It is looked up rather than named, so
        // that the check keeps working when the translation catches up.
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
            $this->markTestSkipped('every text is translated into German, so the fallback cannot be exercised');
        }

        $l10n = $this->aLanguage('de');
        $fallbacks = array();
        Hooks::addAction('translation_fallback_used', function (string $textId, string $language, string $reference) use (&$fallbacks) {
            $fallbacks[] = $textId . ' ' . $language . '->' . $reference;
        });
        $text = $l10n->get($onlyInReference);

        $this->assertSame(array($onlyInReference . ' de->en'), $fallbacks);
        $this->assertNotSame('', $text);
        $this->assertNotSame('#' . $onlyInReference . '#', $text, 'the English text is what is shown');

        $l10n->get($onlyInReference);
        $this->assertCount(1, $fallbacks, 'the cache answers the second time, so it is reported once per session');
    }

    public function testTheReferenceLanguageItselfNeverReportsAFallback(): void
    {
        $l10n = $this->aLanguage('en');
        $fallbacks = array();
        Hooks::addAction('translation_fallback_used', function () use (&$fallbacks) {
            $fallbacks[] = 'reported';
        });
        $l10n->get('SYS_SAVE');

        $this->assertSame(array(), $fallbacks);
    }

    public function testTranslationTextFilterChangesATextAndLeavesOthersAlone(): void
    {
        $l10n = $this->aLanguage();
        Hooks::addFilter('translation_text', function (string $text, string $textId) {
            return ($textId === 'SYS_SAVE') ? 'Sichern' : $text;
        });

        $this->assertSame('Sichern', $l10n->get('SYS_SAVE'));
        $this->assertNotSame('Sichern', $l10n->get('SYS_CANCEL'));
        // it runs on every call and not only on the first
        $this->assertSame('Sichern', $l10n->get('SYS_SAVE'));
    }

    public function testTheFilterSeesThePlaceholdersNotTheFilledInValues(): void
    {
        $l10n = $this->aLanguage();
        Hooks::addFilter('translation_text', function (string $text) {
            return str_replace('#VAR1#', '<b>#VAR1#</b>', $text);
        });
        $created = $l10n->get('SYS_CREATED_BY_AND_AT', array('John Doe', '2026-08-26'));

        $this->assertStringContainsString('<b>John Doe</b>', $created);
    }

    public function testATranslationTextFilterMustAnswerWithAString(): void
    {
        $l10n = $this->aLanguage();
        Hooks::addFilter('translation_text', function () {
            return array('not a text');
        });

        $refused = false;
        try {
            $l10n->get('SYS_SAVE');
        } catch (\Throwable $exception) {
            $refused = str_contains($exception->getMessage(), 'returned array instead of string');
        }
        $this->assertTrue($refused);
    }

    public function testTheCacheIsNotFilteredTheTextIs(): void
    {
        $l10n = $this->aLanguage();
        $l10n->get('SYS_SAVE');                       // fills the cache with the unfiltered text
        Hooks::addFilter('translation_text', function (string $text) {
            return strtoupper($text);
        });
        $this->assertSame('SPEICHERN', $l10n->get('SYS_SAVE'), 'a filter registered after the cache was filled still applies');

        Hooks::reset();
        $this->assertSame('Speichern', $l10n->get('SYS_SAVE'), 'and removing it gives the text back');
    }
}
