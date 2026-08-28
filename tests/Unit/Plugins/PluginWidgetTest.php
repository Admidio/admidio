<?php
/**
 * The overview widget extension point. The hook engine is the real one; the page is a stand-in,
 * because collect() only passes it through to the callbacks and never touches it.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginWidgetTest extends PluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Hooks::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();

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
     * collect() hands the page to the callbacks and does nothing else with it, so an object of the
     * right type without a constructed Smarty engine is enough here.
     */
    private function page(): \Admidio\UI\Presenter\PagePresenter
    {
        return (new \ReflectionClass(\Admidio\UI\Presenter\PagePresenter::class))->newInstanceWithoutConstructor();
    }
}
