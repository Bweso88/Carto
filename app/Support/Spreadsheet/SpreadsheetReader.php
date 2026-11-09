<?php

namespace App\Support\Spreadsheet;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lecture simple de tableaux Excel (.xlsx) et CSV, sans dépendance externe.
 * Renvoie les lignes non vides de la première feuille, chaque cellule en texte (ou null).
 */
final class SpreadsheetReader
{
    /**
     * @param  string  $path  chemin du fichier
     * @param  string|null  $originalName  nom d'origine (sert à deviner le format d'un fichier téléversé)
     * @return list<list<string|null>>
     */
    public static function read(string $path, ?string $originalName = null): array
    {
        $extension = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            default => throw new RuntimeException('Format non pris en charge : utilisez un fichier Excel (.xlsx) ou CSV.'),
        };
    }

    /** @return list<list<string|null>> */
    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Fichier Excel illisible (le fichier est peut-être protégé ou au format .xls ancien).');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new RuntimeException('Aucune feuille trouvée dans le fichier Excel.');
            }
            $sheet = new SimpleXMLElement($sheetXml);
        } finally {
            $zip->close();
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $index = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? null,
                    'inlineStr' => self::richText($c->is),
                    'b' => ((string) $c->v) === '1' ? 'VRAI' : 'FAUX',
                    default => isset($c->v) ? (string) $c->v : null,
                };
                $cells[$index] = $value;
            }
            $rows[] = self::normalizeRow($cells);
        }

        return self::withoutEmptyRows($rows);
    }

    /** @return list<list<string|null>> */
    private static function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        // Fichiers enregistrés par Excel en « CSV (séparateur : point-virgule) » : souvent en Windows-1252
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($line = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = self::normalizeRow($line);
        }
        fclose($stream);

        return self::withoutEmptyRows($rows);
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $strings = [];
        foreach ((new SimpleXMLElement($xml))->si as $si) {
            $strings[] = self::richText($si);
        }

        return $strings;
    }

    /** Texte simple (<t>) ou enrichi (plusieurs <r><t>). */
    private static function richText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** Chemin de la première feuille selon workbook.xml (pas forcément sheet1.xml). */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }
        $wb = new SimpleXMLElement($workbook);
        $first = $wb->sheets->sheet[0] ?? null;
        $rid = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
        foreach ((new SimpleXMLElement($rels))->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return list<string|null>
     */
    private static function normalizeRow(array $cells): array
    {
        if ($cells === []) {
            return [];
        }
        $row = [];
        for ($i = 0, $max = max(array_keys($cells)); $i <= $max; $i++) {
            $v = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            $row[] = $v === '' ? null : $v;
        }

        return $row;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<list<string|null>>
     */
    private static function withoutEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $r) => array_filter($r, fn ($c) => $c !== null) !== []));
    }
}
