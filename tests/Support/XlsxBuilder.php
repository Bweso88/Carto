<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Fabrique un petit classeur .xlsx pour les tests (textes partagés, comme Excel).
 */
final class XlsxBuilder
{
    /** @param list<list<string|int|null>> $rows */
    public static function make(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $strings = [];
        $sheet = '';
        foreach ($rows as $r => $row) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                if ($value === null) {
                    continue;
                }
                $ref = chr(65 + $c).($r + 1);
                if (is_int($value)) {
                    $sheet .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
                } else {
                    $index = array_search($value, $strings, true);
                    if ($index === false) {
                        $strings[] = $value;
                        $index = count($strings) - 1;
                    }
                    $sheet .= "<c r=\"{$ref}\" t=\"s\"><v>{$index}</v></c>";
                }
            }
            $sheet .= '</row>';
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sst = implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>', $strings));

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', "<?xml version=\"1.0\"?><workbook xmlns=\"{$ns}\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets><sheet name=\"Feuil1\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', "<?xml version=\"1.0\"?><worksheet xmlns=\"{$ns}\"><sheetData>{$sheet}</sheetData></worksheet>");
        $zip->addFromString('xl/sharedStrings.xml', "<?xml version=\"1.0\"?><sst xmlns=\"{$ns}\">{$sst}</sst>");
        $zip->close();

        return $path;
    }
}
