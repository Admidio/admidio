<?php
/**
 * A secret of another service, such as the client secret of an OAuth 2.0 app, has to reach the
 * preferences exactly as it was entered. validate() frees every other input from HTML.
 */

namespace Admidio\Tests\Unit\UI;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\UI\Presenter\FormPresenter;
use Psr\Log\NullLogger;

class FormPreserveValueTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        parent::tearDown();
    }

    public function testASecretIsOnlyTrimmedAndEverythingElseIsFreedFromHtml(): void
    {
        $form = new FormPresenter('adm_secret_form', 'form.tpl', 'index.php');
        $form->addInput('client_secret', 'Client secret', '', array('type' => 'password', 'preserveValue' => true));
        $form->addInput('name', 'Name', '');

        $values = $form->validate(array(
            'adm_csrf_token' => $form->getCsrfToken(),
            'client_secret' => " a<b>c&amp;d\"e \n",
            'name' => ' <b>Choir</b> '
        ));

        $this->assertSame('a<b>c&amp;d"e', $values['client_secret']);
        $this->assertSame('Choir', $values['name']);
    }
}
