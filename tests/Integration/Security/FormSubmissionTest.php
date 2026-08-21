<?php
/**
 * Form Submission Security Tests
 *
 * Tests the gate every POST of an Admidio module goes through: FormPresenter::validate(). It checks
 * the CSRF token against the one the form was built with, refuses fields that the form does not
 * contain, enforces the required fields and strips html out of everything that is not an editor
 * field. A module that calls it therefore never sees raw request data.
 */

namespace Admidio\Tests\Integration\Security;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Users\Entity\User;

class FormSubmissionTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        $administrator = new User($this->getDatabase(), $GLOBALS['gProfileFields'], 1);

        return $this->withCurrentUser($administrator, self::ORG_ID, true, $callback);
    }

    /**
     * Build a form with one field of each kind the tests need.
     */
    private function buildForm(): FormPresenter
    {
        $form = new FormPresenter('adm_test_form', 'modules/menu.edit.tpl', 'https://example.local/save');
        $form->addInput('entry_name', 'Name', '', array('property' => FormPresenter::FIELD_REQUIRED));
        $form->addInput('entry_url', 'Website', '', array('type' => 'url'));
        $form->addInput('entry_mail', 'E-Mail', '', array('type' => 'email'));
        $form->addInput('entry_count', 'Count', '', array('type' => 'number'));
        $form->addCheckbox('entry_active', 'Active');

        return $form;
    }

    /**
     * Test that a form carries a token
     *
     * @testdox Every form is built with a CSRF token of its own
     */
    public function testEveryFormIsBuiltWithACsrfToken(): void
    {
        $this->asAdministrator(function () {
            $form = $this->buildForm();
            $token = $form->getCsrfToken();

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{30}$/', $token);

            // the token stays the same while the form lives, so a rendered form can be submitted
            $this->assertEquals($token, $form->getCsrfToken());

            // a second form gets a token of its own
            $this->assertNotEquals($token, $this->buildForm()->getCsrfToken());
        });
    }

    /**
     * Test that a submission has to carry the token
     *
     * @testdox A submission without a CSRF token is refused
     */
    public function testSubmissionWithoutACsrfTokenIsRefused(): void
    {
        $this->asAdministrator(function () {
            $form = $this->buildForm();

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('No CSRF token provided.');
            $form->validate(array('entry_name' => 'Something'));
        });
    }

    /**
     * Test that a foreign token does not work
     *
     * @testdox A submission with a token of another form is refused
     */
    public function testSubmissionWithAForeignCsrfTokenIsRefused(): void
    {
        $this->asAdministrator(function () {
            $form = $this->buildForm();
            $otherForm = $this->buildForm();

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Invalid or missing CSRF token!');
            $form->validate(array(
                'adm_csrf_token' => $otherForm->getCsrfToken(),
                'entry_name' => 'Something'
            ));
        });
    }

    /**
     * Test that the payload may not be extended
     *
     * @testdox A field that the form does not contain is refused
     */
    public function testFieldThatTheFormDoesNotContainIsRefused(): void
    {
        $this->asAdministrator(function () {
            $form = $this->buildForm();

            // a caller that trusts the returned array must not receive a column it never offered
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Invalid payload of the form!');
            $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'rol_administrator' => '1'
            ));
        });
    }

    /**
     * Test that required fields are enforced
     *
     * @testdox A required field that is empty or missing is refused
     */
    public function testRequiredFieldThatIsEmptyIsRefused(): void
    {
        $this->asAdministrator(function () {
            $form = $this->buildForm();
            $token = $form->getCsrfToken();

            $missing = false;
            try {
                $form->validate(array('adm_csrf_token' => $token));
            } catch (Exception $e) {
                $missing = true;
            }
            $this->assertTrue($missing, 'A missing required field must be refused.');

            $this->expectException(Exception::class);
            $form->validate(array('adm_csrf_token' => $token, 'entry_name' => ''));
        });
    }

    /**
     * Test that markup does not survive validation
     *
     * @testdox Html is removed from an ordinary input field
     */
    public function testHtmlIsRemovedFromAnOrdinaryInputField(): void
    {
        $values = $this->asAdministrator(function () {
            $form = $this->buildForm();

            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => '<script>alert(1)</script>Harmless'
            ));
        });

        // strip_tags drops the tags and keeps the text between them
        $this->assertEquals('alert(1)Harmless', $values['entry_name']);
        $this->assertStringNotContainsString('<', $values['entry_name']);
    }

    /**
     * Test that the token never reaches the caller
     *
     * @testdox The CSRF token is not part of the validated values
     */
    public function testCsrfTokenIsNotPartOfTheValidatedValues(): void
    {
        $values = $this->asAdministrator(function () {
            $form = $this->buildForm();

            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something'
            ));
        });

        $this->assertArrayNotHasKey('adm_csrf_token', $values);
        $this->assertArrayHasKey('entry_name', $values);
    }

    /**
     * Test that an address has to look like one
     *
     * @testdox An email field only accepts a well formed address
     */
    public function testEmailFieldOnlyAcceptsAWellFormedAddress(): void
    {
        $accepted = $this->asAdministrator(function () {
            $form = $this->buildForm();

            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_mail' => 'someone@example.org'
            ));
        });
        $this->assertEquals('someone@example.org', $accepted['entry_mail']);

        $this->asAdministrator(function () {
            $form = $this->buildForm();

            $this->expectException(Exception::class);
            $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_mail' => 'not-an-address'
            ));
        });
    }

    /**
     * Test that a url has to look like one
     *
     * @testdox A url field only accepts a well formed address
     */
    public function testUrlFieldOnlyAcceptsAWellFormedAddress(): void
    {
        $accepted = $this->asAdministrator(function () {
            $form = $this->buildForm();

            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_url' => 'https://example.org/page'
            ));
        });
        $this->assertEquals('https://example.org/page', $accepted['entry_url']);

        $this->asAdministrator(function () {
            $form = $this->buildForm();

            $this->expectException(Exception::class);
            $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_url' => 'javascript:alert(1)'
            ));
        });
    }

    /**
     * Test that a number field rejects what is not a positive number
     *
     * @testdox A number field refuses text and negative values
     */
    public function testNumberFieldRefusesTextAndNegativeValues(): void
    {
        $accepted = $this->asAdministrator(function () {
            $form = $this->buildForm();

            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_count' => '7'
            ));
        });
        $this->assertEquals('7', $accepted['entry_count']);

        $this->asAdministrator(function () {
            $form = $this->buildForm();

            $this->expectException(Exception::class);
            $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something',
                'entry_count' => '-5'
            ));
        });
    }

    /**
     * Test that an unchecked box is not simply absent
     *
     * @testdox A checkbox that was not ticked is validated as zero
     */
    public function testCheckboxThatWasNotTickedIsValidatedAsZero(): void
    {
        $values = $this->asAdministrator(function () {
            $form = $this->buildForm();

            // a browser does not submit an unchecked box at all
            return $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'entry_name' => 'Something'
            ));
        });

        $this->assertArrayHasKey('entry_active', $values);
        $this->assertEquals('0', $values['entry_active']);
    }

    /**
     * Test the direct token check used by the ajax actions
     *
     * @testdox The direct token check compares against the token of the session
     */
    public function testDirectTokenCheckComparesAgainstTheSessionToken(): void
    {
        $db = $this->getDatabase();
        $session = new Session($db, COOKIE_PREFIX);
        $previousSession = $GLOBALS['gCurrentSession'] ?? null;
        $GLOBALS['gCurrentSession'] = $session;

        try {
            $token = $session->getCsrfToken();

            // the delete and sequence actions call this instead of building a form
            SecurityUtils::validateCsrfToken($token);
            $this->assertTrue(true, 'A matching token passes without an exception.');

            $refused = false;
            try {
                SecurityUtils::validateCsrfToken('a-token-from-somewhere-else');
            } catch (Exception $e) {
                $refused = true;
                $this->assertStringContainsString('CSRF', $e->getMessage());
            }
            $this->assertTrue($refused, 'A foreign token must be refused.');
        } finally {
            $GLOBALS['gCurrentSession'] = $previousSession;
        }
    }
}
