<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * A formatted .xlsx workbook without a library (Admin → Reports exports): several sheets, real dates and amounts
 * Excel can add up, a coloured header row that stays in view, a filter on the table, set column widths, a totals
 * row, and A4 landscape printing fitted to the page width. Needs PHP's zip extension.
 *
 * A cell is a plain value (text, int, float, date) or [value, style]; styles are the keys of self::STYLES.
 */
final class Xlsx
{
    /** Style name => index in cellXfs (see styles()). */
    public const STYLES = [
        'text' => 0, 'header' => 1, 'datetime' => 2, 'date' => 3, 'int' => 4, 'money' => 5, 'rate' => 6, 'percent' => 7,
        'total' => 8, 'total_int' => 9, 'total_money' => 10, 'title' => 11, 'subtitle' => 12, 'section' => 13,
        'number' => 14, 'total_percent' => 15, 'muted' => 16,
    ];

    /** @var list<array{name: string, widths: list<float>, rows: list<array{cells: array, height: ?float}>, table: ?array}> */
    private array $sheets = [];

    /**
     * @param  list<float|int>  $widths  column widths in characters
     * @param  bool  $table  the first row is a header: kept in view when scrolling, with a filter on every column
     */
    public function sheet(string $name, array $widths, bool $table = false): self
    {
        $this->sheets[] = ['name' => mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name), 0, 31), 'widths' => $widths, 'rows' => [], 'table' => $table ? [] : null];

        return $this;
    }

    /** Adds a row to the last sheet. */
    public function row(array $cells = [], ?float $height = null): self
    {
        $this->sheets[array_key_last($this->sheets)]['rows'][] = ['cells' => array_values($cells), 'height' => $height];

        return $this;
    }

    /** Writes the workbook; returns the path of a temporary .xlsx file (delete it after sending). */
    public function save(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is missing.');
        }

        // In the app's storage, not the system temp folder (not writable under some servers)
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'report-'.bin2hex(random_bytes(8)).'.xlsx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $types = '';
        $rels = '';
        $sheets = '';
        $names = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $types .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $sheets .= '<sheet name="'.self::esc($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            if ($sheet['table'] !== null && $sheet['rows']) {
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">'.self::esc("'".$sheet['name']."'!".self::filterRange($sheet, true)).'</definedName>';
            }
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $this->worksheet($sheet));
        }
        $styles = count($this->sheets) + 1;

        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$types.'</Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $head.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'.($names ? '<definedNames>'.$names.'</definedNames>' : '').'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels.'<Relationship Id="rId'.$styles.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->close();

        return $path;
    }

    private function worksheet(array $sheet): string
    {
        $table = $sheet['table'] !== null;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0"'.($table ? '' : ' showGridLines="0"').'>'
            .($table ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            .'</sheetView></sheetViews><cols>';
        foreach ($sheet['widths'] as $i => $width) {
            $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        foreach ($sheet['rows'] as $r => $row) {
            $height = $row['height'] ?? ($table && $r === 0 ? 24 : null);
            $xml .= '<row r="'.($r + 1).'"'.($height ? ' ht="'.$height.'" customHeight="1"' : '').'>';
            foreach ($row['cells'] as $c => $cell) {
                [$value, $style] = is_array($cell) ? $cell + [null, null] : [$cell, null];
                $style ??= match (true) {
                    $table && $r === 0 => 'header',
                    $value instanceof DateTimeInterface => 'datetime',
                    is_int($value) => 'int',
                    is_float($value) => 'number',
                    default => 'text',
                };
                $s = self::STYLES[$style] ?? 0;
                $ref = self::column($c).($r + 1);

                if ($value === null || $value === '') {
                    // Empty cells only matter when they carry a fill (header, totals row)
                    $xml .= $s ? '<c r="'.$ref.'" s="'.$s.'"/>' : '';
                } elseif ($value instanceof DateTimeInterface) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.self::serial($value).'</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.(is_float($value) ? self::number($value) : $value).'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($table && $sheet['rows']) {
            $xml .= '<autoFilter ref="'.self::filterRange($sheet).'"/>';
        }

        return $xml.'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    /** The table's header and data rows (not a totals row styled "total…" at the end). */
    private static function filterRange(array $sheet, bool $absolute = false): string
    {
        $rows = $sheet['rows'];
        $last = count($rows);
        $lastCells = $rows[$last - 1]['cells'] ?? [];
        $first = $lastCells[0] ?? null;
        if ($last > 1 && is_array($first) && str_starts_with((string) ($first[1] ?? ''), 'total')) {
            $last--;
        }
        $end = self::column(max(0, count($rows[0]['cells']) - 1));

        return $absolute ? '$A$1:$'.$end.'$'.$last : 'A1:'.$end.$last;
    }

    private static function styles(): string
    {
        $xf = fn (int $numFmt, int $font = 0, int $fill = 0, int $border = 0, string $align = '') => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '')
            .($align ? ' applyAlignment="1"><alignment '.$align.'/></xf>' : '/>');
        $middle = 'vertical="center"';

        // Same order as self::STYLES
        $cellXfs = [
            $xf(0, 0, 0, 0, $middle),                    // text
            $xf(0, 1, 2, 0, 'horizontal="center" '.$middle.' wrapText="1"'), // header: white bold on ocean
            $xf(164, 0, 0, 0, 'horizontal="left" '.$middle), // datetime
            $xf(165, 0, 0, 0, 'horizontal="left" '.$middle), // date
            $xf(3, 0, 0, 0, $middle),                    // int 1,234
            $xf(166, 0, 0, 0, $middle),                  // money $1,234.00
            $xf(167, 0, 0, 0, $middle),                  // rate 50.25
            $xf(168, 0, 0, 0, $middle),                  // percent 12.5%
            $xf(0, 2, 3, 1, $middle),                    // total (text)
            $xf(3, 2, 3, 1, $middle),                    // total int
            $xf(166, 2, 3, 1, $middle),                  // total money
            $xf(0, 3),                                   // title
            $xf(0, 4),                                   // subtitle
            $xf(0, 5, 0, 2),                             // section heading, line under it
            $xf(169, 0, 0, 0, $middle),                  // number 1,234.00
            $xf(168, 2, 3, 1, $middle),                  // total percent
            $xf(0, 4, 0, 0, 'horizontal="right" '.$middle), // muted, right-aligned ("—" where there's no figure)
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="6">'
            .'<numFmt numFmtId="164" formatCode="d mmm yyyy hh:mm"/><numFmt numFmtId="165" formatCode="d mmm yyyy"/>'
            .'<numFmt numFmtId="166" formatCode="&quot;$&quot;#,##0.00;[Red]&quot;-$&quot;#,##0.00"/><numFmt numFmtId="167" formatCode="#,##0.00##"/>'
            .'<numFmt numFmtId="168" formatCode="0.0%"/><numFmt numFmtId="169" formatCode="#,##0.00"/>'
            .'</numFmts>'
            .'<fonts count="6">'
            .'<font><sz val="11"/><color rgb="FF0F2A44"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF0F2A44"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="18"/><color rgb="FF0F3A64"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><sz val="10"/><color rgb="FF5B6B7F"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF155C9E"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF124A80"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF6FC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top style="thin"><color rgb="FF124A80"/></top><bottom style="thin"><color rgb="FF124A80"/></bottom><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="medium"><color rgb="FFB3D5F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellXfs).'">'.implode('', $cellXfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /** Excel's date number (days since 1900) for the local date and time shown in the app. */
    private static function serial(DateTimeInterface $d): string
    {
        return self::number(($d->getTimestamp() + $d->getOffset()) / 86400 + 25569);
    }

    private static function number(float $n): string
    {
        return rtrim(rtrim(sprintf('%.10F', $n), '0'), '.') ?: '0';
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function column(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $s): string
    {
        // Control characters are not allowed in XML
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
