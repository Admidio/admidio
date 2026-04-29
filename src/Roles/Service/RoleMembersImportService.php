<?php
namespace Admidio\Roles\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Users\Entity\User;
use DateTime;

/**
 * @brief Service class for importing members into a role
 *
 * This class handles the complete import workflow for role members including:
 * - File parsing
 * - Data validation
 * - Duplicate detection
 * - User matching
 * - Import preview
 * - Actual import execution
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RoleMembersImportService
{
    public const IDENTIFY_BY_UUID = 'uuid';
    public const IDENTIFY_BY_EMAIL = 'email';
    public const IDENTIFY_BY_NAME = 'name';
    public const IDENTIFY_BY_LOGIN = 'login';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_WARNING = 'warning';
    public const RESULT_ERROR = 'error';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_ALREADY_MEMBER = 'already_member';

    protected Database $db;
    protected Role $role;
    protected array $parsedData = [];
    protected array $headers = [];
    protected string $identifyMethod = self::IDENTIFY_BY_EMAIL;
    protected array $fieldMapping = [];
    protected array $validationResults = [];
    protected array $importPreview = [];
    protected int $countSuccess = 0;
    protected int $countErrors = 0;
    protected int $countWarnings = 0;
    protected int $countDuplicates = 0;
    protected array $importLog = [];

    /**
     * Constructor
     * @param Database $database
     * @param Role $role
     */
    public function __construct(Database $database, Role $role)
    {
        $this->db = $database;
        $this->role = $role;
    }

    /**
     * Set the parsed data from file
     * @param array $data
     * @param array $headers
     */
    public function setParsedData(array $data, array $headers): void
    {
        $this->parsedData = $data;
        $this->headers = $headers;
    }

    /**
     * Set the method to identify users
     * @param string $method
     */
    public function setIdentifyMethod(string $method): void
    {
        $this->identifyMethod = $method;
    }

    /**
     * Set field mapping from import columns to Admidio fields
     * @param array $mapping
     */
    public function setFieldMapping(array $mapping): void
    {
        $this->fieldMapping = $mapping;
    }

    /**
     * Auto-detect field mapping based on headers
     * @return array
     */
    public function autoDetectFieldMapping(): array
    {
        $mapping = [];
        $headerLower = array_map('strtolower', $this->headers);

        $fieldPatterns = [
            'usr_uuid' => ['uuid', 'user uuid', 'usr_uuid', 'user id'],
            'email' => ['email', 'e-mail', 'mail', 'e mail'],
            'LAST_NAME' => ['last name', 'lastname', 'nachname', 'name'],
            'FIRST_NAME' => ['first name', 'firstname', 'vorname', 'given name'],
            'usr_login_name' => ['login', 'username', 'user name', 'login name'],
            'leader' => ['leader', 'is_leader', 'role leader', 'gruppenleiter'],
        ];

        foreach ($headerLower as $index => $header) {
            foreach ($fieldPatterns as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($header, $pattern) || $header === $pattern) {
                        $mapping[$index] = $field;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Get data rows from parsed data
     * @param bool $firstRowIsHeader
     * @return array
     */
    public function getDataRows(bool $firstRowIsHeader = true): array
    {
        if ($firstRowIsHeader && count($this->parsedData) > 1) {
            return array_slice($this->parsedData, 1);
        }
        return $this->parsedData;
    }

    /**
     * Validate all import data
     * @return array Validation results
     * @throws Exception
     */
    public function validate(): array
    {
        $this->validationResults = [];
        $this->countErrors = 0;
        $this->countWarnings = 0;
        $this->countDuplicates = 0;

        $dataRows = $this->getDataRows(true);
        $seenIdentifiers = [];

        foreach ($dataRows as $rowIndex => $row) {
            $result = $this->validateRow($row, $rowIndex, $seenIdentifiers);
            $this->validationResults[] = $result;

            if ($result['status'] === self::RESULT_ERROR) {
                $this->countErrors++;
            } elseif ($result['status'] === self::RESULT_WARNING) {
                $this->countWarnings++;
            } elseif ($result['status'] === self::RESULT_DUPLICATE) {
                $this->countDuplicates++;
            }
        }

        return $this->validationResults;
    }

    /**
     * Validate a single row
     * @param array $row
     * @param int $rowIndex
     * @param array $seenIdentifiers
     * @return array
     * @throws Exception
     */
    protected function validateRow(array $row, int $rowIndex, array &$seenIdentifiers): array
    {
        global $gProfileFields;

        $result = [
            'row_index' => $rowIndex,
            'row_data' => $row,
            'status' => self::RESULT_SUCCESS,
            'messages' => [],
            'user_id' => null,
            'user_uuid' => null,
            'user_name' => '',
            'is_leader' => false,
            'is_already_member' => false,
        ];

        $identifier = $this->getIdentifierFromRow($row);

        if (empty($identifier)) {
            $result['status'] = self::RESULT_ERROR;
            $result['messages'][] = 'No identifier found for user identification';
            return $result;
        }

        $identifierKey = $this->identifyMethod . ':' . $identifier;
        if (isset($seenIdentifiers[$identifierKey])) {
            $result['status'] = self::RESULT_DUPLICATE;
            $result['messages'][] = 'Duplicate entry in import file (same identifier as row ' . $seenIdentifiers[$identifierKey] . ')';
            return $result;
        }
        $seenIdentifiers[$identifierKey] = $rowIndex;

        $user = $this->findUser($row);

        if (!$user) {
            $result['status'] = self::RESULT_NOT_FOUND;
            $result['messages'][] = 'User not found in system';
            return $result;
        }

        $result['user_id'] = (int)$user->getValue('usr_id');
        $result['user_uuid'] = $user->getValue('usr_uuid');
        $result['user_name'] = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');

        $result['is_leader'] = $this->getLeaderFlagFromRow($row);

        if ($this->isUserAlreadyMember((int)$user->getValue('usr_id'))) {
            $result['status'] = self::RESULT_ALREADY_MEMBER;
            $result['is_already_member'] = true;
            $result['messages'][] = 'User is already a member of this role';
        }

        return $result;
    }

    /**
     * Get identifier from row based on identification method
     * @param array $row
     * @return string
     */
    protected function getIdentifierFromRow(array $row): string
    {
        $identifier = '';

        switch ($this->identifyMethod) {
            case self::IDENTIFY_BY_UUID:
                $uuidIndex = $this->findFieldIndex('usr_uuid');
                if ($uuidIndex !== null && isset($row[$uuidIndex])) {
                    $identifier = trim($row[$uuidIndex]);
                }
                break;

            case self::IDENTIFY_BY_EMAIL:
                $emailIndex = $this->findFieldIndex('email');
                if ($emailIndex !== null && isset($row[$emailIndex])) {
                    $identifier = trim($row[$emailIndex]);
                }
                break;

            case self::IDENTIFY_BY_LOGIN:
                $loginIndex = $this->findFieldIndex('usr_login_name');
                if ($loginIndex !== null && isset($row[$loginIndex])) {
                    $identifier = trim($row[$loginIndex]);
                }
                break;

            case self::IDENTIFY_BY_NAME:
                $lastNameIndex = $this->findFieldIndex('LAST_NAME');
                $firstNameIndex = $this->findFieldIndex('FIRST_NAME');
                if ($lastNameIndex !== null && $firstNameIndex !== null &&
                    isset($row[$lastNameIndex]) && isset($row[$firstNameIndex])) {
                    $identifier = trim($row[$firstNameIndex]) . '|' . trim($row[$lastNameIndex]);
                }
                break;
        }

        return $identifier;
    }

    /**
     * Find field index by field name
     * @param string $fieldName
     * @return int|null
     */
    protected function findFieldIndex(string $fieldName): ?int
    {
        if (!empty($this->fieldMapping)) {
            foreach ($this->fieldMapping as $index => $mappedField) {
                if ($mappedField === $fieldName) {
                    return (int)$index;
                }
            }
        }

        $headerLower = array_map('strtolower', $this->headers);
        $fieldLower = strtolower($fieldName);

        foreach ($headerLower as $index => $header) {
            if (str_contains($header, $fieldLower) || $header === $fieldLower) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Get leader flag from row
     * @param array $row
     * @return bool
     */
    protected function getLeaderFlagFromRow(array $row): bool
    {
        $leaderIndex = $this->findFieldIndex('leader');
        if ($leaderIndex === null || !isset($row[$leaderIndex])) {
            return false;
        }

        $value = strtolower(trim($row[$leaderIndex]));
        return in_array($value, ['1', 'true', 'yes', 'y', 'ja', 'x', 'leader']);
    }

    /**
     * Find user based on identification method
     * @param array $row
     * @return User|null
     * @throws Exception
     */
    public function findUser(array $row): ?User
    {
        global $gProfileFields;

        switch ($this->identifyMethod) {
            case self::IDENTIFY_BY_UUID:
                return $this->findUserByUuid($row);

            case self::IDENTIFY_BY_EMAIL:
                return $this->findUserByEmail($row);

            case self::IDENTIFY_BY_LOGIN:
                return $this->findUserByLogin($row);

            case self::IDENTIFY_BY_NAME:
                return $this->findUserByName($row);

            default:
                return null;
        }
    }

    /**
     * Find user by UUID
     * @param array $row
     * @return User|null
     * @throws Exception
     */
    protected function findUserByUuid(array $row): ?User
    {
        global $gProfileFields;

        $uuidIndex = $this->findFieldIndex('usr_uuid');
        if ($uuidIndex === null || !isset($row[$uuidIndex]) || trim($row[$uuidIndex]) === '') {
            return null;
        }

        $user = new User($this->db, $gProfileFields);
        if ($user->readDataByUuid(trim($row[$uuidIndex]))) {
            return $user;
        }

        return null;
    }

    /**
     * Find user by email
     * @param array $row
     * @return User|null
     * @throws Exception
     */
    protected function findUserByEmail(array $row): ?User
    {
        global $gProfileFields;

        $emailIndex = $this->findFieldIndex('email');
        if ($emailIndex === null || !isset($row[$emailIndex]) || trim($row[$emailIndex]) === '') {
            return null;
        }

        $email = trim($row[$emailIndex]);

        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_USER_DATA . '
                    ON usd_usr_id = usr_id
                   AND usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
                   AND LOWER(usd_value) = LOWER(?)
                 WHERE usr_valid = true';

        $statement = $this->db->queryPrepared($sql, [
            $gProfileFields->getProperty('EMAIL', 'usf_id'),
            $email
        ]);

        $userId = $statement->fetchColumn();
        if ($userId) {
            $user = new User($this->db, $gProfileFields, (int)$userId);
            return $user;
        }

        return null;
    }

    /**
     * Find user by login name
     * @param array $row
     * @return User|null
     * @throws Exception
     */
    protected function findUserByLogin(array $row): ?User
    {
        global $gProfileFields;

        $loginIndex = $this->findFieldIndex('usr_login_name');
        if ($loginIndex === null || !isset($row[$loginIndex]) || trim($row[$loginIndex]) === '') {
            return null;
        }

        $login = trim($row[$loginIndex]);

        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_valid = true
                   AND LOWER(usr_login_name) = LOWER(?)';

        $statement = $this->db->queryPrepared($sql, [$login]);
        $userId = $statement->fetchColumn();

        if ($userId) {
            $user = new User($this->db, $gProfileFields, (int)$userId);
            return $user;
        }

        return null;
    }

    /**
     * Find user by first and last name
     * @param array $row
     * @return User|null
     * @throws Exception
     */
    protected function findUserByName(array $row): ?User
    {
        global $gProfileFields;

        $lastNameIndex = $this->findFieldIndex('LAST_NAME');
        $firstNameIndex = $this->findFieldIndex('FIRST_NAME');

        if ($lastNameIndex === null || $firstNameIndex === null ||
            !isset($row[$lastNameIndex]) || !isset($row[$firstNameIndex]) ||
            trim($row[$lastNameIndex]) === '' || trim($row[$firstNameIndex]) === '') {
            return null;
        }

        $firstName = trim($row[$firstNameIndex]);
        $lastName = trim($row[$lastNameIndex]);

        $sql = 'SELECT MAX(usr_id) AS usr_id
                  FROM ' . TBL_USERS . '
            INNER JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ?
                   AND last_name.usd_value = ?
            INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ?
                   AND first_name.usd_value = ?
                 WHERE usr_valid = true';

        $statement = $this->db->queryPrepared($sql, [
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $lastName,
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $firstName
        ]);

        $userId = $statement->fetchColumn();
        if ($userId) {
            $user = new User($this->db, $gProfileFields, (int)$userId);
            return $user;
        }

        return null;
    }

    /**
     * Check if user is already a member of the role
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function isUserAlreadyMember(int $userId): bool
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ?
                   AND mem_usr_id = ?
                   AND mem_begin <= ?
                   AND mem_end > ?';

        $statement = $this->db->queryPrepared($sql, [
            (int)$this->role->getValue('rol_id'),
            $userId,
            DATE_NOW,
            DATE_NOW
        ]);

        return (int)$statement->fetchColumn() > 0;
    }

    /**
     * Generate import preview
     * @return array
     * @throws Exception
     */
    public function getPreview(): array
    {
        if (empty($this->validationResults)) {
            $this->validate();
        }

        $this->importPreview = [
            'role_name' => $this->role->getValue('rol_name'),
            'role_uuid' => $this->role->getValue('rol_uuid'),
            'total_rows' => count($this->getDataRows(true)),
            'count_success' => 0,
            'count_warnings' => $this->countWarnings,
            'count_errors' => $this->countErrors,
            'count_duplicates' => $this->countDuplicates,
            'count_already_member' => 0,
            'count_not_found' => 0,
            'results' => [],
        ];

        foreach ($this->validationResults as $result) {
            $previewItem = [
                'row_index' => $result['row_index'],
                'row_data' => $result['row_data'],
                'status' => $result['status'],
                'messages' => $result['messages'],
                'user_id' => $result['user_id'],
                'user_uuid' => $result['user_uuid'],
                'user_name' => $result['user_name'],
                'is_leader' => $result['is_leader'],
                'can_import' => false,
            ];

            if ($result['status'] === self::RESULT_SUCCESS || $result['status'] === self::RESULT_WARNING) {
                $previewItem['can_import'] = true;
                $this->importPreview['count_success']++;
            } elseif ($result['status'] === self::RESULT_ALREADY_MEMBER) {
                $previewItem['can_import'] = true;
                $this->importPreview['count_already_member']++;
            } elseif ($result['status'] === self::RESULT_NOT_FOUND) {
                $this->importPreview['count_not_found']++;
            }

            $this->importPreview['results'][] = $previewItem;
        }

        return $this->importPreview;
    }

    /**
     * Execute the import
     * @param array $selectedRows Optional array of row indices to import
     * @return array Import results
     * @throws Exception
     */
    public function executeImport(array $selectedRows = []): array
    {
        global $gCurrentSession;

        if (empty($this->validationResults)) {
            $this->validate();
        }

        $this->importLog = [];
        $this->countSuccess = 0;
        $this->countErrors = 0;

        $this->db->startTransaction();

        try {
            foreach ($this->validationResults as $result) {
                if (!empty($selectedRows) && !in_array($result['row_index'], $selectedRows)) {
                    continue;
                }

                if ($result['user_id'] === null) {
                    $this->logResult($result, self::RESULT_ERROR, 'User not found');
                    continue;
                }

                $this->importMembership($result);
            }

            $this->db->endTransaction();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return [
            'count_success' => $this->countSuccess,
            'count_errors' => $this->countErrors,
            'log' => $this->importLog,
        ];
    }

    /**
     * Import membership for a single user
     * @param array $result
     * @throws Exception
     */
    protected function importMembership(array $result): void
    {
        $userId = $result['user_id'];
        $isLeader = $result['is_leader'];
        $userName = $result['user_name'];

        try {
            $this->role->startMembership($userId, $isLeader);

            $this->logResult($result, self::RESULT_SUCCESS,
                "Added '{$userName}' to role" . ($isLeader ? ' as leader' : ''));
            $this->countSuccess++;
        } catch (Exception $e) {
            $this->logResult($result, self::RESULT_ERROR,
                "Failed to add '{$userName}': " . $e->getMessage());
            $this->countErrors++;
        }
    }

    /**
     * Log an import result
     * @param array $result
     * @param string $status
     * @param string $message
     */
    protected function logResult(array $result, string $status, string $message): void
    {
        $this->importLog[] = [
            'row_index' => $result['row_index'],
            'user_id' => $result['user_id'],
            'user_name' => $result['user_name'],
            'status' => $status,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get available identification methods
     * @return array
     */
    public static function getIdentifyMethods(): array
    {
        return [
            self::IDENTIFY_BY_EMAIL => 'E-Mail',
            self::IDENTIFY_BY_UUID => 'User UUID',
            self::IDENTIFY_BY_LOGIN => 'Login Name',
            self::IDENTIFY_BY_NAME => 'First Name + Last Name',
        ];
    }

    /**
     * Get validation statistics
     * @return array
     */
    public function getValidationStats(): array
    {
        return [
            'total' => count($this->getDataRows(true)),
            'errors' => $this->countErrors,
            'warnings' => $this->countWarnings,
            'duplicates' => $this->countDuplicates,
        ];
    }
}
