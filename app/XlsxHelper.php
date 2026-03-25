<?php

declare(strict_types=1);

/**
 * Read rows from first sheet of an XLSX file.
 * Returns a list of row arrays with cell values as strings.
 */
function readXlsxRows(string $xlsxPath, int $maxRows = 5000): array
{
    if (!is_file($xlsxPath)) {
        throw new RuntimeException('XLSX file not found.');
    }

    if (!class_exists('PharData')) {
        throw new RuntimeException('XLSX support is unavailable: PharData extension is missing.');
    }

    $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pos_xlsx_' . bin2hex(random_bytes(8));
    if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('Could not prepare temporary folder for XLSX parsing.');
    }

    try {
        $archive = new PharData($xlsxPath);
        $archive->extractTo($tmpDir, null, true);

        $sharedStrings = loadSharedStrings($tmpDir);
        $sheetPath = resolveFirstSheetPath($tmpDir);
        if (!is_file($sheetPath)) {
            throw new RuntimeException('XLSX worksheet not found.');
        }

        $sheetXml = file_get_contents($sheetPath);
        if ($sheetXml === false) {
            throw new RuntimeException('Could not read XLSX worksheet.');
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new RuntimeException('Invalid worksheet XML.');
        }

        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $xml->xpath('//a:sheetData/a:row') ?: [];

        $rows = [];
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        foreach ($rowNodes as $rowNode) {
            if (count($rows) >= $maxRows + 1) {
                throw new RuntimeException('XLSX exceeds the maximum of 5000 data rows. Please split the file and try again.');
            }

            $cellNodes = $rowNode->children($mainNs)->c;
            $indexedCells = [];
            $maxCol = 0;

            foreach ($cellNodes as $cellNode) {
                $cellAttributes = $cellNode->attributes();
                $ref = (string) ($cellAttributes['r'] ?? '');
                $colRef = preg_replace('/\d+/', '', $ref) ?: 'A';
                $colIndex = excelColumnToIndex($colRef);
                $maxCol = max($maxCol, $colIndex);
                $indexedCells[$colIndex] = extractCellValue($cellNode, $sharedStrings);
            }

            if ($maxCol === 0 && empty($indexedCells)) {
                $rows[] = [];
                continue;
            }

            $row = [];
            for ($i = 1; $i <= $maxCol; $i++) {
                $row[] = $indexedCells[$i] ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    } finally {
        deleteDirectoryRecursive($tmpDir);
    }
}

/**
 * Build an XLSX file from tabular rows and write to output path.
 */
function writeXlsxFile(string $outputPath, string $sheetName, array $rows): void
{
    if (!class_exists('PharData')) {
        throw new RuntimeException('XLSX export is unavailable: PharData extension is missing.');
    }

    $sheetName = trim($sheetName);
    if ($sheetName === '') {
        $sheetName = 'Sheet1';
    }

    $sharedStrings = [];
    $sharedStringMap = [];
    $sheetRowsXml = [];

    foreach ($rows as $rowIndex => $rowValues) {
        if (!is_array($rowValues)) {
            continue;
        }

        $cellsXml = [];
        foreach (array_values($rowValues) as $colIndex0 => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            if (!array_key_exists($value, $sharedStringMap)) {
                $sharedStringMap[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            $ref = excelIndexToColumn($colIndex0 + 1) . (string) ($rowIndex + 1);
            $ssIndex = (string) $sharedStringMap[$value];
            $cellsXml[] = '<c r="' . $ref . '" t="s"><v>' . $ssIndex . '</v></c>';
        }

        $sheetRowsXml[] = '<row r="' . (string) ($rowIndex + 1) . '">' . implode('', $cellsXml) . '</row>';
    }

    $sheetDataXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $sheetRowsXml) . '</sheetData>'
        . '</worksheet>';

    $sharedItemsXml = '';
    foreach ($sharedStrings as $string) {
        $sharedItemsXml .= '<si><t>' . xmlEscape($string) . '</t></si>';
    }

    $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
        . $sharedItemsXml
        . '</sst>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="1"><xf/></cellXfs>'
        . '</styleSheet>';

    if (is_file($outputPath)) {
        unlink($outputPath);
    }

    $archive = new PharData($outputPath);
    $archive->addFromString('[Content_Types].xml', $contentTypesXml);
    $archive->addFromString('_rels/.rels', $relsXml);
    $archive->addFromString('xl/workbook.xml', $workbookXml);
    $archive->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $archive->addFromString('xl/styles.xml', $stylesXml);
    $archive->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $archive->addFromString('xl/worksheets/sheet1.xml', $sheetDataXml);
}

function loadSharedStrings(string $extractRoot): array
{
    $path = $extractRoot . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
    if (!is_file($path)) {
        return [];
    }

    $xml = simplexml_load_string((string) file_get_contents($path));
    if ($xml === false) {
        return [];
    }

    $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $nodes = $xml->children($mainNs)->si;

    $strings = [];
    foreach ($nodes as $si) {
        $buffer = (string) ($si->t ?? '');
        if ($buffer === '' && isset($si->r)) {
            foreach ($si->r as $run) {
                $buffer .= (string) ($run->t ?? '');
            }
        }
        $strings[] = $buffer;
    }

    return $strings;
}

function resolveFirstSheetPath(string $extractRoot): string
{
    $default = $extractRoot . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
    if (is_file($default)) {
        return $default;
    }

    $workbookPath = $extractRoot . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'workbook.xml';
    $workbookRelsPath = $extractRoot . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . '_rels' . DIRECTORY_SEPARATOR . 'workbook.xml.rels';

    if (!is_file($workbookPath) || !is_file($workbookRelsPath)) {
        return $default;
    }

    $workbookXml = simplexml_load_string((string) file_get_contents($workbookPath));
    $relsXml = simplexml_load_string((string) file_get_contents($workbookRelsPath));

    if ($workbookXml === false || $relsXml === false) {
        return $default;
    }

    $workbookXml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbookXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $sheetNodes = $workbookXml->xpath('//a:sheets/a:sheet');
    $rid = $sheetNodes && isset($sheetNodes[0]['r:id']) ? (string) $sheetNodes[0]['r:id'] : '';
    if ($rid === '') {
        return $default;
    }

    $relsXml->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $relNodes = $relsXml->xpath('//p:Relationship[@Id="' . $rid . '"]');
    if (!$relNodes || !isset($relNodes[0]['Target'])) {
        return $default;
    }

    $target = str_replace('/', DIRECTORY_SEPARATOR, (string) $relNodes[0]['Target']);
    $candidate = $extractRoot . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . ltrim($target, '\\/');

    return is_file($candidate) ? $candidate : $default;
}

function extractCellValue(SimpleXMLElement $cellNode, array $sharedStrings): string
{
    $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $cellAttributes = $cellNode->attributes();
    $type = (string) ($cellAttributes['t'] ?? '');
    $children = $cellNode->children($mainNs);

    if ($type === 'inlineStr') {
        return (string) ($children->is->t ?? '');
    }

    $rawValue = (string) ($children->v ?? '');

    if ($type === 's') {
        $index = (int) $rawValue;
        return $sharedStrings[$index] ?? '';
    }

    return $rawValue;
}

function excelColumnToIndex(string $column): int
{
    $column = strtoupper($column);
    $value = 0;

    for ($i = 0; $i < strlen($column); $i++) {
        $value = ($value * 26) + (ord($column[$i]) - 64);
    }

    return max(1, $value);
}

function excelIndexToColumn(int $index): string
{
    $index = max(1, $index);
    $result = '';

    while ($index > 0) {
        $index--;
        $result = chr(65 + ($index % 26)) . $result;
        $index = intdiv($index, 26);
    }

    return $result;
}

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function deleteDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($dir);
}
