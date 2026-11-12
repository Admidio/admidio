<?php
namespace Admidio\Infrastructure\Utils;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;

/**
 * @brief Class to implement useful method maintenance of data in the Admidio database.
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Maintenance
{
    /**
     * @var Database A database object with an existing database connection
     */
    private Database $database;

    /**
     * @param Database $database Object of the database that should be checked. A connection should be established.
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Reorganize the sequence of all categories. They will be ordered within each type. First, all categories with no
     * organization and then all categories with an organization will be sorted. The sequence number of categories
     * from each organization will start after the sequence of categories without an organization.
     * The current sequence of the categories will be considered.
     * @return void
     * @throws Exception
     */
    public function reorganizeCategories(): void
    {
        $currentCategoryType = '';
        $currentOrganization = 0;
        $newSequenceBase = 0;
        $newSequenceOrganization = 0;

        $sql = 'SELECT cat.*
                  FROM ' . TBL_CATEGORIES . ' cat
                 ORDER BY cat_type, cat_org_id, cat_sequence';
        $categoryStatement = $this->database->queryPrepared($sql);

        while ($row = $categoryStatement->fetch()) {
            if ($currentCategoryType != $row['cat_type']) {
                $newSequenceBase = 1;
                $newSequenceOrganization = 1;
                $currentCategoryType = $row['cat_type'];
                $currentOrganization = (int) $row['cat_org_id'];
            } elseif ($currentOrganization !== (int) $row['cat_org_id']) {
                $newSequenceOrganization = $newSequenceBase;
                $currentOrganization = (int) $row['cat_org_id'];
            }

            $category = new Category($this->database);
            $category->setArray($row);
            $category->setValue('cat_sequence', $newSequenceOrganization);
            $category->save();

            if ((int) $row['cat_org_id'] === 0) {
                $newSequenceBase++;
            }
            $newSequenceOrganization++;
        }
    }

    /**
     * Repair the path of every descendant folder using its parent folder's path and name.
     * The root paths of all organizations remain unchanged.
     * @return void
     * @throws Exception
     */
    public function repairDocumentsFilesPath(): void
    {
        $sql = 'SELECT fol_id, fol_name, fol_path
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_fol_id_parent IS NULL ';
        $rootFolders = $this->database->queryPrepared($sql)->fetchAll();
        $childSql = 'SELECT fol_id, fol_name, fol_path
                       FROM ' . TBL_FOLDERS . '
                      WHERE fol_fol_id_parent = ?';
        $updateSql = 'UPDATE ' . TBL_FOLDERS . ' SET fol_path = ? WHERE fol_id = ?';

        foreach ($rootFolders as $rowRootFolder) {
            // Maintenance runs across organizations, so folder entities cannot be loaded here:
            // Folder::readData() only accepts folders of the current organization.
            $folders = array($rowRootFolder);

            while ($folders !== array()) {
                $parent = array_pop($folders);
                $parentFullPath = $parent['fol_path'] . '/' . $parent['fol_name'];

                $children = $this->database->queryPrepared($childSql, array($parent['fol_id']))->fetchAll();

                foreach ($children as $folder) {
                    if ($folder['fol_path'] !== $parentFullPath) {
                        $this->database->queryPrepared($updateSql, array($parentFullPath, $folder['fol_id']));
                    }

                    $folder['fol_path'] = $parentFullPath;
                    $folders[] = $folder;
                }
            }
        }
    }

    /**
     * Send reminder emails for upcoming events that are scheduled for reminder today.
     * @return string Returns summary message of sent reminders.
     * @throws Exception If the database query fails.
     * @throws \RuntimeException If the lock file cannot be created.
     */
    public function sendEventReminders(): string
    {
        $currentDateTime = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $stateFile = ADMIDIO_PATH . FOLDER_DATA . '/cron_reminders_' . $today . '.json';
        $lockFile = ADMIDIO_PATH . FOLDER_DATA . '/cron_reminders.lock';

        $lockHandle = @fopen($lockFile, 'c+');
        if ($lockHandle === false) {
            throw new \RuntimeException(
                'Could not open the event reminder lock file ' . $lockFile
                . '. Please check that the cron job can write to ' . ADMIDIO_PATH . FOLDER_DATA . '.'
            );
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            @fclose($lockHandle);
            return 'Event reminders already running or locked.';
        }

        try {
            $this->cleanupReminderStateFiles();

            $processedEventIds = array();
            if (is_file($stateFile)) {
                $content = @file_get_contents($stateFile);
                $decoded = $content === false ? null : json_decode($content, true);
                if (is_array($decoded)) {
                    $processedEventIds = array_map('intval', $decoded);
                }
            }

            // Don't filter cancelled events in SQL: the column dat_recurrence_status only
            // exists since Admidio 5.1. Filter them in PHP instead so this also works on 5.0.x.
            $sql = 'SELECT dat_id
                      FROM ' . TBL_EVENTS . '
                     WHERE dat_begin >= ?';
            $stmt = $this->database->queryPrepared($sql, array($currentDateTime));
            $totalSent = 0;

            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $eventId = (int)$row['dat_id'];
                    if (in_array($eventId, $processedEventIds, true)) {
                        continue;
                    }

                    try {
                        $event = new \Admidio\Events\Entity\Event($this->database, $eventId);

                        // On Admidio < 5.1 the column doesn't exist and getValue() returns an empty string.
                        if ((string)$event->getValue('dat_recurrence_status') === 'cancelled') {
                            continue;
                        }

                        $sent = $event->sendReminderNotification();
                    } catch (\Throwable $e) {
                        // Do not mark the event as processed. Otherwise a temporary failure would
                        // suppress all further reminder attempts for this event on the same day.
                        $this->logReminderError(
                            'Error sending event reminder for event ID ' . $eventId . ': ' . $e->getMessage()
                        );
                        continue;
                    }

                    // Only events that actually sent at least one reminder are written to the state
                    // file. Events that are not due today are cheap to check again on the next run.
                    if ($sent <= 0) {
                        continue;
                    }

                    $totalSent += $sent;
                    $processedEventIds[] = $eventId;
                    $this->writeReminderState($stateFile, $processedEventIds);
                }
            }

            return 'Sent ' . $totalSent . ' event reminder(s).';
        } finally {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    /**
     * Persist the event IDs that already sent reminder emails today.
     * @param string $stateFile Path of the state file.
     * @param array<int,int> $processedEventIds Event IDs that were processed successfully.
     * @return void
     */
    private function writeReminderState(string $stateFile, array $processedEventIds): void
    {
        $tmpFile = $stateFile . '.tmp';
        $encoded = json_encode($processedEventIds);
        if ($encoded === false) {
            $this->logReminderError('Could not encode event reminder state for ' . $stateFile);
            return;
        }

        if (@file_put_contents($tmpFile, $encoded) === false) {
            $this->logReminderError('Could not write event reminder state file ' . $tmpFile);
            return;
        }

        if (!@rename($tmpFile, $stateFile)) {
            $this->logReminderError('Could not replace event reminder state file ' . $stateFile);
            @unlink($tmpFile);
        }
    }

    /**
     * Remove event reminder state files that are older than 30 days.
     * @return void
     */
    private function cleanupReminderStateFiles(): void
    {
        $files = glob(ADMIDIO_PATH . FOLDER_DATA . '/cron_reminders_*');
        if (!is_array($files)) {
            return;
        }

        $threshold = time() - 30 * 86400;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $modified = @filemtime($file);
            if ($modified !== false && $modified < $threshold) {
                @unlink($file);
            }
        }
    }

    /**
     * Write a message about a failed event reminder operation to the Admidio log.
     * @param string $message Message to log.
     * @return void
     */
    private function logReminderError(string $message): void
    {
        if (isset($GLOBALS['gLogger'])) {
            $GLOBALS['gLogger']->error($message);
        }
    }
}
