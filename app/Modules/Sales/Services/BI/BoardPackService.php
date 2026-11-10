<?php

namespace App\Modules\Sales\Services\BI;

use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Drawing\Base64;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

/**
 * Board pack (gap 10): a dashboard as a PowerPoint deck or a print-ready PDF — a cover, the headline
 * numbers on one page, then one chart per page, exactly as drawn on screen. Charts arrive as PNG
 * images captured in the browser; number cards arrive as text.
 */
class BoardPackService
{
    private const PURPLE = 'FF6A0DAD';
    private const INK = 'FF16161F';
    private const MUTED = 'FF77778A';

    /**
     * @param array<int, array{title: string, image?: ?string, text?: ?string}> $items
     * @return array{path: string, name: string, mime: string}
     */
    public function build(string $format, string $dashboard, string $company, string $period, array $items): array
    {
        $numbers = array_values(array_filter($items, fn ($i) => empty($i['image']) && ! empty($i['text'])));
        $charts = array_values(array_filter($items, fn ($i) => ! empty($i['image'])));
        $name = str($dashboard)->slug() . '-' . now()->format('Y-m-d');

        return $format === 'pptx'
            ? $this->pptx($dashboard, $company, $period, $numbers, $charts, $name)
            : $this->pdf($dashboard, $company, $period, $numbers, $charts, $name);
    }

    private function pptx(string $dashboard, string $company, string $period, array $numbers, array $charts, string $name): array
    {
        $deck = new PhpPresentation;
        $deck->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $deck->getDocumentProperties()->setCreator('Mkhaya Sales')->setTitle($dashboard);
        $w = 960;   // 16:9 in pixels as PhpPresentation measures them
        $h = 540;

        // Cover.
        $slide = $deck->getActiveSlide();
        $band = $slide->createRichTextShape()->setWidth($w)->setHeight(12)->setOffsetX(0)->setOffsetY(0);
        $band->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::PURPLE));
        $this->text($slide, strtoupper($company) . ' · MKHAYA SALES', 60, 150, 840, 30, 14, self::PURPLE, true);
        $this->text($slide, $dashboard, 60, 185, 840, 80, 40, self::INK, true);
        $this->text($slide, $period . ' · prepared ' . now()->format('j F Y'), 60, 275, 840, 30, 16, self::MUTED);

        // Headline numbers, up to 8 per slide in a 4 × 2 grid.
        foreach (array_chunk($numbers, 8) as $chunk) {
            $slide = $deck->createSlide();
            $this->text($slide, 'Headline numbers', 40, 24, 880, 40, 24, self::INK, true);
            foreach ($chunk as $i => $n) {
                $x = 40 + ($i % 4) * 222;
                $y = 100 + intdiv($i, 4) * 200;
                $box = $slide->createRichTextShape()->setWidth(206)->setHeight(180)->setOffsetX($x)->setOffsetY($y);
                $box->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFF7F5FB'));
                $box->createTextRun($n['title'] . "\n")->getFont()->setSize(12)->setBold(true)->setColor(new Color(self::MUTED));
                $box->createTextRun($this->firstLine($n['text']) . "\n")->getFont()->setSize(22)->setBold(true)->setColor(new Color(self::INK));
                $box->createTextRun($this->rest($n['text']))->getFont()->setSize(10)->setColor(new Color(self::MUTED));
            }
        }

        // One chart per slide.
        foreach ($charts as $c) {
            $slide = $deck->createSlide();
            $this->text($slide, $c['title'], 40, 20, 880, 40, 22, self::INK, true);
            [$iw, $ih] = $this->size($c['image']);
            $scale = min(880 / max(1, $iw), 440 / max(1, $ih));
            $img = (new Base64)->setName($c['title'])->setData($c['image'])->setResizeProportional(false)
                ->setWidth((int) ($iw * $scale))->setHeight((int) ($ih * $scale));
            $img->setOffsetX((int) (($w - $iw * $scale) / 2))->setOffsetY(75);
            $slide->addShape($img);
            $this->text($slide, "{$dashboard} · {$period}", 40, $h - 30, 880, 20, 9, self::MUTED);
        }

        $path = tempnam(sys_get_temp_dir(), 'pack');
        IOFactory::createWriter($deck, 'PowerPoint2007')->save($path);

        return ['path' => $path, 'name' => "{$name}.pptx", 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    }

    private function pdf(string $dashboard, string $company, string $period, array $numbers, array $charts, string $name): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pack');
        file_put_contents($path, \Barryvdh\DomPDF\Facade\Pdf::loadView('sales.board-pack', compact('dashboard', 'company', 'period', 'numbers', 'charts'))
            ->setPaper('a4', 'landscape')->output());

        return ['path' => $path, 'name' => "{$name}.pdf", 'mime' => 'application/pdf'];
    }

    private function text($slide, string $value, int $x, int $y, int $w, int $h, int $size, string $color, bool $bold = false): void
    {
        $shape = $slide->createRichTextShape()->setWidth($w)->setHeight($h)->setOffsetX($x)->setOffsetY($y);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $shape->createTextRun($value)->getFont()->setSize($size)->setBold($bold)->setColor(new Color($color))->setName('Montserrat');
    }

    private function size(string $dataUri): array
    {
        $info = @getimagesizefromstring(base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)));

        return $info ? [$info[0], $info[1]] : [1600, 900];
    }

    private function firstLine(string $t): string
    {
        return trim(strtok($t, "\n") ?: $t);
    }

    private function rest(string $t): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $t))));

        return implode(' · ', array_slice($lines, 1, 3));
    }
}
