<?php
namespace Admidio\Users\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\UserImport;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 ***********************************************************************************************
 * Data-oriented contact import shared by the web import wizard and headless callers.
 *
 * The service uses internal profile-field names as its canonical mapping. The web adapter converts
 * profile-field UUIDs to those names, while CLI callers can use the stable internal names directly.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class ContactImportService
{
    /** @var array<string,class-string|null> */
    private const INPUT_READERS = array(
        'AUTO' => null,
        'XLSX' => Xlsx::class,
        'XLS' => Xls::class,
        'ODS' => Ods::class,
        'CSV' => Csv::class,
        'HTML' => Html::class
    );

    /** @var array<int,string> */
    public const INPUT_ENCODINGS = array(
        'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'
    );

    /** @var array<string,string> */
    public const CSV_DELIMITERS = array(',' => ',', ';' => ';', '\\t' => "\t", '|' => '|');

    /** @var array<int,string> */
    public const CSV_ENCLOSURES = array('AUTO', '"', "'");

    /** @var array<string,int> */
    public const IMPORT_MODES = array(
        'not-edit' => UserImport::USER_IMPORT_NOT_EDIT,
        'duplicate' => UserImport::USER_IMPORT_DUPLICATE,
        'replace' => UserImport::USER_IMPORT_DISPLACE,
        'complete' => UserImport::USER_IMPORT_COMPLETE
    );

    private Database $database;
    private ProfileFields $profileFields;

    /** @return array<int,string> */
    public static function inputFormats(): array
    {
        return array_keys(self::INPUT_READERS);
    }

    public function __construct(Database $database, ProfileFields $profileFields)
    {
        $this->database = $database;
        $this->profileFields = $profileFields;
    }

    /**
     * Read a supported import file with the same PhpSpreadsheet readers as the web wizard.
     *
     * @return array<int,array<int,mixed>>
     */
    public function readFile(
        string $filename,
        string $inputFormat = 'AUTO',
        string $encoding = '',
        string $delimiter = '',
        string $enclosure = 'AUTO',
        string $worksheet = ''
    ): array {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new InvalidArgumentException('Import file "' . $filename . '" is not readable.');
        }

        $inputFormat = strtoupper($inputFormat);
        if (!array_key_exists($inputFormat, self::INPUT_READERS)) {
            throw new InvalidArgumentException('Unsupported import format "' . $inputFormat . '".');
        }

        if ($inputFormat === 'AUTO') {
            $reader = IOFactory::createReaderForFile($filename);
        } else {
            $readerClass = self::INPUT_READERS[$inputFormat];
            $reader = new $readerClass();
        }

        if ($reader instanceof Csv) {
            if ($encoding !== '' && !in_array($encoding, self::INPUT_ENCODINGS, true)) {
                throw new InvalidArgumentException('Unsupported CSV input encoding "' . $encoding . '".');
            }
            if ($delimiter !== '' && !array_key_exists($delimiter, self::CSV_DELIMITERS)) {
                throw new InvalidArgumentException('Unsupported CSV delimiter.');
            }
            if ($enclosure !== '' && !in_array($enclosure, self::CSV_ENCLOSURES, true)) {
                throw new InvalidArgumentException('Unsupported CSV enclosure.');
            }

            if ($encoding === 'GUESS') {
                $encoding = Csv::guessEncoding($filename);
            } elseif ($encoding === '') {
                $encoding = 'UTF-8';
            }
            $reader->setInputEncoding($encoding);

            if ($delimiter !== '') {
                $reader->setDelimiter(self::CSV_DELIMITERS[$delimiter]);
            }
            if ($enclosure !== 'AUTO') {
                $reader->setEnclosure($enclosure);
            }
        }

        $spreadsheet = $reader->load($filename);
        if ($worksheet !== '') {
            $sheet = ctype_digit($worksheet)
                ? $spreadsheet->getSheet((int)$worksheet)
                : $spreadsheet->getSheetByName($worksheet);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }

        if ($sheet === null) {
            throw new Exception('SYS_IMPORT_SHEET_NOT_EXISTS', array($worksheet));
        }

        return $sheet->toArray(null, true, false);
    }


    /**
     * Convert the web import wizard's UUID-based assignments to the canonical internal-name mapping.
     *
     * @param array<string,mixed> $formValues
     * @return array<string,int>
     */
    public function resolveWebMapping(array $formValues): array
    {
        $mapping = array();
        foreach ($formValues as $field => $column) {
            if ($column === '' || $column === null) {
                continue;
            }

            if (Uuid::isValid($field)) {
                $field = (string)$this->profileFields->getPropertyByUuid($field, 'usf_name_intern');
            } elseif (!in_array($field, array('usr_uuid', 'usr_login_name', 'usr_password'), true)) {
                continue;
            }
            $mapping[$field] = (int)$column;
        }

        return $this->validateMapping($mapping);
    }

    /**
     * Resolve FIELD=COLUMN assignments. FIELD is an internal Admidio profile field name, usr_uuid,
     * usr_login_name or usr_password. COLUMN may be a zero-based numeric index or a first-row title.
     * When a header is present, exact internal-name headers are automatically mapped first and may
     * be overridden by explicit assignments.
     *
     * @param array<int,array<int,mixed>> $rows
     * @param array<string,string> $assignments
     * @return array<string,int>
     */
    public function resolveMapping(array $rows, array $assignments, bool $firstRowHeader = true): array
    {
        if (count($rows) === 0) {
            throw new InvalidArgumentException('Import file contains no rows.');
        }

        $allowedFields = array('usr_uuid' => true, 'usr_login_name' => true, 'usr_password' => true);
        foreach ($this->profileFields->getProfileFields() as $field) {
            $allowedFields[(string)$field->getValue('usf_name_intern')] = true;
        }

        $headers = array();
        if ($firstRowHeader) {
            foreach ($rows[0] as $index => $header) {
                $headers[(string)$header] = (int)$index;
            }

            foreach ($allowedFields as $field => $_) {
                foreach ($headers as $header => $index) {
                    if (strcasecmp(trim($header), $field) === 0) {
                        $assignments[$field] ??= (string)$index;
                        break;
                    }
                }
            }
        }

        $mapping = array();
        foreach ($assignments as $field => $column) {
            if (!isset($allowedFields[$field])) {
                throw new InvalidArgumentException('Unknown import field "' . $field . '". Use an internal profile-field name.');
            }

            if (ctype_digit($column)) {
                $columnIndex = (int)$column;
            } elseif ($firstRowHeader && array_key_exists($column, $headers)) {
                $columnIndex = $headers[$column];
            } else {
                throw new InvalidArgumentException(
                    'Import column "' . $column . '" for field "' . $field . '" was not found.'
                );
            }

            if (!array_key_exists($columnIndex, $rows[0])) {
                throw new InvalidArgumentException('Import column index ' . $columnIndex . ' is outside the input data.');
            }
            $mapping[$field] = $columnIndex;
        }

        return $this->validateMapping($mapping);
    }

    public static function importModeFromName(string $name): int
    {
        if (!array_key_exists($name, self::IMPORT_MODES)) {
            throw new InvalidArgumentException('Unsupported user import mode "' . $name . '".');
        }
        return self::IMPORT_MODES[$name];
    }

    /**
     * Validate or import rows.
     *
     * @param array<int,array<int,mixed>> $rows
     * @param array<string,int> $mapping
     * @return array{new:int,updated:int,memberships:int,errors:int,rows:array<int,array<string,mixed>>}
     */
    public function importRows(
        array $rows,
        array $mapping,
        int $roleId,
        int $importMode,
        bool $firstRowHeader = true,
        bool $dryRun = false
    ): array {
        global $gSettingsManager;

        if (!in_array($importMode, array_values(self::IMPORT_MODES), true)) {
            throw new InvalidArgumentException('Unsupported user import mode.');
        }

        PhpIniUtils::startNewExecutionTimeLimit(600);
        $role = new Role($this->database, $roleId);
        if ((int)$role->getValue('rol_id') === 0) {
            throw new InvalidArgumentException('Import role does not exist.');
        }

        $start = $firstRowHeader ? 1 : 0;
        $result = array('new' => 0, 'updated' => 0, 'memberships' => 0, 'errors' => 0, 'rows' => array());
        $identifyByUuid = isset($mapping['usr_uuid']);

        for ($index = $start, $count = count($rows); $index < $count; ++$index) {
            $rowNumber = $index + 1;
            $row = $rows[$index];
            $this->database->startTransaction();

            try {
                $userImport = new UserImport($this->database, $this->profileFields);
                $userImport->setImportMode($importMode);

                if ($identifyByUuid) {
                    $userImport->readDataByUuid($this->cell($row, $mapping['usr_uuid']));
                } else {
                    $userImport->readDataByFirstnameLastName(
                        $this->cell($row, $mapping['FIRST_NAME']),
                        $this->cell($row, $mapping['LAST_NAME'])
                    );
                }

                $wasNew = $userImport->isNewRecord();
                $loginName = '';
                $password = '';

                foreach ($mapping as $fieldName => $columnIndex) {
                    $value = $this->cell($row, $columnIndex);

                    if ($fieldName === 'usr_uuid') {
                        continue;
                    }
                    if ($fieldName === 'usr_login_name') {
                        $loginName = $value;
                        continue;
                    }
                    if ($fieldName === 'usr_password') {
                        $password = $value;
                        continue;
                    }

                    if ($this->profileFields->getProperty($fieldName, 'usf_type') === 'DATE' && is_numeric($value)) {
                        $value = date(
                            $gSettingsManager->getString('system_date'),
                            Date::excelToTimestamp((float)$value)
                        );
                    }

                    $userImport->setValue($fieldName, $value);
                }

                $messages = array();
                if ($password !== '') {
                    if ($loginName === '') {
                        $loginName = (string)$userImport->getValue('usr_login_name');
                    }
                    if ($loginName !== '') {
                        try {
                            $userImport->setLoginData($loginName, $password);
                        } catch (Exception $exception) {
                            $messages[] = $exception->getMessage();
                        }
                    } else {
                        $messages[] = trim(
                            (string)$userImport->getValue('FIRST_NAME') . ' '
                            . (string)$userImport->getValue('LAST_NAME')
                        ) . ': password given for new user, but no username';
                    }
                } elseif ($loginName !== '') {
                    $userImport->setValue('usr_login_name', $loginName);
                }

                $status = $wasNew ? 'new' : 'updated';
                if (!$dryRun) {
                    $saved = $userImport->save();
                    if (!$saved && !$wasNew) {
                        $status = 'unchanged';
                    }
                    $role->startMembership((int)$userImport->getValue('usr_id'));
                    $this->database->endTransaction();

                    if ($wasNew) {
                        ++$result['new'];
                    } elseif ($saved) {
                        ++$result['updated'];
                    }
                    ++$result['memberships'];
                } else {
                    // Some UserImport modes (notably DISPLACE) alter loaded profile data while
                    // resolving an existing contact. Always undo those effects in check mode.
                    $this->database->rollback();
                }

                $result['rows'][] = array(
                    'row' => $rowNumber,
                    'status' => $status,
                    'name' => trim((string)$userImport->getValue('FIRST_NAME') . ' ' . (string)$userImport->getValue('LAST_NAME')),
                    'messages' => implode('; ', $messages)
                );
            } catch (Throwable $exception) {
                $this->database->rollback();
                ++$result['errors'];
                $result['rows'][] = array(
                    'row' => $rowNumber,
                    'status' => 'error',
                    'name' => '',
                    'messages' => $exception->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string,int> $mapping
     * @return array<string,int>
     */
    private function validateMapping(array $mapping): array
    {
        foreach (array('FIRST_NAME', 'LAST_NAME') as $requiredField) {
            if (!isset($mapping[$requiredField])) {
                throw new InvalidArgumentException('Import mapping must include ' . $requiredField . '.');
            }
        }
        return $mapping;
    }

    /** @param array<int,mixed> $row */
    private function cell(array $row, int $index): string
    {
        return trim(strip_tags((string)($row[$index] ?? '')));
    }
}
