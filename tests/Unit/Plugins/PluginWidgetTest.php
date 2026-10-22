<?php
/**
 * The overview widget extension point. The hook engine is the real one; the page is a stand-in,
 * because collect() only passes it through to the callbacks and never touches it.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Tests\Unit\Plugins\Support\PluginSettingsDouble;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginWidgetTest extends PluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Hooks::reset();
        PluginWidget::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        PluginWidget::reset();

        parent::tearDown();
    }

    /**
     * @testdox An overview without plugins asks for widgets and gets none
     */
    public function testNoWidgets(): void
    {
        $this->assertSame(array(), PluginWidget::collect($this->page()));
    }

    /**
     * @testdox A plugin contributes a widget by adding it to the filtered list
     */
    public function testWidgetIsCollected(): void
    {
        Hooks::addFilter(PluginWidget::HOOK, static function (array $widgets): array {
            $widgets[] = array('id' => 'birthday', 'name' => 'Birthdays', 'sequence' => 20, 'html' => '<p>Ada</p>');
            return $widgets;
        });

        $this->assertSame(
            array(array('id' => 'birthday', 'name' => 'Birthdays', 'sequence' => 20, 'html' => '<p>Ada</p>')),
            PluginWidget::collect($this->page())
        );
    }

    /**
     * @testdox The widgets are ordered by sequence, and by ID where the sequence is equal
     */
    public function testWidgetsAreOrdered(): void
    {
        Hooks::addFilter(PluginWidget::HOOK, static function (array $widgets): array {
            $widgets[] = array('id' => 'zulu', 'sequence' => 10, 'html' => 'z');
            $widgets[] = array('id' => 'alpha', 'sequence' => 10, 'html' => 'a');
            $widgets[] = array('id' => 'first', 'sequence' => 1, 'html' => 'f');
            $widgets[] = array('id' => 'nosequence', 'html' => 'n');
            return $widgets;
        });

        $this->assertSame(
            array('first', 'alpha', 'zulu', 'nosequence'),
            array_column(PluginWidget::collect($this->page()), 'id')
        );
    }

    /**
     * @testdox A widget that decides not to appear simply does not add itself
     */
    public function testWidgetCanDecline(): void
    {
        Hooks::addFilter(PluginWidget::HOOK, static fn(array $widgets): array => $widgets);

        $this->assertSame(array(), PluginWidget::collect($this->page()));
    }

    /**
     * @testdox An entry without an ID or without html is not a widget and is dropped
     */
    public function testIncompleteWidgetIsDropped(): void
    {
        Hooks::addFilter(PluginWidget::HOOK, static function (array $widgets): array {
            $widgets[] = array('id' => 'nohtml');
            $widgets[] = array('html' => 'no id');
            $widgets[] = 'not an array';
            $widgets[] = array('id' => 'good', 'html' => 'ok');
            return $widgets;
        });

        $this->assertSame(array('good'), array_column(PluginWidget::collect($this->page()), 'id'));
    }

    /**
     * @testdox Every callback sees the widgets of the ones before it
     */
    public function testWidgetsPassThroughTheWholeChain(): void
    {
        Hooks::addFilter(PluginWidget::HOOK, static function (array $widgets): array {
            $widgets[] = array('id' => 'one', 'sequence' => 1, 'html' => '1');
            return $widgets;
        }, 10);
        Hooks::addFilter(PluginWidget::HOOK, static function (array $widgets): array {
            $widgets[] = array('id' => 'two', 'sequence' => 2, 'html' => (string)count($widgets));
            return $widgets;
        }, 20);

        $collected = PluginWidget::collect($this->page());

        $this->assertSame(array('one', 'two'), array_column($collected, 'id'));
        $this->assertSame('1', $collected[1]['html'], 'the second callback saw the first widget');
    }

    /**
     * @testdox register() declares the widget and adds the hook that renders it
     */
    public function testRegisterDeclaresAndRenders(): void
    {
        PluginWidget::register(
            PluginRegistry::get('hello'),
            static fn(): string => '<p>Ada</p>'
        );

        $this->assertSame(
            array('hello' => array(
                'id' => 'hello',
                'name' => 'Hello',
                'icon' => 'bi-emoji-smile',
                'sequencePreference' => 'hello_overview_sequence',
                'enabledPreference' => 'hello_plugin_enabled',
                'sequence' => PluginWidget::DEFAULT_SEQUENCE
            )),
            PluginWidget::getDeclarations()
        );
        $this->assertSame(
            array(array(
                'id' => 'hello',
                'name' => 'Hello',
                'sequence' => PluginWidget::DEFAULT_SEQUENCE,
                'html' => '<p>Ada</p>'
            )),
            PluginWidget::collect($this->page())
        );
    }

    /**
     * @testdox A registered widget takes its name, icon and preferences from the declaration
     */
    public function testRegisterAcceptsExplicitOptions(): void
    {
        PluginWidget::register(
            PluginRegistry::get('hello'),
            static fn(): string => 'x',
            array(
                'name' => 'Greetings',
                'icon' => 'bi-star',
                // latest-documents-files does exactly this: its preference is not named after it.
                'sequencePreference' => 'latest_documents_overview_sequence',
                'enabledPreference' => 'latest_documents_files_plugin_enabled',
                'sequence' => 3
            )
        );
        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble(array('latest_documents_overview_sequence' => '7'));

        $this->assertSame('Greetings', PluginWidget::getDeclarations()['hello']['name']);
        $this->assertSame('bi-star', PluginWidget::getDeclarations()['hello']['icon']);
        $this->assertSame(7, PluginWidget::collect($this->page())[0]['sequence'],
            'the position comes from the preference the plugin named');
    }

    /**
     * @testdox A registered widget is placed by its own preference, and by its default without one
     */
    public function testSequenceComesFromThePreference(): void
    {
        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble(array('who_is_online_overview_sequence' => '4'));

        $this->assertSame(4, PluginWidget::getSequence('who_is_online_overview_sequence', 8));
        $this->assertSame(8, PluginWidget::getSequence('birthday_overview_sequence', 8),
            'a preference without a row yet leaves the declared default in place');
    }

    /**
     * @testdox The access preference decides who sees a registered widget
     * @dataProvider accessValues
     */
    public function testAccessPreferenceIsHonoured(string $access, bool $validLogin, bool $expected): void
    {
        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble(array('hello_plugin_enabled' => $access));
        $GLOBALS['gValidLogin'] = $validLogin;

        PluginWidget::register(PluginRegistry::get('hello'), static fn(): string => 'x');

        $this->assertSame($expected, PluginWidget::isVisible('hello_plugin_enabled'));
        $this->assertSame($expected, PluginWidget::collect($this->page()) !== array());
        $this->assertArrayHasKey('hello', PluginWidget::getDeclarations(),
            'the declaration stays, so the preferences page can switch the widget on again');
    }

    /**
     * @return array<string,array<int,bool|string>>
     */
    public static function accessValues(): array
    {
        return array(
            'nobody' => array('0', true, false),
            'everybody, a guest' => array('1', false, true),
            'everybody, a member' => array('1', true, true),
            'registered users, a guest' => array('2', false, false),
            'registered users, a member' => array('2', true, true)
        );
    }

    /**
     * @testdox A registered widget with nothing to show does not appear
     */
    public function testRegisteredWidgetWithoutContentIsDropped(): void
    {
        PluginWidget::register(PluginRegistry::get('hello'), static fn(): string => '');

        $this->assertSame(array(), PluginWidget::collect($this->page()));
    }

    /**
     * collect() hands the page to the callbacks and does nothing else with it, so an object of the
     * right type without a constructed Smarty engine is enough here.
     */
    private function page(): \Admidio\UI\Presenter\PagePresenter
    {
        return (new \ReflectionClass(\Admidio\UI\Presenter\PagePresenter::class))->newInstanceWithoutConstructor();
    }
}
