<?php
namespace Admidio\Infrastructure\Utils;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Utilities for safely exporting data to spreadsheet applications. */
final class SpreadsheetUtils
{
    /**
     * Prevent spreadsheet applications from interpreting exported CSV text as a formula.
     */
    public static function neutralizeFormula(string $value): string
    {
        // Preserve real numeric values such as -5 instead of turning them into text.
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        if (str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }

        return $value;
    }

    /** Write a value without allowing PhpSpreadsheet to infer a formula from text. */
    public static function setCellValue(
        Worksheet $sheet,
        string $coordinate,
        mixed $value,
        bool $neutralizeFormula = false
    ): void {
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
            return;
        }

        if (is_array($value)) {
            $value = $value['value'];
        }
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($neutralizeFormula) {
            $value = self::neutralizeFormula($value);
        }
        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
    }
}
