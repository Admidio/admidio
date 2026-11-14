<?php
namespace Admidio\Events\Service;

use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventReminderRepository;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Language;
use Admidio\Roles\Entity\RolesRights;
use Throwable;

/**
 * Send reminder emails for upcoming events.
 *
 * The state is stored per event and user in adm_event_reminder_sent. The service uses the
 * Maintenance job **event-reminders** and the request_finished action, so it works with or without
 * a cron job.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventReminderService
{
    private Database $database;
    private EventReminderRepository $repository;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->repository = new EventReminderRepository($database);
    }

    /**
     * Send all reminders that are due for the current organization.
     *
     * @param bool $dryRun Only count the reminders, do not send mails and do not write state.
     * @return string|null Summary message, or null if nothing is due.
     * @throws Throwable If an unexpected error occurs outside a single event.
     */
    public function runIfDue(bool $dryRun = false): ?string
    {
        global $gSettingsManager;

        if (!$gSettingsManager->getBool('system_notifications_enabled')) {
            return null;
        }

        $eventIds = $this->repository->getDueEventIds();
        if (count($eventIds) === 0) {
            return null;
        }

        $sent = 0;
        $processedEvents = 0;

        foreach ($eventIds as $eventId) {
            try {
                $event = new Event($this->database, $eventId);
                if ($event->deadlineExceeded() || $event->participantLimitReached()) {
                    continue;
                }

                $eventRoleId = (int) $event->getValue('dat_rol_id');
                if ($eventRoleId <= 0) {
                    continue;
                }

                $authorizedRoleIds = (new RolesRights($this->database, 'event_participation', $eventId))->getRolesIds();
                $recipients = $this->repository->getRecipientUsers($eventRoleId, $authorizedRoleIds);
                if (count($recipients) === 0) {
                    continue;
                }

                $processedEvents++;
                foreach ($recipients as $recipient) {
                    $userId = (int) $recipient['usr_id'];

                    if ($dryRun) {
                        $sent++;
                        continue;
                    }

                    if (!$this->repository->claim($eventId, $userId)) {
                        continue;
                    }

                    try {
                        if (!$this->sendReminder($event, $userId, (string) $recipient['usr_uuid'])) {
                            $this->repository->release($eventId, $userId);
                            continue;
                        }
                        $sent++;
                    } catch (Throwable $exception) {
                        $this->repository->release($eventId, $userId);
                        $this->logError(
                            'Could not send event reminder for event ID ' . $eventId
                            . ' to user ID ' . $userId . ': ' . $exception->getMessage()
                        );
                    }
                }
            } catch (Throwable $exception) {
                $this->logError(
                    'Error processing event reminder for event ID ' . $eventId . ': ' . $exception->getMessage()
                );
            }
        }

        if ($processedEvents === 0) {
            return null;
        }

        return ($dryRun ? 'Would send ' : 'Sent ') . $sent . ' reminder(s) for '
            . $processedEvents . ' event(s).';
    }

    /**
     * Run the due check for the request route and never let a failure reach the request.
     */
    public static function runIfDueSilently(Database $db): void
    {
        global $gLogger;

        try {
            (new self($db))->runIfDue();
        } catch (Throwable $exception) {
            $gLogger->warning(
                'The event reminder check failed.',
                array('message' => $exception->getMessage())
            );
        }
    }

    /**
     * Send one reminder email to the given user.
     *
     * @throws Throwable
     */
    private function sendReminder(Event $event, int $userId, string $userUuid): bool
    {
        global $gCurrentOrganization, $gL10n, $gProfileFields, $gSettingsManager, $gTimezone;

        $email = new Email();
        if ($email->addRecipientsByUser($userUuid) === 0) {
            return false;
        }

        $nameRow = $this->database->queryPrepared(
            'SELECT first_name.usd_value AS firstname, last_name.usd_value AS lastname
               FROM ' . TBL_USER_DATA . ' first_name
               JOIN ' . TBL_USER_DATA . ' last_name
                 ON last_name.usd_usr_id = first_name.usd_usr_id
              WHERE first_name.usd_usr_id = ?
                AND first_name.usd_usf_id = ?
                AND last_name.usd_usf_id = ?',
            array(
                $userId,
                (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id')
            )
        )->fetch();
        $recipientName = trim((string) ($nameRow['firstname'] ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($nameRow['lastname'] ?? ''));
        }
        if ($recipientName === '') {
            $recipientName = $gL10n->get('SYS_UNKNOWN');
        }

        $eventUrl = ADMIDIO_URL . FOLDER_MODULES . '/events.php?dat_uuid=' . $event->getValue('dat_uuid');
        $location = trim((string) $event->getValue('dat_location'));
        if ($location === '') {
            $location = $gL10n->get('SYS_UNKNOWN');
        }

        $calendar = $this->database->queryPrepared(
            'SELECT cat_name FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?',
            array((int) $event->getValue('dat_cat_id'))
        )->fetchColumn();
        $calendar = $calendar === false ? '' : (string) $calendar;
        if (Language::isTranslationStringId($calendar)) {
            $calendar = $gL10n->get($calendar);
        }
        if ($calendar === '') {
            $calendar = $gL10n->get('SYS_UNKNOWN');
        }

        $room = $gL10n->get('SYS_UNKNOWN');
        if ((int) $event->getValue('dat_room_id') > 0) {
            $roomName = $this->database->queryPrepared(
                'SELECT room_name FROM ' . TBL_ROOMS . ' WHERE room_id = ?',
                array((int) $event->getValue('dat_room_id'))
            )->fetchColumn();
            if ($roomName !== false && (string) $roomName !== '') {
                $room = (string) $roomName;
            }
        }

        $message = $gL10n->get('SYS_EVENT_REMINDER_MESSAGE', array(
            $recipientName,
            $event->getDateTimePeriod(),
            $event->getValue('dat_headline'),
            $calendar,
            $location,
            $room,
            $gTimezone !== '' ? $gTimezone : date_default_timezone_get(),
            $eventUrl
        ));

        $email->setSubject(
            $gCurrentOrganization->getValue('org_shortname') . ': '
            . $gL10n->get('SYS_EVENT_REMINDER_SUBJECT', array($event->getValue('dat_headline')))
        );

        if ($gSettingsManager->getBool('mail_html_registered_users')) {
            $email->setHtmlMail();
            $message = str_replace(
                $eventUrl,
                '<a href="' . $eventUrl . '">' . $eventUrl . '</a>',
                $message
            );
        }
        $email->setText($message);

        return $email->sendEmail();
    }

    private function logError(string $message): void
    {
        global $gLogger;

        $gLogger->error($message);
    }
}
