<?php
namespace Admidio\Infrastructure\Utils;

/**
 * @brief Class to enable and disable a generic application-wide maintenance mode.
 *
 * The maintenance state is stored in adm_my_files/maintenance.json so that it can
 * be evaluated by the early bootstrap before the Composer autoloader or any database
 * connection is initialized.
 * 
 * NOTE: All methods in this class MUST WORK WITHOUT A DB CONNECTION OR OTHER ADMIDIO CLASSES LODADED!
 *       Also, no SESSION might be available (when called through the CLI)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class MaintenanceMode
{
    private const DEFAULT_TITLE = 'Maintenance';
    private const DEFAULT_MESSAGE = 'This Admidio installation is currently in maintenance mode. Please try again later.';
    private const STATE_FILE = 'maintenance.json';

    /**
     * Enables maintenance mode.
     *
     * @param string $title          Title that is shown while maintenance mode is active
     * @param string $message        Message that is shown while maintenance mode is active
     * @param array<int,string> $allowedScripts Relative script paths that may still be executed
     * @param int $retryAfter        Number of seconds a client should wait before retrying
     * @param string $owner          Optional identifier of the operation that owns maintenance mode
     * @throws \JsonException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    public static function enable(string $title = self::DEFAULT_TITLE, string $message = self::DEFAULT_MESSAGE,
            array $allowedScripts = array(), int $retryAfter = 120, string $owner = ''
    ): void {
        if ($title === '') {
            $title = self::DEFAULT_TITLE;
        }

        if ($message === '') {
            $message = self::DEFAULT_MESSAGE;
        }

        if ($retryAfter < 1) {
            throw new \UnexpectedValueException('Maintenance mode retry interval must be greater than zero.');
        }

        $normalizedAllowedScripts = array();
        foreach ($allowedScripts as $script) {
            $normalizedAllowedScripts[] = self::normalizeScriptPath($script);
        }
        $normalizedAllowedScripts = array_values(array_unique($normalizedAllowedScripts));

        $currentState = self::getState();
        if ($currentState !== null) {
            $currentOwner = (string) ($currentState['owner'] ?? '');

            if ($currentOwner !== $owner) {
                throw new \RuntimeException('Maintenance mode is already enabled by another operation.');
            }

            return;
        }

        $state = array(
            'schema' => 1,
            'owner' => $owner,
            'startedAt' => time(),
            'retryAfter' => $retryAfter,
            'title' => $title,
            'message' => $message,
            'allowedScripts' => $normalizedAllowedScripts
        );

        $stateDirectory = ADMIDIO_PATH . FOLDER_DATA;
        FileSystemUtils::createDirectoryIfNotExists($stateDirectory);

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporaryFile = tempnam($stateDirectory, '.maintenance-');

        if ($temporaryFile === false) {
            throw new \RuntimeException('Temporary maintenance mode state could not be created.');
        }

        try {
            $bytes = file_put_contents($temporaryFile, $json . PHP_EOL, LOCK_EX);

            if ($bytes === false || !rename($temporaryFile, self::getStateFilePath())) {
                throw new \RuntimeException('Maintenance mode state could not be written.');
            }
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    /**
     * Disables maintenance mode.
     *
     * If an owner is given, the state is only removed if the owner matches the operation that
     * enabled maintenance mode.
     *
     * @param string $owner Optional identifier of the operation that owns maintenance mode
     * @return bool Returns true if maintenance mode was disabled or false if it was not enabled
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public static function disable(string $owner = ''): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        if ($owner !== '') {
            $state = self::getState();

            if ($state === null || (string) ($state['owner'] ?? '') !== $owner) {
                throw new \RuntimeException('Maintenance mode is owned by another operation.');
            }
        }

        if (!unlink(self::getStateFilePath())) {
            throw new \RuntimeException('Maintenance mode state could not be removed.');
        }

        return true;
    }

    /**
     * Returns whether maintenance mode is enabled.
     */
    public static function isEnabled(): bool
    {
        return is_file(self::getStateFilePath());
    }

    /**
     * Returns the current maintenance state.
     *
     * @return array<string,mixed>|null
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public static function getState(): ?array
    {
        $stateFile = self::getStateFilePath();

        if (!is_file($stateFile)) {
            return null;
        }

        $json = file_get_contents($stateFile);
        if ($json === false) {
            throw new \RuntimeException('Maintenance mode state could not be read.');
        }

        $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($state)) {
            throw new \RuntimeException('Maintenance mode state is invalid.');
        }

        return $state;
    }

    /**
     * Returns the absolute path of the maintenance state file.
     */
    private static function getStateFilePath(): string
    {
        return ADMIDIO_PATH . FOLDER_DATA . '/' . self::STATE_FILE;
    }

    /**
     * Normalizes and validates a script path that may bypass maintenance mode.
     *
     * @throws \UnexpectedValueException
     */
    private static function normalizeScriptPath(string $script): string
    {
        $script = ltrim(str_replace('\\', '/', trim($script)), '/');

        if (
            $script === ''
            || str_contains($script, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $script) === 1
        ) {
            throw new \UnexpectedValueException('Invalid maintenance mode script path.');
        }

        return $script;
    }
}
