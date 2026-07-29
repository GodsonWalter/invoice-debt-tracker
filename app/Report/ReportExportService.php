<?php

namespace App\Report;

use App\Data\ReportFilters;
use App\Models\Workspace;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportExportService
{
    public function __construct(private readonly ReportService $reportService) {}

    public function count(Workspace $workspace, ReportFilters $filters): int
    {
        return $this->reportService->count($workspace, $filters);
    }

    public function csv(Workspace $workspace, ReportFilters $filters): StreamedResponse
    {
        $filename = $this->filename($filters, 'csv');

        return response()->streamDownload(function () use ($workspace, $filters): void {
            $this->writeCsv($workspace, $filters, fopen('php://output', 'wb'));
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsx(Workspace $workspace, ReportFilters $filters): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'idt-report-');
        if ($path === false) {
            throw new \RuntimeException('The spreadsheet export could not be created.');
        }

        $this->writeXlsx($workspace, $filters, $path);

        return response()->download($path, $this->filename($filters, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Workspace $workspace, ReportFilters $filters): Response
    {
        $content = $this->pdfContent($workspace, $filters);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($filters, 'pdf').'"',
        ]);
    }

    public function store(Workspace $workspace, ReportFilters $filters, string $path): void
    {
        $diskName = (string) config('reports.export_disk', 'local');
        $disk = Storage::disk($diskName);
        $disk->makeDirectory(dirname($path));
        $write = function (string $absolutePath) use ($workspace, $filters, $path): void {
            match (pathinfo($path, PATHINFO_EXTENSION)) {
                'csv' => $this->writeCsv($workspace, $filters, fopen($absolutePath, 'wb')),
                'xlsx' => $this->writeXlsx($workspace, $filters, $absolutePath),
                'pdf' => file_put_contents($absolutePath, $this->pdfContent($workspace, $filters)),
                default => throw new \InvalidArgumentException('Unsupported report export format.'),
            };
        };

        if (config('filesystems.disks.'.$diskName.'.driver') === 'local') {
            $write($disk->path($path));

            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'idt-export-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('The report export could not be staged.');
        }

        try {
            $write($temporaryPath);
            $stream = fopen($temporaryPath, 'rb');
            $disk->put($path, $stream);
            fclose($stream);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function writeCsv(Workspace $workspace, ReportFilters $filters, mixed $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values($this->reportService->columns($filters->type)));

        foreach ($this->reportService->exportRows($workspace, $filters) as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);
    }

    private function writeXlsx(Workspace $workspace, ReportFilters $filters, string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('The spreadsheet export could not be created.');
        }

        $sheetPath = tempnam(sys_get_temp_dir(), 'idt-sheet-');
        if ($sheetPath === false) {
            $zip->close();
            throw new \RuntimeException('The spreadsheet worksheet could not be created.');
        }

        $sheet = fopen($sheetPath, 'wb');
        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        $headers = array_values($this->reportService->columns($filters->type));
        fwrite($sheet, '<cols>');
        foreach ($headers as $column => $header) {
            $width = min(32, max(12, strlen((string) $header) + 4));
            fwrite($sheet, '<col min="'.($column + 1).'" max="'.($column + 1).'" width="'.$width.'" customWidth="1"/>');
        }
        fwrite($sheet, '</cols><sheetData>');
        fwrite($sheet, '<row r="1">');
        foreach ($headers as $column => $header) {
            fwrite($sheet, $this->cell($column + 1, 1, $header, 1));
        }
        fwrite($sheet, '</row>');

        $rowNumber = 2;
        foreach ($this->reportService->exportRows($workspace, $filters) as $row) {
            fwrite($sheet, '<row r="'.$rowNumber.'">');
            foreach (array_values($row) as $column => $value) {
                $key = array_keys($row)[$column] ?? '';
                $style = in_array($key, ['amount', 'total_amount', 'amount_paid', 'outstanding_balance', 'total_invoiced', 'total_paid', 'overdue_balance'], true) ? 2 : 0;
                fwrite($sheet, $this->cell($column + 1, $rowNumber, $value, $style));
            }
            fwrite($sheet, '</row>');
            $rowNumber++;
        }
        fwrite($sheet, '</sheetData><autoFilter ref="A1:'.$this->columnName(count($headers)).($rowNumber - 1).'"/></worksheet>');
        fclose($sheet);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheetPath);
    }

    private function pdfContent(Workspace $workspace, ReportFilters $filters): string
    {
        $report = $this->reportService->build($workspace, $filters, 0);
        $report['generatedAt'] = now();
        $logoDataUri = null;
        $business = $report['business'];
        if ($business?->logo && config('filesystems.disks.public.driver') === 'local') {
            $logoPath = Storage::disk('public')->path($business->logo);
            if (is_file($logoPath)) {
                $mimeType = mime_content_type($logoPath) ?: 'image/png';
                $logoDataUri = 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($logoPath));
            }
        }

        return Pdf::loadView('reports.pdf', [
            'workspace' => $workspace,
            'report' => $report,
            'business' => $report['business'],
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'landscape')->output();
    }

    private function filename(ReportFilters $filters, string $extension): string
    {
        return 'idt-'.$filters->type->value.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function cell(int $column, int $row, mixed $value, int $style = 0): string
    {
        $reference = $this->columnName($column).$row;
        $value = (string) ($value ?? '');
        $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';

        if ($value !== '' && is_numeric($value) && ! str_contains($value, '-')) {
            return '<c r="'.$reference.'"'.$styleAttribute.'><v>'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'</v></c>';
        }

        return '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $column = intdiv($column - 1, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE9F2FF"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs></styleSheet>';
    }
}
