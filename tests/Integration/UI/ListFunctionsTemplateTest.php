<?php

namespace Admidio\Tests\Integration\UI;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

class ListFunctionsTemplateTest extends TestCase
{
    private Smarty $smarty;
    private string $compileDirectory;

    protected function setUp(): void
    {
        $this->compileDirectory = sys_get_temp_dir() . '/admidio-list-actions-' . bin2hex(random_bytes(8));
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $this->smarty->setCompileDir($this->compileDirectory);
        $this->smarty->registerPlugin('function', 'array_key_exists', 'Admidio\Infrastructure\Plugins\Smarty::arrayKeyExists');
        $this->smarty->assign('l10n', new class {
            public function get(string $key): string
            {
                return $key;
            }
        });
    }

    protected function tearDown(): void
    {
        foreach (glob($this->compileDirectory . '/*') ?: array() as $file) {
            unlink($file);
        }
        if (is_dir($this->compileDirectory)) {
            rmdir($this->compileDirectory);
        }
    }

    private function render(array $actions): DOMXPath
    {
        $this->smarty->assign('data', array('uuid' => 'test', 'actions' => $actions));
        $html = $this->smarty->fetch('sys-template-parts/list.functions.tpl');
        $document = new DOMDocument();
        $document->loadHTML('<html><body>' . $html . '</body></html>');
        return new DOMXPath($document);
    }

    public function testResponsiveActionCounts(): void
    {
        foreach (array(0, 1, 2, 3, 5) as $count) {
            $actions = array_fill(0, $count, array('url' => '/edit', 'icon' => 'bi bi-pencil-square', 'tooltip' => 'Edit'));
            $xpath = $this->render($actions);
            $direct = $xpath->query('//a[@href="/edit" and contains(@class, "admidio-icon-link")]');
            self::assertCount($count > 2 ? 1 : $count, $direct);
            foreach ($direct as $link) {
                self::assertSame($count > 2, str_contains($link->getAttribute('class'), 'd-none d-md-inline-flex'));
            }
            self::assertCount($count > 2 ? 1 : 0, $xpath->query('//a[@data-bs-toggle="dropdown"]'));
            self::assertCount($count > 2 ? $count : 0, $xpath->query('//li'));
            self::assertCount($count > 2 ? 1 : 0, $xpath->query('//li[@class="d-md-none"]'));
        }
    }

    public function testDialogAndCustomActionAttributesSurviveOverflow(): void
    {
        $xpath = $this->render(array(
            array('popup' => true, 'popupClass' => 'modal-xl', 'dataHref' => '/edit', 'icon' => 'bi bi-pencil-square', 'tooltip' => 'Edit'),
            array('dataMessage' => 'Delete "A & B"?', 'dataHref' => "callUrlHideElement('row_test', '/delete', 'csrf')", 'icon' => 'bi bi-trash', 'tooltip' => 'Delete'),
            array('class' => 'admidio-field-move', 'attributes' => array('data-uuid' => 'test', 'data-direction' => 'UP', 'data-target' => 'row_test'), 'icon' => 'bi bi-arrow-up', 'tooltip' => 'Move up')
        ));
        self::assertCount(2, $xpath->query('//a[contains(@class, "openPopup") and @data-class="modal-xl" and @data-href="/edit"]'));
        $delete = $xpath->query('//a[contains(@class, "admidio-messagebox")]')->item(0);
        self::assertSame('Delete "A & B"?', $delete->getAttribute('data-message'));
        self::assertSame("callUrlHideElement('row_test', '/delete', 'csrf')", $delete->getAttribute('data-href'));
        self::assertSame('yes-no', $delete->getAttribute('data-buttons'));
        self::assertCount(1, $xpath->query('//a[contains(@class, "admidio-field-move") and @data-direction="UP" and @data-uuid="test" and @data-target="row_test"]'));
    }

    public function testInformationDialogDoesNotOfferDestructiveConfirmation(): void
    {
        $xpath = $this->render(array(array('dataMessage' => 'Already borrowed', 'attributes' => array('data-type' => 'warning'), 'icon' => 'bi bi-box-arrow-right', 'tooltip' => 'Already borrowed')));
        $link = $xpath->query('//a[contains(@class, "admidio-messagebox")]')->item(0);
        self::assertSame('warning', $link->getAttribute('data-type'));
        self::assertFalse($link->hasAttribute('data-buttons'));
        self::assertFalse($link->hasAttribute('data-href'));
    }

    public function testPluginActionsUseTheSameRulesInBothLayouts(): void
    {
        $entry = array(
            'id' => 'example', 'name' => 'Example', 'icon' => '', 'description' => '',
            'author' => '', 'url' => '', 'version' => '1.0', 'installedVersion' => '1.0',
            'versionState' => 'current', 'notes' => array(), 'diagnostics' => array(),
            'toggle' => array('icon' => 'bi bi-toggle-on', 'class' => 'text-success', 'label' => 'Enabled', 'tooltip' => 'Disable', 'dataHref' => "togglePlugin(this, '/disable', 'csrf', '/refresh')"),
            'actions' => array(
                array('url' => '/settings', 'icon' => 'bi bi-gear', 'tooltip' => 'Settings'),
                array('url' => '/history', 'icon' => 'bi bi-clock-history', 'tooltip' => 'History')
            )
        );
        $this->smarty->assign('failures', array());
        $this->smarty->assign('list', array(array('id' => 'group', 'name' => 'Group', 'entries' => array($entry))));
        $html = $this->smarty->fetch('modules/plugins.list.tpl');
        $document = new DOMDocument();
        $document->loadHTML($html);
        $xpath = new DOMXPath($document);
        self::assertCount(2, $xpath->query('//a[@data-bs-toggle="dropdown"]'));
        self::assertCount(2, $xpath->query('//a[@onclick]'));
        self::assertCount(2, $xpath->query('//a[contains(@class, "d-none d-md-inline-flex") and @href="/settings"]'));
        self::assertCount(1, $xpath->query('//*[@id="adm_dropdown_menu_button_mobile_example"]'));
        foreach ($xpath->query('//a[@onclick]') as $toggle) {
            self::assertSame($entry['toggle']['dataHref'], $toggle->getAttribute('onclick'));
        }
    }

    public function testReservationActionsKeepTheirAjaxAndCommentAttributes(): void
    {
        $this->smarty->assign('reservationList', array(
            'headers' => array_fill(0, 8, 'Column'),
            'rows' => array(array(
                'id' => 'reservation_test', 'status' => 'pending', 'statusIcon' => 'bi-clock', 'statusLabel' => 'Pending',
                'beginIso' => '', 'endIso' => '', 'timestampIso' => '', 'beginDateTimeIso' => '', 'endDateTimeIso' => '',
                'itemName' => 'Item', 'requester' => 'User', 'email' => '', 'timestamp' => '', 'begin' => '', 'end' => '',
                'originUrl' => '', 'origin' => '', 'mailUrl' => 'mailto:user@example.org', 'comment' => 'Comment "A & B"',
                'actions' => array(array('url' => '/approve', 'icon' => 'bi-check', 'label' => 'Approve'))
            ))
        ));
        $html = $this->smarty->fetch('modules/inventory.reservations.list.tpl');
        $document = new DOMDocument();
        $document->loadHTML($html);
        $xpath = new DOMXPath($document);
        self::assertCount(1, $xpath->query('//a[@data-bs-toggle="dropdown"]'));
        self::assertCount(2, $xpath->query('//a[contains(@class, "admidio-inventory-reservation-update") and @data-url="/approve" and @data-row-id="reservation_test"]'));
        self::assertSame('Comment "A & B"', $xpath->query('//a[@data-comment]')->item(0)->getAttribute('data-comment'));
        self::assertCount(1, $xpath->query('//a[@href="mailto:user@example.org"]'));
    }


    public function testProfileInventoryDropdownIdsAreUniqueAcrossBothTables(): void
    {
        $actions = array(
            array('url' => '/borrow', 'icon' => 'bi bi-box-arrow-right', 'tooltip' => 'Borrow'),
            array('url' => '/edit', 'icon' => 'bi bi-pencil-square', 'tooltip' => 'Edit'),
            array('url' => '/copy', 'icon' => 'bi bi-copy', 'tooltip' => 'Copy')
        );
        $list = array('headers' => array('Item', 'Actions'), 'column_align' => array('start', 'end'), 'rows' => array(
            array('item_uuid' => 'same_item', 'data' => array('Item'), 'actions' => $actions)
        ));
        $this->smarty->assign('viewType', 'tab');
        $this->smarty->assign('keeperList', $list);
        $this->smarty->assign('receiverList', $list);
        $this->smarty->assign('keeperListHeader', 'Keeper');
        $this->smarty->assign('receiverListHeader', 'Receiver');
        $html = $this->smarty->fetch('modules/profile.view.inventory.tpl');
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $dropdowns = $xpath->query('//a[@data-bs-toggle="dropdown"]');
        self::assertCount(2, $dropdowns);
        self::assertNotSame($dropdowns->item(0)->getAttribute('id'), $dropdowns->item(1)->getAttribute('id'));
        self::assertCount(2, $xpath->query('//a[contains(@class, "d-none d-md-inline-flex") and @href="/borrow"]'));
    }

}
