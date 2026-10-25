<?php

namespace Admidio\Tests\Unit\UI;

use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

class UpdateStatisticsDialogTest extends TestCase
{
    public function testCheckboxDefaultsToSendingAndOmissionDisablesSending(): void
    {
        $form = new FormPresenter('adm_update_login_form', 'update.tpl');
        $form->addCheckbox('adm_send_statistics', 'Send statistics', true);
        $this->assertSame('checked', $form->getElements()['adm_send_statistics']['attributes']['checked']);
        $token = $form->getElements()['adm_csrf_token']['value'];
        $values = $form->validate(array('adm_csrf_token' => $token));
        $this->assertSame('0', $values['adm_send_statistics']);
        $values = $form->validate(array('adm_csrf_token' => $token, 'adm_send_statistics' => '1'));
        $this->assertSame('1', $values['adm_send_statistics']);
    }

    public static function confirmationForms(): array
    {
        return array(
            'update' => array('update.tpl', 'adm_update_login_form'),
            'installation' => array('installation.install-admidio.tpl', 'adm_installation_install_admidio_form')
        );
    }

    /** @dataProvider confirmationForms */
    public function testFullJsonRemainsTextAndEditableCheckboxBelongsToConfirmationForm(string $template, string $formId): void
    {
        foreach (array('ADMIDIO_VERSION_TEXT' => '5.1.0', 'ADMIDIO_VERSION_BETA' => 0) as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        $form = new FormPresenter($formId, $template);
        $form->addCheckbox('adm_send_statistics', 'Send statistics', true);
        $form->addSubmitButton('adm_next_page', 'Update');
        $json = json_encode(array(array('id' => '</code><script>alert(1)</script>', 'timestamp' => '2026-10-22 10:00:00')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        require_once dirname(__DIR__, 2) . '/constants.php';
        $smarty = InstallationPresenter::createSmartyObject();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->assign(array(
            'l10n' => new class {
                public function get(string $key, array $arguments = array()): string { return $key; }
            },
            'installedDbVersion' => '5.0.0', 'statisticsJson' => $json, 'formType' => 'default',
            'attributes' => $form->getAttributes(), 'elements' => $form->getElements()
        ));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($smarty->fetch($template));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame($json, $xpath->query('//pre/code')->item(0)->textContent);
        $this->assertCount(0, $xpath->query('//script'));
        $this->assertCount(1, $xpath->query('//form[@id="' . $formId . '"]//input[@name="adm_send_statistics" and @checked and not(@disabled)]'));
        $this->assertCount(1, $xpath->query('//input[@id="adm_send_statistics_summary" and @checked and @disabled and not(@name)]'));
    }

    public function testSettingsPreviewContainsJsonWithoutConsentCheckbox(): void
    {
        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $json = '{"id":"</code><script>alert(1)</script>"}';
        $smarty->assign(array(
            'l10n' => new class {
                public function get(string $key, array $arguments = array()): string { return $key; }
            },
            'statisticsJson' => $json, 'statisticsShowCheckbox' => false
        ));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($smarty->fetch('sys-template-parts/installation-statistics-modal.tpl'));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame($json, $xpath->query('//pre/code')->item(0)->textContent);
        $this->assertCount(0, $xpath->query('//input | //script | //div[contains(@class,"modal-footer")]'));
        $this->assertStringNotContainsString('SYS_ANONYMOUS_STATISTICS_CONSENT_DESC', $document->textContent);
        $this->assertCount(1, $xpath->query('//button[@data-bs-dismiss="modal"]'));
    }
}
