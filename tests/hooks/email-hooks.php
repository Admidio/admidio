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
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;

class StubSettings
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

class StubLanguage
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

$GLOBALS['gSettingsManager'] = new StubSettings();
$GLOBALS['gL10n'] = new StubLanguage();
$GLOBALS['gDebug'] = false;
$GLOBALS['gValidLogin'] = false;

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
        } catch (\Throwable $exception) {
            Hooks::doActionCatchErrors('email_failed', $this->subject, $recipientCount, $exception->getMessage());

            throw $exception;
        }

        Hooks::doActionCatchErrors('email_sent', $this->subject, $recipientCount);

        return true;
    }
}

function anEmail(callable $deliver): ProbeEmail
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

// ------------------------------------------------------------------ a recipient can be taken away

$delivered = array();
$mail = anEmail(function (array $recipients) use (&$delivered) {
    $delivered = array_column($recipients, 'email');
});
Hooks::addFilter('email_recipients', function (array $recipients) {
    return array_values(array_filter($recipients, function (array $recipient) {
        return $recipient['email'] !== 'b@example.org';
    }));
});
$mail->sendEmail();

check('a filter can remove a recipient', $delivered === array('a@example.org', 'c@example.org'), implode(',', $delivered));

// removing every recipient means nothing is sent, and that is not an error
$delivered = array();
$mail = anEmail(function (array $recipients) use (&$delivered) {
    $delivered = array_column($recipients, 'email');
});
Hooks::addFilter('email_recipients', function () {
    return array();
});
check('a filter can suppress the mail altogether', $mail->sendEmail() === true && $delivered === array(), implode(',', $delivered));

// ------------------------------------------------------------- the diagnostics say what happened

$sent = array();
$mail = anEmail(function () {
});
Hooks::addAction('email_sent', function (string $subject, int $count) use (&$sent) {
    $sent[] = $subject . '/' . $count;
});
$mail->sendEmail();
check('email_sent reports the subject and how many were addressed', $sent === array('The summer camp/3'), implode(',', $sent));

// the count is the one after the filter, not the one the module handed in
$sent = array();
$mail = anEmail(function () {
});
Hooks::addFilter('email_recipients', function (array $recipients) {
    return array(reset($recipients));
});
Hooks::addAction('email_sent', function (string $subject, int $count) use (&$sent) {
    $sent[] = $subject . '/' . $count;
});
$mail->sendEmail();
check('and it counts what was really addressed, after the filter', $sent === array('The summer camp/1'), implode(',', $sent));

// ------------------------------------------------------------------------------ a failed delivery

$failures = array();
$mail = anEmail(function () {
    throw new \RuntimeException('SMTP connect failed');
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
} catch (\Throwable $exception) {
    $reported = $exception->getMessage();
}
check('email_failed reports the failure', $failures === array('The summer camp/3/SMTP connect failed'), implode(',', $failures));
check('email_sent does not fire for a mail that was not sent', !in_array('sent', $failures, true));
check('and the failure still reaches the caller', $reported === 'SMTP connect failed', $reported);

// a broken diagnostic must not swallow the delivery error
$mail = anEmail(function () {
    throw new \RuntimeException('SMTP connect failed');
});
Hooks::addAction('email_failed', function () {
    throw new \LogicException('the outbox plugin is broken');
});
$reported = '';
try {
    $mail->sendEmail();
} catch (\Throwable $exception) {
    $reported = $exception->getMessage();
}
check('a listener that throws cannot replace the delivery error', $reported === 'SMTP connect failed', $reported);

// --------------------------------------------- a demo installation dispatches nothing at all

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

$real = new \Admidio\Infrastructure\Email();
$real->addRecipient('a@example.org', 'Ann', 'A');
$result = $real->sendEmail();
unset($GLOBALS['gDisableEmailSending']);

check('a mail that is not sent because sending is switched off reports nothing', $result === true && $dispatched === 0, (string)$dispatched);

// ----------------------------------------------- the message itself never reaches a callback

$source = file_get_contents(__DIR__ . '/../../src/Infrastructure/Email.php');
$dispatches = array();
foreach (explode("\n", $source) as $line) {
    if (str_contains($line, 'Hooks::')) {
        $dispatches[] = trim($line);
    }
}

check('the email class dispatches the three email hooks', count($dispatches) === 3, implode(' | ', $dispatches));

// $this as an argument would carry PHPMailer::$Username and ::$Password with it. A property of it,
// such as $this->Subject, is a value and is fine.
$handsOverTheMessage = function (string $line): bool {
    return preg_match('/,\\s*\\$this\\s*(?:,|\\))/', $line) === 1;
};

check(
    'the check would notice a hook that was handed the message',
    $handsOverTheMessage('Hooks::doAction(a, $this->Subject, $this);')
    && !$handsOverTheMessage('Hooks::doAction(a, $this->Subject, $count);')
);

$leaks = array_values(array_filter($dispatches, $handsOverTheMessage));
check('and none of them is handed the message, which holds the SMTP credentials', $leaks === array(), implode(' | ', $leaks));

exit(testSummary());
