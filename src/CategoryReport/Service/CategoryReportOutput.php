<?php
namespace Admidio\CategoryReport\Service;

use Admidio\Infrastructure\Utils\SpreadsheetUtils;

/** Prepares report rows consistently for all presentation and export formats. */
class CategoryReportOutput
{
    /**
     * @param array<int,array<int,mixed>> $rows Generated report rows, optionally followed by a summary row.
     * @param array<int,array<string,mixed>> $headers Report column definitions.
     * @param callable(mixed,int):string $toText Converts a cell to its searchable display text.
     * @return array<int,array{member:int,data:array<int,mixed>,summary:bool,number:int}>
     */
    public static function filterRows(
        array $rows,
        array $headers,
        bool $hasSummary,
        string $summaryLabel,
        string $filter,
        callable $toText
    ): array
    {
        if ($hasSummary && $rows !== array()) {
            array_pop($rows);
        }

        $result = array();
        $totals = array_fill_keys(array_keys($headers), '');
        $filter = trim($filter);

        foreach ($rows as $member => $cells) {
            if ($filter !== '') {
                $searchText = array();
                foreach ($cells as $key => $value) {
                    $searchText[] = $toText($value, (int)$key);
                }
                $haystack = implode(' ', $searchText);
                $matches = function_exists('mb_stripos')
                    ? mb_stripos($haystack, $filter, 0, 'UTF-8')
                    : stripos($haystack, $filter);
                if ($matches === false) {
                    continue;
                }
            }

            foreach ($cells as $key => $value) {
                if ($value === true && array_key_exists($key, $totals)) {
                    $totals[$key] = (int)$totals[$key] + 1;
                }
            }
            $result[] = array('member' => (int)$member, 'data' => $cells, 'summary' => false,
                'number' => count($result) + 1);
        }

        if ($hasSummary) {
            $firstColumn = array_key_first($totals);
            if ($firstColumn !== null) {
                $totals[$firstColumn] = $summaryLabel;
            }
            $result[] = array('member' => 0, 'data' => $totals, 'summary' => true, 'number' => 0);
        }

        return $result;
    }

    /** @param array<int,array<int,string|int|float>> $rows */
    public static function createCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open temporary CSV stream.');
        }

        try {
            foreach ($rows as $row) {
                $row = array_map(
                    static fn(mixed $value): string => SpreadsheetUtils::neutralizeFormula((string)$value),
                    $row
                );
                if (fputcsv($stream, $row, ',', '"', '') === false) {
                    throw new \RuntimeException('Unable to write CSV row.');
                }
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new \RuntimeException('Unable to read temporary CSV stream.');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }

    /**
     * Mark generated, trusted HTML so the report template can distinguish it from text cells.
     *
     * @return array{value:string,html:true,order?:int|string}
     */
    public static function html(string|int $value, int|string|null $order = null): array
    {
        $cell = array('value' => (string)$value, 'html' => true);
        if ($order !== null) {
            $cell['order'] = $order;
        }

        return $cell;
    }

}
