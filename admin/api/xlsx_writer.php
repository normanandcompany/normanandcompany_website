<?php

declare(strict_types=1);

function reportXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function reportColumnLetter(int $column): string
{
    $letter = '';

    while ($column > 0) {
        $column--;
        $letter = chr(65 + ($column % 26)) . $letter;
        $column = intdiv($column, 26);
    }

    return $letter;
}

function reportHeaderLabel(string $column): string
{
    return ucwords(str_replace('_', ' ', $column));
}

function reportCellKind(string $column, mixed $value): string
{
    if ($value === null || $value === '') {
        return 'text';
    }

    if (preg_match('/(^|_)(amount|price|cost|sales|profit|proceeds|tax|fee|shipping|returns?|discounts?)($|_)/', $column)) {
        return is_numeric($value) ? 'currency' : 'text';
    }

    if (str_ends_with($column, '_percentage')) {
        return is_numeric($value) ? 'decimal' : 'text';
    }

    if (preg_match('/(^id$|_id$|_count$|^count$|^quantity$|_views$|^views$|_bytes$|^requests$|^visits$|_events$)/', $column)) {
        return is_numeric($value) ? 'integer' : 'text';
    }

    return 'text';
}

function reportInlineCell(string $reference, string $value, int $style = 0): string
{
    return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
        . reportXml($value) . '</t></is></c>';
}

function buildReportWorkbook(string $title, string $description, array $rows, array $columns = []): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The server XLSX extension is unavailable.');
    }

    $columns = $columns !== [] ? $columns : ($rows === [] ? ['message'] : array_keys($rows[0]));
    $isEmpty = $rows === [];

    $columnCount = count($columns);
    $lastColumn = reportColumnLetter($columnCount);
    $mergeEmptyMessage = $isEmpty && $columnCount > 1;
    $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('Y-m-d H:i:s T');
    $sheetRows = [];
    $sheetRows[] = '<row r="1" ht="28" customHeight="1">' . reportInlineCell('A1', $title, 1) . '</row>';
    $sheetRows[] = '<row r="2" ht="30" customHeight="1">' . reportInlineCell('A2', $description, 2) . '</row>';
    $sheetRows[] = '<row r="3">' . reportInlineCell('A3', 'Generated ' . $generatedAt, 2) . '</row>';

    $headerCells = [];
    foreach ($columns as $index => $column) {
        $headerCells[] = reportInlineCell(reportColumnLetter($index + 1) . '5', reportHeaderLabel($column), 6);
    }
    $sheetRows[] = '<row r="5" ht="24" customHeight="1">' . implode('', $headerCells) . '</row>';

    if ($isEmpty) {
        $sheetRows[] = '<row r="6">' . reportInlineCell('A6', 'No records were available when this report was generated.', 2) . '</row>';
    }

    $widths = array_map(static fn(string $column): int => max(12, mb_strlen(reportHeaderLabel($column)) + 2), $columns);

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 6;
        $cells = [];

        foreach ($columns as $columnIndex => $column) {
            $value = $row[$column] ?? null;
            $textValue = $value === null ? '' : (string) $value;
            $widths[$columnIndex] = min(48, max($widths[$columnIndex], min(48, mb_strlen($textValue) + 2)));
            $reference = reportColumnLetter($columnIndex + 1) . $excelRow;
            $kind = reportCellKind($column, $value);

            if ($kind === 'integer') {
                $cells[] = '<c r="' . $reference . '" s="3"><v>' . (string) ((int) $value) . '</v></c>';
            } elseif ($kind === 'decimal') {
                $cells[] = '<c r="' . $reference . '" s="4"><v>' . (string) ((float) $value) . '</v></c>';
            } elseif ($kind === 'currency') {
                $cells[] = '<c r="' . $reference . '" s="5"><v>' . (string) ((float) $value) . '</v></c>';
            } else {
                $cells[] = reportInlineCell($reference, $textValue, 0);
            }
        }

        $sheetRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
    }

    $columnXml = [];
    foreach ($widths as $index => $width) {
        $columnXml[] = '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $width . '" customWidth="1"/>';
    }

    $lastRow = max(6, count($rows) + 5);
    $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/><cols>' . implode('', $columnXml) . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<autoFilter ref="A5:' . $lastColumn . $lastRow . '"/>'
        . '<mergeCells count="' . ($mergeEmptyMessage ? '4' : '3') . '"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/><mergeCell ref="A3:' . $lastColumn . '3"/>'
        . ($mergeEmptyMessage ? '<mergeCell ref="A6:' . $lastColumn . '6"/>' : '') . '</mergeCells>'
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="#,##0.00"/><numFmt numFmtId="166" formatCode="$#,##0.00"/></numFmts>'
        . '<fonts count="3"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
        . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF12355B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF167D9A"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border/><border><bottom style="thin"><color rgb="FFD9E2E8"/></bottom></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $temporaryFile = tempnam(sys_get_temp_dir(), 'norman-report-');
    if ($temporaryFile === false) {
        throw new RuntimeException('A temporary report file could not be created.');
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($temporaryFile);
        throw new RuntimeException('The report workbook could not be created.');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . reportXml($title) . '</dc:title><dc:creator>Norman and Company</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Norman and Company Reports</Application></Properties>');
    $zip->close();

    $contents = file_get_contents($temporaryFile);
    @unlink($temporaryFile);

    if ($contents === false) {
        throw new RuntimeException('The completed report workbook could not be read.');
    }

    return $contents;
}
