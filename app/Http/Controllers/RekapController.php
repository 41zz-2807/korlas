<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RekapController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->get('kategori', 'kas');

        if (!in_array($kategori, ['kas', 'komite'])) {
            $kategori = 'kas';
        }

        $students = $this->getStudents();
        $months = $this->academicMonths();
        $paid = $this->paidMatrix($kategori);
        $kategoriLabel = $kategori === 'kas' ? 'Kas Kelas' : 'Komite';

        return view('rekap.index', compact('students', 'months', 'paid', 'kategori', 'kategoriLabel'));
    }

    public function export(Request $request)
    {
        $kategori = $request->get('kategori', 'kas');
        $export = $request->get('export', 'siswa_pdf');

        if (!in_array($kategori, ['kas', 'komite'])) {
            $kategori = 'kas';
        }

        $students = $this->getStudents();
        $months = $this->academicMonths();
        $paid = $this->paidMatrix($kategori);
        $kategoriLabel = $kategori === 'kas' ? 'Kas Kelas' : 'Komite';

        $filename = 'Rekap Status Pembayaran Siswa - ' . $kategoriLabel;

        if ($export === 'siswa_xlsx') {
            return $this->exportXlsx($students, $months, $paid, $kategoriLabel, $filename);
        }

        return $this->exportPdf($students, $months, $paid, $kategoriLabel, $filename);
    }

    public function paidMatrix(string $kategori): array
    {
        $paid = [];
        $transactions = Transaction::where('type', 'income')
            ->where('category', $kategori)
            ->get();

        foreach ($transactions as $t) {
            $name = strtoupper(trim($t->name));
            if ($name === '') {
                continue;
            }

            $monthList = is_array($t->months) ? $t->months : [];
            if (empty($monthList)) {
                continue;
            }

            foreach ($monthList as $m) {
                $paid[$name][$m] = true;
            }
        }

        return $paid;
    }

    public function getStudents(): array
    {
        $path = storage_path('app/siswa.txt');
        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_values(array_filter(array_map('trim', $lines)));
    }

    public function academicMonths(): array
    {
        $indo = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;
        $cursor = Carbon::create($startYear, 7, 1);
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $months[$key] = [
                'name' => $indo[$cursor->format('m')],
                'year' => $cursor->format('Y'),
                'label' => $cursor->translatedFormat('M Y'),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    private function exportXlsx(array $students, array $months, array $paid, string $kategoriLabel, string $filename)
    {
        $monthNames = array_column($months, 'name');

        $rows = [['Nama Siswa', ...$monthNames]];
        foreach ($students as $student) {
            $row = [ucwords(strtolower($student))];
            foreach ($months as $m => $meta) {
                $row[] = isset($paid[strtoupper(trim($student))][$m]) ? 'LUNAS' : 'BELUM';
            }
            $rows[] = $row;
        }

        $worksheetXml = $this->buildWorksheetXml($rows);
        $xlsx = $this->buildXlsxZip($worksheetXml);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
        ]);
    }

    private function buildWorksheetXml(array $rows): string
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $r => $row) {
            $sheet .= '<row r="' . ($r + 1) . '">';
            foreach ($row as $c => $value) {
                $ref = $this->colLetter($c) . ($r + 1);
                $style = ($r === 0) ? ' s="1"' : (($r % 2) === 0 ? ' s="2"' : ' s="0"');
                $sheet .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t>' . $this->esc((string) $value) . '</t></is></c>';
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        return $sheet;
    }

    private function buildXlsxZip(string $worksheetXml): string
    {
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rekap" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1"><font><b/></font></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();

        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    private function exportPdf(array $students, array $months, array $paid, string $kategoriLabel, string $filename)
    {
        $pdf = $this->buildRekapPdf($students, $months, $paid, $kategoriLabel);

        $pdf->Output($filename . '.pdf', 'D');
        exit;
    }

    public function buildRekapPdf(array $students, array $months, array $paid, string $kategoriLabel)
    {
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetMargins(8, 10, 8);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 7, 'REKAP STATUS PEMBAYARAN SISWA', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, config('app.school_name', 'Grade 6 Jannatul Firdaus'), 0, 1, 'C');
        $pdf->Cell(0, 6, 'Kategori: ' . $kategoriLabel . '   |   Tahun Ajaran ' . $this->academicYearLabel(), 0, 1, 'C');
        $pdf->Ln(3);

        $monthKeys = array_keys($months);
        $monthNames = array_values(array_map(fn ($m) => substr($m['name'], 0, 3), $months));
        $headers = array_merge(['Nama Siswa'], $monthNames);
        $widths = array_merge([58], array_fill(0, count($monthNames), 17));

        $pdf->SetFillColor(230, 233, 249);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->SetFont('helvetica', 'B', 8);
        foreach ($headers as $i => $h) {
            $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        foreach ($students as $student) {
            $pdf->SetFillColor(244, 247, 252);
            $pdf->Cell($widths[0], 6, ucwords(strtolower($student)), 1, 0, 'L', $fill);
            foreach ($monthKeys as $m) {
                $ok = isset($paid[strtoupper(trim($student))][$m]);
                $pdf->Cell($widths[1], 6, $ok ? 'V' : '-', 1, 0, 'C', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }

        return $pdf;
    }

    private function academicYearLabel(): string
    {
        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;

        return $startYear . '/' . ($startYear + 1);
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        }

        return $letter;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
