<?php
/**
 * The merge that puts plugin panels into the tabs of the preferences page.
 *
 * The presenter is created without its constructor, because the merge reads nothing but its own tab
 * array and PluginPanel - building the page itself would need a database, a session and a theme.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use Admidio\UI\Presenter\PreferencesPresenter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class PreferencesSectionTest extends PluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Hooks::reset();
        PluginPanel::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        PluginPanel::reset();

        parent::tearDown();
    }

    /**
     * @testdox A plugin takes its place among the core panels of the tab it named
     *
     * This is why the core panels carry a sequence. A module that used to be part of the core has to
     * be able to keep the position it had once it becomes a plugin, rather than being appended after
     * everything that is still core.
     */
    public function testPluginPanelIsPlacedBetweenCorePanels(): void
    {
        $this->declare(array(
            'id' => 'weblinks_plus',
            'section' => PluginPanel::SECTION_CONTENT,
            'sequence' => 25,
            'create' => static fn(): string => ''
        ));

        $tabs = $this->merge($this->contentTab());

        $this->assertSame(
            array('events', 'documents_files', 'weblinks_plus', 'photos'),
            array_column($tabs[0]['panels'], 'id')
        );
    }

    /**
     * @testdox A panel that names no tab lands in the one for plugins, not in a core tab
     */
    public function testPanelWithoutASectionLandsInTheDefaultTab(): void
    {
        $this->declare(array('id' => 'stray', 'create' => static fn(): string => ''));

        $tabs = $this->merge($this->contentTab(), $this->tab(PluginPanel::SECTION_DEFAULT));

        $this->assertSame(
            array('events', 'documents_files', 'photos'),
            array_column($tabs[0]['panels'], 'id'),
            'the core tab is untouched'
        );
        $this->assertSame(array('stray'), array_column($tabs[1]['panels'], 'id'));
    }

    /**
     * @testdox A tab no plugin named keeps exactly the panels it had
     */
    public function testTabWithoutPluginPanelsIsUnchanged(): void
    {
        $tabs = $this->merge($this->contentTab(), $this->tab(PluginPanel::SECTION_OVERVIEW));

        $this->assertSame(
            array('events', 'documents_files', 'photos'),
            array_column($tabs[0]['panels'], 'id')
        );
        $this->assertSame(array(), $tabs[1]['panels'], 'and an empty one stays empty, so show() drops it');
    }

    /**
     * Run the real merge over the given tabs and hand back what it produced.
     * @param array<int,array<string,mixed>> $tabs
     * @return array<int,array<string,mixed>>
     */
    private function merge(array ...$tabs): array
    {
        $presenter = (new ReflectionClass(PreferencesPresenter::class))->newInstanceWithoutConstructor();

        $property = new ReflectionProperty(PreferencesPresenter::class, 'preferenceTabs');
        $property->setAccessible(true);
        $property->setValue($presenter, $tabs);

        $method = new ReflectionMethod(PreferencesPresenter::class, 'addPluginPanels');
        $method->setAccessible(true);
        $method->invoke($presenter);

        return $property->getValue($presenter);
    }

    /**
     * The Contents tab with three of its core panels, spaced so that a plugin fits between them.
     * @return array<string,mixed>
     */
    private function contentTab(): array
    {
        return array(
            'key' => PluginPanel::SECTION_CONTENT,
            'label' => 'Contents',
            'panels' => array(
                array('id' => 'events', 'title' => 'Events', 'icon' => 'x', 'subcards' => false, 'sequence' => 10),
                array('id' => 'documents_files', 'title' => 'Documents', 'icon' => 'x', 'subcards' => false, 'sequence' => 20),
                array('id' => 'photos', 'title' => 'Photos', 'icon' => 'x', 'subcards' => false, 'sequence' => 40)
            )
        );
    }

    /**
     * A tab of that section with no core panels of its own.
     * @param string $section
     * @return array<string,mixed>
     */
    private function tab(string $section): array
    {
        return array('key' => $section, 'label' => $section, 'panels' => array());
    }

    /**
     * Register the given panels the way a plugin entry file does.
     * @param array<string,mixed> ...$panels
     */
    private function declare(array ...$panels): void
    {
        Hooks::addFilter(PluginPanel::HOOK, static function (array $collected) use ($panels): array {
            return array_merge($collected, $panels);
        });
    }
}
