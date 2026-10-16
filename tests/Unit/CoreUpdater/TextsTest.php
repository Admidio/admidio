<?php
/**
 * The texts of the updater and where they come from.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;

final class TextsTest extends AdmidioTestCase
{
    private string $directory = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/adm_updater_texts_' . uniqid();
        mkdir($this->directory . '/updater/texts', 0777, true);
        mkdir($this->directory . '/root/languages', 0777, true);
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * Write an Admidio language file with the given strings.
     * @param array<string,string> $strings name => raw text as it stands in the XML
     */
    private function writeLanguageFile(string $code, array $strings): void
    {
        $xml = "<?xml version='1.0' encoding='UTF-8'?>\n<resources>\n";
        foreach ($strings as $name => $text) {
            $xml .= '  <string name="' . $name . '">' . $text . "</string>\n";
        }
        file_put_contents($this->directory . '/root/languages/' . $code . '.xml', $xml . "</resources>\n");
    }

    /**
     * @testdox The updater texts of a language file become plain text, all others are left out
     */
    public function testLanguageFileConversion(): void
    {
        $this->writeLanguageFile('en', array(
            'SYS_CORE_UPDATE_TITLE' => 'Admidio\\\'s update',
            'SYS_CORE_UPDATE_READY' => 'Line one\\nline two with a \\"quote\\" &amp; more',
            'SYS_SAVE' => 'Save'
        ));

        $this->assertSame(array(
            'SYS_CORE_UPDATE_TITLE' => 'Admidio\'s update',
            'SYS_CORE_UPDATE_READY' => "Line one\nline two with a \"quote\" & more"
        ), U\readLanguageFile($this->directory . '/root/languages/en.xml'));
    }

    /**
     * @testdox A text the language lacks comes from its base language and then from English
     */
    public function testFallbackChain(): void
    {
        $this->writeLanguageFile('en', array(
            'SYS_CORE_UPDATE_TITLE' => 'Update',
            'SYS_CORE_UPDATE_READY' => 'Ready',
            'SYS_CORE_UPDATE_START' => 'Start'
        ));
        $this->writeLanguageFile('de', array('SYS_CORE_UPDATE_TITLE' => 'Aktualisierung', 'SYS_CORE_UPDATE_READY' => 'Bereit'));
        $this->writeLanguageFile('de-DE', array('SYS_CORE_UPDATE_TITLE' => 'Aktualisierung (Sie)'));

        $texts = U\loadTexts($this->directory . '/updater', $this->directory . '/root', 'de-DE');

        $this->assertSame('de-DE', $texts['language']);
        $this->assertSame('Aktualisierung (Sie)', U\text($texts, 'SYS_CORE_UPDATE_TITLE'));
        $this->assertSame('Bereit', U\text($texts, 'SYS_CORE_UPDATE_READY'));
        $this->assertSame('Start', U\text($texts, 'SYS_CORE_UPDATE_START'));
    }

    /**
     * @testdox The texts the builder wrote are preferred to the language files
     *
     * An older installation's language files lack the keys of the new updater, so the updater
     * brings its own copy.
     */
    public function testBuilderFilesArePreferred(): void
    {
        $this->writeLanguageFile('en', array('SYS_CORE_UPDATE_TITLE' => 'From the language file'));
        file_put_contents($this->directory . '/updater/texts/en.json',
            json_encode(array('SYS_CORE_UPDATE_TITLE' => 'From the updater', 'SYS_CORE_UPDATE_START' => 'Start')));

        $texts = U\loadTexts($this->directory . '/updater', $this->directory . '/root', 'en');

        $this->assertSame('From the updater', U\text($texts, 'SYS_CORE_UPDATE_TITLE'));
        $this->assertSame('Start', U\text($texts, 'SYS_CORE_UPDATE_START'));
    }

    /**
     * @testdox A language code that is no language code falls back to English
     */
    public function testInvalidLanguage(): void
    {
        $this->writeLanguageFile('en', array('SYS_CORE_UPDATE_TITLE' => 'Update'));

        $texts = U\loadTexts($this->directory . '/updater', $this->directory . '/root', '../../etc/passwd');

        $this->assertSame('en', $texts['language']);
        $this->assertSame('Update', U\text($texts, 'SYS_CORE_UPDATE_TITLE'));
    }

    /**
     * @testdox Placeholders are filled, and a missing text shows its key
     */
    public function testText(): void
    {
        $texts = array('language' => 'en', 'texts' => array('SYS_CORE_UPDATE_SUCCESSFUL' => 'Updated from #VAR1# to #VAR2#.'));

        $this->assertSame('Updated from 5.0.14 to 5.1.0.', U\text($texts, 'SYS_CORE_UPDATE_SUCCESSFUL', array('5.0.14', '5.1.0')));
        $this->assertSame('SYS_CORE_UPDATE_UNKNOWN', U\text($texts, 'SYS_CORE_UPDATE_UNKNOWN'));
        $this->assertTrue(U\isTextKey('SYS_CORE_UPDATE_LOGIN_FAILED'));
        $this->assertFalse(U\isTextKey('SYS_SAVE'));
        $this->assertFalse(U\isTextKey('The database could not be reached.'));
    }

    /**
     * @testdox Every updater text the code uses exists in English and in both German variants
     */
    public function testEveryUsedTextExists(): void
    {
        $root = dirname(__DIR__, 3);
        $used = array();
        foreach (array('install/core-update', 'install/update.php', 'src/InstallationUpdate', 'src/Preferences', 'src/UI/Presenter', 'modules') as $path) {
            $files = is_file($root . '/' . $path) ? array($root . '/' . $path) : array_keys(iterator_to_array(
                new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $path)), '/\.php$/')
            ));
            foreach ($files as $file) {
                preg_match_all('/SYS_CORE_UPDATE_[A-Z0-9_]+/', (string)file_get_contents($file), $matches);
                $used = array_merge($used, $matches[0]);
            }
        }
        // The names of the steps are put together from SYS_CORE_UPDATE_STEP_ and the step.
        foreach (array_merge(array_keys(U\UPDATE_STEPS), array('restore')) as $step) {
            $used[] = 'SYS_CORE_UPDATE_STEP_' . strtoupper($step);
        }
        $used = array_unique(array_filter($used, static fn(string $key): bool => !str_ends_with($key, '_')));
        sort($used);
        $this->assertNotEmpty($used);

        foreach (array('en', 'de', 'de-DE') as $language) {
            $defined = array_keys(U\readLanguageFile($root . '/languages/' . $language . '.xml'));
            $this->assertSame(array(), array_values(array_diff($used, $defined)), 'missing in ' . $language . '.xml');
        }
    }
}
