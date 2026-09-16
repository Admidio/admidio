<?php
namespace Admidio\Infrastructure\Cli;

/**
 ***********************************************************************************************
 * The system account that the current command-line process runs as.
 *
 * The account is read from the operating system and never from the environment: USER, LOGNAME and
 * USERNAME are set by the caller, so a restriction that trusted them could be lifted with a single
 * assignment. On a system with ext-posix the effective user id is used, which is also the correct
 * notion for "sudo -u cron admidio ..."; on Windows the account is read from whoami.exe, which
 * reports the access token the process actually holds.
 *
 * If neither is available the account is unknown. CliPolicy refuses the command in that case,
 * rather than treating the caller as "not on the list" or trusting the environment after all.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliSystemAccount
{
    /**
     * Resolved account of this process, or null while it has not been resolved yet.
     */
    private static ?CliSystemAccount $current = null;

    /**
     * Name of the account, empty when it could not be determined.
     */
    private string $name = '';

    /**
     * Numeric user id, null on a system that has none or when it could not be determined.
     */
    private ?int $id = null;

    /**
     * Every name-like identifier of the account: its name, on Windows also the name without the
     * domain prefix and the security identifier.
     *
     * @var array<int,string>
     */
    private array $names = array();

    /**
     * Names and security identifiers of the groups the account belongs to, supplementary groups
     * included.
     *
     * @var array<int,string>
     */
    private array $groupNames = array();

    /**
     * Numeric ids of the groups the account belongs to.
     *
     * @var array<int,int>
     */
    private array $groupIds = array();

    /**
     * How the account was determined: "posix", "windows" or "" when it is unknown.
     */
    private string $source = '';

    /**
     * Path of whoami.exe while the groups of a Windows account have not been read yet.
     *
     * Reading them starts a second process, and most invocations never ask for a group: only a
     * "%group" entry of $gCliAllowedUsers does. The names are therefore read on first use.
     */
    private ?string $pendingGroupProgram = null;

    /**
     * The account of this process, resolved once.
     */
    public static function current(): CliSystemAccount
    {
        if (self::$current === null) {
            self::$current = self::resolve();
        }

        return self::$current;
    }

    /**
     * Use the given account instead of the one of this process.
     *
     * Only the tests need this: they have to assert the behaviour for accounts that the process
     * running the test suite does not have. Pass null to return to the real account.
     */
    public static function useForTests(?CliSystemAccount $account): void
    {
        self::$current = $account;
    }

    /**
     * Build an account out of known values, used by the tests and by the resolution below.
     *
     * @param array<int,string> $names Name and further name-like identifiers of the account.
     * @param array<int,string> $groupNames Names and identifiers of its groups.
     * @param array<int,int> $groupIds Numeric ids of its groups.
     */
    public static function fromValues(
        string $name,
        ?int $id,
        array $names = array(),
        array $groupNames = array(),
        array $groupIds = array(),
        string $source = 'posix'
    ): CliSystemAccount {
        $account = new self();
        $account->name = $name;
        $account->id = $id;
        $account->names = self::uniqueStrings(array_merge(array($name), $names));
        $account->groupNames = self::uniqueStrings($groupNames);
        $account->groupIds = array_values(array_unique($groupIds));
        $account->source = $source;

        return $account;
    }

    /**
     * Whether the account could be determined at all.
     */
    public function isKnown(): bool
    {
        return $this->source !== '' && ($this->name !== '' || $this->id !== null);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<int,string>
     */
    public function groupNames(): array
    {
        $this->resolveGroups();

        return $this->groupNames;
    }

    /**
     * @return array<int,int>
     */
    public function groupIds(): array
    {
        $this->resolveGroups();

        return $this->groupIds;
    }

    /**
     * The account in the notation used by the log and by the messages of the command line.
     */
    public function description(): string
    {
        if (!$this->isKnown()) {
            return 'unknown';
        }

        if ($this->name !== '' && $this->id !== null) {
            return $this->name . '(' . $this->id . ')';
        }

        return $this->name !== '' ? $this->name : (string)$this->id;
    }

    /**
     * Whether one entry of $gCliAllowedUsers addresses this account.
     *
     * A token that starts with "%" names a group, everything else names a user. A numeric token is
     * an id, every other token is a name. Names are compared without regard to case, because the
     * account names of Windows are not case-sensitive and a Unix name is normally lower case
     * anyway.
     */
    public function matches(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        if (str_starts_with($token, '%')) {
            $group = trim(substr($token, 1));
            if ($group === '') {
                return false;
            }

            if (ctype_digit($group)) {
                return in_array((int)$group, $this->groupIds(), true);
            }

            return self::containsName($this->groupNames(), $group);
        }

        if (ctype_digit($token)) {
            return $this->id !== null && (int)$token === $this->id;
        }

        return self::containsName($this->names, $token);
    }

    /**
     * Read the account of this process from the operating system.
     */
    private static function resolve(): CliSystemAccount
    {
        $account = self::resolveThroughPosix();
        if ($account !== null) {
            return $account;
        }

        $account = self::resolveThroughWindows();
        if ($account !== null) {
            return $account;
        }

        return new self();
    }

    /**
     * Read the account through ext-posix.
     */
    private static function resolveThroughPosix(): ?CliSystemAccount
    {
        if (!function_exists('posix_geteuid')) {
            return null;
        }

        $id = posix_geteuid();
        $name = '';
        $groupIds = array();

        if (function_exists('posix_getpwuid')) {
            $passwd = posix_getpwuid($id);
            if (is_array($passwd)) {
                $name = (string)($passwd['name'] ?? '');
                if (isset($passwd['gid'])) {
                    $groupIds[] = (int)$passwd['gid'];
                }
            }
        }

        if (function_exists('posix_getegid')) {
            $groupIds[] = posix_getegid();
        }

        if (function_exists('posix_getgroups')) {
            foreach (posix_getgroups() as $groupId) {
                $groupIds[] = (int)$groupId;
            }
        }

        $groupIds = array_values(array_unique($groupIds));
        $groupNames = array();

        if (function_exists('posix_getgrgid')) {
            foreach ($groupIds as $groupId) {
                $group = posix_getgrgid($groupId);
                if (is_array($group) && ($group['name'] ?? '') !== '') {
                    $groupNames[] = (string)$group['name'];
                }
            }
        }

        return self::fromValues($name, $id, array(), $groupNames, $groupIds, 'posix');
    }

    /**
     * Read the account through whoami.exe.
     *
     * The program is called by its absolute path below %SystemRoot%, never through PATH, so that a
     * whoami.exe placed in the working directory or in a directory of the caller cannot answer
     * instead of the one of the system.
     */
    private static function resolveThroughWindows(): ?CliSystemAccount
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return null;
        }

        $systemRoot = (string)getenv('SystemRoot');
        if ($systemRoot === '') {
            $systemRoot = 'C:\\Windows';
        }

        $whoami = $systemRoot . '\\System32\\whoami.exe';
        if (!is_file($whoami)) {
            return null;
        }

        $user = self::runWhoami($whoami, '/user /fo csv /nh');
        if ($user === null) {
            return null;
        }

        $names = array();
        $identifier = '';
        foreach (str_getcsv($user, ',', '"', '') as $field) {
            $field = trim((string)$field);
            if ($field === '') {
                continue;
            }
            if (str_starts_with($field, 'S-1-')) {
                $identifier = $field;
                continue;
            }
            $names[] = $field;
            // "machine\reinhold" should also be addressable as "reinhold".
            $separator = strrpos($field, '\\');
            if ($separator !== false) {
                $names[] = substr($field, $separator + 1);
            }
        }

        if ($names === array()) {
            return null;
        }

        $name = array_shift($names);
        if ($identifier !== '') {
            $names[] = $identifier;
        }

        $account = self::fromValues((string)$name, null, $names, array(), array(), 'windows');
        $account->pendingGroupProgram = $whoami;

        return $account;
    }

    /**
     * Read the groups of the Windows account, at most once.
     */
    private function resolveGroups(): void
    {
        if ($this->pendingGroupProgram === null) {
            return;
        }

        $program = $this->pendingGroupProgram;
        $this->pendingGroupProgram = null;

        $groups = self::runWhoami($program, '/groups /fo csv /nh');
        if ($groups === null) {
            return;
        }

        $groupNames = array();

        foreach (preg_split('/\r\n|\n|\r/', $groups) ?: array() as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line, ',', '"', '');
            $name = trim((string)($fields[0] ?? ''));
            if ($name !== '') {
                $groupNames[] = $name;
                $separator = strrpos($name, '\\');
                if ($separator !== false) {
                    $groupNames[] = substr($name, $separator + 1);
                }
            }

            foreach ($fields as $field) {
                $field = trim((string)$field);
                if (str_starts_with($field, 'S-1-')) {
                    $groupNames[] = $field;
                }
            }
        }

        $this->groupNames = self::uniqueStrings($groupNames);
    }

    /**
     * Run whoami.exe and return its output, or null if it could not be executed.
     */
    private static function runWhoami(string $program, string $arguments): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return null;
        }

        $output = @shell_exec('"' . $program . '" ' . $arguments . ' 2>NUL');
        if (!is_string($output)) {
            return null;
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    /**
     * Whether the list contains the name, compared without regard to case.
     *
     * @param array<int,string> $names
     */
    private static function containsName(array $names, string $name): bool
    {
        foreach ($names as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private static function uniqueStrings(array $values): array
    {
        $unique = array();

        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '' && !self::containsName($unique, $value)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }
}
