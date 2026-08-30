<?php
/**
 * The catalogue of plugins published for Admidio.
 *
 * Every case writes a real catalogue file and reads it back through the real class. The catalogue is
 * read with file_get_contents(), so a path on disk exercises the same code a URL would - what is not
 * exercised here is the network itself.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginStore;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginStoreTest extends PluginTestCase
{
    /**
     * Every temporary file a test created.
     * @var array<int,string>
     */
    private array $rubbish = array();

    /**
     * Where this test keeps the catalogue cache.
     */
    private string $cacheFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        // The cache is exercised for real, but never in the adm_my_files of the installation.
        $this->cacheFile = $this->temporaryFile('admidio-store-cache-', '.json');
        $this->rubbish[] = $this->cacheFile;
        PluginStore::setCacheFile($this->cacheFile);
        PluginStore::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            @unlink($path);
        }
        $this->rubbish = array();

        PluginStore::setUrl(null);
        PluginStore::setCacheFile(null);
        PluginStore::reset();

        parent::tearDown();
    }

    /**
     * @testdox A plugin whose release this Admidio satisfies is offered
     */
    public function testMatchingReleaseIsOffered(): void
    {
        $this->catalogue(array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $available = PluginStore::getAvailable();

        $this->assertCount(1, $available);
        $this->assertSame('dummy-plugin', $available[0]['id']);
        $this->assertSame('1.0.0', $available[0]['release']['version']);
    }

    /**
     * @testdox A plugin for an older Admidio is not offered
     *
     * This is what keeps the plugins of the previous runtime out of the store: they are listed in
     * the catalogue with a constraint this Admidio does not satisfy, so nothing that cannot work is
     * ever installable.
     */
    public function testReleaseForAnotherAdmidioIsNotOffered(): void
    {
        $this->catalogue(array(
            $this->entry('old-plugin', array(
                array('version' => '4.0.4', 'requires' => array('admidio' => '>=5.0 <5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertSame(array(), PluginStore::getAvailable());
    }

    /**
     * @testdox The newest release this Admidio satisfies is the one offered
     */
    public function testNewestMatchingReleaseWins(): void
    {
        $this->catalogue(array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/1.zip'),
                array('version' => '2.3.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/2.zip'),
                array('version' => '9.0.0', 'requires' => array('admidio' => '>=6.0'), 'download' => 'https://example.org/9.zip')
            ))
        ));

        $available = PluginStore::getAvailable();

        $this->assertCount(1, $available);
        $this->assertSame('2.3.0', $available[0]['release']['version']);
    }

    /**
     * @testdox A plugin that is already on disk is not offered again
     */
    public function testInstalledPluginIsNotOffered(): void
    {
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '9.9.9', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertNotNull(PluginRegistry::get('hello'), 'the fixture plugin has to be there');
        $this->assertSame(array(), PluginStore::getAvailable());
    }

    /**
     * @testdox An entry without a usable release or a usable ID is skipped
     */
    public function testUnusableEntriesAreSkipped(): void
    {
        $this->catalogue(array(
            array('id' => 'Not An Id', 'releases' => array(array('version' => '1.0.0', 'download' => 'https://example.org/d.zip'))),
            array('id' => 'no-releases', 'releases' => array()),
            array('id' => 'no-download', 'releases' => array(array('version' => '1.0.0'))),
            array('id' => 'no-version', 'releases' => array(array('download' => 'https://example.org/d.zip'))),
            $this->entry('good-one', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertSame(array('good-one'), array_column(PluginStore::getAvailable(), 'id'));
    }

    /**
     * @testdox A catalogue that cannot be reached leaves the store empty and says why
     */
    public function testUnreachableCatalogueIsReported(): void
    {
        PluginStore::setUrl(sys_get_temp_dir() . '/admidio-no-such-catalogue-' . uniqid() . '.json');

        $this->assertSame(array(), PluginStore::getAvailable());
        $this->assertStringContainsString('could not be read', (string)PluginStore::getError());
    }

    /**
     * @testdox A catalogue that is not JSON leaves the store empty and says why
     */
    public function testMalformedCatalogueIsReported(): void
    {
        $this->writeCatalogue('{ "format": 1, oh dear');

        $this->assertSame(array(), PluginStore::getAvailable());
        $this->assertStringContainsString('not valid JSON', (string)PluginStore::getError());
    }

    /**
     * @testdox A catalogue in a format this Admidio does not know is refused rather than guessed at
     */
    public function testUnknownFormatIsRefused(): void
    {
        $this->writeCatalogue(json_encode(array('format' => 99, 'plugins' => array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ))));

        $this->assertSame(array(), PluginStore::getAvailable());
        $this->assertStringContainsString('format 99', (string)PluginStore::getError());
    }

    /**
     * @testdox The catalogue is read once per request
     */
    public function testCatalogueIsReadOncePerRequest(): void
    {
        $file = $this->writeCatalogue(json_encode(array('format' => 1, 'plugins' => array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ))));

        $this->assertCount(1, PluginStore::getAvailable());

        // What is on disk changes; what this request answers must not.
        file_put_contents($file, json_encode(array('format' => 1, 'plugins' => array())));

        $this->assertCount(1, PluginStore::getAvailable(), 'the catalogue must not be fetched again');
    }

    /**
     * One catalogue entry, with only what the store needs.
     * @param string $id
     * @param array<int,array<string,mixed>> $releases
     * @return array<string,mixed>
     */
    private function entry(string $id, array $releases): array
    {
        return array(
            'id' => $id,
            'name' => ucfirst($id),
            'description' => array('en' => 'A plugin for testing.'),
            'author' => 'The Admidio Team',
            'releases' => $releases
        );
    }

    /**
     * Write a catalogue of these entries and point the store at it.
     * @param array<int,array<string,mixed>> $entries
     * @return string The catalogue file.
     */
    private function catalogue(array $entries): string
    {
        return $this->writeCatalogue((string)json_encode(array(
            'format' => PluginStore::FORMAT,
            'updated' => '2026-08-30',
            'plugins' => $entries
        )));
    }

    /**
     * Write this text as the catalogue and point the store at it.
     * @param string $body
     * @return string The catalogue file.
     */
    private function writeCatalogue(string $body): string
    {
        $file = $this->temporaryFile('admidio-catalogue-', '.json');
        file_put_contents($file, $body);
        $this->rubbish[] = $file;

        PluginStore::setUrl($file);

        return $file;
    }

    /**
     * A path in the temporary directory that nothing else is using.
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    private function temporaryFile(string $prefix, string $suffix): string
    {
        return sys_get_temp_dir() . '/' . $prefix . uniqid('', true) . $suffix;
    }
}
