<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Reports;

use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class XlsxWriter
{
    public function write(array $worksheets): string
    {
        if (! class_exists('ZipArchive')) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_UNAVAILABLE, __('Excel export requires the PHP Zip extension.', 'coffeepos'));
        }

        $temporary = function_exists('wp_tempnam') ? wp_tempnam('coffeepos-report.xlsx') : tempnam(sys_get_temp_dir(), 'coffeepos-report-');
        if (! is_string($temporary) || $temporary === '') {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Could not create the Excel export.', 'coffeepos'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Could not open the Excel export package.', 'coffeepos'));
        }

        $names = array_keys($worksheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($names)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($names)));
        $zip->addFromString('xl/styles.xml', $this->styles());

        $index = 1;
        foreach ($worksheets as $rows) {
            $zip->addFromString('xl/worksheets/sheet' . $index . '.xml', $this->worksheet((array) $rows));
            $index++;
        }
        $zip->close();

        $content = file_get_contents($temporary);
        @unlink($temporary);
        if (! is_string($content) || $content === '') {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Could not finalize the Excel export.', 'coffeepos'));
        }
        return $content;
    }

    private function contentTypes(int $count): string
    {
        $sheets = '';
        for ($i = 1; $i <= $count; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $sheets . '</Types>';
    }

    private function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $index => $name) {
            $sheets .= '<sheet name="' . $this->xml((string) $name) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>';
    }

    private function workbookRelationships(int $count): string
    {
        $relationships = '';
        for ($i = 1; $i <= $count; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $relationships .= '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="' . $number . '">';
            foreach (array_values((array) $row) as $columnIndex => $cell) {
                $reference = $this->column($columnIndex + 1) . $number;
                $isHeader = $rowIndex === 0;
                if (is_array($cell) && ($cell['type'] ?? '') === 'number') {
                    $xml .= '<c r="' . $reference . '" s="2" t="n"><v>' . $this->xml((string) ($cell['value'] ?? '0')) . '</v></c>';
                } elseif (is_int($cell) || is_float($cell)) {
                    $xml .= '<c r="' . $reference . '" t="n"><v>' . $this->xml((string) $cell) . '</v></c>';
                } else {
                    $style = $isHeader ? ' s="1"' : '';
                    $xml .= '<c r="' . $reference . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $this->xml((string) $cell) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private function column(int $index): string
    {
        $column = '';
        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)) . $column;
            $index = intdiv($index, 26);
        }
        return $column;
    }

    private function xml(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
