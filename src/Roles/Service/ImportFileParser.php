<?php
namespace Admidio\Roles\Service;

use Admidio\Infrastructure\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * @brief Class to parse import files for role members
 *
 * This class handles parsing of CSV and JSON files that contain member
 * information to be imported into a role.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ImportFileParser
{
    public const FORMAT_CSV = 'CSV';
    public const FORMAT_JSON = 'JSON';
    public const FORMAT_AUTO = 'AUTO';

    protected string $filePath;
    protected string $format;
    protected ?string $encoding = null;
    protected ?string $delimiter = null;
    protected ?string $enclosure = null;
    protected array $parsedData = [];
    protected array $headers = [];
    protected int $rowCount = 0;

    /**
     * Constructor
     * @param string $filePath Path to the import file
     * @param string $format File format (CSV, JSON, AUTO)
     * @throws Exception
     */
    public function __construct(string $filePath, string $format = self::FORMAT_AUTO)
    {
        if (!file_exists($filePath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        $this->filePath = $filePath;
        $this->format = $format;

        if ($format === self::FORMAT_AUTO) {
            $this->detectFormat();
        }
    }

    /**
     * Set encoding for CSV files
     * @param string $encoding Encoding (UTF-8, ISO-8859-1, CP1252, etc.)
     */
    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * Set delimiter for CSV files
     * @param string $delimiter Delimiter character (comma, semicolon, tab, pipe)
     */
    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Set enclosure for CSV files
     * @param string $enclosure Enclosure character
     */
    public function setEnclosure(string $enclosure): void
    {
        $this->enclosure = $enclosure;
    }

    /**
     * Detect file format based on file extension or content
     * @throws Exception
     */
    protected function detectFormat(): void
    {
        $extension = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'csv':
                $this->format = self::FORMAT_CSV;
                break;
            case 'json':
                $this->format = self::FORMAT_JSON;
                break;
            default:
                $content = file_get_contents($this->filePath);
                if ($this->isJson($content)) {
                    $this->format = self::FORMAT_JSON;
                } else {
                    $this->format = self::FORMAT_CSV;
                }
        }
    }

    /**
     * Check if a string is valid JSON
     * @param string $string
     * @return bool
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Parse the import file
     * @return array Parsed data
     * @throws Exception
     */
    public function parse(): array
    {
        if ($this->format === self::FORMAT_CSV) {
            return $this->parseCsv();
        } elseif ($this->format === self::FORMAT_JSON) {
            return $this->parseJson();
        }

        throw new Exception('SYS_INVALID_FILE_FORMAT');
    }

    /**
     * Parse CSV file
     * @return array
     * @throws Exception
     */
    protected function parseCsv(): array
    {
        try {
            $reader = new Csv();

            if ($this->encoding) {
                if ($this->encoding === 'GUESS') {
                    $this->encoding = Csv::guessEncoding($this->filePath);
                }
                $reader->setInputEncoding($this->encoding);
            } else {
                $reader->setInputEncoding('UTF-8');
            }

            if ($this->delimiter) {
                $reader->setDelimiter($this->delimiter);
            }

            if ($this->enclosure && $this->enclosure !== 'AUTO') {
                $reader->setEnclosure($this->enclosure);
            }

            $spreadsheet = $reader->load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $this->parsedData = $sheet->toArray(null, true, false);

            if (empty($this->parsedData)) {
                throw new Exception('SYS_IMPORT_FILE_EMPTY');
            }

            $this->extractHeaders();
            $this->rowCount = count($this->parsedData);

            return $this->parsedData;
        } catch (\Exception $e) {
            throw new Exception('SYS_ERROR_PARSING_FILE: ' . $e->getMessage());
        }
    }

    /**
     * Parse JSON file
     * @return array
     * @throws Exception
     */
    protected function parseJson(): array
    {
        try {
            $content = file_get_contents($this->filePath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('SYS_INVALID_JSON_FORMAT: ' . json_last_error_msg());
            }

            if (!is_array($data)) {
                throw new Exception('SYS_JSON_SHOULD_BE_ARRAY');
            }

            if (empty($data)) {
                throw new Exception('SYS_IMPORT_FILE_EMPTY');
            }

            $this->parsedData = [];
            $firstItem = reset($data);

            if (is_array($firstItem)) {
                $this->headers = array_keys($firstItem);
                $this->parsedData[] = $this->headers;

                foreach ($data as $item) {
                    $row = [];
                    foreach ($this->headers as $header) {
                        $row[] = $item[$header] ?? '';
                    }
                    $this->parsedData[] = $row;
                }
            } else {
                $this->parsedData = $data;
                $this->extractHeaders();
            }

            $this->rowCount = count($this->parsedData);

            return $this->parsedData;
        } catch (Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Exception('SYS_ERROR_PARSING_FILE: ' . $e->getMessage());
        }
    }

    /**
     * Extract headers from first row
     */
    protected function extractHeaders(): void
    {
        if (!empty($this->parsedData)) {
            $this->headers = $this->parsedData[0];
        }
    }

    /**
     * Get parsed data
     * @return array
     */
    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    /**
     * Get headers (first row)
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get row count
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Get detected format
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get data rows (excluding header if firstRowIsHeader is true)
     * @param bool $firstRowIsHeader Whether first row is header
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
     * Get data as associative array using headers as keys
     * @return array
     */
    public function getAssociativeData(): array
    {
        $result = [];
        $dataRows = $this->getDataRows(true);

        foreach ($dataRows as $row) {
            $assocRow = [];
            foreach ($this->headers as $index => $header) {
                $assocRow[$header] = $row[$index] ?? '';
            }
            $result[] = $assocRow;
        }

        return $result;
    }
}
