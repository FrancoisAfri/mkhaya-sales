<?php

namespace App\Modules\Sales\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use RuntimeException;

/**
 * Reads XLSX / XLS / CSV into heading → value rows (SRS §6.1). Values are returned raw; typing and
 * validation belong to the import engine, which knows the layout.
 */
class SpreadsheetReader
{
    public const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    /** @return array{sheets: string[], headings: string[], sample: array<int, array<string, mixed>>} */
    public function inspect(string $path, string $ext, ?string $sheet = null, int $headerRow = 1): array
    {
        $sheets = [];
        $sample = [];
        $headings = [];
        foreach ($this->rows($path, $ext, $sheet, $headerRow, $sheets, $headings) as $row) {
            $sample[] = $row['values'];
            if (count($sample) >= 5) {
                break;
            }
        }

        return ['sheets' => $sheets, 'headings' => $headings, 'sample' => $sample];
    }

    /**
     * @return \Generator<int, array{row: int, values: array<string, mixed>}>
     */
    public function rows(string $path, string $ext, ?string $sheet = null, int $headerRow = 1, ?array &$sheets = null, ?array &$headings = null): \Generator
    {
        $ext = strtolower($ext);
        if (! in_array($ext, self::EXTENSIONS, true)) {
            throw new RuntimeException("Unsupported file type .{$ext}. Use XLSX, XLS or CSV.");
        }

        try {
            $reader = IOFactory::createReader(match ($ext) { 'xlsx' => 'Xlsx', 'xls' => 'Xls', default => 'Csv' });
            if ($reader instanceof Csv) {
                $reader->setDelimiter($this->csvDelimiter($path));
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
            } else {
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
            }
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('The file could not be read — it may be corrupted or not a real spreadsheet. (' . $e->getMessage() . ')');
        }

        $sheets = $book->getSheetNames();
        $ws = $sheet !== null && $sheet !== '' ? $book->getSheetByName($sheet) : $book->getSheet(0);
        if (! $ws) {
            throw new RuntimeException("The workbook has no sheet called \"{$sheet}\". Sheets found: " . implode(', ', $sheets));
        }

        $headings = [];
        foreach ($ws->getRowIterator($headerRow, $headerRow) as $r) {
            foreach ($r->getCellIterator() as $cell) {
                $headings[$cell->getColumn()] = trim((string) $cell->getValue());
            }
        }
        $headings = array_filter($headings, fn ($h) => $h !== '');
        if (! $headings) {
            throw new RuntimeException("Row {$headerRow} has no column headings.");
        }
        $columns = $headings;
        $headings = array_values($headings);

        $last = $ws->getHighestDataRow();
        for ($i = $headerRow + 1; $i <= $last; $i++) {
            $values = [];
            $empty = true;
            foreach ($columns as $col => $heading) {
                $v = $ws->getCell($col . $i)->getValue();
                if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $v = $v->getPlainText();
                }
                if (is_string($v)) {
                    $v = trim($v);
                }
                if ($v !== null && $v !== '') {
                    $empty = false;
                }
                $values[$heading] = $v;
            }
            if (! $empty) {
                yield ['row' => $i, 'values' => $values];
            }
        }

        $book->disconnectWorksheets();
    }

    private function csvDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r'));
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t"), '|' => substr_count($line, '|')];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
