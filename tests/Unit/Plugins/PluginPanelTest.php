<?php
/**
 * The preferences panel extension point. The hook engine is the real one; the presenter is a
 * stand-in, because PluginPanel only hands it to the callback and never touches it.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use Admidio\UI\Presenter\PreferencesPresenter;
use ReflectionClass;

final class PluginPanelTest extends PluginTestCase
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
     * @testdox Preferences without plugins ask for panels and get none
     */
    public function testNoPanels(): void
    {
        $this->assertSame(array(), PluginPanel::collect());
    }

    /**
     * @testdox A plugin contributes a panel by adding it to the filtered list
     */
    public function testPanelIsCollected(): void
    {
        $this->declare(array(
            'id' => 'who_is_online',
            'title' => 'Who is online',
            'icon' => 'bi-building-fill-check',
            'group' => PluginPanel::GROUP_OVERVIEW,
            'sequence' => 8,
            'create' => static fn(): string => '<form></form>'
        ));

        $panel = PluginPanel::get('who_is_online');

        $this->assertNotNull($panel);
        $this->assertSame('Who is online', $panel['title']);
        $this->assertSame('bi-building-fill-check', $panel['icon']);
        $this->assertSame(PluginPanel::GROUP_OVERVIEW, $panel['group']);
        $this->assertSame(8, $panel['sequence']);
        $this->assertFalse($panel['subcards']);
    }

    /**
     * @testdox A panel is built by its own plugin, and only when it is opened
     */
    public function testPanelIsBuiltByThePlugin(): void
    {
        $built = 0;
        $this->declare(array(
            'id' => 'hello',
            'create' => function (PreferencesPresenter $presenter) use (&$built): string {
                ++$built;
                return '<form id="hello"></form>';
            }
        ));

        $this->assertSame(0, $built, 'declaring a panel does not build it');
        $this->assertSame('<form id="hello"></form>', PluginPanel::create('hello', $this->presenter()));
        $this->assertSame(1, $built);
    }

    /**
     * @testdox A loaded plugin that declared settings but no panel gets one from its manifest
     *
     * Every setting is declared with a type, a label and a description, so a plugin that wants
     * nothing more should not have to write a presenter to make its settings reachable.
     */
    public function testSettingsWithoutAPanelGetAGeneratedOne(): void
    {
        PluginLoader::load(PluginRegistry::get('hello'));

        $panel = PluginPanel::get('hello');

        $this->assertNotNull($panel, 'the plugin declares settings, so it gets a panel');
        $this->assertSame('Hello', $panel['title']);
        $this->assertSame('bi-emoji-smile', $panel['icon']);
        $this->assertSame(PluginPanel::GROUP_EXTENSIONS, $panel['group']);
        $this->assertIsCallable($panel['create']);
    }

    /**
     * @testdox A plugin that declared a panel itself keeps it instead of getting a generated one
     */
    public function testDeclaredPanelWinsOverTheGeneratedOne(): void
    {
        $this->declare(array('id' => 'hello', 'title' => 'Written by hand', 'create' => static fn(): string => '<form></form>'));
        PluginLoader::load(PluginRegistry::get('hello'));

        $panels = array_filter(PluginPanel::collect(), static fn(array $panel): bool => $panel['id'] === 'hello');

        $this->assertCount(1, $panels, 'the plugin must not end up with two panels');
        $this->assertSame('Written by hand', array_values($panels)[0]['title']);
    }

    /**
     * @testdox A plugin without settings gets no generated panel
     */
    public function testNoSettingsMeansNoGeneratedPanel(): void
    {
        PluginLoader::load(PluginRegistry::get('no-entry'));

        $this->assertNull(PluginPanel::get('no_entry'));
    }

    /**
     * @testdox A panel no plugin declared cannot be built
     */
    public function testUnknownPanelIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_INVALID_PAGE_VIEW');

        PluginPanel::create('does_not_exist', $this->presenter());
    }

    /**
     * @testdox The panels are ordered by sequence, and by ID where the sequence is equal
     */
    public function testPanelsAreOrdered(): void
    {
        $this->declare(
            array('id' => 'zulu', 'sequence' => 10, 'create' => static fn(): string => ''),
            array('id' => 'alpha', 'sequence' => 10, 'create' => static fn(): string => ''),
            array('id' => 'first', 'sequence' => 1, 'create' => static fn(): string => ''),
            array('id' => 'nosequence', 'create' => static fn(): string => '')
        );

        $this->assertSame(
            array('first', 'alpha', 'zulu', 'nosequence'),
            array_column(PluginPanel::collect(), 'id')
        );
    }

    /**
     * @testdox Each of the two extension tabs gets the panels that belong to it
     */
    public function testPanelsAreGrouped(): void
    {
        $this->declare(
            array('id' => 'birthday', 'group' => PluginPanel::GROUP_OVERVIEW, 'create' => static fn(): string => ''),
            array('id' => 'inventory', 'group' => PluginPanel::GROUP_EXTENSIONS, 'create' => static fn(): string => ''),
            // A panel that names no tab, or names one that does not exist, belongs to the plugins.
            array('id' => 'plain', 'create' => static fn(): string => ''),
            array('id' => 'nonsense', 'group' => 'somewhere_else', 'create' => static fn(): string => '')
        );

        $this->assertSame(
            array('birthday'),
            array_column(PluginPanel::inGroup(PluginPanel::GROUP_OVERVIEW), 'id')
        );
        $this->assertSame(
            array('inventory', 'nonsense', 'plain'),
            array_column(PluginPanel::inGroup(PluginPanel::GROUP_EXTENSIONS), 'id')
        );
    }

    /**
     * @testdox An entry without an ID or without a usable callback is not a panel and is dropped
     */
    public function testIncompletePanelIsDropped(): void
    {
        $this->declare(
            array('id' => 'nocallback'),
            array('create' => static fn(): string => 'no id'),
            array('id' => 'notcallable', 'create' => 'AdmidioPlugin\\Nothing::atAll'),
            array('id' => '!!!', 'create' => static fn(): string => 'no usable id left'),
            array('id' => 'good', 'create' => static fn(): string => 'ok')
        );

        $this->assertSame(array('good'), array_column(PluginPanel::collect(), 'id'));
    }

    /**
     * @testdox A plugin ID becomes a panel ID the preferences page can put into a URL
     */
    public function testNormalizeId(): void
    {
        $this->assertSame('who_is_online', PluginPanel::normalizeId('who-is-online'));
        $this->assertSame('latest_documents_files', PluginPanel::normalizeId('Latest-Documents-Files'));
        $this->assertSame('birthday', PluginPanel::normalizeId('birthday'));
        $this->assertSame('etc', PluginPanel::normalizeId('../../etc'), 'nothing but the safe characters survives');
        $this->assertSame('', PluginPanel::normalizeId('../..'));
    }

    /**
     * @testdox The plugins are asked for their panels once per request
     */
    public function testPanelsAreCollectedOnce(): void
    {
        $asked = 0;
        Hooks::addFilter(PluginPanel::HOOK, static function (array $panels) use (&$asked): array {
            ++$asked;
            $panels[] = array('id' => 'hello', 'create' => static fn(): string => '');
            return $panels;
        });

        PluginPanel::collect();
        PluginPanel::collect();
        PluginPanel::get('hello');

        $this->assertSame(1, $asked);
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

    /**
     * PluginPanel hands the presenter to the callback and does nothing else with it, so an object of
     * the right type without a constructed page is enough here.
     */
    private function presenter(): PreferencesPresenter
    {
        return (new ReflectionClass(PreferencesPresenter::class))->newInstanceWithoutConstructor();
    }
}
