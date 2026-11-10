<?php

namespace Admidio\Tests\Unit\UI\Component;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\UI\Component\ListActions;
use UnexpectedValueException;

class ListActionsTest extends AdmidioTestCase
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

    private function coreActions(): array
    {
        return array(
            array('id' => 'edit', 'url' => '/edit', 'icon' => 'bi bi-pencil-square', 'tooltip' => 'Edit'),
            array('id' => 'delete', 'dataMessage' => 'Sure?', 'dataHref' => 'x()', 'icon' => 'bi bi-trash', 'tooltip' => 'Delete'),
        );
    }

    private function ids(array $actions): array
    {
        return array_map(static fn(array $action) => $action['id'] ?? null, $actions);
    }

    public function testWithoutAFilterTheActionsStayAsTheyAre(): void
    {
        self::assertSame($this->coreActions(), ListActions::filter('menu', $this->coreActions()));
    }

    public function testAListWithoutAnIDTakesNoPartInTheHook(): void
    {
        Hooks::addFilter(ListActions::HOOK, static fn(array $actions) => array());

        self::assertSame($this->coreActions(), ListActions::filter('', $this->coreActions()));
    }

    public function testFiltersReceiveTheListAndTheRow(): void
    {
        $received = null;
        Hooks::addFilter(ListActions::HOOK, function (array $actions, string $listId, array $row) use (&$received) {
            $received = array($listId, $row);
            return $actions;
        });
        ListActions::filter('menu', $this->coreActions(), array('uuid' => 'm-1'));

        self::assertSame(array('menu', array('uuid' => 'm-1')), $received);
    }

    public function testAFilterCanHideAnAction(): void
    {
        Hooks::addFilter(ListActions::HOOK, static fn(array $actions) => array_filter($actions, static fn($action) => $action['id'] !== 'delete'));

        $actions = ListActions::filter('menu', $this->coreActions());

        self::assertSame(array('edit'), $this->ids($actions));
        self::assertSame(array(0), array_keys($actions), 'the keys are consecutive again');
    }

    public function testAFilterCanReorderTheActions(): void
    {
        Hooks::addFilter(ListActions::HOOK, static fn(array $actions) => array_reverse($actions));

        self::assertSame(array('delete', 'edit'), $this->ids(ListActions::filter('menu', $this->coreActions())));
    }

    public function testAFilterCanModifyAnAction(): void
    {
        Hooks::addFilter(ListActions::HOOK, static function (array $actions) {
            $actions[0]['icon'] = 'bi bi-eye';
            return $actions;
        });

        self::assertSame('bi bi-eye', ListActions::filter('menu', $this->coreActions())[0]['icon']);
    }

    public function testAFilterCanAddAnAction(): void
    {
        Hooks::addFilter(ListActions::HOOK, static function (array $actions, string $listId) {
            if ($listId === 'contacts') {
                $actions[] = array('id' => 'myplugin_print', 'url' => '/print', 'icon' => 'bi bi-printer', 'tooltip' => 'Print');
            }
            return $actions;
        });

        self::assertSame(array('edit', 'delete'), $this->ids(ListActions::filter('menu', $this->coreActions())));
        self::assertSame(array('edit', 'delete', 'myplugin_print'), $this->ids(ListActions::filter('contacts', $this->coreActions())));
    }

    public function testAnActionWithoutAnyTargetIsPurelyInformational(): void
    {
        Hooks::addFilter(ListActions::HOOK, static function (array $actions) {
            $actions[] = array('id' => 'locked', 'icon' => 'bi bi-shield-lock', 'tooltip' => 'Cannot be removed');
            return $actions;
        });

        self::assertSame(array('edit', 'delete', 'locked'), $this->ids(ListActions::filter('plugins', $this->coreActions())));
    }

    public function testAnActionWithoutIconOrTooltipIsRejected(): void
    {
        Hooks::addFilter(ListActions::HOOK, static fn(array $actions) => array(array('url' => '/x', 'tooltip' => 'No icon')));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('"menu"');
        ListActions::filter('menu', $this->coreActions());
    }

    public function testAFilterMustReturnAnArray(): void
    {
        Hooks::addFilter(ListActions::HOOK, static fn(array $actions) => '<a href="#">html</a>');

        $this->expectException(UnexpectedValueException::class);
        ListActions::filter('menu', $this->coreActions());
    }
}
