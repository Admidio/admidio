<?php
/**
 * The email hooks.
 *
 * The real `Email` is constructed here, but no mail is ever handed to PHPMailer: a test that tries to
 * deliver would either need a mail server or would actually send something. So the wrapper of
 * `sendEmail()` is checked through a stand-in that carries it verbatim, the short circuit for a demo
 * installation is checked on the real object, and the property that matters - that no email hook is
 * ever handed the message, because PHPMailer keeps the SMTP credentials in public properties - is
 * checked against the real source file.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Email;
use Admidio\Tests\Support\AdmidioTestCase;
use RuntimeException;
use Throwable;

class EmailStubSettings
{
    public array $values = array(
        'mail_sending_mode' => 0,
        'mail_sender_mode' => 2,
        'mail_sender_name' => 'Example Club',
        'mail_sender_email' => 'noreply@example.org',
        'mail_number_recipients' => 50
    );

    public function getBool(string $name): bool
    {
        return (bool)($this->values[$name] ?? false);
    }

    public function getInt(string $name): int
    {
        return (int)($this->values[$name] ?? 0);
    }

    public function getString(string $name): string
    {
        return (string)($this->values[$name] ?? '');
    }

    public function get(string $name)
    {
        return $this->values[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }
}

class EmailStubLanguage
{
    public function getLanguageIsoCode(): string
    {
        return 'en';
    }

    public function get(string $textId, array $params = array()): string
    {
        return $textId;
    }
}

/** The wrapper of Email::sendEmail(), with the delivery replaced by something the test controls. */
class ProbeEmail
{
    public array $recipients = array();
    public string $subject = '';
    /** @var callable what deliver() does */
    public $deliver;

    public function sendEmail(): bool
    {
        $this->recipients = Hooks::applyTypedFilters('email_recipients', $this->recipients, $this->subject);
        $recipientCount = count($this->recipients);

        try {
            ($this->deliver)($this->recipients);
        } catch (Throwable $exception) {
            Hooks::doActionCatchErrors('email_failed', $this->subject, $recipientCount, $exception->getMessage());

            throw $exception;
        }

        Hooks::doActionCatchErrors('email_sent', $this->subject, $recipientCount);

        return true;
    }
}

class EmailHooksTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $GLOBALS['gSettingsManager'] = new EmailStubSettings();
        $GLOBALS['gL10n'] = new EmailStubLanguage();
        $GLOBALS['gDebug'] = false;
        $GLOBALS['gValidLogin'] = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    private function anEmail(callable $deliver): ProbeEmail
    {
        Hooks::reset();
        $mail = new ProbeEmail();
        $mail->subject = 'The summer camp';
        $mail->recipients = array(
            array('email' => 'a@example.org', 'name' => 'Ann', 'firstname' => 'Ann', 'surname' => 'A'),
            array('email' => 'b@example.org', 'name' => 'Bob', 'firstname' => 'Bob', 'surname' => 'B'),
            array('email' => 'c@example.org', 'name' => 'Cid', 'firstname' => 'Cid', 'surname' => 'C')
        );
        $mail->deliver = $deliver;

        return $mail;
    }

    public function testAFilterCanRemoveARecipient(): void
    {
        $delivered = array();
        $mail = $this->anEmail(function (array $recipients) use (&$delivered) {
            $delivered = array_column($recipients, 'email');
        });
        Hooks::addFilter('email_recipients', function (array $recipients) {
            return array_values(array_filter($recipients, function (array $recipient) {
                return $recipient['email'] !== 'b@example.org';
            }));
        });
        $mail->sendEmail();

        $this->assertSame(array('a@example.org', 'c@example.org'), $delivered);
    }

    public function testAFilterCanSuppressTheMailAltogether(): void
    {
        $delivered = array();
        $mail = $this->anEmail(function (array $recipients) use (&$delivered) {
            $delivered = array_column($recipients, 'email');
        });
        Hooks::addFilter('email_recipients', function () {
            return array();
        });

        $this->assertTrue($mail->sendEmail());
        $this->assertSame(array(), $delivered);
    }

    public function testEmailSentReportsTheSubjectAndHowManyWereAddressed(): void
    {
        $sent = array();
        $mail = $this->anEmail(function () {
        });
        Hooks::addAction('email_sent', function (string $subject, int $count) use (&$sent) {
            $sent[] = $subject . '/' . $count;
        });
        $mail->sendEmail();

        $this->assertSame(array('The summer camp/3'), $sent);
    }

    public function testEmailSentCountsWhatWasReallyAddressedAfterTheFilter(): void
    {
        $sent = array();
        $mail = $this->anEmail(function () {
        });
        Hooks::addFilter('email_recipients', function (array $recipients) {
            return array(reset($recipients));
        });
        Hooks::addAction('email_sent', function (string $subject, int $count) use (&$sent) {
            $sent[] = $subject . '/' . $count;
        });
        $mail->sendEmail();

        $this->assertSame(array('The summer camp/1'), $sent);
    }

    public function testAFailedDeliveryReportsEmailFailedAndNotEmailSent(): void
    {
        $failures = array();
        $mail = $this->anEmail(function () {
            throw new RuntimeException('SMTP connect failed');
        });
        Hooks::addAction('email_failed', function (string $subject, int $count, string $error) use (&$failures) {
            $failures[] = $subject . '/' . $count . '/' . $error;
        });
        Hooks::addAction('email_sent', function () use (&$failures) {
            $failures[] = 'sent';
        });

        $reported = '';
        try {
            $mail->sendEmail();
        } catch (Throwable $exception) {
            $reported = $exception->getMessage();
        }
        $this->assertSame(array('The summer camp/3/SMTP connect failed'), $failures, 'email_failed reports the failure');
        $this->assertNotContains('sent', $failures, 'email_sent does not fire for a mail that was not sent');
        $this->assertSame('SMTP connect failed', $reported, 'and the failure still reaches the caller');
    }

    public function testABrokenDiagnosticMustNotSwallowTheDeliveryError(): void
    {
        $mail = $this->anEmail(function () {
            throw new RuntimeException('SMTP connect failed');
        });
        Hooks::addAction('email_failed', function () {
            throw new \LogicException('the outbox plugin is broken');
        });

        $reported = '';
        try {
            $mail->sendEmail();
        } catch (Throwable $exception) {
            $reported = $exception->getMessage();
        }
        $this->assertSame('SMTP connect failed', $reported, 'a listener that throws cannot replace the delivery error');
    }

    public function testADemoInstallationDispatchesNothingAtAll(): void
    {
        Hooks::reset();
        $GLOBALS['gDisableEmailSending'] = true;
        $dispatched = 0;
        foreach (array('email_sent', 'email_failed') as $stage) {
            Hooks::addAction($stage, function () use (&$dispatched) {
                $dispatched++;
            });
        }
        Hooks::addFilter('email_recipients', function (array $recipients) use (&$dispatched) {
            $dispatched++;
            return $recipients;
        });

        $real = new Email();
        $real->addRecipient('a@example.org', 'Ann', 'A');
        $result = $real->sendEmail();
        unset($GLOBALS['gDisableEmailSending']);

        $this->assertTrue($result);
        $this->assertSame(0, $dispatched, 'a mail that is not sent because sending is switched off reports nothing');
    }

    public function testTheMessageItselfNeverReachesACallback(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Infrastructure/Email.php');
        $dispatches = array();
        foreach (explode("\n", $source) as $line) {
            if (str_contains($line, 'Hooks::')) {
                $dispatches[] = trim($line);
            }
        }

        $this->assertCount(3, $dispatches, 'the email class dispatches the three email hooks');

        // $this as an argument would carry PHPMailer::$Username and ::$Password with it. A property
        // of it, such as $this->Subject, is a value and is fine.
        $handsOverTheMessage = function (string $line): bool {
            return preg_match('/,\\s*\\$this\\s*(?:,|\\))/', $line) === 1;
        };

        $this->assertTrue($handsOverTheMessage('Hooks::doAction(a, $this->Subject, $this);'));
        $this->assertFalse($handsOverTheMessage('Hooks::doAction(a, $this->Subject, $count);'));

        $leaks = array_values(array_filter($dispatches, $handsOverTheMessage));
        $this->assertSame(array(), $leaks, 'none of them is handed the message, which holds the SMTP credentials');
    }
}
