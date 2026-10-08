<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The brake on guessing the updater login: after MAX_FAILURES failed logins within WINDOW seconds,
 * no login is accepted for BLOCK seconds - not even a correct one.
 *
 * The count is kept for the whole installation and not per address, because an attacker can change
 * the address and the administrator does not need to log in to the updater often.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Throttle
{
    public const MAX_FAILURES = 5;
    public const WINDOW = 900;
    public const BLOCK = 900;

    /**
     * @param string $file The file that holds the count, adm_my_files/core-update/login-throttle.json.
     */
    public function __construct(private readonly string $file)
    {
    }

    /**
     * The time until which logins are blocked, or 0.
     * @param int $now
     * @return int
     */
    public function getBlockedUntil(int $now): int
    {
        $blockedUntil = (int)($this->read()['blockedUntil'] ?? 0);

        return $blockedUntil > $now ? $blockedUntil : 0;
    }

    /**
     * Count a failed login, and block the login once there were too many.
     * @param int $now
     * @return void
     */
    public function recordFailure(int $now): void
    {
        $this->change(static function (array $data) use ($now): array {
            $failures = array_values(array_filter((array)($data['failures'] ?? array()),
                static fn(mixed $time): bool => is_int($time) && $time > $now - self::WINDOW));
            $failures[] = $now;

            $data['failures'] = $failures;
            if (count($failures) >= self::MAX_FAILURES) {
                $data['blockedUntil'] = $now + self::BLOCK;
                $data['failures'] = array();
            }

            return $data;
        });
    }

    /**
     * Forget the failures after a successful login.
     * @return void
     */
    public function reset(): void
    {
        $this->change(static fn(array $data): array => array('blockedUntil' => (int)($data['blockedUntil'] ?? 0)));
    }

    /**
     * @return array<string,mixed>
     */
    private function read(): array
    {
        $data = is_file($this->file) ? json_decode((string)file_get_contents($this->file), true) : null;

        return is_array($data) ? $data : array();
    }

    /**
     * Change the count under an exclusive lock, so that parallel attempts are all counted.
     * @param callable(array<string,mixed>): array<string,mixed> $change
     * @return void
     */
    private function change(callable $change): void
    {
        if (!is_dir(dirname($this->file))) {
            @mkdir(dirname($this->file), 0770, true);
        }

        $handle = @fopen($this->file, 'c+');
        if ($handle === false) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        try {
            flock($handle, LOCK_EX);
            $data = json_decode((string)stream_get_contents($handle), true);
            $data = $change(is_array($data) ? $data : array());

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
