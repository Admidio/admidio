<?php

namespace Admidio\Tests\Integration\Mail;

use Admidio\Infrastructure\Email;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\AdministratorTestCase;

/**
 * Integration test for the real Admidio SMTP path.
 *
 * Docker health is deliberately not consulted. The test only cares whether SMTP accepts the
 * production email and whether Mailpit's HTTP API exposes that delivered message.
 */
class MailpitIntegrationTest extends AdministratorTestCase
{
    /**
     * @testdox PreferencesService sends a real email through Mailpit and the message reaches its API
     */
    public function testPreferencesTestEmailIsDeliveredThroughMailpit(): void
    {
        global $gCurrentOrganization, $gCurrentUser, $gL10n, $gSettingsManager;

        $smtpHost = getenv('TEST_MAIL_HOST') ?: '127.0.0.1';
        $smtpPort = (int)(getenv('TEST_MAIL_PORT') ?: 1025);
        $apiHost = getenv('TEST_MAILPIT_API_HOST') ?: $smtpHost;
        $apiPort = (int)(getenv('TEST_MAILPIT_API_PORT') ?: 8025);

        $oldSettings = array(
            'mail_send_method' => $gSettingsManager->getString('mail_send_method'),
            'mail_smtp_host' => $gSettingsManager->getString('mail_smtp_host'),
            'mail_smtp_port' => $gSettingsManager->getInt('mail_smtp_port'),
            'mail_smtp_auth' => $gSettingsManager->getBool('mail_smtp_auth'),
            'mail_smtp_secure' => $gSettingsManager->getString('mail_smtp_secure'),
            'mail_smtp_authentication_type' => $gSettingsManager->getString('mail_smtp_authentication_type'),
            'mail_smtp_user' => $gSettingsManager->getString('mail_smtp_user'),
            'mail_smtp_password' => $gSettingsManager->getString('mail_smtp_password'),
            'mail_sending_mode' => $gSettingsManager->getInt('mail_sending_mode'),
            'mail_sender_email' => $gSettingsManager->getString('mail_sender_email'),
            'mail_sender_name' => $gSettingsManager->getString('mail_sender_name'),
            'mail_html_registered_users' => $gSettingsManager->getBool('mail_html_registered_users')
        );

        $recipient = 'mailpit-regression-' . bin2hex(random_bytes(6)) . '@example.test';
        $expectedSubject = $gL10n->get(
            'SYS_EMAIL_FUNCTION_TEST',
            array($gCurrentOrganization->getValue('org_longname', 'database'))
        );

        try {
            $gCurrentUser->setValue('FIRST_NAME', 'Mailpit');
            $gCurrentUser->setValue('LAST_NAME', 'Regression');
            $gCurrentUser->setValue('EMAIL', $recipient);
            $gCurrentUser->save();

            $gSettingsManager->set('mail_send_method', 'SMTP');
            $gSettingsManager->set('mail_smtp_host', $smtpHost);
            $gSettingsManager->set('mail_smtp_port', $smtpPort);
            $gSettingsManager->set('mail_smtp_auth', false);
            $gSettingsManager->set('mail_smtp_secure', '');
            $gSettingsManager->set('mail_smtp_authentication_type', '');
            $gSettingsManager->set('mail_smtp_user', '');
            $gSettingsManager->set('mail_smtp_password', '');
            $gSettingsManager->set('mail_sending_mode', Email::SENDINGMODE_SINGLE);
            $gSettingsManager->set('mail_sender_email', 'admidio-regression@example.test');
            $gSettingsManager->set('mail_sender_name', 'Admidio Regression');
            $gSettingsManager->set('mail_html_registered_users', false);

            $this->assertTrue(
                (new PreferencesService())->sendTestEmail(),
                'The production Email/PHPMailer path did not report a successful SMTP delivery.'
            );

            $message = $this->waitForMailpitMessage($apiHost, $apiPort, $recipient);
            $this->assertIsArray(
                $message,
                'SMTP accepted the message, but Mailpit did not expose the unique recipient through its HTTP API.'
            );

            $subject = (string)($message['Subject'] ?? $message['subject'] ?? '');
            $this->assertSame($expectedSubject, $subject);
        } finally {
            // DatabaseTestCase rolls the administrator profile changes back and
            // AdministratorTestCase discards this in-memory User object in tearDown().
            // Do not issue a second profile-only User::save() just for cleanup.
            foreach ($oldSettings as $name => $value) {
                $gSettingsManager->set($name, $value);
            }
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function waitForMailpitMessage(string $host, int $port, string $recipient): ?array
    {
        $lastError = '';

        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $curl = curl_init();
            $this->assertNotFalse($curl);

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'http://' . $host . ':' . $port . '/api/v1/messages?limit=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 500,
                CURLOPT_TIMEOUT_MS => 1000
            ));

            $response = curl_exec($curl);
            $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($response === false) {
                $lastError = curl_error($curl);
            }
            curl_close($curl);

            if (is_string($response) && $httpStatus === 200) {
                $payload = json_decode($response, true);
                if (is_array($payload)) {
                    $messages = $payload['messages'] ?? $payload['Messages'] ?? array();
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            if (!is_array($message)) {
                                continue;
                            }

                            $encoded = json_encode($message);
                            if (is_string($encoded)
                                && str_contains(strtolower($encoded), strtolower($recipient))) {
                                return $message;
                            }
                        }
                    }
                }
            }

            usleep(100000);
        }

        if ($lastError !== '') {
            $this->fail('Mailpit HTTP API could not be reached: ' . $lastError);
        }

        return null;
    }
}
