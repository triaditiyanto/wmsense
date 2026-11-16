<?php

namespace App\Support\Xlsx;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penulis file Excel (.xlsx) sederhana, PHP murni (tanpa ekstensi zip / library pihak ketiga).
 * File .xlsx = arsip ZIP berisi beberapa XML; ZIP ditulis manual dengan kompresi deflate (ext zlib).
 *
 *   return XlsxWriter::make('Inbound Detail')
 *       ->columns([['label' => 'SKU', 'type' => 'string'], ['label' => 'Qty', 'type' => 'number']])
 *       ->rows($iterable)            // tiap baris = array nilai, urutan sama dengan columns
 *       ->download('inbound-detail.xlsx');
 *
 * Tipe kolom: string | number | percent | date | datetime. Baris judul tebal, dibekukan, dengan autofilter.
 */
class XlsxWriter
{
    private array $columns = [];
    private string $sheetXml = '';
    private int $rowCount = 0;
    private array $widths = [];
    private array $titleLines = [];

    public function __construct(private string $sheetName) {}

    public static function make(string $sheetName): self
    {
        return new self($sheetName);
    }

    /** Baris keterangan di atas tabel (mis. judul laporan & filter). */
    public function title(array $lines): self
    {
        $this->titleLines = array_values(array_filter($lines, fn ($l) => $l !== null && $l !== ''));

        return $this;
    }

    /** @param list<array{label:string, type?:string}> $columns */
    public function columns(array $columns): self
    {
        $this->columns = array_values($columns);
        $this->widths = array_map(fn ($c) => min(60, max(8, mb_strlen($c['label']) + 2)), $this->columns);

        return $this;
    }

    public function rows(iterable $rows): self
    {
        $r = 0;
        $xml = '';
        foreach ($this->titleLines as $i => $line) {
            $xml .= '<row r="'.(++$r).'"><c r="A'.$r.'" t="inlineStr" s="'.($i === 0 ? 4 : 5).'"><is><t xml:space="preserve">'.$this->esc($line).'</t></is></c></row>';
        }
        if ($this->titleLines) {
            $r++; // satu baris kosong
        }
        $this->headerRow = $r + 1;

        $xml .= '<row r="'.(++$r).'">';
        foreach ($this->columns as $i => $c) {
            $xml .= '<c r="'.$this->col($i).$r.'" t="inlineStr" s="1"><is><t xml:space="preserve">'.$this->esc($c['label']).'</t></is></c>';
        }
        $xml .= '</row>';

        foreach ($rows as $row) {
            $row = array_values((array) $row);
            $r++;
            $xml .= '<row r="'.$r.'">';
            foreach ($this->columns as $i => $c) {
                $xml .= $this->cell($this->col($i).$r, $row[$i] ?? null, $c['type'] ?? 'string', $i);
            }
            $xml .= '</row>';
            $this->rowCount++;
        }
        $this->sheetXml = $xml;
        $this->lastRow = $r;

        return $this;
    }

    private int $headerRow = 1;
    private int $lastRow = 1;

    public function download(string $filename): Response
    {
        return new Response($this->build(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $filename).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Isi file .xlsx (biner). */
    public function build(): string
    {
        $lastCol = $this->col(max(0, count($this->columns) - 1));
        $cols = '';
        foreach ($this->widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$this->headerRow.'" topLeftCell="A'.($this->headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$this->sheetXml.'</sheetData>'
            .($this->columns ? '<autoFilter ref="A'.$this->headerRow.':'.$lastCol.max($this->headerRow, $this->lastRow).'"/>' : '')
            .'</worksheet>';

        $name = $this->esc(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->sheetName), 0, 31));
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                .'</Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                .'<dc:title>'.$name.'</dc:title><dc:creator>WMSense</dc:creator>'
                .'<dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>WMSense</Application></Properties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
                .($this->columns ? '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.str_replace("'", "''", $name).'\'!$A$'.$this->headerRow.':$'.$lastCol.'$'.max($this->headerRow, $this->lastRow).'</definedName></definedNames>' : '')
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        return $this->zip($files);
    }

    // style index: 0 normal, 1 header, 2 angka, 3 tanggal, 4 judul, 5 keterangan, 6 tanggal-jam, 7 persen
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0.####"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
            .'<numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF0B1424"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF6B778C"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF007AFF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function cell(string $ref, mixed $v, string $type, int $i): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (in_array($type, ['number', 'percent'], true) && is_numeric($v)) {
            $num = (float) $v;
            if ($type === 'percent') {
                $num /= 100; // nilai laporan 0-100 -> format persen Excel
            }
            $this->widths[$i] = max($this->widths[$i], min(20, strlen((string) $v) + 2));

            return '<c r="'.$ref.'" s="'.($type === 'percent' ? 7 : 2).'"><v>'.$num.'</v></c>';
        }
        if (in_array($type, ['date', 'datetime'], true) && ($serial = $this->serial($v)) !== null) {
            $this->widths[$i] = max($this->widths[$i], $type === 'date' ? 12 : 17);

            return '<c r="'.$ref.'" s="'.($type === 'date' ? 3 : 6).'"><v>'.$serial.'</v></c>';
        }
        $s = (string) $v;
        $this->widths[$i] = max($this->widths[$i], min(60, mb_strlen($s) + 2));

        return '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$this->esc($s).'</t></is></c>';
    }

    /** Tanggal -> nomor seri Excel (hari sejak 1899-12-30). */
    private function serial(mixed $v): ?float
    {
        try {
            $d = $v instanceof DateTimeInterface ? $v : new \DateTimeImmutable((string) $v);
        } catch (\Throwable) {
            return null;
        }
        $base = new \DateTimeImmutable('1899-12-30 00:00:00', $d->getTimezone());

        return round(($d->getTimestamp() - $base->getTimestamp() + $d->getOffset() - $base->getOffset()) / 86400, 6);
    }

    private function col(int $i): string
    {
        $s = '';
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26).$s;
        }

        return $s;
    }

    private function esc(string $s): string
    {
        // buang karakter kontrol yang tidak sah di XML
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** ZIP minimal (deflate) sesuai spesifikasi PKWARE APPNOTE. */
    private function zip(array $files): string
    {
        $out = '';
        $central = '';
        $time = ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
        $date = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');

        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $comp = gzdeflate($data, 6);
            $offset = strlen($out);
            $header = pack('VvvvvvVVVvv', 0x04034B50, 20, 0x0800, 8, $time, $date, $crc, strlen($comp), strlen($data), strlen($name), 0);
            $out .= $header.$name.$comp;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0x0800, 8, $time, $date, $crc, strlen($comp), strlen($data), strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }

        return $out.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($central), strlen($out), 0);
    }
}
