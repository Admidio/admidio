<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Language;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\UI\Component\DataTables;
use HtmlPage;
use Psr\Log\NullLogger;
use ReflectionProperty;

class PortugueseLocaleTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__, 2) . '/constants.php';
        require_once ADMIDIO_PATH . '/system/bootstrap/function.php';
        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    public static function locales(): array
    {
        return array(
            'Portugal' => array('pt-PT', 'pt-PT'),
            'legacy Portuguese' => array('pt', 'pt-PT'),
            'other Portuguese region' => array('pt-AO', 'pt-PT'),
            'Brazil' => array('pt-BR', 'pt-BR'),
            'lowercase Brazilian browser locale' => array('pt-br', 'pt-BR'),
        );
    }

    /** @dataProvider locales */
    public function testLanguageAndBrowserCodesResolveToTheCorrectPortugueseVariant(string $requested, string $expected): void
    {
        $language = new Language($requested);
        $this->assertSame($expected, $language->getLanguage());
        $this->assertSame('pt', $language->getLanguageIsoCode());
        $this->assertSame($expected === 'pt-PT' ? 'pt' : 'pt-BR', $language->getLanguageLibs());
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        try {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $requested . ',en;q=0.8';
            $this->assertSame($expected, Language::determineBrowserLanguage('en'));
        } finally {
            if ($header === null) {
                unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
            }
        }
    }

    public function testPortugalLoadsTheRenamedCoreCountryAndPluginFiles(): void
    {
        $language = new Language('pt-PT');
        $this->assertSame('Guardar', $language->get('SYS_SAVE'));
        $this->assertSame('Polónia', $language->getCountryName('POL'));
        $this->assertSame('Aniversários', $language->get('PLG_BIRTHDAY_HEADLINE'));
        $this->assertArrayHasKey('pt-PT', $language->getAvailableLanguages());
        $this->assertArrayNotHasKey('pt', $language->getAvailableLanguages());
        $this->assertSame('pt-PT', PreferenceDefinitions::normalize('system_language', 'pt'));
        $this->assertSame('pt-PT', PreferenceDefinitions::normalize('system_language', 'pt-PT'));
        $this->assertSame('pt-BR', PreferenceDefinitions::normalize('system_language', 'pt-BR'));
    }

    public function testAnExistingSessionWithTheLegacyCodeReloadsPortugueseInsteadOfEnglish(): void
    {
        $language = new Language('pt-PT');
        (new ReflectionProperty(Language::class, 'language'))->setValue($language, 'pt');
        (new ReflectionProperty(Language::class, 'textCache'))->setValue($language, array('SYS_SAVE' => 'old cached text'));
        (new ReflectionProperty(Language::class, 'languages'))->setValue($language, array('pt' => 'old language list'));
        $restored = unserialize(serialize($language));
        $this->assertSame('pt-PT', $restored->getLanguage());
        $this->assertSame('Guardar', $restored->get('SYS_SAVE'));
        $this->assertSame('Polónia', $restored->getCountryName('POL'));
        $this->assertArrayHasKey('pt-PT', $restored->getAvailableLanguages());
        $this->assertArrayNotHasKey('pt', $restored->getAvailableLanguages());
    }

    public static function dataTablesLocales(): array
    {
        return array(
            array('pt-PT', 'pt-pt'),
            array('pt', 'pt-pt'),
            array('pt-BR', 'pt-br'),
            array('de-DE', 'de'),
            array('en', 'en'),
        );
    }

    /** @dataProvider dataTablesLocales */
    public function testDataTablesLoadsTheRegionalAssetWithABaseLanguageFallback(string $locale, string $asset): void
    {
        $previousLanguage = $GLOBALS['gL10n'] ?? null;
        $previousSettings = $GLOBALS['gSettingsManager'] ?? null;
        $GLOBALS['gL10n'] = new Language($locale);
        $GLOBALS['gSettingsManager'] = new class {
            public function getString(string $name): string
            {
                return $name === 'system_date' ? 'd.m.Y' : 'H:i';
            }
        };
        try {
            $scripts = array();
            $page = $this->createMock(HtmlPage::class);
            $page->method('addJavascript')->willReturnCallback(static function ($script) use (&$scripts): void {
                $scripts[] = $script;
            });
            $table = new DataTables($page, 'locale_test');
            $table->createJavascript();
            $this->assertStringContainsString('/datatables/language/datatables.' . $asset . '.json', implode("\n", $scripts));
            $this->assertFileExists(ADMIDIO_PATH . '/libs/datatables/language/datatables.' . $asset . '.json');
        } finally {
            foreach (array('gL10n' => $previousLanguage, 'gSettingsManager' => $previousSettings) as $name => $value) {
                if ($value === null) {
                    unset($GLOBALS[$name]);
                } else {
                    $GLOBALS[$name] = $value;
                }
            }
        }
    }
}
