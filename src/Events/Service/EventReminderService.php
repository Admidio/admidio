<?php
namespace Admidio\Events\Service;

use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventReminderRepository;
use Admidio\Infrastructure\Database;
use RuntimeException;
use Throwable;

/**
 * Send reminder emails for upcoming events.
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
     * Send all reminders that are due today.
     * @return string Summary message of the sent reminders.
     * @throws RuntimeException If the lock file cannot be created.
     * @throws Throwable If an unexpected error occurs outside a single event.
     */
    public function sendDueReminders(): string
    {
        $lockFile = ADMIDIO_PATH . FOLDER_TEMP_DATA . '/event_reminders.lock';
        $lockHandle = @fopen($lockFile, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException(
                'Could not open the event reminder lock file ' . $lockFile
                . '. Please check that the cron job can write to ' . ADMIDIO_PATH . FOLDER_TEMP_DATA . '.'
            );
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            @fclose($lockHandle);
            return 'Event reminders already running or locked.';
        }

        try {
            $totalSent = 0;

            foreach ($this->repository->getDueEventIds() as $eventId) {
                try {
                    $event = new Event($this->database, $eventId);
                    $totalSent += $event->sendReminderNotification();
                } catch (Throwable $e) {
                    $this->logError(
                        'Error sending event reminder for event ID ' . $eventId . ': ' . $e->getMessage()
                    );
                }
            }

            return 'Sent ' . $totalSent . ' event reminder(s).';
        } finally {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    private function logError(string $message): void
    {
        if (isset($GLOBALS['gLogger'])) {
            $GLOBALS['gLogger']->error($message);
        }
    }
}
