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

    /**
     * Run the due‑event check and optionally send reminders.
     *
     * When $dryRun is true the method only determines which events are due
     * and returns a summary string – no e‑mail is sent and no claim rows are
     * written. This is intended for the CLI command
     * `event:send-reminders --dry-run`.
     *
     * When $dryRun is false the method behaves like {@see sendDueReminders()}
     * (but without the file‑based lock – the claim/release mechanism in the
     * repository takes care of concurrency).
     *
     * @return string|false Summary message or false if nothing is due.
     * @throws Throwable If an unexpected error occurs while processing a single event.
     *
     * @deprecated Will be removed after the maintenance‑job infrastructure from
     *             PR #2310 is merged; the lock‑file based {@see sendDueReminders()}
     *             will then be replaced completely.
     */
    public function runIfDue(bool $dryRun = false): string|false
    {
        if ($dryRun) {
            $dueIds = $this->repository->getDueEventIds();
            if ($dueIds === []) {
                return false;
            }
            // Build a simple list for display – the real UI would use the
            // translation system, but for a quick CLI hint this is enough.
            $list = implode(', ', array_map(static fn($id) => 'Event '.$id, $dueIds));
            return 'The following events are due for reminders: ' . $list . '.';
        }

        // Non‑dry‑run path – delegate to the existing implementation,
        // but we skip the lock because the repository’s claim/release
        // mechanism now handles concurrency.
        // (In a full re‑base after #2310 this would be the new canonical method.)
        return $this->sendDueReminders();
    }

    private function logError(string $message): void
    {
        if (isset($GLOBALS['gLogger'])) {
            $GLOBALS['gLogger']->error($message);
        }
    }
}
