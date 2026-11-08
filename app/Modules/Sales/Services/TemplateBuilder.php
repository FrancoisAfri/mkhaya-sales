<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Support\ImportFields;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Downloadable import templates with instructions, formats and an example row (SRS §9.2). */
class TemplateBuilder
{
    private const EXAMPLES = [
        'transaction_date' => '2026-11-01', 'customer_code' => 'C1001', 'customer_name' => 'Example Retailer (Pty) Ltd',
        'product_code' => 'P2001', 'product_name' => 'Example product 500g', 'quantity' => 24, 'unit_of_measure' => 'CASE',
        'unit_price' => 85.50, 'gross_value' => 2052.00, 'discount' => 52.00, 'net_value' => 2000.00, 'currency' => 'ZAR',
        'external_id' => 'INV-000123', 'region' => 'Gauteng', 'salesperson' => 'REP01', 'category' => 'Baking',
        'subcategory' => 'Pizza', 'brand' => 'Example Brand', 'unit' => 'CASE', 'pack_size' => '12 x 500g',
        'customer_group' => 'Retail', 'status' => 'active', 'alternative_name' => 'Example Store',
    ];

    public function build(string $target, ?ReportLayout $layout = null): string
    {
        $fields = ImportFields::for($target);
        $columns = $layout
            ? collect($layout->mapping)->map(fn ($m) => ['heading' => $m['source'], 'field' => $m['field']])->all()
            : collect($fields)->map(fn ($f, $k) => ['heading' => $f['label'], 'field' => $k])->values()->all();

        $book = new Spreadsheet;
        $book->getProperties()->setTitle(($layout?->name ?? ImportFields::TARGETS[$target]) . ' template')
            ->setSubject('version ' . ($layout?->version ?? 1))->setCreator('Mkhaya Sales');

        $data = $book->getActiveSheet()->setTitle($layout?->sheet ?: 'Data');
        $headerRow = $layout?->header_row ?? 1;
        foreach ($columns as $i => $c) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $data->setCellValue($col . $headerRow, $c['heading']);
            $data->setCellValue($col . ($headerRow + 1), self::EXAMPLES[$c['field']] ?? '');
            $data->getStyle($col . $headerRow)->getFont()->setBold(true);
            $data->getColumnDimension($col)->setAutoSize(true);
        }

        $help = $book->createSheet()->setTitle('Instructions');
        $help->fromArray([
            ['Template', ($layout?->name ?? ImportFields::TARGETS[$target]) . ' — version ' . ($layout?->version ?? 1)],
            ['Dates', $layout?->date_format ? "Use {$layout->date_format}" : 'YYYY-MM-DD, DD/MM/YYYY or an Excel date'],
            ['Numbers', $layout?->decimal_comma ? 'Decimal comma (1 234,56)' : 'Decimal point, no currency symbol (1234.56)'],
            ['Example row', 'Row ' . ($headerRow + 1) . ' on the data sheet is an example — replace it.'],
            [],
            ['Column', 'Maps to', 'Type', 'Required'],
        ]);
        $r = 7;
        foreach ($columns as $c) {
            $f = $fields[$c['field']] ?? null;
            $help->fromArray([[
                $c['heading'],
                $f['label'] ?? (str_starts_with((string) $c['field'], 'attr:') ? 'Custom field (' . substr($c['field'], 5) . ')' : 'Ignored'),
                $f['type'] ?? 'text',
                ($f['required'] ?? false) ? 'Yes' : 'No',
            ]], null, 'A' . $r++);
        }
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $help->getColumnDimension($col)->setAutoSize(true);
        }
        $book->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        (new Xlsx($book))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
