<?php

namespace Admidio\Tests\Unit\UI;

use Admidio\Hooks\Hooks;
use Admidio\UI\Component\PageExtensions;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;
use stdClass;

/**
 * The filters page_header_items, page_user_menu_items and page_notices, and the template part that
 * renders the header items.
 */
class PageExtensionsTest extends TestCase
{
    protected function setUp(): void
    {
        Hooks::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
    }

    public function testNothingIsDispatchedWithoutFilters(): void
    {
        $this->assertSame(array(), PageExtensions::getHeaderItems(new stdClass()));
        $this->assertSame(array(), PageExtensions::getNotices(new stdClass()));
    }

    public function testHeaderItemsAreNormalizedAndSortedBySequence(): void
    {
        $page = new stdClass();
        Hooks::addFilter(PageExtensions::HOOK_HEADER_ITEMS, function (array $items, object $received) use ($page): array {
            $this->assertSame($page, $received);
            $items[] = array('id' => 'second', 'label' => 'Second', 'url' => '/b');
            $items[] = array('id' => 'first', 'label' => 'Language', 'icon' => 'bi-translate', 'sequence' => 10, 'items' => array(
                array('id' => 'de', 'label' => 'Deutsch', 'url' => '/de', 'active' => true),
                array('divider' => true),
                array('id' => 'reset', 'label' => 'Default', 'url' => '/reset')
            ));
            return $items;
        }, 10, 2);

        $items = PageExtensions::getHeaderItems($page);

        $this->assertSame(array('first', 'second'), array_column($items, 'id'));
        $this->assertSame('bi bi-translate', $items[0]['icon']);
        $this->assertCount(3, $items[0]['items']);
        $this->assertTrue($items[0]['items'][0]['active']);
        $this->assertTrue($items[0]['items'][1]['divider']);
        $this->assertSame(PageExtensions::DEFAULT_SEQUENCE, $items[1]['sequence']);
    }

    public function testEqualSequencesKeepTheOrderOfTheFilters(): void
    {
        Hooks::addFilter(PageExtensions::HOOK_USER_MENU_ITEMS, fn(array $items): array => array_merge($items, array(
            array('id' => 'a', 'label' => 'A'), array('id' => 'b', 'label' => 'B'), array('id' => 'c', 'label' => 'C')
        )));

        $this->assertSame(array('a', 'b', 'c'), array_column(PageExtensions::getUserMenuItems(new stdClass()), 'id'));
    }

    public function testAnInvalidAnswerDropsTheExtensionsInsteadOfBreakingThePage(): void
    {
        Hooks::addFilter(PageExtensions::HOOK_HEADER_ITEMS, fn(array $items): array => array_merge($items, array(array('label' => 'no id'))));
        $this->assertSame(array(), PageExtensions::getHeaderItems(new stdClass()));

        Hooks::reset();
        Hooks::addFilter(PageExtensions::HOOK_USER_MENU_ITEMS, fn(array $items): array => array(
            array('id' => 'nested', 'label' => 'Nested', 'items' => array(array('id' => 'x', 'label' => 'X')))
        ));
        $this->assertSame(array(), PageExtensions::getUserMenuItems(new stdClass()), 'the profile menu has no submenus');

        Hooks::reset();
        Hooks::addFilter(PageExtensions::HOOK_HEADER_ITEMS, fn(array $items): string => 'not a list');
        $this->assertSame(array(), PageExtensions::getHeaderItems(new stdClass()));

        Hooks::reset();
        Hooks::addFilter(PageExtensions::HOOK_HEADER_ITEMS, fn(array $items): array => array(array('id' => 'x', 'label' => 'X', 'icon' => '"><script>')));
        $this->assertSame(array(), PageExtensions::getHeaderItems(new stdClass()));
    }

    public function testNotices(): void
    {
        Hooks::addFilter(PageExtensions::HOOK_NOTICES, fn(array $notices): array => array_merge($notices, array(
            array('id' => 'impersonate', 'html' => '<strong>Acting as</strong>', 'type' => 'warning')
        )));

        $this->assertSame(
            array(array('id' => 'impersonate', 'type' => 'warning', 'html' => '<strong>Acting as</strong>', 'sequence' => 100)),
            PageExtensions::getNotices(new stdClass())
        );

        Hooks::addFilter(PageExtensions::HOOK_NOTICES, fn(array $notices): array => array_merge($notices, array(
            array('id' => 'bad', 'html' => 'x', 'type' => 'blinking')
        )));
        $this->assertSame(array(), PageExtensions::getNotices(new stdClass()));
    }

    public function testTemplateEncodesTextsAndUrls(): void
    {
        Hooks::addFilter(PageExtensions::HOOK_HEADER_ITEMS, fn(array $items): array => array(
            array('id' => 'menu', 'label' => '<b>Menu</b>', 'icon' => 'bi-list', 'items' => array(
                array('id' => 'one', 'label' => 'One & two', 'url' => '/x?a=1&b="2"', 'active' => true)
            )),
            array('id' => 'badge', 'label' => 'DevTools', 'class' => 'text-bg-warning'),
            array('id' => 'dialog', 'label' => 'Dialog', 'url' => '/dialog.php', 'popup' => true),
            array('id' => 'flag', 'label' => 'de', 'image' => '/flags/de.svg?a=1&b=2', 'url' => '/x')
        ));

        $compile = sys_get_temp_dir() . '/admidio-page-extensions-' . bin2hex(random_bytes(6));
        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir($compile);
        $smarty->assign('headerItems', PageExtensions::getHeaderItems(new stdClass()));
        $html = $smarty->fetch('sys-template-parts/page.header-items.tpl');
        array_map('unlink', glob($compile . '/*') ?: array());
        @rmdir($compile);

        $this->assertStringContainsString('id="adm_header_item_menu"', $html);
        $this->assertStringContainsString('&lt;b&gt;Menu&lt;/b&gt;', $html);
        $this->assertStringContainsString('href="/x?a=1&amp;b=&quot;2&quot;"', $html);
        $this->assertStringContainsString('One &amp; two', $html);
        $this->assertStringContainsString('class="badge me-2 admidio-header-item text-bg-warning"', $html);
        $this->assertStringContainsString('admidio-header-item admidio-header-item-default', $html, 'an item without a class takes the header colors');
        $this->assertStringContainsString('<img class="admidio-header-item-image" src="/flags/de.svg?a=1&amp;b=2" alt="">', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*openPopup"\s+href="javascript:void\(0\);" data-href="\/dialog.php"/', $html);

        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir($compile);
        $this->assertSame('', trim($smarty->fetch('sys-template-parts/page.header-items.tpl')), 'a page frame without the variable renders nothing');
        array_map('unlink', glob($compile . '/*') ?: array());
        @rmdir($compile);
    }
}
