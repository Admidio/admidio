<?php

namespace Admidio\Tests\Integration\Installation;

use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Language;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\UI\Presenter\PreferencesPresenter;

class SystemMailTemplateModeTest extends DatabaseTestCase
{
    private mixed $previousSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousSession = $GLOBALS['gCurrentSession'];
        $GLOBALS['gCurrentSession'] = new \Admidio\Session\Entity\Session($this->getDatabase(), COOKIE_PREFIX);
    }

    protected function tearDown(): void
    {
        $GLOBALS['gCurrentSession'] = $this->previousSession;
        parent::tearDown();
    }

    private function template(string $name): Text
    {
        $text = new Text($this->getDatabase());
        $text->readDataByColumns(array('txt_org_id' => $GLOBALS['gCurrentOrgId'], 'txt_name' => $name));
        return $text;
    }

    private function renderForm(): string
    {
        $presenter = new PreferencesPresenter();
        $presenter->getSmartyTemplate()->setTemplateDir(ADMIDIO_PATH . '/themes/simple/templates');
        $presenter->getSmartyTemplate()->setCompileDir(ADMIDIO_PATH . '/.phpunit.cache');
        return $presenter->createSystemNotificationsForm();
    }

    public function testAllTemplatesKeepCustomTextAcrossSavedModeChanges(): void
    {
        foreach (Text::SYSTEM_MAIL_DEFAULTS as $name => $id) {
            $custom = '#subject# My subject ' . $name . "\r\n#content# Hello #user_first_name#, my text & details.";
            $text = $this->template($name);
            $text->setSystemMailTemplate(false, $custom);
            $text->save();
            $text = $this->template($name);
            $this->assertSame($custom, $text->getValue('txt_text', 'database'));
            $text->setSystemMailTemplate(true);
            $text->save();
            $text = $this->template($name);
            $this->assertSame($id, $text->getValue('txt_text', 'database'));
            $this->assertSame($custom, $text->getSystemMailCustomText());
            $this->assertNotSame($custom, $text->getValue('txt_text', 'text'));
            $text->setSystemMailTemplate(false);
            $text->save();
            $this->assertSame($custom, $this->template($name)->getValue('txt_text', 'text'));
        }
    }

    public function testExplicitCustomModeStoresPlainTextEvenWhenItMatchesTheDefault(): void
    {
        $previous = $GLOBALS['gL10n'];
        try {
            $GLOBALS['gL10n'] = new Language('en');
            $text = $this->template('SYSMAIL_PASSWORD_RESET');
            $english = $text->getValue('txt_text', 'text');
            $text->setSystemMailTemplate(false, $english);
            $text->save();
            $GLOBALS['gL10n'] = new Language('de');
            $text = $this->template('SYSMAIL_PASSWORD_RESET');
            $this->assertSame($english, $text->getValue('txt_text', 'text'));
            $text->setSystemMailTemplate(true);
            $text->save();
            $this->assertNotSame($english, $this->template('SYSMAIL_PASSWORD_RESET')->getValue('txt_text', 'text'));
            $this->assertSame($english, $this->template('SYSMAIL_PASSWORD_RESET')->getSystemMailCustomText());
        } finally {
            $GLOBALS['gL10n'] = $previous;
        }
    }

    public function testEnteringAnIdRestoresDefaultWithoutLosingLegacyOrEmptyCustomText(): void
    {
        $name = 'SYSMAIL_PASSWORD_RESET';
        $text = $this->template($name);
        $text->setValue('txt_text', 'Existing custom template');
        $text->save();
        $text = $this->template($name);
        $text->setSystemMailTemplate(false, Text::SYSTEM_MAIL_DEFAULTS[$name]);
        $text->save();
        $text = $this->template($name);
        $this->assertSame(Text::SYSTEM_MAIL_DEFAULTS[$name], $text->getValue('txt_text', 'database'));
        $this->assertSame('Existing custom template', $text->getSystemMailCustomText());
        $text->setSystemMailTemplate(false, '');
        $text->save();
        $text = $this->template($name);
        $text->setSystemMailTemplate(true);
        $text->save();
        $text = $this->template($name);
        $this->assertSame('', $text->getSystemMailCustomText());
        $text->setSystemMailTemplate(false);
        $text->save();
        $this->assertSame('', $this->template($name)->getValue('txt_text', 'database'));
    }

    public function testFormRendersSwitchesAndReadOnlyDefaultsAndSavesOnlyTheChosenMode(): void
    {
        if (!defined('THEME_URL')) {
            define('THEME_URL', ADMIDIO_URL . '/themes/simple');
        }
        $html = $this->renderForm();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//input[contains(@class,"admidio-system-mail-default")]')->length);
        foreach (Text::SYSTEM_MAIL_DEFAULTS as $name => $id) {
            $this->assertSame(1, $xpath->query('//textarea[@id="' . $name . '_DEFAULT" and @disabled]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="' . $name . '_custom_panel" and @hidden]')->length);
        }
        $token = $xpath->query('//input[@name="adm_csrf_token"]')->item(0)->getAttribute('value');
        $name = 'SYSMAIL_PASSWORD_RESET';
        (new PreferencesService())->save('system_notifications', array(
            'adm_csrf_token' => $token,
            $name => 'Saved personal draft',
            $name . '_USE_DEFAULT' => '1',
            $name . '_DEFAULT' => 'Tampered preview must not become active',
        ));
        $text = $this->template($name);
        $this->assertSame(Text::SYSTEM_MAIL_DEFAULTS[$name], $text->getValue('txt_text', 'database'));
        $this->assertSame('Saved personal draft', $text->getSystemMailCustomText());
        $this->assertSame(6, (int)$this->getDatabase()->queryPrepared('SELECT COUNT(*) FROM ' . TBL_TEXTS . ' WHERE txt_org_id = ?', array($GLOBALS['gCurrentOrgId']))->fetchColumn());

        $html = $this->renderForm();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Saved personal draft', $xpath->query('//textarea[@id="' . $name . '"]')->item(0)->textContent);
        $token = $xpath->query('//input[@name="adm_csrf_token"]')->item(0)->getAttribute('value');
        (new PreferencesService())->save('system_notifications', array('adm_csrf_token' => $token, $name => 'Saved personal draft'));
        $this->assertSame('Saved personal draft', $this->template($name)->getValue('txt_text', 'database'));
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $this->renderForm());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//input[@id="' . $name . '_USE_DEFAULT" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//textarea[@id="' . $name . '" and @readonly]')->length);
        $this->assertSame(0, $xpath->query('//div[@id="' . $name . '_custom_panel" and @hidden]')->length);
    }

    public function testUpgradeCopiesExistingCustomTemplatesButNotDefaultIds(): void
    {
        $name = 'SYSMAIL_PASSWORD_RESET';
        $text = $this->template($name);
        $text->setValue('txt_text', 'Legacy custom template');
        $text->save();
        $xml = simplexml_load_file(ADMIDIO_PATH . '/install/db_scripts/update_5_1.xml');
        $step = $xml->xpath('step[@id="1300"]');
        $this->getDatabase()->query(str_replace('%PREFIX%', TABLE_PREFIX, (string)$step[0]));
        $this->assertSame('Legacy custom template', $this->template($name)->getSystemMailCustomText());
        $this->assertNull($this->template('SYSMAIL_REGISTRATION_NEW')->getSystemMailCustomText());
    }
}
